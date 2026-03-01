<?php

namespace App\Notifications;

use App\Models\Finding;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a finding's due date is approaching (3 days before).
 *
 * This notification alerts the assignee that their assigned finding
 * has a due date coming up soon, prompting timely action.
 */
class FindingDueDateApproachingNotification extends Notification implements ShouldQueue
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
        $auditName = $finding->audit?->name ?? 'N/A';
        $datacenterName = $finding->audit?->datacenter?->name
            ?? $finding->datacenter?->name
            ?? 'Unknown Datacenter';
        $viewUrl = url("/findings/{$finding->id}");
        $dueDate = $finding->due_date?->format('F j, Y') ?? 'Unknown';

        return (new MailMessage)
            ->subject("Finding Due Soon: {$finding->title}")
            ->greeting('A finding is due soon!')
            ->line("The finding \"{$finding->title}\" is due on {$dueDate}.")
            ->line("**Title:** {$finding->title}")
            ->when($finding->audit, fn ($message) => $message->line("**Audit:** {$auditName}"))
            ->line("**Datacenter:** {$datacenterName}")
            ->line("**Due Date:** {$dueDate}")
            ->action('View Finding', $viewUrl)
            ->line('Please take action before the due date.');
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $finding = $this->finding;
        $dueDate = $finding->due_date;

        return [
            'type' => 'finding_due_date_approaching',
            'finding_id' => $finding->id,
            'title' => $finding->title,
            'audit_id' => $finding->audit_id,
            'audit_name' => $finding->audit?->name,
            'datacenter_id' => $finding->audit?->datacenter_id ?? $finding->datacenter_id,
            'datacenter_name' => $finding->audit?->datacenter?->name ?? $finding->datacenter?->name,
            'due_date' => $dueDate?->format('Y-m-d'),
            'message' => "Finding \"{$finding->title}\" due date is approaching ({$dueDate?->format('M j, Y')})",
        ];
    }
}
