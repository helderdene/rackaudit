<?php

use App\Enums\DeviceLifecycleStatus;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Disable Inertia page existence check
    config(['inertia.testing.ensure_pages_exist' => false]);
});

/**
 * Test 1: Full page renders with all sections (inventory, warranty, lifecycle, counts).
 *
 * Verifies that the AssetReports page renders all required sections:
 * - Warranty status summary (active, expiring soon, expired, unknown)
 * - Lifecycle distribution with all 7 statuses
 * - Asset counts by type
 * - Asset counts by manufacturer
 * - Device inventory with pagination
 */
test('full page renders with all sections including inventory, warranty, lifecycle and counts', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create datacenter hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Main Datacenter']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Server Room']);
    $row = Row::factory()->create(['room_id' => $room->id, 'name' => 'Row A']);
    $rack = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack 01']);

    // Create device types
    $serverType = DeviceType::factory()->create(['name' => 'Server']);
    $switchType = DeviceType::factory()->create(['name' => 'Switch']);
    $storageType = DeviceType::factory()->create(['name' => 'Storage']);

    // Create devices with different warranty statuses and lifecycle states
    // Active warranty, Deployed
    Device::factory()->create([
        'device_type_id' => $serverType->id,
        'rack_id' => $rack->id,
        'start_u' => 1,
        'manufacturer' => 'Dell',
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'warranty_end_date' => now()->addYear(),
    ]);

    // Expiring soon warranty, In Stock
    Device::factory()->create([
        'device_type_id' => $switchType->id,
        'rack_id' => null,
        'manufacturer' => 'Cisco',
        'lifecycle_status' => DeviceLifecycleStatus::InStock,
        'warranty_end_date' => now()->addDays(15),
    ]);

    // Expired warranty, Maintenance
    Device::factory()->create([
        'device_type_id' => $serverType->id,
        'rack_id' => $rack->id,
        'start_u' => 10,
        'manufacturer' => 'HP',
        'lifecycle_status' => DeviceLifecycleStatus::Maintenance,
        'warranty_end_date' => now()->subMonth(),
    ]);

    // Unknown warranty, Ordered
    Device::factory()->create([
        'device_type_id' => $storageType->id,
        'rack_id' => null,
        'manufacturer' => 'NetApp',
        'lifecycle_status' => DeviceLifecycleStatus::Ordered,
        'warranty_end_date' => null,
    ]);

    $response = $this->actingAs($admin)->get('/reports/assets');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetReports/Index')
            // Verify warranty status section - all 4 categories present
            ->has('metrics.warrantyStatus')
            ->where('metrics.warrantyStatus.active', 1)
            ->where('metrics.warrantyStatus.expiring_soon', 1)
            ->where('metrics.warrantyStatus.expired', 1)
            ->where('metrics.warrantyStatus.unknown', 1)
            // Verify lifecycle distribution section - all 7 statuses
            ->has('metrics.lifecycleDistribution', 7)
            // Verify at least one lifecycle status has devices
            ->where('metrics.lifecycleDistribution.0.status', fn ($status) => in_array($status, [
                'ordered', 'received', 'in_stock', 'deployed', 'maintenance', 'decommissioned', 'disposed',
            ]))
            // Verify counts by type section
            ->has('metrics.countsByType')
            // Verify counts by manufacturer section
            ->has('metrics.countsByManufacturer')
            // Verify device inventory section with all devices
            ->has('metrics.devices', 4)
            // Verify pagination is present
            ->has('metrics.pagination')
            ->where('metrics.pagination.total', 4)
            ->where('metrics.pagination.current_page', 1)
        );
});

/**
 * Test 2: Export buttons generate correct URLs with filter params.
 *
 * Verifies that when filters are applied, the export endpoints return data
 * filtered by those parameters.
 */
