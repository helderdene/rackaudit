<?php

use App\Models\DeviceType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('DeviceType can be created with required fields', function () {
    $deviceType = DeviceType::create([
        'name' => 'Server',
        'default_u_size' => 2,
    ]);

    expect($deviceType->name)->toBe('Server');
    expect($deviceType->default_u_size)->toEqual(2);
    expect($deviceType->description)->toBeNull();
    expect($deviceType)->toBeInstanceOf(DeviceType::class);
    expect($deviceType->id)->not->toBeNull();
});

test('DeviceType soft delete preserves historical references', function () {
    $deviceType = DeviceType::factory()->create([
        'name' => 'Switch',
        'default_u_size' => 1,
    ]);

    $originalId = $deviceType->id;

    $deviceType->delete();

    // Should not appear in normal queries
    expect(DeviceType::find($originalId))->toBeNull();
    expect(DeviceType::where('name', 'Switch')->first())->toBeNull();

    // Should still exist in database with deleted_at set
    expect(DeviceType::withTrashed()->find($originalId))->not->toBeNull();
    expect(DeviceType::withTrashed()->find($originalId)->deleted_at)->not->toBeNull();
});

test('DeviceType can be restored after soft delete', function () {
    $deviceType = DeviceType::factory()->create([
        'name' => 'Router',
        'default_u_size' => 1,
    ]);

    $originalId = $deviceType->id;

    $deviceType->delete();

    // Verify it's soft deleted
    expect(DeviceType::find($originalId))->toBeNull();

    // Restore the device type
    DeviceType::withTrashed()->find($originalId)->restore();

    // Should now appear in normal queries
    $restoredDeviceType = DeviceType::find($originalId);
    expect($restoredDeviceType)->not->toBeNull();
    expect($restoredDeviceType->name)->toBe('Router');
    expect($restoredDeviceType->deleted_at)->toBeNull();
});

test('DeviceType name must be unique', function () {
    DeviceType::factory()->create([
        'name' => 'Storage',
    ]);

    // Attempt to create another device type with the same name
    expect(fn () => DeviceType::create([
        'name' => 'Storage',
        'default_u_size' => 4,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
