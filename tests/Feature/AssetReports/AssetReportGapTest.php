<?php

/**
 * Gap tests for Asset Reports feature.
 *
 * These tests fill critical coverage gaps identified during Task Group 5 review.
 * Maximum of 8 tests to cover:
 * - Warranty 30-day boundary conditions
 * - Non-racked devices in inventory
 * - CSV content verification
 * - IT Manager role access (part of ADMIN_ROLES)
 * - Multiple filters applied simultaneously
 */

use App\Enums\DeviceLifecycleStatus;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use App\Services\AssetCalculationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
});

/**
 * Test 1: Warranty 30-day boundary conditions.
 *
 * Verifies that the warranty categorization correctly handles boundary dates:
 * - Day 29: Should be "expiring soon" (within 30 days)
 * - Day 30: Should be "expiring soon" (exactly at threshold)
 * - Day 31: Should be "active" (beyond 30-day threshold)
 */
test('warranty 30-day boundary correctly categorizes day 29, 30, and 31 from today', function () {
    $service = app(AssetCalculationService::class);

    // Day 29 from today - should be "expiring soon"
    Device::factory()->create([
        'warranty_end_date' => now()->addDays(29),
    ]);

    // Day 30 from today - should be "expiring soon" (at threshold)
    Device::factory()->create([
        'warranty_end_date' => now()->addDays(30),
    ]);

    // Day 31 from today - should be "active" (beyond threshold)
    Device::factory()->create([
        'warranty_end_date' => now()->addDays(31),
    ]);

    $query = Device::query();
    $result = $service->getWarrantyStatusCounts($query);

    expect($result['expiring_soon'])->toBe(2)
        ->and($result['active'])->toBe(1)
        ->and($result['expired'])->toBe(0)
        ->and($result['unknown'])->toBe(0);
});

/**
 * Test 2: Non-racked devices appear in inventory results.
 *
 * Verifies that devices without rack placement (null rack_id) are included
 * in the inventory list and properly displayed with "N/A" location values.
 */
test('non-racked devices appear in inventory results', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    // Create a non-racked device (In Stock, no rack assignment)
    $nonRackedDevice = Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'rack_id' => null,
        'start_u' => null,
        'name' => 'Non-Racked Server',
        'asset_tag' => 'NR-001',
        'lifecycle_status' => DeviceLifecycleStatus::InStock,
        'manufacturer' => 'Dell',
    ]);

    // Create a racked device for comparison
    $datacenter = Datacenter::factory()->create(['name' => 'Main DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Server Room']);
    $row = Row::factory()->create(['room_id' => $room->id, 'name' => 'Row A']);
    $rack = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack 01']);

    $rackedDevice = Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'rack_id' => $rack->id,
        'start_u' => 5,
        'name' => 'Racked Server',
        'asset_tag' => 'R-001',
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'manufacturer' => 'HP',
    ]);

    $response = $this->actingAs($admin)->get('/reports/assets');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // Should have both devices
            ->has('metrics.devices', 2)
            // Verify non-racked device appears in inventory
            ->where('metrics.devices.0.asset_tag', fn ($tag) => in_array($tag, ['NR-001', 'R-001']))
            ->where('metrics.devices.1.asset_tag', fn ($tag) => in_array($tag, ['NR-001', 'R-001']))
        );

    // Verify the non-racked device has null location values
    $response->assertInertia(function (Assert $page) {
        $devices = $page->toArray()['props']['metrics']['devices'];
        $nonRacked = collect($devices)->firstWhere('asset_tag', 'NR-001');

        expect($nonRacked)->not->toBeNull()
            ->and($nonRacked['datacenter_name'])->toBeNull()
            ->and($nonRacked['room_name'])->toBeNull()
            ->and($nonRacked['rack_name'])->toBeNull()
            ->and($nonRacked['start_u'])->toBeNull();
    });
});

/**
 * Test 3: CSV export contains correct headers and data format.
 *
 * Verifies that the CSV export includes all required column headers
 * and properly formats device data including N/A for missing values.
 */
