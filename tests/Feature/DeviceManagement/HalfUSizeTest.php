<?php

use App\Enums\DeviceDepth;
use App\Enums\DeviceLifecycleStatus;
use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('Device can be created with 0.5U height', function () {
    $deviceType = DeviceType::factory()->create();

    $device = Device::create([
        'name' => 'Half-U Device',
        'device_type_id' => $deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::InStock,
        'u_height' => 0.5,
        'depth' => DeviceDepth::Standard,
        'width_type' => DeviceWidthType::Full,
        'rack_face' => DeviceRackFace::Front,
    ]);

    expect($device)->toBeInstanceOf(Device::class);
    expect($device->u_height)->toEqual(0.5);
});

test('DeviceType can have 0.5U default size', function () {
    $deviceType = DeviceType::create([
        'name' => 'Half-U Panel',
        'default_u_size' => 0.5,
    ]);

    expect($deviceType->default_u_size)->toEqual(0.5);
});

test('store endpoint rejects 0.5U for device creation', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');
    $deviceType = DeviceType::factory()->create();

    $response = $this->actingAs($user)
        ->post('/devices', [
            'name' => 'Half-U Device',
            'device_type_id' => $deviceType->id,
            'lifecycle_status' => 'in_stock',
            'u_height' => 0.5,
            'depth' => 'standard',
            'width_type' => 'full',
            'rack_face' => 'front',
        ]);

    $response->assertSessionHasErrors(['u_height']);
});

test('store endpoint rejects 0.5U for device type creation', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    $response = $this->actingAs($user)
        ->post('/device-types', [
            'name' => 'Half-U Type',
            'default_u_size' => 0.5,
        ]);

    $response->assertSessionHasErrors(['default_u_size']);
});

test('store endpoint rejects invalid U height values', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');
    $deviceType = DeviceType::factory()->create();

    // Test 0.3 (invalid - not 0.5 or whole number)
    $response = $this->actingAs($user)
        ->post('/devices', [
            'name' => 'Invalid Device',
            'device_type_id' => $deviceType->id,
            'lifecycle_status' => 'in_stock',
            'u_height' => 0.3,
            'depth' => 'standard',
            'width_type' => 'full',
            'rack_face' => 'front',
        ]);

    $response->assertSessionHasErrors(['u_height']);
});

test('factory withUHeight accepts 0.5', function () {
    $deviceType = DeviceType::factory()->create();
    $device = Device::factory()
        ->for($deviceType)
        ->withUHeight(0.5)
        ->create();

    expect($device->u_height)->toEqual(0.5);
});

test('factory withDefaultUSize accepts 0.5', function () {
    $deviceType = DeviceType::factory()
        ->withDefaultUSize(0.5)
        ->create();

    expect($deviceType->default_u_size)->toEqual(0.5);
});
