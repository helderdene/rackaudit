<?php

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Enums\ScheduleFrequency;
use App\Models\DistributionList;
use App\Models\ReportSchedule;
use App\Models\ReportScheduleExecution;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('report schedule can be created with daily frequency', function () {
    $user = User::factory()->create();
    $distributionList = DistributionList::factory()->create(['user_id' => $user->id]);

    $schedule = ReportSchedule::factory()->daily()->create([
        'name' => 'Daily Capacity Report',
        'user_id' => $user->id,
        'distribution_list_id' => $distributionList->id,
        'report_type' => ReportType::Capacity,
        'time_of_day' => '08:00',
        'timezone' => 'America/New_York',
        'format' => ReportFormat::PDF,
    ]);

    expect($schedule->name)->toBe('Daily Capacity Report');
    expect($schedule->frequency)->toBe(ScheduleFrequency::Daily);
    expect($schedule->time_of_day)->toBe('08:00');
    expect($schedule->timezone)->toBe('America/New_York');
    expect($schedule->format)->toBe(ReportFormat::PDF);
    expect($schedule->is_enabled)->toBeTrue();
});

test('report schedule can be created with weekly frequency including day of week', function () {
    $user = User::factory()->create();
    $distributionList = DistributionList::factory()->create(['user_id' => $user->id]);

    $schedule = ReportSchedule::factory()->weekly()->create([
        'name' => 'Weekly Assets Report',
        'user_id' => $user->id,
        'distribution_list_id' => $distributionList->id,
        'report_type' => ReportType::Assets,
        'day_of_week' => 1, // Monday
        'time_of_day' => '09:00',
        'format' => ReportFormat::CSV,
    ]);

    expect($schedule->frequency)->toBe(ScheduleFrequency::Weekly);
    expect($schedule->day_of_week)->toBe(1);
    expect($schedule->day_of_month)->toBeNull();
});

test('report schedule can be created with monthly frequency including day of month', function () {
    $user = User::factory()->create();
    $distributionList = DistributionList::factory()->create(['user_id' => $user->id]);

    $schedule = ReportSchedule::factory()->monthly()->create([
        'name' => 'Monthly Connections Report',
        'user_id' => $user->id,
        'distribution_list_id' => $distributionList->id,
        'report_type' => ReportType::Connections,
        'day_of_month' => '15',
        'time_of_day' => '10:00',
    ]);

    expect($schedule->frequency)->toBe(ScheduleFrequency::Monthly);
    expect($schedule->day_of_month)->toBe('15');
    expect($schedule->day_of_week)->toBeNull();
});

test('report schedule belongs to user and distribution list', function () {
    $user = User::factory()->create();
    $distributionList = DistributionList::factory()->create(['user_id' => $user->id]);

    $schedule = ReportSchedule::factory()->create([
        'user_id' => $user->id,
        'distribution_list_id' => $distributionList->id,
    ]);

    expect($schedule->user)->toBeInstanceOf(User::class);
    expect($schedule->user->id)->toBe($user->id);
    expect($schedule->distributionList)->toBeInstanceOf(DistributionList::class);
    expect($schedule->distributionList->id)->toBe($distributionList->id);
});

test('report schedule stores report configuration as JSON', function () {
    $configuration = [
        'columns' => ['device_name', 'rack_name', 'u_position'],
        'filters' => ['datacenter_id' => 1],
        'sort' => ['column' => 'device_name', 'direction' => 'asc'],
        'group_by' => 'rack_name',
    ];

    $schedule = ReportSchedule::factory()->create([
        'report_configuration' => $configuration,
    ]);

    expect($schedule->report_configuration)->toBeArray();
    expect($schedule->report_configuration['columns'])->toBe(['device_name', 'rack_name', 'u_position']);
    expect($schedule->report_configuration['filters'])->toBe(['datacenter_id' => 1]);
});

test('report schedule can be enabled and disabled', function () {
    $schedule = ReportSchedule::factory()->create(['is_enabled' => true]);
    expect($schedule->is_enabled)->toBeTrue();

    $schedule->update(['is_enabled' => false]);
    $schedule->refresh();
    expect($schedule->is_enabled)->toBeFalse();

    $disabledSchedule = ReportSchedule::factory()->disabled()->create();
    expect($disabledSchedule->is_enabled)->toBeFalse();
});

test('report schedule calculates next run at for daily frequency', function () {
    Carbon::setTestNow(Carbon::parse('2025-01-15 10:00:00', 'America/New_York'));

    $schedule = ReportSchedule::factory()->daily()->create([
        'time_of_day' => '08:00',
        'timezone' => 'America/New_York',
        'next_run_at' => null,
    ]);

    $nextRun = $schedule->calculateNextRunAt();

    // Since current time is 10:00 and schedule is for 08:00, next run should be tomorrow at 08:00
    expect($nextRun->format('Y-m-d H:i'))->toBe('2025-01-16 08:00');
    expect($nextRun->timezone->getName())->toBe('America/New_York');

    Carbon::setTestNow();
});