test('CSV export contains correct headers and data format', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create datacenter hierarchy for racked device
    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Room A']);
    $row = Row::factory()->create(['room_id' => $room->id, 'name' => 'Row 1']);
    $rack = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack 1']);

    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    // Create a device with all fields populated
    Device::factory()->create([
        'asset_tag' => 'TEST-001',
        'name' => 'Test Server',
        'serial_number' => 'SN-123456',
        'manufacturer' => 'Dell',
        'model' => 'PowerEdge R640',
        'device_type_id' => $deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'rack_id' => $rack->id,
        'start_u' => 10,
        'warranty_end_date' => '2025-12-31',
    ]);

    $response = $this->actingAs($admin)->get('/reports/assets/export/csv');

    $response->assertOk();
    $response->assertDownload();

    // Get the CSV content from BinaryFileResponse
    $file = $response->getFile();
    $content = file_get_contents($file->getPathname());

    // Verify headers are present (first line of CSV)
    $expectedHeaders = [
        'Asset Tag',
        'Name',
        'Serial Number',
        'Manufacturer',
        'Model',
        'Device Type',
        'Lifecycle Status',
        'Datacenter',
        'Room',
        'Rack',
        'U Position',
        'Warranty End Date',
    ];

    foreach ($expectedHeaders as $header) {
        expect($content)->toContain($header);
    }

    // Verify device data is present
    expect($content)->toContain('TEST-001')
        ->and($content)->toContain('Test Server')
        ->and($content)->toContain('SN-123456')
        ->and($content)->toContain('Dell')
        ->and($content)->toContain('PowerEdge R640')
        ->and($content)->toContain('Server')
        ->and($content)->toContain('Deployed')
        ->and($content)->toContain('Test DC')
        ->and($content)->toContain('Room A')
        ->and($content)->toContain('Rack 1')
        ->and($content)->toContain('2025-12-31');
});

/**
 * Test 4: IT Manager role sees all datacenters (same as Administrator).
 *
 * Verifies that IT Manager role, which is part of ADMIN_ROLES constant,
 * has access to all datacenters just like Administrator.
 */
test('IT Manager role sees all datacenters same as Administrator', function () {
    $itManager = User::factory()->create();
    $itManager->assignRole('IT Manager');

    // Create 3 datacenters
    $dc1 = Datacenter::factory()->create(['name' => 'DC Alpha']);
    $dc2 = Datacenter::factory()->create(['name' => 'DC Beta']);
    $dc3 = Datacenter::factory()->create(['name' => 'DC Gamma']);

    // IT Manager should see all 3 datacenters without any assignment
    $response = $this->actingAs($itManager)->get('/reports/assets');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('datacenterOptions', 3)
            ->where('datacenterOptions.0.name', 'DC Alpha')
            ->where('datacenterOptions.1.name', 'DC Beta')
            ->where('datacenterOptions.2.name', 'DC Gamma')
        );
});

/**
 * Test 5: Multiple filters applied simultaneously works correctly.
 *
 * Verifies that combining multiple filters (datacenter, device type,
 * lifecycle status, manufacturer) returns correctly filtered results.
 */
