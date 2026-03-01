<?php

/**
 * Tests for the Audit Dashboard chart visualizations.
 *
 * These tests verify that chart data is provided correctly for rendering
 * severity distribution (donut/pie) and audit completion trend (line) charts.
 */

use App\Enums\AuditStatus;
use App\Enums\FindingSeverity;
use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\Finding;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create admin user for dashboard access
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    // Create datacenter for testing
    $this->datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
});

/**
 * Test 1: Severity metrics data is structured for donut/pie chart rendering
 */
test('severity metrics provide chart-compatible data structure with percentages and colors', function () {
    $audit = Audit::factory()->create(['datacenter_id' => $this->datacenter->id]);

    // Create findings with different severities
    Finding::factory()->count(10)->critical()->forAudit($audit)->create();
    Finding::factory()->count(20)->high()->forAudit($audit)->create();
    Finding::factory()->count(30)->medium()->forAudit($audit)->create();
    Finding::factory()->count(40)->low()->forAudit($audit)->create();

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('severityMetrics', fn ($metrics) => $metrics
                // Verify counts
                ->where('critical.count', 10)
                ->where('high.count', 20)
                ->where('medium.count', 30)
                ->where('low.count', 40)
                ->where('total', 100)
                // Verify percentages for pie/donut chart (use numeric comparison)
                ->where('critical.percentage', fn ($value) => abs($value - 10.0) < 0.01)
                ->where('high.percentage', fn ($value) => abs($value - 20.0) < 0.01)
                ->where('medium.percentage', fn ($value) => abs($value - 30.0) < 0.01)
                ->where('low.percentage', fn ($value) => abs($value - 40.0) < 0.01)
                // Verify colors are provided for chart coloring
                ->has('critical.color')
                ->has('high.color')
                ->has('medium.color')
                ->has('low.color')
                // Verify labels are provided for chart legend
                ->has('critical.label')
                ->has('high.label')
                ->has('medium.label')
                ->has('low.label')
            )
        );
});

/**
 * Test 2: Trend data is structured for line chart with proper period granularity
 */
test('trend data provides time series data for line chart rendering', function () {
    // Create completed audits at different times within the last 30 days
    $audit1 = Audit::factory()->completed()->create([
        'datacenter_id' => $this->datacenter->id,
        'updated_at' => now()->subDays(5),
    ]);
    $audit2 = Audit::factory()->completed()->create([
        'datacenter_id' => $this->datacenter->id,
        'updated_at' => now()->subDays(5),
    ]);
    $audit3 = Audit::factory()->completed()->create([
        'datacenter_id' => $this->datacenter->id,
        'updated_at' => now()->subDays(10),
    ]);

    $response = $this->actingAs($this->admin)->get('/audits/dashboard?time_period=30_days');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('trendData', fn ($trendData) => $trendData
                // For 30 days, granularity should be daily - check structure
                ->each(fn ($point) => $point
                    ->has('period')
                    ->has('count')
                )
            )
        );

    // Verify the trendData contains the expected periods
    $pageProps = $response->original->getData()['page']['props'];
    $trendData = $pageProps['trendData'];

    // Should have entries for each day in the period
    expect($trendData)->toBeArray();
    expect(count($trendData))->toBeGreaterThanOrEqual(30);

    // Each entry should have period and count keys
    foreach ($trendData as $point) {
        expect($point)->toHaveKeys(['period', 'count']);
        expect($point['period'])->toMatch('/^\d{4}-\d{2}-\d{2}$/'); // YYYY-MM-DD format for daily
        expect($point['count'])->toBeInt();
    }
});

/**
 * Test 3: Charts handle empty data gracefully with zero values
 */
