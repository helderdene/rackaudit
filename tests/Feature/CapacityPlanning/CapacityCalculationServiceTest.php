<?php

use App\Enums\PortType;
use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Services\CapacityCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('calculates U-space utilization with mixed rack sizes', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    // Create a 42U rack with 10U used
    $rack42 = Rack::factory()->create([
        'row_id' => $row->id,
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
        'power_capacity_watts' => 10000,
    ]);
    Device::factory()->create([
        'rack_id' => $rack42->id,
        'u_height' => 4,
    ]);
    Device::factory()->create([
        'rack_id' => $rack42->id,
        'u_height' => 6,
    ]);

    // Create a 48U rack with 24U used
    $rack48 = Rack::factory()->create([
        'row_id' => $row->id,
        'u_height' => RackUHeight::U48,
        'status' => RackStatus::Active,
        'power_capacity_watts' => 12000,
    ]);
    Device::factory()->create([
        'rack_id' => $rack48->id,
        'u_height' => 12,
    ]);
    Device::factory()->create([
        'rack_id' => $rack48->id,
        'u_height' => 12,
    ]);

    $service = app(CapacityCalculationService::class);

    $query = Rack::query()
        ->where('status', RackStatus::Active)
        ->whereHas('row.room.datacenter', fn ($q) => $q->where('datacenters.id', $datacenter->id));

    $result = $service->calculateUSpaceUtilization($query);

    // Total: 42 + 48 = 90U, Used: 10 + 24 = 34U
    expect($result['total_u_space'])->toBe(90);
    expect($result['used_u_space'])->toBe(34);
    expect($result['available_u_space'])->toBe(56);
    expect($result['utilization_percent'])->toBe(round((34 / 90) * 100, 1));
});

test('calculates power utilization excluding null values', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    // Rack with power capacity and devices with power draw
    $rack1 = Rack::factory()->create([
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'power_capacity_watts' => 10000,
    ]);
    Device::factory()->create([
        'rack_id' => $rack1->id,
        'power_draw_watts' => 500,
    ]);
    Device::factory()->create([
        'rack_id' => $rack1->id,
        'power_draw_watts' => 300,
    ]);

    // Rack with power capacity and one device with null power draw
    $rack2 = Rack::factory()->create([
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'power_capacity_watts' => 8000,
    ]);
    Device::factory()->create([
        'rack_id' => $rack2->id,
        'power_draw_watts' => 400,
    ]);
    Device::factory()->create([
        'rack_id' => $rack2->id,
        'power_draw_watts' => null, // Should be excluded
    ]);

    // Rack with null power capacity (should be excluded from total capacity)
    $rack3 = Rack::factory()->create([
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'power_capacity_watts' => null,
    ]);
    Device::factory()->create([
        'rack_id' => $rack3->id,
        'power_draw_watts' => 200,
    ]);

    $service = app(CapacityCalculationService::class);

    $query = Rack::query()
        ->where('status', RackStatus::Active)
        ->whereHas('row.room.datacenter', fn ($q) => $q->where('datacenters.id', $datacenter->id));

    $result = $service->calculatePowerUtilization($query);

    // Total capacity: 10000 + 8000 = 18000 (rack3 excluded due to null)
    // Total consumption: 500 + 300 + 400 + 200 = 1400 (null device excluded)
    expect($result['total_capacity'])->toBe(18000);
    expect($result['total_consumption'])->toBe(1400);
    expect($result['power_headroom'])->toBe(16600);
    expect($result['utilization_percent'])->toBe(round((1400 / 18000) * 100, 1));
});

test('groups port capacity by PortType enum', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create([
        'row_id' => $row->id,
        'status' => RackStatus::Active,
    ]);

    $device = Device::factory()->create(['rack_id' => $rack->id]);

    // Create Ethernet ports (5 total, 2 connected)
    $ethernetPorts = Port::factory()
        ->count(5)
        ->ethernet()
        ->create(['device_id' => $device->id]);

    // Create connections for 2 ethernet ports
    Connection::factory()->create([
        'source_port_id' => $ethernetPorts[0]->id,
        'destination_port_id' => Port::factory()->ethernet()->create()->id,
    ]);
    Connection::factory()->create([
        'source_port_id' => $ethernetPorts[1]->id,
        'destination_port_id' => Port::factory()->ethernet()->create()->id,
    ]);

    // Create Fiber ports (3 total, 1 connected)
    $fiberPorts = Port::factory()
        ->count(3)
        ->fiber()
        ->create(['device_id' => $device->id]);

    Connection::factory()->create([
        'source_port_id' => $fiberPorts[0]->id,
        'destination_port_id' => Port::factory()->fiber()->create()->id,
    ]);

    // Create Power ports (2 total, 0 connected)
    Port::factory()
        ->count(2)
        ->power()
        ->create(['device_id' => $device->id]);

    $service = app(CapacityCalculationService::class);

    $query = Rack::query()
        ->where('status', RackStatus::Active)
        ->whereHas('row.room.datacenter', fn ($q) => $q->where('datacenters.id', $datacenter->id));

    $result = $service->calculatePortCapacity($query);

    // Verify Ethernet ports
    expect($result[PortType::Ethernet->value]['total_ports'])->toBe(5);
    expect($result[PortType::Ethernet->value]['connected_ports'])->toBe(2);
    expect($result[PortType::Ethernet->value]['available_ports'])->toBe(3);
    expect($result[PortType::Ethernet->value]['label'])->toBe('Ethernet');

    // Verify Fiber ports
    expect($result[PortType::Fiber->value]['total_ports'])->toBe(3);
    expect($result[PortType::Fiber->value]['connected_ports'])->toBe(1);
    expect($result[PortType::Fiber->value]['available_ports'])->toBe(2);
    expect($result[PortType::Fiber->value]['label'])->toBe('Fiber');

    // Verify Power ports
    expect($result[PortType::Power->value]['total_ports'])->toBe(2);
    expect($result[PortType::Power->value]['connected_ports'])->toBe(0);
    expect($result[PortType::Power->value]['available_ports'])->toBe(2);
    expect($result[PortType::Power->value]['label'])->toBe('Power');
});

