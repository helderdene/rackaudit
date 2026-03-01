<?php

/**
 * Strategic integration tests for Finding Management feature.
 *
 * These tests fill critical coverage gaps identified in Task Group 6
 * review of existing tests from Task Groups 1-5.
 */

use App\Enums\DiscrepancyType;
use App\Enums\EvidenceType;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Models\Audit;
use App\Models\Finding;
use App\Models\FindingCategory;
use App\Models\FindingEvidence;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
});

// Test 1: Complete finding workflow from Open to Resolved (end-to-end)
test('complete finding workflow progresses through all statuses to resolved', function () {
    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    $audit = Audit::factory()->create();
    $audit->assignees()->attach($operator->id);

    $finding = Finding::create([
        'audit_id' => $audit->id,
        'discrepancy_type' => DiscrepancyType::Missing,
        'title' => 'End-to-end workflow test',
        'description' => 'Testing complete status workflow',
        'status' => FindingStatus::Open,
        'severity' => FindingSeverity::High,
        'assigned_to' => $operator->id,
    ]);

    // Step 1: Open -> InProgress
    $response = $this->actingAs($operator)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::InProgress->value,
    ]);
    $response->assertRedirect();
    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::InProgress);

    // Step 2: InProgress -> PendingReview
    $response = $this->actingAs($operator)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::PendingReview->value,
    ]);
    $response->assertRedirect();
    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::PendingReview);

    // Step 3: PendingReview -> Resolved (requires resolution_notes)
    $response = $this->actingAs($operator)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::Resolved->value,
        'resolution_notes' => 'Completed all steps and verified the issue is fixed.',
    ]);
    $response->assertRedirect();
    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::Resolved);
    expect($finding->resolution_notes)->toBe('Completed all steps and verified the issue is fixed.');
    expect($finding->resolved_at)->not->toBeNull();
    expect($finding->resolved_by)->toBe($operator->id);
});

// Test 2: Deferred workflow path allows returning to work
test('deferred workflow allows finding to return to open or in progress', function () {
    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    $audit = Audit::factory()->create();
    $audit->assignees()->attach($operator->id);

    $finding = Finding::create([
        'audit_id' => $audit->id,
        'discrepancy_type' => DiscrepancyType::Unexpected,
        'title' => 'Deferred workflow test',
        'description' => 'Testing deferred status branch',
        'status' => FindingStatus::Open,
        'severity' => FindingSeverity::Medium,
        'assigned_to' => $operator->id,
    ]);

    // Open -> Deferred
    $response = $this->actingAs($operator)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::Deferred->value,
    ]);
    $response->assertRedirect();
    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::Deferred);

    // Deferred -> InProgress (resuming work)
    $response = $this->actingAs($operator)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::InProgress->value,
    ]);
    $response->assertRedirect();
    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::InProgress);
});

// Test 3: Non-assigned user cannot view finding detail page
test('non-assigned operator cannot view finding from unassigned audit', function () {
    $assignedOperator = User::factory()->create();
    $assignedOperator->assignRole('Operator');

    $unassignedOperator = User::factory()->create();
    $unassignedOperator->assignRole('Operator');

    $audit = Audit::factory()->create();
    $audit->assignees()->attach($assignedOperator->id);

    $finding = Finding::create([
        'audit_id' => $audit->id,
        'discrepancy_type' => DiscrepancyType::Missing,
        'title' => 'Restricted finding',
        'description' => 'Should not be visible to unassigned user',
        'status' => FindingStatus::Open,
        'severity' => FindingSeverity::Critical,
    ]);

    // Assigned operator can view
    $response = $this->actingAs($assignedOperator)->get("/findings/{$finding->id}");
    $response->assertSuccessful();

    // Unassigned operator cannot view
    $response = $this->actingAs($unassignedOperator)->get("/findings/{$finding->id}");
    $response->assertForbidden();
});

