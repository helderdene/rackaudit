<?php

use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\Finding;
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

    // Create datacenters for testing
    $this->datacenter1 = Datacenter::factory()->create(['name' => 'Alpha DC']);
    $this->datacenter2 = Datacenter::factory()->create(['name' => 'Beta DC']);
    $this->datacenter3 = Datacenter::factory()->create(['name' => 'Gamma DC']);

    // Assign datacenter access to auditor (non-admin user)
    $this->auditor->datacenters()->attach([$this->datacenter1->id, $this->datacenter2->id]);
});

/**
 * Test 1: Datacenter filter restricts audit and finding results to selected datacenter
 */
test('datacenter filter restricts results to selected datacenter', function () {
    // Create audits in different datacenters
    Audit::factory()->count(3)->completed()->create(['datacenter_id' => $this->datacenter1->id]);
    Audit::factory()->count(5)->completed()->create(['datacenter_id' => $this->datacenter2->id]);
    Audit::factory()->count(2)->completed()->create(['datacenter_id' => $this->datacenter3->id]);

    // Create findings in different datacenters
    $auditDc1 = Audit::factory()->create(['datacenter_id' => $this->datacenter1->id]);
    $auditDc2 = Audit::factory()->create(['datacenter_id' => $this->datacenter2->id]);

    Finding::factory()->count(4)->critical()->forAudit($auditDc1)->create();
    Finding::factory()->count(10)->critical()->forAudit($auditDc2)->create();

    // Filter by datacenter1 only
    $response = $this->actingAs($this->admin)
        ->get('/audits/dashboard?datacenter_id='.$this->datacenter1->id);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->where('filters.datacenter_id', (string) $this->datacenter1->id)
            ->has('auditMetrics', fn ($metrics) => $metrics
                ->where('total', 4) // 3 completed + 1 with findings
                ->etc()
            )
            ->has('severityMetrics', fn ($metrics) => $metrics
                ->where('critical.count', 4) // Only from datacenter1
                ->etc()
            )
        );

    // Filter by datacenter2 only
    $response2 = $this->actingAs($this->admin)
        ->get('/audits/dashboard?datacenter_id='.$this->datacenter2->id);

    $response2->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('auditMetrics', fn ($metrics) => $metrics
                ->where('total', 6) // 5 completed + 1 with findings
                ->etc()
            )
            ->has('severityMetrics', fn ($metrics) => $metrics
                ->where('critical.count', 10) // Only from datacenter2
                ->etc()
            )
        );
});

/**
 * Test 2: Time period filter with each preset (30 days, 90 days, quarter, year, all time)
 */
test('time period filter with each preset returns correct date ranges', function (string $timePeriod, int $expectedCount) {
    // Create audits at different times
    // Very recent audit (5 days ago) - should be in all filters
    Audit::factory()->create([
        'datacenter_id' => $this->datacenter1->id,
        'created_at' => now()->subDays(5),
    ]);

    // 45 days ago - should NOT be in 30_days, but should be in 90_days, quarter (if applicable), year, all
    Audit::factory()->create([
        'datacenter_id' => $this->datacenter1->id,
        'created_at' => now()->subDays(45),
    ]);

    // 120 days ago - should NOT be in 30_days or 90_days, but should be in year, all
    Audit::factory()->create([
        'datacenter_id' => $this->datacenter1->id,
        'created_at' => now()->subDays(120),
    ]);

    // 400 days ago - should ONLY be in 'all'
    Audit::factory()->create([
        'datacenter_id' => $this->datacenter1->id,
        'created_at' => now()->subDays(400),
    ]);

    $response = $this->actingAs($this->admin)
        ->get('/audits/dashboard?time_period='.$timePeriod);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->where('filters.time_period', $timePeriod)
            ->has('auditMetrics', fn ($metrics) => $metrics
                ->where('total', $expectedCount)
                ->etc()
            )
        );
})->with([
    '30_days returns audits from last 30 days' => ['30_days', 1],
    '90_days returns audits from last 90 days' => ['90_days', 2],
    'year returns audits from this year start' => ['year', fn () => collect([5, 45, 120, 400])->filter(fn ($days) => now()->subDays($days)->gte(now()->startOfYear()))->count()],
    'all returns all audits regardless of date' => ['all', 4],
]);

