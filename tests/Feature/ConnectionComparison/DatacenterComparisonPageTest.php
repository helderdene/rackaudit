<?php

use App\Enums\CableType;
use App\Enums\ExpectedConnectionStatus;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
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

it('aggregates comparison data from all approved implementation files in datacenter', function () {
    // Create datacenter with hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create two approved implementation files
    $file1 = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);
    $file2 = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    // Create devices and ports for file 1
    $sourceDevice1 = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Server A']);
    $destDevice1 = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Switch A']);
    $sourcePort1 = Port::factory()->ethernet()->create(['device_id' => $sourceDevice1->id, 'label' => 'eth0']);
    $destPort1 = Port::factory()->ethernet()->create(['device_id' => $destDevice1->id, 'label' => 'port1']);

    // Create devices and ports for file 2
    $sourceDevice2 = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Server B']);
    $destDevice2 = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Switch B']);
    $sourcePort2 = Port::factory()->ethernet()->create(['device_id' => $sourceDevice2->id, 'label' => 'eth0']);
    $destPort2 = Port::factory()->ethernet()->create(['device_id' => $destDevice2->id, 'label' => 'port1']);

    // Create confirmed expected connections from both files
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file1)
        ->create([
            'source_device_id' => $sourceDevice1->id,
            'source_port_id' => $sourcePort1->id,
            'dest_device_id' => $destDevice1->id,
            'dest_port_id' => $destPort1->id,
        ]);

    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file2)
        ->create([
            'source_device_id' => $sourceDevice2->id,
            'source_port_id' => $sourcePort2->id,
            'dest_device_id' => $destDevice2->id,
            'dest_port_id' => $destPort2->id,
        ]);

    // Create one matching actual connection
    Connection::factory()->create([
        'source_port_id' => $sourcePort1->id,
        'destination_port_id' => $destPort1->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get("/datacenters/{$datacenter->id}/connection-comparison");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Datacenters/ConnectionComparison')
            ->has('datacenter', fn (Assert $prop) => $prop
                ->where('id', $datacenter->id)
                ->etc()
            )
            ->has('initialComparisons', 2)
            ->has('statistics', fn (Assert $stats) => $stats
                ->where('total', 2)
                ->where('matched', 1)
                ->where('missing', 1)
                ->etc()
            )
        );
});

it('detects and displays conflicts when multiple files specify different destinations for same source port', function () {
    // Create datacenter with hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create two approved implementation files
    $file1 = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);
    $file2 = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    // Create one source device/port and two different destination devices/ports
    $sourceDevice = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Server']);
    $destDevice1 = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Switch A']);
    $destDevice2 = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Switch B']);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id, 'label' => 'eth0']);
    $destPort1 = Port::factory()->ethernet()->create(['device_id' => $destDevice1->id, 'label' => 'port1']);
    $destPort2 = Port::factory()->ethernet()->create(['device_id' => $destDevice2->id, 'label' => 'port2']);

    // Create conflicting expected connections from both files (same source, different destinations)
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file1)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice1->id,
            'dest_port_id' => $destPort1->id,
        ]);

    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file2)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice2->id,
            'dest_port_id' => $destPort2->id,
        ]);

    $response = $this->actingAs($this->user)
        ->get("/datacenters/{$datacenter->id}/connection-comparison");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Datacenters/ConnectionComparison')
            ->has('statistics', fn (Assert $stats) => $stats
                ->where('conflicting', 2)
                ->etc()
            )
        );
});

it('supports pagination for large datasets with 50 rows per page default', function () {
    // Create datacenter with hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create an approved implementation file
    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    // Create a shared device type to avoid unique constraint issues
    $deviceType = DeviceType::factory()->create();

    // Create 60 expected connections directly using Eloquent (avoiding factory overhead)
    for ($i = 1; $i <= 60; $i++) {
        $sourceDevice = Device::factory()->create([
            'rack_id' => $rack->id,
            'name' => "Source $i",
            'device_type_id' => $deviceType->id,
        ]);
        $destDevice = Device::factory()->create([
            'rack_id' => $rack->id,
            'name' => "Dest $i",
            'device_type_id' => $deviceType->id,
        ]);
        $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id, 'label' => "eth$i"]);
        $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id, 'label' => "port$i"]);

        // Create expected connection directly to avoid factory creating extra devices
        ExpectedConnection::create([
            'implementation_file_id' => $file->id,
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice->id,
            'dest_port_id' => $destPort->id,
            'cable_type' => CableType::Cat6,
            'cable_length' => 3.0,
            'row_number' => $i,
            'status' => ExpectedConnectionStatus::Confirmed,
        ]);
    }

    // First page should have 50 results
    $response = $this->actingAs($this->user)
        ->get("/datacenters/{$datacenter->id}/connection-comparison");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Datacenters/ConnectionComparison')
            ->has('paginationMeta', fn (Assert $meta) => $meta
                ->where('total', 60)
                ->where('per_page', 50)
                ->where('current_page', 1)
                ->etc()
            )
        );

    // Second page
    $response = $this->actingAs($this->user)
        ->get("/datacenters/{$datacenter->id}/connection-comparison?page=2");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Datacenters/ConnectionComparison')
            ->has('paginationMeta', fn (Assert $meta) => $meta
                ->where('current_page', 2)
                ->etc()
            )
        );
});

it('supports filtering across aggregated data by discrepancy type and device', function () {
    // Create datacenter with hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create an approved implementation file
    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    // Create devices and ports for matched connection
    $matchedSourceDevice = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Matched Server']);
    $matchedDestDevice = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Matched Switch']);
    $matchedSourcePort = Port::factory()->ethernet()->create(['device_id' => $matchedSourceDevice->id]);
    $matchedDestPort = Port::factory()->ethernet()->create(['device_id' => $matchedDestDevice->id]);

    // Create devices and ports for missing connection
    $missingSourceDevice = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Missing Server']);
    $missingDestDevice = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Missing Switch']);
    $missingSourcePort = Port::factory()->ethernet()->create(['device_id' => $missingSourceDevice->id]);
    $missingDestPort = Port::factory()->ethernet()->create(['device_id' => $missingDestDevice->id]);

    // Create expected connections
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $matchedSourceDevice->id,
            'source_port_id' => $matchedSourcePort->id,
            'dest_device_id' => $matchedDestDevice->id,
            'dest_port_id' => $matchedDestPort->id,
        ]);

    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $missingSourceDevice->id,
            'source_port_id' => $missingSourcePort->id,
            'dest_device_id' => $missingDestDevice->id,
            'dest_port_id' => $missingDestPort->id,
        ]);

    // Create matching actual connection for the first expected connection
    Connection::factory()->create([
        'source_port_id' => $matchedSourcePort->id,
        'destination_port_id' => $matchedDestPort->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get("/datacenters/{$datacenter->id}/connection-comparison");

    // Check that filter options include all involved devices
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Datacenters/ConnectionComparison')
            ->has('filterOptions', fn (Assert $prop) => $prop
                ->has('devices', 4)
                ->has('racks', 1)
            )
            ->has('statistics', fn (Assert $stats) => $stats
                ->where('total', 2)
                ->where('matched', 1)
                ->where('missing', 1)
                ->etc()
            )
        );
});
