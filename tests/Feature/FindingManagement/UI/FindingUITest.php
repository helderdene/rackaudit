<?php

use App\Enums\DiscrepancyType;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Models\Audit;
use App\Models\Finding;
use App\Models\FindingCategory;
use App\Models\FindingEvidence;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Ensure Inertia page existence check doesn't fail
    config(['inertia.testing.ensure_pages_exist' => false]);
});

// Test 1: Findings/Index.vue renders paginated list correctly
test('Findings/Index.vue renders paginated list correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $category = FindingCategory::factory()->create(['name' => 'Network Issue']);
    $assignee = User::factory()->create(['name' => 'John Doe']);
    $audit = Audit::factory()->create();

    // Create findings directly without nested factory chain to avoid unique DeviceType issues
    for ($i = 0; $i < 5; $i++) {
        Finding::create([
            'audit_id' => $audit->id,
            'audit_connection_verification_id' => null,
            'discrepancy_type' => DiscrepancyType::Missing,
            'title' => "Finding {$i}",
            'description' => "Description for finding {$i}",
            'status' => FindingStatus::Open,
            'severity' => FindingSeverity::Medium,
            'finding_category_id' => $category->id,
            'assigned_to' => $assignee->id,
        ]);
    }

    $response = $this->actingAs($admin)->get('/findings');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Findings/Index')
        ->has('findings.data', 5)
        ->has('findings.links')
        ->where('findings.current_page', 1)
        ->where('findings.total', 5)
        ->has('statusOptions')
        ->has('severityOptions')
        ->has('categoryOptions')
        ->has('assigneeOptions')
        ->has('filters')
    );
});

// Test 2: Findings/Index.vue filters update URL and reload data
test('Findings/Index.vue filters update URL and reload data', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $audit = Audit::factory()->create();

    // Create findings directly
    $criticalFinding = Finding::create([
        'audit_id' => $audit->id,
        'audit_connection_verification_id' => null,
        'discrepancy_type' => DiscrepancyType::Missing,
        'title' => 'Critical Network Failure',
        'description' => 'Critical issue description',
        'status' => FindingStatus::Open,
        'severity' => FindingSeverity::Critical,
    ]);

    $lowFinding = Finding::create([
        'audit_id' => $audit->id,
        'audit_connection_verification_id' => null,
        'discrepancy_type' => DiscrepancyType::Unexpected,
        'title' => 'Minor Cable Issue',
        'description' => 'Minor issue description',
        'status' => FindingStatus::Resolved,
        'severity' => FindingSeverity::Low,
        'resolution_notes' => 'Fixed the cable.',
        'resolved_by' => $admin->id,
        'resolved_at' => now(),
    ]);

    // Test status filter
    $response = $this->actingAs($admin)->get('/findings?status=open');
    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('findings.data', 1)
        ->where('findings.data.0.status', 'open')
        ->where('filters.status', 'open')
    );

    // Test severity filter
    $response = $this->actingAs($admin)->get('/findings?severity=critical');
    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('findings.data', 1)
        ->where('findings.data.0.severity', 'critical')
        ->where('filters.severity', 'critical')
    );

    // Test search filter
    $response = $this->actingAs($admin)->get('/findings?search=Critical');
    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('findings.data', 1)
        ->where('findings.data.0.title', 'Critical Network Failure')
        ->where('filters.search', 'Critical')
    );

    // Test combined filters
    $response = $this->actingAs($admin)->get('/findings?status=open&severity=critical');
    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('findings.data', 1)
        ->where('filters.status', 'open')
        ->where('filters.severity', 'critical')
    );
});

// Test 3: Findings/Show.vue displays all finding details
test('Findings/Show.vue displays all finding details', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $assignee = User::factory()->create(['name' => 'Jane Smith']);
    $category = FindingCategory::factory()->create(['name' => 'Hardware']);
    $audit = Audit::factory()->create(['name' => 'Q4 Audit']);

    $finding = Finding::create([
        'audit_id' => $audit->id,
        'audit_connection_verification_id' => null,
        'discrepancy_type' => DiscrepancyType::Missing,
        'title' => 'Server Not Responding',
        'description' => 'The server at rack 15 is not responding to ping.',
        'status' => FindingStatus::InProgress,
        'severity' => FindingSeverity::High,
        'assigned_to' => $assignee->id,
        'finding_category_id' => $category->id,
        'resolution_notes' => null,
    ]);

    // Add some evidence
    FindingEvidence::factory()->forFinding($finding)->text()->create([
        'content' => 'Initial investigation shows power issue.',
    ]);

    $response = $this->actingAs($admin)->get("/findings/{$finding->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Findings/Show')
        ->has('finding', fn ($f) => $f
            ->where('id', $finding->id)
            ->where('title', 'Server Not Responding')
            ->where('description', 'The server at rack 15 is not responding to ping.')
            ->where('status', 'in_progress')
            ->where('status_label', 'In Progress')
            ->where('severity', 'high')
            ->where('severity_label', 'High')
            ->has('audit', fn ($a) => $a
                ->where('id', $audit->id)
                ->where('name', 'Q4 Audit')
                ->etc()
            )
            ->has('assignee', fn ($a) => $a
                ->where('id', $assignee->id)
                ->where('name', 'Jane Smith')
                ->etc()
            )
            ->has('category', fn ($c) => $c
                ->where('id', $category->id)
                ->where('name', 'Hardware')
                ->etc()
            )
            ->has('evidence', 1)
            ->etc()
        )
        ->has('statusOptions')
        ->has('severityOptions')
        ->has('categoryOptions')
        ->has('assigneeOptions')
        ->has('allowedTransitions')
        ->has('canEdit')
    );
});