// Test 4: Index filters only show findings from assigned audits for non-admin users
test('operator index only shows findings from assigned audits', function () {
    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Audit assigned to operator
    $assignedAudit = Audit::factory()->create();
    $assignedAudit->assignees()->attach($operator->id);

    // Audit NOT assigned to operator
    $unassignedAudit = Audit::factory()->create();

    // Create finding in assigned audit
    Finding::create([
        'audit_id' => $assignedAudit->id,
        'discrepancy_type' => DiscrepancyType::Missing,
        'title' => 'Visible to operator',
        'description' => 'From assigned audit',
        'status' => FindingStatus::Open,
        'severity' => FindingSeverity::High,
    ]);

    // Create finding in unassigned audit
    Finding::create([
        'audit_id' => $unassignedAudit->id,
        'discrepancy_type' => DiscrepancyType::Unexpected,
        'title' => 'Hidden from operator',
        'description' => 'From unassigned audit',
        'status' => FindingStatus::Open,
        'severity' => FindingSeverity::Low,
    ]);

    // Operator sees only 1 finding
    $response = $this->actingAs($operator)->get('/findings');
    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('findings.data', 1)
        ->where('findings.data.0.title', 'Visible to operator')
    );

    // Admin sees both findings
    $response = $this->actingAs($admin)->get('/findings');
    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('findings.data', 2)
    );
});

// Test 5: Evidence deletion authorization prevents unauthorized users
test('unauthorized user cannot delete evidence from finding', function () {
    Storage::fake('local');

    $authorizedOperator = User::factory()->create();
    $authorizedOperator->assignRole('Operator');

    $unauthorizedOperator = User::factory()->create();
    $unauthorizedOperator->assignRole('Operator');

    $audit = Audit::factory()->create();
    $audit->assignees()->attach($authorizedOperator->id);

    $finding = Finding::create([
        'audit_id' => $audit->id,
        'discrepancy_type' => DiscrepancyType::Mismatched,
        'title' => 'Evidence auth test',
        'description' => 'Testing evidence deletion authorization',
        'status' => FindingStatus::Open,
        'severity' => FindingSeverity::Medium,
        'assigned_to' => $authorizedOperator->id,
    ]);

    // Create evidence
    $evidence = FindingEvidence::create([
        'finding_id' => $finding->id,
        'type' => EvidenceType::Text,
        'content' => 'Test note that should be protected',
    ]);

    // Authorized user can delete
    $response = $this->actingAs($authorizedOperator)->delete("/findings/{$finding->id}/evidence/{$evidence->id}");
    $response->assertRedirect();
    expect(FindingEvidence::find($evidence->id))->toBeNull();

    // Create new evidence for unauthorized test
    $evidence2 = FindingEvidence::create([
        'finding_id' => $finding->id,
        'type' => EvidenceType::Text,
        'content' => 'Another test note',
    ]);

    // Unauthorized user cannot delete
    $response = $this->actingAs($unauthorizedOperator)->delete("/findings/{$finding->id}/evidence/{$evidence2->id}");
    $response->assertForbidden();
    expect(FindingEvidence::find($evidence2->id))->not->toBeNull();
});

// Test 6: Filter by assignee returns correct findings
test('findings can be filtered by assignee', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $assignee1 = User::factory()->create(['name' => 'Alice']);
    $assignee2 = User::factory()->create(['name' => 'Bob']);

    $audit = Audit::factory()->create();

    // Create findings with different assignees
    Finding::create([
        'audit_id' => $audit->id,
        'discrepancy_type' => DiscrepancyType::Missing,
        'title' => 'Alice finding 1',
        'description' => 'Assigned to Alice',
        'status' => FindingStatus::Open,
        'severity' => FindingSeverity::High,
        'assigned_to' => $assignee1->id,
    ]);

    Finding::create([
        'audit_id' => $audit->id,
        'discrepancy_type' => DiscrepancyType::Unexpected,
        'title' => 'Alice finding 2',
        'description' => 'Also assigned to Alice',
        'status' => FindingStatus::InProgress,
        'severity' => FindingSeverity::Medium,
        'assigned_to' => $assignee1->id,
    ]);

    Finding::create([
        'audit_id' => $audit->id,
        'discrepancy_type' => DiscrepancyType::Conflicting,
        'title' => 'Bob finding',
        'description' => 'Assigned to Bob',
        'status' => FindingStatus::Open,
        'severity' => FindingSeverity::Low,
        'assigned_to' => $assignee2->id,
    ]);

    // Filter by Alice
    $response = $this->actingAs($admin)->get("/findings?assigned_to={$assignee1->id}");
    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('findings.data', 2)
    );

    // Filter by Bob
    $response = $this->actingAs($admin)->get("/findings?assigned_to={$assignee2->id}");
    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('findings.data', 1)
        ->where('findings.data.0.title', 'Bob finding')
    );
});

