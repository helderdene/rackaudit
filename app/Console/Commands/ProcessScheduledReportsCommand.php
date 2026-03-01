<?php

namespace App\Console\Commands;

use App\Jobs\GenerateScheduledReportJob;
use App\Models\ReportSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Command to process due scheduled reports and dispatch generation jobs.
 *
 * This command runs periodically (typically every minute) to find report
 * schedules that are due for execution. For each due schedule, it dispatches
 * a GenerateScheduledReportJob and updates the next run time.
 */
class ProcessScheduledReportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:process-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process due scheduled reports and dispatch generation jobs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for due scheduled reports...');

        $dueSchedules = $this->getDueSchedules();
        $count = $dueSchedules->count();

        if ($count === 0) {
            $this->info('No scheduled reports are due at this time.');

            return Command::SUCCESS;
        }

        $this->info("Found {$count} scheduled report(s) due for processing.");

        $dispatched = 0;
        foreach ($dueSchedules as $schedule) {
            $this->processSchedule($schedule);
            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} report generation job(s).");

        return Command::SUCCESS;
    }

    /**
     * Get all enabled schedules that are due for execution.
     *
     * A schedule is due when:
     * - is_enabled is true
     * - next_run_at is less than or equal to the current time
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ReportSchedule>
     */
    protected function getDueSchedules(): \Illuminate\Database\Eloquent\Collection
    {
        return ReportSchedule::query()
            ->where('is_enabled', true)
            ->where('next_run_at', '<=', Carbon::now())
            ->with(['user', 'distributionList', 'distributionList.members'])
            ->get();
    }

    /**
     * Process a single due schedule.
     *
     * Dispatches the generation job and updates the next run time.
     */
    protected function processSchedule(ReportSchedule $schedule): void
    {
        Log::info('ProcessScheduledReportsCommand: Dispatching job for schedule', [
            'schedule_id' => $schedule->id,
            'schedule_name' => $schedule->name,
            'timezone' => $schedule->timezone,
            'next_run_at' => $schedule->next_run_at?->toIso8601String(),
        ]);

        // Dispatch the job to generate and send the report
        GenerateScheduledReportJob::dispatch($schedule);

        // Update next_run_at to prevent duplicate processing
        // The job will update this again upon completion, but we set it here
        // to avoid re-processing if the command runs before the job completes
        $schedule->next_run_at = $schedule->calculateNextRunAt();
        $schedule->save();

        $this->line("  - Dispatched: {$schedule->name} (ID: {$schedule->id})");
    }
}
