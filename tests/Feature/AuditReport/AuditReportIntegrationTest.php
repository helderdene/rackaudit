<?php

/**
 * Integration tests for Audit Report Generation feature.
 *
 * These tests fill critical gaps in test coverage identified during Task Group 5.
 * They focus on end-to-end workflows, edge cases, and integration points.
 */

use App\Enums\AuditStatus;
use App\Enums\AuditType;
use App\Enums\FindingSeverity;
use App\Jobs\GenerateAuditReportJob;
use App\Models\Audit;
use App\Models\AuditReport;
use App\Models\Datacenter;
use App\Models\Finding;
use App\Models\User;
use App\Services\AuditReportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    $this->datacenter = Datacenter::factory()->create(['name' => 'Integration DC']);
});

/**
 * Test 1: Audit with zero findings generates report successfully
 *
 * Edge case: ensures the system handles audits with no findings gracefully.
 */
test('generates report for audit with zero findings', function () {
    $audit = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'status' => AuditStatus::Completed,
            'type' => AuditType::Connection,
            'created_by' => $this->admin->id,
        ]);

    // No findings created for this audit

    $service = new AuditReportService;
    $report = $service->generateReport($audit, $this->admin);

    expect($report)->toBeInstanceOf(AuditReport::class);
    expect($report->audit_id)->toBe($audit->id);
    expect($report->file_size_bytes)->toBeGreaterThan(0);

    // Verify executive summary with zero findings
    $summary = $service->calculateExecutiveSummary($audit);
    expect($summary['total_findings'])->toBe(0);
    expect($summary['resolution_rate'])->toBe(0.0);
    expect($summary['critical_count'])->toBe(0);

    Storage::disk('local')->assertExists($report->file_path);
});

/**
 * Test 2: Reports Index filters by audit name search
 *
 * Verifies the search filter functionality works correctly.
 */
test('reports index filters by audit name search', function () {
    $this->withoutVite();

    $audit1 = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'name' => 'Quarterly Network Audit',
            'status' => AuditStatus::Completed,
            'created_by' => $this->admin->id,
        ]);

    $audit2 = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'name' => 'Annual Security Review',
            'status' => AuditStatus::Completed,
            'created_by' => $this->admin->id,
        ]);

    AuditReport::factory()->forAudit($audit1)->generatedBy($this->admin)->create();
    AuditReport::factory()->forAudit($audit2)->generatedBy($this->admin)->create();

    // Search for "Network"
    $response = $this->actingAs($this->admin)
        ->get('/reports?search=Network');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->has('reports.data', 1)
            ->where('reports.data.0.audit_name', 'Quarterly Network Audit')
        );

    // Search for "Security"
    $response = $this->actingAs($this->admin)
        ->get('/reports?search=Security');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->has('reports.data', 1)
            ->where('reports.data.0.audit_name', 'Annual Security Review')
        );
});

/**
 * Test 3: Reports Index filters by date range
 *
 * Verifies the date range filter functionality works correctly.
 */
test('reports index filters by date range', function () {
    $this->withoutVite();

    $audit = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'status' => AuditStatus::Completed,
            'created_by' => $this->admin->id,
        ]);

    // Create reports with different dates
    AuditReport::factory()
        ->forAudit($audit)
        ->generatedBy($this->admin)
        ->create(['generated_at' => '2025-12-01 10:00:00']);

    AuditReport::factory()
        ->forAudit($audit)
        ->generatedBy($this->admin)
        ->create(['generated_at' => '2025-12-15 10:00:00']);

    AuditReport::factory()
        ->forAudit($audit)
        ->generatedBy($this->admin)
        ->create(['generated_at' => '2025-12-28 10:00:00']);

    // Filter for early December only
    $response = $this->actingAs($this->admin)
        ->get('/reports?date_from=2025-12-01&date_to=2025-12-10');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->has('reports.data', 1)
        );

    // Filter for mid to late December
    $response = $this->actingAs($this->admin)
        ->get('/reports?date_from=2025-12-14&date_to=2025-12-31');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->has('reports.data', 2)
        );
});

/**
 * Test 4: Reports Index sorts by audit name
 *
 * Verifies sorting functionality works correctly.
 */
