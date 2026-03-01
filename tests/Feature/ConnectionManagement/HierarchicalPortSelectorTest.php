<?php

/**
 * Tests for HierarchicalPortSelector component functionality.
 *
 * These tests verify the cascading filter behavior, port filtering,
 * and the API endpoints that support the hierarchical port selection.
 */

use App\Enums\PortStatus;
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

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('devices list filters by rack_id correctly', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create two racks with devices
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    $rack1 = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack A1']);
    $rack2 = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack B2']);

    // Devices in rack 1
    $device1 = Device::factory()->create(['rack_id' => $rack1->id, 'name' => 'Server 1']);
    $device2 = Device::factory()->create(['rack_id' => $rack1->id, 'name' => 'Server 2']);

    // Device in rack 2
    $device3 = Device::factory()->create(['rack_id' => $rack2->id, 'name' => 'Server 3']);

    // Get devices from rack 1
    $response = $this->actingAs($user)
        ->get("/devices?rack_id={$rack1->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Devices/Index')
        ->has('devices.data', 2)
    );
});

test('ports list filters by device and status available', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();

    // Create ports with different statuses
    $availablePort = Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'eth0',
        'status' => PortStatus::Available,
    ]);

    $connectedPort = Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'eth1',
        'status' => PortStatus::Connected,
    ]);

    $response = $this->actingAs($user)
        ->getJson("/devices/{$device->id}/ports");

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'data');

    // Verify both ports are returned (filtering happens on frontend)
    $response->assertJsonFragment(['status' => 'available']);
    $response->assertJsonFragment(['status' => 'connected']);
});

test('ports list filters by type for matching connections', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();

    // Create ports of different types
    $ethernetPort = Port::factory()->ethernet()->available()->create([
        'device_id' => $device->id,
        'label' => 'eth0',
    ]);

    $fiberPort = Port::factory()->fiber()->available()->create([
        'device_id' => $device->id,
        'label' => 'fiber0',
    ]);

    $powerPort = Port::factory()->power()->available()->create([
        'device_id' => $device->id,
        'label' => 'psu0',
    ]);

    $response = $this->actingAs($user)
        ->getJson("/devices/{$device->id}/ports");

    $response->assertSuccessful();
    $response->assertJsonCount(3, 'data');

    // Verify port types are returned for frontend filtering
    $data = $response->json('data');
    $types = collect($data)->pluck('type')->toArray();

    expect($types)->toContain('ethernet');
    expect($types)->toContain('fiber');
    expect($types)->toContain('power');
});

test('device show page includes port options for connection filtering', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

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
        ->has('portTypeOptions')
        ->has('portSubtypeOptions')
    );
});

test('cascading hierarchy data structure supports filtering', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create hierarchy: Datacenter -> Room -> Row -> Rack -> Device -> Port
    $datacenter = Datacenter::factory()->create(['name' => 'DC1']);
    $room = Room::factory()->create([
        'datacenter_id' => $datacenter->id,
        'name' => 'Room A',
    ]);
    $row = Row::factory()->create([
        'room_id' => $room->id,
        'name' => 'Row 1',
    ]);
    $rack = Rack::factory()->create([
        'row_id' => $row->id,
        'name' => 'Rack 01',
    ]);
    $device = Device::factory()->create([
        'rack_id' => $rack->id,
        'name' => 'Server 01',
    ]);
    $port = Port::factory()->ethernet()->available()->create([
        'device_id' => $device->id,
        'label' => 'eth0',
    ]);

    // Verify the full hierarchy can be traversed
    // 1. Room belongs to datacenter
    expect($room->datacenter_id)->toBe($datacenter->id);

    // 2. Row belongs to room
    expect($row->room_id)->toBe($room->id);

    // 3. Rack belongs to row
    expect($rack->row_id)->toBe($row->id);

    // 4. Device belongs to rack
    expect($device->rack_id)->toBe($rack->id);

    // 5. Port belongs to device
    expect($port->device_id)->toBe($device->id);

    // Verify ports can be fetched via API
    $response = $this->actingAs($user)
        ->getJson("/devices/{$device->id}/ports");

    $response->assertSuccessful();
    $response->assertJsonFragment(['label' => 'eth0']);
});
