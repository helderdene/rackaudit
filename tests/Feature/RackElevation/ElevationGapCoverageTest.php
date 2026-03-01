<?php

/**
 * Gap Coverage Tests for Rack Elevation Feature
 *
 * These tests fill critical gaps identified during the test review phase,
 * focusing on end-to-end workflows and integration points.
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
use Illuminate\Support\Facades\File;
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
        'name' => 'Gap Coverage Test Rack',
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
    ]);

    // Create device types
    $this->serverType = DeviceType::factory()->create(['name' => 'Server', 'default_u_size' => 2]);
    $this->switchType = DeviceType::factory()->create(['name' => 'Switch', 'default_u_size' => 1]);
    $this->storageType = DeviceType::factory()->create(['name' => 'Storage', 'default_u_size' => 4]);

    // Create placed devices (front face) - includes multi-U device
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
    // Storage device (multi-U)
    Device::factory()->create([
        'rack_id' => $this->rack->id,
        'device_type_id' => $this->storageType->id,
        'name' => 'Storage Array',
        'start_u' => 10,
        'u_height' => 4,
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
    Device::factory()->create([
        'rack_id' => null,
        'device_type_id' => $this->switchType->id,
        'name' => 'Unplaced Switch',
        'start_u' => null,
        'u_height' => 1,
    ]);

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');
});

/**
 * Test 1: Verify useRackElevation composable file exists
 * Critical for the drag-and-drop and state management functionality
 */
test('useRackElevation composable TypeScript file exists', function () {
    $composableFile = resource_path('js/composables/useRackElevation.ts');

    expect(File::exists($composableFile))->toBeTrue();

    $content = File::get($composableFile);

    // Verify core functions are exported
    expect($content)->toContain('export function useRackElevation');
    expect($content)->toContain('canPlaceAt');
    expect($content)->toContain('placeDevice');
    expect($content)->toContain('moveDevice');
    expect($content)->toContain('removeDevice');
    expect($content)->toContain('utilizationStats');
});

/**
 * Test 2: Verify all elevation Vue component files exist
 * Critical for ensuring the frontend implementation is complete
 */
test('all elevation Vue component files exist', function () {
    $components = [
        'USlot.vue',
        'DeviceBlock.vue',
        'RackElevationView.vue',
        'UtilizationCard.vue',
        'UnplacedDevicesSidebar.vue',
    ];

    $basePath = resource_path('js/components/elevation');

    foreach ($components as $component) {
        $path = $basePath.'/'.$component;
        expect(File::exists($path))->toBeTrue("Component {$component} should exist");
    }
});

/**
 * Test 3: Verify Elevation.vue page imports and uses all required components
 * Critical for ensuring page integration is complete
 */
test('Elevation.vue page imports all required elevation components', function () {
    $pageFile = resource_path('js/pages/Racks/Elevation.vue');

    expect(File::exists($pageFile))->toBeTrue();

    $content = File::get($pageFile);

    // Verify required imports
    expect($content)->toContain('import RackElevationView from');
    expect($content)->toContain('import UnplacedDevicesSidebar from');
    expect($content)->toContain('import UtilizationCard from');
    expect($content)->toContain('import { useRackElevation } from');

    // Verify component usage in template
    expect($content)->toContain('<RackElevationView');
    expect($content)->toContain('<UnplacedDevicesSidebar');
    expect($content)->toContain('<UtilizationCard');
});

/**
 * Test 4: Verify collision detection logic in sample data
 * Full-width devices should not share U-space with any other device
 */
test('sample data validates full-width device cannot share U-space', function () {
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

    // Find all full-width devices
    $fullWidthDevices = array_filter($placedDevices, fn ($d) => $d['width'] === 'full');

    // For each full-width device, verify no other device occupies the same U on same face
    foreach ($fullWidthDevices as $fullDevice) {
        $occupiedUs = range($fullDevice['start_u'], $fullDevice['start_u'] + $fullDevice['u_size'] - 1);

        $otherDevices = array_filter($placedDevices, fn ($d) => $d['id'] !== $fullDevice['id'] && $d['face'] === $fullDevice['face']
        );

        foreach ($otherDevices as $otherDevice) {
            if ($otherDevice['start_u'] === null) {
                continue;
            }
            $otherOccupiedUs = range(
                $otherDevice['start_u'],
                $otherDevice['start_u'] + $otherDevice['u_size'] - 1
            );

            $intersection = array_intersect($occupiedUs, $otherOccupiedUs);
            expect($intersection)
                ->toBeEmpty("Full-width device {$fullDevice['name']} should not share U-space with {$otherDevice['name']}");
        }
    }
});

/**
 * Test 5: Verify multi-U devices do not overlap in sample data
 * Tests that collision detection works for devices spanning multiple rack units
 */
