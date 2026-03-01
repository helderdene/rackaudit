<?php

use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Finding;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create required roles
    Role::create(['name' => 'Administrator']);
    Role::create(['name' => 'IT Manager']);
    Role::create(['name' => 'Auditor']);

    // Create a user with appropriate permissions
    $this->user = User::factory()->create();
    $this->user->assignRole('IT Manager');
    $this->datacenter = Datacenter::factory()->create();
    $this->user->datacenters()->attach($this->datacenter);
});

/**
 * Test that Connections Diagram page is accessible and can render with real-time components.
 */
test('connections diagram page renders successfully for authorized users', function () {
    $response = $this->actingAs($this->user)
        ->get(route('connections.diagram'));

    $response->assertOk();
});

/**
 * Test that Connections Show page is accessible with connection data.
 */
test('connections show page renders successfully with connection data', function () {
    // Create the hierarchy for connection
    $room = Room::factory()->for($this->datacenter)->create();
    $row = Row::factory()->for($room)->create();
    $rack = Rack::factory()->for($row)->create();

    // Create devices with ports
    $sourceDevice = Device::factory()->create();
    $destDevice = Device::factory()->create();

    $sourcePort = Port::factory()->for($sourceDevice)->create();
    $destPort = Port::factory()->for($destDevice)->create();

    // Create connection
    $connection = Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('connections.show', $connection));

    $response->assertOk();
});

/**
 * Test that Devices Index page renders successfully.
 */
test('devices index page renders successfully for authorized users', function () {
    // Create some devices
    Device::factory()->count(3)->create();

    $response = $this->actingAs($this->user)
        ->get(route('devices.index'));

    $response->assertOk();
});

/**
 * Test that Devices Edit page renders successfully for editing.
 */
test('devices edit page renders successfully with device data', function () {
    $device = Device::factory()->create();

    $response = $this->actingAs($this->user)
        ->get(route('devices.edit', $device));

    $response->assertOk();
});

/**
 * Test that Racks Index page renders successfully.
 */
test('racks index page renders successfully for authorized users', function () {
    $room = Room::factory()->for($this->datacenter)->create();
    $row = Row::factory()->for($room)->create();
    Rack::factory()->for($row)->count(3)->create();

    $response = $this->actingAs($this->user)
        ->get(route('datacenters.rooms.rows.racks.index', [
            'datacenter' => $this->datacenter->id,
            'room' => $room->id,
            'row' => $row->id,
        ]));

    $response->assertOk();
});

/**
 * Test that Racks Edit page renders successfully for editing.
 */
test('racks edit page renders successfully with rack data', function () {
    $room = Room::factory()->for($this->datacenter)->create();
    $row = Row::factory()->for($room)->create();
    $rack = Rack::factory()->for($row)->create();

    $response = $this->actingAs($this->user)
        ->get(route('datacenters.rooms.rows.racks.edit', [
            'datacenter' => $this->datacenter->id,
            'room' => $room->id,
            'row' => $row->id,
            'rack' => $rack->id,
        ]));

    $response->assertOk();
});

/**
 * Test that Findings Index page renders successfully.
 */
test('findings index page renders successfully for authorized users', function () {
    $response = $this->actingAs($this->user)
        ->get(route('findings.index'));

    $response->assertOk();
});

/**
 * Test that Findings Show page renders successfully with finding data.
 */
test('findings show page renders successfully with finding data', function () {
    $finding = Finding::factory()->create();

    $response = $this->actingAs($this->user)
        ->get(route('findings.show', $finding));

    $response->assertOk();
});
