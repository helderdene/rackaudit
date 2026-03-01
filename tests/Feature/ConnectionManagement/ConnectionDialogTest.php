<?php

/**
 * Tests for Connection Dialog Components.
 *
 * These tests verify:
 * - CreateConnectionDialog form submission
 * - ConnectionDetailDialog displays connection info
 * - EditConnectionDialog pre-fills existing values
 * - Delete confirmation workflow
 * - Permission-based button visibility
 */

use App\Enums\CableType;
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

test('CreateConnectionDialog submits valid connection data', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $sourceDevice = Device::factory()->create(['name' => 'Source Server']);
    $destDevice = Device::factory()->create(['name' => 'Destination Server']);

    $sourcePort = Port::factory()->ethernet()->available()->create([
        'device_id' => $sourceDevice->id,
        'label' => 'eth0',
    ]);
    $destPort = Port::factory()->ethernet()->available()->create([
        'device_id' => $destDevice->id,
        'label' => 'eth1',
    ]);

    $connectionData = [
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_type' => CableType::Cat6->value,
        'cable_length' => 3.5,
        'cable_color' => 'blue',
        'path_notes' => 'Test connection from dialog',
    ];

    $response = $this->actingAs($user)
        ->postJson('/connections', $connectionData);

    $response->assertCreated();
    $response->assertJsonFragment(['cable_type' => 'cat6']);
    // Cable length is returned as a formatted string with 2 decimal places
    $response->assertJsonFragment(['cable_length' => '3.50']);
    $response->assertJsonFragment(['cable_color' => 'blue']);

    // Verify ports are now connected
    $this->assertDatabaseHas('ports', [
        'id' => $sourcePort->id,
        'status' => PortStatus::Connected->value,
    ]);
    $this->assertDatabaseHas('ports', [
        'id' => $destPort->id,
        'status' => PortStatus::Connected->value,
    ]);
});

test('ConnectionDetailDialog receives complete connection info from API', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $sourceDevice = Device::factory()->create(['name' => 'Server A']);
    $destDevice = Device::factory()->create(['name' => 'Switch B']);

    $sourcePort = Port::factory()->ethernet()->connected()->create([
        'device_id' => $sourceDevice->id,
        'label' => 'eth0',
    ]);
    $destPort = Port::factory()->ethernet()->connected()->create([
        'device_id' => $destDevice->id,
        'label' => 'port-24',
    ]);

    $connection = Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_type' => CableType::Cat6a,
        'cable_length' => 5.0,
        'cable_color' => 'yellow',
        'path_notes' => 'Main uplink cable',
    ]);

    $response = $this->actingAs($user)
        ->getJson("/connections/{$connection->id}");

    $response->assertSuccessful();

    // Verify cable properties are returned
    $response->assertJsonFragment(['cable_type' => 'cat6a']);
    // Cable length is returned as a formatted string
    $response->assertJsonFragment(['cable_length' => '5.00']);
    $response->assertJsonFragment(['cable_color' => 'yellow']);
    $response->assertJsonFragment(['path_notes' => 'Main uplink cable']);

    // Verify source port info
    $response->assertJsonPath('data.source_port.label', 'eth0');
    $response->assertJsonPath('data.source_port.device.name', 'Server A');

    // Verify destination port info
    $response->assertJsonPath('data.destination_port.label', 'port-24');
    $response->assertJsonPath('data.destination_port.device.name', 'Switch B');
});

test('EditConnectionDialog can update cable properties via API', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $connection = Connection::factory()->create([
        'cable_type' => CableType::Cat6,
        'cable_length' => 3.0,
        'cable_color' => 'blue',
        'path_notes' => 'Original notes',
    ]);

    // Update via PUT request (like EditConnectionDialog would submit)
    $updateData = [
        'cable_type' => CableType::Cat6a->value,
        'cable_length' => 5.5,
        'cable_color' => 'orange',
        'path_notes' => 'Updated cable notes',
    ];

    $response = $this->actingAs($user)
        ->putJson("/connections/{$connection->id}", $updateData);

    $response->assertSuccessful();
    $response->assertJsonFragment(['cable_type' => 'cat6a']);
    // Cable length is returned as a formatted string
    $response->assertJsonFragment(['cable_length' => '5.50']);
    $response->assertJsonFragment(['cable_color' => 'orange']);
    $response->assertJsonFragment(['path_notes' => 'Updated cable notes']);

    // Verify database was updated
    $this->assertDatabaseHas('connections', [
        'id' => $connection->id,
        'cable_type' => 'cat6a',
        'cable_length' => 5.5,
        'cable_color' => 'orange',
    ]);
});

test('DeleteConnectionConfirmation deletes connection and frees ports', function () {
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

    // Verify connection was soft deleted
    $this->assertSoftDeleted('connections', [
        'id' => $connection->id,
    ]);

    // Verify both ports are now available
    $this->assertDatabaseHas('ports', [
        'id' => $sourcePort->id,
        'status' => PortStatus::Available->value,
    ]);
    $this->assertDatabaseHas('ports', [
        'id' => $destPort->id,
        'status' => PortStatus::Available->value,
    ]);
});

test('permission-based visibility: viewer user cannot create connection', function () {
    $user = User::factory()->create();
    $user->assignRole('Viewer');

    $sourcePort = Port::factory()->ethernet()->available()->create();
    $destPort = Port::factory()->ethernet()->available()->create();

    $connectionData = [
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_type' => CableType::Cat6->value,
        'cable_length' => 3.0,
    ];

    $response = $this->actingAs($user)
        ->postJson('/connections', $connectionData);

    $response->assertForbidden();
});

test('permission-based visibility: viewer user cannot delete connection', function () {
    $user = User::factory()->create();
    $user->assignRole('Viewer');

    $connection = Connection::factory()->create();

    $response = $this->actingAs($user)
        ->deleteJson("/connections/{$connection->id}");

    $response->assertForbidden();
});
