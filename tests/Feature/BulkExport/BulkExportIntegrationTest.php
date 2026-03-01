<?php

use App\Enums\BulkExportStatus;
use App\Enums\BulkImportEntityType;
use App\Jobs\ProcessBulkExportJob;
use App\Models\BulkExport;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use App\Services\BulkExportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Test 1: End-to-end workflow - create export, process job, download file
 */
test('end-to-end workflow: create export, process, and download', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create test data
    $datacenter = Datacenter::factory()->create(['name' => 'E2E Test DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'E2E Test Room']);
    $row = Row::factory()->create(['room_id' => $room->id, 'name' => 'E2E Test Row']);
    Rack::factory()->count(3)->create(['row_id' => $row->id]);

    // Step 1: Initiate export via service
    $service = app(BulkExportService::class);
    $bulkExport = $service->initiateExport(
        $admin,
        BulkImportEntityType::Rack,
        'xlsx',
        ['datacenter_id' => $datacenter->id]
    );

    // Step 2: Verify export is completed (sync for small dataset)
    expect($bulkExport->fresh()->status)->toBe(BulkExportStatus::Completed);
    expect($bulkExport->fresh()->processed_rows)->toBe(3);

    // Step 3: Verify file exists
    expect(Storage::disk('local')->exists($bulkExport->file_path))->toBeTrue();

    // Step 4: Download via controller
    $response = $this->actingAs($admin)
        ->get("/exports/{$bulkExport->id}/download");

    $response->assertOk();
    $response->assertDownload();
});

/**
 * Test 2: Hierarchical filters applied correctly across all entity types
 */
test('hierarchical filters applied correctly across all entity types', function () {
    $user = User::factory()->create();

    // Create a complete hierarchy
    $dc1 = Datacenter::factory()->create(['name' => 'DC1']);
    $dc2 = Datacenter::factory()->create(['name' => 'DC2']);

    $room1 = Room::factory()->create(['datacenter_id' => $dc1->id]);
    $room2 = Room::factory()->create(['datacenter_id' => $dc2->id]);

    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);

    $rack1 = Rack::factory()->create(['row_id' => $row1->id]);
    $rack2 = Rack::factory()->create(['row_id' => $row2->id]);

    $deviceType = DeviceType::factory()->create();

    $device1 = Device::factory()->create(['rack_id' => $rack1->id, 'device_type_id' => $deviceType->id]);
    $device2 = Device::factory()->create(['rack_id' => $rack2->id, 'device_type_id' => $deviceType->id]);

    // Create ports with unique labels per device
    Port::factory()->create(['device_id' => $device1->id, 'label' => 'port1']);
    Port::factory()->create(['device_id' => $device1->id, 'label' => 'port2']);
    Port::factory()->create(['device_id' => $device2->id, 'label' => 'port1']);
    Port::factory()->create(['device_id' => $device2->id, 'label' => 'port2']);
    Port::factory()->create(['device_id' => $device2->id, 'label' => 'port3']);

    $service = app(BulkExportService::class);

    // Test filtering by datacenter_id for each entity type
    $roomQuery = $service->buildExportQuery(BulkImportEntityType::Room, ['datacenter_id' => $dc1->id]);
    expect($roomQuery->count())->toBe(1);

    $rowQuery = $service->buildExportQuery(BulkImportEntityType::Row, ['datacenter_id' => $dc1->id]);
    expect($rowQuery->count())->toBe(1);

    $rackQuery = $service->buildExportQuery(BulkImportEntityType::Rack, ['datacenter_id' => $dc1->id]);
    expect($rackQuery->count())->toBe(1);

    $deviceQuery = $service->buildExportQuery(BulkImportEntityType::Device, ['datacenter_id' => $dc1->id]);
    expect($deviceQuery->count())->toBe(1);

    $portQuery = $service->buildExportQuery(BulkImportEntityType::Port, ['datacenter_id' => $dc1->id]);
    expect($portQuery->count())->toBe(2);
});