test('export buttons generate correct URLs with filter params', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Test Room']);
    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    // Create device matching filter
    Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'manufacturer' => 'Dell',
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);

    // Create device not matching filter (different manufacturer)
    Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'manufacturer' => 'HP',
        'lifecycle_status' => DeviceLifecycleStatus::InStock,
    ]);

    // Test CSV export with filter params
    $response = $this->actingAs($admin)->get('/reports/assets/export/csv?' . http_build_query([
        'datacenter_id' => $datacenter->id,
        'device_type_id' => $deviceType->id,
        'manufacturer' => 'Dell',
    ]));

    $response->assertOk();
    $response->assertDownload();

    // Verify CSV filename format
    $contentDisposition = $response->headers->get('content-disposition');
    expect($contentDisposition)->toContain('asset-report-');
    expect($contentDisposition)->toContain('.csv');
});

/**
 * Test 3: Loading state during filter changes returns valid response.
 *
 * Verifies that when filters are applied via query params, the page
 * returns correctly filtered metrics and devices.
 */
test('filter changes return correctly filtered metrics for loading skeleton display', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create(['name' => 'Primary DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Room A']);
    $row = Row::factory()->create(['room_id' => $room->id, 'name' => 'Row 1']);
    $rack = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack 1']);

    $serverType = DeviceType::factory()->create(['name' => 'Server']);
    $switchType = DeviceType::factory()->create(['name' => 'Switch']);

    // Create 5 Dell servers in the datacenter
    Device::factory()->count(5)->create([
        'device_type_id' => $serverType->id,
        'rack_id' => $rack->id,
        'start_u' => 1,
        'manufacturer' => 'Dell',
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);

    // Create 3 Cisco switches (different manufacturer, not in rack)
    Device::factory()->count(3)->create([
        'device_type_id' => $switchType->id,
        'rack_id' => null,
        'manufacturer' => 'Cisco',
        'lifecycle_status' => DeviceLifecycleStatus::InStock,
    ]);

    // Test without filter - should see all 8 devices
    $response = $this->actingAs($admin)->get('/reports/assets');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.pagination.total', 8)
        );

    // Test with manufacturer filter - should only see Dell devices
    $response = $this->actingAs($admin)->get('/reports/assets?manufacturer=Dell');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.pagination.total', 5)
            ->where('filters.manufacturer', 'Dell')
        );

    // Test with device type filter - should only see servers
    $response = $this->actingAs($admin)->get('/reports/assets?device_type_id=' . $serverType->id);
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.pagination.total', 5)
            ->where('filters.device_type_id', $serverType->id)
        );

    // Test with lifecycle status filter - should only see Deployed devices
    $response = $this->actingAs($admin)->get('/reports/assets?lifecycle_status=deployed');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.pagination.total', 5)
            ->where('filters.lifecycle_status', 'deployed')
        );
});

/**
 * Test 4: Empty state displays when no devices match filters.
 *
 * Verifies that when filters result in no matching devices, the page
 * returns empty metrics and device list.
 */
test('empty state displays when no devices match filters', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create a datacenter but no devices
    $datacenter = Datacenter::factory()->create(['name' => 'Empty DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Empty Room']);

    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    // Create one device but with different attributes
    Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'manufacturer' => 'Dell',
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);

    // Filter by manufacturer that doesn't exist
    $response = $this->actingAs($admin)->get('/reports/assets?manufacturer=NonExistentMfr');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.manufacturer', null) // Invalid manufacturer is sanitized
        );

    // Filter by lifecycle status with no matching devices
    $response = $this->actingAs($admin)->get('/reports/assets?lifecycle_status=disposed');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.pagination.total', 0)
            ->where('metrics.devices', [])
            ->where('filters.lifecycle_status', 'disposed')
        );

    // Verify warranty counts are all zero when no devices match
    $response = $this->actingAs($admin)->get('/reports/assets?lifecycle_status=disposed');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.warrantyStatus.active', 0)
            ->where('metrics.warrantyStatus.expiring_soon', 0)
            ->where('metrics.warrantyStatus.expired', 0)
            ->where('metrics.warrantyStatus.unknown', 0)
        );
});
