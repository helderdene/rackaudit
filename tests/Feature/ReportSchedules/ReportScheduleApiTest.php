<?php

/**
 * API tests for Report Schedule CRUD operations.
 *
 * Tests cover:
 * - Index returns user's schedules with status info
 * - Store creates schedule with all frequency types
 * - Store validates report configuration against CustomReportBuilderService
 * - Toggle enables/disables schedule
 * - Destroy removes schedule
 * - Execution history retrieval
 * - Re-enable after failure
 */

use App\Enums\ReportType;
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

    // Disable Inertia page existence check since Vue components are created in later task groups
    config(['inertia.testing.ensure_pages_exist' => false]);

    // Create users with different roles
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    $this->itManager = User::factory()->create();
    $this->itManager->assignRole('IT Manager');

    $this->operator = User::factory()->create();
    $this->operator->assignRole('Operator');
});

test('index returns users schedules with status info', function () {
    // Create distribution list for the operator
    $distributionList = DistributionList::factory()->create([
        'user_id' => $this->operator->id,
    ]);

    // Create schedules with varying statuses
    $schedule1 = ReportSchedule::factory()->daily()->create([
        'user_id' => $this->operator->id,
        'distribution_list_id' => $distributionList->id,
        'name' => 'Daily Capacity Report',
        'report_type' => ReportType::Capacity,
        'is_enabled' => true,
        'consecutive_failures' => 0,
        'last_run_at' => now()->subDay(),
        'last_run_status' => 'success',
        'next_run_at' => now()->addDay(),
    ]);

    $schedule2 = ReportSchedule::factory()->weekly()->create([
        'user_id' => $this->operator->id,
        'distribution_list_id' => $distributionList->id,
        'name' => 'Weekly Assets Report',
        'report_type' => ReportType::Assets,
        'is_enabled' => false,
        'consecutive_failures' => 2,
        'last_run_status' => 'failed',
    ]);

    // Create another user's schedule (should not appear)
    ReportSchedule::factory()->create();

    $response = $this->actingAs($this->operator)
        ->get(route('report-schedules.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ReportSchedules/Index')
            ->has('schedules', 2)
            ->has('schedules.0', fn (Assert $schedule) => $schedule
                ->where('name', 'Daily Capacity Report')
                ->where('is_enabled', true)
                ->where('consecutive_failures', 0)
                ->where('last_run_status', 'success')
                ->has('next_run_at')
                ->has('distribution_list')
                ->etc()
            )
        );
});

test('store creates schedule with all frequency types', function () {
    $distributionList = DistributionList::factory()->create([
        'user_id' => $this->operator->id,
    ]);

    // Test daily frequency
    $response = $this->actingAs($this->operator)
        ->post(route('report-schedules.store'), [
            'name' => 'Daily Test Report',
            'distribution_list_id' => $distributionList->id,
            'report_type' => 'capacity',
            'report_configuration' => [
                'columns' => ['rack_name', 'datacenter_name', 'utilization_percent'],
                'filters' => [],
                'sort' => [],
                'group_by' => null,
            ],
            'frequency' => 'daily',
            'time_of_day' => '08:00',
            'timezone' => 'America/New_York',
            'format' => 'pdf',
        ]);

    $response->assertRedirect(route('report-schedules.index'));

    $this->assertDatabaseHas('report_schedules', [
        'name' => 'Daily Test Report',
        'user_id' => $this->operator->id,
        'frequency' => 'daily',
    ]);

    // Test weekly frequency
    $response = $this->actingAs($this->operator)
        ->post(route('report-schedules.store'), [
            'name' => 'Weekly Test Report',
            'distribution_list_id' => $distributionList->id,
            'report_type' => 'assets',
            'report_configuration' => [
                'columns' => ['asset_tag', 'name', 'datacenter_name'],
                'filters' => [],
                'sort' => [],
                'group_by' => null,
            ],
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'time_of_day' => '09:00',
            'timezone' => 'UTC',
            'format' => 'csv',
        ]);

    $response->assertRedirect(route('report-schedules.index'));

    $this->assertDatabaseHas('report_schedules', [
        'name' => 'Weekly Test Report',
        'frequency' => 'weekly',
        'day_of_week' => 1,
    ]);

    // Test monthly frequency
    $response = $this->actingAs($this->operator)
        ->post(route('report-schedules.store'), [
            'name' => 'Monthly Test Report',
            'distribution_list_id' => $distributionList->id,
            'report_type' => 'connections',
            'report_configuration' => [
                'columns' => ['source_device', 'destination_device', 'cable_type'],
                'filters' => [],
                'sort' => [],
                'group_by' => null,
            ],
            'frequency' => 'monthly',
            'day_of_month' => '15',
            'time_of_day' => '10:00',
            'timezone' => 'Europe/London',
            'format' => 'pdf',
        ]);

    $response->assertRedirect(route('report-schedules.index'));

    $this->assertDatabaseHas('report_schedules', [
        'name' => 'Monthly Test Report',
        'frequency' => 'monthly',
        'day_of_month' => '15',
    ]);
});

