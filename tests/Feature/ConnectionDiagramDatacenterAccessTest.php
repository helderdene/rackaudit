<?php

/**
 * Tests for Connection Diagram page datacenter access filtering.
 *
 * Verifies that the filter options on the connection diagram page
 * respect user datacenter assignments:
 * - Administrators and IT Managers see all datacenters
 * - Other roles only see their assigned datacenters
 */

use App\Models\Datacenter;
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
    config(['inertia.testing.ensure_pages_exist' => false]);

    // Create two datacenters with full hierarchy
    $this->datacenter1 = Datacenter::factory()->create(['name' => 'Datacenter Alpha']);
    $this->room1 = Room::factory()->create(['datacenter_id' => $this->datacenter1->id, 'name' => 'Room A1']);
    $this->row1 = Row::factory()->create(['room_id' => $this->room1->id, 'name' => 'Row A1-1']);
    $this->rack1 = Rack::factory()->create(['row_id' => $this->row1->id, 'name' => 'Rack A1-1-1']);

    $this->datacenter2 = Datacenter::factory()->create(['name' => 'Datacenter Beta']);
    $this->room2 = Room::factory()->create(['datacenter_id' => $this->datacenter2->id, 'name' => 'Room B1']);
    $this->row2 = Row::factory()->create(['room_id' => $this->room2->id, 'name' => 'Row B1-1']);
    $this->rack2 = Rack::factory()->create(['row_id' => $this->row2->id, 'name' => 'Rack B1-1-1']);
});

test('administrator sees all datacenters in filter options', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $response = $this->actingAs($admin)->get('/connections/diagram/page');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Connections/Diagram')
        ->has('filterOptions.datacenters', 2)
        ->where('filterOptions.datacenters.0.label', 'Datacenter Alpha')
        ->where('filterOptions.datacenters.1.label', 'Datacenter Beta')
    );
});

test('IT manager sees all datacenters in filter options', function () {
    $itManager = User::factory()->create();
    $itManager->assignRole('IT Manager');

    $response = $this->actingAs($itManager)->get('/connections/diagram/page');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Connections/Diagram')
        ->has('filterOptions.datacenters', 2)
    );
});

test('operator only sees assigned datacenters in filter options', function () {
    $operator = User::factory()->create();
    $operator->assignRole('Operator');
    $operator->datacenters()->attach($this->datacenter1->id);

    $response = $this->actingAs($operator)->get('/connections/diagram/page');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Connections/Diagram')
        ->has('filterOptions.datacenters', 1)
        ->where('filterOptions.datacenters.0.label', 'Datacenter Alpha')
    );
});

test('operator only sees rooms within assigned datacenters', function () {
    $operator = User::factory()->create();
    $operator->assignRole('Operator');
    $operator->datacenters()->attach($this->datacenter1->id);

    $response = $this->actingAs($operator)->get('/connections/diagram/page');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Connections/Diagram')
        ->has('filterOptions.rooms', 1)
        ->where('filterOptions.rooms.0.label', 'Room A1')
    );
});

test('operator only sees rows within assigned datacenters', function () {
    $operator = User::factory()->create();
    $operator->assignRole('Operator');
    $operator->datacenters()->attach($this->datacenter1->id);

    $response = $this->actingAs($operator)->get('/connections/diagram/page');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Connections/Diagram')
        ->has('filterOptions.rows', 1)
        ->where('filterOptions.rows.0.label', 'Row A1-1')
    );
});

test('operator only sees racks within assigned datacenters', function () {
    $operator = User::factory()->create();
    $operator->assignRole('Operator');
    $operator->datacenters()->attach($this->datacenter1->id);

    $response = $this->actingAs($operator)->get('/connections/diagram/page');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Connections/Diagram')
        ->has('filterOptions.racks', 1)
        ->where('filterOptions.racks.0.label', 'Rack A1-1-1')
    );
});

test('operator with multiple datacenter assignments sees all assigned datacenters', function () {
    $operator = User::factory()->create();
    $operator->assignRole('Operator');
    $operator->datacenters()->attach([$this->datacenter1->id, $this->datacenter2->id]);

    $response = $this->actingAs($operator)->get('/connections/diagram/page');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Connections/Diagram')
        ->has('filterOptions.datacenters', 2)
        ->has('filterOptions.rooms', 2)
        ->has('filterOptions.rows', 2)
        ->has('filterOptions.racks', 2)
    );
});

test('operator with no datacenter assignments sees empty filter options', function () {
    $operator = User::factory()->create();
    $operator->assignRole('Operator');
    // No datacenter assignments

    $response = $this->actingAs($operator)->get('/connections/diagram/page');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Connections/Diagram')
        ->has('filterOptions.datacenters', 0)
        ->has('filterOptions.rooms', 0)
        ->has('filterOptions.rows', 0)
        ->has('filterOptions.racks', 0)
    );
});
