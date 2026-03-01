<?php

/**
 * Tests for PortsSection Connection Integration.
 *
 * These tests verify:
 * - "Connected To" column displays for connected ports
 * - "Connect" button appears for available ports when canEdit=true
 * - Connection info is available to open detail dialog
 * - Connection column data is hidden when no connections exist
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

test('device show page includes connection data for connected ports', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create two devices with ports
    $sourceDevice = Device::factory()->create(['name' => 'Source Server']);
    $destDevice = Device::factory()->create(['name' => 'Destination Switch']);

    // Create ports and connection
    $sourcePort = Port::factory()->ethernet()->connected()->create([
        'device_id' => $sourceDevice->id,
        'label' => 'eth0',
    ]);
    $destPort = Port::factory()->ethernet()->connected()->create([
        'device_id' => $destDevice->id,
        'label' => 'port-24',
    ]);

    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_type' => CableType::Cat6,
        'cable_length' => 3.5,
    ]);

    // Visit the source device show page
    $response = $this->actingAs($user)
        ->get("/devices/{$sourceDevice->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Devices/Show')
        ->has('ports', 1)
        ->has('ports.0.connection')
        ->where('ports.0.connection.cable_type', 'cat6')
        ->has('ports.0.remote_device_name')
        ->has('ports.0.remote_port_label')
    );
});

test('device show page includes filter options for connection dialogs', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create hierarchy for filter options
    $datacenter = Datacenter::factory()->create(['name' => 'DC1']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Room A']);
    $row = Row::factory()->create(['room_id' => $room->id, 'name' => 'Row 1']);
    $rack = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack 01']);
    $device = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Server 01']);

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
        ->has('filterOptions.datacenters')
        ->has('filterOptions.rooms')
        ->has('filterOptions.rows')
        ->has('filterOptions.racks')
        ->has('cableTypeOptions')
    );
});

test('device show page includes cable type options for connection dialogs', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();
    Port::factory()->ethernet()->available()->create([
        'device_id' => $device->id,
        'label' => 'eth0',
    ]);

    $response = $this->actingAs($user)
        ->get("/devices/{$device->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Devices/Show')
        ->has('cableTypeOptions')
        // Verify cable type options contain expected types
        ->where('cableTypeOptions', function ($options) {
            $values = collect($options)->pluck('value')->toArray();

            return in_array('cat6', $values) &&
                   in_array('fiber_sm', $values) &&
                   in_array('power_c13', $values);
        })
    );
});

test('device show page shows ports without connection as having no connection data', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create(['name' => 'Test Server']);

    // Create an available port with no connection
    Port::factory()->ethernet()->available()->create([
        'device_id' => $device->id,
        'label' => 'eth0',
    ]);

    $response = $this->actingAs($user)
        ->get("/devices/{$device->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Devices/Show')
        ->has('ports', 1)
        ->where('ports.0.connection', null)
        ->where('ports.0.status', 'available')
    );
});

test('canEdit prop is passed correctly for permission-based UI', function () {
    // Create hierarchy for the device
    $datacenter = Datacenter::factory()->create(['name' => 'DC1']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);
    Port::factory()->ethernet()->available()->create(['device_id' => $device->id]);

    // Test with editor user
    $editor = User::factory()->create();
    $editor->assignRole('IT Manager');

    $response = $this->actingAs($editor)
        ->get("/devices/{$device->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Devices/Show')
        ->where('canEdit', true)
    );

    // Test with viewer user who has access to the datacenter
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $viewer->datacenters()->attach($datacenter->id);

    $response = $this->actingAs($viewer)
        ->get("/devices/{$device->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Devices/Show')
        ->where('canEdit', false)
    );
});
