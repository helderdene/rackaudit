<?php

/**
 * Tests for Drag-and-Drop Functionality
 * These tests verify the drag-and-drop behavior, collision detection,
 * and device placement validation in the rack elevation system.
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

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Create hierarchy: Datacenter > Room > Row > Rack
    $this->datacenter = Datacenter::factory()->create();
    $this->room = Room::factory()->create(['datacenter_id' => $this->datacenter->id]);
    $this->row = Row::factory()->create(['room_id' => $this->room->id]);
    $this->rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Drag Drop Test Rack',
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
    ]);

    // Create device types
    $this->serverType = DeviceType::factory()->create(['name' => 'Server', 'default_u_size' => 2]);
    $this->switchType = DeviceType::factory()->create(['name' => 'Switch', 'default_u_size' => 1]);

    // Create placed devices (front face) with full and half widths
    Device::factory()->create([
        'rack_id' => $this->rack->id,
        'device_type_id' => $this->serverType->id,
        'name' => 'Server 1',
        'start_u' => 1,
        'u_height' => 2,
        'rack_face' => DeviceRackFace::Front,
        'width_type' => DeviceWidthType::Full,
    ]);
    // Half-width devices at same U position
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
 * Test 1: Elevation page provides both placed and unplaced devices for drag-and-drop
 */
test('elevation page provides unplaced devices for drag-and-drop source', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $this->rack->id,
        ]));

    $response->assertOk();

    $page = $response->viewData('page');
    $devices = $page['props']['devices'];

    // Must have both placed and unplaced arrays for drag-and-drop
    expect($devices)->toHaveKey('placed');
    expect($devices)->toHaveKey('unplaced');

    // Unplaced devices should be available for dragging to rack
    expect($devices['unplaced'])->toBeArray();
    expect(count($devices['unplaced']))->toBeGreaterThan(0);
});

/**
 * Test 2: Placed devices have valid start_u positions within rack height bounds
 */
test('placed devices have start_u positions within valid rack height bounds', function () {
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
    $rackHeight = $page['props']['rack']['u_height'];

    foreach ($placedDevices as $device) {
        $startU = $device['start_u'];
        $uSize = $device['u_size'];
        $endU = $startU + $uSize - 1;

        // start_u must be at least 1 (U positions start at 1)
        expect($startU)->toBeGreaterThanOrEqual(1);

        // Device must not exceed rack height
        expect($endU)->toBeLessThanOrEqual($rackHeight);
    }
});

/**
 * Test 3: Sample data has no overlapping devices (collision-free initial state)
 * This validates the collision detection logic indirectly by ensuring
 * the initial sample data is valid.
 */
test('sample placed devices do not overlap on same face and position', function () {
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

    // Group devices by face
    $frontDevices = array_filter($placedDevices, fn ($d) => $d['face'] === 'front');
    $rearDevices = array_filter($placedDevices, fn ($d) => $d['face'] === 'rear');

    // Check front face for overlaps
    $frontOccupied = [];
    foreach ($frontDevices as $device) {
        for ($u = $device['start_u']; $u < $device['start_u'] + $device['u_size']; $u++) {
            $position = $u.'-'.$device['width'];
            // Full width conflicts with both halves, and vice versa
            if ($device['width'] === 'full') {
                expect(in_array($u.'-full', $frontOccupied))->toBeFalse("Overlap detected at U{$u} full");
                expect(in_array($u.'-half-left', $frontOccupied))->toBeFalse("Overlap detected at U{$u} with half-left");
                expect(in_array($u.'-half-right', $frontOccupied))->toBeFalse("Overlap detected at U{$u} with half-right");
                $frontOccupied[] = $u.'-full';
            } else {
                expect(in_array($u.'-full', $frontOccupied))->toBeFalse("Half-width conflicts with full at U{$u}");
                expect(in_array($position, $frontOccupied))->toBeFalse("Overlap detected at {$position}");
                $frontOccupied[] = $position;
            }
        }
    }

    // Check rear face for overlaps using same logic
    $rearOccupied = [];
    foreach ($rearDevices as $device) {
        for ($u = $device['start_u']; $u < $device['start_u'] + $device['u_size']; $u++) {
            $position = $u.'-'.$device['width'];
            if ($device['width'] === 'full') {
                expect(in_array($u.'-full', $rearOccupied))->toBeFalse("Rear overlap at U{$u} full");
                expect(in_array($u.'-half-left', $rearOccupied))->toBeFalse("Rear overlap at U{$u} with half-left");
                expect(in_array($u.'-half-right', $rearOccupied))->toBeFalse("Rear overlap at U{$u} with half-right");
                $rearOccupied[] = $u.'-full';
            } else {
                expect(in_array($u.'-full', $rearOccupied))->toBeFalse("Rear half-width conflicts with full at U{$u}");
                expect(in_array($position, $rearOccupied))->toBeFalse("Rear overlap at {$position}");
                $rearOccupied[] = $position;
            }
        }
    }
});

/**
 * Test 4: Half-width device pairs can coexist at same U position
 */
test('sample data includes half-width device pair at same U position', function () {
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

    // Find pairs of half-left and half-right devices at same U and face
    $halfLeftDevices = array_filter($placedDevices, fn ($d) => $d['width'] === 'half-left');
    $halfRightDevices = array_filter($placedDevices, fn ($d) => $d['width'] === 'half-right');

    $foundPair = false;
    foreach ($halfLeftDevices as $leftDevice) {
        foreach ($halfRightDevices as $rightDevice) {
            if (
                $leftDevice['start_u'] === $rightDevice['start_u'] &&
                $leftDevice['face'] === $rightDevice['face']
            ) {
                $foundPair = true;
                break 2;
            }
        }
    }

    // Sample data should include at least one half-width pair to demonstrate the feature
    expect($foundPair)->toBeTrue('Expected at least one half-width device pair at same U position');
});
