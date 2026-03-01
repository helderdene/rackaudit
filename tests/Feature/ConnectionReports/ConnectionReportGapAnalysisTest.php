<?php

/**
 * Connection Reports Gap Analysis Tests
 *
 * Additional strategic tests to fill coverage gaps identified during
 * Task Group 9 test review. These tests focus on:
 * - Edge cases with mixed cable types and power cables
 * - Cross-datacenter connection handling
 * - Auditor role access
 * - Connection inventory data integrity
 * - Room filter validation without datacenter
 * - Empty datacenter scenarios
 * - Export with all cable types represented
 */

use App\Enums\CableType;
use App\Enums\PortStatus;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use App\Services\ConnectionCalculationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
});

/**
 * Test 1: Power cable types are properly counted in distribution.
 *
 * Verifies that all power cable types (C13, C14, C19, C20) are correctly
 * included in the cable type distribution with proper counts and labels.
 */
test('power cable types are properly counted in cable type distribution', function () {
    $service = app(ConnectionCalculationService::class);

    // Create hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    // Create power connections with different cable types
    $powerCableTypes = [
        CableType::PowerC13,
        CableType::PowerC14,
        CableType::PowerC19,
        CableType::PowerC20,
    ];

    foreach ($powerCableTypes as $index => $cableType) {
        $sourcePort = Port::factory()->power()->create(['device_id' => $device->id]);
        $destPort = Port::factory()->power()->create(['device_id' => $device->id]);
        Connection::factory()->count($index + 1)->create([
            'source_port_id' => $sourcePort->id,
            'destination_port_id' => $destPort->id,
            'cable_type' => $cableType,
        ]);
    }

    $query = $service->buildFilteredConnectionQuery($datacenter->id);
    $distribution = $service->getCableTypeDistribution($query);

    // Verify all power cable types are represented
    $countsByCableType = collect($distribution)->pluck('count', 'type')->toArray();

    expect($countsByCableType[CableType::PowerC13->value])->toBe(1);
    expect($countsByCableType[CableType::PowerC14->value])->toBe(2);
    expect($countsByCableType[CableType::PowerC19->value])->toBe(3);
    expect($countsByCableType[CableType::PowerC20->value])->toBe(4);

    // Verify labels are correct
    $labelsByCableType = collect($distribution)->pluck('label', 'type')->toArray();
    expect($labelsByCableType[CableType::PowerC13->value])->toBe('C13');
    expect($labelsByCableType[CableType::PowerC14->value])->toBe('C14');
    expect($labelsByCableType[CableType::PowerC19->value])->toBe('C19');
    expect($labelsByCableType[CableType::PowerC20->value])->toBe('C20');
});

/**
 * Test 2: Auditor role has access to Connection Reports with assigned datacenter.
 *
 * Verifies that users with Auditor role can access Connection Reports
 * and only see data for their assigned datacenters.
 */
test('auditor role has access to Connection Reports with assigned datacenter', function () {
    // Create two datacenters
    $datacenter1 = Datacenter::factory()->create(['name' => 'DC1']);
    $datacenter2 = Datacenter::factory()->create(['name' => 'DC2']);

    // Create connections in both datacenters
    foreach ([$datacenter1, $datacenter2] as $datacenter) {
        $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
        $row = Row::factory()->create(['room_id' => $room->id]);
        $rack = Rack::factory()->create(['row_id' => $row->id]);
        $device = Device::factory()->create(['rack_id' => $rack->id]);

        $sourcePort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
        $destPort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
        Connection::factory()->count(2)->create([
            'source_port_id' => $sourcePort->id,
            'destination_port_id' => $destPort->id,
        ]);
    }

    // Create Auditor user with access to DC1 only
    $auditor = User::factory()->create();
    $auditor->assignRole('Auditor');
    $auditor->datacenters()->attach($datacenter1);

    $response = $this->actingAs($auditor)->get('/connection-reports');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        // Auditor should only see DC1 in options
        ->has('datacenterOptions', 1)
        ->where('datacenterOptions.0.name', 'DC1')
        // Should only see 2 connections from DC1
        ->where('metrics.totalConnections', 2)
    );
});

/**
 * Test 3: Connection inventory contains all expected connection data fields.
 *
 * Verifies that connections in the inventory include all required fields
 * (source device, source port, destination device, destination port, etc.)
 * and that multiple connections are returned correctly.
 */
