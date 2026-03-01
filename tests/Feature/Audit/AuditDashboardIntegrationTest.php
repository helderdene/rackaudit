<?php

/**
 * Integration tests for the Audit Dashboard feature.
 *
 * These tests fill critical gaps in coverage by testing:
 * - Edge cases for progress bar calculations
 * - Filter application across all dashboard sections
 * - End-to-end data consistency
 * - Quarter time period filter
 */

use App\Models\Audit;
use App\Models\AuditConnectionVerification;
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

    // Create datacenters for testing
    $this->datacenter1 = Datacenter::factory()->create(['name' => 'Primary DC']);
    $this->datacenter2 = Datacenter::factory()->create(['name' => 'Secondary DC']);
});

/**
 * Test 1: Progress bars handle zero total verifications edge case gracefully.
 */
test('progress bars handle zero total verifications without errors', function () {
    // Create in-progress audit with no verifications at all
    $auditNoVerifications = Audit::factory()
        ->connectionType()
        ->inProgress()
        ->create([
            'datacenter_id' => $this->datacenter1->id,
            'name' => 'Empty Audit',
            'due_date' => now()->addDays(14),
        ]);

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('activeAuditProgress', 1)
            // Progress should be 0% when no verifications exist
            ->where('activeAuditProgress.0.progressPercentage', fn ($value) => $value === 0 || $value === 0.0)
            ->where('activeAuditProgress.0.name', 'Empty Audit')
        );
});

/**
 * Test 2: Quarter time period filter calculates correct date range.
 */
test('quarter time period filter returns audits from current quarter', function () {
    // Determine current quarter boundaries
    $quarterStart = now()->startOfQuarter();
    $quarterEnd = now()->endOfQuarter();

    // Create audit within current quarter
    $inQuarterAudit = Audit::factory()->create([
        'datacenter_id' => $this->datacenter1->id,
        'created_at' => now()->subDays(5), // Definitely in current quarter
        'name' => 'In Quarter Audit',
    ]);

    // Create audit before current quarter (if possible)
    $beforeQuarterDate = $quarterStart->copy()->subDays(30);
    $beforeQuarterAudit = Audit::factory()->create([
        'datacenter_id' => $this->datacenter1->id,
        'created_at' => $beforeQuarterDate,
        'name' => 'Before Quarter Audit',
    ]);

    $response = $this->actingAs($this->admin)
        ->get('/audits/dashboard?time_period=quarter');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->where('filters.time_period', 'quarter')
        );

    // Verify the audit counts make sense for the quarter filter
    $pageProps = $response->original->getData()['page']['props'];
    $auditMetrics = $pageProps['auditMetrics'];

    // At minimum, the in-quarter audit should be counted
    expect($auditMetrics['total'])->toBeGreaterThanOrEqual(1);

    // If before quarter audit was created before quarter start, total should be 1 or more
    // (depending on whether beforeQuarterDate is actually before quarter start)
    if ($beforeQuarterDate < $quarterStart) {
        expect($auditMetrics['total'])->toBe(1);
    }
});

/**
 * Test 3: Breakdown table respects datacenter filter.
 */
test('breakdown table data respects datacenter filter', function () {
    // Create audits with findings in different datacenters
    $auditDc1 = Audit::factory()->create([
        'datacenter_id' => $this->datacenter1->id,
        'name' => 'DC1 Audit',
    ]);
    Finding::factory()->count(5)->critical()->forAudit($auditDc1)->create();

    $auditDc2 = Audit::factory()->create([
        'datacenter_id' => $this->datacenter2->id,
        'name' => 'DC2 Audit',
    ]);
    Finding::factory()->count(10)->high()->forAudit($auditDc2)->create();

    // Request with filter for datacenter1 only
    $response = $this->actingAs($this->admin)
        ->get('/audits/dashboard?datacenter_id='.$this->datacenter1->id);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('auditBreakdown', 1) // Only DC1 audit should appear
            ->where('auditBreakdown.0.name', 'DC1 Audit')
            ->where('auditBreakdown.0.critical', 5)
        );
});

/**
 * Test 4: Active audit progress section respects datacenter filter.
 */
test('active audit progress respects datacenter filter', function () {
    // Create in-progress audits in different datacenters
    $auditDc1 = Audit::factory()
        ->connectionType()
        ->inProgress()
        ->create([
            'datacenter_id' => $this->datacenter1->id,
            'name' => 'DC1 In Progress',
            'due_date' => now()->addDays(14),
        ]);
    AuditConnectionVerification::factory()->count(5)->pending()->forAudit($auditDc1)->create();

    $auditDc2 = Audit::factory()
        ->connectionType()
        ->inProgress()
        ->create([
            'datacenter_id' => $this->datacenter2->id,
            'name' => 'DC2 In Progress',
            'due_date' => now()->addDays(7),
        ]);
    AuditConnectionVerification::factory()->count(3)->pending()->forAudit($auditDc2)->create();

    // Request with filter for datacenter1 only
    $response = $this->actingAs($this->admin)
        ->get('/audits/dashboard?datacenter_id='.$this->datacenter1->id);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('activeAuditProgress', 1) // Only DC1 audit should appear
            ->where('activeAuditProgress.0.name', 'DC1 In Progress')
        );
});

/**
 * Test 5: Active audit progress section respects time period filter.
 */
