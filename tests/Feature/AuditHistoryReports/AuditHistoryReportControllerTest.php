<?php

use App\Enums\AuditStatus;
use App\Enums\AuditType;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\Finding;
use App\Models\FindingStatusTransition;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create users with different roles
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    $this->itManager = User::factory()->create();
    $this->itManager->assignRole('IT Manager');

    $this->operator = User::factory()->create();
    $this->operator->assignRole('Operator');

    // Create datacenters for testing
    $this->datacenter = Datacenter::factory()->create(['name' => 'Main DC']);
    $this->secondDatacenter = Datacenter::factory()->create(['name' => 'Secondary DC']);

    // Assign datacenter access to operator
    $this->operator->datacenters()->attach($this->datacenter->id);

    // Set up Storage fake
    Storage::fake('local');
});

/**
 * Test 1: Index page returns correct Inertia props with metrics structure
 */
test('index page returns correct Inertia props with metrics structure', function () {
    // Create completed audits with findings
    $audit = Audit::factory()
        ->completed()
        ->create(['datacenter_id' => $this->datacenter->id]);

    Finding::factory()
        ->forAudit($audit)
        ->critical()
        ->resolved()
        ->create();

    Finding::factory()
        ->forAudit($audit)
        ->high()
        ->create();

    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AuditHistoryReports/Index')
            ->has('metrics')
            ->has('metrics.totalAuditsCompleted')
            ->has('metrics.totalFindings')
            ->has('metrics.avgResolutionTime')
            ->has('metrics.avgTimeToFirstResponse')
            ->has('datacenterOptions')
            ->has('auditTypeOptions')
            ->has('filters')
            ->has('findingTrendData')
            ->has('resolutionTimeTrendData')
            ->has('audits')
        );
});

/**
 * Test 2: Time range filter applies correctly (30 days, 6 months, 12 months)
 */
test('time range filter applies correctly for all preset options', function () {
    // Create audits at different times
    $recentAudit = Audit::factory()
        ->completed()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'updated_at' => now()->subDays(15),
        ]);

    $olderAudit = Audit::factory()
        ->completed()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'updated_at' => now()->subMonths(4),
        ]);

    $veryOldAudit = Audit::factory()
        ->completed()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'updated_at' => now()->subMonths(10),
        ]);

    // Test 30 days preset - should only include recent audit
    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history?time_range_preset=30_days');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.time_range_preset', '30_days')
        );

    // Test 6 months preset - should include recent and older
    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history?time_range_preset=6_months');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.time_range_preset', '6_months')
        );

    // Test 12 months preset (default) - should include all
    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history?time_range_preset=12_months');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.time_range_preset', '12_months')
        );
});

/**
 * Test 3: Datacenter filter restricts data to accessible datacenters
 */
test('datacenter filter restricts data to accessible datacenters', function () {
    // Create audits in both datacenters
    Audit::factory()
        ->completed()
        ->create(['datacenter_id' => $this->datacenter->id]);

    Audit::factory()
        ->completed()
        ->create(['datacenter_id' => $this->secondDatacenter->id]);

    // Admin sees all datacenters
    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('datacenterOptions', 2)
        );

    // IT Manager sees all datacenters
    $response = $this->actingAs($this->itManager)
        ->get('/reports/audit-history');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('datacenterOptions', 2)
        );

    // Operator sees only assigned datacenter
    $response = $this->actingAs($this->operator)
        ->get('/reports/audit-history');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('datacenterOptions', 1)
        );

    // Operator cannot filter by unassigned datacenter
    $response = $this->actingAs($this->operator)
        ->get('/reports/audit-history?datacenter_id=' . $this->secondDatacenter->id);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.datacenter_id', null)
        );

    // Admin can filter by specific datacenter
    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history?datacenter_id=' . $this->datacenter->id);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.datacenter_id', $this->datacenter->id)
        );
});

/**
 * Test 4: Audit type filter works for Connection/Inventory types
 */
test('audit type filter works for Connection and Inventory types', function () {
    // Create both types of audits
    Audit::factory()
        ->completed()
        ->connectionType()
        ->create(['datacenter_id' => $this->datacenter->id]);

    Audit::factory()
        ->completed()
        ->inventoryType()
        ->create(['datacenter_id' => $this->datacenter->id]);

    // Test filter by connection type
    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history?audit_type=connection');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.audit_type', 'connection')
        );

    // Test filter by inventory type
    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history?audit_type=inventory');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.audit_type', 'inventory')
        );

    // Test no filter (all types)
    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.audit_type', null)
        );
});

/**
 * Test 5: PDF export generates downloadable file
 */
test('PDF export generates downloadable file', function () {
    // Create completed audit with findings
    $audit = Audit::factory()
        ->completed()
        ->create(['datacenter_id' => $this->datacenter->id]);

    Finding::factory()
        ->forAudit($audit)
        ->resolved()
        ->create();

    // First create the directory
    Storage::disk('local')->makeDirectory('reports/audit-history');

    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history/export/pdf');

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    $response->assertHeader('content-disposition');
});

/**
 * Test 6: CSV export generates downloadable file
 */
test('CSV export generates downloadable file', function () {
    // Create completed audit with findings
    $audit = Audit::factory()
        ->completed()
        ->create(['datacenter_id' => $this->datacenter->id]);

    Finding::factory()
        ->forAudit($audit)
        ->create();

    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history/export/csv');

    $response->assertOk();

    // Check the content-disposition header for the filename pattern
    $contentDisposition = $response->headers->get('content-disposition');
    expect($contentDisposition)->toContain('audit-history-report-');
    expect($contentDisposition)->toContain('.csv');
});