test('reports index sorts by audit name', function () {
    $this->withoutVite();

    $auditZ = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'name' => 'Zebra Audit',
            'status' => AuditStatus::Completed,
            'created_by' => $this->admin->id,
        ]);

    $auditA = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'name' => 'Alpha Audit',
            'status' => AuditStatus::Completed,
            'created_by' => $this->admin->id,
        ]);

    $auditM = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'name' => 'Middle Audit',
            'status' => AuditStatus::Completed,
            'created_by' => $this->admin->id,
        ]);

    AuditReport::factory()->forAudit($auditZ)->generatedBy($this->admin)->create();
    AuditReport::factory()->forAudit($auditA)->generatedBy($this->admin)->create();
    AuditReport::factory()->forAudit($auditM)->generatedBy($this->admin)->create();

    // Sort ascending by audit name
    $response = $this->actingAs($this->admin)
        ->get('/reports?sort=audit_name&direction=asc');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->has('reports.data', 3)
            ->where('reports.data.0.audit_name', 'Alpha Audit')
            ->where('reports.data.2.audit_name', 'Zebra Audit')
        );

    // Sort descending by audit name
    $response = $this->actingAs($this->admin)
        ->get('/reports?sort=audit_name&direction=desc');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->where('reports.data.0.audit_name', 'Zebra Audit')
            ->where('reports.data.2.audit_name', 'Alpha Audit')
        );
});

/**
 * Test 5: Full workflow - generate report and download
 *
 * Integration test: POST to generate, verify AuditReport created, file exists, download works
 */
test('full workflow generates report and enables download', function () {
    Queue::fake();

    $audit = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'status' => AuditStatus::Completed,
            'type' => AuditType::Connection,
            'created_by' => $this->admin->id,
        ]);
    $audit->assignees()->attach($this->admin->id);

    Finding::factory()->forAudit($audit)->critical()->create();
    Finding::factory()->forAudit($audit)->high()->create();

    // Step 1: Generate report via POST
    $response = $this->actingAs($this->admin)
        ->post("/audits/{$audit->id}/reports");

    $response->assertRedirect();
    $response->assertSessionHas('success');

    Queue::assertPushed(GenerateAuditReportJob::class, function ($job) use ($audit) {
        return $job->auditId === $audit->id && $job->userId === $this->admin->id;
    });

    // Step 2: Manually run the service to create the report (simulating job execution)
    $service = new AuditReportService;
    $report = $service->generateReport($audit, $this->admin);

    expect(AuditReport::count())->toBe(1);
    expect($report->audit_id)->toBe($audit->id);
    Storage::disk('local')->assertExists($report->file_path);

    // Step 3: Download the report
    $downloadResponse = $this->actingAs($this->admin)
        ->get("/reports/{$report->id}/download");

    $downloadResponse->assertOk();
    $downloadResponse->assertHeader('Content-Type', 'application/pdf');
});

/**
 * Test 6: Report generation with findings across all severity levels
 *
 * Edge case: verifies findings are properly grouped when all severities present.
 */
test('report includes findings across all severity levels', function () {
    $audit = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'status' => AuditStatus::InProgress,
            'type' => AuditType::Connection,
            'created_by' => $this->admin->id,
        ]);

    // Create findings with all severity levels
    Finding::factory()->forAudit($audit)->critical()->create(['title' => 'Critical Finding']);
    Finding::factory()->forAudit($audit)->high()->create(['title' => 'High Finding']);
    Finding::factory()->forAudit($audit)->medium()->create(['title' => 'Medium Finding']);
    Finding::factory()->forAudit($audit)->low()->create(['title' => 'Low Finding']);

    $service = new AuditReportService;
    $groupedFindings = $service->groupFindingsBySeverity($audit);

    // All four severity levels should be present
    expect($groupedFindings)->toHaveCount(4);
    expect($groupedFindings)->toHaveKey(FindingSeverity::Critical->value);
    expect($groupedFindings)->toHaveKey(FindingSeverity::High->value);
    expect($groupedFindings)->toHaveKey(FindingSeverity::Medium->value);
    expect($groupedFindings)->toHaveKey(FindingSeverity::Low->value);

    // Verify correct order (Critical first, Low last)
    $severityOrder = array_keys($groupedFindings);
    expect($severityOrder[0])->toBe(FindingSeverity::Critical->value);
    expect($severityOrder[3])->toBe(FindingSeverity::Low->value);

    // Generate actual report
    $report = $service->generateReport($audit, $this->admin);
    expect($report)->toBeInstanceOf(AuditReport::class);
    Storage::disk('local')->assertExists($report->file_path);
});

/**
 * Test 7: Inventory audit skips connection comparison section
 *
 * Edge case: generating report for inventory audit (no connection comparison)
 */