test('store validates report configuration structure', function () {
    $distributionList = DistributionList::factory()->create([
        'user_id' => $this->operator->id,
    ]);

    // Test with missing required configuration fields
    $response = $this->actingAs($this->operator)
        ->post(route('report-schedules.store'), [
            'name' => 'Invalid Config Report',
            'distribution_list_id' => $distributionList->id,
            'report_type' => 'capacity',
            'report_configuration' => [
                // Missing columns
                'filters' => [],
            ],
            'frequency' => 'daily',
            'time_of_day' => '08:00',
            'timezone' => 'UTC',
            'format' => 'pdf',
        ]);

    $response->assertSessionHasErrors(['report_configuration.columns']);

    // Test with invalid report type
    $response = $this->actingAs($this->operator)
        ->post(route('report-schedules.store'), [
            'name' => 'Invalid Report Type',
            'distribution_list_id' => $distributionList->id,
            'report_type' => 'invalid_type',
            'report_configuration' => [
                'columns' => ['rack_name'],
                'filters' => [],
                'sort' => [],
                'group_by' => null,
            ],
            'frequency' => 'daily',
            'time_of_day' => '08:00',
            'timezone' => 'UTC',
            'format' => 'pdf',
        ]);

    $response->assertSessionHasErrors(['report_type']);
});

test('toggle enables and disables schedule', function () {
    $distributionList = DistributionList::factory()->create([
        'user_id' => $this->operator->id,
    ]);

    $schedule = ReportSchedule::factory()->create([
        'user_id' => $this->operator->id,
        'distribution_list_id' => $distributionList->id,
        'is_enabled' => true,
    ]);

    // Disable the schedule
    $response = $this->actingAs($this->operator)
        ->patch(route('report-schedules.toggle', $schedule), [
            'is_enabled' => false,
        ]);

    $response->assertRedirect();
    $schedule->refresh();
    expect($schedule->is_enabled)->toBeFalse();

    // Re-enable the schedule
    $response = $this->actingAs($this->operator)
        ->patch(route('report-schedules.toggle', $schedule), [
            'is_enabled' => true,
        ]);

    $response->assertRedirect();
    $schedule->refresh();
    expect($schedule->is_enabled)->toBeTrue();
});

test('destroy soft deletes schedule', function () {
    $distributionList = DistributionList::factory()->create([
        'user_id' => $this->operator->id,
    ]);

    $schedule = ReportSchedule::factory()->create([
        'user_id' => $this->operator->id,
        'distribution_list_id' => $distributionList->id,
        'name' => 'Schedule To Delete',
    ]);

    $scheduleId = $schedule->id;

    $response = $this->actingAs($this->operator)
        ->delete(route('report-schedules.destroy', $schedule));

    $response->assertRedirect(route('report-schedules.index'));

    // Verify soft delete
    $this->assertSoftDeleted('report_schedules', ['id' => $scheduleId]);

    // Should still exist in database with deleted_at
    $this->assertDatabaseHas('report_schedules', ['id' => $scheduleId]);
});

test('execution history retrieval returns paginated results', function () {
    $distributionList = DistributionList::factory()->create([
        'user_id' => $this->operator->id,
    ]);

    $schedule = ReportSchedule::factory()->create([
        'user_id' => $this->operator->id,
        'distribution_list_id' => $distributionList->id,
    ]);

    // Create multiple execution records
    ReportScheduleExecution::factory()->success()->count(3)->create([
        'report_schedule_id' => $schedule->id,
    ]);
    ReportScheduleExecution::factory()->failed()->count(2)->create([
        'report_schedule_id' => $schedule->id,
    ]);

    $response = $this->actingAs($this->operator)
        ->get(route('report-schedules.history', $schedule));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'status',
                    'started_at',
                    'completed_at',
                    'error_message',
                    'file_size_bytes',
                    'recipients_count',
                    'duration_seconds',
                ],
            ],
            'links',
            'meta',
        ])
        ->assertJsonCount(5, 'data');
});

test('re-enable after failure resets consecutive failures', function () {
    $distributionList = DistributionList::factory()->create([
        'user_id' => $this->operator->id,
    ]);

    $schedule = ReportSchedule::factory()->disabledDueToFailures()->create([
        'user_id' => $this->operator->id,
        'distribution_list_id' => $distributionList->id,
        'consecutive_failures' => 3,
    ]);

    expect($schedule->is_enabled)->toBeFalse();
    expect($schedule->consecutive_failures)->toBe(3);

    // Re-enable the schedule
    $response = $this->actingAs($this->operator)
        ->patch(route('report-schedules.toggle', $schedule), [
            'is_enabled' => true,
        ]);

    $response->assertRedirect();
    $schedule->refresh();

    expect($schedule->is_enabled)->toBeTrue();
    expect($schedule->consecutive_failures)->toBe(0);
    expect($schedule->next_run_at)->not->toBeNull();
});

test('unauthorized user cannot access another users schedule', function () {
    $otherOperator = User::factory()->create();
    $otherOperator->assignRole('Operator');

    $distributionList = DistributionList::factory()->create([
        'user_id' => $otherOperator->id,
    ]);

    $otherUsersSchedule = ReportSchedule::factory()->create([
        'user_id' => $otherOperator->id,
        'distribution_list_id' => $distributionList->id,
    ]);

    // Operator should not be able to view another operator's schedule
    $response = $this->actingAs($this->operator)
        ->get(route('report-schedules.show', $otherUsersSchedule));
    $response->assertForbidden();

    // Operator should not be able to toggle another operator's schedule
    $response = $this->actingAs($this->operator)
        ->patch(route('report-schedules.toggle', $otherUsersSchedule), [
            'is_enabled' => false,
        ]);
    $response->assertForbidden();

    // Operator should not be able to delete another operator's schedule
    $response = $this->actingAs($this->operator)
        ->delete(route('report-schedules.destroy', $otherUsersSchedule));
    $response->assertForbidden();

    // Admin CAN manage any schedule
    $response = $this->actingAs($this->admin)
        ->get(route('report-schedules.show', $otherUsersSchedule));
    $response->assertOk();
});
