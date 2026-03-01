<?php

/**
 * Strategic integration tests for Connection Management feature.
 *
 * These tests fill gaps identified during test review and focus on:
 * - End-to-end workflows
 * - Filter functionality (rack, port type)
 * - Multiple patch panel logical paths
 * - Soft delete/restore scenarios
 * - Valid power connections
 * - GET single connection endpoint
 */

use App\Enums\CableType;
use App\Enums\PortDirection;
use App\Enums\PortStatus;
use App\Enums\PortType;
use App\Models\Connection;
use App\Models\Device;
use App\Models\Port;
use App\Models\Rack;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('GET /connections/{connection} returns connection details with relationships', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $sourcePort = Port::factory()->ethernet()->create(['label' => 'eth0']);
    $destPort = Port::factory()->ethernet()->create(['label' => 'eth1']);

    $connection = Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_type' => CableType::Cat6a,
        'cable_length' => 5.5,
        'cable_color' => 'yellow',
        'path_notes' => 'Test path notes',
    ]);

    $response = $this->actingAs($user)
        ->getJson("/connections/{$connection->id}");

    $response->assertSuccessful();
    $response->assertJsonFragment(['cable_type' => 'cat6a']);
    $response->assertJsonFragment(['cable_color' => 'yellow']);
    $response->assertJsonPath('data.source_port.label', 'eth0');
    $response->assertJsonPath('data.destination_port.label', 'eth1');
});

test('GET /connections filters by rack_id correctly', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $rack1 = Rack::factory()->create(['name' => 'Rack A1']);
    $rack2 = Rack::factory()->create(['name' => 'Rack B2']);

    $device1 = Device::factory()->create(['rack_id' => $rack1->id]);
    $device2 = Device::factory()->create(['rack_id' => $rack2->id]);

    $port1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    $port2 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    $port3 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);
    $port4 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);

    // Connection in rack 1
    Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
    ]);

    // Connection in rack 2
    Connection::factory()->create([
        'source_port_id' => $port3->id,
        'destination_port_id' => $port4->id,
    ]);

    // Filter by rack1
    $response = $this->actingAs($user)
        ->getJson("/connections?rack_id={$rack1->id}");

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
});

test('GET /connections filters by port_type correctly', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $ethernetPort1 = Port::factory()->ethernet()->create();
    $ethernetPort2 = Port::factory()->ethernet()->create();
    $fiberPort1 = Port::factory()->fiber()->create();
    $fiberPort2 = Port::factory()->fiber()->create();

    // Ethernet connection
    Connection::factory()->create([
        'source_port_id' => $ethernetPort1->id,
        'destination_port_id' => $ethernetPort2->id,
        'cable_type' => CableType::Cat6,
    ]);

    // Fiber connection
    Connection::factory()->create([
        'source_port_id' => $fiberPort1->id,
        'destination_port_id' => $fiberPort2->id,
        'cable_type' => CableType::FiberSm,
    ]);

    // Filter by ethernet
    $response = $this->actingAs($user)
        ->getJson('/connections?port_type='.PortType::Ethernet->value);

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['cable_type' => 'cat6']);
});

test('POST /connections creates valid power connection with correct direction', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Source must be Output (power supply)
    $sourcePort = Port::factory()->power()->available()->create([
        'direction' => PortDirection::Output,
        'label' => 'psu-out',
    ]);

    // Destination must be Input (device power input)
    $destPort = Port::factory()->power()->available()->create([
        'direction' => PortDirection::Input,
        'label' => 'psu-in',
    ]);

    $connectionData = [
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_type' => CableType::PowerC13->value,
        'cable_length' => 2.0,
        'cable_color' => 'black',
    ];

    $response = $this->actingAs($user)
        ->postJson('/connections', $connectionData);

    $response->assertCreated();
    $response->assertJsonFragment(['cable_type' => 'power_c13']);

    $this->assertDatabaseHas('connections', [
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);
});

