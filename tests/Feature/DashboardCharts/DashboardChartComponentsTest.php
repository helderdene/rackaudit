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

test('dashboard page renders chart data via deferred props', function () {
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
            'activity_by_entity' => [
                'Device' => 10 + $i,
                'Rack' => 5 + $i,
                'Connection' => 15 + $i,
            ],
        ]);
    }

    // Visit the dashboard page
    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('metrics')
        ->has('datacenterOptions')
        ->has('filters')
    );
});

test('capacity trend chart data is correctly formatted for chart rendering', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');
    $datacenter = Datacenter::factory()->create();

    // Create capacity snapshots with predictable values
    $dates = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = now()->subDays($i)->toDateString();
        $dates[] = $date;
        CapacitySnapshot::factory()->forDate($date)->create([
            'datacenter_id' => $datacenter->id,
            'rack_utilization_percent' => 50 + ($i * 5),
            'device_count' => 100 + ($i * 10),
        ]);
    }

    $response = $this->actingAs($user)->get(route('dashboard.charts', ['time_period' => '7_days']));

    $response->assertSuccessful();
    $data = $response->json('capacityTrend');

    expect($data['labels'])->toBeArray();
    expect($data['data'])->toBeArray();
    expect(count($data['labels']))->toBe(count($data['data']));
    expect(count($data['labels']))->toBeGreaterThanOrEqual(1);

    // Verify data values are numeric percentages
    foreach ($data['data'] as $value) {
        expect($value)->toBeNumeric();
        expect($value)->toBeGreaterThanOrEqual(0);
        expect($value)->toBeLessThanOrEqual(100);
    }
});

test('device count trend chart data returns integer counts', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');
    $datacenter = Datacenter::factory()->create();

    // Create capacity snapshots with device counts
    for ($i = 6; $i >= 0; $i--) {
        CapacitySnapshot::factory()->forDate(now()->subDays($i)->toDateString())->create([
            'datacenter_id' => $datacenter->id,
            'device_count' => 100 + ($i * 10),
        ]);
    }

    $response = $this->actingAs($user)->get(route('dashboard.charts', ['time_period' => '7_days']));

    $response->assertSuccessful();
    $data = $response->json('deviceCountTrend');

    expect($data['labels'])->toBeArray();
    expect($data['data'])->toBeArray();
    expect(count($data['labels']))->toBe(count($data['data']));

    // Verify data values are non-negative integers
    foreach ($data['data'] as $value) {
        expect($value)->toBeInt();
        expect($value)->toBeGreaterThanOrEqual(0);
    }
});

test('charts display empty state when no historical data exists', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');
    Datacenter::factory()->create();

    // No snapshots created - should return empty arrays

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
});

test('time period filter changes return different data ranges', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');
    $datacenter = Datacenter::factory()->create();

    // Create snapshots for 45 days to test 7, 30, and 90 day filters
    for ($i = 44; $i >= 0; $i--) {
        CapacitySnapshot::factory()->forDate(now()->subDays($i)->toDateString())->create([
            'datacenter_id' => $datacenter->id,
            'rack_utilization_percent' => 50 + ($i % 20),
            'device_count' => 100 + $i,
        ]);
    }

    // Test 7 days
    $response7 = $this->actingAs($user)->get(route('dashboard.charts', ['time_period' => '7_days']));
    $data7 = $response7->json('capacityTrend');
    expect(count($data7['labels']))->toBeLessThanOrEqual(7);

    // Test 30 days
    $response30 = $this->actingAs($user)->get(route('dashboard.charts', ['time_period' => '30_days']));
    $data30 = $response30->json('capacityTrend');
    expect(count($data30['labels']))->toBeLessThanOrEqual(30);

    // 30 days should have more or equal data points than 7 days
    expect(count($data30['labels']))->toBeGreaterThanOrEqual(count($data7['labels']));
});

test('severity distribution data includes all severity levels with correct structure', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');
    $datacenter = Datacenter::factory()->create();

    // Create audit with findings
    $audit = Audit::factory()->create([
        'datacenter_id' => $datacenter->id,
        'status' => AuditStatus::InProgress,
    ]);

    $verification = AuditConnectionVerification::factory()->discrepant()->create([
        'audit_id' => $audit->id,
    ]);

    // Create findings of different severities
    Finding::factory()->count(2)->create([
        'audit_id' => $audit->id,
        'audit_connection_verification_id' => $verification->id,
        'severity' => FindingSeverity::Critical,
        'status' => FindingStatus::Open,
    ]);
    Finding::factory()->count(3)->create([
        'audit_id' => $audit->id,
        'audit_connection_verification_id' => $verification->id,
        'severity' => FindingSeverity::High,
        'status' => FindingStatus::Open,
    ]);
    Finding::factory()->count(4)->create([
        'audit_id' => $audit->id,
        'audit_connection_verification_id' => $verification->id,
        'severity' => FindingSeverity::Medium,
        'status' => FindingStatus::InProgress,
    ]);
    Finding::factory()->count(1)->create([
        'audit_id' => $audit->id,
        'audit_connection_verification_id' => $verification->id,
        'severity' => FindingSeverity::Low,
        'status' => FindingStatus::Open,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.charts'));

    $response->assertSuccessful();
    $data = $response->json('severityDistribution');

    expect($data)->toHaveKeys(['critical', 'high', 'medium', 'low', 'total']);
    expect($data['total'])->toBe(10);
    expect($data['critical']['count'])->toBe(2);
    expect($data['high']['count'])->toBe(3);
    expect($data['medium']['count'])->toBe(4);
    expect($data['low']['count'])->toBe(1);

    // Verify each severity has required structure
    foreach (['critical', 'high', 'medium', 'low'] as $severity) {
        expect($data[$severity])->toHaveKeys(['count', 'color', 'label', 'percentage']);
        expect($data[$severity]['color'])->toStartWith('rgb(');
    }
});
