<?php

/**
 * Frontend tests for Report Schedule UI components.
 *
 * Tests cover:
 * - Schedule index page shows schedules with status
 * - Create schedule form captures all configuration
 * - Frequency selection shows appropriate day/time fields
 * - Enable/disable toggle works
 * - Execution history displays correctly
 * - Schedule creation integrates with Custom Report Builder
 */

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Enums\ScheduleFrequency;
use App\Models\DistributionList;
use App\Models\ReportSchedule;
use App\Models\ReportScheduleExecution;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Create users with different roles
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    $this->itManager = User::factory()->create();
    $this->itManager->assignRole('IT Manager');

    $this->operator = User::factory()->create();
    $this->operator->assignRole('Operator');
});

test('schedule index page shows schedules with status', function () {
    // Create distribution list for schedules
    $distributionList = DistributionList::factory()->create([
        'user_id' => $this->operator->id,
        'name' => 'Test Recipients',
    ]);

    // Create schedules with various statuses
    $enabledSchedule = ReportSchedule::factory()->create([
        'user_id' => $this->operator->id,
        'name' => 'Enabled Weekly Report',
        'distribution_list_id' => $distributionList->id,
        'report_type' => ReportType::Capacity,
        'frequency' => ScheduleFrequency::Weekly,
        'day_of_week' => 1, // Monday
        'time_of_day' => '09:00',
        'timezone' => 'America/New_York',
        'format' => ReportFormat::PDF,
        'is_enabled' => true,
        'consecutive_failures' => 0,
        'last_run_status' => 'success',
    ]);

    $disabledSchedule = ReportSchedule::factory()->create([
        'user_id' => $this->operator->id,
        'name' => 'Disabled Report',
        'distribution_list_id' => $distributionList->id,
        'report_type' => ReportType::Assets,
        'frequency' => ScheduleFrequency::Daily,
        'time_of_day' => '08:00',
        'timezone' => 'UTC',
        'format' => ReportFormat::CSV,
        'is_enabled' => false,
        'consecutive_failures' => 3,
        'last_run_status' => 'failed',
    ]);

    $response = $this->actingAs($this->operator)
        ->get(route('report-schedules.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ReportSchedules/Index')
            ->has('schedules', 2)
            ->has('schedules.0', fn (Assert $schedule) => $schedule
                ->has('id')
                ->has('name')
                ->has('report_type')
                ->has('report_type_label')
                ->has('frequency')
                ->has('frequency_label')
                ->has('schedule_display')
                ->has('format')
                ->has('format_label')
                ->has('is_enabled')
                ->has('consecutive_failures')
                ->has('next_run_at')
                ->has('last_run_status')
                ->has('distribution_list')
                ->etc()
            )
            ->where('canCreate', true)
        );
});

test('create schedule form captures all configuration', function () {
    // Create distribution list
    $distributionList = DistributionList::factory()->create([
        'user_id' => $this->operator->id,
        'name' => 'Team Recipients',
    ]);

    $response = $this->actingAs($this->operator)
        ->post(route('report-schedules.store'), [
            'name' => 'Weekly Capacity Report',
            'distribution_list_id' => $distributionList->id,
            'report_type' => 'capacity',
            'report_configuration' => [
                'columns' => ['rack_name', 'datacenter_name', 'utilization_percent'],
                'filters' => [],
                'sort' => [['column' => 'rack_name', 'direction' => 'asc']],
                'group_by' => null,
            ],
            'frequency' => 'weekly',
            'day_of_week' => 1, // Monday
            'time_of_day' => '09:00',
            'timezone' => 'America/New_York',
            'format' => 'pdf',
        ]);

    $response->assertRedirect(route('report-schedules.index'));
    $response->assertSessionHas('success');

    // Verify schedule was created
    $schedule = ReportSchedule::where('name', 'Weekly Capacity Report')->first();
    expect($schedule)->not->toBeNull();
    expect($schedule->distribution_list_id)->toBe($distributionList->id);
    expect($schedule->report_type)->toBe(ReportType::Capacity);
    expect($schedule->frequency)->toBe(ScheduleFrequency::Weekly);
    expect($schedule->day_of_week)->toBe(1);
    expect($schedule->time_of_day)->toBe('09:00');
    expect($schedule->timezone)->toBe('America/New_York');
    expect($schedule->format)->toBe(ReportFormat::PDF);
    expect($schedule->is_enabled)->toBeTrue();

    // Use toMatchArray for flexible comparison (ignores key order)
    expect($schedule->report_configuration)->toMatchArray([
        'columns' => ['rack_name', 'datacenter_name', 'utilization_percent'],
        'filters' => [],
        'sort' => [['column' => 'rack_name', 'direction' => 'asc']],
        'group_by' => null,
    ]);
});

