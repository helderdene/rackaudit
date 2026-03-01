<?php

/**
 * Gap Coverage Tests for Device Management Feature.
 *
 * This file contains strategic tests identified during Task Group 9
 * to fill critical gaps in test coverage. Each test targets a specific
 * workflow or edge case that was not covered in previous task groups.
 *
 * Tests focus on:
 * - Warranty status calculation logic (active/expired/none)
 * - Authorization/policy enforcement
 * - Validation rules for device creation
 * - Half-width device placement scenarios
 * - Device lifecycle workflow transitions
 */

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

    // Create viewer user (read-only access)
    $this->viewer = User::factory()->create();
    $this->viewer->assignRole('Viewer');

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
 * Gap Test 1: Warranty status returns 'active' for device with future end date.
 *
 * This test verifies the warranty_status calculation in DeviceResource
 * correctly returns 'active' when warranty_end_date is in the future.
 */
test('warranty status returns active for device with future warranty end date', function () {
    $device = Device::factory()->create([
        'name' => 'Server with Active Warranty',
        'device_type_id' => $this->deviceType->id,
        'warranty_start_date' => now()->subYear(),
        'warranty_end_date' => now()->addYear(),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/devices/{$device->id}");

    $response->assertOk()
        ->assertJsonPath('data.warranty_status', 'active');
});

/**
 * Gap Test 2: Warranty status returns 'expired' for device with past end date.
 *
 * This test verifies the warranty_status calculation correctly returns
 * 'expired' when warranty_end_date is in the past.
 */
test('warranty status returns expired for device with past warranty end date', function () {
    $device = Device::factory()->create([
        'name' => 'Server with Expired Warranty',
        'device_type_id' => $this->deviceType->id,
        'warranty_start_date' => now()->subYears(3),
        'warranty_end_date' => now()->subYear(),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/devices/{$device->id}");

    $response->assertOk()
        ->assertJsonPath('data.warranty_status', 'expired');
});

/**
 * Gap Test 3: Warranty status returns 'none' for device without warranty dates.
 *
 * This test verifies the warranty_status calculation correctly returns
 * 'none' when both warranty_start_date and warranty_end_date are null.
 */
test('warranty status returns none for device without warranty dates', function () {
    $device = Device::factory()->create([
        'name' => 'Server without Warranty',
        'device_type_id' => $this->deviceType->id,
        'warranty_start_date' => null,
        'warranty_end_date' => null,
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/devices/{$device->id}");

    $response->assertOk()
        ->assertJsonPath('data.warranty_status', 'none');
});

/**
 * Gap Test 4: Viewer cannot create devices (authorization enforcement).
 *
 * This test verifies that users with Viewer role are denied access
 * when attempting to create devices, enforcing the DevicePolicy.
 */
test('viewer cannot create devices', function () {
    $response = $this->actingAs($this->viewer)
        ->postJson('/devices', [
            'name' => 'Unauthorized Device',
            'device_type_id' => $this->deviceType->id,
            'lifecycle_status' => DeviceLifecycleStatus::InStock->value,
            'u_height' => 2,
            'depth' => DeviceDepth::Standard->value,
            'width_type' => DeviceWidthType::Full->value,
            'rack_face' => DeviceRackFace::Front->value,
        ]);

    $response->assertForbidden();

    $this->assertDatabaseMissing('devices', [
        'name' => 'Unauthorized Device',
    ]);
});

/**
 * Gap Test 5: Viewer cannot update or delete devices (authorization enforcement).
 *
 * This test verifies that users with Viewer role are denied access
 * when attempting to update or delete devices.
 */
test('viewer cannot update or delete devices', function () {
    $device = Device::factory()->create([
        'name' => 'Protected Device',
        'device_type_id' => $this->deviceType->id,
    ]);

    // Test update
    $updateResponse = $this->actingAs($this->viewer)
        ->putJson("/devices/{$device->id}", [
            'name' => 'Attempted Update',
            'device_type_id' => $this->deviceType->id,
            'lifecycle_status' => DeviceLifecycleStatus::Deployed->value,
            'u_height' => 2,
            'depth' => DeviceDepth::Standard->value,
            'width_type' => DeviceWidthType::Full->value,
            'rack_face' => DeviceRackFace::Front->value,
        ]);

    $updateResponse->assertForbidden();

    // Test delete
    $deleteResponse = $this->actingAs($this->viewer)
        ->deleteJson("/devices/{$device->id}");

    $deleteResponse->assertForbidden();

    // Verify device still exists unchanged
    $this->assertDatabaseHas('devices', [
        'id' => $device->id,
        'name' => 'Protected Device',
    ]);
});

/**
 * Gap Test 6: Validation rejects warranty end date before start date.
 *
 * This test verifies the StoreDeviceRequest validation rules
 * correctly reject invalid warranty date combinations.
 */
test('validation rejects warranty end date before start date', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/devices', [
            'name' => 'Device with Invalid Warranty',
            'device_type_id' => $this->deviceType->id,
            'lifecycle_status' => DeviceLifecycleStatus::InStock->value,
            'u_height' => 2,
            'depth' => DeviceDepth::Standard->value,
            'width_type' => DeviceWidthType::Full->value,
            'rack_face' => DeviceRackFace::Front->value,
            'warranty_start_date' => now()->format('Y-m-d'),
            'warranty_end_date' => now()->subYear()->format('Y-m-d'),
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['warranty_end_date']);
});

/**
 * Gap Test 7: Half-width devices can share same U position on same face.
 *
 * This test verifies that two half-width devices (half_left and half_right)
 * can be placed at the same U position on the same face without collision.
 */
test('half-width devices can share same U position on same face', function () {
    // Create first half-width device (left side)
    $leftDevice = Device::factory()->create([
        'name' => 'Left Half Device',
        'device_type_id' => $this->deviceType->id,
        'rack_id' => $this->rack->id,
        'start_u' => 10,
        'u_height' => 2,
        'rack_face' => DeviceRackFace::Front,
        'width_type' => DeviceWidthType::HalfLeft,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);

    // Create second half-width device (right side) at same position
    $rightDevice = Device::factory()->unplaced()->create([
        'name' => 'Right Half Device',
        'device_type_id' => $this->deviceType->id,
        'u_height' => 2,
    ]);

    // Place right device at same U position - should succeed
    $response = $this->actingAs($this->admin)
        ->patchJson("/devices/{$rightDevice->id}/place", [
            'rack_id' => $this->rack->id,
            'start_u' => 10,
            'face' => DeviceRackFace::Front->value,
            'width_type' => DeviceWidthType::HalfRight->value,
        ]);

    $response->assertOk();

    $rightDevice->refresh();
    expect($rightDevice->rack_id)->toBe($this->rack->id);
    expect($rightDevice->start_u)->toBe(10);
    expect($rightDevice->width_type)->toBe(DeviceWidthType::HalfRight);
});

/**
 * Gap Test 8: Device lifecycle workflow from ordered to deployed.
 *
 * This test verifies a device can transition through the expected
 * lifecycle states: ordered -> received -> in_stock -> deployed.
 */
test('device lifecycle workflow transitions correctly', function () {
    // Create device in ordered state
    $device = Device::factory()->create([
        'name' => 'Lifecycle Test Server',
        'device_type_id' => $this->deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::Ordered,
        'rack_id' => null,
    ]);

    expect($device->lifecycle_status)->toBe(DeviceLifecycleStatus::Ordered);

    // Transition to received
    $this->actingAs($this->admin)
        ->putJson("/devices/{$device->id}", [
            'name' => $device->name,
            'device_type_id' => $device->device_type_id,
            'lifecycle_status' => DeviceLifecycleStatus::Received->value,
            'u_height' => $device->u_height,
            'depth' => $device->depth->value,
            'width_type' => $device->width_type->value,
            'rack_face' => $device->rack_face->value,
        ])
        ->assertOk();

    $device->refresh();
    expect($device->lifecycle_status)->toBe(DeviceLifecycleStatus::Received);

    // Transition to in_stock
    $this->actingAs($this->admin)
        ->putJson("/devices/{$device->id}", [
            'name' => $device->name,
            'device_type_id' => $device->device_type_id,
            'lifecycle_status' => DeviceLifecycleStatus::InStock->value,
            'u_height' => $device->u_height,
            'depth' => $device->depth->value,
            'width_type' => $device->width_type->value,
            'rack_face' => $device->rack_face->value,
        ])
        ->assertOk();

    $device->refresh();
    expect($device->lifecycle_status)->toBe(DeviceLifecycleStatus::InStock);

    // Transition to deployed (with rack placement)
    $this->actingAs($this->admin)
        ->putJson("/devices/{$device->id}", [
            'name' => $device->name,
            'device_type_id' => $device->device_type_id,
            'lifecycle_status' => DeviceLifecycleStatus::Deployed->value,
            'rack_id' => $this->rack->id,
            'start_u' => 20,
            'u_height' => $device->u_height,
            'depth' => $device->depth->value,
            'width_type' => $device->width_type->value,
            'rack_face' => $device->rack_face->value,
        ])
        ->assertOk();

    $device->refresh();
    expect($device->lifecycle_status)->toBe(DeviceLifecycleStatus::Deployed);
    expect($device->rack_id)->toBe($this->rack->id);
    expect($device->start_u)->toBe(20);
});
