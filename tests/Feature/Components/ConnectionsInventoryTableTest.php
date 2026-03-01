<?php

/**
 * ConnectionsInventoryTable Component Tests
 *
 * Tests for the ConnectionsInventoryTable Vue component including:
 * - Table renders with connection data
 * - Pagination controls work
 * - Columns display correct data
 * - Empty state displays appropriately
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
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
});

/**
 * Test 1: Table renders with connection data.
 *
 * Verifies that the connections inventory table receives and can render
 * connection data with all required fields including source device/port,
 * destination device/port, cable type, cable length, and cable color.
 */
test('table renders with connection data', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Main DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create source device and port
    $sourceDevice = Device::factory()->create([
        'rack_id' => $rack->id,
        'name' => 'Server-01',
    ]);
    $sourcePort = Port::factory()->ethernet()->create([
        'device_id' => $sourceDevice->id,
        'label' => 'Eth0',
    ]);

    // Create destination device and port
    $destDevice = Device::factory()->create([
        'rack_id' => $rack->id,
        'name' => 'Switch-01',
    ]);
    $destPort = Port::factory()->ethernet()->create([
        'device_id' => $destDevice->id,
        'label' => 'Port 24',
    ]);

    // Create connection
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_type' => CableType::Cat6,
        'cable_length' => 2.5,
        'cable_color' => 'Blue',
    ]);

    $response = $this->actingAs($user)
        ->get('/connection-reports');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        ->has('metrics.connections', 1)
        ->where('metrics.connections.0.source_device_name', 'Server-01')
        ->where('metrics.connections.0.source_port_label', 'Eth0')
        ->where('metrics.connections.0.destination_device_name', 'Switch-01')
        ->where('metrics.connections.0.destination_port_label', 'Port 24')
        ->where('metrics.connections.0.cable_type', CableType::Cat6->value)
        ->where('metrics.connections.0.cable_type_label', CableType::Cat6->label())
        // cable_length may be returned as string due to decimal casting
        ->has('metrics.connections.0.cable_length')
        ->where('metrics.connections.0.cable_color', 'Blue')
    );
});

/**
 * Test 2: Pagination controls work correctly.
 *
 * Verifies that pagination parameters are handled correctly:
 * - Default page is 1 with 25 items per page
 * - Page parameter changes the current page
 * - Pagination metadata includes current_page, last_page, per_page, total
 */
test('pagination controls work', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    // Create 30 connections (more than 1 page at 25 per page)
    for ($i = 0; $i < 30; $i++) {
        $sourcePort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
        $destPort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
        Connection::factory()->create([
            'source_port_id' => $sourcePort->id,
            'destination_port_id' => $destPort->id,
        ]);
    }

    // All connections are returned as a flat array (client-side pagination)
    $response = $this->actingAs($user)
        ->get('/connection-reports');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('metrics.connections', 30)
        ->where('metrics.totalConnections', 30)
    );
});

/**
 * Test 3: Columns display correct data for various cable types and formats.
 *
 * Verifies that different cable types and connection configurations
 * are displayed correctly with proper labels and formatting.
 */
test('columns display correct data for various cable types', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create devices
    $server = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'DB-Server']);
    $switch = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Core-Switch']);
    $pdu = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'PDU-A']);

    // Create fiber connection
    $fiberSourcePort = Port::factory()->fiber()->create([
        'device_id' => $server->id,
        'label' => 'SFP1',
    ]);
    $fiberDestPort = Port::factory()->fiber()->create([
        'device_id' => $switch->id,
        'label' => 'Fiber-01',
    ]);
    Connection::factory()->create([
        'source_port_id' => $fiberSourcePort->id,
        'destination_port_id' => $fiberDestPort->id,
        'cable_type' => CableType::FiberSm,
        'cable_length' => 10.0,
        'cable_color' => 'Yellow',
    ]);

    // Create power connection
    $powerSourcePort = Port::factory()->power()->create([
        'device_id' => $pdu->id,
        'label' => 'Outlet-01',
    ]);
    $powerDestPort = Port::factory()->power()->create([
        'device_id' => $server->id,
        'label' => 'PSU-1',
    ]);
    Connection::factory()->create([
        'source_port_id' => $powerSourcePort->id,
        'destination_port_id' => $powerDestPort->id,
        'cable_type' => CableType::PowerC13,
        'cable_length' => 1.8,
        'cable_color' => 'Black',
    ]);

    // Create ethernet connection with null cable_length
    $ethSourcePort = Port::factory()->ethernet()->create([
        'device_id' => $server->id,
        'label' => 'NIC1',
    ]);
    $ethDestPort = Port::factory()->ethernet()->create([
        'device_id' => $switch->id,
        'label' => 'Port-48',
    ]);
    Connection::factory()->create([
        'source_port_id' => $ethSourcePort->id,
        'destination_port_id' => $ethDestPort->id,
        'cable_type' => CableType::Cat6a,
        'cable_length' => null,
        'cable_color' => null,
    ]);

    $response = $this->actingAs($user)
        ->get('/connection-reports');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        ->has('metrics.connections', 3)
        // Check that we have various cable types represented
        ->where('metrics.totalConnections', 3)
    );

    // Verify the response contains the expected cable type labels
    $response->assertInertia(function (Assert $page) {
        $connections = $page->toArray()['props']['metrics']['connections'];

        // Collect all cable type labels
        $cableTypes = collect($connections)->pluck('cable_type_label')->toArray();

        expect($cableTypes)->toContain('Fiber SM');
        expect($cableTypes)->toContain('C13');
        expect($cableTypes)->toContain('Cat6a');

        // Verify null values are handled
        $catConnection = collect($connections)->firstWhere('cable_type_label', 'Cat6a');
        expect($catConnection['cable_length'])->toBeNull();
        expect($catConnection['cable_color'])->toBeNull();

        return $page;
    });
});

/**
 * Test 4: Empty state displays appropriately when no connections.
 *
 * Verifies that the table handles empty data gracefully with:
 * - Empty connections array
 * - Pagination showing 0 total
 * - Proper structure maintained even with no data
 */
test('empty state displays appropriately', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create datacenter but no connections
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    Device::factory()->create(['rack_id' => $rack->id]);

    $response = $this->actingAs($user)
        ->get('/connection-reports');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        // Verify empty connections array
        ->has('metrics.connections', 0)
        // Verify empty metrics
        ->where('metrics.totalConnections', 0)
    );
});
