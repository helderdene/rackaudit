<?php

/**
 * Feature tests for the enhanced Rack Show page frontend components
 *
 * These tests verify the frontend page receives the correct props and data
 * structure for all the enhanced rack page features.
 */

use App\Enums\DeviceLifecycleStatus;
use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Pdu;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Disable Inertia page existence check
    config(['inertia.testing.ensure_pages_exist' => false]);

    // Create hierarchy: Datacenter > Room > Row
    $this->datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $this->room = Room::factory()->create([
        'datacenter_id' => $this->datacenter->id,
        'name' => 'Test Room',
    ]);
    $this->row = Row::factory()->create([
        'room_id' => $this->room->id,
        'name' => 'Test Row',
    ]);

    // Create admin user
    $this->admin = User::factory()->create(['email' => 'admin@example.com']);
    $this->admin->assignRole('Administrator');
});

/**
 * Test 1: Rack Show page receives new rack detail fields as props
 */
test('rack show page receives new rack detail fields', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Enhanced Test Rack',
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
        'manufacturer' => 'APC',
        'model' => 'NetShelter SX',
        'depth' => '1070mm',
        'installation_date' => '2024-06-15',
        'location_notes' => 'Near fire exit, requires clearance check',
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.show', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Show')
            ->has('rack')
            ->where('rack.name', 'Enhanced Test Rack')
            ->where('rack.manufacturer', 'APC')
            ->where('rack.model', 'NetShelter SX')
            ->where('rack.depth', '1070mm')
            ->where('rack.installation_date', '2024-06-15')
            ->where('rack.location_notes', 'Near fire exit, requires clearance check')
        );
});

/**
 * Test 2: Specifications prop contains key-value pairs
 */
test('rack show page receives specifications as key-value pairs', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Specs Test Rack',
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
        'specs' => [
            'max_weight_kg' => 1500,
            'cable_management' => 'vertical',
            'cooling_type' => 'rear-door',
        ],
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.show', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Show')
            ->has('rack.specs')
            ->where('rack.specs.max_weight_kg', 1500)
            ->where('rack.specs.cable_management', 'vertical')
            ->where('rack.specs.cooling_type', 'rear-door')
        );
});

/**
 * Test 3: Devices prop contains device list with correct structure
 */
test('rack show page receives devices with correct structure', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
    ]);

    // Create devices at various U positions
    Device::factory()->placed($rack, 25)->create([
        'name' => 'Server at U25',
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);
    Device::factory()->placed($rack, 10)->create([
        'name' => 'Switch at U10',
        'lifecycle_status' => DeviceLifecycleStatus::Maintenance,
    ]);
    Device::factory()->placed($rack, 5)->create([
        'name' => 'Server at U5',
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.show', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Show')
            ->has('devices', 3)
            // Devices should be sorted by start_u descending
            ->where('devices.0.name', 'Server at U25')
            ->where('devices.0.start_u', 25)
            ->has('devices.0.type')
            ->has('devices.0.lifecycle_status')
            ->has('devices.0.lifecycle_status_label')
            ->where('devices.1.name', 'Switch at U10')
            ->where('devices.1.start_u', 10)
            ->where('devices.2.name', 'Server at U5')
            ->where('devices.2.start_u', 5)
        );
});

/**
 * Test 4: Utilization prop contains correct metrics
 */
test('rack show page receives utilization metrics', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
    ]);

    // Create devices taking up 10U total (4U + 2U + 4U)
    Device::factory()->placed($rack, 1)->withUHeight(4)->create();
    Device::factory()->placed($rack, 10)->withUHeight(2)->create();
    Device::factory()->placed($rack, 20)->withUHeight(4)->create();

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.show', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Show')
            ->has('utilization')
            ->where('utilization.totalU', 42)
            ->where('utilization.usedU', 10)
            ->where('utilization.availableU', 32)
            ->has('utilization.utilizationPercent')
        );
});

/**
 * Test 5: Power metrics prop contains correct data
 */
test('rack show page receives power metrics', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
    ]);

    // Create devices with power draw
    Device::factory()->placed($rack, 1)->create(['power_draw_watts' => 500]);
    Device::factory()->placed($rack, 5)->create(['power_draw_watts' => 300]);

    // Create PDUs with capacity
    $pdu = Pdu::factory()->create([
        'room_id' => $this->room->id,
        'row_id' => null,
        'total_capacity_kw' => 10.0,
    ]);
    $rack->pdus()->attach($pdu->id);

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.show', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Show')
            ->has('powerMetrics')
            ->where('powerMetrics.totalPowerDraw', 800)
            ->where('powerMetrics.pduCapacity', 10000)
            ->has('powerMetrics.powerUtilizationPercent')
        );
});

/**
 * Test 6: Empty rack shows zero utilization and no devices
 */
test('rack show page handles empty rack correctly', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
        'specs' => null,
        'location_notes' => null,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.show', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Show')
            ->has('devices', 0)
            ->where('utilization.usedU', 0)
            ->where('utilization.utilizationPercent', 0)
            ->where('powerMetrics.totalPowerDraw', 0)
            ->where('powerMetrics.pduCapacity', 0)
        );
});
