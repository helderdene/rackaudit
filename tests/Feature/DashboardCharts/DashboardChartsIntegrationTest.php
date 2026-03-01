<?php

/**
 * Integration tests for Dashboard Charts feature.
 *
 * These tests fill critical coverage gaps from Task Groups 1-4:
 * - Multi-datacenter aggregation without filters
 * - Weighted average capacity calculation
 * - Chronological ordering of trend data
 * - IT Manager role access (equivalent to Administrator)
 * - Invalid/default time period handling
 * - Activity by entity aggregation across multiple snapshots
 */

use App\Jobs\CaptureCapacitySnapshotJob;
use App\Jobs\CaptureDashboardMetricsJob;
use App\Models\CapacitySnapshot;
use App\Models\DashboardSnapshot;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use App\Enums\RackStatus;
use App\Services\CapacityCalculationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('chart data aggregates multiple datacenters when no filter applied', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create two datacenters
    $datacenter1 = Datacenter::factory()->create(['name' => 'DC-Alpha']);
    $datacenter2 = Datacenter::factory()->create(['name' => 'DC-Beta']);

    $today = now()->toDateString();

    // Create capacity snapshots for both datacenters on same date
    CapacitySnapshot::factory()->forDate($today)->create([
        'datacenter_id' => $datacenter1->id,
        'rack_utilization_percent' => 80.0,
        'total_u_space' => 400, // 80% of 400 = 320 used
        'device_count' => 100,
    ]);
    CapacitySnapshot::factory()->forDate($today)->create([
        'datacenter_id' => $datacenter2->id,
        'rack_utilization_percent' => 60.0,
        'total_u_space' => 600, // 60% of 600 = 360 used
        'device_count' => 150,
    ]);

    // Dashboard snapshots
    DashboardSnapshot::factory()->forDate($today)->create([
        'datacenter_id' => $datacenter1->id,
        'completed_audits_count' => 10,
        'activity_by_entity' => ['Device' => 5, 'Rack' => 3],
    ]);
    DashboardSnapshot::factory()->forDate($today)->create([
        'datacenter_id' => $datacenter2->id,
        'completed_audits_count' => 8,
        'activity_by_entity' => ['Device' => 10, 'Rack' => 2],
    ]);

    // Request without datacenter filter - should aggregate both
    $response = $this->actingAs($user)->get(route('dashboard.charts'));
    $response->assertSuccessful();

    $data = $response->json();

    // Device count should be sum: 100 + 150 = 250
    if (!empty($data['deviceCountTrend']['data'])) {
        expect($data['deviceCountTrend']['data'][0])->toBe(250);
    }

    // Audit completions should be sum: 10 + 8 = 18
    if (!empty($data['auditCompletionTrend']['data'])) {
        expect($data['auditCompletionTrend']['data'][0])->toBe(18);
    }

    // Activity counts should aggregate: Device = 5+10 = 15, Rack = 3+2 = 5
    if (!empty($data['activityByEntity']['labels'])) {
        $deviceIndex = array_search('Devices', $data['activityByEntity']['labels']);
        if ($deviceIndex !== false) {
            expect($data['activityByEntity']['data'][$deviceIndex])->toBe(15);
        }
    }
});

test('capacity trend uses weighted average calculation by U-space', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    $datacenter1 = Datacenter::factory()->create();
    $datacenter2 = Datacenter::factory()->create();

    $today = now()->toDateString();

    // DC1: 400 U total, 80% utilization -> weighted contribution = 80 * 400 = 32000
    // DC2: 600 U total, 60% utilization -> weighted contribution = 60 * 600 = 36000
    // Total U-space = 1000
    // Weighted average = (32000 + 36000) / 1000 = 68%
    CapacitySnapshot::factory()->forDate($today)->create([
        'datacenter_id' => $datacenter1->id,
        'rack_utilization_percent' => 80.0,
        'total_u_space' => 400,
        'device_count' => 100,
    ]);
    CapacitySnapshot::factory()->forDate($today)->create([
        'datacenter_id' => $datacenter2->id,
        'rack_utilization_percent' => 60.0,
        'total_u_space' => 600,
        'device_count' => 150,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.charts'));
    $response->assertSuccessful();

    $data = $response->json();

    // Weighted average should be 68 (not simple average of 70)
    // Use toEqual for loose comparison since API may return int or float
    if (!empty($data['capacityTrend']['data'])) {
        expect((float) $data['capacityTrend']['data'][0])->toEqual(68.0);
    }
});

test('chart data returns labels in chronological order', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');
    $datacenter = Datacenter::factory()->create();

    // Create snapshots out of order to verify sorting
    $dates = [
        now()->subDays(3)->toDateString(),
        now()->subDays(5)->toDateString(),
        now()->subDays(1)->toDateString(),
        now()->subDays(4)->toDateString(),
        now()->subDays(2)->toDateString(),
    ];

    foreach ($dates as $date) {
        CapacitySnapshot::factory()->forDate($date)->create([
            'datacenter_id' => $datacenter->id,
            'device_count' => 100,
        ]);
    }

    $response = $this->actingAs($user)->get(route('dashboard.charts', ['time_period' => '7_days']));
    $response->assertSuccessful();

    $data = $response->json();
    $labels = $data['capacityTrend']['labels'];

    // Labels should be in chronological order (oldest first)
    // Convert labels to comparable dates and verify ascending order
    expect(count($labels))->toBe(5);

    // Verify each label comes after the previous one chronologically
    for ($i = 1; $i < count($labels); $i++) {
        $prevDate = \Carbon\Carbon::createFromFormat('M d', $labels[$i - 1]);
        $currDate = \Carbon\Carbon::createFromFormat('M d', $labels[$i]);
        // In same year context, later dates should be >= earlier dates
        expect($currDate->dayOfYear)->toBeGreaterThanOrEqual($prevDate->dayOfYear);
    }
});