test('frequency selection shows appropriate day and time validation', function () {
    $distributionList = DistributionList::factory()->create([
        'user_id' => $this->operator->id,
    ]);

    // Test weekly requires day_of_week
    $response = $this->actingAs($this->operator)
        ->post(route('report-schedules.store'), [
            'name' => 'Test Report',
            'distribution_list_id' => $distributionList->id,
            'report_type' => 'capacity',
            'report_configuration' => ['columns' => ['rack_name'], 'filters' => [], 'sort' => [], 'group_by' => null],
            'frequency' => 'weekly',
            // Missing day_of_week
            'time_of_day' => '09:00',
            'timezone' => 'UTC',
            'format' => 'pdf',
        ]);

    $response->assertSessionHasErrors(['day_of_week']);

    // Test monthly requires day_of_month
    $response = $this->actingAs($this->operator)
        ->post(route('report-schedules.store'), [
            'name' => 'Test Report',
            'distribution_list_id' => $distributionList->id,
            'report_type' => 'capacity',
            'report_configuration' => ['columns' => ['rack_name'], 'filters' => [], 'sort' => [], 'group_by' => null],
            'frequency' => 'monthly',
            // Missing day_of_month
            'time_of_day' => '09:00',
            'timezone' => 'UTC',
            'format' => 'pdf',
        ]);

    $response->assertSessionHasErrors(['day_of_month']);

    // Test daily does not require day_of_week or day_of_month
    $response = $this->actingAs($this->operator)
        ->post(route('report-schedules.store'), [
            'name' => 'Daily Report',
            'distribution_list_id' => $distributionList->id,
            'report_type' => 'capacity',
            'report_configuration' => ['columns' => ['rack_name'], 'filters' => [], 'sort' => [], 'group_by' => null],
            'frequency' => 'daily',
            'time_of_day' => '09:00',
            'timezone' => 'UTC',
            'format' => 'pdf',
        ]);

    $response->assertRedirect(route('report-schedules.index'));
    $response->assertSessionHas('success');
});

