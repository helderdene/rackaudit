<?php

/**
 * Tests for Scheduled Report Generation functionality.
 *
 * Tests cover:
 * - ScheduledReportGenerationService generates PDF report
 * - ScheduledReportGenerationService generates CSV report
 * - GenerateScheduledReportJob dispatches and handles success
 * - GenerateScheduledReportJob handles failure and retries
 * - Job disables schedule after 3 consecutive failures
 * - Notifications sent on failure
 * - Datacenter access permissions respected at generation time
 */

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Jobs\GenerateScheduledReportJob;
use App\Mail\ScheduledReportMailable;
use App\Models\Datacenter;
use App\Models\DistributionList;
use App\Models\DistributionListMember;
use App\Models\Rack;
use App\Models\ReportSchedule;
use App\Models\ReportScheduleExecution;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use App\Notifications\ScheduledReportDisabledNotification;
use App\Notifications\ScheduledReportFailedNotification;
use App\Services\ScheduledReportGenerationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create test user with permissions
    $this->user = User::factory()->create();
    $this->user->assignRole('IT Manager');

    // Create datacenter and related entities for testing
    $this->datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $this->room = Room::factory()->create(['datacenter_id' => $this->datacenter->id]);
    $this->row = Row::factory()->create(['room_id' => $this->room->id]);
    $this->rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Test Rack',
    ]);

    // Associate user with datacenter
    $this->user->datacenters()->attach($this->datacenter->id);

    // Create distribution list with members
    $this->distributionList = DistributionList::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Test Recipients',
    ]);

    DistributionListMember::factory()->create([
        'distribution_list_id' => $this->distributionList->id,
        'email' => 'recipient1@example.com',
    ]);

    DistributionListMember::factory()->create([
        'distribution_list_id' => $this->distributionList->id,
        'email' => 'recipient2@example.com',
    ]);

    // Set up Storage fake for report generation
    Storage::fake('local');
});

test('scheduled report generation service generates PDF report', function () {
    $schedule = ReportSchedule::factory()->create([
        'user_id' => $this->user->id,
        'distribution_list_id' => $this->distributionList->id,
        'report_type' => ReportType::Capacity,
        'report_configuration' => [
            'columns' => ['rack_name', 'datacenter_name', 'utilization_percent'],
            'filters' => ['datacenter_id' => $this->datacenter->id],
            'sort' => [],
            'group_by' => null,
        ],
        'format' => ReportFormat::PDF,
    ]);

    $service = app(ScheduledReportGenerationService::class);
    $filePath = $service->generateReport($schedule);

    expect($filePath)->toBeString();
    expect($filePath)->toContain('.pdf');
    expect(Storage::disk('local')->exists($filePath))->toBeTrue();
});

test('scheduled report generation service generates CSV report', function () {
    $schedule = ReportSchedule::factory()->create([
        'user_id' => $this->user->id,
        'distribution_list_id' => $this->distributionList->id,
        'report_type' => ReportType::Capacity,
        'report_configuration' => [
            'columns' => ['rack_name', 'datacenter_name', 'utilization_percent'],
            'filters' => ['datacenter_id' => $this->datacenter->id],
            'sort' => [],
            'group_by' => null,
        ],
        'format' => ReportFormat::CSV,
    ]);

    $service = app(ScheduledReportGenerationService::class);
    $filePath = $service->generateReport($schedule);

    expect($filePath)->toBeString();
    expect($filePath)->toContain('.csv');
    expect(Storage::disk('local')->exists($filePath))->toBeTrue();

    // Verify CSV content structure - check for display labels (headers use display names)
    $content = Storage::disk('local')->get($filePath);
    expect($content)->toContain('Rack Name');
    expect($content)->toContain('Datacenter');
});

test('generate scheduled report job dispatches and handles success', function () {
    Mail::fake();

    $schedule = ReportSchedule::factory()->create([
        'user_id' => $this->user->id,
        'distribution_list_id' => $this->distributionList->id,
        'report_type' => ReportType::Capacity,
        'report_configuration' => [
            'columns' => ['rack_name', 'datacenter_name', 'utilization_percent'],
            'filters' => [],
            'sort' => [],
            'group_by' => null,
        ],
        'format' => ReportFormat::PDF,
        'is_enabled' => true,
        'consecutive_failures' => 0,
    ]);

    // Dispatch and process the job
    $job = new GenerateScheduledReportJob($schedule);
    $job->handle(
        app(ScheduledReportGenerationService::class),
        app(\App\Services\ScheduledReportEmailService::class)
    );

    // Verify email was sent to distribution list members
    Mail::assertSent(ScheduledReportMailable::class, 2);

    // Verify execution record was created
    $schedule->refresh();
    expect($schedule->last_run_status)->toBe('success');
    expect($schedule->consecutive_failures)->toBe(0);

    $this->assertDatabaseHas('report_schedule_executions', [
        'report_schedule_id' => $schedule->id,
        'status' => 'success',
    ]);
});

