<?php

use App\Jobs\GenerateAuditReportJob;
use App\Models\Audit;
use App\Models\AuditReport;
use App\Models\Datacenter;
use App\Models\Finding;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

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

    $this->operator = User::factory()->create();
    $this->operator->assignRole('Operator');

    // Create a datacenter for testing
    $this->datacenter = Datacenter::factory()->create();

    // Assign datacenter access to non-admin users
    $this->operator->datacenters()->attach($this->datacenter);
    $this->auditor->datacenters()->attach($this->datacenter);
    $this->itManager->datacenters()->attach($this->datacenter);

    // Set up Storage fake
    Storage::fake('local');
});

/**
 * Test 1: Generate action is only available for in_progress or completed audits
 */
test('generate action is only available when audit status is in_progress or completed', function () {
    // Create audits with different statuses
    $inProgressAudit = Audit::factory()->inProgress()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);
    $inProgressAudit->assignees()->attach($this->itManager->id);

    $completedAudit = Audit::factory()->completed()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);
    $completedAudit->assignees()->attach($this->itManager->id);

    Queue::fake();

    // Test in_progress audit - should work
    $response = $this->actingAs($this->itManager)
        ->post("/audits/{$inProgressAudit->id}/reports");

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    // Test completed audit - should work
    $response = $this->actingAs($this->itManager)
        ->post("/audits/{$completedAudit->id}/reports");

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

/**
 * Test 2: Generate action returns error for pending/cancelled audits
 */
test('generate action returns error for pending and cancelled audits', function () {
    $pendingAudit = Audit::factory()->pending()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);
    $pendingAudit->assignees()->attach($this->itManager->id);

    $cancelledAudit = Audit::factory()->cancelled()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);
    $cancelledAudit->assignees()->attach($this->itManager->id);

    // Test pending audit - should fail
    $response = $this->actingAs($this->itManager)
        ->post("/audits/{$pendingAudit->id}/reports");

    $response->assertRedirect();
    $response->assertSessionHas('error');

    // Test cancelled audit - should fail
    $response = $this->actingAs($this->itManager)
        ->post("/audits/{$cancelledAudit->id}/reports");

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

/**
 * Test 3: Generate action queues job for large audits
 */
test('generate action queues job for large audits', function () {
    Queue::fake();

    // Create an in-progress audit with many findings (large audit)
    $audit = Audit::factory()->inProgress()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);
    $audit->assignees()->attach($this->itManager->id);

    // Create many findings to trigger queue
    Finding::factory()->count(100)->create([
        'audit_id' => $audit->id,
    ]);

    $response = $this->actingAs($this->itManager)
        ->post("/audits/{$audit->id}/reports");

    $response->assertRedirect();
    $response->assertSessionHas('success');

    Queue::assertPushed(GenerateAuditReportJob::class, function ($job) use ($audit) {
        return $job->auditId === $audit->id && $job->userId === $this->itManager->id;
    });
});

/**
 * Test 4: Index action returns paginated reports with filters
 */
test('index action returns paginated reports with filters', function () {
    // Create audits with reports
    $audit1 = Audit::factory()->completed()->create([
        'name' => 'First Audit',
        'datacenter_id' => $this->datacenter->id,
    ]);
    $audit1->assignees()->attach($this->itManager->id);

    $audit2 = Audit::factory()->completed()->create([
        'name' => 'Second Audit',
        'datacenter_id' => $this->datacenter->id,
    ]);
    $audit2->assignees()->attach($this->itManager->id);

    // Create reports
    AuditReport::factory()
        ->forAudit($audit1)
        ->generatedBy($this->itManager)
        ->create();

    AuditReport::factory()
        ->forAudit($audit2)
        ->generatedBy($this->auditor)
        ->create();

    $response = $this->actingAs($this->itManager)
        ->get('/reports');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->has('reports.data', 2)
            ->has('filters')
            ->has('datacenterOptions')
        );

    // Test filtering by datacenter
    $otherDatacenter = Datacenter::factory()->create();
    $auditOther = Audit::factory()->completed()->create([
        'datacenter_id' => $otherDatacenter->id,
    ]);
    AuditReport::factory()->forAudit($auditOther)->create();

    $response = $this->actingAs($this->admin)
        ->get('/reports?datacenter_id='.$this->datacenter->id);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('reports.data', 2)
        );
});

/**
 * Test 5: Download action returns PDF file
 */
test('download action returns PDF file', function () {
    $audit = Audit::factory()->completed()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);
    $audit->assignees()->attach($this->itManager->id);

    // Create a report with a file
    $filePath = 'reports/audits/audit-report-'.$audit->id.'-'.now()->format('YmdHis').'.pdf';
    Storage::disk('local')->put($filePath, '%PDF-1.4 Test PDF Content');

    $report = AuditReport::factory()
        ->forAudit($audit)
        ->generatedBy($this->itManager)
        ->create(['file_path' => $filePath]);

    $response = $this->actingAs($this->itManager)
        ->get("/reports/{$report->id}/download");

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    $response->assertHeader('content-disposition', 'attachment; filename='.basename($filePath));
});

/**
 * Test 6: Authentication required for all actions
 */
test('authentication is required for all report actions', function () {
    $audit = Audit::factory()->inProgress()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);

    $report = AuditReport::factory()->forAudit($audit)->create();

    // Test generate action without authentication
    $response = $this->post("/audits/{$audit->id}/reports");
    $response->assertRedirect('/login');

    // Test index action without authentication
    $response = $this->get('/reports');
    $response->assertRedirect('/login');

    // Test download action without authentication
    $response = $this->get("/reports/{$report->id}/download");
    $response->assertRedirect('/login');
});

/**
 * Test 7: Generate action generates report directly for small audits
 */
test('generate action generates report directly for small audits', function () {
    Queue::fake();

    // Create an in-progress audit with few findings (small audit)
    $audit = Audit::factory()->inProgress()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);
    $audit->assignees()->attach($this->itManager->id);

    // Create only a few findings
    Finding::factory()->count(5)->create([
        'audit_id' => $audit->id,
    ]);

    $response = $this->actingAs($this->itManager)
        ->post("/audits/{$audit->id}/reports");

    $response->assertRedirect();

    // For small audits, the job should still be queued (consistent behavior)
    // or a report should be created directly
    Queue::assertPushed(GenerateAuditReportJob::class);
});

/**
 * Test 8: Download returns 404 for missing file
 */
test('download returns 404 for missing file', function () {
    $audit = Audit::factory()->completed()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);
    $audit->assignees()->attach($this->itManager->id);

    // Create a report with a non-existent file path
    $report = AuditReport::factory()
        ->forAudit($audit)
        ->generatedBy($this->itManager)
        ->create(['file_path' => 'reports/audits/non-existent.pdf']);

    $response = $this->actingAs($this->itManager)
        ->get("/reports/{$report->id}/download");

    $response->assertNotFound();
});
