<?php

/**
 * Strategic permission tests for Connection Management feature.
 *
 * These tests fill gaps identified in Task Group 5 review:
 * - Viewer can view connection details (read-only access)
 * - Viewer cannot update connection (PUT permission)
 * - Filter options structure passed to Device show page
 */

use App\Enums\CableType;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
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

test('viewer user can view connection details (read-only access)', function () {
    // Create hierarchy for datacenter access
    $datacenter = Datacenter::factory()->create(['name' => 'DC1']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    $sourceDevice = Device::factory()->create([
        'name' => 'Source Server',
        'rack_id' => $rack->id,
    ]);
    $destDevice = Device::factory()->create([
        'name' => 'Destination Switch',
        'rack_id' => $rack->id,
    ]);

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

    // Create viewer with datacenter access
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $viewer->datacenters()->attach($datacenter->id);

    // Viewer should be able to GET connection details
    $response = $this->actingAs($viewer)
        ->getJson("/connections/{$connection->id}");

    $response->assertSuccessful();
    $response->assertJsonFragment(['cable_type' => 'cat6a']);
    $response->assertJsonFragment(['cable_color' => 'yellow']);
    $response->assertJsonPath('data.source_port.label', 'eth0');
    $response->assertJsonPath('data.destination_port.label', 'port-24');
});

test('viewer user cannot update connection (PUT permission denied)', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    $connection = Connection::factory()->create([
        'cable_type' => CableType::Cat6,
        'cable_length' => 3.0,
        'cable_color' => 'blue',
    ]);

    $updateData = [
        'cable_type' => CableType::Cat6a->value,
        'cable_length' => 5.5,
        'cable_color' => 'orange',
    ];

    $response = $this->actingAs($viewer)
        ->putJson("/connections/{$connection->id}", $updateData);

    $response->assertForbidden();

    // Verify connection was NOT updated
    $this->assertDatabaseHas('connections', [
        'id' => $connection->id,
        'cable_type' => 'cat6',
        'cable_color' => 'blue',
    ]);
});

test('filter options structure is complete for hierarchical port selector', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create complete hierarchy with multiple items at each level
    $datacenter1 = Datacenter::factory()->create(['name' => 'DC1']);
    $datacenter2 = Datacenter::factory()->create(['name' => 'DC2']);

    $room1 = Room::factory()->create(['datacenter_id' => $datacenter1->id, 'name' => 'Room A']);
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter1->id, 'name' => 'Room B']);
    $room3 = Room::factory()->create(['datacenter_id' => $datacenter2->id, 'name' => 'Room C']);

    $row1 = Row::factory()->create(['room_id' => $room1->id, 'name' => 'Row 1']);
    $row2 = Row::factory()->create(['room_id' => $room2->id, 'name' => 'Row 2']);

    $rack1 = Rack::factory()->create(['row_id' => $row1->id, 'name' => 'Rack 01']);
    $rack2 = Rack::factory()->create(['row_id' => $row1->id, 'name' => 'Rack 02']);

    $device = Device::factory()->create(['rack_id' => $rack1->id, 'name' => 'Server 01']);
    Port::factory()->ethernet()->available()->create([
        'device_id' => $device->id,
        'label' => 'eth0',
    ]);

    $response = $this->actingAs($user)
        ->get("/devices/{$device->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Devices/Show')
        ->has('filterOptions')
        // Verify datacenters array has correct structure
        ->has('filterOptions.datacenters', 2)
        ->where('filterOptions.datacenters.0.label', 'DC1')
        // Verify rooms have datacenter_id reference
        ->has('filterOptions.rooms', 3)
        ->where('filterOptions.rooms.0.datacenter_id', $datacenter1->id)
        // Verify rows have room_id reference
        ->has('filterOptions.rows', 2)
        ->where('filterOptions.rows.0.room_id', $room1->id)
        // Verify racks have row_id reference
        ->has('filterOptions.racks', 2)
        ->where('filterOptions.racks.0.row_id', $row1->id)
    );
});

test('device show page exposes connection for both source and destination ports', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create two devices
    $device1 = Device::factory()->create(['name' => 'Server A']);
    $device2 = Device::factory()->create(['name' => 'Switch B']);

    // Create ports and connection
    $port1 = Port::factory()->ethernet()->connected()->create([
        'device_id' => $device1->id,
        'label' => 'eth0',
    ]);
    $port2 = Port::factory()->ethernet()->connected()->create([
        'device_id' => $device2->id,
        'label' => 'port-1',
    ]);

    Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
        'cable_type' => CableType::Cat6,
    ]);

    // Check device1 (source device) shows connection
    $response1 = $this->actingAs($user)
        ->get("/devices/{$device1->id}");

    $response1->assertSuccessful();
    $response1->assertInertia(fn ($page) => $page
        ->component('Devices/Show')
        ->has('ports', 1)
        ->has('ports.0.connection')
        ->where('ports.0.remote_device_name', 'Switch B')
        ->where('ports.0.remote_port_label', 'port-1')
    );

    // Check device2 (destination device) also shows connection
    $response2 = $this->actingAs($user)
        ->get("/devices/{$device2->id}");

    $response2->assertSuccessful();
    $response2->assertInertia(fn ($page) => $page
        ->component('Devices/Show')
        ->has('ports', 1)
        ->has('ports.0.connection')
        ->where('ports.0.remote_device_name', 'Server A')
        ->where('ports.0.remote_port_label', 'eth0')
    );
});
