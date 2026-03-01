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

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('GET /connections/diagram returns connections with device relationships when filtering by rack', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create the hierarchy: Datacenter > Room > Row > Rack > Device > Port
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    $deviceType = DeviceType::factory()->create(['name' => 'Server']);
    $device1 = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'name' => 'Server 01',
    ]);
    $device2 = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'name' => 'Server 02',
    ]);

    $port1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    $port2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);

    Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
        'cable_type' => CableType::Cat6,
        'cable_color' => 'blue',
    ]);

    // Filter by rack to get device-level aggregation
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?rack_id={$rack->id}");

    $response->assertSuccessful();
    $response->assertJsonPath('data.aggregation_level', 'device');
    $response->assertJsonStructure([
        'data' => [
            'nodes' => [
                '*' => [
                    'id',
                    'name',
                    'node_type',
                    'asset_tag',
                    'device_type',
                    'rack_id',
                    'port_count',
                    'connection_count',
                ],
            ],
            'edges' => [
                '*' => [
                    'id',
                    'source_device_id',
                    'destination_device_id',
                    'cable_type',
                    'cable_color',
                    'verified',
                    'connection_count',
                ],
            ],
            'aggregation_level',
        ],
    ]);

    // Verify device nodes are included
    $response->assertJsonPath('data.nodes.0.name', 'Server 01');
});

test('GET /connections/diagram filters by hierarchical location parameters', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create two separate datacenter hierarchies with multiple racks
    $datacenter1 = Datacenter::factory()->create(['name' => 'DC1']);
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter1->id]);
    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $rack1a = Rack::factory()->create(['row_id' => $row1->id, 'name' => 'Rack 1A']);
    $rack1b = Rack::factory()->create(['row_id' => $row1->id, 'name' => 'Rack 1B']);

    $datacenter2 = Datacenter::factory()->create(['name' => 'DC2']);
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter2->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);
    $rack2 = Rack::factory()->create(['row_id' => $row2->id, 'name' => 'Rack 2']);

    $deviceType = DeviceType::factory()->create(['name' => 'Switch']);

    // Devices and connection in DC1 (between two racks)
    $device1a = Device::factory()->create(['rack_id' => $rack1a->id, 'device_type_id' => $deviceType->id]);
    $device1b = Device::factory()->create(['rack_id' => $rack1b->id, 'device_type_id' => $deviceType->id]);
    $port1a = Port::factory()->ethernet()->create(['device_id' => $device1a->id]);
    $port1b = Port::factory()->ethernet()->create(['device_id' => $device1b->id]);
    Connection::factory()->create(['source_port_id' => $port1a->id, 'destination_port_id' => $port1b->id]);

    // Devices and connections in DC2 (within same rack - won't show as edge in rack view)
    $device2a = Device::factory()->create(['rack_id' => $rack2->id, 'device_type_id' => $deviceType->id]);
    $device2b = Device::factory()->create(['rack_id' => $rack2->id, 'device_type_id' => $deviceType->id]);
    $port2a = Port::factory()->ethernet()->create(['device_id' => $device2a->id]);
    $port2b = Port::factory()->ethernet()->create(['device_id' => $device2b->id]);
    Connection::factory()->create(['source_port_id' => $port2a->id, 'destination_port_id' => $port2b->id]);

    // Filter by datacenter_id - returns rack-level aggregation
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?datacenter_id={$datacenter1->id}");

    $response->assertSuccessful();
    $response->assertJsonPath('data.aggregation_level', 'rack');
    $response->assertJsonCount(2, 'data.nodes'); // 2 racks
    $response->assertJsonCount(1, 'data.edges'); // 1 inter-rack connection

    // Filter by room_id - returns rack-level aggregation
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?room_id={$room1->id}");

    $response->assertSuccessful();
    $response->assertJsonPath('data.aggregation_level', 'rack');
    $response->assertJsonCount(2, 'data.nodes');

    // Filter by row_id - returns rack-level aggregation
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?row_id={$row1->id}");

    $response->assertSuccessful();
    $response->assertJsonPath('data.aggregation_level', 'rack');
    $response->assertJsonCount(2, 'data.nodes');

    // Filter by rack_id - returns device-level aggregation
    // Note: This returns devices in the rack AND their connected devices (even if in other racks)
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?rack_id={$rack1a->id}");

    $response->assertSuccessful();
    $response->assertJsonPath('data.aggregation_level', 'device');
    // Returns 2 nodes: device in rack1a + connected device in rack1b
    $response->assertJsonCount(2, 'data.nodes');
});

