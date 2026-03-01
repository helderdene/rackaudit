<?php

/**
 * Integration tests for the Rack Page Enhancement feature.
 *
 * These tests fill critical gaps in coverage for edge cases and integration points
 * not covered by Task Groups 1-3 tests.
 *
 * Maximum 10 tests as per spec requirements.
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
 * Test 1: Store creates rack with all new enhancement fields
 */
test('store creates rack with all enhancement fields', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('datacenters.rooms.rows.racks.store', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
        ]), [
            'name' => 'Enhanced Rack',
            'position' => 1,
            'u_height' => RackUHeight::U42->value,
            'status' => RackStatus::Active->value,
            'manufacturer' => 'APC',
            'model' => 'NetShelter SX',
            'depth' => '1070mm',
            'installation_date' => '2024-06-15',
            'location_notes' => 'Near fire exit, requires clearance',
            'specs' => [
                'max_weight_kg' => 1500,
                'cable_management' => 'vertical',
            ],
        ]);

    $response->assertRedirect();

    $rack = Rack::where('name', 'Enhanced Rack')->first();

    expect($rack)->not->toBeNull();
    expect($rack->manufacturer)->toBe('APC');
    expect($rack->model)->toBe('NetShelter SX');
    expect($rack->depth)->toBe('1070mm');
    expect($rack->installation_date->format('Y-m-d'))->toBe('2024-06-15');
    expect($rack->location_notes)->toBe('Near fire exit, requires clearance');
    expect($rack->specs)->toBe([
        'max_weight_kg' => 1500,
        'cable_management' => 'vertical',
    ]);
});

/**
 * Test 2: Update modifies rack with all new enhancement fields
 */
test('update modifies rack with all enhancement fields', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'manufacturer' => null,
        'model' => null,
        'specs' => null,
    ]);

    $response = $this->actingAs($this->admin)
        ->put(route('datacenters.rooms.rows.racks.update', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]), [
            'name' => $rack->name,
            'position' => $rack->position,
            'u_height' => $rack->u_height->value,
            'status' => $rack->status->value,
            'manufacturer' => 'Eaton',
            'model' => 'RS Series',
            'depth' => '900mm',
            'installation_date' => '2025-01-10',
            'location_notes' => 'Updated location notes',
            'specs' => [
                'max_weight_kg' => 2000,
                'cooling_type' => 'rear-door',
            ],
        ]);

    $response->assertRedirect();

    $rack->refresh();

    expect($rack->manufacturer)->toBe('Eaton');
    expect($rack->model)->toBe('RS Series');
    expect($rack->depth)->toBe('900mm');
    expect($rack->installation_date->format('Y-m-d'))->toBe('2025-01-10');
    expect($rack->location_notes)->toBe('Updated location notes');
    expect($rack->specs)->toBe([
        'max_weight_kg' => 2000,
        'cooling_type' => 'rear-door',
    ]);
});

/**
 * Test 3: Full rack (100% utilization) calculates correctly
 */
test('show calculates 100 percent utilization for full rack', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'u_height' => RackUHeight::U42, // 42U total
    ]);

    // Fill the rack completely: 42U total
    Device::factory()->placed($rack, 1)->withUHeight(10)->create();
    Device::factory()->placed($rack, 11)->withUHeight(10)->create();
    Device::factory()->placed($rack, 21)->withUHeight(10)->create();
    Device::factory()->placed($rack, 31)->withUHeight(10)->create();
    Device::factory()->placed($rack, 41)->withUHeight(2)->create();

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
            ->where('utilization.usedU', 42)
            ->where('utilization.availableU', 0)
            ->where('utilization.utilizationPercent', fn ($value) => $value == 100)
        );
});

/**
 * Test 4: Utilization calculation works for different rack sizes (48U)
 */
test('show calculates utilization correctly for 48U rack', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'u_height' => RackUHeight::U48, // 48U total
    ]);

    // Add 24U of devices (50% utilization)
    Device::factory()->placed($rack, 1)->withUHeight(12)->create();
    Device::factory()->placed($rack, 20)->withUHeight(12)->create();

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
            ->where('utilization.totalU', 48)
            ->where('utilization.usedU', 24)
            ->where('utilization.availableU', 24)
            ->where('utilization.utilizationPercent', fn ($value) => $value == 50)
        );
});

/**
 * Test 5: Power metrics handle devices with null power_draw_watts
 */
test('show handles devices with null power draw in power metrics', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'u_height' => RackUHeight::U42,
    ]);

    // Create devices: one with power draw, two without (null)
    Device::factory()->placed($rack, 1)->create(['power_draw_watts' => 500]);
    Device::factory()->placed($rack, 5)->create(['power_draw_watts' => null]);
    Device::factory()->placed($rack, 10)->create(['power_draw_watts' => null]);

    // Create PDU
    $pdu = Pdu::factory()->create([
        'room_id' => $this->room->id,
        'row_id' => null,
        'total_capacity_kw' => 5.0,
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
            // Only the device with power_draw_watts should be summed
            ->where('powerMetrics.totalPowerDraw', 500)
            ->where('powerMetrics.pduCapacity', 5000)
            ->where('powerMetrics.powerUtilizationPercent', fn ($value) => $value == 10)
        );
});

