<?php

/**
 * Elevation Responsive Layout Tests
 *
 * Tests for the Racks/Elevation.vue page responsive layout behavior:
 * - Front/rear views stack vertically on tablet viewport (md breakpoint)
 * - Side-by-side layout is maintained on desktop (lg+ breakpoint)
 * - Collapsible unplaced devices sidebar works on tablet
 * - Max-height constraints prevent excessive scrolling on tablet
 *
 * Note: These tests verify the backend/API aspects and document expected
 * frontend behavior. The Vue component uses Tailwind CSS responsive
 * classes which execute client-side.
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

    // Create hierarchy: Datacenter > Room > Row > Rack
    $this->datacenter = Datacenter::factory()->create();
    $this->room = Room::factory()->create(['datacenter_id' => $this->datacenter->id]);
    $this->row = Row::factory()->create(['room_id' => $this->room->id]);
    $this->rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Responsive Test Rack',
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
    ]);

    // Create device type
    $this->serverType = DeviceType::factory()->create(['name' => 'Server', 'default_u_size' => 2]);

    // Create placed devices (front and rear faces)
    Device::factory()->create([
        'rack_id' => $this->rack->id,
        'device_type_id' => $this->serverType->id,
        'name' => 'Front Server',
        'start_u' => 1,
        'u_height' => 2,
        'rack_face' => DeviceRackFace::Front,
        'width_type' => DeviceWidthType::Full,
    ]);

    Device::factory()->create([
        'rack_id' => $this->rack->id,
        'device_type_id' => $this->serverType->id,
        'name' => 'Rear Server',
        'start_u' => 1,
        'u_height' => 2,
        'rack_face' => DeviceRackFace::Rear,
        'width_type' => DeviceWidthType::Full,
    ]);

    // Create unplaced devices
    Device::factory()->create([
        'rack_id' => null,
        'device_type_id' => $this->serverType->id,
        'name' => 'Unplaced Device 1',
        'start_u' => null,
        'u_height' => 2,
    ]);

    Device::factory()->create([
        'rack_id' => null,
        'device_type_id' => $this->serverType->id,
        'name' => 'Unplaced Device 2',
        'start_u' => null,
        'u_height' => 1,
    ]);

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');
});

/**
 * Test 1: Elevation page provides both front and rear view data for stacking/side-by-side layout
 *
 * Documents expected behavior: Front and rear views stack vertically on tablet (md)
 * and display side-by-side on desktop (lg+) using Tailwind responsive classes.
 * The component uses `flex-col gap-4 lg:flex-row` pattern on the elevation views container.
 */
test('elevation page provides front and rear view data for responsive layout', function () {
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
            ->has('rack.u_height')
            ->has('devices.placed')
        );

    $page = $response->viewData('page');
    $placedDevices = $page['props']['devices']['placed'];

    // Verify devices exist on both faces for front/rear view rendering
    $frontDevices = array_filter($placedDevices, fn ($d) => $d['face'] === 'front');
    $rearDevices = array_filter($placedDevices, fn ($d) => $d['face'] === 'rear');

    expect(count($frontDevices))->toBeGreaterThan(0, 'Front face devices should exist');
    expect(count($rearDevices))->toBeGreaterThan(0, 'Rear face devices should exist');

    // Verify rack u_height is provided for view height calculations
    expect($page['props']['rack']['u_height'])->toBe(42);
});

/**
 * Test 2: Elevation page provides unplaced devices data for collapsible sidebar
 *
 * Documents expected behavior: Unplaced devices sidebar uses Collapsible component
 * with `lg:hidden` / `hidden lg:flex` toggle to show collapsible on mobile/tablet
 * and always-visible sidebar on desktop.
 */
test('elevation page provides unplaced devices data for collapsible sidebar', function () {
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

    // Verify unplaced devices exist for sidebar display
    expect($unplacedDevices)->toBeArray();
    expect(count($unplacedDevices))->toBe(2, 'Should have 2 unplaced devices');

    // Verify each device has required properties for sidebar
    foreach ($unplacedDevices as $device) {
        expect($device)->toHaveKey('id');
        expect($device)->toHaveKey('name');
        expect($device)->toHaveKey('u_size');
    }
});

/**
 * Test 3: Elevation page provides rack height for max-height constraint calculations
 *
 * Documents expected behavior: CardContent uses responsive max-height to prevent
 * excessive scrolling. Pattern: `max-h-[calc(100vh-20rem)] lg:max-h-[calc(100vh-24rem)]`
 * allows more content on tablet (vertically stacked) and optimizes for desktop (side-by-side).
 */
test('elevation page provides rack height for max-height calculations', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $this->rack->id,
        ]));

    $response->assertOk();

    $page = $response->viewData('page');

    // Verify rack u_height is available for calculating appropriate max-height
    expect($page['props']['rack'])->toHaveKey('u_height');
    expect($page['props']['rack']['u_height'])->toBeInt();
    expect($page['props']['rack']['u_height'])->toBeGreaterThan(0);

    // Verify u_height_label is available for display
    expect($page['props']['rack'])->toHaveKey('u_height_label');
});

/**
 * Test 4: Elevation page supports tablet viewport responsive structure
 *
 * Documents expected behavior:
 * - Main content container: `flex flex-1 flex-col gap-4 lg:flex-row`
 * - Elevation views container: `flex flex-1 flex-col gap-4 lg:flex-row` (changed from md:flex-row)
 * - Collapsible sidebar: visible on tablet/mobile with `lg:hidden`
 * - Desktop sidebar: visible on desktop with `hidden lg:flex`
 */
test('elevation page renders correctly with required component structure', function () {
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
            // Verify all required props exist for component rendering
            ->has('datacenter.id')
            ->has('datacenter.name')
            ->has('room.id')
            ->has('room.name')
            ->has('row.id')
            ->has('row.name')
            ->has('rack.id')
            ->has('rack.name')
            ->has('rack.u_height')
            ->has('rack.status')
            ->has('devices.placed')
            ->has('devices.unplaced')
        );

    // Verify page can be rendered at different viewports
    // (actual viewport testing happens in browser)
    $page = $response->viewData('page');
    expect($page['component'])->toBe('Racks/Elevation');
});
