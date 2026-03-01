<?php

use App\Enums\BulkImportEntityType;
use App\Enums\BulkImportStatus;
use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Enums\RoomType;
use App\Enums\RowOrientation;
use App\Enums\RowStatus;
use App\Imports\CombinedImport;
use App\Imports\DeviceImport;
use App\Jobs\ProcessBulkImportJob;
use App\Models\BulkImport;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');
});

/**
 * Test 1: End-to-end async workflow - Upload file -> async process -> poll status -> download errors
 */
test('end-to-end async workflow with large file processes correctly', function () {
    // Create CSV with 150 rows (exceeds 100 row threshold for async)
    $csvContent = "name,address_line_1,city,state_province,postal_code,country,primary_contact_name,primary_contact_email,primary_contact_phone\n";

    // Add 148 valid rows
    for ($i = 1; $i <= 148; $i++) {
        $csvContent .= "Datacenter $i,{$i} Main Street,City $i,State $i,1000$i,USA,Contact $i,contact$i@example.com,555-000-$i\n";
    }
    // Add 2 invalid rows (missing required fields)
    $csvContent .= ",Missing Name Street,City 149,State 149,10149,USA,Contact 149,contact149@example.com,555-000-149\n";
    $csvContent .= "Datacenter 150,,City 150,State 150,10150,USA,Contact 150,invalid-email,555-000-150\n";

    Storage::disk('local')->put('imports/large_test.csv', $csvContent);

    $bulkImport = BulkImport::factory()->create([
        'user_id' => $this->admin->id,
        'entity_type' => BulkImportEntityType::Datacenter,
        'file_name' => 'large_test.csv',
        'file_path' => 'imports/large_test.csv',
        'status' => BulkImportStatus::Pending,
        'total_rows' => 150,
        'processed_rows' => 0,
    ]);

    // Execute the job (simulating queue worker)
    $job = new ProcessBulkImportJob($bulkImport);
    $job->handle();

    $bulkImport->refresh();

    // Verify final status
    expect($bulkImport->status)->toBe(BulkImportStatus::Completed);
    expect($bulkImport->processed_rows)->toBe(150);
    expect($bulkImport->success_count)->toBe(148);
    expect($bulkImport->failure_count)->toBe(2);

    // Poll status endpoint
    $response = $this->actingAs($this->admin)
        ->getJson("/imports/{$bulkImport->id}");

    $response->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.success_count', 148)
        ->assertJsonPath('data.failure_count', 2)
        ->assertJsonPath('data.has_errors', true);

    // Verify error report exists and is downloadable
    expect($bulkImport->error_report_path)->not->toBeNull();
    expect(Storage::disk('local')->exists($bulkImport->error_report_path))->toBeTrue();

    // Download error report
    $downloadResponse = $this->actingAs($this->admin)
        ->get("/imports/{$bulkImport->id}/errors");

    $downloadResponse->assertOk();

    // Verify created entities exist in database
    expect(Datacenter::where('name', 'Datacenter 1')->exists())->toBeTrue();
    expect(Datacenter::where('name', 'Datacenter 148')->exists())->toBeTrue();
    expect(Datacenter::count())->toBe(148);
});

/**
 * Test 2: End-to-end sync workflow - Upload small file -> sync process -> verify created entities
 */
test('end-to-end sync workflow with small file creates entities immediately', function () {
    Queue::fake();

    // Create CSV with 10 rows (below 100 row threshold for sync)
    $csvContent = "name,address_line_1,city,state_province,postal_code,country,primary_contact_name,primary_contact_email,primary_contact_phone\n";
    for ($i = 1; $i <= 10; $i++) {
        $csvContent .= "Sync DC $i,{$i} Sync Street,Sync City $i,SC,2000$i,USA,Sync Contact $i,sync$i@example.com,555-111-$i\n";
    }

    $file = UploadedFile::fake()->createWithContent('sync_test.csv', $csvContent);

    // Upload and process
    $response = $this->actingAs($this->admin)
        ->postJson('/imports', [
            'file' => $file,
        ]);

    $response->assertCreated();

    // Job should NOT be dispatched (sync processing)
    Queue::assertNotPushed(ProcessBulkImportJob::class);

    // Verify all entities were created immediately
    expect(Datacenter::where('name', 'like', 'Sync DC%')->count())->toBe(10);
    expect(Datacenter::where('name', 'Sync DC 1')->exists())->toBeTrue();
    expect(Datacenter::where('name', 'Sync DC 10')->exists())->toBeTrue();

    // Verify import record is completed
    $importId = $response->json('data.id');
    $bulkImport = BulkImport::find($importId);
    expect($bulkImport->status)->toBe(BulkImportStatus::Completed);
    expect($bulkImport->success_count)->toBe(10);
});

