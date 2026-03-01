<?php

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
});

/**
 * Test 1: Device types index page renders list of device types
 */
test('device types index page renders list', function () {
    // Create device types
    $server = DeviceType::factory()->create([
        'name' => 'Server',
        'description' => 'Rack-mounted server',
        'default_u_size' => 2,
    ]);
    $switch = DeviceType::factory()->create([
        'name' => 'Switch',
        'description' => 'Network switch',
        'default_u_size' => 1,
    ]);
    $router = DeviceType::factory()->create([
        'name' => 'Router',
        'description' => 'Network router',
        'default_u_size' => 1,
    ]);

    $response = $this->actingAs($this->viewer)->get('/device-types');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('DeviceTypes/Index')
            ->has('deviceTypes', 3)
            ->where('deviceTypes.0.name', fn ($name) => in_array($name, ['Server', 'Switch', 'Router']))
            ->has('deviceTypes.0.description')
            ->has('deviceTypes.0.default_u_size')
        );
});

/**
 * Test 2: Create form submits with valid data
 */
test('create form submits with valid data', function () {
    $response = $this->actingAs($this->admin)
        ->post('/device-types', [
            'name' => 'Storage Array',
            'description' => 'Enterprise storage system',
            'default_u_size' => 4,
        ]);

    $response->assertRedirect('/device-types');

    $this->assertDatabaseHas('device_types', [
        'name' => 'Storage Array',
        'description' => 'Enterprise storage system',
        'default_u_size' => 4,
    ]);
});

/**
 * Test 3: Delete removes device type from list
 */
test('delete removes device type from list', function () {
    $deviceType = DeviceType::factory()->create([
        'name' => 'Obsolete Type',
        'description' => 'This will be deleted',
        'default_u_size' => 1,
    ]);

    // Verify it exists initially
    $response = $this->actingAs($this->viewer)->get('/device-types');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('deviceTypes', 1)
            ->where('deviceTypes.0.name', 'Obsolete Type')
        );

    // Delete the device type
    $deleteResponse = $this->actingAs($this->admin)
        ->delete("/device-types/{$deviceType->id}");

    $deleteResponse->assertRedirect('/device-types');

    // Verify it's no longer in the list
    $response = $this->actingAs($this->viewer)->get('/device-types');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('deviceTypes', 0)
        );

    // Verify soft delete was used
    expect(DeviceType::withTrashed()->find($deviceType->id))->not->toBeNull();
    expect(DeviceType::withTrashed()->find($deviceType->id)->deleted_at)->not->toBeNull();
});