test('enable disable toggle works', function () {
    $distributionList = DistributionList::factory()->create([
        'user_id' => $this->operator->id,
    ]);

    $schedule = ReportSchedule::factory()->create([
        'user_id' => $this->operator->id,
        'distribution_list_id' => $distributionList->id,
        'is_enabled' => true,
        'consecutive_failures' => 0,
    ]);

    // Disable the schedule
    $response = $this->actingAs($this->operator)
        ->patch(route('report-schedules.toggle', $schedule), [
            'is_enabled' => false,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $schedule->refresh();
    expect($schedule->is_enabled)->toBeFalse();

    // Re-enable the schedule (with failures to test reset)
    $schedule->update(['consecutive_failures' => 2]);

    $response = $this->actingAs($this->operator)
        ->patch(route('report-schedules.toggle', $schedule), [
            'is_enabled' => true,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $schedule->refresh();
    expect($schedule->is_enabled)->toBeTrue();
    expect($schedule->consecutive_failures)->toBe(0); // Reset on re-enable
});

test('execution history displays correctly', function () {
    $distributionList = DistributionList::factory()->create([
        'user_id' => $this->operator->id,
    ]);

    $schedule = ReportSchedule::factory()->create([
        'user_id' => $this->operator->id,
        'distribution_list_id' => $distributionList->id,
        'name' => 'History Test Schedule',
    ]);

    // Create execution history records
    ReportScheduleExecution::factory()->create([
        'report_schedule_id' => $schedule->id,
        'status' => 'success',
        'started_at' => now()->subHour(),
        'completed_at' => now()->subHour()->addMinutes(2),
        'file_size_bytes' => 1024 * 50,
        'recipients_count' => 5,
    ]);

    ReportScheduleExecution::factory()->create([
        'report_schedule_id' => $schedule->id,
        'status' => 'failed',
        'started_at' => now()->subDay(),
        'completed_at' => now()->subDay()->addMinutes(1),
        'error_message' => 'SMTP connection failed',
        'recipients_count' => 0,
    ]);

    $response = $this->actingAs($this->operator)
        ->get(route('report-schedules.show', $schedule));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ReportSchedules/Show')
            ->where('schedule.id', $schedule->id)
            ->where('schedule.name', 'History Test Schedule')
            ->has('recentExecutions', 2)
            ->has('recentExecutions.0', fn (Assert $execution) => $execution
                ->has('id')
                ->has('status')
                ->has('started_at')
                ->has('completed_at')
                ->has('duration_seconds')
                ->has('recipients_count')
                ->has('file_size_bytes')
                ->etc()
            )
        );
});

test('schedule creation integrates with custom report builder configuration', function () {
    $distributionList = DistributionList::factory()->create([
        'user_id' => $this->operator->id,
    ]);

    // Test with full report configuration including filters, sort, and group_by
    $response = $this->actingAs($this->operator)
        ->post(route('report-schedules.store'), [
            'name' => 'Complex Report Schedule',
            'distribution_list_id' => $distributionList->id,
            'report_type' => 'assets',
            'report_configuration' => [
                'columns' => ['asset_tag', 'name', 'datacenter_name', 'lifecycle_status'],
                'filters' => [
                    'datacenter_id' => 1,
                    'lifecycle_status' => 'active',
                ],
                'sort' => [
                    ['column' => 'asset_tag', 'direction' => 'asc'],
                    ['column' => 'name', 'direction' => 'desc'],
                ],
                'group_by' => 'datacenter_name',
            ],
            'frequency' => 'monthly',
            'day_of_month' => '15',
            'time_of_day' => '10:30',
            'timezone' => 'Europe/London',
            'format' => 'csv',
        ]);

    $response->assertRedirect(route('report-schedules.index'));
    $response->assertSessionHas('success');

    $schedule = ReportSchedule::where('name', 'Complex Report Schedule')->first();
    expect($schedule)->not->toBeNull();
    expect($schedule->report_type)->toBe(ReportType::Assets);

    // Use toMatchArray for flexible comparison (ignores key order)
    expect($schedule->report_configuration)->toMatchArray([
        'columns' => ['asset_tag', 'name', 'datacenter_name', 'lifecycle_status'],
        'filters' => [
            'datacenter_id' => 1,
            'lifecycle_status' => 'active',
        ],
        'sort' => [
            ['column' => 'asset_tag', 'direction' => 'asc'],
            ['column' => 'name', 'direction' => 'desc'],
        ],
        'group_by' => 'datacenter_name',
    ]);
    expect($schedule->format)->toBe(ReportFormat::CSV);
});

test('create page renders with all required data', function () {
    // Create distribution lists
    DistributionList::factory()->count(2)->create([
        'user_id' => $this->operator->id,
    ]);

    $response = $this->actingAs($this->operator)
        ->get(route('report-schedules.create'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ReportSchedules/Create')
            ->has('distributionLists', 2)
            ->has('reportTypes')
            ->has('frequencies')
            ->has('formats')
            ->has('timezones')
            ->has('defaultTimezone')
        );
});

test('show page displays schedule configuration and distribution list', function () {
    $distributionList = DistributionList::factory()->withMembers(3)->create([
        'user_id' => $this->operator->id,
        'name' => 'My Distribution List',
    ]);

    $schedule = ReportSchedule::factory()->create([
        'user_id' => $this->operator->id,
        'distribution_list_id' => $distributionList->id,
        'name' => 'Detailed Schedule',
        'report_type' => ReportType::Connections,
        'report_configuration' => [
            'columns' => ['source_device', 'destination_device'],
            'filters' => [],
            'sort' => [],
            'group_by' => null,
        ],
    ]);

    $response = $this->actingAs($this->operator)
        ->get(route('report-schedules.show', $schedule));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ReportSchedules/Show')
            ->where('schedule.name', 'Detailed Schedule')
            ->where('schedule.report_type', 'connections')
            ->has('schedule.report_configuration')
            ->has('schedule.distribution_list', fn (Assert $list) => $list
                ->where('name', 'My Distribution List')
                ->has('members_count')
                ->etc()
            )
            ->has('canToggle')
            ->has('canDelete')
        );
});