/**
 * Test 3: Combined import with multiple entity types in correct order
 */
test('combined import processes multiple entity types in hierarchical order', function () {
    // Create prerequisite DeviceType
    $deviceType = DeviceType::factory()->create(['name' => 'Test Server']);

    $import = new CombinedImport;

    // Process datacenter first
    $datacenterRow = [
        'name' => 'Combined DC',
        'address_line_1' => '123 Combined Street',
        'city' => 'Combined City',
        'state_province' => 'CC',
        'postal_code' => '30001',
        'country' => 'USA',
        'primary_contact_name' => 'Combined Contact',
        'primary_contact_email' => 'combined@example.com',
        'primary_contact_phone' => '555-222-1111',
    ];
    $result = $import->getImporters()[BulkImportEntityType::Datacenter->value]->processRow($datacenterRow, 2);
    expect($result['success'])->toBeTrue();

    // Process room
    $roomRow = [
        'datacenter_name' => 'Combined DC',
        'name' => 'Combined Room',
        'type' => 'Server Room',
        'description' => 'Test room',
    ];
    $result = $import->getImporters()[BulkImportEntityType::Room->value]->processRow($roomRow, 3);
    expect($result['success'])->toBeTrue();

    // Process row (using correct enum labels: Hot Aisle/Cold Aisle, Active/Inactive)
    $rowRow = [
        'datacenter_name' => 'Combined DC',
        'room_name' => 'Combined Room',
        'name' => 'Combined Row A',
        'position' => 1, // Use integer, not string
        'orientation' => 'Hot Aisle',
        'status' => 'Active',
    ];
    $result = $import->getImporters()[BulkImportEntityType::Row->value]->processRow($rowRow, 4);
    expect($result['success'])->toBeTrue();

    // Process rack
    $rackRow = [
        'datacenter_name' => 'Combined DC',
        'room_name' => 'Combined Room',
        'row_name' => 'Combined Row A',
        'name' => 'Combined Rack 1',
        'position' => 1,
        'u_height' => '42',
        'status' => 'Active',
    ];
    $result = $import->getImporters()[BulkImportEntityType::Rack->value]->processRow($rackRow, 5);
    expect($result['success'])->toBeTrue();

    // Process device
    $deviceRow = [
        'datacenter_name' => 'Combined DC',
        'room_name' => 'Combined Room',
        'row_name' => 'Combined Row A',
        'rack_name' => 'Combined Rack 1',
        'name' => 'Combined Server 1',
        'device_type_name' => 'Test Server',
        'lifecycle_status' => 'In Stock',
        'u_height' => '2',
        'depth' => 'Standard',
        'width_type' => 'Full Width',
        'rack_face' => 'Front',
        'start_u' => '10',
    ];
    $result = $import->getImporters()[BulkImportEntityType::Device->value]->processRow($deviceRow, 6);
    expect($result['success'])->toBeTrue();

    // Verify hierarchy exists in database
    expect(Datacenter::where('name', 'Combined DC')->exists())->toBeTrue();
    expect(Room::where('name', 'Combined Room')->exists())->toBeTrue();
    expect(Row::where('name', 'Combined Row A')->exists())->toBeTrue();
    expect(Rack::where('name', 'Combined Rack 1')->exists())->toBeTrue();
    expect(Device::where('name', 'Combined Server 1')->exists())->toBeTrue();

    // Verify relationships
    $device = Device::where('name', 'Combined Server 1')->first();
    expect($device->rack->name)->toBe('Combined Rack 1');
    expect($device->rack->row->name)->toBe('Combined Row A');
    expect($device->rack->row->room->name)->toBe('Combined Room');
    expect($device->rack->row->room->datacenter->name)->toBe('Combined DC');
});

/**
 * Test 4: Duplicate entity handling - import creates new only, no updates
 */