test('inventory audit report skips connection comparison section', function () {
    $audit = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'status' => AuditStatus::Completed,
            'type' => AuditType::Inventory,
            'created_by' => $this->admin->id,
        ]);

    Finding::factory()->forAudit($audit)->medium()->count(3)->create();

    $service = new AuditReportService;

    // Connection comparison should be null for inventory audits
    $connectionComparison = $service->buildConnectionComparisonSummary($audit);
    expect($connectionComparison)->toBeNull();

    // Report should still generate successfully
    $report = $service->generateReport($audit, $this->admin);
    expect($report)->toBeInstanceOf(AuditReport::class);
    expect($report->audit->type)->toBe(AuditType::Inventory);
    Storage::disk('local')->assertExists($report->file_path);
});

/**
 * Test 8: Report history shows reports in correct order on audit page
 *
 * Verifies that the most recent report appears first in the report history.
 */
test('report history shows most recent reports first', function () {
    $this->withoutVite();

    $audit = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'status' => AuditStatus::Completed,
            'type' => AuditType::Connection,
            'created_by' => $this->admin->id,
        ]);

    // Create reports in non-chronological order
    $oldestReport = AuditReport::factory()
        ->forAudit($audit)
        ->generatedBy($this->admin)
        ->create(['generated_at' => now()->subDays(5)]);

    $newestReport = AuditReport::factory()
        ->forAudit($audit)
        ->generatedBy($this->admin)
        ->create(['generated_at' => now()]);

    $middleReport = AuditReport::factory()
        ->forAudit($audit)
        ->generatedBy($this->admin)
        ->create(['generated_at' => now()->subDays(2)]);

    $response = $this->actingAs($this->admin)
        ->get("/audits/{$audit->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Audits/Show')
            ->has('reports', 3)
            ->where('reports.0.id', $newestReport->id)
            ->where('reports.2.id', $oldestReport->id)
        );
});

/**
 * Test 9: Non-admin users can only see reports for their assigned audits
 *
 * Authorization: ensures role-based access control for reports index.
 */
test('non-admin users only see reports for assigned audits', function () {
    $this->withoutVite();

    $auditor = User::factory()->create();
    $auditor->assignRole('Auditor');
    $auditor->datacenters()->attach($this->datacenter);

    // Audit assigned to auditor
    $assignedAudit = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'name' => 'Assigned Audit',
            'status' => AuditStatus::Completed,
            'created_by' => $this->admin->id,
        ]);
    $assignedAudit->assignees()->attach($auditor->id);

    // Audit NOT assigned to auditor
    $unassignedAudit = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'name' => 'Unassigned Audit',
            'status' => AuditStatus::Completed,
            'created_by' => $this->admin->id,
        ]);

    // Create reports for both audits
    AuditReport::factory()->forAudit($assignedAudit)->generatedBy($this->admin)->create();
    AuditReport::factory()->forAudit($unassignedAudit)->generatedBy($this->admin)->create();

    // Auditor should only see report for assigned audit
    $response = $this->actingAs($auditor)
        ->get('/reports');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->has('reports.data', 1)
            ->where('reports.data.0.audit_name', 'Assigned Audit')
        );

    // Admin should see both reports
    $response = $this->actingAs($this->admin)
        ->get('/reports');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->has('reports.data', 2)
        );
});

/**
 * Test 10: Resolution rate calculation handles mixed finding statuses
 *
 * Verifies the resolution rate formula: (resolved / total) * 100
 */
test('resolution rate correctly calculates with mixed statuses', function () {
    $audit = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'status' => AuditStatus::InProgress,
            'type' => AuditType::Connection,
            'created_by' => $this->admin->id,
        ]);

    // Create 10 findings with different statuses
    // 3 resolved (should count)
    Finding::factory()->forAudit($audit)->resolved()->count(3)->create();
    // 2 open
    Finding::factory()->forAudit($audit)->open()->count(2)->create();
    // 2 in progress
    Finding::factory()->forAudit($audit)->inProgress()->count(2)->create();
    // 2 pending review
    Finding::factory()->forAudit($audit)->pendingReview()->count(2)->create();
    // 1 deferred
    Finding::factory()->forAudit($audit)->deferred()->create();

    $service = new AuditReportService;
    $summary = $service->calculateExecutiveSummary($audit);

    // 10 total findings, 3 resolved = 30% resolution rate
    expect($summary['total_findings'])->toBe(10);
    expect($summary['resolution_rate'])->toBe(30.0);
});
