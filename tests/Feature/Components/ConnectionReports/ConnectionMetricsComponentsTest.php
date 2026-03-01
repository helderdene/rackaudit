<?php

/**
 * Connection Metrics Components Tests
 *
 * Tests for the Connection Reports metrics components including:
 * - ConnectionMetricsCards renders total connections
 * - CableTypeDistributionChart renders pie chart
 * - PortUtilizationChart renders bar chart
 * - Components handle empty data gracefully
 *
 * These tests verify that the Connection Reports page provides correct
 * data structures for the metrics components to render.
 */

use App\Enums\CableType;
use App\Enums\PortStatus;
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
 * Test 1: ConnectionMetricsCards component receives correct data for rendering total connections
 *
 * Verifies that the metrics data includes total connections count and
 * port type distribution for the metrics cards to render.
 */
test('ConnectionMetricsCards renders total connections with port type breakdown', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    // Create 3 Ethernet connections with cable_length
    for ($i = 0; $i < 3; $i++) {
        $sourcePort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
        $destPort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
        Connection::factory()->create([
            'source_port_id' => $sourcePort->id,
            'destination_port_id' => $destPort->id,
            'cable_type' => CableType::Cat6,
            'cable_length' => 2.5 + $i,
        ]);
    }

    // Create 2 Fiber connections without cable_length (explicitly null)
    for ($i = 0; $i < 2; $i++) {
        $sourcePort = Port::factory()->fiber()->create(['device_id' => $device->id]);
        $destPort = Port::factory()->fiber()->create(['device_id' => $device->id]);
        Connection::factory()->create([
            'source_port_id' => $sourcePort->id,
            'destination_port_id' => $destPort->id,
            'cable_type' => CableType::FiberSm,
            'cable_length' => null,
        ]);
    }

    $response = $this->actingAs($user)->get('/connection-reports');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        // Verify total connections count for metrics card
        ->where('metrics.totalConnections', 5)
        // Verify port type distribution for breakdown cards
        ->has('metrics.portTypeDistribution')
        ->has('metrics.portTypeDistribution', fn ($distribution) => $distribution
            ->each(fn ($item) => $item
                ->has('type')
                ->has('label')
                ->has('count')
                ->has('percentage')
            )
        )
        // Verify cable length statistics for stats display
        ->has('metrics.cableLengthStats')
        ->has('metrics.cableLengthStats.mean')
        ->has('metrics.cableLengthStats.min')
        ->has('metrics.cableLengthStats.max')
        ->has('metrics.cableLengthStats.count')
    );

    // Verify the actual port type counts
    $pageProps = $response->original->getData()['page']['props'];
    $portTypeDistribution = $pageProps['metrics']['portTypeDistribution'];

    // Find Ethernet and Fiber counts
    $ethernetItem = collect($portTypeDistribution)->firstWhere('type', PortType::Ethernet->value);
    $fiberItem = collect($portTypeDistribution)->firstWhere('type', PortType::Fiber->value);

    expect($ethernetItem['count'])->toBe(3);
    expect($fiberItem['count'])->toBe(2);

    // Verify cable length stats (only 3 Ethernet connections have cable_length)
    $cableLengthStats = $pageProps['metrics']['cableLengthStats'];
    expect($cableLengthStats['count'])->toBe(3);
    expect($cableLengthStats['min'])->toBe(2.5);
    expect($cableLengthStats['max'])->toBe(4.5);
});

/**
 * Test 2: CableTypeDistributionChart component receives correct data for pie chart rendering
 *
 * Verifies that cable type distribution data is properly structured for
 * Chart.js pie/donut chart with counts and percentages.
 */
