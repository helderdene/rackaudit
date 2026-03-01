<?php

use App\Enums\DeviceLifecycleStatus;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Disable Inertia page existence check during testing
    config(['inertia.testing.ensure_pages_exist' => false]);

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    // Create viewer user
    $this->viewer = User::factory()->create();
    $this->viewer->assignRole('Viewer');

    // Create device type for tests
    $this->deviceType = DeviceType::factory()->create([
        'name' => 'Server',
        'default_u_size' => 2,
    ]);
});

/**
 * Test 1: Devices index page renders device list
 */
test('devices index page renders device list', function () {
    // Create devices
    Device::factory()->create([
        'name' => 'Web Server 01',
        'device_type_id' => $this->deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);
    Device::factory()->create([
        'name' => 'DB Server 01',
        'device_type_id' => $this->deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::InStock,
    ]);

    $response = $this->actingAs($this->admin)->get('/devices');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Devices/Index')
            ->has('devices.data', 2)
            ->has('lifecycleStatusOptions')
            ->has('filters')
            ->has('canCreate')
        );
});

/**
 * Test 2: Create form with device type selection
 */
test('create form displays device type selection', function () {
    // Create additional device types
    DeviceType::factory()->create(['name' => 'Switch', 'default_u_size' => 1]);
    DeviceType::factory()->create(['name' => 'Router', 'default_u_size' => 1]);

    $response = $this->actingAs($this->admin)->get('/devices/create');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Devices/Create')
            ->has('deviceTypeOptions', 3)
            ->has('lifecycleStatusOptions')
            ->has('depthOptions')
            ->has('widthTypeOptions')
            ->has('rackFaceOptions')
        );
});

/**
 * Test 3: Show page displays all device details
 */
test('show page displays all device details', function () {
    $device = Device::factory()->create([
        'name' => 'Production Server',
        'device_type_id' => $this->deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'serial_number' => 'SN-12345',
        'manufacturer' => 'Dell',
        'model' => 'PowerEdge R750',
        'u_height' => 2,
        'specs' => ['cpu' => '32 cores', 'ram' => '256GB'],
        'warranty_start_date' => now()->subYear(),
        'warranty_end_date' => now()->addYear(),
    ]);

    $response = $this->actingAs($this->admin)->get("/devices/{$device->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Devices/Show')
            ->where('device.id', $device->id)
            ->where('device.name', 'Production Server')
            ->where('device.serial_number', 'SN-12345')
            ->where('device.manufacturer', 'Dell')
            ->where('device.model', 'PowerEdge R750')
            ->where('device.warranty_status', 'active')
            ->has('device.device_type')
            ->has('device.specs')
            ->has('canEdit')
            ->has('canDelete')
        );
});

/**
 * Test 4: Edit form updates device
 */
test('edit form updates device', function () {
    $device = Device::factory()->create([
        'name' => 'Old Name',
        'device_type_id' => $this->deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::InStock,
    ]);

    // First verify edit page loads with device data
    $response = $this->actingAs($this->admin)->get("/devices/{$device->id}/edit");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Devices/Edit')
            ->where('device.id', $device->id)
            ->where('device.name', 'Old Name')
            ->has('deviceTypeOptions')
            ->has('lifecycleStatusOptions')
        );

    // Then submit update
    $updateResponse = $this->actingAs($this->admin)
        ->put("/devices/{$device->id}", [
            'name' => 'New Name',
            'device_type_id' => $this->deviceType->id,
            'lifecycle_status' => 'deployed',
            'u_height' => 2,
            'depth' => 'standard',
            'width_type' => 'full',
            'rack_face' => 'front',
        ]);

    $updateResponse->assertRedirect('/devices');

    $this->assertDatabaseHas('devices', [
        'id' => $device->id,
        'name' => 'New Name',
        'lifecycle_status' => 'deployed',
    ]);
});