/**
 * Test 3: Combined datacenter and time period filters work together
 */
test('combined datacenter and time period filters work together', function () {
    // Create audits in datacenter1 at different times
    Audit::factory()->create([
        'datacenter_id' => $this->datacenter1->id,
        'created_at' => now()->subDays(10), // Recent, DC1
    ]);
    Audit::factory()->create([
        'datacenter_id' => $this->datacenter1->id,
        'created_at' => now()->subDays(60), // Old, DC1
    ]);

    // Create audits in datacenter2 at different times
    Audit::factory()->create([
        'datacenter_id' => $this->datacenter2->id,
        'created_at' => now()->subDays(10), // Recent, DC2
    ]);
    Audit::factory()->create([
        'datacenter_id' => $this->datacenter2->id,
        'created_at' => now()->subDays(60), // Old, DC2
    ]);

    // Filter by datacenter1 AND last 30 days - should return only 1
    $response = $this->actingAs($this->admin)
        ->get('/audits/dashboard?datacenter_id='.$this->datacenter1->id.'&time_period=30_days');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->where('filters.datacenter_id', (string) $this->datacenter1->id)
            ->where('filters.time_period', '30_days')
            ->has('auditMetrics', fn ($metrics) => $metrics
                ->where('total', 1)
                ->etc()
            )
        );

    // Filter by datacenter1 AND last 90 days - should return 2
    $response2 = $this->actingAs($this->admin)
        ->get('/audits/dashboard?datacenter_id='.$this->datacenter1->id.'&time_period=90_days');

    $response2->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('auditMetrics', fn ($metrics) => $metrics
                ->where('total', 2)
                ->etc()
            )
        );
});

/**
 * Test 4: User-accessible datacenters are correctly retrieved for non-admin users
 */
test('user-accessible datacenters are correctly retrieved for non-admin users', function () {
    // Auditor has access to datacenter1 and datacenter2, but not datacenter3
    $response = $this->actingAs($this->auditor)
        ->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('datacenterOptions', 2) // Only datacenter1 and datacenter2
            ->where('datacenterOptions.0.id', $this->datacenter1->id)
            ->where('datacenterOptions.0.name', 'Alpha DC')
            ->where('datacenterOptions.1.id', $this->datacenter2->id)
            ->where('datacenterOptions.1.name', 'Beta DC')
        );
});

/**
 * Test 5: Admin users see all datacenters in filter options
 */
test('admin users see all datacenters in filter options', function () {
    $response = $this->actingAs($this->admin)
        ->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('datacenterOptions', 3) // All 3 datacenters
        );

    // IT Manager should also see all datacenters
    $response2 = $this->actingAs($this->itManager)
        ->get('/audits/dashboard');

    $response2->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('datacenterOptions', 3)
        );
});

/**
 * Test 6: Filter values persist in URL query parameters and are returned in response
 */
test('filter values persist in URL query parameters', function () {
    // Test with various filter combinations
    $response = $this->actingAs($this->admin)
        ->get('/audits/dashboard?datacenter_id='.$this->datacenter1->id.'&time_period=quarter');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->where('filters.datacenter_id', (string) $this->datacenter1->id)
            ->where('filters.time_period', 'quarter')
        );

    // Test default time period when not specified
    $response2 = $this->actingAs($this->admin)
        ->get('/audits/dashboard');

    $response2->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->where('filters.datacenter_id', null)
            ->where('filters.time_period', '30_days') // Default value
        );

    // Verify time period options are returned
    $response2->assertInertia(fn ($page) => $page
        ->has('timePeriodOptions', 5)
        ->where('timePeriodOptions.0.value', '30_days')
        ->where('timePeriodOptions.0.label', 'Last 30 days')
        ->where('timePeriodOptions.1.value', '90_days')
        ->where('timePeriodOptions.1.label', 'Last 90 days')
        ->where('timePeriodOptions.2.value', 'quarter')
        ->where('timePeriodOptions.2.label', 'This quarter')
        ->where('timePeriodOptions.3.value', 'year')
        ->where('timePeriodOptions.3.label', 'This year')
        ->where('timePeriodOptions.4.value', 'all')
        ->where('timePeriodOptions.4.label', 'All time')
    );
});
