<?php

use App\Enums\AuditStatus;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\Finding;
use App\Models\FindingStatusTransition;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create users with different roles
    $this->admin = User::factory()->create(['name' => 'Admin User']);
    $this->admin->assignRole('Administrator');

    $this->operator = User::factory()->create(['name' => 'Operator User']);
    $this->operator->assignRole('Operator');

    // Create datacenters for testing
    $this->datacenter = Datacenter::factory()->create(['name' => 'Test DC One']);
    $this->secondDatacenter = Datacenter::factory()->create(['name' => 'Test DC Two']);

    // Assign only first datacenter to operator
    $this->operator->datacenters()->attach($this->datacenter->id);
});

/**
 * Test 1: Custom date range filtering works correctly on index endpoint
 *
 * This fills a gap where custom dates weren't tested via the controller endpoint.
 */
test('custom date range filtering excludes audits outside the range', function () {
    // Create audit within custom date range
    $auditInRange = Audit::factory()
        ->completed()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'name' => 'Audit In Range',
            'updated_at' => now()->subDays(10),
        ]);

    // Create audit outside custom date range
    $auditOutOfRange = Audit::factory()
        ->completed()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'name' => 'Audit Out Of Range',
            'updated_at' => now()->subMonths(3),
        ]);

    $startDate = now()->subDays(30)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    $response = $this->actingAs($this->admin)
        ->get("/reports/audit-history?start_date={$startDate}&end_date={$endDate}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.start_date', $startDate)
            ->where('filters.end_date', $endDate)
            ->has('audits.data', 1)
            ->where('audits.data.0.name', 'Audit In Range')
        );
});

/**
 * Test 2: Metrics show correct values when there are no findings
 *
 * Edge case: completed audits with zero findings should display N/A for resolution metrics.
 */
test('metrics handle audits with no findings correctly', function () {
    // Create completed audit with no findings
    Audit::factory()
        ->completed()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'name' => 'Empty Audit',
            'updated_at' => now()->subDays(5),
        ]);

    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('metrics.totalAuditsCompleted.value', 1)
            ->where('metrics.totalFindings.value', 0)
            ->where('metrics.avgResolutionTime.formatted', 'N/A')
            ->where('metrics.avgTimeToFirstResponse.formatted', 'N/A')
        );
});

/**
 * Test 3: Metrics handle findings with no resolved findings correctly
 *
 * Edge case: findings exist but none are resolved yet.
 */
test('metrics handle findings with no resolved findings correctly', function () {
    $audit = Audit::factory()
        ->completed()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'updated_at' => now()->subDays(5),
        ]);

    // Create open findings (not resolved)
    Finding::factory()
        ->forAudit($audit)
        ->critical()
        ->count(2)
        ->create(['status' => FindingStatus::Open]);

    Finding::factory()
        ->forAudit($audit)
        ->high()
        ->create(['status' => FindingStatus::InProgress]);

    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('metrics.totalFindings.value', 3)
            ->where('metrics.totalFindings.bySeverity.critical', 2)
            ->where('metrics.totalFindings.bySeverity.high', 1)
            ->where('metrics.avgResolutionTime.formatted', 'N/A')
        );
});

/**
 * Test 4: Finding trend data structure contains expected fields
 *
 * Verifies trend data aggregation returns properly formatted data with all severity fields.
 */
test('finding trend data contains expected structure and fields', function () {
    $audit = Audit::factory()
        ->completed()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'updated_at' => now()->subDays(5),
        ]);

    Finding::factory()
        ->forAudit($audit)
        ->critical()
        ->create(['created_at' => now()->subDays(2)]);

    Finding::factory()
        ->forAudit($audit)
        ->high()
        ->create(['created_at' => now()->subDays(10)]);

    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history?time_range_preset=30_days');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            // Verify trend data exists and has the expected structure
            ->has('findingTrendData')
            ->has('findingTrendData.0.period')
            ->has('findingTrendData.0.critical')
            ->has('findingTrendData.0.high')
            ->has('findingTrendData.0.medium')
            ->has('findingTrendData.0.low')
        );
});

/**
 * Test 5: Resolution time trend data structure contains expected fields
 *
 * Verifies resolution time trend data contains expected fields for chart rendering.
 */
test('resolution time trend data contains expected structure', function () {
    $audit = Audit::factory()
        ->completed()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'updated_at' => now()->subDays(5),
        ]);

    // Create resolved finding with transition for first response
    $finding = Finding::factory()
        ->forAudit($audit)
        ->resolved()
        ->create([
            'created_at' => now()->subDays(10),
            'resolved_at' => now()->subDays(5),
        ]);

    // Add status transition for first response
    FindingStatusTransition::create([
        'finding_id' => $finding->id,
        'from_status' => FindingStatus::Open,
        'to_status' => FindingStatus::InProgress,
        'transitioned_at' => now()->subDays(9),
        'user_id' => $this->admin->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history?time_range_preset=6_months');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            // Verify resolution time trend data exists and has expected structure
            ->has('resolutionTimeTrendData')
            ->has('resolutionTimeTrendData.0.period')
            ->has('resolutionTimeTrendData.0.avg_resolution_time')
            ->has('resolutionTimeTrendData.0.avg_first_response')
        );
});

