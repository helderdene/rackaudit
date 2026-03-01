<?php

use App\Enums\AuditStatus;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Finding;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Test 1: Dashboard route returns correct Inertia page with expected data structure
 */
test('dashboard route returns correct Inertia page with expected data structure', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('metrics', fn ($metrics) => $metrics
                ->has('rackUtilization')
                ->has('deviceCount')
                ->has('pendingAudits')
                ->has('openFindings')
            )
            ->has('datacenterOptions')
            ->has('filters')
            ->has('recentActivity')
        );
});

/**
 * Test 2: Metrics data structure is correct with all required fields
 */
test('metrics data structure contains all required fields including sparkline and trend data', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create test data
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->active()->withUHeight(RackUHeight::U42)->create(['row_id' => $row->id]);
    Device::factory()->placed($rack, 1)->withUHeight(4)->create();

    $audit = Audit::factory()->pending()->create(['datacenter_id' => $datacenter->id]);
    Finding::factory()->critical()->open()->forAudit($audit)->create();

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('metrics.rackUtilization', fn ($metric) => $metric
                ->has('value')
                ->has('trend')
                ->has('sparkline')
            )
            ->has('metrics.deviceCount', fn ($metric) => $metric
                ->has('value')
                ->has('trend')
                ->has('sparkline')
            )
            ->has('metrics.pendingAudits', fn ($metric) => $metric
                ->has('value')
                ->has('trend')
                ->has('sparkline')
                ->has('pastDue')
            )
            ->has('metrics.openFindings', fn ($metric) => $metric
                ->has('value')
                ->has('trend')
                ->has('sparkline')
                ->has('bySeverity')
            )
        );
});

/**
 * Test 3: Datacenter filter parameter affects all metrics
 */
test('datacenter filter parameter affects all metrics results', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create two datacenters with different data
    $datacenter1 = Datacenter::factory()->create(['name' => 'Datacenter 1']);
    $datacenter2 = Datacenter::factory()->create(['name' => 'Datacenter 2']);

    // Create devices in datacenter 1
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter1->id]);
    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $rack1 = Rack::factory()->active()->withUHeight(RackUHeight::U42)->create(['row_id' => $row1->id]);
    Device::factory()->count(5)->placed($rack1, 1)->withUHeight(1)->create();

    // Create devices in datacenter 2
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter2->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);
    $rack2 = Rack::factory()->active()->withUHeight(RackUHeight::U42)->create(['row_id' => $row2->id]);
    Device::factory()->count(10)->placed($rack2, 1)->withUHeight(1)->create();

    // Filter by datacenter 1 - should show only 5 devices
    $response = $this->actingAs($admin)->get(route('dashboard', ['datacenter_id' => $datacenter1->id]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('metrics.deviceCount.value', 5)
            ->where('filters.datacenter_id', $datacenter1->id)
        );

    // Filter by datacenter 2 - should show 10 devices
    $response2 = $this->actingAs($admin)->get(route('dashboard', ['datacenter_id' => $datacenter2->id]));

    $response2->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('metrics.deviceCount.value', 10)
        );
});

/**
 * Test 4: Permission scoping for non-admin users
 */
test('non-admin users see only their assigned datacenters in metrics', function () {
    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    // Create two datacenters
    $datacenter1 = Datacenter::factory()->create(['name' => 'Accessible DC']);
    $datacenter2 = Datacenter::factory()->create(['name' => 'Not Accessible DC']);

    // Assign operator to datacenter 1 only
    $operator->datacenters()->attach($datacenter1);

    // Create audits in both datacenters
    Audit::factory()->count(2)->pending()->create(['datacenter_id' => $datacenter1->id]);
    Audit::factory()->count(5)->pending()->create(['datacenter_id' => $datacenter2->id]);

    $response = $this->actingAs($operator)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            // Should only see pending audits from datacenter1 (2), not datacenter2 (5)
            ->where('metrics.pendingAudits.value', 2)
            // Should only see datacenter1 in options
            ->has('datacenterOptions', 1)
        );
});

/**
 * Test 5: Sparkline data returns 7-day values array
 */
test('sparkline data returns array of 7 values for each metric', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('metrics.rackUtilization.sparkline', 7)
            ->has('metrics.deviceCount.sparkline', 7)
            ->has('metrics.pendingAudits.sparkline', 7)
            ->has('metrics.openFindings.sparkline', 7)
        );
});

/**
 * Test 6: Open findings metric includes correct severity breakdown
 */
test('open findings metric includes correct severity breakdown', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();
    $audit = Audit::factory()->create(['datacenter_id' => $datacenter->id]);

    // Create findings with different severities
    Finding::factory()->count(2)->critical()->open()->forAudit($audit)->create();
    Finding::factory()->count(3)->high()->inProgress()->forAudit($audit)->create();
    Finding::factory()->count(4)->medium()->pendingReview()->forAudit($audit)->create();
    Finding::factory()->count(1)->low()->deferred()->forAudit($audit)->create();
    // Resolved findings should not count
    Finding::factory()->count(5)->critical()->resolved()->forAudit($audit)->create();

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('metrics.openFindings.value', 10) // 2+3+4+1 = 10 open findings
            ->where('metrics.openFindings.bySeverity.critical', 2)
            ->where('metrics.openFindings.bySeverity.high', 3)
            ->where('metrics.openFindings.bySeverity.medium', 4)
            ->where('metrics.openFindings.bySeverity.low', 1)
        );
});
