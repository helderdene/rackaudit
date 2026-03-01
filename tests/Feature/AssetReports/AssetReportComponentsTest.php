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
 * Test 1: AssetFilters component data includes all filter dropdowns data.
 *
 * Verifies that the controller provides all necessary data for the AssetFilters
 * component including datacenter options, device types, lifecycle statuses, and manufacturers.
 */
test('AssetFilters component receives all filter dropdown data', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create test data
    $datacenter = Datacenter::factory()->create(['name' => 'Primary DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Server Room A']);
    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'manufacturer' => 'Dell',
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);

    $response = $this->actingAs($admin)->get('/reports/assets');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetReports/Index')
            // Verify datacenter options are provided
            ->has('datacenterOptions')
            ->where('datacenterOptions.0.name', 'Primary DC')
            // Verify device type options are provided
            ->has('deviceTypeOptions')
            ->where('deviceTypeOptions.0.name', 'Server')
            // Verify lifecycle status options contain all 7 statuses
            ->has('lifecycleStatusOptions', 7)
            // Verify manufacturer options are provided
            ->has('manufacturerOptions')
            ->where('manufacturerOptions.0', 'Dell')
            // Verify filter state is provided
            ->has('filters')
            ->where('filters.datacenter_id', null)
            ->where('filters.room_id', null)
            ->where('filters.device_type_id', null)
            ->where('filters.lifecycle_status', null)
            ->where('filters.manufacturer', null)
            ->where('filters.warranty_start', null)
            ->where('filters.warranty_end', null)
        );
});

/**
 * Test 2: Cascading filter behavior - selecting datacenter loads rooms.
 *
 * Verifies that when a datacenter filter is applied, the room options are
 * populated for that datacenter.
 */
test('cascading filter behavior loads rooms when datacenter is selected', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create datacenter with rooms
    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Room Alpha']);
    Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Room Beta']);

    // Create another datacenter with different rooms
    $datacenter2 = Datacenter::factory()->create(['name' => 'Other DC']);
    Room::factory()->create(['datacenter_id' => $datacenter2->id, 'name' => 'Room Gamma']);

    // Test without datacenter filter - room options should be empty
    $response = $this->actingAs($admin)->get('/reports/assets');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('roomOptions', 0)
        );

    // Test with datacenter filter - should load rooms for that datacenter
    $response = $this->actingAs($admin)->get('/reports/assets?datacenter_id='.$datacenter->id);
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('roomOptions', 2)
            ->where('roomOptions.0.name', 'Room Alpha')
            ->where('roomOptions.1.name', 'Room Beta')
            ->where('filters.datacenter_id', $datacenter->id)
        );
});

/**
 * Test 3: Filter application returns filtered metrics.
 *
 * Verifies that applying filters updates the metrics data returned
 * to reflect only the matching devices.
 */
test('filter application returns correctly filtered metrics', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create test data with multiple device types and manufacturers
    $serverType = DeviceType::factory()->create(['name' => 'Server']);
    $switchType = DeviceType::factory()->create(['name' => 'Switch']);

    // Create 3 servers from Dell
    Device::factory()->count(3)->create([
        'device_type_id' => $serverType->id,
        'manufacturer' => 'Dell',
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);

    // Create 2 switches from Cisco
    Device::factory()->count(2)->create([
        'device_type_id' => $switchType->id,
        'manufacturer' => 'Cisco',
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);

    // Test without filter - should see all 5 devices
    $response = $this->actingAs($admin)->get('/reports/assets');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.pagination.total', 5)
        );

    // Test with device type filter - should see only 3 servers
    $response = $this->actingAs($admin)->get('/reports/assets?device_type_id='.$serverType->id);
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.pagination.total', 3)
            ->where('filters.device_type_id', $serverType->id)
        );

    // Test with manufacturer filter - should see only 2 Cisco switches
    $response = $this->actingAs($admin)->get('/reports/assets?manufacturer=Cisco');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.pagination.total', 2)
            ->where('filters.manufacturer', 'Cisco')
        );
});

/**
 * Test 4: Index page returns all report sections data.
 *
 * Verifies that the page receives all necessary data to render:
 * - Warranty status cards (4 categories)
 * - Lifecycle distribution chart
 * - Device counts by type and manufacturer
 * - Device inventory table with pagination
 */
test('AssetReports Index page returns all report sections data', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create comprehensive test data
    $datacenter = Datacenter::factory()->create(['name' => 'Main DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Main Room']);
    $row = Row::factory()->create(['room_id' => $room->id, 'name' => 'Row 1']);
    $rack = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack A']);

    $serverType = DeviceType::factory()->create(['name' => 'Server']);
    $switchType = DeviceType::factory()->create(['name' => 'Switch']);

    // Create devices with various warranty statuses and lifecycle states
    // Active warranty
    Device::factory()->create([
        'device_type_id' => $serverType->id,
        'rack_id' => $rack->id,
        'start_u' => 1,
        'manufacturer' => 'Dell',
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'warranty_end_date' => now()->addYear(),
    ]);

    // Expiring soon
    Device::factory()->create([
        'device_type_id' => $switchType->id,
        'rack_id' => null,
        'manufacturer' => 'Cisco',
        'lifecycle_status' => DeviceLifecycleStatus::InStock,
        'warranty_end_date' => now()->addDays(15),
    ]);

    // Expired
    Device::factory()->create([
        'device_type_id' => $serverType->id,
        'rack_id' => null,
        'manufacturer' => 'HP',
        'lifecycle_status' => DeviceLifecycleStatus::Maintenance,
        'warranty_end_date' => now()->subMonth(),
    ]);

    // Unknown warranty
    Device::factory()->create([
        'device_type_id' => $serverType->id,
        'rack_id' => null,
        'manufacturer' => 'Lenovo',
        'lifecycle_status' => DeviceLifecycleStatus::Ordered,
        'warranty_end_date' => null,
    ]);

    $response = $this->actingAs($admin)->get('/reports/assets');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetReports/Index')
            // Verify warranty status cards data
            ->has('metrics.warrantyStatus')
            ->where('metrics.warrantyStatus.active', 1)
            ->where('metrics.warrantyStatus.expiring_soon', 1)
            ->where('metrics.warrantyStatus.expired', 1)
            ->where('metrics.warrantyStatus.unknown', 1)
            // Verify lifecycle distribution data (7 statuses)
            ->has('metrics.lifecycleDistribution', 7)
            // Verify counts by type
            ->has('metrics.countsByType')
            // Verify counts by manufacturer
            ->has('metrics.countsByManufacturer')
            // Verify device inventory data
            ->has('metrics.devices', 4)
            // Verify pagination data
            ->has('metrics.pagination')
            ->where('metrics.pagination.total', 4)
            ->where('metrics.pagination.current_page', 1)
        );
});
