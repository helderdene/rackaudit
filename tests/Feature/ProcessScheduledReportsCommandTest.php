<?php

use App\Jobs\GenerateScheduledReportJob;
use App\Models\DistributionList;
use App\Models\ReportSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('finds due schedules that need processing', function () {
    // Create a user with a distribution list
    $user = User::factory()->create();
    $distributionList = DistributionList::factory()
        ->for($user)
        ->create();

    // Create a schedule that is due (next_run_at in the past)
    $dueSchedule = ReportSchedule::factory()
        ->forUser($user)
        ->forDistributionList($distributionList)
        ->create([
            'is_enabled' => true,
            'next_run_at' => Carbon::now()->subMinutes(5),
        ]);

    // Create a schedule that is not yet due (next_run_at in the future)
    $futureSchedule = ReportSchedule::factory()
        ->forUser($user)
        ->forDistributionList($distributionList)
        ->create([
            'is_enabled' => true,
            'next_run_at' => Carbon::now()->addHour(),
        ]);

    $this->artisan('reports:process-scheduled')
        ->assertSuccessful();

    // Should dispatch job only for the due schedule
    Queue::assertPushed(GenerateScheduledReportJob::class, 1);
    Queue::assertPushed(GenerateScheduledReportJob::class, function ($job) use ($dueSchedule) {
        return $job->schedule->id === $dueSchedule->id;
    });
});

it('dispatches jobs for all due schedules', function () {
    $user = User::factory()->create();
    $distributionList = DistributionList::factory()
        ->for($user)
        ->create();

    // Create multiple due schedules
    $dueSchedules = ReportSchedule::factory()
        ->count(3)
        ->forUser($user)
        ->forDistributionList($distributionList)
        ->create([
            'is_enabled' => true,
            'next_run_at' => Carbon::now()->subMinutes(5),
        ]);

    $this->artisan('reports:process-scheduled')
        ->assertSuccessful();

    // Should dispatch jobs for all due schedules
    Queue::assertPushed(GenerateScheduledReportJob::class, 3);

    // Verify each schedule had a job dispatched
    foreach ($dueSchedules as $schedule) {
        Queue::assertPushed(GenerateScheduledReportJob::class, function ($job) use ($schedule) {
            return $job->schedule->id === $schedule->id;
        });
    }
});

it('respects timezone for each schedule when determining due status', function () {
    $user = User::factory()->create();
    $distributionList = DistributionList::factory()
        ->for($user)
        ->create();

    // Freeze time at 10:00 UTC
    Carbon::setTestNow(Carbon::create(2024, 6, 15, 10, 0, 0, 'UTC'));

    // Create a schedule with next_run_at in the past using a specific timezone
    // Schedule set for 09:00 UTC (which is past)
    $pastSchedule = ReportSchedule::factory()
        ->forUser($user)
        ->forDistributionList($distributionList)
        ->create([
            'is_enabled' => true,
            'timezone' => 'UTC',
            'time_of_day' => '09:00',
            'next_run_at' => Carbon::create(2024, 6, 15, 9, 0, 0, 'UTC'),
        ]);

    // Create a schedule with next_run_at in the future
    // Schedule set for 11:00 UTC (which is future)
    $futureSchedule = ReportSchedule::factory()
        ->forUser($user)
        ->forDistributionList($distributionList)
        ->create([
            'is_enabled' => true,
            'timezone' => 'UTC',
            'time_of_day' => '11:00',
            'next_run_at' => Carbon::create(2024, 6, 15, 11, 0, 0, 'UTC'),
        ]);

    $this->artisan('reports:process-scheduled')
        ->assertSuccessful();

    // Only the past schedule should have a job dispatched
    Queue::assertPushed(GenerateScheduledReportJob::class, 1);
    Queue::assertPushed(GenerateScheduledReportJob::class, function ($job) use ($pastSchedule) {
        return $job->schedule->id === $pastSchedule->id;
    });

    Carbon::setTestNow();
});

it('skips disabled schedules even if they are due', function () {
    $user = User::factory()->create();
    $distributionList = DistributionList::factory()
        ->for($user)
        ->create();

    // Create a disabled schedule that is due
    $disabledSchedule = ReportSchedule::factory()
        ->forUser($user)
        ->forDistributionList($distributionList)
        ->disabled()
        ->create([
            'next_run_at' => Carbon::now()->subMinutes(5),
        ]);

    // Create an enabled schedule that is due
    $enabledSchedule = ReportSchedule::factory()
        ->forUser($user)
        ->forDistributionList($distributionList)
        ->create([
            'is_enabled' => true,
            'next_run_at' => Carbon::now()->subMinutes(5),
        ]);

    $this->artisan('reports:process-scheduled')
        ->assertSuccessful();

    // Should only dispatch job for the enabled schedule
    Queue::assertPushed(GenerateScheduledReportJob::class, 1);
    Queue::assertPushed(GenerateScheduledReportJob::class, function ($job) use ($enabledSchedule) {
        return $job->schedule->id === $enabledSchedule->id;
    });
    Queue::assertNotPushed(GenerateScheduledReportJob::class, function ($job) use ($disabledSchedule) {
        return $job->schedule->id === $disabledSchedule->id;
    });
});
