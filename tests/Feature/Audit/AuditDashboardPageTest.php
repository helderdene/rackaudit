<?php

/**
 * Tests for the Audit Dashboard page structure, layout, and navigation.
 *
 * These tests verify that the dashboard page renders correctly with the proper
 * layout, heading, breadcrumbs, filter dropdowns, and navigation links.
 */

use App\Models\Datacenter;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create users with different roles
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    $this->itManager = User::factory()->create();
    $this->itManager->assignRole('IT Manager');

    $this->auditor = User::factory()->create();
    $this->auditor->assignRole('Auditor');

    // Create datacenters for filter dropdown testing
    $this->datacenter1 = Datacenter::factory()->create(['name' => 'Primary DC']);
    $this->datacenter2 = Datacenter::factory()->create(['name' => 'Secondary DC']);

    // Assign datacenter access to auditor
    $this->auditor->datacenters()->attach($this->datacenter1);
});

/**
 * Test 1: Page renders with correct layout and heading
 */
test('dashboard page renders with correct layout and heading', function () {
    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('filters')
            ->has('datacenterOptions')
            ->has('timePeriodOptions')
            ->has('auditMetrics')
            ->has('severityMetrics')
            ->has('resolutionMetrics')
        );
});

/**
 * Test 2: Filter dropdowns appear with correct options
 */
test('filter dropdowns have correct options', function () {
    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            // Check datacenter options exist
            ->has('datacenterOptions', 2)
            ->where('datacenterOptions.0.name', 'Primary DC')
            ->where('datacenterOptions.1.name', 'Secondary DC')
            // Check time period options
            ->has('timePeriodOptions', 5)
            ->where('timePeriodOptions.0.value', '30_days')
            ->where('timePeriodOptions.0.label', 'Last 30 days')
            ->where('timePeriodOptions.1.value', '90_days')
            ->where('timePeriodOptions.2.value', 'quarter')
            ->where('timePeriodOptions.3.value', 'year')
            ->where('timePeriodOptions.4.value', 'all')
        );
});

/**
 * Test 3: Navigation to dashboard from sidebar works (route exists and is accessible)
 */
test('dashboard is accessible via audits.dashboard route', function () {
    // Verify the route name resolves correctly
    $url = route('audits.dashboard');
    expect($url)->toEndWith('/audits/dashboard');

    // Verify we can navigate to the dashboard
    $response = $this->actingAs($this->auditor)->get($url);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->component('Audits/Dashboard'));
});

/**
 * Test 4: Breadcrumbs data is available for the page
 */
test('dashboard page provides data needed for breadcrumbs', function () {
    $response = $this->actingAs($this->itManager)->get('/audits/dashboard');

    // The page should render successfully and contain the Audits/Dashboard component
    // Breadcrumbs are implemented in the Vue component using the data provided
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            // Filter values should be present for maintaining state
            ->has('filters')
            ->where('filters.time_period', '30_days') // Default value
        );
});

/**
 * Test 5: Filter changes update URL query parameters
 */
test('filter changes are reflected in response data', function () {
    $response = $this->actingAs($this->admin)
        ->get('/audits/dashboard?datacenter_id='.$this->datacenter1->id.'&time_period=90_days');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->where('filters.datacenter_id', (string) $this->datacenter1->id)
            ->where('filters.time_period', '90_days')
        );
});
