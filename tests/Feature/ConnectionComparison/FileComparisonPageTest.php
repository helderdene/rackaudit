<?php

use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\ExpectedConnection;
use App\Models\ImplementationFile;
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
    // Seed roles and permissions
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create an admin user who has access to all datacenters
    $this->user = User::factory()->create();
    $this->user->assignRole('Administrator');
});

it('loads the comparison page for an approved file with confirmed connections', function () {
    // Create datacenter with hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create an approved implementation file
    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    // Create devices and ports
    $sourceDevice = Device::factory()->create(['rack_id' => $rack->id]);
    $destDevice = Device::factory()->create(['rack_id' => $rack->id]);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    // Create a confirmed expected connection
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice->id,
            'dest_port_id' => $destPort->id,
        ]);

    $response = $this->actingAs($this->user)
        ->get("/implementation-files/{$file->id}/comparison");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ImplementationFiles/Comparison')
            ->has('implementationFile', fn (Assert $prop) => $prop
                ->where('id', $file->id)
                ->etc()
            )
            ->has('initialComparisons')
            ->has('filterOptions', fn (Assert $prop) => $prop
                ->has('devices')
                ->has('racks')
            )
            ->has('statistics')
        );
});

it('shows error for non-approved implementation file', function () {
    // Create a pending approval implementation file
    $datacenter = Datacenter::factory()->create();
    $file = ImplementationFile::factory()->xlsx()->pendingApproval()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get("/implementation-files/{$file->id}/comparison");

    // Should redirect with error message or return forbidden
    $response->assertForbidden();
});

it('displays comparison data correctly with matched connections', function () {
    // Create datacenter with hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create an approved implementation file
    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    // Create devices and ports
    $sourceDevice = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Source Server']);
    $destDevice = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Dest Switch']);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id, 'label' => 'eth0']);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id, 'label' => 'port1']);

    // Create a confirmed expected connection
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice->id,
            'dest_port_id' => $destPort->id,
        ]);

    // Create matching actual connection
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get("/implementation-files/{$file->id}/comparison");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ImplementationFiles/Comparison')
            ->has('initialComparisons', 1)
            ->has('statistics', fn (Assert $stats) => $stats
                ->where('total', 1)
                ->where('matched', 1)
                ->where('missing', 0)
                ->etc()
            )
        );
});

it('can create connection from comparison page for missing connections', function () {
    // Create datacenter with hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create an approved implementation file
    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    // Create devices and ports (no actual connection)
    $sourceDevice = Device::factory()->create(['rack_id' => $rack->id]);
    $destDevice = Device::factory()->create(['rack_id' => $rack->id]);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    // Create a confirmed expected connection (missing actual)
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice->id,
            'dest_port_id' => $destPort->id,
        ]);

    // Verify page loads with missing connection
    $response = $this->actingAs($this->user)
        ->get("/implementation-files/{$file->id}/comparison");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ImplementationFiles/Comparison')
            ->has('statistics', fn (Assert $stats) => $stats
                ->where('missing', 1)
                ->etc()
            )
        );

    // Create the connection using the connections endpoint
    $connectionResponse = $this->actingAs($this->user)
        ->post('/connections', [
            'source_port_id' => $sourcePort->id,
            'destination_port_id' => $destPort->id,
            'cable_type' => 'cat6',
            'cable_length' => 3.0,
        ]);

    $connectionResponse->assertRedirect();

    // Verify connection was created
    $this->assertDatabaseHas('connections', [
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);
});
