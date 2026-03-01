<?php

use App\Jobs\CaptureCapacitySnapshotJob;
use App\Jobs\CaptureDashboardMetricsJob;
use App\Jobs\CleanupOldSnapshotsJob;
use App\Jobs\DetectDiscrepanciesJob;
use App\Models\Datacenter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Here you may define all of your scheduled tasks. The scheduler will
| run these tasks based on their defined schedule.
|
*/

Schedule::command('activity:cleanup')->dailyAt('02:00');
Schedule::command('imports:cleanup-errors')->dailyAt('03:00');

/*
|--------------------------------------------------------------------------
| Finding Due Date Notifications Schedule
|--------------------------------------------------------------------------
|
| Send notifications for findings approaching their due date or overdue.
| Runs daily at 08:00 AM to give users timely reminders.
|
*/

Schedule::command('findings:send-due-date-notifications')
    ->dailyAt('08:00')
    ->name('finding-due-date-notifications')
    ->description('Send notifications for findings approaching due date or overdue');

/*
|--------------------------------------------------------------------------
| Discrepancy Detection Schedule
|--------------------------------------------------------------------------
|
| Run nightly discrepancy detection for all datacenters. The time is
| configurable via the config/discrepancies.php configuration file.
|
*/

if (config('discrepancies.schedule.enabled', true)) {
    $scheduleTime = config('discrepancies.schedule.time', '02:00');
    $scheduleTimezone = config('discrepancies.schedule.timezone', config('app.timezone'));

    Schedule::call(function () {
        // Dispatch detection job for each datacenter
        Datacenter::all()->each(function (Datacenter $datacenter) {
            DetectDiscrepanciesJob::dispatch(datacenterId: $datacenter->id);
        });
    })
        ->dailyAt($scheduleTime)
        ->timezone($scheduleTimezone)
        ->name('discrepancy-detection')
        ->description('Detect discrepancies between expected and actual connections');
}

/*
|--------------------------------------------------------------------------
| Dashboard Snapshot Schedule
|--------------------------------------------------------------------------
|
| Capture daily capacity and dashboard metrics snapshots for all datacenters.
| Runs at midnight to record historical metrics for trend analysis.
| Cleanup job runs 30 minutes after snapshot capture to remove old records.
|
*/

Schedule::job(new CaptureCapacitySnapshotJob)
    ->dailyAt('00:00')
    ->name('capacity-snapshot-capture')
    ->description('Capture daily capacity snapshots for all datacenters');

Schedule::job(new CaptureDashboardMetricsJob)
    ->dailyAt('00:00')
    ->name('dashboard-metrics-capture')
    ->description('Capture daily dashboard metrics snapshots for all datacenters');

Schedule::job(new CleanupOldSnapshotsJob)
    ->dailyAt('00:30')
    ->name('capacity-snapshot-cleanup')
    ->description('Clean up capacity snapshots older than 52 weeks');

/*
|--------------------------------------------------------------------------
| Scheduled Report Processing
|--------------------------------------------------------------------------
|
| Process due scheduled reports and dispatch generation jobs. Runs every
| minute to check for reports that need to be generated and sent.
| Each schedule has its own timezone setting for accurate execution timing.
|
*/

Schedule::command('reports:process-scheduled')
    ->everyMinute()
    ->name('scheduled-report-processing')
    ->description('Process due scheduled reports and dispatch generation jobs');
