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

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    $this->datacenter = Datacenter::factory()->create(['name' => 'Main DC']);
});

/**
 * Test 1: Index page renders with correct structure and sections
 */
test('index page renders with correct structure and sections', function () {
    // Create test data
    $audit = Audit::factory()
        ->completed()
        ->create(['datacenter_id' => $this->datacenter->id]);

    Finding::factory()
        ->forAudit($audit)
        ->critical()
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
            // Verify metrics structure
            ->has('metrics.totalAuditsCompleted.value')
            ->has('metrics.totalAuditsCompleted.sparkline')
            ->has('metrics.totalFindings.value')
            ->has('metrics.totalFindings.bySeverity')
            ->has('metrics.avgResolutionTime.value')
            ->has('metrics.avgResolutionTime.formatted')
            ->has('metrics.avgTimeToFirstResponse.value')
            ->has('metrics.avgTimeToFirstResponse.formatted')
            // Verify filter options
            ->has('datacenterOptions')
            ->has('auditTypeOptions')
            // Verify filters
            ->has('filters.time_range_preset')
            ->has('filters.datacenter_id')
            ->has('filters.audit_type')
            ->has('filters.sort_by')
            ->has('filters.sort_direction')
            // Verify trend data
            ->has('findingTrendData')
            ->has('resolutionTimeTrendData')
            // Verify paginated audits structure
            ->has('audits.data')
            ->has('audits.current_page')
            ->has('audits.last_page')
            ->has('audits.per_page')
            ->has('audits.total')
        );
});

/**
 * Test 2: Filters update URL parameters correctly
 */
test('filters update URL parameters correctly', function () {
    Audit::factory()
        ->completed()
        ->create(['datacenter_id' => $this->datacenter->id]);

    // Test with time range preset filter
    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history?time_range_preset=30_days');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.time_range_preset', '30_days')
        );

    // Test with datacenter filter
    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history?datacenter_id='.$this->datacenter->id);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.datacenter_id', $this->datacenter->id)
        );

    // Test with audit type filter
    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history?audit_type=connection');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.audit_type', 'connection')
        );

    // Test with multiple filters combined
    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history?time_range_preset=6_months&datacenter_id='.$this->datacenter->id.'&audit_type=inventory');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.time_range_preset', '6_months')
            ->where('filters.datacenter_id', $this->datacenter->id)
            ->where('filters.audit_type', 'inventory')
        );

    // Test with sort parameters
    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history?sort_by=total_findings&sort_direction=asc');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort_by', 'total_findings')
            ->where('filters.sort_direction', 'asc')
        );
});

/**
 * Test 3: Export buttons generate correct URLs with current filters
 */
test('export buttons generate correct URLs with current filters', function () {
    Audit::factory()
        ->completed()
        ->create(['datacenter_id' => $this->datacenter->id]);

    // Test PDF export with filters
    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history/export/pdf?time_range_preset=30_days&datacenter_id='.$this->datacenter->id);

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');

    // Test CSV export with filters
    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history/export/csv?time_range_preset=30_days&datacenter_id='.$this->datacenter->id);

    $response->assertOk();
    $contentDisposition = $response->headers->get('content-disposition');
    expect($contentDisposition)->toContain('audit-history-report-');
    expect($contentDisposition)->toContain('.csv');

    // Test exports respect audit type filter
    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history/export/csv?audit_type=connection');

    $response->assertOk();
});

/**
 * Test 4: Table pagination and sorting work
 */
test('table pagination and sorting work', function () {
    // Create multiple audits to test pagination
    for ($i = 0; $i < 20; $i++) {
        $audit = Audit::factory()
            ->completed()
            ->create([
                'datacenter_id' => $this->datacenter->id,
                'updated_at' => now()->subDays($i),
            ]);

        // Add varying number of findings
        Finding::factory()
            ->count(rand(1, 5))
            ->forAudit($audit)
            ->create();
    }

    // Test first page (default)
    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('audits.current_page', 1)
            ->where('audits.per_page', 15)
            ->where('audits.total', 20)
            ->has('audits.data', 15) // First page has 15 items
        );

    // Test second page
    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history?page=2');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('audits.current_page', 2)
            ->has('audits.data', 5) // Second page has remaining 5 items
        );

    // Test sorting by completion date ascending
    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history?sort_by=completion_date&sort_direction=asc');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort_by', 'completion_date')
            ->where('filters.sort_direction', 'asc')
        );

    // Test sorting by total findings
    $response = $this->actingAs($this->admin)
        ->get('/reports/audit-history?sort_by=total_findings&sort_direction=desc');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort_by', 'total_findings')
            ->where('filters.sort_direction', 'desc')
        );
});
