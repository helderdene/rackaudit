<?php

use App\Models\CapacitySnapshot;
use App\Models\DashboardSnapshot;
use App\Models\Datacenter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('CapacitySnapshot model includes device_count field', function () {
    $datacenter = Datacenter::factory()->create();

    $snapshot = CapacitySnapshot::create([
        'datacenter_id' => $datacenter->id,
        'snapshot_date' => now()->toDateString(),
        'rack_utilization_percent' => 75.50,
        'power_utilization_percent' => 60.25,
        'total_u_space' => 1000,
        'used_u_space' => 755,
        'total_power_capacity' => 50000,
        'total_power_consumption' => 30125,
        'port_stats' => [],
        'device_count' => 150,
    ]);

    expect($snapshot->device_count)->toBe(150);
    expect($snapshot->device_count)->toBeInt();

    // Test nullable behavior for backward compatibility
    $snapshotWithoutDeviceCount = CapacitySnapshot::create([
        'datacenter_id' => $datacenter->id,
        'snapshot_date' => now()->subDay()->toDateString(),
        'rack_utilization_percent' => 70.00,
        'total_u_space' => 500,
        'used_u_space' => 350,
        'port_stats' => [],
        'device_count' => null,
    ]);

    expect($snapshotWithoutDeviceCount->device_count)->toBeNull();
});

test('DashboardSnapshot model can be created with all fields', function () {
    $datacenter = Datacenter::factory()->create();

    $activityByEntity = [
        'Device' => 45,
        'Rack' => 20,
        'Connection' => 30,
        'Audit' => 10,
        'Finding' => 15,
    ];

    $snapshot = DashboardSnapshot::create([
        'datacenter_id' => $datacenter->id,
        'snapshot_date' => now()->toDateString(),
        'open_findings_count' => 25,
        'critical_findings_count' => 3,
        'high_findings_count' => 7,
        'medium_findings_count' => 10,
        'low_findings_count' => 5,
        'pending_audits_count' => 8,
        'completed_audits_count' => 42,
        'activity_count' => 120,
        'activity_by_entity' => $activityByEntity,
    ]);

    expect($snapshot->open_findings_count)->toBe(25);
    expect($snapshot->critical_findings_count)->toBe(3);
    expect($snapshot->high_findings_count)->toBe(7);
    expect($snapshot->medium_findings_count)->toBe(10);
    expect($snapshot->low_findings_count)->toBe(5);
    expect($snapshot->pending_audits_count)->toBe(8);
    expect($snapshot->completed_audits_count)->toBe(42);
    expect($snapshot->activity_count)->toBe(120);
    expect($snapshot->activity_by_entity)->toBeArray();
    expect($snapshot->activity_by_entity['Device'])->toBe(45);
    expect($snapshot->snapshot_date->format('Y-m-d'))->toBe(now()->toDateString());
});

test('DashboardSnapshot belongs to Datacenter relationship', function () {
    $datacenter = Datacenter::factory()->create();

    $snapshot = DashboardSnapshot::factory()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    expect($snapshot->datacenter)->toBeInstanceOf(Datacenter::class);
    expect($snapshot->datacenter->id)->toBe($datacenter->id);
});

test('Datacenter has many DashboardSnapshots relationship', function () {
    $datacenter = Datacenter::factory()->create();

    // Create multiple snapshots for the same datacenter
    DashboardSnapshot::factory()->forDate(now()->subWeek()->toDateString())->create([
        'datacenter_id' => $datacenter->id,
    ]);

    DashboardSnapshot::factory()->forDate(now()->toDateString())->create([
        'datacenter_id' => $datacenter->id,
    ]);

    expect($datacenter->dashboardSnapshots)->toHaveCount(2);
    expect($datacenter->dashboardSnapshots->first())->toBeInstanceOf(DashboardSnapshot::class);
});

test('DashboardSnapshot snapshot_date is unique per datacenter constraint', function () {
    $datacenter = Datacenter::factory()->create();
    $snapshotDate = now()->toDateString();

    // Create first snapshot
    DashboardSnapshot::factory()->create([
        'datacenter_id' => $datacenter->id,
        'snapshot_date' => $snapshotDate,
    ]);

    // Attempting to create a second snapshot with same datacenter and date should fail
    expect(fn () => DashboardSnapshot::factory()->create([
        'datacenter_id' => $datacenter->id,
        'snapshot_date' => $snapshotDate,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('DashboardSnapshot factory generates realistic test data', function () {
    $snapshot = DashboardSnapshot::factory()->create();

    expect($snapshot->datacenter_id)->toBeInt();
    expect($snapshot->open_findings_count)->toBeGreaterThanOrEqual(0);
    expect($snapshot->critical_findings_count)->toBeGreaterThanOrEqual(0);
    expect($snapshot->high_findings_count)->toBeGreaterThanOrEqual(0);
    expect($snapshot->medium_findings_count)->toBeGreaterThanOrEqual(0);
    expect($snapshot->low_findings_count)->toBeGreaterThanOrEqual(0);
    expect($snapshot->pending_audits_count)->toBeGreaterThanOrEqual(0);
    expect($snapshot->completed_audits_count)->toBeGreaterThanOrEqual(0);
    expect($snapshot->activity_count)->toBeGreaterThanOrEqual(0);
    expect($snapshot->activity_by_entity)->toBeArray();
    expect($snapshot->activity_by_entity)->toHaveKeys(['Device', 'Rack', 'Connection', 'Audit', 'Finding']);
});
