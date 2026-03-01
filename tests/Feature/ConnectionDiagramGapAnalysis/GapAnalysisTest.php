<?php

/**
 * Gap Analysis Tests for Connection Visualization Feature
 *
 * These tests fill critical gaps identified in the existing test coverage:
 * - End-to-end workflows
 * - Combined filter scenarios
 * - Edge cases for empty states
 * - Cross-location connections
 */

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

test('diagram endpoint handles combined hierarchical and type filters', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create full hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    $serverType = DeviceType::factory()->create(['name' => 'Server']);
    $switchType = DeviceType::factory()->create(['name' => 'Switch']);

    // Create server with ethernet connection
    $server1 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $serverType->id]);
    $server2 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $serverType->id]);
    $serverPort1 = Port::factory()->ethernet()->create(['device_id' => $server1->id]);
    $serverPort2 = Port::factory()->ethernet()->create(['device_id' => $server2->id]);
    Connection::factory()->create([
        'source_port_id' => $serverPort1->id,
        'destination_port_id' => $serverPort2->id,
        'cable_type' => CableType::Cat6,
    ]);

    // Create switch with fiber connection in same rack
    $switch1 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $switchType->id]);
    $switch2 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $switchType->id]);
    $switchPort1 = Port::factory()->fiber()->create(['device_id' => $switch1->id]);
    $switchPort2 = Port::factory()->fiber()->create(['device_id' => $switch2->id]);
    Connection::factory()->fiberSm()->create([
        'source_port_id' => $switchPort1->id,
        'destination_port_id' => $switchPort2->id,
    ]);

    // Filter by rack + device_type (servers only) to get device-level aggregation
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?rack_id={$rack->id}&device_type_id={$serverType->id}");

    $response->assertSuccessful();
    $nodes = $response->json('data.nodes');
    expect(count($nodes))->toBe(2); // Only 2 server devices

    // Filter by rack + port_type (fiber only) to get device-level aggregation
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?rack_id={$rack->id}&port_type=fiber");

    $response->assertSuccessful();
    $nodes = $response->json('data.nodes');
    expect(count($nodes))->toBe(2); // Only 2 switch devices with fiber
});

test('diagram endpoint returns empty result for rack with no connections', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $rack = Rack::factory()->create();
    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    // Create devices but no connections
    Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id]);
    Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id]);

    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?rack_id={$rack->id}");

    $response->assertSuccessful();
    $response->assertJson([
        'data' => [
            'nodes' => [],
            'edges' => [],
        ],
    ]);
});

test('diagram endpoint handles cross-rack connections correctly', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create two racks in same datacenter
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack1 = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack A']);
    $rack2 = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack B']);

    $deviceType = DeviceType::factory()->create(['name' => 'Switch']);

    // Device in rack 1
    $device1 = Device::factory()->create(['rack_id' => $rack1->id, 'device_type_id' => $deviceType->id]);
    $port1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);

    // Device in rack 2
    $device2 = Device::factory()->create(['rack_id' => $rack2->id, 'device_type_id' => $deviceType->id]);
    $port2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);

    // Connection between racks
    Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
        'cable_type' => CableType::Cat6a,
    ]);

    // When filtering by datacenter, should see rack-level aggregation (2 rack nodes)
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?datacenter_id={$datacenter->id}");

    $response->assertSuccessful();
    $nodes = $response->json('data.nodes');
    $edges = $response->json('data.edges');

    // At datacenter level, we get rack aggregation
    expect(count($nodes))->toBe(2); // 2 rack nodes
    expect(count($edges))->toBe(1); // 1 edge between racks

    // Verify these are rack nodes
    expect($nodes[0]['node_type'])->toBe('rack');

    // When filtering by rack1, should show device-level aggregation
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?rack_id={$rack1->id}");

    $response->assertSuccessful();
    // Should include device in rack1 and its connected device from rack2
    $nodes = $response->json('data.nodes');
    expect(count($nodes))->toBe(2); // 2 device nodes
});

test('port drill-down API includes complete connection chain information', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $rack = Rack::factory()->create();
    $deviceType = DeviceType::factory()->create(['name' => 'Switch']);

    $coreSwitch = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'name' => 'Core Switch',
    ]);
    $accessSwitch = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'name' => 'Access Switch',
    ]);

    // Create ports on core switch with connections
    $corePort1 = Port::factory()->ethernet()->connected()->create([
        'device_id' => $coreSwitch->id,
        'label' => 'Gi0/1',
    ]);
    $corePort2 = Port::factory()->fiber()->connected()->create([
        'device_id' => $coreSwitch->id,
        'label' => 'Te0/1',
    ]);

    // Create ports on access switch
    $accessPort1 = Port::factory()->ethernet()->connected()->create([
        'device_id' => $accessSwitch->id,
        'label' => 'Fa0/1',
    ]);
    $accessPort2 = Port::factory()->fiber()->connected()->create([
        'device_id' => $accessSwitch->id,
        'label' => 'SFP0/1',
    ]);

    // Create connections
    Connection::factory()->create([
        'source_port_id' => $corePort1->id,
        'destination_port_id' => $accessPort1->id,
        'cable_type' => CableType::Cat6,
        'cable_color' => 'blue',
    ]);
    Connection::factory()->fiberSm()->create([
        'source_port_id' => $corePort2->id,
        'destination_port_id' => $accessPort2->id,
    ]);

    $response = $this->actingAs($user)
        ->getJson("/devices/{$coreSwitch->id}/ports/diagram");

    $response->assertSuccessful();

    $ports = $response->json('data');
    expect(count($ports))->toBe(2);

    // Verify ethernet port has complete connection info
    $ethernetPort = collect($ports)->firstWhere('label', 'Gi0/1');
    expect($ethernetPort['connection'])->not->toBeNull();
    expect($ethernetPort['connection']['cable_type'])->toBe('cat6');
    expect($ethernetPort['connection']['cable_color'])->toBe('blue');
    expect($ethernetPort['connection']['remote_device']['name'])->toBe('Access Switch');
    expect($ethernetPort['connection']['remote_port']['label'])->toBe('Fa0/1');

    // Verify fiber port has complete connection info
    $fiberPort = collect($ports)->firstWhere('label', 'Te0/1');
    expect($fiberPort['connection'])->not->toBeNull();
    expect($fiberPort['connection']['cable_type'])->toBe('fiber_sm');
});

