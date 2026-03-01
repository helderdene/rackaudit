<?php

use App\Enums\PortDirection;
use App\Enums\PortStatus;
use App\Enums\PortSubtype;
use App\Enums\PortType;
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
});

test('port section displays on Device Show page', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();

    // Create some ports for the device
    Port::factory()->create([
        'device_id' => $device->id,
        'label' => 'eth0',
        'type' => PortType::Ethernet,
        'subtype' => PortSubtype::Gbe10,
        'status' => PortStatus::Available,
        'direction' => PortDirection::Bidirectional,
    ]);
    Port::factory()->create([
        'device_id' => $device->id,
        'label' => 'eth1',
        'type' => PortType::Ethernet,
        'subtype' => PortSubtype::Gbe10,
        'status' => PortStatus::Connected,
        'direction' => PortDirection::Uplink,
    ]);

    $response = $this->actingAs($user)
        ->get("/devices/{$device->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Devices/Show')
        ->has('ports', 2)
        ->has('portTypeOptions')
        ->has('portSubtypeOptions')
        ->has('portStatusOptions')
        ->has('portDirectionOptions')
    );
});

test('port table renders with correct columns', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();

    $port = Port::factory()->create([
        'device_id' => $device->id,
        'label' => 'eth0',
        'type' => PortType::Ethernet,
        'subtype' => PortSubtype::Gbe10,
        'status' => PortStatus::Available,
        'direction' => PortDirection::Bidirectional,
    ]);

    $response = $this->actingAs($user)
        ->get("/devices/{$device->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Devices/Show')
        ->has('ports', 1)
        ->where('ports.0.label', 'eth0')
        ->where('ports.0.type', 'ethernet')
        ->where('ports.0.type_label', 'Ethernet')
        ->where('ports.0.subtype', 'gbe10')
        ->where('ports.0.subtype_label', '10GbE')
        ->where('ports.0.status', 'available')
        ->where('ports.0.status_label', 'Available')
        ->where('ports.0.direction', 'bidirectional')
        ->where('ports.0.direction_label', 'Bidirectional')
    );
});

test('empty state displays when no ports exist', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();

    // Device has no ports

    $response = $this->actingAs($user)
        ->get("/devices/{$device->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Devices/Show')
        ->has('ports', 0)
        ->has('portTypeOptions')
        ->has('portSubtypeOptions')
        ->has('portStatusOptions')
        ->has('portDirectionOptions')
    );
});

test('add port button only shows when user has edit permission', function () {
    // Create a Viewer user (cannot edit but can view devices in assigned datacenter)
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    // Create an IT Manager user (can edit)
    $manager = User::factory()->create();
    $manager->assignRole('IT Manager');

    // Create a device placed in a rack within a datacenter the viewer has access to
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create([
        'rack_id' => $rack->id,
        'start_u' => 1,
    ]);

    // Assign the datacenter to the viewer
    $viewer->datacenters()->attach($datacenter->id);

    // Viewer should be able to view the device but not edit it
    $response = $this->actingAs($viewer)
        ->get("/devices/{$device->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Devices/Show')
        ->where('canEdit', false)
    );

    // IT Manager should have canEdit permission
    $response = $this->actingAs($manager)
        ->get("/devices/{$device->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Devices/Show')
        ->where('canEdit', true)
    );
});
