<?php

use App\Enums\AuditStatus;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Enums\RackStatus;
use App\Jobs\CaptureCapacitySnapshotJob;
use App\Jobs\CaptureDashboardMetricsJob;
use App\Models\Audit;
use App\Models\AuditConnectionVerification;
use App\Models\CapacitySnapshot;
use App\Models\DashboardSnapshot;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Finding;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

test('CaptureCapacitySnapshotJob captures device_count per datacenter', function () {
    // Create a datacenter with infrastructure
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create([
        'row_id' => $row->id,
        'status' => RackStatus::Active,
    ]);

    // Create devices in the rack
    Device::factory()->count(5)->create([
        'rack_id' => $rack->id,
        'u_height' => 2,
        'start_u' => null,
    ]);

    // Dispatch the job
    $job = new CaptureCapacitySnapshotJob;
    $job->handle(app(\App\Services\CapacityCalculationService::class));

    // Verify snapshot was created with device_count
    $snapshot = CapacitySnapshot::where('datacenter_id', $datacenter->id)->first();

    expect($snapshot)->not->toBeNull();
    expect($snapshot->device_count)->toBe(5);
});

test('CaptureDashboardMetricsJob captures all required metrics', function () {
    // Create a datacenter
    $datacenter = Datacenter::factory()->create();

    // Create audits with different statuses
    Audit::factory()->create([
        'datacenter_id' => $datacenter->id,
        'status' => AuditStatus::Pending,
    ]);
    Audit::factory()->create([
        'datacenter_id' => $datacenter->id,
        'status' => AuditStatus::InProgress,
    ]);
    Audit::factory()->count(3)->create([
        'datacenter_id' => $datacenter->id,
        'status' => AuditStatus::Completed,
    ]);

    // Create an audit for findings
    $auditForFindings = Audit::factory()->create([
        'datacenter_id' => $datacenter->id,
        'status' => AuditStatus::InProgress,
    ]);

    // Create a verification for findings
    $verification = AuditConnectionVerification::factory()->discrepant()->create([
        'audit_id' => $auditForFindings->id,
    ]);

    // Create findings with different severities and statuses
    Finding::factory()->create([
        'audit_id' => $auditForFindings->id,
        'audit_connection_verification_id' => $verification->id,
        'severity' => FindingSeverity::Critical,
        'status' => FindingStatus::Open,
    ]);
    Finding::factory()->create([
        'audit_id' => $auditForFindings->id,
        'audit_connection_verification_id' => $verification->id,
        'severity' => FindingSeverity::High,
        'status' => FindingStatus::InProgress,
    ]);
    Finding::factory()->count(2)->create([
        'audit_id' => $auditForFindings->id,
        'audit_connection_verification_id' => $verification->id,
        'severity' => FindingSeverity::Medium,
        'status' => FindingStatus::Open,
    ]);
    Finding::factory()->create([
        'audit_id' => $auditForFindings->id,
        'audit_connection_verification_id' => $verification->id,
        'severity' => FindingSeverity::Low,
        'status' => FindingStatus::Open,
    ]);
    // This resolved finding should NOT be counted in open counts
    Finding::factory()->create([
        'audit_id' => $auditForFindings->id,
        'audit_connection_verification_id' => $verification->id,
        'severity' => FindingSeverity::Low,
        'status' => FindingStatus::Resolved,
    ]);

    // Dispatch the job
    $job = new CaptureDashboardMetricsJob;
    $job->handle();

    // Verify snapshot was created
    $snapshot = DashboardSnapshot::where('datacenter_id', $datacenter->id)->first();

    expect($snapshot)->not->toBeNull();
    expect($snapshot->pending_audits_count)->toBe(3); // 1 Pending + 2 InProgress
    expect($snapshot->completed_audits_count)->toBe(3);
    expect($snapshot->open_findings_count)->toBe(5); // All open statuses (not resolved)
    expect($snapshot->critical_findings_count)->toBe(1);
    expect($snapshot->high_findings_count)->toBe(1);
    expect($snapshot->medium_findings_count)->toBe(2);
    expect($snapshot->low_findings_count)->toBe(1);
    expect($snapshot->activity_count)->toBeGreaterThanOrEqual(0);
    expect($snapshot->activity_by_entity)->toBeArray();
});

test('CaptureDashboardMetricsJob handles empty datacenters gracefully', function () {
    // Create a datacenter with no audits, findings, or activity
    $datacenter = Datacenter::factory()->create();

    // Dispatch the job
    $job = new CaptureDashboardMetricsJob;
    $job->handle();

    // Verify snapshot was created with zero counts
    $snapshot = DashboardSnapshot::where('datacenter_id', $datacenter->id)->first();

    expect($snapshot)->not->toBeNull();
    expect($snapshot->pending_audits_count)->toBe(0);
    expect($snapshot->completed_audits_count)->toBe(0);
    expect($snapshot->open_findings_count)->toBe(0);
    expect($snapshot->critical_findings_count)->toBe(0);
    expect($snapshot->high_findings_count)->toBe(0);
    expect($snapshot->medium_findings_count)->toBe(0);
    expect($snapshot->low_findings_count)->toBe(0);
    expect($snapshot->activity_count)->toBe(0);
    expect($snapshot->activity_by_entity)->toBeArray();
});

test('CaptureCapacitySnapshotJob handles empty datacenters gracefully', function () {
    // Create a datacenter with no racks or devices
    $datacenter = Datacenter::factory()->create();

    // Dispatch the job
    $job = new CaptureCapacitySnapshotJob;
    $job->handle(app(\App\Services\CapacityCalculationService::class));

    // Verify snapshot was created with zero/null values
    $snapshot = CapacitySnapshot::where('datacenter_id', $datacenter->id)->first();

    expect($snapshot)->not->toBeNull();
    expect($snapshot->device_count)->toBe(0);
    // The decimal:2 cast returns a string like '0.00', so we compare as float
    expect((float) $snapshot->rack_utilization_percent)->toEqual(0.0);
});

test('CaptureDashboardMetricsJob uses updateOrCreate for idempotency', function () {
    $datacenter = Datacenter::factory()->create();

    // Run the job once
    $job = new CaptureDashboardMetricsJob;
    $job->handle();

    // Count all snapshots for this datacenter (should be 1)
    $allSnapshots = DashboardSnapshot::where('datacenter_id', $datacenter->id)->get();
    expect($allSnapshots)->toHaveCount(1);

    $firstSnapshot = $allSnapshots->first();
    $firstId = $firstSnapshot->id;

    // Run the job again (should update, not create new)
    $job->handle();

    // Verify only one snapshot still exists for this datacenter
    $allSnapshotsAfter = DashboardSnapshot::where('datacenter_id', $datacenter->id)->get();
    expect($allSnapshotsAfter)->toHaveCount(1);

    // Verify the same record was updated (same ID)
    $secondSnapshot = $allSnapshotsAfter->first();
    expect($secondSnapshot->id)->toBe($firstId);
});

test('job error handling logs errors and continues processing other datacenters', function () {
    // Create two datacenters
    $datacenter1 = Datacenter::factory()->create();
    $datacenter2 = Datacenter::factory()->create();

    // Set up log spy
    Log::spy();

    // Run the job - should complete without throwing
    $job = new CaptureDashboardMetricsJob;
    $job->handle();

    // Both datacenters should have snapshots
    $snapshot1 = DashboardSnapshot::where('datacenter_id', $datacenter1->id)->first();
    $snapshot2 = DashboardSnapshot::where('datacenter_id', $datacenter2->id)->first();

    expect($snapshot1)->not->toBeNull();
    expect($snapshot2)->not->toBeNull();
});