test('CableTypeDistributionChart renders pie chart with cable type distribution', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    // Create connections with different cable types using array with string keys
    $cableTypeConfigs = [
        ['type' => CableType::Cat5e, 'count' => 2, 'portType' => 'ethernet'],
        ['type' => CableType::Cat6, 'count' => 5, 'portType' => 'ethernet'],
        ['type' => CableType::Cat6a, 'count' => 3, 'portType' => 'ethernet'],
        ['type' => CableType::FiberSm, 'count' => 4, 'portType' => 'fiber'],
        ['type' => CableType::FiberMm, 'count' => 1, 'portType' => 'fiber'],
    ];

    foreach ($cableTypeConfigs as $config) {
        $portType = $config['portType'];
        for ($i = 0; $i < $config['count']; $i++) {
            $sourcePort = Port::factory()->{$portType}()->create(['device_id' => $device->id]);
            $destPort = Port::factory()->{$portType}()->create(['device_id' => $device->id]);
            Connection::factory()->create([
                'source_port_id' => $sourcePort->id,
                'destination_port_id' => $destPort->id,
                'cable_type' => $config['type'],
            ]);
        }
    }

    $response = $this->actingAs($user)->get('/connection-reports');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        // Verify cable type distribution structure for Chart.js
        ->has('metrics.cableTypeDistribution')
        ->has('metrics.cableTypeDistribution', fn ($distribution) => $distribution
            ->each(fn ($item) => $item
                ->has('type')      // Cable type enum value for identification
                ->has('label')     // Human-readable label for chart legend
                ->has('count')     // Count for chart data
                ->has('percentage') // Percentage for chart tooltip/legend
            )
        )
    );

    // Verify specific cable type counts and percentages
    $pageProps = $response->original->getData()['page']['props'];
    $distribution = $pageProps['metrics']['cableTypeDistribution'];

    // Find and verify Cat6 (most common)
    $cat6Item = collect($distribution)->firstWhere('type', CableType::Cat6->value);
    expect($cat6Item['count'])->toBe(5);
    expect($cat6Item['percentage'])->toBeGreaterThan(33); // Should be ~33.3%

    // Find and verify FiberSm
    $fiberSmItem = collect($distribution)->firstWhere('type', CableType::FiberSm->value);
    expect($fiberSmItem['count'])->toBe(4);

    // Verify all distribution items have valid percentages
    foreach ($distribution as $item) {
        expect($item['percentage'])->toBeGreaterThanOrEqual(0);
        expect($item['percentage'])->toBeLessThanOrEqual(100);
    }
});

/**
 * Test 3: PortUtilizationChart component receives correct data for bar chart rendering
 *
 * Verifies that port utilization data by type is properly structured for
 * Chart.js horizontal bar chart with total/connected/percentage.
 */
