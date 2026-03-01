<?php

use App\Enums\DeviceLifecycleStatus;
use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    // Create device type
    $this->deviceType = DeviceType::factory()->create([
        'name' => 'Server',
        'default_u_size' => 2,
    ]);

    // Create datacenter hierarchy
    $this->datacenter = Datacenter::factory()->create();
    $this->room = Room::factory()->create(['datacenter_id' => $this->datacenter->id]);
    $this->row = Row::factory()->create(['room_id' => $this->room->id]);
    $this->rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'u_height' => 42,
    ]);
});

/**
 * Test 1: Elevation page renders real devices
 */
test('elevation page renders real devices', function () {
    // Create placed devices
    $placedDevice = Device::factory()->create([
        'name' => 'Web Server 01',
        'device_type_id' => $this->deviceType->id,
        'rack_id' => $this->rack->id,
        'start_u' => 1,
        'u_height' => 2,
        'rack_face' => DeviceRackFace::Front,
        'width_type' => DeviceWidthType::Full,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);

    // Create unplaced device
    $unplacedDevice = Device::factory()->unplaced()->create([
        'name' => 'Spare Server',
        'device_type_id' => $this->deviceType->id,
        'u_height' => 2,
        'lifecycle_status' => DeviceLifecycleStatus::InStock,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            $this->datacenter,
            $this->room,
            $this->row,
            $this->rack,
        ]));

    $response->assertOk();

    // Verify the elevation page renders with real device data
    $response->assertInertia(fn ($page) => $page
        ->component('Racks/Elevation')
        // Check devices data is passed (should have 'devices' not 'devices')
        ->has('devices')
        ->has('devices.placed', 1)
        ->has('devices.unplaced', 1)
        // Verify placed device has expected structure with all placement fields
        ->where('devices.placed.0.id', (string) $placedDevice->id)
        ->where('devices.placed.0.name', 'Web Server 01')
        ->where('devices.placed.0.type', 'Server')
        ->where('devices.placed.0.u_size', 2)
        ->where('devices.placed.0.start_u', 1)
        ->where('devices.placed.0.face', 'front')
        ->where('devices.placed.0.width', 'full')
        // Verify unplaced device has expected structure (no placement fields like start_u/face)
        ->where('devices.unplaced.0.id', (string) $unplacedDevice->id)
        ->where('devices.unplaced.0.name', 'Spare Server')
        ->where('devices.unplaced.0.type', 'Server')
        ->where('devices.unplaced.0.u_size', 2)
        ->where('devices.unplaced.0.width', 'full')
    );
});

/**
 * Test 2: Device drag-and-drop updates placement
 */
test('device drag-and-drop updates placement', function () {
    // Create an unplaced device
    $device = Device::factory()->unplaced()->create([
        'name' => 'Server To Place',
        'device_type_id' => $this->deviceType->id,
        'u_height' => 2,
        'lifecycle_status' => DeviceLifecycleStatus::InStock,
    ]);

    // Verify device is unplaced
    expect($device->rack_id)->toBeNull();
    expect($device->start_u)->toBeNull();

    // Simulate placing device via the place API (this is what the frontend would call on drop)
    $response = $this->actingAs($this->admin)
        ->patchJson("/devices/{$device->id}/place", [
            'rack_id' => $this->rack->id,
            'start_u' => 5,
            'face' => DeviceRackFace::Front->value,
            'width_type' => DeviceWidthType::Full->value,
        ]);

    $response->assertOk();

    // Verify device is now placed
    $device->refresh();
    expect($device->rack_id)->toBe($this->rack->id);
    expect($device->start_u)->toBe(5);
    expect($device->rack_face)->toBe(DeviceRackFace::Front);
    expect($device->width_type)->toBe(DeviceWidthType::Full);

    // Verify the elevation page now shows the device as placed
    $elevationResponse = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            $this->datacenter,
            $this->room,
            $this->row,
            $this->rack,
        ]));

    $elevationResponse->assertInertia(fn ($page) => $page
        ->has('devices.placed', 1)
        ->has('devices.unplaced', 0)
        ->where('devices.placed.0.id', (string) $device->id)
        ->where('devices.placed.0.start_u', 5)
    );
});

/**
 * Test 3: Unplaced devices appear in sidebar
 */
test('unplaced devices appear in sidebar', function () {
    // Create multiple unplaced devices
    $device1 = Device::factory()->unplaced()->create([
        'name' => 'Spare Server A',
        'device_type_id' => $this->deviceType->id,
        'u_height' => 2,
        'lifecycle_status' => DeviceLifecycleStatus::InStock,
    ]);

    $device2 = Device::factory()->unplaced()->create([
        'name' => 'Spare Server B',
        'device_type_id' => $this->deviceType->id,
        'u_height' => 4,
        'lifecycle_status' => DeviceLifecycleStatus::Received,
    ]);

    // Create one placed device (should NOT appear in unplaced)
    $placedDevice = Device::factory()->create([
        'name' => 'Deployed Server',
        'device_type_id' => $this->deviceType->id,
        'rack_id' => $this->rack->id,
        'start_u' => 10,
        'u_height' => 2,
        'rack_face' => DeviceRackFace::Front,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            $this->datacenter,
            $this->room,
            $this->row,
            $this->rack,
        ]));

    $response->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('Racks/Elevation')
        // Should have 2 unplaced devices
        ->has('devices.unplaced', 2)
        // Should have 1 placed device
        ->has('devices.placed', 1)
        // Verify unplaced devices are in the list (ordered by name)
        ->where('devices.unplaced.0.name', 'Spare Server A')
        ->where('devices.unplaced.1.name', 'Spare Server B')
        // Verify placed device is not in unplaced
        ->where('devices.placed.0.name', 'Deployed Server')
    );
});
