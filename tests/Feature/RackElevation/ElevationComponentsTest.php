<?php

/**
 * Tests for Elevation Vue Components
 * These tests verify that the Vue components render correctly via the Inertia endpoint
 * and that the data structure supports multi-U and half-width devices.
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
        'name' => 'Component Test Rack',
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
    ]);

    // Create device types
    $this->serverType = DeviceType::factory()->create(['name' => 'Server', 'default_u_size' => 2]);
    $this->switchType = DeviceType::factory()->create(['name' => 'Switch', 'default_u_size' => 1]);

    // Create placed devices (front face) - multi-U and full width
    Device::factory()->create([
        'rack_id' => $this->rack->id,
        'device_type_id' => $this->serverType->id,
        'name' => 'Server 1',
        'start_u' => 1,
        'u_height' => 2,
        'rack_face' => DeviceRackFace::Front,
        'width_type' => DeviceWidthType::Full,
    ]);
    // Half-width devices
    Device::factory()->create([
        'rack_id' => $this->rack->id,
        'device_type_id' => $this->switchType->id,
        'name' => 'Switch Left',
        'start_u' => 10,
        'u_height' => 1,
        'rack_face' => DeviceRackFace::Front,
        'width_type' => DeviceWidthType::HalfLeft,
    ]);
    Device::factory()->create([
        'rack_id' => $this->rack->id,
        'device_type_id' => $this->switchType->id,
        'name' => 'Switch Right',
        'start_u' => 10,
        'u_height' => 1,
        'rack_face' => DeviceRackFace::Front,
        'width_type' => DeviceWidthType::HalfRight,
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
 * Test 1: Elevation page renders with rack data including u_height for slot generation
 */
test('elevation page receives rack u_height for generating correct number of slots', function () {
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
            ->has('rack')
            ->where('rack.u_height', 42)
            ->where('rack.name', 'Component Test Rack')
        );
});

/**
 * Test 2: Placed devices have all required properties for component rendering
 */
test('placed devices have required properties for DeviceBlock and RackElevationView', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $this->rack->id,
        ]));

    $response->assertOk();

    // Get raw data to verify structure
    $page = $response->viewData('page');
    $placedDevices = $page['props']['devices']['placed'];

    expect($placedDevices)->toBeArray();
    expect(count($placedDevices))->toBeGreaterThan(0);

    foreach ($placedDevices as $device) {
        // Required fields for DeviceBlock component
        expect($device)->toHaveKey('id');
        expect($device)->toHaveKey('name');
        expect($device)->toHaveKey('type');
        expect($device)->toHaveKey('u_size');
        expect($device)->toHaveKey('width');

        // Required fields for RackElevationView positioning
        expect($device)->toHaveKey('start_u');
        expect($device)->toHaveKey('face');

        // Type validation
        expect($device['name'])->toBeString()->not->toBeEmpty();
        expect($device['type'])->toBeString()->not->toBeEmpty();
        expect($device['u_size'])->toBeNumeric()->toBeGreaterThanOrEqual(1);
        expect($device['width'])->toBeIn(['full', 'half-left', 'half-right']);
        expect($device['face'])->toBeIn(['front', 'rear']);
        expect($device['start_u'])->toBeNumeric()->toBeGreaterThanOrEqual(1);
    }
});

/**
 * Test 3: Sample data includes multi-U devices for RackElevationView spanning
 */
test('sample data includes multi-U devices for elevation spanning', function () {
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

    $uSizes = array_map(fn ($device) => $device['u_size'], $placedDevices);
    $uniqueSizes = array_unique($uSizes);

    // Should have at least 2 different U-sizes (1U and multi-U)
    expect(count($uniqueSizes))->toBeGreaterThanOrEqual(2);

    // Should have at least one multi-U device (u_size > 1)
    $hasMultiU = array_filter($uSizes, fn ($size) => $size > 1);
    expect(count($hasMultiU))->toBeGreaterThan(0);
});

/**
 * Test 4: Sample data includes half-width devices for USlot half-width rendering
 */
test('sample data includes half-width devices for slot half-width rendering', function () {
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

    $widths = array_map(fn ($device) => $device['width'], $placedDevices);

    // Should have at least one half-width device
    $hasHalfLeft = in_array('half-left', $widths);
    $hasHalfRight = in_array('half-right', $widths);

    expect($hasHalfLeft || $hasHalfRight)->toBeTrue();
});

/**
 * Test 5: Placed devices span both front and rear faces
 */
test('sample data includes devices on both front and rear faces', function () {
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

    $faces = array_map(fn ($device) => $device['face'], $placedDevices);

    // Should have devices on both front and rear
    expect(in_array('front', $faces))->toBeTrue();
    expect(in_array('rear', $faces))->toBeTrue();
});

/**
 * Test 6: Unplaced devices have required properties without position data
 */
test('unplaced devices have device properties without position data', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $this->rack->id,
        ]));

    $response->assertOk();

    $page = $response->viewData('page');
    $unplacedDevices = $page['props']['devices']['unplaced'];

    expect($unplacedDevices)->toBeArray();
    expect(count($unplacedDevices))->toBeGreaterThan(0);

    foreach ($unplacedDevices as $device) {
        // Required fields for DeviceBlock component
        expect($device)->toHaveKey('id');
        expect($device)->toHaveKey('name');
        expect($device)->toHaveKey('type');
        expect($device)->toHaveKey('u_size');
        expect($device)->toHaveKey('width');

        // Unplaced devices should NOT have position data
        expect($device)->not->toHaveKey('start_u');
        expect($device)->not->toHaveKey('face');
    }
});