test('sample data multi-U devices do not overlap within same face', function () {
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

    // Get only multi-U devices (u_size > 1)
    $multiUDevices = array_filter($placedDevices, fn ($d) => $d['u_size'] > 1);

    expect(count($multiUDevices))->toBeGreaterThan(0, 'Should have at least one multi-U device for testing');

    // Group by face
    $devicesByFace = [];
    foreach ($placedDevices as $device) {
        $devicesByFace[$device['face']][] = $device;
    }

    // Check each face for overlaps
    foreach ($devicesByFace as $face => $devices) {
        $occupiedSlots = [];

        foreach ($devices as $device) {
            for ($u = $device['start_u']; $u < $device['start_u'] + $device['u_size']; $u++) {
                $slotKey = $u.'-'.$device['width'];

                // Full-width conflicts with everything
                if ($device['width'] === 'full') {
                    expect(in_array($u.'-full', $occupiedSlots))->toBeFalse(
                        "Multi-U device overlap at U{$u} on {$face} face (full)"
                    );
                    $occupiedSlots[] = $u.'-full';
                } else {
                    // Half-width only conflicts with same half or full
                    expect(in_array($u.'-full', $occupiedSlots))->toBeFalse(
                        "Half-width conflicts with full at U{$u} on {$face} face"
                    );
                    expect(in_array($slotKey, $occupiedSlots))->toBeFalse(
                        "Half-width overlap at {$slotKey} on {$face} face"
                    );
                    $occupiedSlots[] = $slotKey;
                }
            }
        }
    }
});

/**
 * Test 6: Verify utilization calculation data completeness
 * Ensures the composable has all necessary data for accurate utilization stats
 */
test('sample data supports accurate utilization calculation', function () {
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

    // Calculate expected utilization
    $totalU = $rack['u_height'];
    $frontUsedUs = [];
    $rearUsedUs = [];

    foreach ($placedDevices as $device) {
        for ($u = $device['start_u']; $u < $device['start_u'] + $device['u_size']; $u++) {
            if ($device['face'] === 'front') {
                $frontUsedUs[$u] = true;
            } else {
                $rearUsedUs[$u] = true;
            }
        }
    }

    $frontUsedU = count($frontUsedUs);
    $rearUsedU = count($rearUsedUs);
    $usedU = max($frontUsedU, $rearUsedU);

    // Verify we have meaningful utilization data
    expect($totalU)->toBe(42);
    expect($frontUsedU)->toBeGreaterThan(0);
    expect($rearUsedU)->toBeGreaterThan(0);
    expect($usedU)->toBeLessThanOrEqual($totalU);

    // Verify utilization percentage calculation is possible
    $utilizationPercent = ($usedU / $totalU) * 100;
    expect($utilizationPercent)->toBeGreaterThanOrEqual(0);
    expect($utilizationPercent)->toBeLessThanOrEqual(100);
});

/**
 * Test 7: Verify sample data includes variety of device types
 * Ensures the placeholder system demonstrates multiple device categories
 */
test('sample data includes variety of device types for demonstration', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $this->rack->id,
        ]));

    $response->assertOk();

    $page = $response->viewData('page');
    $allDevices = array_merge(
        $page['props']['devices']['placed'],
        $page['props']['devices']['unplaced']
    );

    $deviceTypes = array_unique(array_map(fn ($d) => strtolower($d['type']), $allDevices));

    // Should have at least 2 different device types
    expect(count($deviceTypes))->toBeGreaterThanOrEqual(2);

    // Common device types should be represented (case-insensitive)
    $hasServer = in_array('server', $deviceTypes);
    $hasSwitch = in_array('switch', $deviceTypes);
    $hasStorage = in_array('storage', $deviceTypes);

    expect($hasServer || $hasSwitch || $hasStorage)->toBeTrue(
        'Sample data should include at least one common device type (server, switch, or storage)'
    );
});

/**
 * Test 8: Verify device IDs are unique across all devices
 * Critical for drag-and-drop and device identification
 */
test('all device IDs are unique across placed and unplaced devices', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $this->rack->id,
        ]));

    $response->assertOk();

    $page = $response->viewData('page');
    $allDevices = array_merge(
        $page['props']['devices']['placed'],
        $page['props']['devices']['unplaced']
    );

    $ids = array_map(fn ($d) => $d['id'], $allDevices);
    $uniqueIds = array_unique($ids);

    expect(count($ids))->toBe(count($uniqueIds), 'All device IDs should be unique');
});

/**
 * Test 9: Verify elevation endpoint with 45U rack
 * Tests different rack height scenarios
 */
test('elevation page works correctly with 45U rack height', function () {
    $rack45U = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Rack 45U',
        'u_height' => RackUHeight::U45,
        'status' => RackStatus::Active,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack45U->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Elevation')
            ->where('rack.u_height', 45)
            ->has('devices.placed')
            ->has('devices.unplaced')
        );

    // Verify placed devices respect 45U limit
    $page = $response->viewData('page');
    $placedDevices = $page['props']['devices']['placed'];

    foreach ($placedDevices as $device) {
        $endU = $device['start_u'] + $device['u_size'] - 1;
        expect($endU)->toBeLessThanOrEqual(45, 'Device should not exceed 45U rack height');
    }
});

/**
 * Test 10: Verify back navigation link data is available
 * Tests that the page has sufficient data for the "Back to Rack" functionality
 */
test('elevation page provides data for back navigation to rack', function () {
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
            // Hierarchy data for navigation
            ->has('datacenter')
            ->has('datacenter.id')
            ->has('datacenter.name')
            ->has('room')
            ->has('room.id')
            ->has('room.name')
            ->has('row')
            ->has('row.id')
            ->has('row.name')
            ->has('rack')
            ->has('rack.id')
            ->has('rack.name')
        );
});