test('diagram page provides initial filters for device-scoped navigation from Device Show', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $rack = Rack::factory()->create();
    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    $device = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'name' => 'Target Server',
    ]);
    $otherDevice = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
    ]);

    // Create connection for the device
    $port1 = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    $port2 = Port::factory()->ethernet()->create(['device_id' => $otherDevice->id]);
    Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
    ]);

    // Navigate to diagram with device filter (simulating "View Connections" button click)
    $response = $this->actingAs($user)
        ->get("/connections/diagram/page?device_id={$device->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Connections/Diagram')
        ->where('initialFilters.device_id', $device->id)
    );

    // Verify API filters correctly when device_id is applied
    $apiResponse = $this->actingAs($user)
        ->getJson("/connections/diagram?device_id={$device->id}");

    $apiResponse->assertSuccessful();
    $nodes = $apiResponse->json('data.nodes');
    $nodeIds = collect($nodes)->pluck('id')->toArray();

    // Should include the target device and its connected device
    expect($nodeIds)->toContain($device->id);
    expect($nodeIds)->toContain($otherDevice->id);
});

test('diagram edge aggregates multiple connections between same device pair', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $rack = Rack::factory()->create();
    $deviceType = DeviceType::factory()->create(['name' => 'Switch']);

    $switch1 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id, 'name' => 'Switch A']);
    $switch2 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id, 'name' => 'Switch B']);

    // Create multiple connections between the same device pair (LAG/Port Channel scenario)
    $port1a = Port::factory()->ethernet()->create(['device_id' => $switch1->id, 'label' => 'Gi0/1']);
    $port2a = Port::factory()->ethernet()->create(['device_id' => $switch2->id, 'label' => 'Gi0/1']);
    Connection::factory()->create([
        'source_port_id' => $port1a->id,
        'destination_port_id' => $port2a->id,
        'cable_type' => CableType::Cat6,
    ]);

    $port1b = Port::factory()->ethernet()->create(['device_id' => $switch1->id, 'label' => 'Gi0/2']);
    $port2b = Port::factory()->ethernet()->create(['device_id' => $switch2->id, 'label' => 'Gi0/2']);
    Connection::factory()->create([
        'source_port_id' => $port1b->id,
        'destination_port_id' => $port2b->id,
        'cable_type' => CableType::Cat6,
    ]);

    $port1c = Port::factory()->ethernet()->create(['device_id' => $switch1->id, 'label' => 'Gi0/3']);
    $port2c = Port::factory()->ethernet()->create(['device_id' => $switch2->id, 'label' => 'Gi0/3']);
    Connection::factory()->create([
        'source_port_id' => $port1c->id,
        'destination_port_id' => $port2c->id,
        'cable_type' => CableType::Cat6,
    ]);

    // Use rack_id filter to get device-level aggregation
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?rack_id={$rack->id}");

    $response->assertSuccessful();

    // Should have 2 nodes (the two switches)
    $nodes = $response->json('data.nodes');
    expect(count($nodes))->toBe(2);

    // Should have 1 aggregated edge with connection_count = 3
    $edges = $response->json('data.edges');
    expect(count($edges))->toBe(1);
    expect($edges[0]['connection_count'])->toBe(3);
});

test('rack elevation API provides connection data for overlay integration', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack 01']);

    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    $server1 = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'name' => 'Server 01',
        'start_u' => 10,
        'rack_face' => 'front',
    ]);
    $server2 = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'name' => 'Server 02',
        'start_u' => 20,
        'rack_face' => 'front',
    ]);

    $port1 = Port::factory()->ethernet()->create(['device_id' => $server1->id]);
    $port2 = Port::factory()->ethernet()->create(['device_id' => $server2->id]);
    Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
        'cable_type' => CableType::Cat6,
        'cable_color' => 'red',
    ]);

    // Fetch connection diagram data for the rack
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?rack_id={$rack->id}");

    $response->assertSuccessful();

    $edges = $response->json('data.edges');
    expect(count($edges))->toBe(1);

    $edge = $edges[0];
    expect($edge['source_device_id'])->toBe($server1->id);
    expect($edge['destination_device_id'])->toBe($server2->id);
    expect($edge['cable_type'])->toBe('cat6');
    expect($edge['cable_color'])->toBe('red');
});

test('diagram displays verified status badge correctly for all connections', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $rack = Rack::factory()->create();
    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    $device1 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id]);
    $device2 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id]);
    $port1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    $port2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);

    Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
        'cable_type' => CableType::Cat6,
    ]);

    // Use rack_id filter to get device-level aggregation
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?rack_id={$rack->id}");

    $response->assertSuccessful();

    $edges = $response->json('data.edges');
    expect(count($edges))->toBe(1);
    // Verify the edge has a verified property
    expect($edges[0])->toHaveKey('verified');
});