test('GET /connections/diagram filters by connection type and verified status', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $rack = Rack::factory()->create();
    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    // Create devices with Ethernet connection
    $device1 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id]);
    $device2 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id]);
    $ethernetPort1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    $ethernetPort2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);
    Connection::factory()->create([
        'source_port_id' => $ethernetPort1->id,
        'destination_port_id' => $ethernetPort2->id,
        'cable_type' => CableType::Cat6,
    ]);

    // Create devices with Fiber connection
    $device3 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id]);
    $device4 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id]);
    $fiberPort1 = Port::factory()->fiber()->create(['device_id' => $device3->id]);
    $fiberPort2 = Port::factory()->fiber()->create(['device_id' => $device4->id]);
    Connection::factory()->fiberSm()->create([
        'source_port_id' => $fiberPort1->id,
        'destination_port_id' => $fiberPort2->id,
    ]);

    // Test filter by port_type=ethernet with rack_id for device-level view
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?rack_id={$rack->id}&port_type=ethernet");

    $response->assertSuccessful();
    $response->assertJsonPath('data.aggregation_level', 'device');
    // Should return only devices with ethernet connections
    $response->assertJsonCount(2, 'data.nodes');
    $response->assertJsonCount(1, 'data.edges');

    // Test filter by port_type=fiber with rack_id for device-level view
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?rack_id={$rack->id}&port_type=fiber");

    $response->assertSuccessful();
    $response->assertJsonPath('data.aggregation_level', 'device');
    $response->assertJsonCount(2, 'data.nodes');
    $response->assertJsonCount(1, 'data.edges');
});

test('GET /connections/diagram returns device node aggregation with correct format', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $rack = Rack::factory()->create();
    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    $device = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'name' => 'Test Server',
        'asset_tag' => 'ASSET-20241226-00001',
    ]);

    // Create multiple ports on the device
    $port1 = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    $port2 = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    $port3 = Port::factory()->ethernet()->create(['device_id' => $device->id]);

    // Create another device to connect to
    $otherDevice = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
    ]);
    $otherPort1 = Port::factory()->ethernet()->create(['device_id' => $otherDevice->id]);
    $otherPort2 = Port::factory()->ethernet()->create(['device_id' => $otherDevice->id]);

    // Create two connections from the test device
    Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $otherPort1->id,
    ]);
    Connection::factory()->create([
        'source_port_id' => $port2->id,
        'destination_port_id' => $otherPort2->id,
    ]);

    // Use rack_id filter to get device-level aggregation
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?rack_id={$rack->id}");

    $response->assertSuccessful();
    $response->assertJsonPath('data.aggregation_level', 'device');

    // Find the test device node
    $nodes = $response->json('data.nodes');
    $testDeviceNode = collect($nodes)->firstWhere('id', $device->id);

    expect($testDeviceNode)->not->toBeNull();
    expect($testDeviceNode['name'])->toBe('Test Server');
    expect($testDeviceNode['node_type'])->toBe('device');
    expect($testDeviceNode['asset_tag'])->toBe('ASSET-20241226-00001');
    expect($testDeviceNode['device_type'])->toBe('Server');
    expect($testDeviceNode['port_count'])->toBe(3);
    expect($testDeviceNode['connection_count'])->toBe(2);
});