// Test 7: Multiple combined filters work correctly together
test('multiple filters can be combined for precise results', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $category = FindingCategory::factory()->create(['name' => 'Network Issue']);
    $assignee = User::factory()->create();
    $audit = Audit::factory()->create();

    // Finding matching all criteria
    Finding::create([
        'audit_id' => $audit->id,
        'discrepancy_type' => DiscrepancyType::Missing,
        'title' => 'Critical network problem',
        'description' => 'Matches all filters',
        'status' => FindingStatus::Open,
        'severity' => FindingSeverity::Critical,
        'finding_category_id' => $category->id,
        'assigned_to' => $assignee->id,
    ]);

    // Finding with different status
    Finding::create([
        'audit_id' => $audit->id,
        'discrepancy_type' => DiscrepancyType::Missing,
        'title' => 'Resolved network issue',
        'description' => 'Different status',
        'status' => FindingStatus::Resolved,
        'severity' => FindingSeverity::Critical,
        'finding_category_id' => $category->id,
        'assigned_to' => $assignee->id,
        'resolution_notes' => 'Fixed',
        'resolved_at' => now(),
    ]);

    // Finding with different severity
    Finding::create([
        'audit_id' => $audit->id,
        'discrepancy_type' => DiscrepancyType::Unexpected,
        'title' => 'Low priority issue',
        'description' => 'Different severity',
        'status' => FindingStatus::Open,
        'severity' => FindingSeverity::Low,
        'finding_category_id' => $category->id,
        'assigned_to' => $assignee->id,
    ]);

    // Finding with different category
    Finding::create([
        'audit_id' => $audit->id,
        'discrepancy_type' => DiscrepancyType::Conflicting,
        'title' => 'Hardware problem',
        'description' => 'Different category',
        'status' => FindingStatus::Open,
        'severity' => FindingSeverity::Critical,
        'finding_category_id' => null,
        'assigned_to' => $assignee->id,
    ]);

    // Apply all filters: status=open, severity=critical, category, assignee
    $response = $this->actingAs($admin)->get("/findings?status=open&severity=critical&category={$category->id}&assigned_to={$assignee->id}");
    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('findings.data', 1)
        ->where('findings.data.0.title', 'Critical network problem')
    );
});

// Test 8: Pagination works correctly with filtered results
test('pagination maintains filters across pages', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $audit = Audit::factory()->create();

    // Create 20 open findings (pagination is 15 per page)
    for ($i = 1; $i <= 20; $i++) {
        Finding::create([
            'audit_id' => $audit->id,
            'discrepancy_type' => DiscrepancyType::Missing,
            'title' => "Open finding {$i}",
            'description' => "Description {$i}",
            'status' => FindingStatus::Open,
            'severity' => FindingSeverity::Medium,
        ]);
    }

    // Create 5 resolved findings
    for ($i = 1; $i <= 5; $i++) {
        Finding::create([
            'audit_id' => $audit->id,
            'discrepancy_type' => DiscrepancyType::Unexpected,
            'title' => "Resolved finding {$i}",
            'description' => "Resolved description {$i}",
            'status' => FindingStatus::Resolved,
            'severity' => FindingSeverity::Low,
            'resolution_notes' => 'Fixed',
            'resolved_at' => now(),
        ]);
    }

    // Page 1 with status filter
    $response = $this->actingAs($admin)->get('/findings?status=open');
    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('findings.data', 15)
        ->where('findings.total', 20)
        ->where('findings.current_page', 1)
        ->where('findings.last_page', 2)
        ->where('filters.status', 'open')
    );

    // Page 2 with same filter
    $response = $this->actingAs($admin)->get('/findings?status=open&page=2');
    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('findings.data', 5)
        ->where('findings.current_page', 2)
        ->where('filters.status', 'open')
    );
});