test('PortUtilizationChart renders bar chart with port utilization by type', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    // Create 10 Ethernet ports: 6 connected, 4 available
    $ethernetPorts = Port::factory()
        ->count(10)
        ->ethernet()
        ->create(['device_id' => $device->id, 'status' => PortStatus::Available]);

    // Connect 6 of the Ethernet ports (3 connections)
    for ($i = 0; $i < 3; $i++) {
        $sourcePort = $ethernetPorts[$i * 2];
        $destPort = $ethernetPorts[$i * 2 + 1];
        $sourcePort->update(['status' => PortStatus::Connected]);
        $destPort->update(['status' => PortStatus::Connected]);
        Connection::factory()->create([
            'source_port_id' => $sourcePort->id,
            'destination_port_id' => $destPort->id,
            'cable_type' => CableType::Cat6,
        ]);
    }

    // Create 6 Fiber ports: 4 connected, 2 available
    $fiberPorts = Port::factory()
        ->count(6)
        ->fiber()
        ->create(['device_id' => $device->id, 'status' => PortStatus::Available]);

    // Connect 4 of the Fiber ports (2 connections)
    for ($i = 0; $i < 2; $i++) {
        $sourcePort = $fiberPorts[$i * 2];
        $destPort = $fiberPorts[$i * 2 + 1];
        $sourcePort->update(['status' => PortStatus::Connected]);
        $destPort->update(['status' => PortStatus::Connected]);
        Connection::factory()->create([
            'source_port_id' => $sourcePort->id,
            'destination_port_id' => $destPort->id,
            'cable_type' => CableType::FiberSm,
        ]);
    }

    $response = $this->actingAs($user)->get('/connection-reports');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        // Verify port utilization structure for Chart.js bar chart
        ->has('metrics.portUtilization')
        ->has('metrics.portUtilization.byType')
        ->has('metrics.portUtilization.byType', fn ($byType) => $byType
            ->each(fn ($item) => $item
                ->has('type')       // Port type enum value
                ->has('label')      // Human-readable label for axis
                ->has('total')      // Total ports for bar length
                ->has('connected')  // Connected ports for filled portion
                ->has('percentage') // Utilization percentage for display
            )
        )
        ->has('metrics.portUtilization.byStatus')
        ->has('metrics.portUtilization.overall')
    );

    // Verify specific port utilization values
    $pageProps = $response->original->getData()['page']['props'];
    $portUtilization = $pageProps['metrics']['portUtilization'];

    // Verify overall utilization
    expect($portUtilization['overall']['total'])->toBe(16); // 10 Ethernet + 6 Fiber
    expect($portUtilization['overall']['connected'])->toBe(10); // 6 Ethernet + 4 Fiber

    // Find and verify Ethernet utilization (60%)
    $ethernetUtil = collect($portUtilization['byType'])->firstWhere('type', PortType::Ethernet->value);
    expect($ethernetUtil['total'])->toBe(10);
    expect($ethernetUtil['connected'])->toBe(6);
    expect($ethernetUtil['percentage'])->toBe(60.0);

    // Find and verify Fiber utilization (~67%)
    $fiberUtil = collect($portUtilization['byType'])->firstWhere('type', PortType::Fiber->value);
    expect($fiberUtil['total'])->toBe(6);
    expect($fiberUtil['connected'])->toBe(4);
    expect(abs($fiberUtil['percentage'] - 66.67))->toBeLessThan(0.1);
});

/**
 * Test 4: Components handle empty data gracefully with zero values
 *
 * Verifies that when no connections exist, all metrics components receive
 * properly structured data with zero values that they can render as empty states.
 */
test('components handle empty data gracefully with zero values', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create datacenter but no connections
    $datacenter = Datacenter::factory()->create(['name' => 'Empty DC']);

    // Create some available ports with no connections
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);
    Port::factory()->count(5)->ethernet()->create([
        'device_id' => $device->id,
        'status' => PortStatus::Available,
    ]);

    $response = $this->actingAs($user)->get('/connection-reports');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        // Total connections should be 0
        ->where('metrics.totalConnections', 0)
        // Cable type distribution should have all types with 0 counts
        ->has('metrics.cableTypeDistribution', count(CableType::cases()))
        // Port type distribution should have all types with 0 counts
        ->has('metrics.portTypeDistribution', count(PortType::cases()))
        // Cable length stats should indicate no data
        ->has('metrics.cableLengthStats')
        ->where('metrics.cableLengthStats.count', 0)
        // Port utilization should have structure but 0 connections
        ->has('metrics.portUtilization.overall')
        ->where('metrics.portUtilization.overall.connected', 0)
    );

    // Verify all cable type counts are 0
    $pageProps = $response->original->getData()['page']['props'];
    $cableTypeDistribution = $pageProps['metrics']['cableTypeDistribution'];

    foreach ($cableTypeDistribution as $item) {
        expect($item['count'])->toBe(0);
        expect($item['percentage'])->toBe(0.0);
    }

    // Verify all port type counts are 0
    $portTypeDistribution = $pageProps['metrics']['portTypeDistribution'];

    foreach ($portTypeDistribution as $item) {
        expect($item['count'])->toBe(0);
        expect($item['percentage'])->toBe(0.0);
    }

    // Verify cable length stats are null/0
    $cableLengthStats = $pageProps['metrics']['cableLengthStats'];
    expect($cableLengthStats['mean'])->toBeNull();
    expect($cableLengthStats['min'])->toBeNull();
    expect($cableLengthStats['max'])->toBeNull();

    // Verify port utilization by status has proper structure
    $portUtilization = $pageProps['metrics']['portUtilization'];
    expect($portUtilization['byStatus'])->toBeArray();
    expect($portUtilization['byType'])->toBeArray();
});
