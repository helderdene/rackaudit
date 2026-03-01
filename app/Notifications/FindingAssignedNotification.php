<?php

namespace App\Notifications;

use App\Models\Finding;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a finding is assigned to a user.
 *
 * This notification alerts users when a finding has been assigned to them,
 * including relevant details about the finding and a link to view it.
 */
class FindingAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Finding $finding
    ) {}

    /**
     * Get the notification's delivery channels.
     * Database is always included. Mail is only included if properly configured
     * and the user has email enabled for finding_updates category.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // Only include mail if configured with a real mail driver
        $mailDriver = config('mail.default');
        if ($mailDriver && ! in_array($mailDriver, ['log', 'array'])) {
            // Check user preference for finding_updates category
            if ($notifiable instanceof User && $notifiable->hasEmailEnabledFor(User::NOTIFICATION_CATEGORY_FINDING_UPDATES)) {
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
        $finding = $this->finding;
        $auditName = $finding->audit?->name ?? 'Unknown Audit';
        $datacenterName = $finding->audit?->datacenter?->name ?? 'Unknown Datacenter';
        $viewUrl = url("/findings/{$finding->id}");

        return (new MailMessage)
            ->subject("Finding Assigned: {$finding->title}")
            ->greeting('A finding has been assigned to you!')
            ->line("**Title:** {$finding->title}")
            ->line("**Audit:** {$auditName}")
            ->line("**Datacenter:** {$datacenterName}")
            ->line("**Severity:** {$finding->severity->label()}")
            ->line("**Status:** {$finding->status->label()}")
            ->when($finding->due_date, function ($message) use ($finding) {
                return $message->line("**Due Date:** {$finding->due_date->format('Y-m-d')}");
            })
            ->action('View Finding', $viewUrl)
            ->line('Please review and take appropriate action.');
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $finding = $this->finding;

        return [
            'type' => 'finding_assigned',
            'finding_id' => $finding->id,
            'title' => $finding->title,
            'audit_id' => $finding->audit_id,
            'audit_name' => $finding->audit?->name,
            'datacenter_id' => $finding->audit?->datacenter_id,
            'datacenter_name' => $finding->audit?->datacenter?->name,
            'severity' => $finding->severity->value,
            'status' => $finding->status->value,
            'due_date' => $finding->due_date?->format('Y-m-d'),
            'message' => "You have been assigned to finding: {$finding->title}",
        ];
    }
}
