<?php

namespace App\Notifications;

use App\Models\Finding;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a finding is auto-created from a persistent discrepancy.
 *
 * Alerts IT Managers that a discrepancy has been open long enough to warrant
 * automatic promotion to a finding, including severity, datacenter, and due date.
 */
class PersistentDiscrepancyFindingCreatedNotification extends Notification implements ShouldQueue
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
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        $mailDriver = config('mail.default');
        if ($mailDriver && ! in_array($mailDriver, ['log', 'array'])) {
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
        $datacenterName = $finding->datacenter?->name
            ?? $finding->audit?->datacenter?->name
            ?? 'Unknown Datacenter';
        $viewUrl = url("/findings/{$finding->id}");

        return (new MailMessage)
            ->subject("Auto-Finding Created: {$finding->title}")
            ->greeting('A finding has been automatically created!')
            ->line('A persistent discrepancy has been promoted to a finding.')
            ->line("**Title:** {$finding->title}")
            ->line("**Datacenter:** {$datacenterName}")
            ->line("**Severity:** {$finding->severity->label()}")
            ->when($finding->due_date, function ($message) use ($finding) {
                return $message->line("**Due Date:** {$finding->due_date->format('Y-m-d')}");
            })
            ->action('View Finding', $viewUrl)
            ->line('Please review and assign this finding for resolution.');
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
            'type' => 'persistent_discrepancy_finding_created',
            'finding_id' => $finding->id,
            'title' => $finding->title,
            'datacenter_id' => $finding->datacenter_id,
            'datacenter_name' => $finding->datacenter?->name,
            'severity' => $finding->severity->value,
            'status' => $finding->status->value,
            'due_date' => $finding->due_date?->format('Y-m-d'),
            'message' => "Auto-finding created: {$finding->title}",
        ];
    }
}
