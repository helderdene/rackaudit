<?php

namespace App\Jobs;

use App\Models\Finding;
use App\Models\User;
use App\Notifications\PersistentDiscrepancyFindingCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job for notifying users about auto-created findings from persistent discrepancies.
 *
 * Notifies active IT Managers with access to the relevant datacenter about
 * findings that were automatically promoted from persistent discrepancies.
 */
class NotifyUsersOfPersistentDiscrepancyFindings implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     *
     * @param  Collection<int, Finding>  $findings  Collection of newly created findings
     * @param  int|null  $datacenterId  Optional datacenter ID to scope notifications
     */
    public function __construct(
        public Collection $findings,
        public ?int $datacenterId = null
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->findings->isEmpty()) {
            Log::info('NotifyUsersOfPersistentDiscrepancyFindings: No findings to notify about');

            return;
        }

        Log::info('NotifyUsersOfPersistentDiscrepancyFindings: Starting notification process', [
            'findings_count' => $this->findings->count(),
            'datacenter_id' => $this->datacenterId,
        ]);

        $users = $this->getUsersToNotify();

        Log::info('NotifyUsersOfPersistentDiscrepancyFindings: Found users to notify', [
            'user_count' => $users->count(),
        ]);

        foreach ($users as $user) {
            foreach ($this->findings as $finding) {
                $user->notify(new PersistentDiscrepancyFindingCreatedNotification($finding));
            }
        }

        Log::info('NotifyUsersOfPersistentDiscrepancyFindings: Notification process completed');
    }

    /**
     * Get active IT Managers with access to the relevant datacenter.
     *
     * @return Collection<int, User>
     */
    protected function getUsersToNotify(): Collection
    {
        $query = User::query()
            ->where('status', 'active')
            ->role('IT Manager');

        if ($this->datacenterId !== null) {
            $query->whereHas('datacenters', function ($q) {
                $q->where('datacenters.id', $this->datacenterId);
            });
        }

        return $query->get();
    }
}
