<?php

namespace App\Notifications;

use App\Models\Discrepancy;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a new discrepancy is detected.
 *
 * This notification alerts users (IT Managers, subscribed Operators) about
 * discrepancies between expected and actual connections in datacenters.
 */
class NewDiscrepancyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Discrepancy $discrepancy
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
        $datacenterName = $this->discrepancy->datacenter->name;
        $discrepancyType = $this->discrepancy->discrepancy_type->label();
        $title = $this->discrepancy->title;

        $sourceDevice = $this->discrepancy->sourcePort?->device?->name ?? 'Unknown';
        $sourcePort = $this->discrepancy->sourcePort?->name ?? 'Unknown';
        $destDevice = $this->discrepancy->destPort?->device?->name ?? 'Unknown';
        $destPort = $this->discrepancy->destPort?->name ?? 'Unknown';

        $viewUrl = url('/discrepancies');

        return (new MailMessage)
            ->subject("New Discrepancy Detected: {$title}")
            ->greeting('Discrepancy Alert!')
            ->line("A new {$discrepancyType} discrepancy has been detected in {$datacenterName}.")
            ->line("**Title:** {$title}")
            ->line("**Type:** {$discrepancyType}")
            ->line("**Source:** {$sourceDevice} / {$sourcePort}")
            ->line("**Destination:** {$destDevice} / {$destPort}")
            ->action('View Discrepancies', $viewUrl)
            ->line('Please review and take appropriate action.');
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_discrepancy',
            'discrepancy_id' => $this->discrepancy->id,
            'discrepancy_type' => $this->discrepancy->discrepancy_type->value,
            'title' => $this->discrepancy->title,
            'datacenter_id' => $this->discrepancy->datacenter_id,
            'datacenter_name' => $this->discrepancy->datacenter->name,
            'source_port_id' => $this->discrepancy->source_port_id,
            'dest_port_id' => $this->discrepancy->dest_port_id,
            'source_device' => $this->discrepancy->sourcePort?->device?->name,
            'source_port' => $this->discrepancy->sourcePort?->name,
            'dest_device' => $this->discrepancy->destPort?->device?->name,
            'dest_port' => $this->discrepancy->destPort?->name,
            'message' => "New {$this->discrepancy->discrepancy_type->label()} discrepancy detected: {$this->discrepancy->title}",
        ];
    }
}
