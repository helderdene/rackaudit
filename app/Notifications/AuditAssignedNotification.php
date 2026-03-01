<?php

namespace App\Notifications;

use App\Models\Audit;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a user is assigned to an audit.
 *
 * This notification alerts users when they have been assigned to execute
 * an audit, including relevant details about the audit scope and deadline.
 */
class AuditAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Audit $audit
    ) {}

    /**
     * Get the notification's delivery channels.
     * Database is always included. Mail is only included if properly configured
     * and the user has email enabled for audit_assignments category.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // Only include mail if configured with a real mail driver
        $mailDriver = config('mail.default');
        if ($mailDriver && ! in_array($mailDriver, ['log', 'array'])) {
            // Check user preference for audit_assignments category
            if ($notifiable instanceof User && $notifiable->hasEmailEnabledFor(User::NOTIFICATION_CATEGORY_AUDIT_ASSIGNMENTS)) {
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
        $audit = $this->audit;
        $datacenterName = $audit->datacenter?->name ?? 'Unknown Datacenter';
        $viewUrl = url("/audits/{$audit->id}");

        $mailMessage = (new MailMessage)
            ->subject("Audit Assignment: {$audit->name}")
            ->greeting('You have been assigned to an audit!')
            ->line("**Audit Name:** {$audit->name}")
            ->line("**Audit Type:** {$audit->type->label()}")
            ->line("**Datacenter:** {$datacenterName}");

        if ($audit->due_date) {
            $mailMessage->line("**Due Date:** {$audit->due_date->format('Y-m-d')}");
        }

        if ($audit->description) {
            $mailMessage->line("**Description:** {$audit->description}");
        }

        return $mailMessage
            ->action('View Audit', $viewUrl)
            ->line('Please review the audit scope and prepare for execution.');
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $audit = $this->audit;

        return [
            'type' => 'audit_assigned',
            'audit_id' => $audit->id,
            'audit_name' => $audit->name,
            'audit_type' => $audit->type->value,
            'audit_type_label' => $audit->type->label(),
            'datacenter_id' => $audit->datacenter_id,
            'datacenter_name' => $audit->datacenter?->name,
            'due_date' => $audit->due_date?->format('Y-m-d'),
            'message' => "You have been assigned to audit: {$audit->name}",
        ];
    }
}
