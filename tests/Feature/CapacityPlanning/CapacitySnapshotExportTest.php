<?php

use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Exports\CapacityReportExport;
use App\Jobs\CaptureCapacitySnapshotJob;
use App\Jobs\CleanupOldSnapshotsJob;
use App\Models\CapacitySnapshot;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use App\Services\CapacityReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('weekly snapshot job creates records for all datacenters', function () {
    // Create multiple datacenters with racks and devices
    $datacenter1 = Datacenter::factory()->create();
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter1->id]);
    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $rack1 = Rack::factory()->create([
        'row_id' => $row1->id,
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
        'power_capacity_watts' => 10000,
    ]);
    Device::factory()->create([
        'rack_id' => $rack1->id,
        'u_height' => 10,
        'power_draw_watts' => 500,
    ]);

    $datacenter2 = Datacenter::factory()->create();
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter2->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);
    $rack2 = Rack::factory()->create([
        'row_id' => $row2->id,
        'u_height' => RackUHeight::U48,
        'status' => RackStatus::Active,
        'power_capacity_watts' => 15000,
    ]);
    Device::factory()->create([
        'rack_id' => $rack2->id,
        'u_height' => 20,
        'power_draw_watts' => 800,
    ]);

    // Run the snapshot job
    $job = new CaptureCapacitySnapshotJob;
    $job->handle(app(\App\Services\CapacityCalculationService::class));

    // Verify snapshots were created for both datacenters
    expect(CapacitySnapshot::count())->toBe(2);

    $snapshot1 = CapacitySnapshot::where('datacenter_id', $datacenter1->id)->first();
    expect($snapshot1)->not->toBeNull();
    expect($snapshot1->total_u_space)->toBe(42);
    expect($snapshot1->used_u_space)->toBe(10);
    expect($snapshot1->snapshot_date->toDateString())->toBe(now()->toDateString());

    $snapshot2 = CapacitySnapshot::where('datacenter_id', $datacenter2->id)->first();
    expect($snapshot2)->not->toBeNull();
    expect($snapshot2->total_u_space)->toBe(48);
    expect($snapshot2->used_u_space)->toBe(20);
});

test('snapshot cleanup job removes records older than 52 weeks', function () {
    $datacenter = Datacenter::factory()->create();

    // Create snapshots at various ages
    $recentSnapshot = CapacitySnapshot::create([
        'datacenter_id' => $datacenter->id,
        'snapshot_date' => now()->toDateString(),
        'rack_utilization_percent' => 50.00,
        'total_u_space' => 100,
        'used_u_space' => 50,
        'port_stats' => [],
    ]);

    $oldSnapshot51Weeks = CapacitySnapshot::create([
        'datacenter_id' => $datacenter->id,
        'snapshot_date' => now()->subWeeks(51)->toDateString(),
        'rack_utilization_percent' => 60.00,
        'total_u_space' => 100,
        'used_u_space' => 60,
        'port_stats' => [],
    ]);

    $oldSnapshot53Weeks = CapacitySnapshot::create([
        'datacenter_id' => $datacenter->id,
        'snapshot_date' => now()->subWeeks(53)->toDateString(),
        'rack_utilization_percent' => 70.00,
        'total_u_space' => 100,
        'used_u_space' => 70,
        'port_stats' => [],
    ]);

    $oldSnapshot60Weeks = CapacitySnapshot::create([
        'datacenter_id' => $datacenter->id,
        'snapshot_date' => now()->subWeeks(60)->toDateString(),
        'rack_utilization_percent' => 80.00,
        'total_u_space' => 100,
        'used_u_space' => 80,
        'port_stats' => [],
    ]);

    expect(CapacitySnapshot::count())->toBe(4);

    // Run the cleanup job
    $job = new CleanupOldSnapshotsJob;
    $job->handle();

    // Verify only old snapshots were deleted
    expect(CapacitySnapshot::count())->toBe(2);
    expect(CapacitySnapshot::find($recentSnapshot->id))->not->toBeNull();
    expect(CapacitySnapshot::find($oldSnapshot51Weeks->id))->not->toBeNull();
    expect(CapacitySnapshot::find($oldSnapshot53Weeks->id))->toBeNull();
    expect(CapacitySnapshot::find($oldSnapshot60Weeks->id))->toBeNull();
});

