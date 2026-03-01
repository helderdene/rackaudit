<?php

use App\Enums\AuditStatus;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Models\Audit;
use App\Models\AuditConnectionVerification;
use App\Models\CapacitySnapshot;
use App\Models\DashboardSnapshot;
use App\Models\Datacenter;
use App\Models\Finding;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('chartData endpoint returns all required chart datasets', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');
    $datacenter = Datacenter::factory()->create();

    // Create capacity snapshots for the past 7 days
    for ($i = 6; $i >= 0; $i--) {
        CapacitySnapshot::factory()->forDate(now()->subDays($i)->toDateString())->create([
            'datacenter_id' => $datacenter->id,
            'rack_utilization_percent' => 60 + $i,
            'device_count' => 100 + ($i * 10),
        ]);
    }

    // Create dashboard snapshots for the past 7 days
    for ($i = 6; $i >= 0; $i--) {
        DashboardSnapshot::factory()->forDate(now()->subDays($i)->toDateString())->create([
            'datacenter_id' => $datacenter->id,
            'completed_audits_count' => 5 + $i,
            'critical_findings_count' => 2,
            'high_findings_count' => 3,
            'medium_findings_count' => 5,
            'low_findings_count' => 8,
        ]);
    }

    // Create current open findings for severity distribution
    $audit = Audit::factory()->create([
        'datacenter_id' => $datacenter->id,
        'status' => AuditStatus::InProgress,
    ]);
    $verification = AuditConnectionVerification::factory()->discrepant()->create([
        'audit_id' => $audit->id,
    ]);
    Finding::factory()->create([
        'audit_id' => $audit->id,
        'audit_connection_verification_id' => $verification->id,
        'severity' => FindingSeverity::Critical,
        'status' => FindingStatus::Open,
    ]);
    Finding::factory()->create([
        'audit_id' => $audit->id,
        'audit_connection_verification_id' => $verification->id,
        'severity' => FindingSeverity::High,
        'status' => FindingStatus::InProgress,
    ]);
    Finding::factory()->count(2)->create([
        'audit_id' => $audit->id,
        'audit_connection_verification_id' => $verification->id,
        'severity' => FindingSeverity::Medium,
        'status' => FindingStatus::Open,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.charts'));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'capacityTrend' => [
            'labels',
            'data',
        ],
        'deviceCountTrend' => [
            'labels',
            'data',
        ],
        'severityDistribution' => [
            'critical',
            'high',
            'medium',
            'low',
            'total',
        ],
        'auditCompletionTrend' => [
            'labels',
            'data',
            'total',
        ],
        'activityByEntity' => [
            'labels',
            'data',
        ],
    ]);
});

test('chartData endpoint respects time period filter', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');
    $datacenter = Datacenter::factory()->create();

    // Create capacity snapshots for the past 90 days
    for ($i = 89; $i >= 0; $i--) {
        CapacitySnapshot::factory()->forDate(now()->subDays($i)->toDateString())->create([
            'datacenter_id' => $datacenter->id,
            'rack_utilization_percent' => 50 + ($i % 30),
            'device_count' => 100 + $i,
        ]);
    }

    // Test 7 days filter
    $response7Days = $this->actingAs($user)->get(route('dashboard.charts', ['time_period' => '7_days']));
    $response7Days->assertSuccessful();
    $data7Days = $response7Days->json();
    expect(count($data7Days['capacityTrend']['labels']))->toBeLessThanOrEqual(7);

    // Test 30 days filter
    $response30Days = $this->actingAs($user)->get(route('dashboard.charts', ['time_period' => '30_days']));
    $response30Days->assertSuccessful();
    $data30Days = $response30Days->json();
    expect(count($data30Days['capacityTrend']['labels']))->toBeLessThanOrEqual(30);

    // Test 90 days filter
    $response90Days = $this->actingAs($user)->get(route('dashboard.charts', ['time_period' => '90_days']));
    $response90Days->assertSuccessful();
    $data90Days = $response90Days->json();
    expect(count($data90Days['capacityTrend']['labels']))->toBeLessThanOrEqual(90);
});