test('chart data handles empty state gracefully with zero values', function () {
    // Create audits with no findings and no completed audits
    Audit::factory()->pending()->create(['datacenter_id' => $this->datacenter->id]);

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            // Severity metrics should have zero counts with valid structure
            ->has('severityMetrics', fn ($metrics) => $metrics
                ->where('critical.count', 0)
                ->where('high.count', 0)
                ->where('medium.count', 0)
                ->where('low.count', 0)
                ->where('total', 0)
                // Percentages should be 0 when no findings (use numeric comparison)
                ->where('critical.percentage', fn ($value) => $value == 0)
                ->where('high.percentage', fn ($value) => $value == 0)
                ->where('medium.percentage', fn ($value) => $value == 0)
                ->where('low.percentage', fn ($value) => $value == 0)
            )
            // Trend data should have structure but all zeros
            ->has('trendData')
        );

    // Verify trend data has proper structure even with no completions
    $pageProps = $response->original->getData()['page']['props'];
    $trendData = $pageProps['trendData'];

    expect($trendData)->toBeArray();
    foreach ($trendData as $point) {
        expect($point)->toHaveKeys(['period', 'count']);
    }
});

/**
 * Test 4: Trend data adjusts granularity based on time period filter
 */
test('trend data granularity adjusts based on time period filter', function () {
    // Create some completed audits
    Audit::factory()->count(5)->completed()->create([
        'datacenter_id' => $this->datacenter->id,
        'updated_at' => now()->subDays(10),
    ]);

    // Test 30 days - should be daily granularity (YYYY-MM-DD)
    $response30 = $this->actingAs($this->admin)->get('/audits/dashboard?time_period=30_days');
    $response30->assertOk();
    $trendData30 = $response30->original->getData()['page']['props']['trendData'];
    // Daily format: YYYY-MM-DD
    $firstPeriod30 = $trendData30[0]['period'] ?? '';
    expect($firstPeriod30)->toMatch('/^\d{4}-\d{2}-\d{2}$/');

    // Test 90 days - should be weekly granularity (YYYY-Www)
    $response90 = $this->actingAs($this->admin)->get('/audits/dashboard?time_period=90_days');
    $response90->assertOk();
    $trendData90 = $response90->original->getData()['page']['props']['trendData'];
    // Weekly format: YYYY-Www
    $firstPeriod90 = $trendData90[0]['period'] ?? '';
    expect($firstPeriod90)->toMatch('/^\d{4}-W\d{2}$/');

    // Test year - should be monthly granularity (YYYY-MM)
    $responseYear = $this->actingAs($this->admin)->get('/audits/dashboard?time_period=year');
    $responseYear->assertOk();
    $trendDataYear = $responseYear->original->getData()['page']['props']['trendData'];
    // Monthly format: YYYY-MM
    $firstPeriodYear = $trendDataYear[0]['period'] ?? '';
    expect($firstPeriodYear)->toMatch('/^\d{4}-\d{2}$/');
});

/**
 * Test 5: Chart data respects datacenter filter
 */
test('chart data respects datacenter filter for both severity and trend', function () {
    $datacenter2 = Datacenter::factory()->create(['name' => 'Other DC']);

    // Create audits and findings in main datacenter
    $audit1 = Audit::factory()->completed()->create([
        'datacenter_id' => $this->datacenter->id,
        'updated_at' => now()->subDays(5),
    ]);
    Finding::factory()->count(5)->critical()->forAudit($audit1)->create();

    // Create audits and findings in other datacenter
    $audit2 = Audit::factory()->completed()->create([
        'datacenter_id' => $datacenter2->id,
        'updated_at' => now()->subDays(5),
    ]);
    Finding::factory()->count(10)->high()->forAudit($audit2)->create();

    // Request with filter for main datacenter only
    $response = $this->actingAs($this->admin)->get('/audits/dashboard?datacenter_id=' . $this->datacenter->id);

    $response->assertOk();

    // Verify severity metrics through props inspection for datacenter filter
    $pageProps = $response->original->getData()['page']['props'];
    $severityMetrics = $pageProps['severityMetrics'];

    // Should only have critical findings from main datacenter
    expect($severityMetrics['critical']['count'])->toBe(5);
    expect($severityMetrics['high']['count'])->toBe(0); // High findings are in other datacenter
    expect($severityMetrics['total'])->toBe(5);

    // Verify trend data only includes audits from filtered datacenter
    $trendData = $pageProps['trendData'];
    $totalCount = array_sum(array_column($trendData, 'count'));
    expect($totalCount)->toBe(1); // Only 1 completed audit in main datacenter
});
