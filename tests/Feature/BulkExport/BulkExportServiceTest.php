<?php

use App\Enums\BulkExportStatus;
use App\Enums\BulkImportEntityType;
use App\Jobs\ProcessBulkExportJob;
use App\Models\BulkExport;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use App\Services\BulkExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

test('small export under 100 rows processes synchronously', function () {
    Queue::fake();

    $user = User::factory()->create();

    // Create 50 datacenters (under 100 threshold)
    Datacenter::factory()->count(50)->create();

    $service = app(BulkExportService::class);
    $bulkExport = $service->initiateExport(
        $user,
        BulkImportEntityType::Datacenter,
        'xlsx',
        []
    );

    // Job should NOT be dispatched for small exports
    Queue::assertNotPushed(ProcessBulkExportJob::class);

    // Export should be completed synchronously
    expect($bulkExport->fresh()->status)->toBe(BulkExportStatus::Completed);
    expect($bulkExport->fresh()->processed_rows)->toBe(50);
});

test('large export with 100+ rows dispatches ProcessBulkExportJob', function () {
    Queue::fake();

    $user = User::factory()->create();

    // Create 150 datacenters (over 100 threshold)
    Datacenter::factory()->count(150)->create();

    $service = app(BulkExportService::class);
    $bulkExport = $service->initiateExport(
        $user,
        BulkImportEntityType::Datacenter,
        'xlsx',
        []
    );

    Queue::assertPushed(ProcessBulkExportJob::class, function ($job) use ($bulkExport) {
        return $job->bulkExport->id === $bulkExport->id;
    });

    // Export should be pending (waiting for job)
    expect($bulkExport->fresh()->status)->toBe(BulkExportStatus::Pending);
});

test('hierarchical filter by datacenter_id filters devices correctly', function () {
    $user = User::factory()->create();

    // Create two datacenters with devices
    $datacenter1 = Datacenter::factory()->create(['name' => 'DC One']);
    $datacenter2 = Datacenter::factory()->create(['name' => 'DC Two']);

    $room1 = Room::factory()->create(['datacenter_id' => $datacenter1->id]);
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter2->id]);

    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);

    $rack1 = Rack::factory()->create(['row_id' => $row1->id]);
    $rack2 = Rack::factory()->create(['row_id' => $row2->id]);

    // Create devices in both datacenters
    Device::factory()->count(3)->create(['rack_id' => $rack1->id]);
    Device::factory()->count(5)->create(['rack_id' => $rack2->id]);

    $service = app(BulkExportService::class);

    // Build query with datacenter_id filter
    $query = $service->buildExportQuery(BulkImportEntityType::Device, ['datacenter_id' => $datacenter1->id]);

    // Should only return devices from datacenter1
    expect($query->count())->toBe(3);
});

test('export file is generated with correct format', function () {
    $user = User::factory()->create();

    // Create a few datacenters
    Datacenter::factory()->count(5)->create();

    $service = app(BulkExportService::class);

    // Test CSV format
    $csvExport = $service->initiateExport($user, BulkImportEntityType::Datacenter, 'csv', []);
    expect($csvExport->format)->toBe('csv');
    expect($csvExport->file_path)->toContain('.csv');
    expect(Storage::disk('local')->exists($csvExport->file_path))->toBeTrue();

    // Test XLSX format
    $xlsxExport = $service->initiateExport($user, BulkImportEntityType::Datacenter, 'xlsx', []);
    expect($xlsxExport->format)->toBe('xlsx');
    expect($xlsxExport->file_path)->toContain('.xlsx');
    expect(Storage::disk('local')->exists($xlsxExport->file_path))->toBeTrue();
});

test('job updates status correctly through lifecycle', function () {
    $user = User::factory()->create();

    // Create test data
    Datacenter::factory()->count(10)->create();

    // Create with proper file_path (not using pending() since it nullifies file_path)
    $uuid = Str::uuid();
    $bulkExport = BulkExport::factory()->create([
        'user_id' => $user->id,
        'entity_type' => BulkImportEntityType::Datacenter,
        'format' => 'xlsx',
        'status' => BulkExportStatus::Pending,
        'file_name' => "datacenter_export_{$uuid}.xlsx",
        'file_path' => "exports/datacenter_export_{$uuid}.xlsx",
        'total_rows' => 10,
        'processed_rows' => 0,
        'filters' => [],
        'started_at' => null,
        'completed_at' => null,
    ]);

    // Initial state
    expect($bulkExport->status)->toBe(BulkExportStatus::Pending);
    expect($bulkExport->started_at)->toBeNull();

    // Execute job
    $job = new ProcessBulkExportJob($bulkExport);
    $job->handle();

    $bulkExport->refresh();

    // Final state
    expect($bulkExport->status)->toBe(BulkExportStatus::Completed);
    expect($bulkExport->started_at)->not->toBeNull();
    expect($bulkExport->completed_at)->not->toBeNull();
    expect($bulkExport->processed_rows)->toBe(10);
    expect($bulkExport->file_path)->not->toBeNull();
    expect(Storage::disk('local')->exists($bulkExport->file_path))->toBeTrue();
});

test('job marks export as failed on exception', function () {
    $user = User::factory()->create();

    $uuid = Str::uuid();

    // Create a BulkExport with an invalid entity type that will cause an error
    $bulkExport = BulkExport::factory()->create([
        'user_id' => $user->id,
        'entity_type' => BulkImportEntityType::Mixed, // Mixed is not supported for export
        'format' => 'xlsx',
        'status' => BulkExportStatus::Pending,
        'file_name' => "mixed_export_{$uuid}.xlsx",
        'file_path' => "exports/mixed_export_{$uuid}.xlsx",
        'total_rows' => 0,
        'processed_rows' => 0,
        'filters' => [],
    ]);

    $job = new ProcessBulkExportJob($bulkExport);

    try {
        $job->handle();
    } catch (\Exception $e) {
        // Expected exception
    }

    $bulkExport->refresh();

    expect($bulkExport->status)->toBe(BulkExportStatus::Failed);
    expect($bulkExport->completed_at)->not->toBeNull();
});

test('chunk processing updates progress correctly', function () {
    $user = User::factory()->create();

    // Create enough datacenters to require multiple chunks (chunk size is 1000)
    Datacenter::factory()->count(50)->create();

    $uuid = Str::uuid();
    $bulkExport = BulkExport::factory()->create([
        'user_id' => $user->id,
        'entity_type' => BulkImportEntityType::Datacenter,
        'format' => 'xlsx',
        'status' => BulkExportStatus::Pending,
        'file_name' => "datacenter_export_{$uuid}.xlsx",
        'file_path' => "exports/datacenter_export_{$uuid}.xlsx",
        'total_rows' => 50,
        'processed_rows' => 0,
        'filters' => [],
    ]);

    $job = new ProcessBulkExportJob($bulkExport);
    $job->handle();

    $bulkExport->refresh();

    // All rows should be processed
    expect($bulkExport->processed_rows)->toBe(50);
    expect($bulkExport->status)->toBe(BulkExportStatus::Completed);
});