test('GET /connections/diagram filters by device type', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $rack = Rack::factory()->create();
    $serverType = DeviceType::factory()->create(['name' => 'Server']);
    $switchType = DeviceType::factory()->create(['name' => 'Switch']);

    // Create server devices with connection
    $server1 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $serverType->id]);
    $server2 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $serverType->id]);
    $serverPort1 = Port::factory()->ethernet()->create(['device_id' => $server1->id]);
    $serverPort2 = Port::factory()->ethernet()->create(['device_id' => $server2->id]);
    Connection::factory()->create(['source_port_id' => $serverPort1->id, 'destination_port_id' => $serverPort2->id]);

    // Create switch devices with connection
    $switch1 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $switchType->id]);
    $switch2 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $switchType->id]);
    $switchPort1 = Port::factory()->ethernet()->create(['device_id' => $switch1->id]);
    $switchPort2 = Port::factory()->ethernet()->create(['device_id' => $switch2->id]);
    Connection::factory()->create(['source_port_id' => $switchPort1->id, 'destination_port_id' => $switchPort2->id]);

    // Filter by device_type_id with rack_id - should only return servers (device-level view)
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?rack_id={$rack->id}&device_type_id={$serverType->id}");

    $response->assertSuccessful();
    $response->assertJsonPath('data.aggregation_level', 'device');
    $response->assertJsonCount(2, 'data.nodes');

    // All nodes should be servers
    $nodes = $response->json('data.nodes');
    foreach ($nodes as $node) {
        expect($node['node_type'])->toBe('device');
        expect($node['device_type'])->toBe('Server');
    }
});

test('GET /connections/diagram returns rack-level aggregation when filtering by datacenter', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create datacenter hierarchy with two racks
    $datacenter = Datacenter::factory()->create(['name' => 'DC1']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id, 'name' => 'Row A']);
    $rack1 = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack 01']);
    $rack2 = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack 02']);

    $deviceType = DeviceType::factory()->create(['name' => 'Switch']);

    // Create devices in rack1 and rack2
    $device1 = Device::factory()->create(['rack_id' => $rack1->id, 'device_type_id' => $deviceType->id]);
    $device2 = Device::factory()->create(['rack_id' => $rack2->id, 'device_type_id' => $deviceType->id]);
    $port1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    $port2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);

    // Create connection between devices in different racks
    Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
        'cable_type' => CableType::Cat6,
    ]);

    // Filter by datacenter - should return rack-level aggregation
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?datacenter_id={$datacenter->id}");

    $response->assertSuccessful();

    // Verify aggregation level is 'rack'
    $response->assertJsonPath('data.aggregation_level', 'rack');

    // Verify we get rack nodes, not device nodes
    $nodes = $response->json('data.nodes');
    expect(count($nodes))->toBe(2);

    foreach ($nodes as $node) {
        expect($node['node_type'])->toBe('rack');
        expect($node)->toHaveKey('row_name');
        expect($node)->toHaveKey('room_name');
        expect($node)->toHaveKey('datacenter_name');
        expect($node)->toHaveKey('device_count');
    }

    // Verify edges connect racks
    $edges = $response->json('data.edges');
    expect(count($edges))->toBe(1);
    expect($edges[0]['id'])->toStartWith('rack-');
});

test('GET /connections/diagram returns device-level aggregation when filtering by rack', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    // Create devices in the same rack
    $device1 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id, 'name' => 'Server 01']);
    $device2 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id, 'name' => 'Server 02']);
    $port1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    $port2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);

    Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
    ]);

    // Filter by rack - should return device-level aggregation
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?rack_id={$rack->id}");

    $response->assertSuccessful();

    // Verify aggregation level is 'device'
    $response->assertJsonPath('data.aggregation_level', 'device');

    // Verify we get device nodes
    $nodes = $response->json('data.nodes');
    expect(count($nodes))->toBe(2);

    foreach ($nodes as $node) {
        expect($node['node_type'])->toBe('device');
        expect($node)->toHaveKey('device_type');
        expect($node)->toHaveKey('port_count');
    }
});

test('GET /connections/diagram rack aggregation skips intra-rack connections', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create hierarchy with one rack
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    // Create two devices in the SAME rack
    $device1 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id]);
    $device2 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id]);
    $port1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    $port2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);

    // Create connection within the same rack
    Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
    ]);

    // Filter by datacenter - rack aggregation
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?datacenter_id={$datacenter->id}");

    $response->assertSuccessful();
    $response->assertJsonPath('data.aggregation_level', 'rack');

    // Should have one rack node (with connection count), but no edges (intra-rack)
    $nodes = $response->json('data.nodes');
    expect(count($nodes))->toBe(1);
    expect($nodes[0]['connection_count'])->toBe(1);

    // No edges because both devices are in the same rack
    $edges = $response->json('data.edges');
    expect(count($edges))->toBe(0);
});