test('soft deleted connection frees up ports for new connections', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $sourcePort = Port::factory()->ethernet()->connected()->create();
    $destPort = Port::factory()->ethernet()->connected()->create();

    $connection = Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    // Delete the connection
    $response = $this->actingAs($user)
        ->deleteJson("/connections/{$connection->id}");

    $response->assertSuccessful();

    // Verify ports are available
    $this->assertDatabaseHas('ports', [
        'id' => $sourcePort->id,
        'status' => PortStatus::Available->value,
    ]);

    // Create a new port to connect to the now-available source port
    $newDestPort = Port::factory()->ethernet()->available()->create();

    // Try to create a new connection using the freed source port
    $newConnectionData = [
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $newDestPort->id,
        'cable_type' => CableType::Cat6->value,
        'cable_length' => 3.0,
        'cable_color' => 'red',
    ];

    $response = $this->actingAs($user)
        ->postJson('/connections', $newConnectionData);

    $response->assertCreated();
});

test('logical path derivation through multiple patch panels', function () {
    // Create devices: Server -> Patch Panel 1 -> Patch Panel 2 -> Switch
    $server = Device::factory()->create(['name' => 'Test Server']);
    $patchPanel1 = Device::factory()->create(['name' => 'Patch Panel 1']);
    $patchPanel2 = Device::factory()->create(['name' => 'Patch Panel 2']);
    $switch = Device::factory()->create(['name' => 'Core Switch']);

    // Server port
    $serverPort = Port::factory()->ethernet()->create([
        'device_id' => $server->id,
        'label' => 'eth0',
    ]);

    // Patch Panel 1 - front and back (paired)
    $pp1Front = Port::factory()->ethernet()->create([
        'device_id' => $patchPanel1->id,
        'label' => 'pp1-front-1',
    ]);
    $pp1Back = Port::factory()->ethernet()->create([
        'device_id' => $patchPanel1->id,
        'label' => 'pp1-back-1',
    ]);

    // Create bidirectional pairing for Patch Panel 1
    $pp1Front->update(['paired_port_id' => $pp1Back->id]);
    $pp1Back->update(['paired_port_id' => $pp1Front->id]);

    // Patch Panel 2 - front and back (paired)
    $pp2Front = Port::factory()->ethernet()->create([
        'device_id' => $patchPanel2->id,
        'label' => 'pp2-front-1',
    ]);
    $pp2Back = Port::factory()->ethernet()->create([
        'device_id' => $patchPanel2->id,
        'label' => 'pp2-back-1',
    ]);

    // Create bidirectional pairing for Patch Panel 2
    $pp2Front->update(['paired_port_id' => $pp2Back->id]);
    $pp2Back->update(['paired_port_id' => $pp2Front->id]);

    // Switch port
    $switchPort = Port::factory()->ethernet()->create([
        'device_id' => $switch->id,
        'label' => 'port-1',
    ]);

    // Create connection: Server Port -> Patch Panel 1 Front
    $connection = Connection::factory()->create([
        'source_port_id' => $serverPort->id,
        'destination_port_id' => $pp1Front->id,
        'cable_type' => CableType::Cat6,
    ]);

    // Load relationships for path derivation
    $connection->load(['sourcePort.pairedPort', 'destinationPort.pairedPort']);

    // Get logical path
    $path = $connection->getLogicalPath();

    // Verify the path includes server port, patch panel front, and patch panel back
    expect($path)->toHaveCount(3);
    expect($path[0]->id)->toBe($serverPort->id);
    expect($path[1]->id)->toBe($pp1Front->id);
    expect($path[2]->id)->toBe($pp1Back->id);
});

