<?php

/**
 * Dashboard Tablet Behavior Tests
 *
 * Tests for the Dashboard Vue component tablet viewport behavior:
 * - Metric cards grid displays correctly with expected props structure
 * - Filter controls render with appropriate data for tablet layouts
 * - Charts and progress sections have data for readable display
 *
 * Note: These tests verify the backend/API aspects and document expected
 * frontend behavior. The actual responsive CSS classes (grid-cols-1 sm:grid-cols-2
 * lg:grid-cols-4) execute client-side via Tailwind CSS.
 */

use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\Finding;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
});

/**
 * Test 1: Dashboard renders metric cards with complete data structure for tablet grid display.
 *
 * Verifies that all four metric cards receive the complete data structure
 * needed for proper rendering at tablet viewports (2-column grid on sm breakpoint).
 * Each metric card requires: value, trend (percentage + change), and sparkline data.
 */
test('dashboard metric cards have complete data structure for tablet grid display', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create a datacenter with infrastructure
    $datacenter = Datacenter::factory()->create();

    $response = $this->actingAs($user)
        ->get('/dashboard');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        // Verify metric cards have all required fields for tablet display
        ->has('metrics.rackUtilization', fn (Assert $metric) => $metric
            ->has('value')
            ->has('trend.percentage')
            ->has('trend.change')
            ->has('sparkline')
        )
        ->has('metrics.deviceCount', fn (Assert $metric) => $metric
            ->has('value')
            ->has('trend.percentage')
            ->has('trend.change')
            ->has('sparkline')
        )
        ->has('metrics.pendingAudits', fn (Assert $metric) => $metric
            ->has('value')
            ->has('pastDue')
            ->has('trend.percentage')
            ->has('trend.change')
            ->has('sparkline')
        )
        ->has('metrics.openFindings', fn (Assert $metric) => $metric
            ->has('value')
            ->has('bySeverity.critical')
            ->has('bySeverity.high')
            ->has('bySeverity.medium')
            ->has('bySeverity.low')
            ->has('trend.percentage')
            ->has('trend.change')
            ->has('sparkline')
        )
    );
});

/**
 * Test 2: Dashboard filter controls receive proper options for tablet landscape layout.
 *
 * Verifies that the datacenter filter dropdown receives all available options,
 * supporting full-width dropdowns on tablet viewports with adequate touch targets.
 */
test('dashboard filter controls receive datacenter options for tablet landscape', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create multiple datacenters
    $datacenter1 = Datacenter::factory()->create(['name' => 'DC Alpha']);
    $datacenter2 = Datacenter::factory()->create(['name' => 'DC Beta']);
    $datacenter3 = Datacenter::factory()->create(['name' => 'DC Gamma']);

    $response = $this->actingAs($user)
        ->get('/dashboard');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        // Verify datacenter options for filter dropdown
        ->has('datacenterOptions', 3)
        ->has('datacenterOptions.0', fn (Assert $option) => $option
            ->has('id')
            ->has('name')
        )
        // Verify filters state is provided for controlled inputs
        ->has('filters', fn (Assert $filters) => $filters
            ->has('datacenter_id')
        )
    );
});

/**
 * Test 3: Dashboard progress data supports readable badge and progress bar display.
 *
 * Verifies that the Open Findings card receives severity breakdown data
 * which is displayed as badges and progress indicators. This test ensures
 * the data supports the increased badge sizes for tablet readability.
 */
test('dashboard open findings has severity breakdown for progress badges display', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create datacenter
    $datacenter = Datacenter::factory()->create();

    // Create an audit for the datacenter
    $audit = Audit::factory()->create([
        'datacenter_id' => $datacenter->id,
        'status' => 'completed',
    ]);

    // Create findings with different severities using factory methods
    Finding::factory()
        ->forAudit($audit)
        ->critical()
        ->open()
        ->create();

    Finding::factory()
        ->forAudit($audit)
        ->high()
        ->open()
        ->create();

    Finding::factory()
        ->forAudit($audit)
        ->medium()
        ->open()
        ->create();

    $response = $this->actingAs($user)
        ->get('/dashboard');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        // Verify severity breakdown is properly structured for badge display
        ->has('metrics.openFindings.bySeverity', fn (Assert $severity) => $severity
            ->where('critical', 1)
            ->where('high', 1)
            ->where('medium', 1)
            ->where('low', 0)
        )
        // Verify total count matches individual severities
        ->where('metrics.openFindings.value', 3)
    );
});
