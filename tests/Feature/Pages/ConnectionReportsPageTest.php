<?php

/**
 * Connection Reports Page Tests
 *
 * Tests for the Connection Reports frontend page including:
 * - Page renders with metrics data
 * - Filter dropdowns populate correctly
 * - Filter changes trigger Inertia request
 * - Empty state displays when no connections
 */

use App\Enums\CableType;
use App\Enums\PortType;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Port;
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

/**
 * Test 1: Page renders with metrics data.
 *
 * Verifies that the Connection Reports page renders successfully with all
 * required metrics data including total connections, cable type distribution,
 * port type distribution, cable length stats, and port utilization.
 */
test('page renders with metrics data', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create hierarchy with connections
    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    // Create ports and connections
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_type' => CableType::Cat6,
        'cable_length' => 3.5,
    ]);

    $response = $this->actingAs($user)
        ->get('/connection-reports');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        // Verify all metrics data is present
        ->has('metrics')
        ->has('metrics.totalConnections')
        ->where('metrics.totalConnections', 1)
        ->has('metrics.cableTypeDistribution')
        ->has('metrics.portTypeDistribution')
        ->has('metrics.cableLengthStats')
        ->has('metrics.portUtilization')
        // Verify connections array (client-side pagination)
        ->has('metrics.connections', 1)
    );
});

/**
 * Test 2: Filter dropdowns populate correctly.
 *
 * Verifies that the page receives correct filter options including:
 * - Datacenter options for cascading filter
 * - Room options (when datacenter is selected)
 * - Current filter state
 */
test('filter dropdowns populate correctly', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create multiple datacenters with rooms
    $datacenter1 = Datacenter::factory()->create(['name' => 'Alpha DC']);
    $datacenter2 = Datacenter::factory()->create(['name' => 'Beta DC']);

    $room1 = Room::factory()->create(['datacenter_id' => $datacenter1->id, 'name' => 'Room A']);
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter1->id, 'name' => 'Room B']);
    Room::factory()->create(['datacenter_id' => $datacenter2->id, 'name' => 'Room C']);

    // Test without datacenter filter - should have datacenters but no rooms
    $response = $this->actingAs($user)
        ->get('/connection-reports');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        // Verify datacenter options
        ->has('datacenterOptions', 2)
        ->where('datacenterOptions.0.name', 'Alpha DC')
        ->where('datacenterOptions.1.name', 'Beta DC')
        // Verify room options are empty when no datacenter selected
        ->has('roomOptions', 0)
        // Verify filter state
        ->has('filters')
        ->where('filters.datacenter_id', null)
        ->where('filters.room_id', null)
    );

    // Test with datacenter filter - should load rooms for that datacenter
    $response = $this->actingAs($user)
        ->get("/connection-reports?datacenter_id={$datacenter1->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        // Verify room options are filtered to selected datacenter
        ->has('roomOptions', 2)
        ->where('roomOptions.0.name', 'Room A')
        ->where('roomOptions.1.name', 'Room B')
        // Verify filter state reflects the selection
        ->where('filters.datacenter_id', $datacenter1->id)
    );
});

/**
 * Test 3: Filter changes trigger Inertia request with correct parameters.
 *
 * Verifies that applying filters updates the page with filtered data
 * and that the filter state is preserved in the response.
 */
test('filter changes trigger Inertia request with correct filtered data', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create hierarchy for datacenter 1
    $datacenter1 = Datacenter::factory()->create(['name' => 'DC1']);
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter1->id, 'name' => 'Room 1']);
    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $rack1 = Rack::factory()->create(['row_id' => $row1->id]);
    $device1 = Device::factory()->create(['rack_id' => $rack1->id]);

    // Create 3 connections in DC1
    for ($i = 0; $i < 3; $i++) {
        $sourcePort = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
        $destPort = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
        Connection::factory()->create([
            'source_port_id' => $sourcePort->id,
            'destination_port_id' => $destPort->id,
        ]);
    }

    // Create hierarchy for datacenter 2
    $datacenter2 = Datacenter::factory()->create(['name' => 'DC2']);
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter2->id, 'name' => 'Room 2']);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);
    $rack2 = Rack::factory()->create(['row_id' => $row2->id]);
    $device2 = Device::factory()->create(['rack_id' => $rack2->id]);

    // Create 5 connections in DC2
    for ($i = 0; $i < 5; $i++) {
        $sourcePort = Port::factory()->ethernet()->create(['device_id' => $device2->id]);
        $destPort = Port::factory()->ethernet()->create(['device_id' => $device2->id]);
        Connection::factory()->create([
            'source_port_id' => $sourcePort->id,
            'destination_port_id' => $destPort->id,
        ]);
    }

    // Test without filter - should see all 8 connections
    $response = $this->actingAs($user)
        ->get('/connection-reports');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('metrics.totalConnections', 8)
    );

    // Test with datacenter filter - should see only 3 connections
    $response = $this->actingAs($user)
        ->get("/connection-reports?datacenter_id={$datacenter1->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('metrics.totalConnections', 3)
        ->where('filters.datacenter_id', $datacenter1->id)
    );

    // Test with room filter - should also show 3 connections
    $response = $this->actingAs($user)
        ->get("/connection-reports?datacenter_id={$datacenter1->id}&room_id={$room1->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('metrics.totalConnections', 3)
        ->where('filters.datacenter_id', $datacenter1->id)
        ->where('filters.room_id', $room1->id)
    );
});

/**
 * Test 4: Empty state displays when no connections exist.
 *
 * Verifies that the page handles the empty state gracefully when
 * there are no connections in the system or matching the filters.
 * The service returns all enum types with zero counts for distributions.
 */
test('empty state displays when no connections', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create datacenter but no connections
    Datacenter::factory()->create(['name' => 'Empty DC']);

    $response = $this->actingAs($user)
        ->get('/connection-reports');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        // Verify empty metrics
        ->where('metrics.totalConnections', 0)
        ->has('metrics.connections', 0)
        // Verify cable type distribution includes all enum types with zero counts
        ->has('metrics.cableTypeDistribution', count(CableType::cases()))
        ->where('metrics.cableTypeDistribution.0.count', 0)
        // Verify port type distribution includes all enum types with zero counts
        ->has('metrics.portTypeDistribution', count(PortType::cases()))
        ->where('metrics.portTypeDistribution.0.count', 0)
        // Verify empty cable length stats
        ->has('metrics.cableLengthStats')
        ->where('metrics.cableLengthStats.count', 0)
    );
});