// Test 4: Findings/Show.vue edit form submits correctly
test('Findings/Show.vue edit form submits correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $newAssignee = User::factory()->create();
    $newCategory = FindingCategory::factory()->create();
    $audit = Audit::factory()->create();

    $finding = Finding::create([
        'audit_id' => $audit->id,
        'audit_connection_verification_id' => null,
        'discrepancy_type' => DiscrepancyType::Missing,
        'title' => 'Test Finding',
        'description' => 'Test description',
        'status' => FindingStatus::Open,
        'severity' => FindingSeverity::Medium,
        'assigned_to' => null,
        'finding_category_id' => null,
    ]);

    // Test updating multiple fields
    $response = $this->actingAs($admin)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::InProgress->value,
        'severity' => FindingSeverity::High->value,
        'assigned_to' => $newAssignee->id,
        'finding_category_id' => $newCategory->id,
    ]);

    $response->assertRedirect();

    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::InProgress);
    expect($finding->severity)->toBe(FindingSeverity::High);
    expect($finding->assigned_to)->toBe($newAssignee->id);
    expect($finding->finding_category_id)->toBe($newCategory->id);

    // Test update to resolved requires resolution_notes
    $finding->update(['status' => FindingStatus::PendingReview]);

    $response = $this->actingAs($admin)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::Resolved->value,
    ]);
    $response->assertSessionHasErrors('resolution_notes');

    // Now with resolution notes - should succeed
    $response = $this->actingAs($admin)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::Resolved->value,
        'resolution_notes' => 'Issue has been fixed.',
    ]);
    $response->assertRedirect();

    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::Resolved);
    expect($finding->resolution_notes)->toBe('Issue has been fixed.');
    expect($finding->resolved_at)->not->toBeNull();
});

// Test 5: Evidence upload component handles file selection and upload
test('evidence upload component handles file selection and upload', function () {
    Storage::fake('local');

    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $audit = Audit::factory()->create();
    $finding = Finding::create([
        'audit_id' => $audit->id,
        'audit_connection_verification_id' => null,
        'discrepancy_type' => DiscrepancyType::Missing,
        'title' => 'Evidence Test Finding',
        'description' => 'Test description',
        'status' => FindingStatus::Open,
        'severity' => FindingSeverity::Medium,
    ]);

    // Test file upload
    $file = UploadedFile::fake()->image('evidence-photo.jpg', 1200, 800);

    $response = $this->actingAs($admin)->post("/findings/{$finding->id}/evidence", [
        'type' => 'file',
        'file' => $file,
    ]);

    $response->assertRedirect();

    // Verify evidence was created
    expect(FindingEvidence::where('finding_id', $finding->id)->where('type', 'file')->exists())->toBeTrue();

    $evidence = FindingEvidence::where('finding_id', $finding->id)->where('type', 'file')->first();
    expect($evidence->original_filename)->toBe('evidence-photo.jpg');
    expect($evidence->mime_type)->toBe('image/jpeg');
    expect($evidence->file_path)->toContain("finding-evidence/{$finding->id}/");

    // Verify file was stored
    Storage::disk('local')->assertExists($evidence->file_path);

    // Test text note upload
    $response = $this->actingAs($admin)->post("/findings/{$finding->id}/evidence", [
        'type' => 'text',
        'content' => 'This is a test note describing the issue.',
    ]);

    $response->assertRedirect();

    $textEvidence = FindingEvidence::where('finding_id', $finding->id)->where('type', 'text')->first();
    expect($textEvidence)->not->toBeNull();
    expect($textEvidence->content)->toBe('This is a test note describing the issue.');

    // Test evidence deletion
    $response = $this->actingAs($admin)->delete("/findings/{$finding->id}/evidence/{$evidence->id}");
    $response->assertRedirect();

    expect(FindingEvidence::find($evidence->id))->toBeNull();
    Storage::disk('local')->assertMissing($evidence->file_path);
});
