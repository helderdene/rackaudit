<?php

namespace App\Notifications;

use App\Models\ReportSchedule;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a scheduled report generation fails.
 *
 * Alerts the schedule owner via database and email channels with
 * the schedule name, error message, failure count, and action link.
 */
class ScheduledReportFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public ReportSchedule $schedule,
        public string $errorMessage
    ) {}

    /**
     * Get the notification's delivery channels.
     * Database is always included. Mail is only included if properly configured
     * and the user has email enabled for scheduled_reports category.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // Only include mail if configured with a real mail driver
        $mailDriver = config('mail.default');
        if ($mailDriver && ! in_array($mailDriver, ['log', 'array'])) {
            // Check user preference for scheduled_reports category
            if ($notifiable instanceof User && $notifiable->hasEmailEnabledFor(User::NOTIFICATION_CATEGORY_SCHEDULED_REPORTS)) {
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
        $failureCount = $this->schedule->consecutive_failures;
        $maxFailures = ReportSchedule::MAX_CONSECUTIVE_FAILURES;
        $remainingAttempts = max(0, $maxFailures - $failureCount);

        $viewUrl = url("/report-schedules/{$this->schedule->id}");

        return (new MailMessage)
            ->subject("Scheduled Report Failed: {$this->schedule->name}")
            ->greeting('Report Generation Failed')
            ->line("Your scheduled report \"{$this->schedule->name}\" failed to generate.")
            ->line("**Error:** {$this->errorMessage}")
            ->line("**Failure Count:** {$failureCount} consecutive failure(s)")
            ->line("**Remaining Attempts:** {$remainingAttempts} before automatic disable")
            ->action('View Schedule', $viewUrl)
            ->line('Please review the schedule configuration and try again.')
            ->line('The system will automatically retry at the next scheduled time.');
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'scheduled_report_failed',
            'schedule_id' => $this->schedule->id,
            'schedule_name' => $this->schedule->name,
            'report_type' => $this->schedule->report_type->value,
            'error_message' => $this->errorMessage,
            'failure_count' => $this->schedule->consecutive_failures,
            'message' => "Scheduled report \"{$this->schedule->name}\" failed: {$this->errorMessage}",
            'action_url' => "/report-schedules/{$this->schedule->id}",
        ];
    }
}