/**
 * Test 3: Export with empty result set handles gracefully
 */
test('export with empty result set handles gracefully', function () {
    $user = User::factory()->create();

    // Create datacenter but filter by non-existent ID
    Datacenter::factory()->create();

    $service = app(BulkExportService::class);
    $bulkExport = $service->initiateExport(
        $user,
        BulkImportEntityType::Room,
        'csv',
        ['datacenter_id' => 99999] // Non-existent datacenter
    );

    expect($bulkExport->total_rows)->toBe(0);
    expect($bulkExport->processed_rows)->toBe(0);
    expect($bulkExport->status)->toBe(BulkExportStatus::Completed);
    expect(Storage::disk('local')->exists($bulkExport->file_path))->toBeTrue();
});

/**
 * Test 4: Export with maximum filter depth (all filters applied)
 */
test('export with maximum filter depth applies all filters correctly', function () {
    $user = User::factory()->create();

    // Create complete hierarchy
    $dc = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $dc->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $deviceType = DeviceType::factory()->create();
    $device = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id]);

    // Create another device in same rack
    Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id]);

    // Create device in different rack
    $otherRow = Row::factory()->create(['room_id' => $room->id]);
    $otherRack = Rack::factory()->create(['row_id' => $otherRow->id]);
    Device::factory()->create(['rack_id' => $otherRack->id, 'device_type_id' => $deviceType->id]);

    $service = app(BulkExportService::class);

    // Apply all filters at maximum depth
    $query = $service->buildExportQuery(BulkImportEntityType::Device, [
        'datacenter_id' => $dc->id,
        'room_id' => $room->id,
        'row_id' => $row->id,
        'rack_id' => $rack->id,
    ]);

    // Should return only devices in the specific rack (2 devices)
    expect($query->count())->toBe(2);
});

/**
 * Test 5: Concurrent exports are handled independently
 */
test('concurrent exports are handled independently', function () {
    $user = User::factory()->create();

    // Create datacenters
    $datacenters = Datacenter::factory()->count(10)->create();

    // Create rooms explicitly attached to specific datacenters
    $rooms = collect();
    foreach ($datacenters->take(5) as $dc) {
        $rooms->push(Room::factory()->create(['datacenter_id' => $dc->id]));
    }

    $service = app(BulkExportService::class);

    // Get counts before export
    $datacenterCount = Datacenter::count();
    $roomCount = Room::count();

    // Create multiple exports simultaneously
    $export1 = $service->initiateExport($user, BulkImportEntityType::Datacenter, 'xlsx', []);
    $export2 = $service->initiateExport($user, BulkImportEntityType::Room, 'csv', []);

    // Both should complete successfully
    expect($export1->fresh()->status)->toBe(BulkExportStatus::Completed);
    expect($export2->fresh()->status)->toBe(BulkExportStatus::Completed);

    // Files should be different
    expect($export1->file_path)->not->toBe($export2->file_path);
    expect(Storage::disk('local')->exists($export1->file_path))->toBeTrue();
    expect(Storage::disk('local')->exists($export2->file_path))->toBeTrue();

    // Both should have correct row counts
    expect($export1->processed_rows)->toBe($datacenterCount);
    expect($export2->processed_rows)->toBe($roomCount);
});

/**
 * Test 6: File cleanup command removes old files correctly
 */
