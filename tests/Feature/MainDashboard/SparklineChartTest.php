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
 * Test 1: SparklineChart component renders with data array
 * Verifies that sparkline data is an array of numeric values passed to the Dashboard
 */
test('sparkline component receives valid numeric data array for rendering', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            // Verify sparkline arrays exist and have 7 values
            ->has('metrics.rackUtilization.sparkline', 7)
            ->has('metrics.deviceCount.sparkline', 7)
            ->has('metrics.pendingAudits.sparkline', 7)
            ->has('metrics.openFindings.sparkline', 7)
        );

    // Additionally verify the sparkline values are numeric by checking the page props
    $pageProps = $response->original->getData()['page']['props'];
    $metrics = $pageProps['metrics'];

    expect($metrics['rackUtilization']['sparkline'])
        ->toBeArray()
        ->toHaveCount(7)
        ->each->toBeNumeric();

    expect($metrics['deviceCount']['sparkline'])
        ->toBeArray()
        ->toHaveCount(7)
        ->each->toBeNumeric();

    expect($metrics['pendingAudits']['sparkline'])
        ->toBeArray()
        ->toHaveCount(7)
        ->each->toBeNumeric();

    expect($metrics['openFindings']['sparkline'])
        ->toBeArray()
        ->toHaveCount(7)
        ->each->toBeNumeric();
});

/**
 * Test 2: SparklineChart receives 7-day data correctly sized for chart dimensions
 * Verifies that sparkline data contains exactly 7 values representing 7 days
 * The 80px x 30px dimensions are enforced in the Vue component
 */
test('sparkline data contains exactly 7 values for 7-day chart display', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create datacenter for context
    Datacenter::factory()->create();

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            // Each sparkline must have exactly 7 values (one per day for the past week)
            ->has('metrics.rackUtilization.sparkline', 7)
            ->has('metrics.deviceCount.sparkline', 7)
            ->has('metrics.pendingAudits.sparkline', 7)
            ->has('metrics.openFindings.sparkline', 7)
        );
});

/**
 * Test 3: SparklineChart data supports optional color prop through metric type
 * Verifies that each metric type's sparkline can be identified for color styling
 */
test('sparkline data is correctly associated with each metric type', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            // Verify each metric has its own sparkline data
            ->has('metrics.rackUtilization', fn ($metric) => $metric
                ->has('value')
                ->has('sparkline')
                ->etc()
            )
            ->has('metrics.deviceCount', fn ($metric) => $metric
                ->has('value')
                ->has('sparkline')
                ->etc()
            )
            ->has('metrics.pendingAudits', fn ($metric) => $metric
                ->has('value')
                ->has('sparkline')
                ->etc()
            )
            ->has('metrics.openFindings', fn ($metric) => $metric
                ->has('value')
                ->has('sparkline')
                ->etc()
            )
        );
});