test('active audit progress respects time period filter', function () {
    // Create recent in-progress audit
    $recentAudit = Audit::factory()
        ->connectionType()
        ->inProgress()
        ->create([
            'datacenter_id' => $this->datacenter1->id,
            'name' => 'Recent In Progress',
            'due_date' => now()->addDays(14),
            'created_at' => now()->subDays(5),
        ]);
    AuditConnectionVerification::factory()->count(5)->pending()->forAudit($recentAudit)->create();

    // Create old in-progress audit
    $oldAudit = Audit::factory()
        ->connectionType()
        ->inProgress()
        ->create([
            'datacenter_id' => $this->datacenter1->id,
            'name' => 'Old In Progress',
            'due_date' => now()->addDays(7),
            'created_at' => now()->subDays(60),
        ]);
    AuditConnectionVerification::factory()->count(3)->pending()->forAudit($oldAudit)->create();

    // Request with 30 days filter
    $response = $this->actingAs($this->admin)
        ->get('/audits/dashboard?time_period=30_days');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('activeAuditProgress', 1) // Only recent audit should appear
            ->where('activeAuditProgress.0.name', 'Recent In Progress')
        );
});

/**
 * Test 6: Breakdown table excludes audits with zero findings.
 */
test('breakdown table only includes audits that have findings', function () {
    // Create audit with findings
    $auditWithFindings = Audit::factory()->create([
        'datacenter_id' => $this->datacenter1->id,
        'name' => 'Audit With Findings',
    ]);
    Finding::factory()->count(3)->critical()->forAudit($auditWithFindings)->create();

    // Create audit without findings
    $auditNoFindings = Audit::factory()->create([
        'datacenter_id' => $this->datacenter1->id,
        'name' => 'Audit Without Findings',
    ]);

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('auditBreakdown', 1) // Only audit with findings
            ->where('auditBreakdown.0.name', 'Audit With Findings')
        );
});

/**
 * Test 7: All dashboard sections work together with combined filters.
 */
test('all dashboard sections return consistent data with combined filters', function () {
    // Create comprehensive test data in datacenter1
    $auditCompleted = Audit::factory()->completed()->create([
        'datacenter_id' => $this->datacenter1->id,
        'created_at' => now()->subDays(5),
    ]);
    $auditInProgress = Audit::factory()
        ->connectionType()
        ->inProgress()
        ->create([
            'datacenter_id' => $this->datacenter1->id,
            'created_at' => now()->subDays(10),
            'due_date' => now()->addDays(7),
        ]);
    AuditConnectionVerification::factory()->count(5)->pending()->forAudit($auditInProgress)->create();

    Finding::factory()->count(2)->critical()->forAudit($auditCompleted)->create();
    Finding::factory()->count(3)->high()->forAudit($auditInProgress)->create();

    // Create data in datacenter2 (should be excluded by filter)
    $auditDc2 = Audit::factory()->create([
        'datacenter_id' => $this->datacenter2->id,
        'created_at' => now()->subDays(5),
    ]);
    Finding::factory()->count(10)->critical()->forAudit($auditDc2)->create();

    // Request with datacenter1 and 30 days filters
    $response = $this->actingAs($this->admin)
        ->get('/audits/dashboard?datacenter_id='.$this->datacenter1->id.'&time_period=30_days');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            // Audit metrics should reflect only DC1 audits
            ->has('auditMetrics', fn ($metrics) => $metrics
                ->where('total', 2) // completed + in_progress
                ->etc()
            )
            // Severity metrics should only count DC1 findings
            ->has('severityMetrics', fn ($metrics) => $metrics
                ->where('critical.count', 2) // Only from DC1
                ->where('high.count', 3)
                ->where('total', 5)
                ->etc()
            )
            // Active progress should only show DC1 in-progress audit
            ->has('activeAuditProgress', 1)
            // Breakdown should only show DC1 audits with findings
            ->has('auditBreakdown', 2)
        );
});

/**
 * Test 8: Auditor datacenter filter dropdown is limited to accessible datacenters.
 *
 * Note: The dashboard shows all organization data by default (aggregate view),
 * but the datacenter filter dropdown only shows datacenters the auditor has access to.
 * This allows auditors to see the big picture but filter to their specific datacenters.
 */
test('auditor datacenter filter dropdown is limited to accessible datacenters', function () {
    // Create auditor with access to only datacenter1
    $auditor = User::factory()->create();
    $auditor->assignRole('Auditor');
    $auditor->datacenters()->attach($this->datacenter1);

    // Create audits in both datacenters
    $auditDc1 = Audit::factory()->create([
        'datacenter_id' => $this->datacenter1->id,
        'name' => 'Accessible DC Audit',
    ]);
    Finding::factory()->count(3)->critical()->forAudit($auditDc1)->create();

    $auditDc2 = Audit::factory()->create([
        'datacenter_id' => $this->datacenter2->id,
        'name' => 'Other DC Audit',
    ]);
    Finding::factory()->count(10)->high()->forAudit($auditDc2)->create();

    // Auditor requests dashboard without explicit filter
    $response = $this->actingAs($auditor)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            // Auditor should only see datacenter1 in filter options
            ->has('datacenterOptions', 1)
            ->where('datacenterOptions.0.name', 'Primary DC')
        );

    // When auditor applies their accessible datacenter filter, data is restricted
    $filteredResponse = $this->actingAs($auditor)
        ->get('/audits/dashboard?datacenter_id='.$this->datacenter1->id);

    $filteredResponse->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('auditMetrics', fn ($metrics) => $metrics
                ->where('total', 1) // Only DC1 audit
                ->etc()
            )
            ->has('severityMetrics', fn ($metrics) => $metrics
                ->where('critical.count', 3) // Only from DC1
                ->where('high.count', 0) // High findings are in DC2
                ->where('total', 3)
                ->etc()
            )
        );
});
