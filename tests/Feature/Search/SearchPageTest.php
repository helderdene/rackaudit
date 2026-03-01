<?php

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

test('search results page renders with search results', function () {
    // Create admin user
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create datacenter hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Test Datacenter']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Server Room']);
    $row = Row::factory()->create(['room_id' => $room->id, 'name' => 'Row A']);
    $rack = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Test Rack']);
    Device::factory()->create([
        'name' => 'Test Server',
        'rack_id' => $rack->id,
    ]);

    $response = $this->actingAs($admin)
        ->get('/search?q=Test');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Search/Index')
        ->has('results')
        ->has('results.datacenters')
        ->has('results.racks')
        ->has('results.devices')
        ->has('results.ports')
        ->has('results.connections')
        ->where('query', 'Test')
        ->has('filters')
    );
});

test('search results page passes filter options to frontend', function () {
    // Create admin user
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create datacenter hierarchy for filter options
    $datacenter = Datacenter::factory()->create(['name' => 'NYC Datacenter']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Main Room']);
    $row = Row::factory()->create(['room_id' => $room->id, 'name' => 'Row 1']);
    Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack 1']);

    $response = $this->actingAs($admin)
        ->get('/search?q=test');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Search/Index')
        // Verify datacenter options are passed
        ->has('filterOptions.datacenters', 1)
        ->where('filterOptions.datacenters.0.name', 'NYC Datacenter')
        // Verify enum values for entity-specific filters are passed
        ->has('filterOptions.lifecycleStatuses')
        ->has('filterOptions.portTypes')
        ->has('filterOptions.portStatuses')
        ->has('filterOptions.rackStatuses')
    );
});

test('search results page persists query parameters in filters', function () {
    // Create admin user
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create datacenter hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    Rack::factory()->create(['row_id' => $row->id]);

    $response = $this->actingAs($admin)
        ->get('/search?q=server&datacenter_id='.$datacenter->id.'&lifecycle_status=deployed');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Search/Index')
        ->where('query', 'server')
        ->where('filters.datacenter_id', $datacenter->id)
        ->where('filters.lifecycle_status', 'deployed')
    );
});

test('search results page loads room options when datacenter is selected', function () {
    // Create admin user
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create datacenter with rooms
    $datacenter = Datacenter::factory()->create(['name' => 'Primary DC']);
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Room Alpha']);
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Room Beta']);

    // Create another datacenter to ensure filtering works
    $otherDc = Datacenter::factory()->create(['name' => 'Secondary DC']);
    Room::factory()->create(['datacenter_id' => $otherDc->id, 'name' => 'Other Room']);

    $response = $this->actingAs($admin)
        ->get('/search?q=test&datacenter_id='.$datacenter->id);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Search/Index')
        // Should only show rooms from the selected datacenter
        ->has('filterOptions.rooms', 2)
        ->where('filterOptions.rooms.0.name', 'Room Alpha')
        ->where('filterOptions.rooms.1.name', 'Room Beta')
    );
});
