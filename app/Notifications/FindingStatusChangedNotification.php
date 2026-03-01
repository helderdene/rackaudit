<?php

namespace App\Notifications;

use App\Enums\FindingStatus;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a finding's status changes.
 *
 * This notification alerts the assignee when the status of their assigned
 * finding has been changed, including the old and new status.
 */
class FindingStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Finding $finding,
        public FindingStatus $oldStatus,
        public FindingStatus $newStatus
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
            ->subject("Finding Status Changed: {$finding->title}")
            ->greeting('Finding status has been updated!')
            ->line("The finding \"{$finding->title}\" status has changed.")
            ->line("**From:** {$this->oldStatus->label()}")
            ->line("**To:** {$this->newStatus->label()}")
            ->action('View Finding', $viewUrl)
            ->line('Please review and take appropriate action if needed.');
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
            'type' => 'finding_status_changed',
            'finding_id' => $finding->id,
            'title' => $finding->title,
            'old_status' => $this->oldStatus->value,
            'old_status_label' => $this->oldStatus->label(),
            'new_status' => $this->newStatus->value,
            'new_status_label' => $this->newStatus->label(),
            'message' => "Finding \"{$finding->title}\" status changed from {$this->oldStatus->label()} to {$this->newStatus->label()}",
        ];
    }
}
