<?php

use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use App\Services\SearchService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->searchService = app(SearchService::class);
});

test('basic text search finds devices by name', function () {
    // Create datacenter hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'NYC Datacenter']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create searchable device
    Device::factory()->create([
        'name' => 'Web Server Alpha',
        'rack_id' => $rack->id,
    ]);

    // Create another device that shouldn't match
    Device::factory()->create([
        'name' => 'Database Server Beta',
        'rack_id' => $rack->id,
    ]);

    // Create admin user for full access
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $results = $this->searchService->search('Alpha', $admin);

    expect($results)->toHaveKey('devices');
    expect($results['devices']['items'])->toHaveCount(1);
    expect($results['devices']['items'][0]['name'])->toContain('Alpha');
});

test('RBAC filtering limits results for non-admin users to assigned datacenters only', function () {
    // Create two datacenters
    $datacenter1 = Datacenter::factory()->create(['name' => 'Allowed DC']);
    $datacenter2 = Datacenter::factory()->create(['name' => 'Restricted DC']);

    // Create hierarchy for both datacenters
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter1->id]);
    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $rack1 = Rack::factory()->create(['row_id' => $row1->id]);

    $room2 = Room::factory()->create(['datacenter_id' => $datacenter2->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);
    $rack2 = Rack::factory()->create(['row_id' => $row2->id]);

    // Create devices in both datacenters with matching names
    Device::factory()->create([
        'name' => 'Test Server',
        'rack_id' => $rack1->id,
    ]);
    Device::factory()->create([
        'name' => 'Test Server',
        'rack_id' => $rack2->id,
    ]);

    // Create a non-admin user with access to only datacenter1
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $viewer->datacenters()->attach($datacenter1->id);

    $results = $this->searchService->search('Test Server', $viewer);

    expect($results['devices']['items'])->toHaveCount(1);
    expect($results['devices']['items'][0]['datacenter_name'])->toBe('Allowed DC');
});

test('multi-entity unified search returns grouped results', function () {
    // Create datacenter hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Main Datacenter']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Main Room']);
    $row = Row::factory()->create(['room_id' => $room->id, 'name' => 'Main Row']);
    $rack = Rack::factory()->create(['name' => 'Main Rack', 'row_id' => $row->id]);
    $device = Device::factory()->create(['name' => 'Main Server', 'rack_id' => $rack->id]);
    $port = Port::factory()->create(['device_id' => $device->id, 'label' => 'main-eth0']);

    // Create admin user
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $results = $this->searchService->search('Main', $admin);

    // Should return results grouped by entity type
    expect($results)->toHaveKeys(['datacenters', 'racks', 'devices', 'ports', 'connections']);
    expect($results['datacenters']['items'])->not->toBeEmpty();
    expect($results['racks']['items'])->not->toBeEmpty();
    expect($results['devices']['items'])->not->toBeEmpty();
    expect($results['ports']['items'])->not->toBeEmpty();
});

test('empty search query returns empty results structure', function () {
    // Create admin user
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $results = $this->searchService->search('', $admin);

    expect($results)->toHaveKeys(['datacenters', 'racks', 'devices', 'ports', 'connections']);
    expect($results['datacenters']['items'])->toBeEmpty();
    expect($results['racks']['items'])->toBeEmpty();
    expect($results['devices']['items'])->toBeEmpty();
    expect($results['ports']['items'])->toBeEmpty();
    expect($results['connections']['items'])->toBeEmpty();
});

test('search result formatting includes breadcrumb location context', function () {
    // Create full datacenter hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'NYC-DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Server Room']);
    $row = Row::factory()->create(['room_id' => $room->id, 'name' => 'Row-A']);
    $rack = Rack::factory()->create(['name' => 'Rack-01', 'row_id' => $row->id]);
    $device = Device::factory()->create([
        'name' => 'Web Server 01',
        'rack_id' => $rack->id,
    ]);

    // Create admin user
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $results = $this->searchService->search('Web Server 01', $admin);

    expect($results['devices']['items'])->toHaveCount(1);
    $deviceResult = $results['devices']['items'][0];

    // Check breadcrumb context exists
    expect($deviceResult)->toHaveKey('breadcrumb');
    expect($deviceResult['breadcrumb'])->toContain('NYC-DC');
    expect($deviceResult['breadcrumb'])->toContain('Server Room');
    expect($deviceResult['breadcrumb'])->toContain('Row-A');
    expect($deviceResult['breadcrumb'])->toContain('Rack-01');
});

test('search includes matched field information for highlighting', function () {
    // Create datacenter hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'NYC Datacenter']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create device with specific serial number
    Device::factory()->create([
        'name' => 'Database Server',
        'serial_number' => 'SN-UNIQUE-12345',
        'rack_id' => $rack->id,
    ]);

    // Create admin user
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $results = $this->searchService->search('UNIQUE', $admin);

    expect($results['devices']['items'])->toHaveCount(1);
    $deviceResult = $results['devices']['items'][0];

    expect($deviceResult)->toHaveKey('matched_fields');
    expect($deviceResult['matched_fields'])->toContain('serial_number');
});
