<?php

namespace App\Notifications;

use App\Models\ImplementationFile;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to IT Managers and Administrators when a new implementation file
 * is uploaded and requires approval.
 */
class ImplementationFileAwaitingApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public ImplementationFile $implementationFile
    ) {}

    /**
     * Get the notification's delivery channels.
     * Database is always included. Mail is only included if properly configured
     * and the user has email enabled for approval_requests category.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // Only include mail if configured with a real mail driver
        $mailDriver = config('mail.default');
        if ($mailDriver && ! in_array($mailDriver, ['log', 'array'])) {
            // Check user preference for approval_requests category
            if ($notifiable instanceof User && $notifiable->hasEmailEnabledFor(User::NOTIFICATION_CATEGORY_APPROVAL_REQUESTS)) {
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
        $datacenterName = $this->implementationFile->datacenter->name;
        $uploaderName = $this->implementationFile->uploader->name;
        $fileName = $this->implementationFile->original_name;

        $viewUrl = route('datacenters.implementation-files.index', [
            'datacenter' => $this->implementationFile->datacenter_id,
        ]);

        return (new MailMessage)
            ->subject("Implementation File Awaiting Approval: {$fileName}")
            ->greeting('Hello!')
            ->line("A new implementation file has been uploaded and requires your approval.")
            ->line("**File:** {$fileName}")
            ->line("**Datacenter:** {$datacenterName}")
            ->line("**Uploaded by:** {$uploaderName}")
            ->action('View Implementation Files', $viewUrl)
            ->line('Please review and approve this file if it meets the required standards.');
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'implementation_file_awaiting_approval',
            'implementation_file_id' => $this->implementationFile->id,
            'file_name' => $this->implementationFile->original_name,
            'datacenter_id' => $this->implementationFile->datacenter_id,
            'datacenter_name' => $this->implementationFile->datacenter->name,
            'uploader_id' => $this->implementationFile->uploaded_by,
            'uploader_name' => $this->implementationFile->uploader->name,
            'message' => "New implementation file '{$this->implementationFile->original_name}' awaiting approval in {$this->implementationFile->datacenter->name}",
        ];
    }
}