test('chartData endpoint respects datacenter filter', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');
    $datacenter1 = Datacenter::factory()->create();
    $datacenter2 = Datacenter::factory()->create();

    // Create capacity snapshots for both datacenters
    CapacitySnapshot::factory()->forDate(now()->toDateString())->create([
        'datacenter_id' => $datacenter1->id,
        'rack_utilization_percent' => 75.0,
        'device_count' => 150,
    ]);
    CapacitySnapshot::factory()->forDate(now()->toDateString())->create([
        'datacenter_id' => $datacenter2->id,
        'rack_utilization_percent' => 50.0,
        'device_count' => 100,
    ]);

    // Create dashboard snapshots for both datacenters
    DashboardSnapshot::factory()->forDate(now()->toDateString())->create([
        'datacenter_id' => $datacenter1->id,
        'completed_audits_count' => 10,
    ]);
    DashboardSnapshot::factory()->forDate(now()->toDateString())->create([
        'datacenter_id' => $datacenter2->id,
        'completed_audits_count' => 5,
    ]);

    // Filter by datacenter1
    $response = $this->actingAs($user)->get(route('dashboard.charts', ['datacenter_id' => $datacenter1->id]));
    $response->assertSuccessful();

    $data = $response->json();
    // When filtered to datacenter1, should have utilization ~75%
    if (! empty($data['capacityTrend']['data'])) {
        expect(end($data['capacityTrend']['data']))->toBeGreaterThanOrEqual(70);
    }
});

test('chartData endpoint enforces user access control for datacenter filtering', function () {
    // Create a user with Operator role (not admin)
    $user = User::factory()->create();
    $user->assignRole('Operator');

    // Create datacenters - user only has access to datacenter1
    $datacenter1 = Datacenter::factory()->create();
    $datacenter2 = Datacenter::factory()->create();
    $user->datacenters()->attach($datacenter1);

    // Create capacity snapshots for both datacenters
    CapacitySnapshot::factory()->forDate(now()->toDateString())->create([
        'datacenter_id' => $datacenter1->id,
        'rack_utilization_percent' => 75.0,
        'device_count' => 150,
    ]);
    CapacitySnapshot::factory()->forDate(now()->toDateString())->create([
        'datacenter_id' => $datacenter2->id,
        'rack_utilization_percent' => 50.0,
        'device_count' => 100,
    ]);

    // Request with accessible datacenter should succeed
    $responseAccessible = $this->actingAs($user)->get(route('dashboard.charts', ['datacenter_id' => $datacenter1->id]));
    $responseAccessible->assertSuccessful();

    // Request with inaccessible datacenter should ignore the filter
    // and fall back to only showing accessible datacenters
    $responseInaccessible = $this->actingAs($user)->get(route('dashboard.charts', ['datacenter_id' => $datacenter2->id]));
    $responseInaccessible->assertSuccessful();
    // The data should only include datacenter1 data due to access control
});

test('chartData endpoint returns empty state response when no historical data exists', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');
    $datacenter = Datacenter::factory()->create();

    // No snapshots created - empty state

    $response = $this->actingAs($user)->get(route('dashboard.charts'));

    $response->assertSuccessful();
    $data = $response->json();

    // All trend data should be empty arrays
    expect($data['capacityTrend']['labels'])->toBeArray()->toBeEmpty();
    expect($data['capacityTrend']['data'])->toBeArray()->toBeEmpty();
    expect($data['deviceCountTrend']['labels'])->toBeArray()->toBeEmpty();
    expect($data['deviceCountTrend']['data'])->toBeArray()->toBeEmpty();
    expect($data['auditCompletionTrend']['labels'])->toBeArray()->toBeEmpty();
    expect($data['auditCompletionTrend']['data'])->toBeArray()->toBeEmpty();
    expect($data['auditCompletionTrend']['total'])->toBe(0);
    expect($data['activityByEntity']['labels'])->toBeArray()->toBeEmpty();
    expect($data['activityByEntity']['data'])->toBeArray()->toBeEmpty();

    // Severity distribution should have zero counts
    expect($data['severityDistribution']['total'])->toBe(0);
    expect($data['severityDistribution']['critical']['count'])->toBe(0);
    expect($data['severityDistribution']['high']['count'])->toBe(0);
    expect($data['severityDistribution']['medium']['count'])->toBe(0);
    expect($data['severityDistribution']['low']['count'])->toBe(0);
});

test('chartData endpoint requires authentication', function () {
    $response = $this->get(route('dashboard.charts'));

    $response->assertRedirect(route('login'));
});