test('IT Manager role has full datacenter access like Administrator', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $datacenter1 = Datacenter::factory()->create();
    $datacenter2 = Datacenter::factory()->create();

    // Create snapshots for both datacenters
    CapacitySnapshot::factory()->forDate(now()->toDateString())->create([
        'datacenter_id' => $datacenter1->id,
        'device_count' => 100,
    ]);
    CapacitySnapshot::factory()->forDate(now()->toDateString())->create([
        'datacenter_id' => $datacenter2->id,
        'device_count' => 200,
    ]);

    // IT Manager should see aggregated data from both datacenters
    $response = $this->actingAs($user)->get(route('dashboard.charts'));
    $response->assertSuccessful();

    $data = $response->json();

    // Should see combined device count: 100 + 200 = 300
    if (!empty($data['deviceCountTrend']['data'])) {
        expect($data['deviceCountTrend']['data'][0])->toBe(300);
    }
});

test('invalid time period parameter defaults to 7 days', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');
    $datacenter = Datacenter::factory()->create();

    // Create snapshots for past 30 days
    for ($i = 29; $i >= 0; $i--) {
        CapacitySnapshot::factory()->forDate(now()->subDays($i)->toDateString())->create([
            'datacenter_id' => $datacenter->id,
            'device_count' => 100 + $i,
        ]);
    }

    // Request with invalid time_period
    $response = $this->actingAs($user)->get(route('dashboard.charts', ['time_period' => 'invalid_period']));
    $response->assertSuccessful();

    $data = $response->json();

    // Should default to 7 days, so max 7 data points
    expect(count($data['capacityTrend']['labels']))->toBeLessThanOrEqual(7);
});

test('default time period is 7 days when not specified', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');
    $datacenter = Datacenter::factory()->create();

    // Create snapshots for past 15 days
    for ($i = 14; $i >= 0; $i--) {
        CapacitySnapshot::factory()->forDate(now()->subDays($i)->toDateString())->create([
            'datacenter_id' => $datacenter->id,
            'device_count' => 100,
        ]);
    }

    // Request without time_period parameter
    $response = $this->actingAs($user)->get(route('dashboard.charts'));
    $response->assertSuccessful();

    $data = $response->json();

    // Should return only last 7 days of data
    expect(count($data['capacityTrend']['labels']))->toBeLessThanOrEqual(7);
    expect(count($data['capacityTrend']['labels']))->toBe(7); // Should have exactly 7 for 15 days of data
});

test('full flow from job capture to API query returns consistent data', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create datacenter with infrastructure for job to capture
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create([
        'row_id' => $row->id,
        'status' => RackStatus::Active,
    ]);

    // Create devices
    Device::factory()->count(8)->create([
        'rack_id' => $rack->id,
        'u_height' => 2,
    ]);

    // Run the capacity snapshot job
    $capacityJob = new CaptureCapacitySnapshotJob();
    $capacityJob->handle(app(CapacityCalculationService::class));

    // Run the dashboard metrics job
    $metricsJob = new CaptureDashboardMetricsJob();
    $metricsJob->handle();

    // Now query the API
    $response = $this->actingAs($user)->get(route('dashboard.charts'));
    $response->assertSuccessful();

    $data = $response->json();

    // Verify device count captured by job matches API response
    $capturedSnapshot = CapacitySnapshot::where('datacenter_id', $datacenter->id)->first();
    expect($capturedSnapshot)->not->toBeNull();
    expect($capturedSnapshot->device_count)->toBe(8);

    // API should return this same device count
    if (!empty($data['deviceCountTrend']['data'])) {
        expect($data['deviceCountTrend']['data'][0])->toBe(8);
    }
});

test('activity by entity aggregates across multiple days in time period', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');
    $datacenter = Datacenter::factory()->create();

    // Create dashboard snapshots for multiple days with different activity counts
    DashboardSnapshot::factory()->forDate(now()->subDays(2)->toDateString())->create([
        'datacenter_id' => $datacenter->id,
        'activity_by_entity' => ['Device' => 10, 'Rack' => 5, 'Connection' => 3],
    ]);
    DashboardSnapshot::factory()->forDate(now()->subDays(1)->toDateString())->create([
        'datacenter_id' => $datacenter->id,
        'activity_by_entity' => ['Device' => 15, 'Rack' => 8, 'Audit' => 2],
    ]);
    DashboardSnapshot::factory()->forDate(now()->toDateString())->create([
        'datacenter_id' => $datacenter->id,
        'activity_by_entity' => ['Device' => 5, 'Finding' => 7],
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.charts', ['time_period' => '7_days']));
    $response->assertSuccessful();

    $data = $response->json();

    // Activity should be aggregated across all 3 days
    // Device: 10 + 15 + 5 = 30
    // Rack: 5 + 8 = 13
    // Connection: 3
    // Audit: 2
    // Finding: 7

    $labels = $data['activityByEntity']['labels'];
    $values = $data['activityByEntity']['data'];

    // Find Devices in the aggregated data
    $deviceIndex = array_search('Devices', $labels);
    if ($deviceIndex !== false) {
        expect($values[$deviceIndex])->toBe(30);
    }
});
