<?php

namespace App\Notifications;

use App\Models\Finding;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to the previous assignee when a finding is reassigned.
 *
 * This notification alerts users when a finding has been reassigned away from them
 * to another user, including details about the new assignee.
 */
class FindingReassignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Finding $finding,
        public User $newAssignee
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
        $viewUrl = url("/findings/{$finding->id}");

        return (new MailMessage)
            ->subject("Finding Reassigned: {$finding->title}")
            ->greeting('A finding has been reassigned!')
            ->line("The finding \"{$finding->title}\" has been reassigned to {$this->newAssignee->name}.")
            ->line("**Title:** {$finding->title}")
            ->line("**New Assignee:** {$this->newAssignee->name}")
            ->action('View Finding', $viewUrl)
            ->line('No further action is required from you on this finding.');
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
            'type' => 'finding_reassigned',
            'finding_id' => $finding->id,
            'title' => $finding->title,
            'new_assignee_id' => $this->newAssignee->id,
            'new_assignee_name' => $this->newAssignee->name,
            'message' => "Finding \"{$finding->title}\" has been reassigned to {$this->newAssignee->name}",
        ];
    }
}
