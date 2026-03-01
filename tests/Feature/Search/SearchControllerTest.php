<?php

use App\Enums\DeviceLifecycleStatus;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('quick search endpoint returns limited results per entity type', function () {
    // Create admin user
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create datacenter hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Test Datacenter']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Test Rack']);

    // Create multiple devices with matching names
    for ($i = 1; $i <= 10; $i++) {
        Device::factory()->create([
            'name' => "Test Device {$i}",
            'rack_id' => $rack->id,
        ]);
    }

    $response = $this->actingAs($admin)
        ->getJson('/api/search/quick?q=Test');

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'datacenters' => ['items', 'total'],
            'racks' => ['items', 'total'],
            'devices' => ['items', 'total'],
            'ports' => ['items', 'total'],
            'connections' => ['items', 'total'],
        ],
    ]);

    // Quick search should return max 5 results per entity type
    $devices = $response->json('data.devices.items');
    expect(count($devices))->toBeLessThanOrEqual(5);
    expect($response->json('data.devices.total'))->toBe(10);
});

test('full search endpoint returns paginated results', function () {
    // Create admin user
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create datacenter hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Production DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Production Rack']);

    // Create many devices
    for ($i = 1; $i <= 25; $i++) {
        Device::factory()->create([
            'name' => "Production Server {$i}",
            'rack_id' => $rack->id,
        ]);
    }

    $response = $this->actingAs($admin)
        ->getJson('/api/search?q=Production');

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'datacenters' => ['items', 'total'],
            'racks' => ['items', 'total'],
            'devices' => ['items', 'total'],
            'ports' => ['items', 'total'],
            'connections' => ['items', 'total'],
        ],
    ]);

    // Full search should return more results (up to 20 by default)
    $devices = $response->json('data.devices.items');
    expect(count($devices))->toBeLessThanOrEqual(20);
    expect($response->json('data.devices.total'))->toBe(25);
});

test('search with hierarchical filters returns filtered results', function () {
    // Create admin user
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create two datacenters with devices
    $datacenter1 = Datacenter::factory()->create(['name' => 'NYC DC']);
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter1->id]);
    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $rack1 = Rack::factory()->create(['row_id' => $row1->id]);

    $datacenter2 = Datacenter::factory()->create(['name' => 'LA DC']);
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter2->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);
    $rack2 = Rack::factory()->create(['row_id' => $row2->id]);

    // Create devices in both datacenters
    Device::factory()->create([
        'name' => 'Web Server',
        'rack_id' => $rack1->id,
    ]);
    Device::factory()->create([
        'name' => 'Web Server',
        'rack_id' => $rack2->id,
    ]);

    // Search with datacenter filter
    $response = $this->actingAs($admin)
        ->getJson('/api/search?q=Web&datacenter_id='.$datacenter1->id);

    $response->assertOk();

    $devices = $response->json('data.devices.items');
    expect(count($devices))->toBe(1);
    expect($devices[0]['datacenter_id'])->toBe($datacenter1->id);
});

test('search with entity-specific attribute filters works correctly', function () {
    // Create admin user
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create datacenter hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create devices with different lifecycle statuses
    $deployedDevice = Device::factory()->create([
        'name' => 'Server Alpha',
        'rack_id' => $rack->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);
    Device::factory()->create([
        'name' => 'Server Beta',
        'rack_id' => $rack->id,
        'lifecycle_status' => DeviceLifecycleStatus::Maintenance,
    ]);

    // Search with lifecycle_status filter
    $response = $this->actingAs($admin)
        ->getJson('/api/search?q=Server&lifecycle_status=deployed');

    $response->assertOk();

    $devices = $response->json('data.devices.items');
    expect(count($devices))->toBe(1);
    expect($devices[0]['id'])->toBe($deployedDevice->id);
});

test('search endpoints require authentication', function () {
    // Test quick search without authentication
    $response = $this->getJson('/api/search/quick?q=test');
    $response->assertUnauthorized();

    // Test full search without authentication
    $response = $this->getJson('/api/search?q=test');
    $response->assertUnauthorized();
});

test('search validates request parameters correctly', function () {
    // Create admin user
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Test with invalid enum value for lifecycle_status
    $response = $this->actingAs($admin)
        ->getJson('/api/search?q=test&lifecycle_status=invalid_status');

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['lifecycle_status']);

    // Test with invalid datacenter_id
    $response = $this->actingAs($admin)
        ->getJson('/api/search?q=test&datacenter_id=not_a_number');

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['datacenter_id']);
});
