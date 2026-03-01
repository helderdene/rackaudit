<?php

/**
 * Tests for the Search/Index page component functionality.
 *
 * These tests verify the search results page UI renders correctly,
 * filters work properly, and pagination functions as expected.
 */

use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
});

test('search index page renders with search results grouped by entity type', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create test data hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'SearchTest Datacenter']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'SearchTest Room']);
    $row = Row::factory()->create(['room_id' => $room->id, 'name' => 'SearchTest Row']);
    $rack = Rack::factory()->create(['row_id' => $row->id, 'name' => 'SearchTest Rack']);
    Device::factory()->create([
        'name' => 'SearchTest Device',
        'rack_id' => $rack->id,
    ]);

    $response = $this->actingAs($admin)
        ->get('/search?q=SearchTest');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Search/Index')
        ->has('results.datacenters.items')
        ->has('results.racks.items')
        ->has('results.devices.items')
        ->has('results.ports')
        ->has('results.connections')
        ->where('query', 'SearchTest')
    );
});

test('search index page filters by entity type correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create test data
    $datacenter = Datacenter::factory()->create(['name' => 'FilterTest DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id, 'name' => 'FilterTest Rack']);
    Device::factory()->create([
        'name' => 'FilterTest Device',
        'rack_id' => $rack->id,
    ]);

    // Filter to only show devices
    $response = $this->actingAs($admin)
        ->get('/search?q=FilterTest&type=devices');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Search/Index')
        ->where('filters.type', 'devices')
        // When filtering by type, other entity type results should be empty
        ->has('results.devices.items')
        ->where('results.datacenters.items', [])
        ->where('results.racks.items', [])
    );
});

test('search index page cascades hierarchical filters correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create datacenter with nested hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Cascade DC']);
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Room A']);
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Room B']);
    $row = Row::factory()->create(['room_id' => $room1->id, 'name' => 'Row 1']);
    Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack 1']);

    // Test that selecting a datacenter loads its rooms
    $response = $this->actingAs($admin)
        ->get('/search?q=test&datacenter_id='.$datacenter->id);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Search/Index')
        ->where('filters.datacenter_id', $datacenter->id)
        // Should have room options loaded
        ->has('filterOptions.rooms', 2)
        // Room filter should still be null since not selected
        ->where('filters.room_id', null)
    );

    // Test that selecting a room loads its rows
    $response = $this->actingAs($admin)
        ->get('/search?q=test&datacenter_id='.$datacenter->id.'&room_id='.$room1->id);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Search/Index')
        ->where('filters.datacenter_id', $datacenter->id)
        ->where('filters.room_id', $room1->id)
        // Should have row options loaded for the selected room
        ->has('filterOptions.rows', 1)
    );
});

test('search index page displays empty state when no results found', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Search for something that doesn't exist
    $response = $this->actingAs($admin)
        ->get('/search?q=NonExistentSearchTerm12345');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Search/Index')
        ->where('query', 'NonExistentSearchTerm12345')
        ->where('results.datacenters.total', 0)
        ->where('results.racks.total', 0)
        ->where('results.devices.total', 0)
        ->where('results.ports.total', 0)
        ->where('results.connections.total', 0)
    );
});

test('search index page passes all filter options to frontend', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create a datacenter so we have filter options
    $datacenter = Datacenter::factory()->create(['name' => 'Options Test DC']);

    $response = $this->actingAs($admin)
        ->get('/search?q=test');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Search/Index')
        // Verify all filter options are passed
        ->has('filterOptions.datacenters')
        ->has('filterOptions.entityTypes')
        ->has('filterOptions.lifecycleStatuses')
        ->has('filterOptions.portTypes')
        ->has('filterOptions.portStatuses')
        ->has('filterOptions.rackStatuses')
        // Verify entity type options are correct
        ->where('filterOptions.entityTypes.0.value', 'datacenters')
        ->where('filterOptions.entityTypes.1.value', 'racks')
        ->where('filterOptions.entityTypes.2.value', 'devices')
        ->where('filterOptions.entityTypes.3.value', 'ports')
        ->where('filterOptions.entityTypes.4.value', 'connections')
    );
});