test('report schedule calculates next run at for weekly frequency', function () {
    // Set current time to Wednesday, January 15, 2025
    Carbon::setTestNow(Carbon::parse('2025-01-15 10:00:00', 'America/New_York'));

    $schedule = ReportSchedule::factory()->weekly()->create([
        'day_of_week' => 1, // Monday
        'time_of_day' => '09:00',
        'timezone' => 'America/New_York',
        'next_run_at' => null,
    ]);

    $nextRun = $schedule->calculateNextRunAt();

    // Next Monday from Wednesday Jan 15 is Monday Jan 20
    expect($nextRun->format('Y-m-d'))->toBe('2025-01-20');
    expect($nextRun->format('H:i'))->toBe('09:00');

    Carbon::setTestNow();
});

test('report schedule calculates next run at for monthly frequency', function () {
    Carbon::setTestNow(Carbon::parse('2025-01-20 10:00:00', 'America/New_York'));

    $schedule = ReportSchedule::factory()->monthly()->create([
        'day_of_month' => '15',
        'time_of_day' => '10:00',
        'timezone' => 'America/New_York',
        'next_run_at' => null,
    ]);

    $nextRun = $schedule->calculateNextRunAt();

    // Since we're past the 15th, next run should be February 15
    expect($nextRun->format('Y-m-d'))->toBe('2025-02-15');
    expect($nextRun->format('H:i'))->toBe('10:00');

    Carbon::setTestNow();
});

test('report schedule handles timezone correctly', function () {
    Carbon::setTestNow(Carbon::parse('2025-01-15 06:00:00', 'UTC'));

    // Create schedule for 08:00 Tokyo time (UTC+9)
    // Current time is 06:00 UTC = 15:00 Tokyo
    $schedule = ReportSchedule::factory()->daily()->create([
        'time_of_day' => '08:00',
        'timezone' => 'Asia/Tokyo',
        'next_run_at' => null,
    ]);

    $nextRun = $schedule->calculateNextRunAt();

    // Since 08:00 Tokyo has already passed today (it's 15:00 Tokyo now),
    // next run should be tomorrow at 08:00 Tokyo = 2025-01-15 23:00 UTC
    expect($nextRun->timezone->getName())->toBe('Asia/Tokyo');
    expect($nextRun->format('H:i'))->toBe('08:00');

    Carbon::setTestNow();
});

test('report schedule tracks failure count and can be disabled after threshold', function () {
    $schedule = ReportSchedule::factory()->create([
        'consecutive_failures' => 0,
        'is_enabled' => true,
    ]);

    expect($schedule->shouldDisable())->toBeFalse();

    $schedule->incrementFailureCount();
    expect($schedule->consecutive_failures)->toBe(1);
    expect($schedule->shouldDisable())->toBeFalse();

    $schedule->incrementFailureCount();
    $schedule->incrementFailureCount();
    expect($schedule->consecutive_failures)->toBe(3);
    expect($schedule->shouldDisable())->toBeTrue();

    $schedule->resetFailureCount();
    expect($schedule->consecutive_failures)->toBe(0);
    expect($schedule->shouldDisable())->toBeFalse();
});

test('report schedule execution records track execution history', function () {
    $schedule = ReportSchedule::factory()->create();

    $execution = ReportScheduleExecution::factory()->success()->create([
        'report_schedule_id' => $schedule->id,
        'recipients_count' => 5,
        'file_size_bytes' => 1024000,
    ]);

    expect($execution->reportSchedule)->toBeInstanceOf(ReportSchedule::class);
    expect($execution->reportSchedule->id)->toBe($schedule->id);
    expect($execution->status)->toBe('success');
    expect($execution->recipients_count)->toBe(5);
    expect($execution->file_size_bytes)->toBe(1024000);

    // Check relationship from schedule side
    expect($schedule->executions)->toHaveCount(1);
    expect($schedule->executions->first()->id)->toBe($execution->id);
});

test('report schedule mark as run updates last run status correctly', function () {
    Carbon::setTestNow(Carbon::parse('2025-01-15 10:00:00', 'UTC'));

    $schedule = ReportSchedule::factory()->create([
        'last_run_at' => null,
        'last_run_status' => null,
        'consecutive_failures' => 2,
    ]);

    // Successful run
    $schedule->markAsRun(true);
    expect($schedule->last_run_at->format('Y-m-d H:i'))->toBe('2025-01-15 10:00');
    expect($schedule->last_run_status)->toBe('success');
    expect($schedule->consecutive_failures)->toBe(0);

    // Failed run
    $schedule->markAsRun(false, 'Connection timeout');
    expect($schedule->last_run_status)->toBe('failed');
    expect($schedule->consecutive_failures)->toBe(1);

    Carbon::setTestNow();
});
