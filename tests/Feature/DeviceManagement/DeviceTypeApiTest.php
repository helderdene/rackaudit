<?php

use App\Models\DeviceType;
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
});

/**
 * Test 1: Index returns all device types (excluding soft-deleted)
 */
test('index returns all device types excluding soft-deleted', function () {
    // Create active device types
    $server = DeviceType::factory()->create(['name' => 'Server', 'default_u_size' => 2]);
    $switch = DeviceType::factory()->create(['name' => 'Switch', 'default_u_size' => 1]);
    $router = DeviceType::factory()->create(['name' => 'Router', 'default_u_size' => 1]);

    // Create and soft-delete a device type
    $deleted = DeviceType::factory()->create(['name' => 'Deleted Type']);
    $deleted->delete();

    $response = $this->actingAs($this->viewer)
        ->getJson('/device-types');

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonFragment(['name' => 'Server'])
        ->assertJsonFragment(['name' => 'Switch'])
        ->assertJsonFragment(['name' => 'Router'])
        ->assertJsonMissing(['name' => 'Deleted Type']);
});

/**
 * Test 2: Store creates device type with valid data
 */
test('store creates device type with valid data', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/device-types', [
            'name' => 'Blade Chassis',
            'description' => 'Enterprise blade server chassis',
            'default_u_size' => 10,
        ]);

    $response->assertCreated()
        ->assertJsonFragment([
            'name' => 'Blade Chassis',
            'description' => 'Enterprise blade server chassis',
            'default_u_size' => 10,
        ]);

    $this->assertDatabaseHas('device_types', [
        'name' => 'Blade Chassis',
        'description' => 'Enterprise blade server chassis',
        'default_u_size' => 10,
    ]);
});

/**
 * Test 3: Update modifies device type
 */
test('update modifies device type', function () {
    $deviceType = DeviceType::factory()->create([
        'name' => 'Original Name',
        'description' => 'Original description',
        'default_u_size' => 2,
    ]);

    $response = $this->actingAs($this->admin)
        ->putJson("/device-types/{$deviceType->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'default_u_size' => 4,
        ]);

    $response->assertOk()
        ->assertJsonFragment([
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'default_u_size' => 4,
        ]);

    $this->assertDatabaseHas('device_types', [
        'id' => $deviceType->id,
        'name' => 'Updated Name',
        'description' => 'Updated description',
        'default_u_size' => 4,
    ]);
});

/**
 * Test 4: Destroy soft-deletes device type
 */
test('destroy soft-deletes device type', function () {
    $deviceType = DeviceType::factory()->create(['name' => 'To Be Deleted']);

    $response = $this->actingAs($this->admin)
        ->deleteJson("/device-types/{$deviceType->id}");

    $response->assertOk()
        ->assertJsonFragment(['message' => 'Device type deleted successfully.']);

    // Should not appear in normal queries
    $this->assertDatabaseMissing('device_types', [
        'id' => $deviceType->id,
        'deleted_at' => null,
    ]);

    // Should still exist with deleted_at set (soft deleted)
    expect(DeviceType::withTrashed()->find($deviceType->id))->not->toBeNull();
    expect(DeviceType::withTrashed()->find($deviceType->id)->deleted_at)->not->toBeNull();
});
