<?php

use App\Enums\CableType;
use App\Enums\PortDirection;
use App\Enums\PortStatus;
use App\Models\Connection;
use App\Models\Device;
use App\Models\Port;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('POST /connections creates connection with valid data', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $sourcePort = Port::factory()->ethernet()->available()->create();
    $destPort = Port::factory()->ethernet()->available()->create();

    $connectionData = [
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_type' => CableType::Cat6->value,
        'cable_length' => 3.5,
        'cable_color' => 'blue',
        'path_notes' => 'Test connection',
    ];

    $response = $this->actingAs($user)
        ->postJson('/connections', $connectionData);

    $response->assertCreated();
    $response->assertJsonFragment(['cable_type' => 'cat6']);
    $response->assertJsonFragment(['cable_color' => 'blue']);

    $this->assertDatabaseHas('connections', [
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_type' => 'cat6',
    ]);

    // Verify port statuses were updated to Connected
    $this->assertDatabaseHas('ports', [
        'id' => $sourcePort->id,
        'status' => PortStatus::Connected->value,
    ]);
    $this->assertDatabaseHas('ports', [
        'id' => $destPort->id,
        'status' => PortStatus::Connected->value,
    ]);
});

test('POST /connections fails with incompatible port types', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $ethernetPort = Port::factory()->ethernet()->available()->create();
    $fiberPort = Port::factory()->fiber()->available()->create();

    $connectionData = [
        'source_port_id' => $ethernetPort->id,
        'destination_port_id' => $fiberPort->id,
        'cable_type' => CableType::Cat6->value,
        'cable_length' => 3.5,
        'cable_color' => 'blue',
    ];

    $response = $this->actingAs($user)
        ->postJson('/connections', $connectionData);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['destination_port_id']);
});

test('POST /connections fails with invalid power direction', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create source port with Input direction (invalid for source)
    $sourcePort = Port::factory()->power()->available()->create([
        'direction' => PortDirection::Input,
    ]);
    // Create destination port with Output direction (invalid for destination)
    $destPort = Port::factory()->power()->available()->create([
        'direction' => PortDirection::Output,
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

    $response->assertUnprocessable();
    // Should fail because power source must be Output and destination must be Input
    $response->assertJsonValidationErrors(['source_port_id']);
});

test('POST /connections fails when port already has connection', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create an existing connection
    $existingSourcePort = Port::factory()->ethernet()->connected()->create();
    $existingDestPort = Port::factory()->ethernet()->connected()->create();
    Connection::factory()->create([
        'source_port_id' => $existingSourcePort->id,
        'destination_port_id' => $existingDestPort->id,
    ]);

    // Try to create another connection using the same source port
    $newDestPort = Port::factory()->ethernet()->available()->create();

    $connectionData = [
        'source_port_id' => $existingSourcePort->id,
        'destination_port_id' => $newDestPort->id,
        'cable_type' => CableType::Cat6->value,
        'cable_length' => 3.5,
        'cable_color' => 'blue',
    ];

    $response = $this->actingAs($user)
        ->postJson('/connections', $connectionData);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['source_port_id']);
});

test('GET /connections returns list with filtering', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device1 = Device::factory()->create();
    $device2 = Device::factory()->create();

    $port1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    $port2 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    $port3 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);
    $port4 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);

    Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
    ]);
    Connection::factory()->create([
        'source_port_id' => $port3->id,
        'destination_port_id' => $port4->id,
    ]);

    // Test without filter - returns all
    $response = $this->actingAs($user)
        ->getJson('/connections');

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'data');

    // Test with device filter
    $response = $this->actingAs($user)
        ->getJson("/connections?device_id={$device1->id}");

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
});

test('PUT /connections/{connection} updates cable properties', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $connection = Connection::factory()->create([
        'cable_color' => 'blue',
        'cable_length' => 3.5,
        'path_notes' => 'Original notes',
    ]);

    $updateData = [
        'cable_type' => CableType::Cat6a->value,
        'cable_length' => 5.0,
        'cable_color' => 'yellow',
        'path_notes' => 'Updated notes',
    ];

    $response = $this->actingAs($user)
        ->putJson("/connections/{$connection->id}", $updateData);

    $response->assertSuccessful();
    $response->assertJsonFragment(['cable_color' => 'yellow']);
    $response->assertJsonFragment(['cable_type' => 'cat6a']);

    $this->assertDatabaseHas('connections', [
        'id' => $connection->id,
        'cable_color' => 'yellow',
        'cable_type' => 'cat6a',
        'cable_length' => 5.0,
    ]);
});

test('DELETE /connections/{connection} soft deletes connection', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $sourcePort = Port::factory()->ethernet()->connected()->create();
    $destPort = Port::factory()->ethernet()->connected()->create();

    $connection = Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    $response = $this->actingAs($user)
        ->deleteJson("/connections/{$connection->id}");

    $response->assertSuccessful();
    $response->assertJsonFragment(['message' => 'Connection deleted successfully.']);

    // Verify soft delete
    $this->assertSoftDeleted('connections', [
        'id' => $connection->id,
    ]);

    // Verify port statuses were updated to Available
    $this->assertDatabaseHas('ports', [
        'id' => $sourcePort->id,
        'status' => PortStatus::Available->value,
    ]);
    $this->assertDatabaseHas('ports', [
        'id' => $destPort->id,
        'status' => PortStatus::Available->value,
    ]);
});
