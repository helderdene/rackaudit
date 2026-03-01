<?php

/**
 * Tests for Elevation Page Integration
 * These tests verify that the Elevation.vue page correctly integrates all components:
 * front/rear views, unplaced devices sidebar, utilization stats, and click-to-navigate.
 */

use App\Enums\DeviceWidthType;
use App\Enums\DeviceRackFace;
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

    // Create hierarchy: Datacenter > Room > Row > Rack
    $this->datacenter = Datacenter::factory()->create();
    $this->room = Room::factory()->create(['datacenter_id' => $this->datacenter->id]);
    $this->row = Row::factory()->create(['room_id' => $this->room->id]);
    $this->rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Integration Test Rack',
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
    ]);

    // Create device types
    $this->serverType = DeviceType::factory()->create(['name' => 'Server', 'default_u_size' => 2]);
    $this->switchType = DeviceType::factory()->create(['name' => 'Switch', 'default_u_size' => 1]);

    // Create placed devices (front face)
    Device::factory()->create([
        'rack_id' => $this->rack->id,
        'device_type_id' => $this->serverType->id,
        'name' => 'Server 1',
        'start_u' => 1,
        'u_height' => 2,
        'rack_face' => DeviceRackFace::Front,
        'width_type' => DeviceWidthType::Full,
    ]);
    Device::factory()->create([
        'rack_id' => $this->rack->id,
        'device_type_id' => $this->switchType->id,
        'name' => 'Switch 1',
        'start_u' => 5,
        'u_height' => 1,
        'rack_face' => DeviceRackFace::Front,
        'width_type' => DeviceWidthType::Full,
    ]);

    // Create placed devices (rear face)
    Device::factory()->create([
        'rack_id' => $this->rack->id,
        'device_type_id' => $this->serverType->id,
        'name' => 'Rear Server',
        'start_u' => 1,
        'u_height' => 2,
        'rack_face' => DeviceRackFace::Rear,
        'width_type' => DeviceWidthType::Full,
    ]);

    // Create unplaced devices (no rack assignment)
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
});

/**
 * Test 1: Elevation page provides data for front and rear side-by-side views
 */
test('elevation page provides data for front and rear views to render side-by-side', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $this->rack->id,
        ]));

    $response->assertOk();

    $page = $response->viewData('page');
    $placedDevices = $page['props']['devices']['placed'];
    $rackUHeight = $page['props']['rack']['u_height'];

    // Verify rack u_height is available for generating slots
    expect($rackUHeight)->toBe(42);

    // Verify devices exist on both faces for side-by-side rendering
    $frontDevices = array_filter($placedDevices, fn ($d) => $d['face'] === 'front');
    $rearDevices = array_filter($placedDevices, fn ($d) => $d['face'] === 'rear');

    expect(count($frontDevices))->toBeGreaterThan(0);
    expect(count($rearDevices))->toBeGreaterThan(0);
});

/**
 * Test 2: Elevation page provides unplaced devices for sidebar display
 */
test('elevation page provides unplaced devices for sidebar display', function () {
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
            ->has('devices.unplaced')
        );

    $page = $response->viewData('page');
    $unplacedDevices = $page['props']['devices']['unplaced'];

    // Verify unplaced devices exist and have required sidebar display properties
    expect($unplacedDevices)->toBeArray();
    expect(count($unplacedDevices))->toBeGreaterThan(0);

    foreach ($unplacedDevices as $device) {
        // These are needed for UnplacedDevicesSidebar display
        expect($device)->toHaveKey('id');
        expect($device)->toHaveKey('name');
        expect($device)->toHaveKey('type');
        expect($device)->toHaveKey('u_size');
        expect($device)->toHaveKey('width');
    }
});

/**
 * Test 3: Elevation page data supports utilization calculation
 */
test('elevation page provides sufficient data for utilization stats calculation', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $this->rack->id,
        ]));

    $response->assertOk();

    $page = $response->viewData('page');
    $rack = $page['props']['rack'];
    $placedDevices = $page['props']['devices']['placed'];

    // Total U is available from rack
    expect($rack)->toHaveKey('u_height');
    expect($rack['u_height'])->toBeInt()->toBeGreaterThan(0);

    // Calculate expected utilization from placed devices
    $usedUs = [];
    foreach ($placedDevices as $device) {
        if (isset($device['start_u']) && isset($device['u_size'])) {
            for ($u = $device['start_u']; $u < $device['start_u'] + $device['u_size']; $u++) {
                $usedUs[$device['face']][$u] = true;
            }
        }
    }

    // Verify we have enough data to calculate front/rear usage
    expect(array_key_exists('front', $usedUs))->toBeTrue();
    expect(array_key_exists('rear', $usedUs))->toBeTrue();
});

/**
 * Test 4: Placed devices have sufficient data for click-to-navigate functionality
 */
test('placed devices have id for click-to-navigate functionality', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $this->rack->id,
        ]));

    $response->assertOk();

    $page = $response->viewData('page');
    $placedDevices = $page['props']['devices']['placed'];

    // Each placed device should have an id for navigation
    foreach ($placedDevices as $device) {
        expect($device)->toHaveKey('id');
        expect($device['id'])->toBeString()->not->toBeEmpty();
    }
});