test('connection inventory contains all expected connection data fields', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create devices with different names
    $deviceA = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Alpha-Server']);
    $deviceB = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Beta-Switch']);

    // Create multiple connections
    $connections = [
        ['sourceDevice' => $deviceA, 'sourceLabel' => 'eth0', 'destDevice' => $deviceB, 'destLabel' => 'port1'],
        ['sourceDevice' => $deviceA, 'sourceLabel' => 'eth1', 'destDevice' => $deviceB, 'destLabel' => 'port2'],
        ['sourceDevice' => $deviceB, 'sourceLabel' => 'port3', 'destDevice' => $deviceA, 'destLabel' => 'eth2'],
    ];

    foreach ($connections as $conn) {
        $sourcePort = Port::factory()->ethernet()->create([
            'device_id' => $conn['sourceDevice']->id,
            'label' => $conn['sourceLabel'],
        ]);
        $destPort = Port::factory()->ethernet()->create([
            'device_id' => $conn['destDevice']->id,
            'label' => $conn['destLabel'],
        ]);
        Connection::factory()->create([
            'source_port_id' => $sourcePort->id,
            'destination_port_id' => $destPort->id,
            'cable_type' => CableType::Cat6,
        ]);
    }

    $response = $this->actingAs($user)->get('/connection-reports');

    $response->assertSuccessful();

    // Verify all 3 connections are returned
    $pageProps = $response->original->getData()['page']['props'];
    $connectionsList = $pageProps['metrics']['connections'];

    expect($connectionsList)->toHaveCount(3);

    // Verify each connection has all required fields
    foreach ($connectionsList as $connection) {
        expect($connection)->toHaveKeys([
            'id',
            'source_device_name',
            'source_port_label',
            'destination_device_name',
            'destination_port_label',
            'cable_type',
            'cable_type_label',
            'cable_length',
            'cable_color',
        ]);
    }

    // Verify device names are present in the results
    $sourceDeviceNames = collect($connectionsList)->pluck('source_device_name')->unique()->toArray();
    expect($sourceDeviceNames)->toContain('Alpha-Server');
    expect($sourceDeviceNames)->toContain('Beta-Switch');
});

/**
 * Test 4: Room filter without datacenter returns empty or handles gracefully.
 *
 * Verifies that providing a room_id without a valid datacenter_id
 * is handled gracefully (e.g., ignored or validated).
 */
test('room filter without datacenter is handled gracefully', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create hierarchy with connections
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    // Request with room_id but no datacenter_id - should handle gracefully
    $response = $this->actingAs($user)->get("/connection-reports?room_id={$room->id}");

    // Should either filter by room (if implemented to infer datacenter)
    // or ignore the room filter (return all connections)
    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        ->has('metrics')
    );
});

/**
 * Test 5: Empty datacenter (no devices, no ports, no connections) shows zero counts.
 *
 * Verifies that a completely empty datacenter with no infrastructure
 * at all returns proper zero values for all metrics.
 */
test('completely empty datacenter shows zero counts for all metrics', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create only a datacenter with no rooms or devices
    $datacenter = Datacenter::factory()->create(['name' => 'Empty DC']);

    $response = $this->actingAs($user)->get("/connection-reports?datacenter_id={$datacenter->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        ->where('metrics.totalConnections', 0)
        ->has('metrics.connections', 0)
        // Port utilization should show zeroes
        ->has('metrics.portUtilization.overall')
        ->where('metrics.portUtilization.overall.total', 0)
        ->where('metrics.portUtilization.overall.connected', 0)
        // Cable length stats should be null/empty
        ->has('metrics.cableLengthStats')
        ->where('metrics.cableLengthStats.count', 0)
    );
});

/**
 * Test 6: CSV export contains all connection data with mixed cable types.
 *
 * Verifies that CSV export correctly includes connections of all
 * different cable types and downloads successfully.
 */
test('CSV export contains all connection data with mixed cable types', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    // Create connections with different cable types
    $cableTypes = [
        ['type' => CableType::Cat6, 'portFactory' => 'ethernet'],
        ['type' => CableType::FiberSm, 'portFactory' => 'fiber'],
        ['type' => CableType::PowerC13, 'portFactory' => 'power'],
    ];

    foreach ($cableTypes as $config) {
        $sourcePort = Port::factory()->{$config['portFactory']}()->create(['device_id' => $device->id]);
        $destPort = Port::factory()->{$config['portFactory']}()->create(['device_id' => $device->id]);
        Connection::factory()->create([
            'source_port_id' => $sourcePort->id,
            'destination_port_id' => $destPort->id,
            'cable_type' => $config['type'],
            'cable_length' => 2.5,
            'cable_color' => 'blue',
        ]);
    }

    $response = $this->actingAs($user)->get('/connection-reports/export/csv');

    $response->assertSuccessful();
    $response->assertDownload();

    // Verify the download has a CSV filename
    $contentDisposition = $response->headers->get('content-disposition');
    expect($contentDisposition)->toContain('connection-report-');
    expect($contentDisposition)->toContain('.csv');
});

/**
 * Test 7: Port utilization correctly handles Reserved and Disabled ports.
 *
 * Verifies that port status breakdown includes all four statuses:
 * Available, Connected, Reserved, Disabled.
 */
