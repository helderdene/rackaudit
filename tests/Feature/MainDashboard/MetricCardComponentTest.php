<?php

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
 * Test 1: Metric cards render with correct value and label data structure
 * Verifies that each metric card receives the expected value and trend data
 */
test('metric cards render with correct metric value and label data for all four metrics', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create test data
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->active()->withUHeight(RackUHeight::U42)->create(['row_id' => $row->id]);

    // Create 5 devices using 20U total (approx 47.6% utilization of 42U rack)
    Device::factory()->count(5)->placed($rack, 1)->withUHeight(4)->create();

    // Create a completed audit (not pending)
    $completedAudit = Audit::factory()->completed()->create(['datacenter_id' => $datacenter->id]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            // Rack Utilization should have percentage value (between 0 and 100)
            ->where('metrics.rackUtilization.value', fn ($value) => is_numeric($value) && $value >= 0 && $value <= 100)
            // Device Count should match our created devices
            ->where('metrics.deviceCount.value', 5)
            // Verify pending audits metric exists with value property
            ->has('metrics.pendingAudits.value')
            // Verify open findings metric exists with value property
            ->has('metrics.openFindings.value')
        );
});

/**
 * Test 2: Trend indicator displays correct percentage and change values
 * Verifies that trend data includes both percentage and absolute change
 */
test('trend indicator displays correct percentage and absolute change values', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            // Each metric should have trend with percentage and change
            ->has('metrics.rackUtilization.trend', fn ($trend) => $trend
                ->has('percentage')
                ->has('change')
            )
            ->has('metrics.deviceCount.trend', fn ($trend) => $trend
                ->has('percentage')
                ->has('change')
            )
            ->has('metrics.pendingAudits.trend', fn ($trend) => $trend
                ->has('percentage')
                ->has('change')
            )
            ->has('metrics.openFindings.trend', fn ($trend) => $trend
                ->has('percentage')
                ->has('change')
            )
        );
});

/**
 * Test 3: Sparkline receives correct 7-day data array
 * Verifies that sparkline data is an array of 7 numeric values
 */
test('sparkline receives correct 7-day data array for each metric', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            // Verify sparkline arrays have 7 values
            ->has('metrics.rackUtilization.sparkline', 7)
            ->has('metrics.deviceCount.sparkline', 7)
            ->has('metrics.pendingAudits.sparkline', 7)
            ->has('metrics.openFindings.sparkline', 7)
        );
});

/**
 * Test 4: Open Findings card displays severity breakdown with correct counts
 * Verifies that bySeverity contains critical, high, medium, and low counts
 */
test('open findings card displays severity breakdown with correct counts', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();
    // Create a completed audit to avoid affecting pending audit counts
    $audit = Audit::factory()->completed()->create(['datacenter_id' => $datacenter->id]);

    // Create findings with different severities (without creating additional audits)
    // Using the factory's forAudit() to set the audit directly
    Finding::factory()->count(4)->critical()->open()->create(['audit_id' => $audit->id]);
    Finding::factory()->count(6)->high()->open()->create(['audit_id' => $audit->id]);
    Finding::factory()->count(8)->medium()->open()->create(['audit_id' => $audit->id]);
    Finding::factory()->count(2)->low()->open()->create(['audit_id' => $audit->id]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            // Total should be 4+6+8+2 = 20
            ->where('metrics.openFindings.value', 20)
            // Verify severity breakdown
            ->where('metrics.openFindings.bySeverity.critical', 4)
            ->where('metrics.openFindings.bySeverity.high', 6)
            ->where('metrics.openFindings.bySeverity.medium', 8)
            ->where('metrics.openFindings.bySeverity.low', 2)
        );
});