/**
 * Test 6: Empty specs array vs null are both handled correctly
 */
test('show handles empty specs array correctly', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'u_height' => RackUHeight::U42,
        'specs' => [], // Empty array instead of null
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
            ->where('rack.specs', [])
        );
});

/**
 * Test 7: Rack with complete data (specs, devices, PDUs) loads correctly
 */
test('show loads rack with complete data including specs devices and PDUs', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Complete Rack',
        'u_height' => RackUHeight::U42,
        'manufacturer' => 'APC',
        'model' => 'NetShelter SX',
        'depth' => '1070mm',
        'installation_date' => '2024-06-15',
        'location_notes' => 'Full rack test',
        'specs' => [
            'max_weight_kg' => 1500,
            'cable_management' => 'vertical',
            'power_phases' => 3,
        ],
    ]);

    // Add devices
    Device::factory()->placed($rack, 10)->create([
        'name' => 'Server A',
        'power_draw_watts' => 400,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);
    Device::factory()->placed($rack, 5)->create([
        'name' => 'Server B',
        'power_draw_watts' => 300,
        'lifecycle_status' => DeviceLifecycleStatus::Maintenance,
    ]);

    // Add PDUs
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
            // Rack details
            ->where('rack.name', 'Complete Rack')
            ->where('rack.manufacturer', 'APC')
            ->where('rack.model', 'NetShelter SX')
            ->where('rack.depth', '1070mm')
            ->where('rack.installation_date', '2024-06-15')
            ->where('rack.location_notes', 'Full rack test')
            // Specs
            ->where('rack.specs.max_weight_kg', 1500)
            ->where('rack.specs.cable_management', 'vertical')
            ->where('rack.specs.power_phases', 3)
            // Devices (sorted by start_u descending)
            ->has('devices', 2)
            ->where('devices.0.name', 'Server A')
            ->where('devices.0.start_u', 10)
            ->where('devices.1.name', 'Server B')
            ->where('devices.1.start_u', 5)
            // PDUs
            ->has('pdus', 2)
            // Utilization
            ->has('utilization')
            // Power metrics
            ->where('powerMetrics.totalPowerDraw', 700)
            ->where('powerMetrics.pduCapacity', 15000)
        );
});

/**
 * Test 8: Edit form returns rack with all enhancement fields
 */
test('edit returns rack with all enhancement fields for editing', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Editable Rack',
        'u_height' => RackUHeight::U45,
        'manufacturer' => 'Vertiv',
        'model' => 'VR Rack',
        'depth' => '800mm',
        'installation_date' => '2023-12-01',
        'location_notes' => 'Notes for editing',
        'specs' => [
            'cooling_type' => 'in-row',
        ],
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.edit', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Edit')
            ->where('rack.manufacturer', 'Vertiv')
            ->where('rack.model', 'VR Rack')
            ->where('rack.depth', '800mm')
            ->where('rack.installation_date', '2023-12-01')
            ->where('rack.location_notes', 'Notes for editing')
            ->where('rack.specs.cooling_type', 'in-row')
        );
});

/**
 * Test 9: Power metrics with high utilization (over 80%) for warning threshold
 */
test('show returns correct power utilization percentage near warning threshold', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'u_height' => RackUHeight::U42,
    ]);

    // Create devices with high power draw: 8500W total
    Device::factory()->placed($rack, 1)->create(['power_draw_watts' => 2000]);
    Device::factory()->placed($rack, 5)->create(['power_draw_watts' => 2000]);
    Device::factory()->placed($rack, 10)->create(['power_draw_watts' => 2000]);
    Device::factory()->placed($rack, 15)->create(['power_draw_watts' => 2500]);

    // Create PDU with 10kW capacity -> 85% utilization
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
            ->where('powerMetrics.totalPowerDraw', 8500)
            ->where('powerMetrics.pduCapacity', 10000)
            ->where('powerMetrics.powerUtilizationPercent', fn ($value) => $value == 85)
        );
});

/**
 * Test 10: Store validates new enhancement field constraints
 */
test('store validates enhancement field constraints', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('datacenters.rooms.rows.racks.store', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
        ]), [
            'name' => 'Test Rack',
            'position' => 1,
            'u_height' => RackUHeight::U42->value,
            'status' => RackStatus::Active->value,
            // Exceeds max:255
            'manufacturer' => str_repeat('A', 256),
            // Exceeds max:100
            'depth' => str_repeat('B', 101),
            // Invalid date
            'installation_date' => 'not-a-date',
            // Exceeds max:1000
            'location_notes' => str_repeat('C', 1001),
            // Invalid - should be array
            'specs' => 'not-an-array',
        ]);

    $response->assertSessionHasErrors([
        'manufacturer',
        'depth',
        'installation_date',
        'location_notes',
        'specs',
    ]);
});
