<?php

use App\Models\CapacitySnapshot;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Rack;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Device power_draw_watts field is cast to integer and accepts null', function () {
    $device = Device::factory()->create([
        'power_draw_watts' => 500,
    ]);

    expect($device->power_draw_watts)->toBe(500);
    expect($device->power_draw_watts)->toBeInt();

    // Test nullable behavior
    $deviceWithNull = Device::factory()->create([
        'power_draw_watts' => null,
    ]);

    expect($deviceWithNull->power_draw_watts)->toBeNull();
});

test('Rack power_capacity_watts field is cast to integer and accepts null', function () {
    $rack = Rack::factory()->create([
        'power_capacity_watts' => 10000,
    ]);

    expect($rack->power_capacity_watts)->toBe(10000);
    expect($rack->power_capacity_watts)->toBeInt();

    // Test nullable behavior
    $rackWithNull = Rack::factory()->create([
        'power_capacity_watts' => null,
    ]);

    expect($rackWithNull->power_capacity_watts)->toBeNull();
});

test('CapacitySnapshot can be created with JSON port_stats', function () {
    $datacenter = Datacenter::factory()->create();

    $portStats = [
        'ethernet' => ['total' => 100, 'connected' => 75, 'available' => 25],
        'fiber' => ['total' => 50, 'connected' => 30, 'available' => 20],
        'power' => ['total' => 40, 'connected' => 35, 'available' => 5],
    ];

    $snapshot = CapacitySnapshot::create([
        'datacenter_id' => $datacenter->id,
        'snapshot_date' => now()->toDateString(),
        'rack_utilization_percent' => 75.50,
        'power_utilization_percent' => 60.25,
        'total_u_space' => 1000,
        'used_u_space' => 755,
        'total_power_capacity' => 50000,
        'total_power_consumption' => 30125,
        'port_stats' => $portStats,
    ]);

    expect($snapshot->port_stats)->toBeArray();
    expect($snapshot->port_stats['ethernet']['total'])->toBe(100);
    expect($snapshot->port_stats['fiber']['connected'])->toBe(30);
    expect($snapshot->port_stats['power']['available'])->toBe(5);
});

test('CapacitySnapshot belongs to Datacenter relationship', function () {
    $datacenter = Datacenter::factory()->create();

    $snapshot = CapacitySnapshot::create([
        'datacenter_id' => $datacenter->id,
        'snapshot_date' => now()->toDateString(),
        'rack_utilization_percent' => 80.00,
        'total_u_space' => 500,
        'used_u_space' => 400,
        'port_stats' => [],
    ]);

    expect($snapshot->datacenter)->toBeInstanceOf(Datacenter::class);
    expect($snapshot->datacenter->id)->toBe($datacenter->id);
});

test('snapshot_date is unique per datacenter constraint', function () {
    $datacenter = Datacenter::factory()->create();
    $snapshotDate = now()->toDateString();

    // Create first snapshot
    CapacitySnapshot::create([
        'datacenter_id' => $datacenter->id,
        'snapshot_date' => $snapshotDate,
        'rack_utilization_percent' => 75.00,
        'total_u_space' => 500,
        'used_u_space' => 375,
        'port_stats' => [],
    ]);

    // Attempting to create a second snapshot with same datacenter and date should fail
    expect(fn () => CapacitySnapshot::create([
        'datacenter_id' => $datacenter->id,
        'snapshot_date' => $snapshotDate,
        'rack_utilization_percent' => 80.00,
        'total_u_space' => 500,
        'used_u_space' => 400,
        'port_stats' => [],
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('Datacenter has many CapacitySnapshots relationship', function () {
    $datacenter = Datacenter::factory()->create();

    // Create multiple snapshots for the same datacenter
    CapacitySnapshot::create([
        'datacenter_id' => $datacenter->id,
        'snapshot_date' => now()->subWeek()->toDateString(),
        'rack_utilization_percent' => 70.00,
        'total_u_space' => 500,
        'used_u_space' => 350,
        'port_stats' => [],
    ]);

    CapacitySnapshot::create([
        'datacenter_id' => $datacenter->id,
        'snapshot_date' => now()->toDateString(),
        'rack_utilization_percent' => 75.00,
        'total_u_space' => 500,
        'used_u_space' => 375,
        'port_stats' => [],
    ]);

    expect($datacenter->capacitySnapshots)->toHaveCount(2);
    expect($datacenter->capacitySnapshots->first())->toBeInstanceOf(CapacitySnapshot::class);
});
