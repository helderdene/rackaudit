<?php

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
use App\Services\ConnectionCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('buildFilteredConnectionQuery filters connections by datacenter', function () {
    $service = app(ConnectionCalculationService::class);

    // Create hierarchy for datacenter 1
    $datacenter1 = Datacenter::factory()->create();
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter1->id]);
    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $rack1 = Rack::factory()->create(['row_id' => $row1->id]);
    $device1 = Device::factory()->create(['rack_id' => $rack1->id]);

    // Create hierarchy for datacenter 2
    $datacenter2 = Datacenter::factory()->create();
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter2->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);
    $rack2 = Rack::factory()->create(['row_id' => $row2->id]);
    $device2 = Device::factory()->create(['rack_id' => $rack2->id]);

    // Create connections in datacenter 1
    $sourcePort1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    $destPort1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    Connection::factory()->create([
        'source_port_id' => $sourcePort1->id,
        'destination_port_id' => $destPort1->id,
    ]);

    // Create connections in datacenter 2
    $sourcePort2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);
    $destPort2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);
    Connection::factory()->count(2)->create([
        'source_port_id' => $sourcePort2->id,
        'destination_port_id' => $destPort2->id,
    ]);

    // Filter by datacenter 1 - should return 1 connection
    $query = $service->buildFilteredConnectionQuery($datacenter1->id);
    expect($query->count())->toBe(1);

    // Filter by datacenter 2 - should return 2 connections
    $query = $service->buildFilteredConnectionQuery($datacenter2->id);
    expect($query->count())->toBe(2);
});

test('buildFilteredConnectionQuery filters connections by room', function () {
    $service = app(ConnectionCalculationService::class);

    // Create hierarchy with 2 rooms in same datacenter
    $datacenter = Datacenter::factory()->create();
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $rack1 = Rack::factory()->create(['row_id' => $row1->id]);
    $device1 = Device::factory()->create(['rack_id' => $rack1->id]);

    $room2 = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);
    $rack2 = Rack::factory()->create(['row_id' => $row2->id]);
    $device2 = Device::factory()->create(['rack_id' => $rack2->id]);

    // Create 2 connections in room 1
    $sourcePort1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    $destPort1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    Connection::factory()->count(2)->create([
        'source_port_id' => $sourcePort1->id,
        'destination_port_id' => $destPort1->id,
    ]);

    // Create 3 connections in room 2
    $sourcePort2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);
    $destPort2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);
    Connection::factory()->count(3)->create([
        'source_port_id' => $sourcePort2->id,
        'destination_port_id' => $destPort2->id,
    ]);

    // Filter by room 1 - should return 2 connections
    $query = $service->buildFilteredConnectionQuery($datacenter->id, $room1->id);
    expect($query->count())->toBe(2);

    // Filter by room 2 - should return 3 connections
    $query = $service->buildFilteredConnectionQuery($datacenter->id, $room2->id);
    expect($query->count())->toBe(3);
});

test('getConnectionMetrics returns correct structure with all metrics', function () {
    $service = app(ConnectionCalculationService::class);

    // Create a datacenter hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    // Create some connections
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    Connection::factory()->count(3)->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_length' => 5.0,
    ]);

    $metrics = $service->getConnectionMetrics($datacenter->id);

    // Verify structure contains all expected keys
    expect($metrics)->toHaveKeys([
        'totalConnections',
        'cableTypeDistribution',
        'portTypeDistribution',
        'cableLengthStats',
        'portUtilization',
    ]);

    expect($metrics['totalConnections'])->toBe(3);
    expect($metrics['cableTypeDistribution'])->toBeArray();
    expect($metrics['portTypeDistribution'])->toBeArray();
    expect($metrics['cableLengthStats'])->toBeArray();
    expect($metrics['portUtilization'])->toBeArray();
});

