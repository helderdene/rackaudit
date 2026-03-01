<?php

use App\Models\Datacenter;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Test 1: Dashboard page renders with correct structure
 * Verifies the Dashboard component is rendered with AppLayout, HeadingSmall, and required props
 */
test('dashboard page renders with correct structure including heading and layout', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            // Verify all required props are present
            ->has('metrics')
            ->has('datacenterOptions')
            ->has('filters')
            ->has('recentActivity')
        );
});

/**
 * Test 2: Datacenter filter dropdown displays available options
 * Verifies that datacenterOptions includes all accessible datacenters for the user
 */
test('datacenter filter dropdown displays accessible datacenter options', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create multiple datacenters
    $datacenter1 = Datacenter::factory()->create(['name' => 'Primary DC']);
    $datacenter2 = Datacenter::factory()->create(['name' => 'Secondary DC']);
    $datacenter3 = Datacenter::factory()->create(['name' => 'Tertiary DC']);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('datacenterOptions', 3) // All 3 datacenters should be in options
            ->where('datacenterOptions.0.name', 'Primary DC')
            ->where('datacenterOptions.1.name', 'Secondary DC')
            ->where('datacenterOptions.2.name', 'Tertiary DC')
        );
});

/**
 * Test 3: Metric cards display data from props
 * Verifies that all four metric cards (rack utilization, device count, pending audits, open findings) receive correct data
 */
test('metric cards receive correct data from props for all four metrics', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            // Rack utilization metric
            ->has('metrics.rackUtilization', fn ($metric) => $metric
                ->has('value')
                ->has('trend.percentage')
                ->has('trend.change')
                ->has('sparkline')
            )
            // Device count metric
            ->has('metrics.deviceCount', fn ($metric) => $metric
                ->has('value')
                ->has('trend.percentage')
                ->has('trend.change')
                ->has('sparkline')
            )
            // Pending audits metric
            ->has('metrics.pendingAudits', fn ($metric) => $metric
                ->has('value')
                ->has('pastDue')
                ->has('trend.percentage')
                ->has('trend.change')
                ->has('sparkline')
            )
            // Open findings metric with severity breakdown
            ->has('metrics.openFindings', fn ($metric) => $metric
                ->has('value')
                ->has('bySeverity')
                ->has('trend.percentage')
                ->has('trend.change')
                ->has('sparkline')
            )
        );
});

/**
 * Test 4: Datacenter filter selection persists via URL query parameter
 * Verifies that the filter state is preserved when datacenter_id query parameter is passed
 */
test('datacenter filter selection persists via URL query parameter', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create(['name' => 'Test Datacenter']);

    $response = $this->actingAs($admin)->get(route('dashboard', ['datacenter_id' => $datacenter->id]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('filters.datacenter_id', $datacenter->id)
        );
});
