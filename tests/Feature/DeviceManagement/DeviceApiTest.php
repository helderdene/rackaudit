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

    // Create regular viewer user
    $this->viewer = User::factory()->create();
    $this->viewer->assignRole('Viewer');

    // Create device type
    $this->deviceType = DeviceType::factory()->create([
        'name' => 'Server',
        'default_u_size' => 2,
    ]);

    // Create datacenter hierarchy for placed devices
    $this->datacenter = Datacenter::factory()->create();
    $this->room = Room::factory()->create(['datacenter_id' => $this->datacenter->id]);
    $this->row = Row::factory()->create(['room_id' => $this->room->id]);
    $this->rack = Rack::factory()->create(['row_id' => $this->row->id]);
});

/**
 * Test 1: Index returns devices with filtering by rack_id
 */
test('index returns devices with filtering by rack_id', function () {
    // Create devices in different racks
    $device1 = Device::factory()->create([
        'name' => 'Server 01',
        'device_type_id' => $this->deviceType->id,
        'rack_id' => $this->rack->id,
        'start_u' => 1,
    ]);

    $rack2 = Rack::factory()->create(['row_id' => $this->row->id]);
    $device2 = Device::factory()->create([
        'name' => 'Server 02',
        'device_type_id' => $this->deviceType->id,
        'rack_id' => $rack2->id,
        'start_u' => 5,
    ]);

    // Unplaced device
    $device3 = Device::factory()->create([
        'name' => 'Server 03',
        'device_type_id' => $this->deviceType->id,
        'rack_id' => null,
    ]);

    // Test filtering by rack_id
    $response = $this->actingAs($this->admin)
        ->getJson("/devices?rack_id={$this->rack->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['name' => 'Server 01'])
        ->assertJsonMissing(['name' => 'Server 02'])
        ->assertJsonMissing(['name' => 'Server 03']);

    // Test getting all devices without filter
    $response = $this->actingAs($this->admin)
        ->getJson('/devices');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

/**
 * Test 2: Store creates device with auto-generated asset_tag
 */
test('store creates device with auto-generated asset_tag', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/devices', [
            'name' => 'New Web Server',
            'device_type_id' => $this->deviceType->id,
            'lifecycle_status' => DeviceLifecycleStatus::InStock->value,
            'u_height' => 2,
            'depth' => DeviceDepth::Standard->value,
            'width_type' => DeviceWidthType::Full->value,
            'rack_face' => DeviceRackFace::Front->value,
        ]);

    $response->assertCreated()
        ->assertJsonFragment(['name' => 'New Web Server']);

    // Verify asset_tag was auto-generated
    $data = $response->json('data');
    expect($data['asset_tag'])->not->toBeNull();
    expect($data['asset_tag'])->toMatch('/^ASSET-\d{8}-\d{5}$/');

    $this->assertDatabaseHas('devices', [
        'name' => 'New Web Server',
        'device_type_id' => $this->deviceType->id,
    ]);
});

/**
 * Test 3: Show returns device with all relationships
 */
test('show returns device with all relationships', function () {
    $device = Device::factory()->create([
        'name' => 'Database Server',
        'device_type_id' => $this->deviceType->id,
        'rack_id' => $this->rack->id,
        'start_u' => 10,
        'serial_number' => 'SN-12345',
        'manufacturer' => 'Dell',
        'model' => 'PowerEdge R740',
        'warranty_start_date' => now()->subYear(),
        'warranty_end_date' => now()->addYear(),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/devices/{$device->id}");

    $response->assertOk()
        ->assertJsonFragment([
            'name' => 'Database Server',
            'serial_number' => 'SN-12345',
            'manufacturer' => 'Dell',
            'model' => 'PowerEdge R740',
        ])
        ->assertJsonPath('data.device_type.id', $this->deviceType->id)
        ->assertJsonPath('data.device_type.name', 'Server')
        ->assertJsonPath('data.rack.id', $this->rack->id)
        ->assertJsonPath('data.warranty_status', 'active');
});

/**
 * Test 4: Update modifies device fields (but not asset_tag)
 */
test('update modifies device fields but not asset_tag', function () {
    $device = Device::factory()->create([
        'name' => 'Original Name',
        'device_type_id' => $this->deviceType->id,
        'serial_number' => 'ORIGINAL-SN',
    ]);

    $originalAssetTag = $device->asset_tag;

    $newDeviceType = DeviceType::factory()->create(['name' => 'Switch']);

    $response = $this->actingAs($this->admin)
        ->putJson("/devices/{$device->id}", [
            'name' => 'Updated Name',
            'device_type_id' => $newDeviceType->id,
            'lifecycle_status' => DeviceLifecycleStatus::Deployed->value,
            'serial_number' => 'UPDATED-SN',
            'u_height' => 4,
            'depth' => DeviceDepth::Deep->value,
            'width_type' => DeviceWidthType::Full->value,
            'rack_face' => DeviceRackFace::Front->value,
        ]);

    $response->assertOk()
        ->assertJsonFragment([
            'name' => 'Updated Name',
            'serial_number' => 'UPDATED-SN',
        ]);

    // Verify asset_tag remains unchanged
    $data = $response->json('data');
    expect($data['asset_tag'])->toBe($originalAssetTag);

    $this->assertDatabaseHas('devices', [
        'id' => $device->id,
        'name' => 'Updated Name',
        'asset_tag' => $originalAssetTag,
    ]);
});

/**
 * Test 5: Update can place/unplace device from rack
 */
test('update can place and unplace device from rack', function () {
    // Create an unplaced device
    $device = Device::factory()->unplaced()->create([
        'name' => 'Movable Server',
        'device_type_id' => $this->deviceType->id,
    ]);

    expect($device->rack_id)->toBeNull();

    // Place the device in a rack
    $response = $this->actingAs($this->admin)
        ->putJson("/devices/{$device->id}", [
            'name' => $device->name,
            'device_type_id' => $device->device_type_id,
            'lifecycle_status' => DeviceLifecycleStatus::Deployed->value,
            'rack_id' => $this->rack->id,
            'start_u' => 5,
            'u_height' => $device->u_height,
            'depth' => $device->depth->value,
            'width_type' => $device->width_type->value,
            'rack_face' => $device->rack_face->value,
        ]);

    $response->assertOk();

    $device->refresh();
    expect($device->rack_id)->toBe($this->rack->id);
    expect($device->start_u)->toBe(5);

    // Unplace the device
    $response = $this->actingAs($this->admin)
        ->putJson("/devices/{$device->id}", [
            'name' => $device->name,
            'device_type_id' => $device->device_type_id,
            'lifecycle_status' => DeviceLifecycleStatus::InStock->value,
            'rack_id' => null,
            'start_u' => null,
            'u_height' => $device->u_height,
            'depth' => $device->depth->value,
            'width_type' => $device->width_type->value,
            'rack_face' => $device->rack_face->value,
        ]);

    $response->assertOk();

    $device->refresh();
    expect($device->rack_id)->toBeNull();
    expect($device->start_u)->toBeNull();
});

/**
 * Test 6: Destroy deletes device
 */
test('destroy deletes device', function () {
    $device = Device::factory()->create([
        'name' => 'To Be Deleted',
        'device_type_id' => $this->deviceType->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->deleteJson("/devices/{$device->id}");

    $response->assertOk()
        ->assertJsonFragment(['message' => 'Device deleted successfully.']);

    $this->assertDatabaseMissing('devices', [
        'id' => $device->id,
    ]);
});
