<?php

namespace App\Jobs;

use App\Models\Discrepancy;
use App\Models\User;
use App\Notifications\DiscrepancyThresholdNotification;
use App\Notifications\NewDiscrepancyNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job for notifying users about newly detected discrepancies.
 *
 * This job queries users by role and datacenter access, applies notification
 * preference filtering, and sends appropriate notifications:
 * - IT Managers with 'all' preference receive individual notifications for each discrepancy
 * - Auditors with 'threshold_only' receive notifications only when threshold is exceeded
 * - Operators receive notifications only if explicitly subscribed (preference = 'all')
 */
class NotifyUsersOfDiscrepancies implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     *
     * @param  Collection<int, Discrepancy>  $discrepancies  Collection of newly detected discrepancies
     * @param  int|null  $datacenterId  Optional datacenter ID to scope notifications
     */
    public function __construct(
        public Collection $discrepancies,
        public ?int $datacenterId = null
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->discrepancies->isEmpty()) {
            Log::info('NotifyUsersOfDiscrepancies: No discrepancies to notify about');

            return;
        }

        Log::info('NotifyUsersOfDiscrepancies: Starting notification process', [
            'discrepancy_count' => $this->discrepancies->count(),
            'datacenter_id' => $this->datacenterId,
        ]);

        // Get threshold from config
        $threshold = config('discrepancies.notifications.threshold', 10);
        $thresholdExceeded = $this->discrepancies->count() >= $threshold;

        // Get users who should be notified
        $usersToNotify = $this->getUsersToNotify();

        Log::info('NotifyUsersOfDiscrepancies: Found users to notify', [
            'user_count' => $usersToNotify->count(),
            'threshold_exceeded' => $thresholdExceeded,
        ]);

        foreach ($usersToNotify as $user) {
            $this->notifyUser($user, $thresholdExceeded);
        }

        Log::info('NotifyUsersOfDiscrepancies: Notification process completed');
    }

    /**
     * Get users who should be notified about discrepancies.
     *
     * @return Collection<int, User>
     */
    protected function getUsersToNotify(): Collection
    {
        $query = User::query()
            ->where('status', 'active')
            ->where('discrepancy_notifications', '!=', 'none');

        // If datacenter is specified, filter users with access to that datacenter
        if ($this->datacenterId !== null) {
            $query->whereHas('datacenters', function ($q) {
                $q->where('datacenters.id', $this->datacenterId);
            });
        }

        return $query->get();
    }

    /**
     * Notify a single user based on their preferences.
     */
    protected function notifyUser(User $user, bool $thresholdExceeded): void
    {
        // Users with 'all' preference receive individual notifications
        if ($user->wantsAllDiscrepancyNotifications()) {
            foreach ($this->discrepancies as $discrepancy) {
                $user->notify(new NewDiscrepancyNotification($discrepancy));
            }

            // Also send threshold notification if exceeded
            if ($thresholdExceeded) {
                $this->sendThresholdNotification($user);
            }

            return;
        }

        // Users with 'threshold_only' preference only get notifications when threshold exceeded
        if ($user->wantsThresholdOnlyDiscrepancyNotifications() && $thresholdExceeded) {
            $this->sendThresholdNotification($user);
        }
    }

    /**
     * Send a threshold notification to a user.
     */
    protected function sendThresholdNotification(User $user): void
    {
        $summary = $this->buildSummary();
        $user->notify(new DiscrepancyThresholdNotification($this->discrepancies, $summary));
    }

    /**
     * Build summary data for threshold notifications.
     *
     * @return array<string, mixed>
     */
    protected function buildSummary(): array
    {
        // Count discrepancies by type
        $byType = $this->discrepancies
            ->groupBy(fn ($d) => $d->discrepancy_type->value)
            ->map(fn ($group) => $group->count())
            ->toArray();

        // Get datacenter info
        $datacenter = null;
        $datacenterName = 'Multiple Datacenters';

        if ($this->datacenterId !== null) {
            $datacenter = $this->discrepancies->first()?->datacenter;
            $datacenterName = $datacenter?->name ?? 'Unknown Datacenter';
        }

        return [
            'total_count' => $this->discrepancies->count(),
            'by_type' => $byType,
            'datacenter_id' => $this->datacenterId,
            'datacenter_name' => $datacenterName,
        ];
    }
}