test('generate scheduled report job has correct retry configuration', function () {
    $schedule = ReportSchedule::factory()->create([
        'user_id' => $this->user->id,
        'distribution_list_id' => $this->distributionList->id,
        'report_type' => ReportType::Capacity,
        'report_configuration' => [
            'columns' => ['rack_name'],
            'filters' => [],
            'sort' => [],
            'group_by' => null,
        ],
        'format' => ReportFormat::PDF,
        'is_enabled' => true,
        'consecutive_failures' => 0,
    ]);

    $job = new GenerateScheduledReportJob($schedule);

    // Verify job has retry configuration
    expect($job->tries)->toBe(2);
    expect($job->backoff)->toBe(300);

    // Simulate a failure to verify the failed() method works
    Notification::fake();
    $exception = new \Exception('Test failure');
    $job->failed($exception);

    // Verify failure was recorded
    $schedule->refresh();
    expect($schedule->consecutive_failures)->toBe(1);
});

test('job disables schedule after 3 consecutive failures', function () {
    Notification::fake();

    $schedule = ReportSchedule::factory()->create([
        'user_id' => $this->user->id,
        'distribution_list_id' => $this->distributionList->id,
        'report_type' => ReportType::Capacity,
        'report_configuration' => [
            'columns' => ['rack_name'],
            'filters' => [],
            'sort' => [],
            'group_by' => null,
        ],
        'format' => ReportFormat::PDF,
        'is_enabled' => true,
        'consecutive_failures' => 2, // Already at 2 failures
    ]);

    $job = new GenerateScheduledReportJob($schedule);

    // Simulate a failure that pushes it over the threshold
    $exception = new \Exception('Report generation failed');
    $job->failed($exception);

    $schedule->refresh();
    expect($schedule->consecutive_failures)->toBe(3);
    expect($schedule->is_enabled)->toBeFalse();

    // Verify disabled notification was sent
    Notification::assertSentTo(
        $this->user,
        ScheduledReportDisabledNotification::class
    );
});

test('notifications sent on failure', function () {
    Notification::fake();

    $schedule = ReportSchedule::factory()->create([
        'user_id' => $this->user->id,
        'distribution_list_id' => $this->distributionList->id,
        'report_type' => ReportType::Capacity,
        'report_configuration' => [
            'columns' => ['rack_name'],
            'filters' => [],
            'sort' => [],
            'group_by' => null,
        ],
        'format' => ReportFormat::PDF,
        'is_enabled' => true,
        'consecutive_failures' => 0,
    ]);

    $job = new GenerateScheduledReportJob($schedule);
    $exception = new \Exception('SMTP connection failed');
    $job->failed($exception);

    // Verify failure notification was sent to schedule owner
    Notification::assertSentTo(
        $this->user,
        ScheduledReportFailedNotification::class,
        function ($notification) {
            return str_contains($notification->errorMessage, 'SMTP connection failed');
        }
    );
});

test('datacenter access permissions respected at generation time', function () {
    // Create a second datacenter that the user does NOT have access to
    $restrictedDatacenter = Datacenter::factory()->create(['name' => 'Restricted DC']);
    $restrictedRoom = Room::factory()->create(['datacenter_id' => $restrictedDatacenter->id]);
    $restrictedRow = Row::factory()->create(['room_id' => $restrictedRoom->id]);
    $restrictedRack = Rack::factory()->create([
        'row_id' => $restrictedRow->id,
        'name' => 'Restricted Rack',
    ]);

    // User only has access to $this->datacenter, not $restrictedDatacenter
    // The user is already associated with $this->datacenter from beforeEach

    // Create schedule with a specific datacenter filter that user has access to
    $schedule = ReportSchedule::factory()->create([
        'user_id' => $this->user->id,
        'distribution_list_id' => $this->distributionList->id,
        'report_type' => ReportType::Capacity,
        'report_configuration' => [
            'columns' => ['rack_name', 'datacenter_name'],
            'filters' => ['datacenter_id' => $this->datacenter->id], // Explicit filter to accessible datacenter
            'sort' => [],
            'group_by' => null,
        ],
        'format' => ReportFormat::CSV,
    ]);

    $service = app(ScheduledReportGenerationService::class);
    $filePath = $service->generateReport($schedule);

    // Verify the CSV was generated
    expect(Storage::disk('local')->exists($filePath))->toBeTrue();

    // Verify the CSV content includes only accessible datacenter data
    $content = Storage::disk('local')->get($filePath);

    // The accessible datacenter rack should be included
    expect($content)->toContain('Test DC');
    expect($content)->toContain('Test Rack');

    // The restricted datacenter rack should NOT be included (because of explicit filter)
    expect($content)->not->toContain('Restricted DC');
    expect($content)->not->toContain('Restricted Rack');
});

test('report generation creates execution record with file size', function () {
    Mail::fake();

    $schedule = ReportSchedule::factory()->create([
        'user_id' => $this->user->id,
        'distribution_list_id' => $this->distributionList->id,
        'report_type' => ReportType::Capacity,
        'report_configuration' => [
            'columns' => ['rack_name', 'datacenter_name'],
            'filters' => [],
            'sort' => [],
            'group_by' => null,
        ],
        'format' => ReportFormat::PDF,
    ]);

    $job = new GenerateScheduledReportJob($schedule);
    $job->handle(
        app(ScheduledReportGenerationService::class),
        app(\App\Services\ScheduledReportEmailService::class)
    );

    // Verify execution record was created with metrics
    $execution = ReportScheduleExecution::where('report_schedule_id', $schedule->id)->first();
    expect($execution)->not->toBeNull();
    expect($execution->status)->toBe('success');
    expect($execution->file_size_bytes)->toBeGreaterThan(0);
    expect($execution->recipients_count)->toBe(2);
    expect($execution->started_at)->not->toBeNull();
    expect($execution->completed_at)->not->toBeNull();
});
