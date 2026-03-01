<?php

namespace App\Notifications;

use App\Models\ReportSchedule;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a scheduled report is disabled due to consecutive failures.
 *
 * Alerts the schedule owner that their schedule has been automatically disabled
 * after reaching the maximum consecutive failure threshold.
 */
class ScheduledReportDisabledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public ReportSchedule $schedule
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
        $maxFailures = ReportSchedule::MAX_CONSECUTIVE_FAILURES;
        $viewUrl = url("/report-schedules/{$this->schedule->id}");

        return (new MailMessage)
            ->subject("Scheduled Report Disabled: {$this->schedule->name}")
            ->greeting('Schedule Automatically Disabled')
            ->line("Your scheduled report \"{$this->schedule->name}\" has been automatically disabled.")
            ->line("The schedule was disabled after {$maxFailures} consecutive failed attempts to generate the report.")
            ->line('**To Re-enable:**')
            ->line('1. Review and fix any issues with the schedule configuration')
            ->line('2. Click the link below to view the schedule')
            ->line('3. Toggle the schedule back to enabled')
            ->action('View & Re-enable Schedule', $viewUrl)
            ->line('Please review the execution history for more details on the failures.');
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $maxFailures = ReportSchedule::MAX_CONSECUTIVE_FAILURES;

        return [
            'type' => 'scheduled_report_disabled',
            'schedule_id' => $this->schedule->id,
            'schedule_name' => $this->schedule->name,
            'report_type' => $this->schedule->report_type->value,
            'failure_count' => $this->schedule->consecutive_failures,
            'message' => "Scheduled report \"{$this->schedule->name}\" has been disabled after {$maxFailures} consecutive failures.",
            'action_url' => "/report-schedules/{$this->schedule->id}",
            'action_text' => 'View & Re-enable',
        ];
    }
}
