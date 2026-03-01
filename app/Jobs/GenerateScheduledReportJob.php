<?php

namespace App\Jobs;

use App\Models\ReportSchedule;
use App\Models\ReportScheduleExecution;
use App\Notifications\ScheduledReportDisabledNotification;
use App\Notifications\ScheduledReportFailedNotification;
use App\Services\ScheduledReportEmailService;
use App\Services\ScheduledReportGenerationService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job for generating and sending scheduled reports.
 *
 * Generates the report based on the schedule configuration, sends it via email
 * to the distribution list members, and records the execution result.
 * Handles failures with retry logic and disables schedules after consecutive failures.
 */
class GenerateScheduledReportJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     * Initial attempt + 1 retry = 2 tries total.
     */
    public int $tries = 2;

    /**
     * The number of seconds to wait before retrying the job.
     * 5 minutes delay between retries.
     */
    public int $backoff = 300;

    /**
     * The execution record for tracking this run.
     */
    protected ?ReportScheduleExecution $execution = null;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ReportSchedule $schedule
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        ScheduledReportGenerationService $reportService,
        ScheduledReportEmailService $emailService
    ): void {
        // Refresh the schedule to get latest state
        $this->schedule->refresh();
        $this->schedule->loadMissing(['user', 'distributionList', 'distributionList.members']);

        // Create execution record
        $this->execution = ReportScheduleExecution::create([
            'report_schedule_id' => $this->schedule->id,
            'status' => 'pending',
            'started_at' => Carbon::now(),
        ]);

        try {
            Log::info('GenerateScheduledReportJob: Starting report generation', [
                'schedule_id' => $this->schedule->id,
                'schedule_name' => $this->schedule->name,
                'format' => $this->schedule->format->value,
            ]);

            // Generate the report
            $filePath = $reportService->generateReport($this->schedule);
            $fileSize = $reportService->getReportFileSize($filePath);

            Log::info('GenerateScheduledReportJob: Report generated successfully', [
                'schedule_id' => $this->schedule->id,
                'file_path' => $filePath,
                'file_size' => $fileSize,
            ]);

            // Send the report via email
            $emailService->sendReport($this->schedule, $filePath);
            $recipientsCount = $emailService->getRecipientsCount($this->schedule);

            Log::info('GenerateScheduledReportJob: Report sent successfully', [
                'schedule_id' => $this->schedule->id,
                'recipients_count' => $recipientsCount,
            ]);

            // Update execution record as success
            $this->execution->update([
                'status' => 'success',
                'completed_at' => Carbon::now(),
                'file_size_bytes' => $fileSize,
                'recipients_count' => $recipientsCount,
            ]);

            // Mark schedule as successfully run
            $this->schedule->markAsRun(true);

            // Calculate next run time
            $this->schedule->next_run_at = $this->schedule->calculateNextRunAt();
            $this->schedule->save();

        } catch (\Exception $e) {
            Log::error('GenerateScheduledReportJob: Report generation failed', [
                'schedule_id' => $this->schedule->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update execution record as failed
            if ($this->execution !== null) {
                $this->execution->update([
                    'status' => 'failed',
                    'completed_at' => Carbon::now(),
                    'error_message' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * Increments the failure count, sends failure notification, and disables
     * the schedule if the consecutive failure threshold is exceeded.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateScheduledReportJob: Job failed permanently', [
            'schedule_id' => $this->schedule->id,
            'schedule_name' => $this->schedule->name,
            'error' => $exception->getMessage(),
        ]);

        // Refresh schedule to get current state
        $this->schedule->refresh();
        $this->schedule->loadMissing('user');

        // Increment failure count (this also saves the model)
        $this->schedule->incrementFailureCount();

        // Update last run status
        $this->schedule->last_run_at = Carbon::now();
        $this->schedule->last_run_status = 'failed';
        $this->schedule->save();

        // Calculate next run time even on failure
        $this->schedule->next_run_at = $this->schedule->calculateNextRunAt();
        $this->schedule->save();

        // Check if schedule should be disabled
        if ($this->schedule->shouldDisable()) {
            $this->schedule->is_enabled = false;
            $this->schedule->save();

            // Send disabled notification
            $this->schedule->user->notify(new ScheduledReportDisabledNotification($this->schedule));

            Log::warning('GenerateScheduledReportJob: Schedule disabled due to consecutive failures', [
                'schedule_id' => $this->schedule->id,
                'consecutive_failures' => $this->schedule->consecutive_failures,
            ]);
        } else {
            // Send failure notification
            $this->schedule->user->notify(new ScheduledReportFailedNotification(
                $this->schedule,
                $exception->getMessage()
            ));
        }

        // Update execution record if it exists
        if ($this->execution !== null) {
            $this->execution->update([
                'status' => 'failed',
                'completed_at' => Carbon::now(),
                'error_message' => $exception->getMessage(),
            ]);
        }
    }
}