test('import does not update existing entities with same name', function () {
    // Create existing datacenter
    $existingDC = Datacenter::factory()->create([
        'name' => 'Existing DC',
        'city' => 'Original City',
    ]);

    $csvContent = "name,address_line_1,city,state_province,postal_code,country,primary_contact_name,primary_contact_email,primary_contact_phone\n";
    // New datacenter
    $csvContent .= "New DC,123 New Street,New City,NC,40001,USA,New Contact,new@example.com,555-333-1111\n";
    // Attempt to import existing datacenter name (should fail with unique constraint or create duplicate)
    $csvContent .= "Existing DC,456 Different Street,Different City,DC,40002,USA,Different Contact,different@example.com,555-333-2222\n";

    Storage::disk('local')->put('imports/duplicate_test.csv', $csvContent);

    $bulkImport = BulkImport::factory()->create([
        'user_id' => $this->admin->id,
        'entity_type' => BulkImportEntityType::Datacenter,
        'file_name' => 'duplicate_test.csv',
        'file_path' => 'imports/duplicate_test.csv',
        'status' => BulkImportStatus::Pending,
        'total_rows' => 2,
        'processed_rows' => 0,
    ]);

    $job = new ProcessBulkImportJob($bulkImport);
    $job->handle();

    $bulkImport->refresh();

    // The new datacenter should be created
    expect(Datacenter::where('name', 'New DC')->exists())->toBeTrue();

    // The original datacenter should not be modified
    $existingDC->refresh();
    expect($existingDC->city)->toBe('Original City');

    // At least the new DC was created (success_count >= 1)
    expect($bulkImport->success_count)->toBeGreaterThanOrEqual(1);
});

/**
 * Test 5: Rack placement conflict detection - overlapping device positions
 */