test('full end-to-end workflow: pair ports, create connection, verify path, delete', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create devices
    $server = Device::factory()->create(['name' => 'Web Server 01']);
    $patchPanel = Device::factory()->create(['name' => 'Patch Panel A']);
    $switch = Device::factory()->create(['name' => 'Access Switch 01']);

    // Create ports
    $serverPort = Port::factory()->ethernet()->available()->create([
        'device_id' => $server->id,
        'label' => 'eth0',
    ]);

    $ppFront = Port::factory()->ethernet()->available()->create([
        'device_id' => $patchPanel->id,
        'label' => 'front-1',
    ]);

    $ppBack = Port::factory()->ethernet()->available()->create([
        'device_id' => $patchPanel->id,
        'label' => 'back-1',
    ]);

    $switchPort = Port::factory()->ethernet()->available()->create([
        'device_id' => $switch->id,
        'label' => 'port-1',
    ]);

    // Step 1: Pair patch panel ports
    $pairResponse = $this->actingAs($user)
        ->postJson("/devices/{$patchPanel->id}/ports/{$ppFront->id}/pair", [
            'paired_port_id' => $ppBack->id,
        ]);

    $pairResponse->assertSuccessful();

    // Verify pairing
    $this->assertDatabaseHas('ports', [
        'id' => $ppFront->id,
        'paired_port_id' => $ppBack->id,
    ]);
    $this->assertDatabaseHas('ports', [
        'id' => $ppBack->id,
        'paired_port_id' => $ppFront->id,
    ]);

    // Step 2: Create connection from server to patch panel front
    $createResponse = $this->actingAs($user)
        ->postJson('/connections', [
            'source_port_id' => $serverPort->id,
            'destination_port_id' => $ppFront->id,
            'cable_type' => CableType::Cat6a->value,
            'cable_length' => 3.0,
            'cable_color' => 'blue',
            'path_notes' => 'Server to patch panel',
        ]);

    $createResponse->assertCreated();
    $connectionId = $createResponse->json('data.id');

    // Verify ports are connected
    $this->assertDatabaseHas('ports', [
        'id' => $serverPort->id,
        'status' => PortStatus::Connected->value,
    ]);

    // Step 3: Verify logical path includes paired port
    $showResponse = $this->actingAs($user)
        ->getJson("/connections/{$connectionId}");

    $showResponse->assertSuccessful();

    $logicalPath = $showResponse->json('data.logical_path');
    expect($logicalPath)->toHaveCount(3);
    expect($logicalPath[0]['id'])->toBe($serverPort->id);
    expect($logicalPath[1]['id'])->toBe($ppFront->id);
    expect($logicalPath[2]['id'])->toBe($ppBack->id);

    // Step 4: Delete connection
    $deleteResponse = $this->actingAs($user)
        ->deleteJson("/connections/{$connectionId}");

    $deleteResponse->assertSuccessful();

    // Verify soft delete
    $this->assertSoftDeleted('connections', ['id' => $connectionId]);

    // Verify ports are available again
    $this->assertDatabaseHas('ports', [
        'id' => $serverPort->id,
        'status' => PortStatus::Available->value,
    ]);
    $this->assertDatabaseHas('ports', [
        'id' => $ppFront->id,
        'status' => PortStatus::Available->value,
    ]);
});

test('port status transitions correctly through connection lifecycle', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $sourcePort = Port::factory()->ethernet()->available()->create();
    $destPort = Port::factory()->ethernet()->available()->create();

    // Initial state: Available
    expect($sourcePort->fresh()->status)->toBe(PortStatus::Available);
    expect($destPort->fresh()->status)->toBe(PortStatus::Available);

    // Create connection: ports become Connected
    $createResponse = $this->actingAs($user)
        ->postJson('/connections', [
            'source_port_id' => $sourcePort->id,
            'destination_port_id' => $destPort->id,
            'cable_type' => CableType::Cat6->value,
            'cable_length' => 2.0,
            'cable_color' => 'green',
        ]);

    $createResponse->assertCreated();
    $connectionId = $createResponse->json('data.id');

    expect($sourcePort->fresh()->status)->toBe(PortStatus::Connected);
    expect($destPort->fresh()->status)->toBe(PortStatus::Connected);

    // Delete connection: ports become Available
    $deleteResponse = $this->actingAs($user)
        ->deleteJson("/connections/{$connectionId}");

    $deleteResponse->assertSuccessful();

    expect($sourcePort->fresh()->status)->toBe(PortStatus::Available);
    expect($destPort->fresh()->status)->toBe(PortStatus::Available);
});

test('connection without paired ports has simple logical path', function () {
    $server = Device::factory()->create(['name' => 'Server']);
    $switch = Device::factory()->create(['name' => 'Switch']);

    $serverPort = Port::factory()->ethernet()->create([
        'device_id' => $server->id,
        'label' => 'eth0',
    ]);

    $switchPort = Port::factory()->ethernet()->create([
        'device_id' => $switch->id,
        'label' => 'port-1',
    ]);

    // Direct connection (no patch panel)
    $connection = Connection::factory()->create([
        'source_port_id' => $serverPort->id,
        'destination_port_id' => $switchPort->id,
        'cable_type' => CableType::Cat6,
    ]);

    $connection->load(['sourcePort.pairedPort', 'destinationPort.pairedPort']);

    $path = $connection->getLogicalPath();

    // Path should only have source and destination
    expect($path)->toHaveCount(2);
    expect($path[0]->id)->toBe($serverPort->id);
    expect($path[1]->id)->toBe($switchPort->id);
});
