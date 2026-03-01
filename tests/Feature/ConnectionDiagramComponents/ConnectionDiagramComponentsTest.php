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

test('diagram page loads and displays connection canvas with device nodes', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create test data
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    $deviceType = DeviceType::factory()->create(['name' => 'Server']);
    $device1 = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'name' => 'Web Server 01',
    ]);
    $device2 = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'name' => 'Database Server',
    ]);

    $port1 = Port::factory()->ethernet()->create(['device_id' => $device1->id, 'label' => 'eth0']);
    $port2 = Port::factory()->ethernet()->create(['device_id' => $device2->id, 'label' => 'eth0']);

    Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
        'cable_type' => CableType::Cat6,
        'cable_color' => 'blue',
    ]);

    // Test that the diagram API returns proper node data for frontend rendering
    // Use rack_id filter to get device-level aggregation
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?rack_id={$rack->id}");

    $response->assertSuccessful();

    // Verify nodes contain correct device information for rendering
    $nodes = $response->json('data.nodes');
    expect($nodes)->toBeArray();
    expect(count($nodes))->toBe(2);

    // Find the specific device nodes
    $webServerNode = collect($nodes)->firstWhere('name', 'Web Server 01');
    $dbServerNode = collect($nodes)->firstWhere('name', 'Database Server');

    expect($webServerNode)->not->toBeNull();
    expect($webServerNode['device_type'])->toBe('Server');
    expect($dbServerNode)->not->toBeNull();
});

test('diagram edges render with correct line styles based on verified status', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $rack = Rack::factory()->create();
    $deviceType = DeviceType::factory()->create(['name' => 'Switch']);

    $device1 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id]);
    $device2 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id]);

    $port1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    $port2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);

    Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
        'cable_type' => CableType::Cat6,
        'cable_color' => 'yellow',
    ]);

    // Use rack_id filter to get device-level aggregation
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?rack_id={$rack->id}");

    $response->assertSuccessful();

    // Verify edge data contains verified status for line styling
    $edges = $response->json('data.edges');
    expect($edges)->toBeArray();
    expect(count($edges))->toBe(1);

    $edge = $edges[0];
    expect($edge)->toHaveKey('verified');
    expect($edge)->toHaveKey('cable_type');
    expect($edge)->toHaveKey('cable_color');
    expect($edge['cable_color'])->toBe('yellow');
    expect($edge['cable_type'])->toBe('cat6');
});

test('diagram supports filtering by connection type for edge rendering', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $rack = Rack::factory()->create();
    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    // Create ethernet connection
    $ethDevice1 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id]);
    $ethDevice2 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id]);
    $ethPort1 = Port::factory()->ethernet()->create(['device_id' => $ethDevice1->id]);
    $ethPort2 = Port::factory()->ethernet()->create(['device_id' => $ethDevice2->id]);
    Connection::factory()->create([
        'source_port_id' => $ethPort1->id,
        'destination_port_id' => $ethPort2->id,
        'cable_type' => CableType::Cat6,
    ]);

    // Create fiber connection
    $fiberDevice1 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id]);
    $fiberDevice2 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id]);
    $fiberPort1 = Port::factory()->fiber()->create(['device_id' => $fiberDevice1->id]);
    $fiberPort2 = Port::factory()->fiber()->create(['device_id' => $fiberDevice2->id]);
    Connection::factory()->fiberSm()->create([
        'source_port_id' => $fiberPort1->id,
        'destination_port_id' => $fiberPort2->id,
    ]);

    // Filter by ethernet with rack_id - should only show ethernet devices
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?rack_id={$rack->id}&port_type=ethernet");

    $response->assertSuccessful();
    $nodes = $response->json('data.nodes');
    $edges = $response->json('data.edges');

    expect(count($nodes))->toBe(2); // Only ethernet devices
    expect(count($edges))->toBe(1); // Only ethernet edge
    expect($edges[0]['cable_type'])->toBe('cat6');

    // Filter by fiber with rack_id - should only show fiber devices
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?rack_id={$rack->id}&port_type=fiber");

    $response->assertSuccessful();
    $nodes = $response->json('data.nodes');
    $edges = $response->json('data.edges');

    expect(count($nodes))->toBe(2); // Only fiber devices
    expect(count($edges))->toBe(1); // Only fiber edge
    expect($edges[0]['cable_type'])->toBe('fiber_sm');
});

test('diagram node data includes port count and connection count for tooltips', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $rack = Rack::factory()->create();
    $deviceType = DeviceType::factory()->create(['name' => 'Router']);

    $device1 = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'name' => 'Core Router',
    ]);
    $device2 = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'name' => 'Edge Router',
    ]);

    // Create multiple ports on device1
    $port1a = Port::factory()->ethernet()->create(['device_id' => $device1->id, 'label' => 'eth0']);
    $port1b = Port::factory()->ethernet()->create(['device_id' => $device1->id, 'label' => 'eth1']);
    $port1c = Port::factory()->ethernet()->create(['device_id' => $device1->id, 'label' => 'eth2']);

    // Create one port on device2
    $port2 = Port::factory()->ethernet()->create(['device_id' => $device2->id, 'label' => 'eth0']);

    // Create connection between device1 (eth0) and device2
    Connection::factory()->create([
        'source_port_id' => $port1a->id,
        'destination_port_id' => $port2->id,
        'cable_type' => CableType::Cat6a,
    ]);

    // Use rack_id filter to get device-level aggregation
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?rack_id={$rack->id}");

    $response->assertSuccessful();

    $nodes = $response->json('data.nodes');
    $coreRouter = collect($nodes)->firstWhere('name', 'Core Router');

    expect($coreRouter)->not->toBeNull();
    expect($coreRouter['port_count'])->toBe(3); // 3 ports
    expect($coreRouter['connection_count'])->toBe(1); // 1 connection
});

test('diagram edge data includes discrepancy flag for warning indicators', function () {
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
    expect($edges)->toBeArray();
    expect(count($edges))->toBe(1);

    // Verify edge includes has_discrepancy flag for visual warning indicator
    $edge = $edges[0];
    expect($edge)->toHaveKey('has_discrepancy');
    expect($edge)->toHaveKey('verified');

    // Currently no discrepancies expected
    expect($edge['has_discrepancy'])->toBe(false);
    expect($edge['verified'])->toBe(true);
});
