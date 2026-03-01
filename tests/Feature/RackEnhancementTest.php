<?php

use App\Models\Device;
use App\Models\Rack;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('rack specs JSON cast stores and retrieves key-value pairs correctly', function () {
    $specs = [
        'max_weight_kg' => 1000,
        'cable_management' => 'vertical',
        'cooling_type' => 'rear-door',
        'power_phases' => 3,
    ];

    $rack = Rack::factory()->create([
        'specs' => $specs,
    ]);

    // Refresh from database to ensure cast is working on retrieval
    $rack->refresh();

    expect($rack->specs)->toBeArray();
    expect($rack->specs)->toBe($specs);
    expect($rack->specs['max_weight_kg'])->toBe(1000);
    expect($rack->specs['cable_management'])->toBe('vertical');
    expect($rack->specs['cooling_type'])->toBe('rear-door');
    expect($rack->specs['power_phases'])->toBe(3);
});

test('rack specs JSON cast handles null values correctly', function () {
    $rack = Rack::factory()->create([
        'specs' => null,
    ]);

    $rack->refresh();

    expect($rack->specs)->toBeNull();
});

test('rack installation_date cast converts to Carbon date correctly', function () {
    $installDate = '2024-06-15';

    $rack = Rack::factory()->create([
        'installation_date' => $installDate,
    ]);

    $rack->refresh();

    expect($rack->installation_date)->toBeInstanceOf(Carbon::class);
    expect($rack->installation_date->format('Y-m-d'))->toBe($installDate);
});

test('rack new fillable fields can be mass assigned', function () {
    $rack = Rack::factory()->create([
        'manufacturer' => 'APC',
        'model' => 'NetShelter SX',
        'depth' => '1070mm',
        'installation_date' => '2024-03-20',
        'location_notes' => 'Near fire exit, requires clearance check',
        'specs' => ['max_load' => 1500],
    ]);

    $rack->refresh();

    expect($rack->manufacturer)->toBe('APC');
    expect($rack->model)->toBe('NetShelter SX');
    expect($rack->depth)->toBe('1070mm');
    expect($rack->installation_date->format('Y-m-d'))->toBe('2024-03-20');
    expect($rack->location_notes)->toBe('Near fire exit, requires clearance check');
    expect($rack->specs)->toBe(['max_load' => 1500]);
});

test('rack devices relationship returns correct devices with eager loading', function () {
    $rack = Rack::factory()->create();

    // Create devices placed in this rack
    $device1 = Device::factory()->placed($rack, 1)->create(['name' => 'Server 01']);
    $device2 = Device::factory()->placed($rack, 5)->create(['name' => 'Server 02']);
    $device3 = Device::factory()->placed($rack, 10)->create(['name' => 'Switch 01']);

    // Create a device NOT in this rack
    Device::factory()->unplaced()->create(['name' => 'Unplaced Device']);

    // Test eager loading
    $rackWithDevices = Rack::with('devices')->find($rack->id);

    expect($rackWithDevices->devices)->toHaveCount(3);
    expect($rackWithDevices->devices->pluck('name')->toArray())
        ->toContain('Server 01', 'Server 02', 'Switch 01');
});

test('rack devices relationship eager loads device type correctly', function () {
    $rack = Rack::factory()->create();

    // Create devices with device types
    Device::factory()->placed($rack, 1)->create();
    Device::factory()->placed($rack, 5)->create();

    // Test eager loading with deviceType
    $rackWithDevices = Rack::with('devices.deviceType')->find($rack->id);

    expect($rackWithDevices->devices)->toHaveCount(2);

    foreach ($rackWithDevices->devices as $device) {
        expect($device->relationLoaded('deviceType'))->toBeTrue();
        expect($device->deviceType)->not->toBeNull();
    }
});
