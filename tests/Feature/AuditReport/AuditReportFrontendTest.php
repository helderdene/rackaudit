<?php

use App\Enums\AuditStatus;
use App\Enums\AuditType;
use App\Models\Audit;
use App\Models\AuditReport;
use App\Models\Datacenter;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
    Storage::fake('local');

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    // Create IT Manager user
    $this->itManager = User::factory()->create();
    $this->itManager->assignRole('IT Manager');

    // Create datacenter for audits
    $this->datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
});

/**
 * Test 1: Generate Report button only visible for in_progress audits
 */
test('generate report button visibility for in_progress audit', function () {
    $audit = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'status' => AuditStatus::InProgress,
            'type' => AuditType::Connection,
            'created_by' => $this->admin->id,
        ]);

    $response = $this->actingAs($this->admin)
        ->get("/audits/{$audit->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Audits/Show')
            ->has('audit', fn (Assert $auditData) => $auditData
                ->where('id', $audit->id)
                ->where('status', 'in_progress')
                ->etc()
            )
            ->where('can_generate_report', true)
        );
});

/**
 * Test 2: Generate Report button only visible for completed audits
 */
test('generate report button visibility for completed audit', function () {
    $audit = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'status' => AuditStatus::Completed,
            'type' => AuditType::Connection,
            'created_by' => $this->admin->id,
        ]);

    $response = $this->actingAs($this->admin)
        ->get("/audits/{$audit->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Audits/Show')
            ->where('can_generate_report', true)
        );
});

/**
 * Test 3: Generate Report button NOT visible for pending audits
 */
test('generate report button not visible for pending audit', function () {
    $audit = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'status' => AuditStatus::Pending,
            'type' => AuditType::Connection,
            'created_by' => $this->admin->id,
        ]);

    $response = $this->actingAs($this->admin)
        ->get("/audits/{$audit->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Audits/Show')
            ->where('can_generate_report', false)
        );
});

/**
 * Test 4: Report History section displays on Audit Show page
 */
test('report history section displays on audit show page', function () {
    $audit = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'status' => AuditStatus::Completed,
            'type' => AuditType::Connection,
            'created_by' => $this->admin->id,
        ]);

    // Create some reports for this audit
    $report1 = AuditReport::factory()
        ->forAudit($audit)
        ->generatedBy($this->admin)
        ->create([
            'generated_at' => now()->subDay(),
            'file_size_bytes' => 150000,
        ]);

    $report2 = AuditReport::factory()
        ->forAudit($audit)
        ->generatedBy($this->itManager)
        ->create([
            'generated_at' => now(),
            'file_size_bytes' => 200000,
        ]);

    $response = $this->actingAs($this->admin)
        ->get("/audits/{$audit->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Audits/Show')
            ->has('reports', 2)
            ->has('reports.0', fn (Assert $report) => $report
                ->has('id')
                ->has('generated_at')
                ->has('generator_name')
                ->has('file_size_formatted')
                ->has('download_url')
                ->etc()
            )
        );
});

/**
 * Test 5: Reports Index page renders with filters
 */
test('reports index page renders with filters', function () {
    // Create some reports
    $audit = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'status' => AuditStatus::Completed,
            'type' => AuditType::Connection,
            'created_by' => $this->admin->id,
        ]);

    AuditReport::factory()
        ->forAudit($audit)
        ->generatedBy($this->admin)
        ->count(3)
        ->create();

    $response = $this->actingAs($this->admin)
        ->get('/reports');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->has('reports.data', 3)
            ->has('reports.data.0', fn (Assert $report) => $report
                ->has('id')
                ->has('audit_id')
                ->has('audit_name')
                ->has('datacenter_name')
                ->has('generator_name')
                ->has('generated_at')
                ->has('file_size_bytes')
                ->has('file_size_formatted')
                ->etc()
            )
            ->has('filters')
            ->has('datacenterOptions')
        );
});

/**
 * Test 6: Reports Index page filters by datacenter
 */
test('reports index page filters by datacenter', function () {
    $datacenter2 = Datacenter::factory()->create(['name' => 'Test DC 2']);

    $audit1 = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'status' => AuditStatus::Completed,
            'type' => AuditType::Connection,
            'created_by' => $this->admin->id,
        ]);

    $audit2 = Audit::factory()
        ->for($datacenter2)
        ->create([
            'status' => AuditStatus::Completed,
            'type' => AuditType::Connection,
            'created_by' => $this->admin->id,
        ]);

    // Create reports for both audits
    AuditReport::factory()->forAudit($audit1)->generatedBy($this->admin)->create();
    AuditReport::factory()->forAudit($audit2)->generatedBy($this->admin)->create();

    // Filter by first datacenter
    $response = $this->actingAs($this->admin)
        ->get('/reports?datacenter_id='.$this->datacenter->id);

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->has('reports.data', 1)
            ->where('reports.data.0.datacenter_name', 'Test DC')
        );
});

/**
 * Test 7: Download link functionality works
 */
test('download link functionality works', function () {
    $audit = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'status' => AuditStatus::Completed,
            'type' => AuditType::Connection,
            'created_by' => $this->admin->id,
        ]);

    // Create the report with a file
    $filePath = 'reports/audits/audit-report-1-20251229123456.pdf';
    Storage::disk('local')->put($filePath, 'PDF content here');

    $report = AuditReport::factory()
        ->forAudit($audit)
        ->generatedBy($this->admin)
        ->create([
            'file_path' => $filePath,
        ]);

    // Test download
    $response = $this->actingAs($this->admin)
        ->get("/reports/{$report->id}/download");

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

/**
 * Test 8: Empty report history shows appropriate message
 */
test('empty report history shows appropriate empty state', function () {
    $audit = Audit::factory()
        ->for($this->datacenter)
        ->create([
            'status' => AuditStatus::InProgress,
            'type' => AuditType::Connection,
            'created_by' => $this->admin->id,
        ]);

    $response = $this->actingAs($this->admin)
        ->get("/audits/{$audit->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Audits/Show')
            ->has('reports', 0)
        );
});