test('GET /connections/diagram returns rack aggregation with correct device count', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack1 = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack 01']);
    $rack2 = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack 02']);

    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    // Create 3 devices in rack1
    $device1a = Device::factory()->create(['rack_id' => $rack1->id, 'device_type_id' => $deviceType->id]);
    $device1b = Device::factory()->create(['rack_id' => $rack1->id, 'device_type_id' => $deviceType->id]);
    $device1c = Device::factory()->create(['rack_id' => $rack1->id, 'device_type_id' => $deviceType->id]);

    // Create 1 device in rack2
    $device2a = Device::factory()->create(['rack_id' => $rack2->id, 'device_type_id' => $deviceType->id]);

    // Create ports
    $port1a = Port::factory()->ethernet()->create(['device_id' => $device1a->id]);
    $port1b = Port::factory()->ethernet()->create(['device_id' => $device1b->id]);
    $port2a = Port::factory()->ethernet()->create(['device_id' => $device2a->id]);

    // Create connections from rack1 devices to rack2
    Connection::factory()->create([
        'source_port_id' => $port1a->id,
        'destination_port_id' => $port2a->id,
    ]);
    Connection::factory()->create([
        'source_port_id' => $port1b->id,
        'destination_port_id' => $port2a->id,
    ]);

    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?datacenter_id={$datacenter->id}");

    $response->assertSuccessful();

    $nodes = $response->json('data.nodes');
    $rack1Node = collect($nodes)->firstWhere('id', $rack1->id);
    $rack2Node = collect($nodes)->firstWhere('id', $rack2->id);

    // rack1 has 2 devices with connections (device1c has no connection)
    expect($rack1Node['device_count'])->toBe(2);
    // rack2 has 1 device with connections
    expect($rack2Node['device_count'])->toBe(1);

    // Verify connection count aggregation
    expect($rack1Node['connection_count'])->toBe(2);
    expect($rack2Node['connection_count'])->toBe(2);
});

test('GET /devices/{device}/ports/diagram returns ports with connection info for drill-down', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $rack = Rack::factory()->create();
    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    $device = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
    ]);
    $otherDevice = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
    ]);

    // Create multiple ports with different statuses
    $connectedPort = Port::factory()->ethernet()->connected()->create([
        'device_id' => $device->id,
        'label' => 'eth0',
    ]);
    $availablePort = Port::factory()->ethernet()->available()->create([
        'device_id' => $device->id,
        'label' => 'eth1',
    ]);
    $reservedPort = Port::factory()->ethernet()->reserved()->create([
        'device_id' => $device->id,
        'label' => 'eth2',
    ]);

    $otherPort = Port::factory()->ethernet()->create(['device_id' => $otherDevice->id]);

    // Create a connection for the connected port
    Connection::factory()->create([
        'source_port_id' => $connectedPort->id,
        'destination_port_id' => $otherPort->id,
        'cable_type' => CableType::Cat6,
        'cable_color' => 'yellow',
    ]);

    $response = $this->actingAs($user)
        ->getJson("/devices/{$device->id}/ports/diagram");

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'label',
                'type',
                'status',
                'connection',
            ],
        ],
    ]);

    // Verify we get all 3 ports
    $response->assertJsonCount(3, 'data');

    // Find the connected port and verify connection info
    $ports = $response->json('data');
    $connectedPortData = collect($ports)->firstWhere('id', $connectedPort->id);

    expect($connectedPortData)->not->toBeNull();
    expect($connectedPortData['status'])->toBe('connected');
    expect($connectedPortData['connection'])->not->toBeNull();
    expect($connectedPortData['connection']['cable_type'])->toBe('cat6');
    expect($connectedPortData['connection']['cable_color'])->toBe('yellow');
});