test('aggregates metrics by datacenter, room, and row filters', function () {
    $datacenter1 = Datacenter::factory()->create();
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter1->id]);
    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $row2 = Row::factory()->create(['room_id' => $room1->id]);

    // Create racks in different rows
    $rack1 = Rack::factory()->create([
        'row_id' => $row1->id,
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
        'power_capacity_watts' => 10000,
    ]);
    Device::factory()->create(['rack_id' => $rack1->id, 'u_height' => 10]);

    $rack2 = Rack::factory()->create([
        'row_id' => $row2->id,
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
        'power_capacity_watts' => 10000,
    ]);
    Device::factory()->create(['rack_id' => $rack2->id, 'u_height' => 20]);

    // Second datacenter (should be excluded when filtering)
    $datacenter2 = Datacenter::factory()->create();
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter2->id]);
    $row3 = Row::factory()->create(['room_id' => $room2->id]);
    $rack3 = Rack::factory()->create([
        'row_id' => $row3->id,
        'u_height' => RackUHeight::U48,
        'status' => RackStatus::Active,
    ]);
    Device::factory()->create(['rack_id' => $rack3->id, 'u_height' => 30]);

    $service = app(CapacityCalculationService::class);

    // Filter by datacenter1 only
    $metrics = $service->getCapacityMetrics($datacenter1->id, null, null);
    expect($metrics['u_space']['total_u_space'])->toBe(84); // 42 + 42
    expect($metrics['u_space']['used_u_space'])->toBe(30); // 10 + 20

    // Filter by specific row
    $metricsRow1 = $service->getCapacityMetrics($datacenter1->id, $room1->id, $row1->id);
    expect($metricsRow1['u_space']['total_u_space'])->toBe(42);
    expect($metricsRow1['u_space']['used_u_space'])->toBe(10);
});

test('handles empty datasets gracefully', function () {
    $datacenter = Datacenter::factory()->create();

    $service = app(CapacityCalculationService::class);

    // Test with empty datacenter (no racks)
    $metrics = $service->getCapacityMetrics($datacenter->id, null, null);

    // U-space should return zeros
    expect($metrics['u_space']['total_u_space'])->toBe(0);
    expect($metrics['u_space']['used_u_space'])->toBe(0);
    expect($metrics['u_space']['available_u_space'])->toBe(0);
    expect($metrics['u_space']['utilization_percent'])->toBe(0.0);

    // Power should return null or zeros
    expect($metrics['power']['total_capacity'])->toBe(0);
    expect($metrics['power']['total_consumption'])->toBe(0);
    expect($metrics['power']['utilization_percent'])->toBeNull();

    // Port capacity should be empty or have zero counts
    foreach (PortType::cases() as $portType) {
        expect($metrics['port_capacity'][$portType->value]['total_ports'])->toBe(0);
        expect($metrics['port_capacity'][$portType->value]['connected_ports'])->toBe(0);
        expect($metrics['port_capacity'][$portType->value]['available_ports'])->toBe(0);
    }

    // Racks approaching capacity should be empty
    expect($metrics['racks_approaching_capacity'])->toBeEmpty();
});

test('classifies racks by warning and critical thresholds', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    // Rack at 75% utilization (below warning threshold)
    $rackNormal = Rack::factory()->create([
        'row_id' => $row->id,
        'u_height' => RackUHeight::U48,
        'status' => RackStatus::Active,
    ]);
    // 48U rack with 36U used = 75%
    Device::factory()->create(['rack_id' => $rackNormal->id, 'u_height' => 36]);

    // Rack at 85% utilization (warning threshold: 80-89%)
    $rackWarning = Rack::factory()->create([
        'row_id' => $row->id,
        'u_height' => RackUHeight::U48,
        'status' => RackStatus::Active,
    ]);
    // 48U rack with ~41U used = ~85%
    Device::factory()->create(['rack_id' => $rackWarning->id, 'u_height' => 41]);

    // Rack at 95% utilization (critical threshold: 90%+)
    $rackCritical = Rack::factory()->create([
        'row_id' => $row->id,
        'u_height' => RackUHeight::U48,
        'status' => RackStatus::Active,
    ]);
    // 48U rack with ~46U used = ~96%
    Device::factory()->create(['rack_id' => $rackCritical->id, 'u_height' => 46]);

    $service = app(CapacityCalculationService::class);

    $query = Rack::query()
        ->where('status', RackStatus::Active)
        ->whereHas('row.room.datacenter', fn ($q) => $q->where('datacenters.id', $datacenter->id));

    $result = $service->getRacksApproachingCapacity($query, 80);

    // Should only include racks at or above 80%
    expect($result)->toHaveCount(2);

    // Verify the warning rack
    $warningRack = $result->firstWhere('id', $rackWarning->id);
    expect($warningRack)->not->toBeNull();
    expect($warningRack['status'])->toBe('warning');
    expect($warningRack['utilization_percent'])->toBeGreaterThanOrEqual(80);
    expect($warningRack['utilization_percent'])->toBeLessThan(90);

    // Verify the critical rack
    $criticalRack = $result->firstWhere('id', $rackCritical->id);
    expect($criticalRack)->not->toBeNull();
    expect($criticalRack['status'])->toBe('critical');
    expect($criticalRack['utilization_percent'])->toBeGreaterThanOrEqual(90);
});
