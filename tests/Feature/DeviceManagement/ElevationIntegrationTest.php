<?php

use App\Enums\DeviceDepth;
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
 * Test 1: Elevation endpoint returns real devices instead of placeholders
 */
test('elevation endpoint returns real devices instead of placeholders', function () {
    // Create placed devices
    $placedDevice1 = Device::factory()->create([
        'name' => 'Web Server 01',
        'device_type_id' => $this->deviceType->id,
        'rack_id' => $this->rack->id,
        'start_u' => 1,
        'u_height' => 2,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);

    $placedDevice2 = Device::factory()->create([
        'name' => 'Database Server',
        'device_type_id' => $this->deviceType->id,
        'rack_id' => $this->rack->id,
        'start_u' => 5,
        'u_height' => 4,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);

    // Create unplaced devices
    $unplacedDevice = Device::factory()->create([
        'name' => 'Spare Server',
        'device_type_id' => $this->deviceType->id,
        'rack_id' => null,
        'start_u' => null,
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

    // Verify placed devices are in the response
    $response->assertInertia(fn ($page) => $page
        ->component('Racks/Elevation')
        ->has('devices.placed', 2)
        ->has('devices.unplaced', 1)
        ->where('devices.placed.0.name', 'Database Server') // Ordered by name
        ->where('devices.placed.1.name', 'Web Server 01')
        ->where('devices.unplaced.0.name', 'Spare Server')
    );
});

/**
 * Test 2: Placing a device updates rack_id and start_u
 */
test('placing a device updates rack_id and start_u', function () {
    // Create an unplaced device
    $device = Device::factory()->unplaced()->create([
        'name' => 'Server To Place',
        'device_type_id' => $this->deviceType->id,
        'u_height' => 2,
    ]);

    expect($device->rack_id)->toBeNull();
    expect($device->start_u)->toBeNull();

    // Place the device
    $response = $this->actingAs($this->admin)
        ->patchJson("/devices/{$device->id}/place", [
            'rack_id' => $this->rack->id,
            'start_u' => 10,
            'face' => DeviceRackFace::Front->value,
            'width_type' => DeviceWidthType::Full->value,
        ]);

    $response->assertOk()
        ->assertJsonFragment(['message' => 'Device placed successfully.']);

    $device->refresh();

    expect($device->rack_id)->toBe($this->rack->id);
    expect($device->start_u)->toBe(10);
    expect($device->rack_face)->toBe(DeviceRackFace::Front);
    expect($device->width_type)->toBe(DeviceWidthType::Full);
});

/**
 * Test 3: Removing device from rack clears placement fields
 */
test('removing device from rack clears placement fields', function () {
    // Create a placed device
    $device = Device::factory()->create([
        'name' => 'Server To Remove',
        'device_type_id' => $this->deviceType->id,
        'rack_id' => $this->rack->id,
        'start_u' => 15,
        'rack_face' => DeviceRackFace::Front,
        'width_type' => DeviceWidthType::Full,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);

    expect($device->rack_id)->toBe($this->rack->id);
    expect($device->start_u)->toBe(15);

    // Remove the device from the rack
    $response = $this->actingAs($this->admin)
        ->patchJson("/devices/{$device->id}/unplace");

    $response->assertOk()
        ->assertJsonFragment(['message' => 'Device removed from rack successfully.']);

    $device->refresh();

    expect($device->rack_id)->toBeNull();
    expect($device->start_u)->toBeNull();
});

/**
 * Test 4: Device collision detection prevents overlapping U positions
 */
test('device collision detection prevents overlapping U positions', function () {
    // Create an existing placed device at U1-U4 (4U device)
    $existingDevice = Device::factory()->create([
        'name' => 'Existing Server',
        'device_type_id' => $this->deviceType->id,
        'rack_id' => $this->rack->id,
        'start_u' => 1,
        'u_height' => 4,
        'rack_face' => DeviceRackFace::Front,
        'width_type' => DeviceWidthType::Full,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);

    // Create an unplaced device to try to place in overlapping position
    $newDevice = Device::factory()->unplaced()->create([
        'name' => 'New Server',
        'device_type_id' => $this->deviceType->id,
        'u_height' => 2,
    ]);

    // Try to place at U3 (overlaps with existing device at U1-U4)
    $response = $this->actingAs($this->admin)
        ->patchJson("/devices/{$newDevice->id}/place", [
            'rack_id' => $this->rack->id,
            'start_u' => 3,
            'face' => DeviceRackFace::Front->value,
            'width_type' => DeviceWidthType::Full->value,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['start_u']);

    // Verify device was not placed
    $newDevice->refresh();
    expect($newDevice->rack_id)->toBeNull();

    // However, placing on rear face at same position should work
    $response = $this->actingAs($this->admin)
        ->patchJson("/devices/{$newDevice->id}/place", [
            'rack_id' => $this->rack->id,
            'start_u' => 3,
            'face' => DeviceRackFace::Rear->value,
            'width_type' => DeviceWidthType::Full->value,
        ]);

    $response->assertOk();

    $newDevice->refresh();
    expect($newDevice->rack_id)->toBe($this->rack->id);
    expect($newDevice->start_u)->toBe(3);
    expect($newDevice->rack_face)->toBe(DeviceRackFace::Rear);
});