test('getCableTypeDistribution groups connections by cable type correctly', function () {
    $service = app(ConnectionCalculationService::class);

    // Create a datacenter hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    // Create Ethernet ports for Cat6 connections
    $ethSourcePort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    $ethDestPort = Port::factory()->ethernet()->create(['device_id' => $device->id]);

    // Create Fiber ports for Fiber SM connections
    $fiberSourcePort = Port::factory()->fiber()->create(['device_id' => $device->id]);
    $fiberDestPort = Port::factory()->fiber()->create(['device_id' => $device->id]);

    // Create Power ports for C13 connections
    $powerSourcePort = Port::factory()->power()->create(['device_id' => $device->id]);
    $powerDestPort = Port::factory()->power()->create(['device_id' => $device->id]);

    // Create 3 Cat6 connections
    Connection::factory()->count(3)->create([
        'source_port_id' => $ethSourcePort->id,
        'destination_port_id' => $ethDestPort->id,
        'cable_type' => CableType::Cat6,
    ]);

    // Create 2 Fiber SM connections
    Connection::factory()->count(2)->create([
        'source_port_id' => $fiberSourcePort->id,
        'destination_port_id' => $fiberDestPort->id,
        'cable_type' => CableType::FiberSm,
    ]);

    // Create 1 Power C13 connection
    Connection::factory()->create([
        'source_port_id' => $powerSourcePort->id,
        'destination_port_id' => $powerDestPort->id,
        'cable_type' => CableType::PowerC13,
    ]);

    $query = $service->buildFilteredConnectionQuery($datacenter->id);
    $distribution = $service->getCableTypeDistribution($query);

    // Find counts by cable type value
    $countsByCableType = collect($distribution)->pluck('count', 'type')->toArray();

    expect($countsByCableType[CableType::Cat6->value])->toBe(3);
    expect($countsByCableType[CableType::FiberSm->value])->toBe(2);
    expect($countsByCableType[CableType::PowerC13->value])->toBe(1);

    // Verify percentages are calculated correctly (total 6 connections)
    $percentagesByCableType = collect($distribution)->pluck('percentage', 'type')->toArray();
    expect($percentagesByCableType[CableType::Cat6->value])->toBe(50.0);
    // 2/6 = 0.333... rounds to 33.3%
    expect($percentagesByCableType[CableType::FiberSm->value])->toBe(33.3);
    // 1/6 = 0.166... rounds to 16.7%
    expect($percentagesByCableType[CableType::PowerC13->value])->toBe(16.7);
});

test('getPortUtilizationMetrics calculates percentages correctly', function () {
    $service = app(ConnectionCalculationService::class);

    // Create a datacenter hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    // Create 10 Ethernet ports - 4 connected, 3 available, 2 reserved, 1 disabled
    Port::factory()->count(4)->ethernet()->connected()->create(['device_id' => $device->id]);
    Port::factory()->count(3)->ethernet()->available()->create(['device_id' => $device->id]);
    Port::factory()->count(2)->ethernet()->reserved()->create(['device_id' => $device->id]);
    Port::factory()->count(1)->ethernet()->disabled()->create(['device_id' => $device->id]);

    // Create 5 Fiber ports - 2 connected, 3 available
    Port::factory()->count(2)->fiber()->connected()->create(['device_id' => $device->id]);
    Port::factory()->count(3)->fiber()->available()->create(['device_id' => $device->id]);

    $query = $service->buildFilteredPortQuery($datacenter->id);
    $utilization = $service->getPortUtilizationMetrics($query);

    // Verify structure
    expect($utilization)->toHaveKeys(['byType', 'byStatus', 'overall']);

    // Check Ethernet utilization (4 connected out of 10 = 40%)
    $ethernetUtil = collect($utilization['byType'])->firstWhere('type', PortType::Ethernet->value);
    expect($ethernetUtil['total'])->toBe(10);
    expect($ethernetUtil['connected'])->toBe(4);
    expect($ethernetUtil['percentage'])->toBe(40.0);

    // Check Fiber utilization (2 connected out of 5 = 40%)
    $fiberUtil = collect($utilization['byType'])->firstWhere('type', PortType::Fiber->value);
    expect($fiberUtil['total'])->toBe(5);
    expect($fiberUtil['connected'])->toBe(2);
    expect($fiberUtil['percentage'])->toBe(40.0);

    // Check overall stats by status
    $statusCounts = collect($utilization['byStatus'])->pluck('count', 'status')->toArray();
    expect($statusCounts[PortStatus::Connected->value])->toBe(6);
    expect($statusCounts[PortStatus::Available->value])->toBe(6);
    expect($statusCounts[PortStatus::Reserved->value])->toBe(2);
    expect($statusCounts[PortStatus::Disabled->value])->toBe(1);
});

test('getCableLengthStatistics handles null cable_length values gracefully', function () {
    $service = app(ConnectionCalculationService::class);

    // Create a datacenter hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $device->id]);

    // Create connections with various cable lengths including null
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_length' => 5.0,
    ]);
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_length' => 10.0,
    ]);
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_length' => 15.0,
    ]);
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_length' => null, // Null value
    ]);

    $query = $service->buildFilteredConnectionQuery($datacenter->id);
    $stats = $service->getCableLengthStatistics($query);

    // Stats should only include non-null values
    expect($stats)->toHaveKeys(['mean', 'min', 'max', 'count']);
    expect($stats['count'])->toBe(3); // Only 3 connections have cable_length
    expect($stats['min'])->toBe(5.0);
    expect($stats['max'])->toBe(15.0);
    expect($stats['mean'])->toBe(10.0); // (5 + 10 + 15) / 3 = 10

    // Test with all null values - should handle edge case
    Connection::query()->update(['cable_length' => null]);

    $stats = $service->getCableLengthStatistics($query);
    expect($stats['count'])->toBe(0);
    expect($stats['min'])->toBeNull();
    expect($stats['max'])->toBeNull();
    expect($stats['mean'])->toBeNull();
});