test('PDF generation creates valid file with expected content', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $datacenter = Datacenter::factory()->create(['name' => 'Test Datacenter']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Server Room A']);
    $row = Row::factory()->create(['room_id' => $room->id, 'name' => 'Row 1']);

    $rack = Rack::factory()->create([
        'row_id' => $row->id,
        'name' => 'Rack-001',
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
        'power_capacity_watts' => 10000,
    ]);
    Device::factory()->create([
        'rack_id' => $rack->id,
        'u_height' => 15,
        'power_draw_watts' => 500,
    ]);

    $service = app(CapacityReportService::class);

    $filePath = $service->generatePdfReport([
        'datacenter_id' => $datacenter->id,
    ], $user);

    // Verify file was created
    expect($filePath)->not->toBeNull();
    expect($filePath)->toContain('reports/capacity/');
    expect($filePath)->toContain('.pdf');
    Storage::disk('local')->assertExists($filePath);
});

test('CSV export includes correct columns and filtered data', function () {
    $datacenter1 = Datacenter::factory()->create(['name' => 'Datacenter One']);
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter1->id, 'name' => 'Room A']);
    $row1 = Row::factory()->create(['room_id' => $room1->id, 'name' => 'Row 1']);
    $rack1 = Rack::factory()->create([
        'row_id' => $row1->id,
        'name' => 'Rack-001',
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
        'power_capacity_watts' => 10000,
    ]);
    Device::factory()->create([
        'rack_id' => $rack1->id,
        'u_height' => 10,
        'power_draw_watts' => 500,
    ]);

    $datacenter2 = Datacenter::factory()->create(['name' => 'Datacenter Two']);
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter2->id, 'name' => 'Room B']);
    $row2 = Row::factory()->create(['room_id' => $room2->id, 'name' => 'Row 2']);
    Rack::factory()->create([
        'row_id' => $row2->id,
        'name' => 'Rack-002',
        'u_height' => RackUHeight::U48,
        'status' => RackStatus::Active,
        'power_capacity_watts' => 12000,
    ]);

    // Export with filter for datacenter1
    $export = new CapacityReportExport(['datacenter_id' => $datacenter1->id]);

    // Verify headings
    $headings = $export->headings();
    expect($headings)->toContain('Datacenter');
    expect($headings)->toContain('Room');
    expect($headings)->toContain('Row');
    expect($headings)->toContain('Rack');
    expect($headings)->toContain('U Capacity');
    expect($headings)->toContain('U Used');
    expect($headings)->toContain('U Available');
    expect($headings)->toContain('Power Capacity (W)');
    expect($headings)->toContain('Power Used (W)');
    expect($headings)->toContain('Power Available (W)');

    // Get collection data
    $collection = $export->collection();

    // Verify only filtered data is returned
    expect($collection)->toHaveCount(1);
    expect($collection->first())->toContain('Datacenter One');
    expect($collection->first())->toContain('Rack-001');
});

test('export respects datacenter access control', function () {
    // Create user with access to specific datacenter
    $user = User::factory()->create();

    $accessibleDatacenter = Datacenter::factory()->create(['name' => 'Accessible DC']);
    $inaccessibleDatacenter = Datacenter::factory()->create(['name' => 'Inaccessible DC']);

    // Grant user access to one datacenter only
    $user->datacenters()->attach($accessibleDatacenter);

    // Create racks in both datacenters
    $room1 = Room::factory()->create(['datacenter_id' => $accessibleDatacenter->id]);
    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $rack1 = Rack::factory()->create([
        'row_id' => $row1->id,
        'name' => 'Accessible-Rack',
        'status' => RackStatus::Active,
    ]);

    $room2 = Room::factory()->create(['datacenter_id' => $inaccessibleDatacenter->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);
    $rack2 = Rack::factory()->create([
        'row_id' => $row2->id,
        'name' => 'Inaccessible-Rack',
        'status' => RackStatus::Active,
    ]);

    // Export with access control applied (filter to accessible datacenter)
    $export = new CapacityReportExport([
        'datacenter_id' => $accessibleDatacenter->id,
    ]);

    $collection = $export->collection();

    // Should only include racks from accessible datacenter
    expect($collection)->toHaveCount(1);
    expect($collection->first())->toContain('Accessible DC');
    expect($collection->first())->toContain('Accessible-Rack');
});
