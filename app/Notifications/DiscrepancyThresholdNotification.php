<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Notification sent when the number of discrepancies exceeds a configurable threshold.
 *
 * This notification alerts users (IT Managers, Auditors) when a significant number
 * of discrepancies are detected, indicating potential systemic issues.
 */
class DiscrepancyThresholdNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  Collection<int, \App\Models\Discrepancy>  $discrepancies  Collection of discrepancies
     * @param  array<string, mixed>  $summary  Summary data including counts by type and datacenter
     */
    public function __construct(
        public Collection $discrepancies,
        public array $summary
    ) {}

    /**
     * Get the notification's delivery channels.
     * Database is always included. Mail is only included if properly configured
     * and the user has email enabled for discrepancies category.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // Only include mail if configured with a real mail driver
        $mailDriver = config('mail.default');
        if ($mailDriver && ! in_array($mailDriver, ['log', 'array'])) {
            // Check user preference for discrepancies category
            if ($notifiable instanceof User && $notifiable->hasEmailEnabledFor(User::NOTIFICATION_CATEGORY_DISCREPANCIES)) {
                $channels[] = 'mail';
            }
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $totalCount = $this->summary['total_count'] ?? $this->discrepancies->count();
        $threshold = config('discrepancies.notifications.threshold', 10);
        $datacenterName = $this->summary['datacenter_name'] ?? 'Multiple Datacenters';

        $mailMessage = (new MailMessage)
            ->subject("Discrepancy Threshold Exceeded: {$totalCount} discrepancies detected")
            ->greeting('Threshold Alert!')
            ->line("The discrepancy count ({$totalCount}) has exceeded the configured threshold ({$threshold}) for {$datacenterName}.")
            ->line('**Summary by Type:**');

        // Add breakdown by type
        if (isset($this->summary['by_type']) && is_array($this->summary['by_type'])) {
            foreach ($this->summary['by_type'] as $type => $count) {
                $mailMessage->line('- '.ucfirst($type).": {$count}");
            }
        }

        $viewUrl = url('/discrepancies');

        return $mailMessage
            ->action('View All Discrepancies', $viewUrl)
            ->line('Please review and prioritize resolution of these issues.');
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $totalCount = $this->summary['total_count'] ?? $this->discrepancies->count();
        $threshold = config('discrepancies.notifications.threshold', 10);

        return [
            'type' => 'discrepancy_threshold_exceeded',
            'total_count' => $totalCount,
            'threshold' => $threshold,
            'datacenter_id' => $this->summary['datacenter_id'] ?? null,
            'datacenter_name' => $this->summary['datacenter_name'] ?? 'Multiple Datacenters',
            'by_type' => $this->summary['by_type'] ?? [],
            'discrepancy_ids' => $this->discrepancies->pluck('id')->toArray(),
            'message' => "Discrepancy threshold exceeded: {$totalCount} discrepancies detected (threshold: {$threshold})",
        ];
    }
}
