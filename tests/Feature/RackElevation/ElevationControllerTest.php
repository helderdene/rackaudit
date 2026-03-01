<?php

/**
 * Tests for RackController elevation method - placeholder devices data
 * These tests verify the elevation endpoint returns the correct data structure
 * including placeholder devices for the rack elevation view feature.
 */

use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Disable Inertia page existence check since Vue components are created in later task groups
    config(['inertia.testing.ensure_pages_exist' => false]);

    // Create hierarchy: Datacenter > Room > Row > Rack
    $this->datacenter = Datacenter::factory()->create();
    $this->room = Room::factory()->create(['datacenter_id' => $this->datacenter->id]);
    $this->row = Row::factory()->create(['room_id' => $this->room->id]);
    $this->rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Test Rack',
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
    ]);

    // Create device types
    $this->serverType = DeviceType::factory()->create(['name' => 'Server', 'default_u_size' => 2]);

    // Create placed devices
    Device::factory()->create([
        'rack_id' => $this->rack->id,
        'device_type_id' => $this->serverType->id,
        'name' => 'Server 1',
        'start_u' => 1,
        'u_height' => 2,
        'rack_face' => DeviceRackFace::Front,
        'width_type' => DeviceWidthType::Full,
    ]);

    // Create unplaced devices
    Device::factory()->create([
        'rack_id' => null,
        'device_type_id' => $this->serverType->id,
        'name' => 'Unplaced Server',
        'start_u' => null,
        'u_height' => 2,
    ]);

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    // Create viewer user (non-admin) with datacenter assignment
    $this->viewer = User::factory()->create();
    $this->viewer->assignRole('Viewer');
    $this->viewer->datacenters()->attach($this->datacenter->id);
});

/**
 * Test 1: Elevation endpoint returns placeholder devices data structure
 */
test('elevation returns placeholder devices with placed and unplaced arrays', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $this->rack->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Elevation')
            ->has('devices')
            ->has('devices.placed')
            ->has('devices.unplaced')
        );
});

/**
 * Test 2: Placed devices contain required device properties
 */
test('placed devices contain all required properties with position data', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $this->rack->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Elevation')
            ->has('devices.placed', fn (Assert $placed) => $placed
                ->each(fn (Assert $device) => $device
                    ->has('id')
                    ->has('name')
                    ->has('type')
                    ->has('u_size')
                    ->has('width')
                    ->has('start_u')
                    ->has('face')
                )
            )
        );
});

/**
 * Test 3: Unplaced devices contain required properties without position data
 */
test('unplaced devices contain device properties without position', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $this->rack->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Elevation')
            ->has('devices.unplaced', fn (Assert $unplaced) => $unplaced
                ->each(fn (Assert $device) => $device
                    ->has('id')
                    ->has('name')
                    ->has('type')
                    ->has('u_size')
                    ->has('width')
                    // Unplaced devices should NOT have start_u and face properties
                    ->missing('start_u')
                    ->missing('face')
                )
            )
        );
});

/**
 * Test 4: Authorization is enforced on elevation endpoint
 */
test('elevation endpoint requires authorization', function () {
    // Test unauthenticated access redirects to login
    $response = $this->get(route('datacenters.rooms.rows.racks.elevation', [
        'datacenter' => $this->datacenter->id,
        'room' => $this->room->id,
        'row' => $this->row->id,
        'rack' => $this->rack->id,
    ]));

    $response->assertRedirect(route('login'));

    // Test viewer with datacenter assignment can access
    $viewerResponse = $this->actingAs($this->viewer)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $this->rack->id,
        ]));

    $viewerResponse->assertOk();

    // Test viewer without datacenter assignment cannot access
    $unassignedViewer = User::factory()->create();
    $unassignedViewer->assignRole('Viewer');
    // Note: not attaching any datacenter to this viewer

    $unassignedResponse = $this->actingAs($unassignedViewer)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $this->rack->id,
        ]));

    $unassignedResponse->assertForbidden();
});