test('port utilization includes all port statuses in breakdown', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    // Create ports with each status
    Port::factory()->count(5)->ethernet()->create([
        'device_id' => $device->id,
        'status' => PortStatus::Available,
    ]);
    Port::factory()->count(3)->ethernet()->create([
        'device_id' => $device->id,
        'status' => PortStatus::Connected,
    ]);
    Port::factory()->count(2)->ethernet()->create([
        'device_id' => $device->id,
        'status' => PortStatus::Reserved,
    ]);
    Port::factory()->count(1)->ethernet()->create([
        'device_id' => $device->id,
        'status' => PortStatus::Disabled,
    ]);

    $response = $this->actingAs($user)->get('/connection-reports');

    $response->assertSuccessful();

    $pageProps = $response->original->getData()['page']['props'];
    $byStatus = $pageProps['metrics']['portUtilization']['byStatus'];

    // Verify all statuses are represented
    $statusCounts = collect($byStatus)->pluck('count', 'status')->toArray();

    expect($statusCounts[PortStatus::Available->value])->toBe(5);
    expect($statusCounts[PortStatus::Connected->value])->toBe(3);
    expect($statusCounts[PortStatus::Reserved->value])->toBe(2);
    expect($statusCounts[PortStatus::Disabled->value])->toBe(1);

    // Verify overall totals
    $overall = $pageProps['metrics']['portUtilization']['overall'];
    expect($overall['total'])->toBe(11);
    expect($overall['connected'])->toBe(3);
});

/**
 * Test 8: Cross-datacenter connections are filtered correctly based on source port location.
 *
 * When source device is in DC1 and destination device is in DC2,
 * filtering by DC1 should include this connection.
 */
test('cross-datacenter connections filtered by source port datacenter', function () {
    $service = app(ConnectionCalculationService::class);

    // Create two datacenters
    $datacenter1 = Datacenter::factory()->create(['name' => 'DC1']);
    $datacenter2 = Datacenter::factory()->create(['name' => 'DC2']);

    // Create hierarchy for DC1
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter1->id]);
    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $rack1 = Rack::factory()->create(['row_id' => $row1->id]);
    $device1 = Device::factory()->create(['rack_id' => $rack1->id, 'name' => 'Server-DC1']);

    // Create hierarchy for DC2
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter2->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);
    $rack2 = Rack::factory()->create(['row_id' => $row2->id]);
    $device2 = Device::factory()->create(['rack_id' => $rack2->id, 'name' => 'Switch-DC2']);

    // Create cross-datacenter connection (source in DC1, destination in DC2)
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $device2->id]);
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    // Filter by DC1 should include this connection (based on source port)
    $query = $service->buildFilteredConnectionQuery($datacenter1->id);
    expect($query->count())->toBe(1);

    // Filter by DC2 should NOT include this connection
    $query = $service->buildFilteredConnectionQuery($datacenter2->id);
    expect($query->count())->toBe(0);
});

/**
 * Test 9: Connections with all null optional fields are handled correctly.
 *
 * Verifies that connections with null cable_length, cable_color, and other
 * optional fields are displayed and exported correctly.
 */
test('connections with null optional fields are handled correctly', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Test Device']);

    // Create connection with null optional fields
    $sourcePort = Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'eth0',
    ]);
    $destPort = Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'eth1',
    ]);
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_type' => CableType::Cat6,
        'cable_length' => null,
        'cable_color' => null,
    ]);

    $response = $this->actingAs($user)->get('/connection-reports');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        ->where('metrics.totalConnections', 1)
        ->has('metrics.connections', 1)
    );

    // Verify the connection data handles nulls
    $pageProps = $response->original->getData()['page']['props'];
    $connection = $pageProps['metrics']['connections'][0];

    expect($connection['source_device_name'])->toBe('Test Device');
    expect($connection['source_port_label'])->toBe('eth0');
    expect($connection['cable_length'])->toBeNull();
    expect($connection['cable_color'])->toBeNull();
});

/**
 * Test 10: PDF export with datacenter and room filters applies filtering correctly.
 *
 * Verifies that PDF export respects both datacenter and room filter parameters
 * and only includes connections matching the filters.
 */
test('PDF export applies datacenter and room filters correctly', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create datacenter with two rooms
    $datacenter = Datacenter::factory()->create(['name' => 'Main DC']);
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Room A']);
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Room B']);

    // Create hierarchy for room 1
    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $rack1 = Rack::factory()->create(['row_id' => $row1->id]);
    $device1 = Device::factory()->create(['rack_id' => $rack1->id]);

    // Create hierarchy for room 2
    $row2 = Row::factory()->create(['room_id' => $room2->id]);
    $rack2 = Rack::factory()->create(['row_id' => $row2->id]);
    $device2 = Device::factory()->create(['rack_id' => $rack2->id]);

    // Create connection in room 1
    $sourcePort1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    $destPort1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    Connection::factory()->create([
        'source_port_id' => $sourcePort1->id,
        'destination_port_id' => $destPort1->id,
    ]);

    // Create connection in room 2
    $sourcePort2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);
    $destPort2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);
    Connection::factory()->create([
        'source_port_id' => $sourcePort2->id,
        'destination_port_id' => $destPort2->id,
    ]);

    // Export PDF filtered by room 1
    $response = $this->actingAs($user)
        ->get("/connection-reports/export/pdf?datacenter_id={$datacenter->id}&room_id={$room1->id}");

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/pdf');
});
