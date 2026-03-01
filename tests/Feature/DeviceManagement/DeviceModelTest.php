<?php

use App\Enums\DeviceDepth;
use App\Enums\DeviceLifecycleStatus;
use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Rack;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Device can be created with required fields', function () {
    $deviceType = DeviceType::factory()->create();

    $device = Device::create([
        'name' => 'Web Server 01',
        'device_type_id' => $deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::InStock,
        'u_height' => 2,
        'depth' => DeviceDepth::Standard,
        'width_type' => DeviceWidthType::Full,
        'rack_face' => DeviceRackFace::Front,
    ]);

    expect($device)->toBeInstanceOf(Device::class);
    expect($device->name)->toBe('Web Server 01');
    expect($device->device_type_id)->toBe($deviceType->id);
    expect($device->lifecycle_status)->toBe(DeviceLifecycleStatus::InStock);
    expect($device->u_height)->toEqual(2);
    expect($device->rack_id)->toBeNull();
    expect($device->id)->not->toBeNull();
});

test('Device auto-generates asset_tag on create', function () {
    $deviceType = DeviceType::factory()->create();

    $device = Device::create([
        'name' => 'Database Server 01',
        'device_type_id' => $deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::InStock,
        'u_height' => 4,
        'depth' => DeviceDepth::Standard,
        'width_type' => DeviceWidthType::Full,
        'rack_face' => DeviceRackFace::Front,
    ]);

    expect($device->asset_tag)->not->toBeNull();
    expect($device->asset_tag)->not->toBeEmpty();
});

test('Device asset_tag format matches ASSET-{YYYYMMDD}-{sequential} pattern', function () {
    $deviceType = DeviceType::factory()->create();

    $device1 = Device::create([
        'name' => 'Server 01',
        'device_type_id' => $deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::InStock,
        'u_height' => 2,
        'depth' => DeviceDepth::Standard,
        'width_type' => DeviceWidthType::Full,
        'rack_face' => DeviceRackFace::Front,
    ]);

    $device2 = Device::create([
        'name' => 'Server 02',
        'device_type_id' => $deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::InStock,
        'u_height' => 2,
        'depth' => DeviceDepth::Standard,
        'width_type' => DeviceWidthType::Full,
        'rack_face' => DeviceRackFace::Front,
    ]);

    $today = now()->format('Ymd');
    $pattern = '/^ASSET-' . $today . '-\d{5}$/';

    expect($device1->asset_tag)->toMatch($pattern);
    expect($device2->asset_tag)->toMatch($pattern);

    // Verify sequential numbering
    $tag1Number = (int) substr($device1->asset_tag, -5);
    $tag2Number = (int) substr($device2->asset_tag, -5);
    expect($tag2Number)->toBe($tag1Number + 1);
});

test('Device belongs to DeviceType', function () {
    $deviceType = DeviceType::factory()->create([
        'name' => 'Server',
        'default_u_size' => 2,
    ]);

    $device = Device::factory()->create([
        'device_type_id' => $deviceType->id,
    ]);

    expect($device->deviceType)->toBeInstanceOf(DeviceType::class);
    expect($device->deviceType->id)->toBe($deviceType->id);
    expect($device->deviceType->name)->toBe('Server');
});

test('Device belongs to Rack (nullable relationship)', function () {
    $deviceType = DeviceType::factory()->create();
    $rack = Rack::factory()->create();

    // Device without rack (unplaced inventory)
    $unplacedDevice = Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'rack_id' => null,
    ]);

    expect($unplacedDevice->rack)->toBeNull();

    // Device with rack (placed in rack)
    $placedDevice = Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'rack_id' => $rack->id,
        'start_u' => 5,
    ]);

    expect($placedDevice->rack)->toBeInstanceOf(Rack::class);
    expect($placedDevice->rack->id)->toBe($rack->id);
});

test('Device lifecycle status enum is properly cast', function () {
    $deviceType = DeviceType::factory()->create();

    $device = Device::create([
        'name' => 'Test Server',
        'device_type_id' => $deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'u_height' => 1,
        'depth' => DeviceDepth::Standard,
        'width_type' => DeviceWidthType::Full,
        'rack_face' => DeviceRackFace::Front,
    ]);

    // Retrieve fresh from database
    $freshDevice = Device::find($device->id);

    expect($freshDevice->lifecycle_status)->toBeInstanceOf(DeviceLifecycleStatus::class);
    expect($freshDevice->lifecycle_status)->toBe(DeviceLifecycleStatus::Deployed);
    expect($freshDevice->lifecycle_status->label())->toBe('Deployed');
});