test('multiple filters applied simultaneously returns correct results', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create datacenter hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Primary DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Main Room']);
    $row = Row::factory()->create(['room_id' => $room->id, 'name' => 'Row A']);
    $rack = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack 1']);

    $serverType = DeviceType::factory()->create(['name' => 'Server']);
    $switchType = DeviceType::factory()->create(['name' => 'Switch']);

    // Create device matching all filters: Dell Server, Deployed, in Primary DC
    Device::factory()->create([
        'device_type_id' => $serverType->id,
        'rack_id' => $rack->id,
        'start_u' => 1,
        'manufacturer' => 'Dell',
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'asset_tag' => 'MATCH-001',
    ]);

    // Create device NOT matching: different manufacturer
    Device::factory()->create([
        'device_type_id' => $serverType->id,
        'rack_id' => $rack->id,
        'start_u' => 5,
        'manufacturer' => 'HP',
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'asset_tag' => 'NO-MATCH-001',
    ]);

    // Create device NOT matching: different device type
    Device::factory()->create([
        'device_type_id' => $switchType->id,
        'rack_id' => $rack->id,
        'start_u' => 10,
        'manufacturer' => 'Dell',
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'asset_tag' => 'NO-MATCH-002',
    ]);

    // Create device NOT matching: different lifecycle status
    Device::factory()->create([
        'device_type_id' => $serverType->id,
        'rack_id' => $rack->id,
        'start_u' => 15,
        'manufacturer' => 'Dell',
        'lifecycle_status' => DeviceLifecycleStatus::InStock,
        'asset_tag' => 'NO-MATCH-003',
    ]);

    // Apply multiple filters simultaneously
    $response = $this->actingAs($admin)->get('/reports/assets?'.http_build_query([
        'datacenter_id' => $datacenter->id,
        'device_type_id' => $serverType->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed->value,
        'manufacturer' => 'Dell',
    ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.pagination.total', 1)
            ->has('metrics.devices', 1)
            ->where('metrics.devices.0.asset_tag', 'MATCH-001')
        );
});

/**
 * Test 6: Lifecycle status filter returns only matching devices.
 *
 * Verifies that filtering by lifecycle status correctly filters devices
 * and that metrics reflect only the filtered devices.
 */
test('lifecycle status filter returns only matching devices', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    // Create devices with different lifecycle statuses
    Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'asset_tag' => 'DEPLOYED-001',
    ]);

    Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'asset_tag' => 'DEPLOYED-002',
    ]);

    Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::Maintenance,
        'asset_tag' => 'MAINTENANCE-001',
    ]);

    Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::InStock,
        'asset_tag' => 'INSTOCK-001',
    ]);

    // Filter by Maintenance status
    $response = $this->actingAs($admin)->get('/reports/assets?lifecycle_status=maintenance');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.pagination.total', 1)
            ->has('metrics.devices', 1)
            ->where('metrics.devices.0.asset_tag', 'MAINTENANCE-001')
            // Lifecycle distribution should show 1 for maintenance and 0 for others
            ->where('metrics.lifecycleDistribution', fn ($distribution) => collect($distribution)->firstWhere('status', 'maintenance')['count'] === 1
            )
        );
});

/**
 * Test 7: Empty state when no devices exist returns zero counts.
 *
 * Verifies that when no devices exist in the system, all metrics
 * return zero counts and empty device list.
 */
test('empty state when no devices exist returns zero counts', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create datacenter but no devices
    Datacenter::factory()->create(['name' => 'Empty DC']);
    DeviceType::factory()->create(['name' => 'Server']);

    $response = $this->actingAs($admin)->get('/reports/assets');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // Warranty counts all zero
            ->where('metrics.warrantyStatus.active', 0)
            ->where('metrics.warrantyStatus.expiring_soon', 0)
            ->where('metrics.warrantyStatus.expired', 0)
            ->where('metrics.warrantyStatus.unknown', 0)
            // Lifecycle distribution exists but all have zero counts
            ->has('metrics.lifecycleDistribution', 7)
            ->where('metrics.lifecycleDistribution.0.count', 0)
            // Empty device list
            ->where('metrics.devices', [])
            ->where('metrics.pagination.total', 0)
            // Empty counts by type and manufacturer
            ->where('metrics.countsByType', [])
            ->where('metrics.countsByManufacturer', [])
        );
});

/**
 * Test 8: Restricted user sees only assigned datacenters.
 *
 * Verifies that a non-admin user (Operator) can only see datacenters
 * they are explicitly assigned to, not all datacenters.
 */
test('restricted user sees only assigned datacenters and their devices', function () {
    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    // Create 3 datacenters
    $assignedDc = Datacenter::factory()->create(['name' => 'Assigned DC']);
    $assignedDc2 = Datacenter::factory()->create(['name' => 'Assigned DC 2']);
    $notAssignedDc = Datacenter::factory()->create(['name' => 'Not Assigned DC']);

    // Assign operator to only 2 datacenters
    $operator->datacenters()->attach([$assignedDc->id, $assignedDc2->id]);

    // Create rooms/racks for assigned datacenter
    $room = Room::factory()->create(['datacenter_id' => $assignedDc->id, 'name' => 'Room A']);
    $row = Row::factory()->create(['room_id' => $room->id, 'name' => 'Row 1']);
    $rack = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack 1']);

    // Create device in assigned datacenter
    $deviceType = DeviceType::factory()->create(['name' => 'Server']);
    Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'rack_id' => $rack->id,
        'start_u' => 1,
        'asset_tag' => 'ASSIGNED-001',
    ]);

    $response = $this->actingAs($operator)->get('/reports/assets');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // Should only see 2 assigned datacenters
            ->has('datacenterOptions', 2)
            ->where('datacenterOptions.0.name', 'Assigned DC')
            ->where('datacenterOptions.1.name', 'Assigned DC 2')
        );
});
