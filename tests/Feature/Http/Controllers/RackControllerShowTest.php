<?php

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

    // Disable Inertia page existence check since Vue components may be created in later task groups
    config(['inertia.testing.ensure_pages_exist' => false]);

    // Create hierarchy: Datacenter > Room > Row
    $this->datacenter = Datacenter::factory()->create();
    $this->room = Room::factory()->create(['datacenter_id' => $this->datacenter->id]);
    $this->row = Row::factory()->create(['room_id' => $this->room->id]);

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');
});

/**
 * Test 1: show() returns new rack fields (manufacturer, model, depth, installation_date, location_notes, specs)
 */
test('show returns new rack enhancement fields', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Enhanced Rack',
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
        'manufacturer' => 'APC',
        'model' => 'NetShelter SX',
        'depth' => '1070mm',
        'installation_date' => '2024-06-15',
        'location_notes' => 'Near fire exit, requires clearance check',
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
            ->where('rack.manufacturer', 'APC')
            ->where('rack.model', 'NetShelter SX')
            ->where('rack.depth', '1070mm')
            ->where('rack.installation_date', '2024-06-15')
            ->where('rack.location_notes', 'Near fire exit, requires clearance check')
            ->where('rack.specs.max_weight_kg', 1500)
            ->where('rack.specs.cable_management', 'vertical')
            ->where('rack.specs.cooling_type', 'rear-door')
        );
});

/**
 * Test 2: show() returns devices list with device_type eager loaded
 */
test('show returns devices list with device type eager loaded', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'u_height' => RackUHeight::U42,
    ]);

    // Create devices placed in the rack
    $device1 = Device::factory()->placed($rack, 10)->create([
        'name' => 'Web Server 01',
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);
    $device2 = Device::factory()->placed($rack, 5)->create([
        'name' => 'Database Server 01',
        'lifecycle_status' => DeviceLifecycleStatus::Maintenance,
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
            ->has('devices', 2)
            ->where('devices.0.name', 'Web Server 01')
            ->has('devices.0.type')
            ->where('devices.0.lifecycle_status', DeviceLifecycleStatus::Deployed->value)
            ->where('devices.0.lifecycle_status_label', 'Deployed')
            ->where('devices.1.name', 'Database Server 01')
            ->has('devices.1.type')
            ->where('devices.1.lifecycle_status', DeviceLifecycleStatus::Maintenance->value)
            ->where('devices.1.lifecycle_status_label', 'Maintenance')
        );
});

/**
 * Test 3: show() returns utilization stats (totalU, usedU, availableU, utilizationPercent)
 */
test('show returns utilization stats', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'u_height' => RackUHeight::U42, // 42U total
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
            ->where('utilization.totalU', 42)
            ->where('utilization.usedU', 10)
            ->where('utilization.availableU', 32)
            ->where('utilization.utilizationPercent', round((10 / 42) * 100, 1))
        );
});

/**
 * Test 4: show() returns power metrics (totalPowerDraw, pduCapacity, powerUtilizationPercent)
 */
test('show returns power metrics', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'u_height' => RackUHeight::U42,
    ]);

    // Create devices with power draw (500W + 300W + 200W = 1000W)
    Device::factory()->placed($rack, 1)->create(['power_draw_watts' => 500]);
    Device::factory()->placed($rack, 5)->create(['power_draw_watts' => 300]);
    Device::factory()->placed($rack, 10)->create(['power_draw_watts' => 200]);

    // Create PDUs with capacity (10kW + 5kW = 15kW = 15000W)
    $pdu1 = Pdu::factory()->create([
        'room_id' => $this->room->id,
        'row_id' => null,
        'total_capacity_kw' => 10.0,
    ]);
    $pdu2 = Pdu::factory()->create([
        'room_id' => $this->room->id,
        'row_id' => null,
        'total_capacity_kw' => 5.0,
    ]);
    $rack->pdus()->attach([$pdu1->id, $pdu2->id]);

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
            ->where('powerMetrics.totalPowerDraw', 1000)
            ->where('powerMetrics.pduCapacity', 15000)
            ->where('powerMetrics.powerUtilizationPercent', round((1000 / 15000) * 100, 1))
        );
});

/**
 * Test 5: Devices are sorted by start_u descending
 */
test('show returns devices sorted by start_u descending', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'u_height' => RackUHeight::U42,
    ]);

    // Create devices at various positions (not in order)
    Device::factory()->placed($rack, 5)->create(['name' => 'Device at U5']);
    Device::factory()->placed($rack, 25)->create(['name' => 'Device at U25']);
    Device::factory()->placed($rack, 10)->create(['name' => 'Device at U10']);
    Device::factory()->placed($rack, 40)->create(['name' => 'Device at U40']);

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
            ->has('devices', 4)
            // Sorted descending: U40, U25, U10, U5
            ->where('devices.0.name', 'Device at U40')
            ->where('devices.0.start_u', 40)
            ->where('devices.1.name', 'Device at U25')
            ->where('devices.1.start_u', 25)
            ->where('devices.2.name', 'Device at U10')
            ->where('devices.2.start_u', 10)
            ->where('devices.3.name', 'Device at U5')
            ->where('devices.3.start_u', 5)
        );
});

/**
 * Test 6: Power metrics handle case with no PDUs assigned
 */
test('show returns zero power capacity when no PDUs assigned', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'u_height' => RackUHeight::U42,
    ]);

    // Create device with power draw but no PDUs
    Device::factory()->placed($rack, 1)->create(['power_draw_watts' => 500]);

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
            ->where('powerMetrics.totalPowerDraw', 500)
            ->where('powerMetrics.pduCapacity', 0)
            ->where('powerMetrics.powerUtilizationPercent', 0)
        );
});