test('cleanup command removes exports older than retention period', function () {
    $user = User::factory()->create();

    // Create an old export (8 days ago)
    $oldFilePath = 'exports/old_export.xlsx';
    Storage::disk('local')->put($oldFilePath, 'old content');

    $oldExport = BulkExport::factory()->completed()->create([
        'user_id' => $user->id,
        'file_path' => $oldFilePath,
        'file_name' => 'old_export.xlsx',
        'created_at' => now()->subDays(8),
    ]);

    // Create a recent export (2 days ago)
    $recentFilePath = 'exports/recent_export.xlsx';
    Storage::disk('local')->put($recentFilePath, 'recent content');

    $recentExport = BulkExport::factory()->completed()->create([
        'user_id' => $user->id,
        'file_path' => $recentFilePath,
        'file_name' => 'recent_export.xlsx',
        'created_at' => now()->subDays(2),
    ]);

    // Run cleanup command with default 7 days
    Artisan::call('exports:cleanup');

    // Old export should be deleted
    expect(BulkExport::find($oldExport->id))->toBeNull();
    expect(Storage::disk('local')->exists($oldFilePath))->toBeFalse();

    // Recent export should still exist
    expect(BulkExport::find($recentExport->id))->not->toBeNull();
    expect(Storage::disk('local')->exists($recentFilePath))->toBeTrue();
});

/**
 * Test 7: Cleanup command with custom retention period
 */
test('cleanup command respects custom retention period', function () {
    $user = User::factory()->create();

    // Create export from 4 days ago
    $filePath = 'exports/export_4_days.xlsx';
    Storage::disk('local')->put($filePath, 'test content');

    $export = BulkExport::factory()->completed()->create([
        'user_id' => $user->id,
        'file_path' => $filePath,
        'file_name' => 'export_4_days.xlsx',
        'created_at' => now()->subDays(4),
    ]);

    // With default 7 days, should not be deleted
    Artisan::call('exports:cleanup', ['--days' => 7]);
    expect(BulkExport::find($export->id))->not->toBeNull();

    // With 3 days retention, should be deleted
    Artisan::call('exports:cleanup', ['--days' => 3]);
    expect(BulkExport::find($export->id))->toBeNull();
    expect(Storage::disk('local')->exists($filePath))->toBeFalse();
});

/**
 * Test 8: Large export queues job correctly and processes via job
 */
test('large export processes via queued job', function () {
    Queue::fake();

    $user = User::factory()->create();

    // Create more than 100 datacenters to trigger async processing
    Datacenter::factory()->count(150)->create();

    $service = app(BulkExportService::class);
    $bulkExport = $service->initiateExport(
        $user,
        BulkImportEntityType::Datacenter,
        'xlsx',
        []
    );

    // Job should be dispatched
    Queue::assertPushed(ProcessBulkExportJob::class, function ($job) use ($bulkExport) {
        return $job->bulkExport->id === $bulkExport->id;
    });

    // Export should be pending initially
    expect($bulkExport->fresh()->status)->toBe(BulkExportStatus::Pending);
    expect($bulkExport->fresh()->total_rows)->toBe(150);

    // Now actually process the job
    Queue::fake([]);
    $uuid = Str::uuid();
    $bulkExport->update([
        'file_name' => "datacenter_export_{$uuid}.xlsx",
        'file_path' => "exports/datacenter_export_{$uuid}.xlsx",
    ]);

    $job = new ProcessBulkExportJob($bulkExport);
    $job->handle();

    // Verify completed successfully
    $bulkExport->refresh();
    expect($bulkExport->status)->toBe(BulkExportStatus::Completed);
    expect($bulkExport->processed_rows)->toBe(150);
    expect(Storage::disk('local')->exists($bulkExport->file_path))->toBeTrue();
});

/**
 * Test 9: Store request with filters creates export with stored filters
 */
test('store request with filters creates export with stored filters', function () {
    $this->withoutVite();
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);

    $response = $this->actingAs($admin)
        ->post('/exports', [
            'entity_type' => 'device',
            'format' => 'xlsx',
            'datacenter_id' => $datacenter->id,
            'room_id' => $room->id,
        ]);

    $response->assertRedirect();

    $export = BulkExport::latest()->first();
    expect($export->filters['datacenter_id'])->toBe($datacenter->id);
    expect($export->filters['room_id'])->toBe($room->id);
});