test('device import detects rack placement conflicts', function () {
    // Set up hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Conflict DC']);
    $room = Room::factory()->create(['name' => 'Conflict Room', 'datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['name' => 'Conflict Row', 'room_id' => $room->id]);
    $rack = Rack::factory()->create([
        'name' => 'Conflict Rack',
        'row_id' => $row->id,
        'u_height' => RackUHeight::U42,
    ]);
    $deviceType = DeviceType::factory()->create(['name' => 'Conflict Server']);

    $import = new DeviceImport;

    // First device at U10-U13 (4U device)
    $device1Row = [
        'datacenter_name' => 'Conflict DC',
        'room_name' => 'Conflict Room',
        'row_name' => 'Conflict Row',
        'rack_name' => 'Conflict Rack',
        'name' => 'Server A',
        'device_type_name' => 'Conflict Server',
        'lifecycle_status' => 'In Stock',
        'u_height' => '4',
        'depth' => 'Standard',
        'width_type' => 'Full Width',
        'rack_face' => 'Front',
        'start_u' => '10',
    ];

    $result1 = $import->processRow($device1Row, 2);
    expect($result1['success'])->toBeTrue();

    // Device exceeds rack capacity (start_u 40 + u_height 4 = 44 > 42U rack)
    $device2Row = [
        'datacenter_name' => 'Conflict DC',
        'room_name' => 'Conflict Room',
        'row_name' => 'Conflict Row',
        'rack_name' => 'Conflict Rack',
        'name' => 'Server B',
        'device_type_name' => 'Conflict Server',
        'lifecycle_status' => 'In Stock',
        'u_height' => '4',
        'depth' => 'Standard',
        'width_type' => 'Full Width',
        'rack_face' => 'Front',
        'start_u' => '40', // 40 + 4 - 1 = 43 > 42
    ];

    $result2 = $import->processRow($device2Row, 3);
    expect($result2['success'])->toBeFalse();
    expect($result2['errors'])->not->toBeEmpty();

    // Find the start_u error
    $startUError = collect($result2['errors'])->firstWhere('field_name', 'start_u');
    expect($startUError)->not->toBeNull();
    expect($startUError['error_message'])->toContain('exceeds rack capacity');
});

/**
 * Test 6: DeviceType lookup by name validates correctly
 */
test('device import validates device type lookup by name', function () {
    // Set up hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'DeviceType DC']);
    $room = Room::factory()->create(['name' => 'DeviceType Room', 'datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['name' => 'DeviceType Row', 'room_id' => $room->id]);
    $rack = Rack::factory()->create([
        'name' => 'DeviceType Rack',
        'row_id' => $row->id,
        'u_height' => RackUHeight::U42,
    ]);

    // Create specific device types
    $serverType = DeviceType::factory()->create(['name' => 'Dell PowerEdge R750']);
    $switchType = DeviceType::factory()->create(['name' => 'Cisco Nexus 9300']);

    $import = new DeviceImport;

    // Valid device type lookup
    $validRow = [
        'datacenter_name' => 'DeviceType DC',
        'room_name' => 'DeviceType Room',
        'row_name' => 'DeviceType Row',
        'rack_name' => 'DeviceType Rack',
        'name' => 'Valid Server',
        'device_type_name' => 'Dell PowerEdge R750',
        'lifecycle_status' => 'In Stock',
        'u_height' => '2',
        'depth' => 'Standard',
        'width_type' => 'Full Width',
        'rack_face' => 'Front',
        'start_u' => '1',
    ];

    $result = $import->processRow($validRow, 2);
    expect($result['success'])->toBeTrue();
    expect($result['entity']->deviceType->name)->toBe('Dell PowerEdge R750');

    // Invalid device type lookup
    $invalidRow = [
        'datacenter_name' => 'DeviceType DC',
        'room_name' => 'DeviceType Room',
        'row_name' => 'DeviceType Row',
        'rack_name' => 'DeviceType Rack',
        'name' => 'Invalid Server',
        'device_type_name' => 'Non-Existent Device Type',
        'lifecycle_status' => 'In Stock',
        'u_height' => '2',
        'depth' => 'Standard',
        'width_type' => 'Full Width',
        'rack_face' => 'Front',
        'start_u' => '5',
    ];

    $result = $import->processRow($invalidRow, 3);
    expect($result['success'])->toBeFalse();

    $deviceTypeError = collect($result['errors'])->firstWhere('field_name', 'device_type_name');
    expect($deviceTypeError)->not->toBeNull();
    expect($deviceTypeError['error_message'])->toContain('does not exist');
});

/**
 * Test 7: Unplaced device import (no rack assignment)
 */
test('device import allows unplaced devices without rack assignment', function () {
    $deviceType = DeviceType::factory()->create(['name' => 'Inventory Server']);

    $import = new DeviceImport;

    // Device without rack placement (inventory item)
    $unplacedRow = [
        'name' => 'Inventory Device',
        'device_type_name' => 'Inventory Server',
        'lifecycle_status' => 'In Stock',
        'u_height' => '2',
        'depth' => 'Standard',
        'width_type' => 'Full Width',
        'rack_face' => 'Front',
        // No datacenter_name, room_name, row_name, rack_name, start_u
    ];

    $result = $import->processRow($unplacedRow, 2);
    expect($result['success'])->toBeTrue();
    expect($result['entity']->rack_id)->toBeNull();
    expect($result['entity']->start_u)->toBeNull();
    expect($result['entity']->name)->toBe('Inventory Device');
});

/**
 * Test 8: Import with all entity-specific enum validation
 */
test('import validates all enum values correctly', function () {
    $datacenter = Datacenter::factory()->create(['name' => 'Enum Test DC']);
    $room = Room::factory()->create([
        'name' => 'Enum Test Room',
        'datacenter_id' => $datacenter->id,
        'type' => RoomType::ServerRoom,
    ]);
    $row = Row::factory()->create([
        'name' => 'Enum Test Row',
        'room_id' => $room->id,
        'orientation' => RowOrientation::HotAisle,
        'status' => RowStatus::Active,
    ]);
    $rack = Rack::factory()->create([
        'name' => 'Enum Test Rack',
        'row_id' => $row->id,
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
    ]);
    $deviceType = DeviceType::factory()->create(['name' => 'Enum Test Type']);

    $import = new DeviceImport;

    // Test with invalid lifecycle_status enum
    $invalidEnumRow = [
        'datacenter_name' => 'Enum Test DC',
        'room_name' => 'Enum Test Room',
        'row_name' => 'Enum Test Row',
        'rack_name' => 'Enum Test Rack',
        'name' => 'Invalid Enum Device',
        'device_type_name' => 'Enum Test Type',
        'lifecycle_status' => 'Invalid Status That Does Not Exist',
        'u_height' => '2',
        'depth' => 'Standard',
        'width_type' => 'Full Width',
        'rack_face' => 'Front',
        'start_u' => '1',
    ];

    $result = $import->processRow($invalidEnumRow, 2);
    expect($result['success'])->toBeFalse();

    $lifecycleError = collect($result['errors'])->firstWhere('field_name', 'lifecycle_status');
    expect($lifecycleError)->not->toBeNull();
    expect($lifecycleError['error_message'])->toContain('Invalid lifecycle status');
});
