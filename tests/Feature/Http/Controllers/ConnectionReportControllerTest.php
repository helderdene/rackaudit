<?php

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
    // Disable page component existence check since Vue component is created in later task group
    config(['inertia.testing.ensure_pages_exist' => false]);
});

test('index returns correct Inertia response with metrics', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create hierarchy with connections
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    // Create ports and a connection
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_type' => CableType::Cat6,
    ]);

    $response = $this->actingAs($user)
        ->get('/connection-reports');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        ->has('metrics')
        ->has('metrics.totalConnections')
        ->has('metrics.cableTypeDistribution')
        ->has('metrics.portTypeDistribution')
        ->has('metrics.cableLengthStats')
        ->has('metrics.portUtilization')
        ->has('metrics.connections')
        ->has('datacenterOptions')
        ->has('roomOptions')
        ->has('filters')
    );
});

test('index applies datacenter filter correctly', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create hierarchy for datacenter 1
    $datacenter1 = Datacenter::factory()->create(['name' => 'DC1']);
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter1->id]);
    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $rack1 = Rack::factory()->create(['row_id' => $row1->id]);
    $device1 = Device::factory()->create(['rack_id' => $rack1->id]);

    // Create connections in datacenter 1
    $sourcePort1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    $destPort1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    Connection::factory()->count(3)->create([
        'source_port_id' => $sourcePort1->id,
        'destination_port_id' => $destPort1->id,
    ]);

    // Create hierarchy for datacenter 2
    $datacenter2 = Datacenter::factory()->create(['name' => 'DC2']);
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter2->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);
    $rack2 = Rack::factory()->create(['row_id' => $row2->id]);
    $device2 = Device::factory()->create(['rack_id' => $rack2->id]);

    // Create connections in datacenter 2
    $sourcePort2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);
    $destPort2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);
    Connection::factory()->count(5)->create([
        'source_port_id' => $sourcePort2->id,
        'destination_port_id' => $destPort2->id,
    ]);

    // Filter by datacenter 1
    $response = $this->actingAs($user)
        ->get("/connection-reports?datacenter_id={$datacenter1->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        ->where('metrics.totalConnections', 3)
        ->where('filters.datacenter_id', $datacenter1->id)
    );
});

test('index respects role-based access control for admin vs operator', function () {
    // Create two datacenters
    $datacenter1 = Datacenter::factory()->create(['name' => 'DC1']);
    $datacenter2 = Datacenter::factory()->create(['name' => 'DC2']);

    // Create hierarchy and connections for both
    foreach ([$datacenter1, $datacenter2] as $datacenter) {
        $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
        $row = Row::factory()->create(['room_id' => $room->id]);
        $rack = Rack::factory()->create(['row_id' => $row->id]);
        $device = Device::factory()->create(['rack_id' => $rack->id]);

        $sourcePort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
        $destPort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
        Connection::factory()->create([
            'source_port_id' => $sourcePort->id,
            'destination_port_id' => $destPort->id,
        ]);
    }

    // Admin user - should see all datacenters
    $adminUser = User::factory()->create();
    $adminUser->assignRole('Administrator');

    $response = $this->actingAs($adminUser)
        ->get('/connection-reports');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        ->has('datacenterOptions', 2)
        ->where('metrics.totalConnections', 2)
    );

    // Operator user - should only see assigned datacenter
    $operatorUser = User::factory()->create();
    $operatorUser->assignRole('Operator');
    $operatorUser->datacenters()->attach($datacenter1);

    $response = $this->actingAs($operatorUser)
        ->get('/connection-reports');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        ->has('datacenterOptions', 1)
        ->where('metrics.totalConnections', 1)
    );
});

test('exportPdf returns PDF file download', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create hierarchy with a connection
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    $response = $this->actingAs($user)
        ->get('/connection-reports/export/pdf');

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/pdf');
});

test('exportCsv returns CSV file download', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create hierarchy with a connection
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    $response = $this->actingAs($user)
        ->get('/connection-reports/export/csv');

    $response->assertSuccessful();
    $response->assertDownload();
});

test('all connections are returned for client-side pagination', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create hierarchy with many connections
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    // Create 30 connections
    for ($i = 0; $i < 30; $i++) {
        $sourcePort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
        $destPort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
        Connection::factory()->create([
            'source_port_id' => $sourcePort->id,
            'destination_port_id' => $destPort->id,
        ]);
    }

    // All connections should be returned (client-side pagination)
    $response = $this->actingAs($user)
        ->get('/connection-reports');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        ->where('metrics.totalConnections', 30)
        ->has('metrics.connections', 30)
    );
});