/**
 * Test 6: Operator user only sees their assigned datacenter's data in metrics
 *
 * Critical access control test: operator should only see data from their datacenter.
 */
test('operator only sees metrics from assigned datacenters', function () {
    // Create audit in operator's datacenter
    $operatorAudit = Audit::factory()
        ->completed()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'name' => 'Operator DC Audit',
            'updated_at' => now()->subDays(5),
        ]);

    Finding::factory()
        ->forAudit($operatorAudit)
        ->critical()
        ->count(3)
        ->create();

    // Create audit in second datacenter (operator should NOT see this)
    $otherAudit = Audit::factory()
        ->completed()
        ->create([
            'datacenter_id' => $this->secondDatacenter->id,
            'name' => 'Other DC Audit',
            'updated_at' => now()->subDays(5),
        ]);

    Finding::factory()
        ->forAudit($otherAudit)
        ->high()
        ->count(10)
        ->create();

    $response = $this->actingAs($this->operator)
        ->get('/reports/audit-history');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            // Operator should only see 1 audit and 3 findings
            ->where('metrics.totalAuditsCompleted.value', 1)
            ->where('metrics.totalFindings.value', 3)
            ->where('metrics.totalFindings.bySeverity.critical', 3)
            ->where('metrics.totalFindings.bySeverity.high', 0)
            // Only assigned datacenter in options
            ->has('datacenterOptions', 1)
            // Only 1 audit in table
            ->has('audits.data', 1)
            ->where('audits.data.0.name', 'Operator DC Audit')
        );
});

/**
 * Test 7: Combining multiple filters works correctly
 *
 * Tests that datacenter + audit type + custom date filters combine properly.
 */
test('multiple filters combine correctly', function () {
    $startDate = now()->subDays(60)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    // Create connection audit in first datacenter within date range - SHOULD MATCH
    $matchingAudit = Audit::factory()
        ->completed()
        ->connectionType()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'name' => 'Matching Audit',
            'updated_at' => now()->subDays(10),
        ]);

    // Create inventory audit - should NOT match (wrong type)
    Audit::factory()
        ->completed()
        ->inventoryType()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'name' => 'Wrong Type',
            'updated_at' => now()->subDays(10),
        ]);

    // Create connection in wrong datacenter - should NOT match
    Audit::factory()
        ->completed()
        ->connectionType()
        ->create([
            'datacenter_id' => $this->secondDatacenter->id,
            'name' => 'Wrong DC',
            'updated_at' => now()->subDays(10),
        ]);

    // Create connection outside date range - should NOT match
    Audit::factory()
        ->completed()
        ->connectionType()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'name' => 'Outside Date Range',
            'updated_at' => now()->subMonths(4),
        ]);

    $response = $this->actingAs($this->admin)
        ->get("/reports/audit-history?datacenter_id={$this->datacenter->id}&audit_type=connection&start_date={$startDate}&end_date={$endDate}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.datacenter_id', $this->datacenter->id)
            ->where('filters.audit_type', 'connection')
            ->where('filters.start_date', $startDate)
            ->where('filters.end_date', $endDate)
            ->has('audits.data', 1)
            ->where('audits.data.0.name', 'Matching Audit')
        );
});

/**
 * Test 8: Avg resolution time correctly calculates across multiple resolved findings
 *
 * Verifies that the average is calculated correctly, not just returned for one finding.
 */
test('average resolution time correctly calculates across multiple findings', function () {
    $audit = Audit::factory()
        ->completed()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'updated_at' => now()->subDays(5),
        ]);

    // Create first resolved finding: 1 day (1440 minutes) resolution time
    Finding::factory()
        ->forAudit($audit)
        ->resolved()
        ->create([
            'created_at' => now()->subDays(11),
            'resolved_at' => now()->subDays(10),
        ]);

    // Create second resolved finding: 2 days (2880 minutes) resolution time
    Finding::factory()
        ->forAudit($audit)
        ->resolved()
        ->create([
            'created_at' => now()->subDays(12),
            'resolved_at' => now()->subDays(10),
        ]);

    // Create third resolved finding: 3 days (4320 minutes) resolution time
    Finding::factory()
        ->forAudit($audit)
        ->resolved()
        ->create([
            'created_at' => now()->subDays(13),
            'resolved_at' => now()->subDays(10),
        ]);

    // Average should be (1440 + 2880 + 4320) / 3 = 2880 minutes = 48 hours = 2 days
    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history');

    $response->assertOk();

    // Extract the page data to verify the calculation
    $page = $response->original;
    $avgResolutionValue = $page->getData()['page']['props']['metrics']['avgResolutionTime']['value'];
    $avgResolutionFormatted = $page->getData()['page']['props']['metrics']['avgResolutionTime']['formatted'];

    // Allow for slight timing differences (1% tolerance)
    expect($avgResolutionValue)->toBeGreaterThan(2800);
    expect($avgResolutionValue)->toBeLessThan(3000);

    // Formatted should show "2 days"
    expect($avgResolutionFormatted)->toBe('2 days');
});
