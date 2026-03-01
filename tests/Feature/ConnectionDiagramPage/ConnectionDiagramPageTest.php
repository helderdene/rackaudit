<?php

use App\Enums\CableType;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('diagram page loads with Inertia page and required props for canvas rendering', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create test infrastructure
    $datacenter = Datacenter::factory()->create(['name' => 'Main DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Server Room']);
    $row = Row::factory()->create(['room_id' => $room->id, 'name' => 'Row A']);
    $rack = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack 01']);

    $deviceType = DeviceType::factory()->create(['name' => 'Server']);
    $device1 = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'name' => 'Web Server',
    ]);
    $device2 = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'name' => 'DB Server',
    ]);

    $port1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    $port2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);

    Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
        'cable_type' => CableType::Cat6,
    ]);

    $response = $this->actingAs($user)
        ->get('/connections/diagram/page');

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Connections/Diagram')
        ->has('filterOptions')
        ->has('filterOptions.datacenters')
        ->has('filterOptions.rooms')
        ->has('filterOptions.rows')
        ->has('filterOptions.racks')
        ->has('deviceTypes')
        ->has('portTypeOptions')
        ->has('initialFilters')
    );
});

test('diagram page provides hierarchical filter options for cascading dropdowns', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create two datacenters with different hierarchies
    $dc1 = Datacenter::factory()->create(['name' => 'DC East']);
    $dc2 = Datacenter::factory()->create(['name' => 'DC West']);

    $room1 = Room::factory()->create(['datacenter_id' => $dc1->id, 'name' => 'Room 101']);
    $room2 = Room::factory()->create(['datacenter_id' => $dc2->id, 'name' => 'Room 201']);

    $row1 = Row::factory()->create(['room_id' => $room1->id, 'name' => 'Row A']);
    $row2 = Row::factory()->create(['room_id' => $room2->id, 'name' => 'Row B']);

    $rack1 = Rack::factory()->create(['row_id' => $row1->id, 'name' => 'Rack 01']);
    $rack2 = Rack::factory()->create(['row_id' => $row2->id, 'name' => 'Rack 02']);

    $response = $this->actingAs($user)
        ->get('/connections/diagram/page');

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Connections/Diagram')
        ->has('filterOptions.datacenters', 2)
        ->has('filterOptions.rooms', 2)
        ->has('filterOptions.rows', 2)
        ->has('filterOptions.racks', 2)
        // Verify room has datacenter_id for cascading
        ->where('filterOptions.rooms.0.datacenter_id', $dc1->id)
        // Verify row has room_id for cascading
        ->where('filterOptions.rows.0.room_id', $room1->id)
        // Verify rack has row_id for cascading
        ->where('filterOptions.racks.0.row_id', $row1->id)
    );
});

test('diagram page API returns filtered data when filter parameters are applied', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create two separate datacenter hierarchies
    $dc1 = Datacenter::factory()->create(['name' => 'DC1']);
    $room1 = Room::factory()->create(['datacenter_id' => $dc1->id]);
    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $rack1 = Rack::factory()->create(['row_id' => $row1->id]);

    $dc2 = Datacenter::factory()->create(['name' => 'DC2']);
    $room2 = Room::factory()->create(['datacenter_id' => $dc2->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);
    $rack2 = Rack::factory()->create(['row_id' => $row2->id]);

    $deviceType = DeviceType::factory()->create(['name' => 'Switch']);

    // Devices and connections in DC1
    $device1a = Device::factory()->create(['rack_id' => $rack1->id, 'device_type_id' => $deviceType->id]);
    $device1b = Device::factory()->create(['rack_id' => $rack1->id, 'device_type_id' => $deviceType->id]);
    $port1a = Port::factory()->ethernet()->create(['device_id' => $device1a->id]);
    $port1b = Port::factory()->ethernet()->create(['device_id' => $device1b->id]);
    Connection::factory()->create([
        'source_port_id' => $port1a->id,
        'destination_port_id' => $port1b->id,
        'cable_type' => CableType::Cat6,
    ]);

    // Devices and connections in DC2
    $device2a = Device::factory()->create(['rack_id' => $rack2->id, 'device_type_id' => $deviceType->id]);
    $device2b = Device::factory()->create(['rack_id' => $rack2->id, 'device_type_id' => $deviceType->id]);
    $port2a = Port::factory()->ethernet()->create(['device_id' => $device2a->id]);
    $port2b = Port::factory()->ethernet()->create(['device_id' => $device2b->id]);
    Connection::factory()->create([
        'source_port_id' => $port2a->id,
        'destination_port_id' => $port2b->id,
        'cable_type' => CableType::Cat6a,
    ]);

    // Test filtering by datacenter - returns rack-level aggregation (1 rack in DC1)
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?datacenter_id={$dc1->id}");

    $response->assertSuccessful();
    // At datacenter level, we get 1 rack node (intra-rack connections don't create edges at rack level)
    $response->assertJsonCount(1, 'data.nodes');
    // Verify it's a rack node
    expect($response->json('data.nodes.0.node_type'))->toBe('rack');

    // Test filtering by rack - returns device-level aggregation
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?rack_id={$rack1->id}");

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'data.nodes');
    // Verify it's a device node
    expect($response->json('data.nodes.0.node_type'))->toBe('device');

    // Test filtering by rack + port type - returns device-level aggregation
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?rack_id={$rack1->id}&port_type=ethernet");

    $response->assertSuccessful();
    // Should return devices from rack1 only
    $response->assertJsonCount(2, 'data.nodes');
});

test('diagram page provides device type options for filtering dropdown', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create device types - alphabetically ordered: Router, Server, Switch
    $routerType = DeviceType::factory()->create(['name' => 'Router']);
    $serverType = DeviceType::factory()->create(['name' => 'Server']);
    $switchType = DeviceType::factory()->create(['name' => 'Switch']);

    $response = $this->actingAs($user)
        ->get('/connections/diagram/page');

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Connections/Diagram')
        ->has('deviceTypes', 3)
        // Device types are ordered alphabetically by name
        ->where('deviceTypes.0.label', 'Router')
        ->where('deviceTypes.1.label', 'Server')
        ->where('deviceTypes.2.label', 'Switch')
    );
});

test('diagram page provides port type options matching PortType enum values', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $response = $this->actingAs($user)
        ->get('/connections/diagram/page');

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Connections/Diagram')
        ->has('portTypeOptions', 3) // ethernet, fiber, power
        ->where('portTypeOptions.0.value', 'ethernet')
        ->where('portTypeOptions.0.label', 'Ethernet')
        ->where('portTypeOptions.1.value', 'fiber')
        ->where('portTypeOptions.1.label', 'Fiber')
        ->where('portTypeOptions.2.value', 'power')
        ->where('portTypeOptions.2.label', 'Power')
    );
});

test('diagram page preserves initial filters from URL query parameters', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $deviceType = DeviceType::factory()->create(['name' => 'Switch']);

    $response = $this->actingAs($user)
        ->get("/connections/diagram/page?datacenter_id={$datacenter->id}&device_type_id={$deviceType->id}&port_type=ethernet");

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Connections/Diagram')
        ->has('initialFilters')
        ->where('initialFilters.datacenter_id', $datacenter->id)
        ->where('initialFilters.device_type_id', $deviceType->id)
        ->where('initialFilters.port_type', 'ethernet')
    );
});
