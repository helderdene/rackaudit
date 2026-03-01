<?php

use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Models\Audit;
use App\Models\Finding;
use App\Models\FindingCategory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Disable Inertia page existence check since Vue components are created in Task Group 5
    config(['inertia.testing.ensure_pages_exist' => false]);
});

// Test 1: Index action returns paginated findings with filters applied
test('index action returns paginated findings with filters applied', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $category = FindingCategory::factory()->create();

    // Create findings with various attributes
    $openFinding = Finding::factory()->create([
        'status' => FindingStatus::Open,
        'severity' => FindingSeverity::Critical,
        'finding_category_id' => $category->id,
        'title' => 'Critical network issue',
    ]);

    $resolvedFinding = Finding::factory()->create([
        'status' => FindingStatus::Resolved,
        'severity' => FindingSeverity::Low,
        'title' => 'Minor cable issue',
    ]);

    // Test index without filters
    $response = $this->actingAs($admin)->get('/findings');
    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Findings/Index')
        ->has('findings.data', 2)
    );

    // Test filter by status
    $response = $this->actingAs($admin)->get('/findings?status=open');
    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('findings.data', 1)
        ->where('findings.data.0.status', 'open')
    );

    // Test filter by severity
    $response = $this->actingAs($admin)->get('/findings?severity=critical');
    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('findings.data', 1)
        ->where('findings.data.0.severity', 'critical')
    );

    // Test filter by category
    $response = $this->actingAs($admin)->get('/findings?category='.$category->id);
    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('findings.data', 1)
    );

    // Test search by title
    $response = $this->actingAs($admin)->get('/findings?search=network');
    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('findings.data', 1)
        ->where('findings.data.0.title', 'Critical network issue')
    );
});

// Test 2: Show action returns finding with all relationships loaded
test('show action returns finding with all relationships loaded', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $assignee = User::factory()->create();
    $category = FindingCategory::factory()->create();
    $audit = Audit::factory()->create();

    $finding = Finding::factory()->create([
        'audit_id' => $audit->id,
        'assigned_to' => $assignee->id,
        'finding_category_id' => $category->id,
        'title' => 'Test Finding',
        'description' => 'Test description',
        'status' => FindingStatus::Open,
        'severity' => FindingSeverity::High,
    ]);

    $response = $this->actingAs($admin)->get("/findings/{$finding->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Findings/Show')
        ->has('finding', fn ($finding) => $finding
            ->has('id')
            ->has('title')
            ->has('description')
            ->has('status')
            ->has('severity')
            ->has('audit')
            ->has('assignee')
            ->has('category')
            ->etc()
        )
    );
});

// Test 3: Update action successfully updates finding properties
test('update action successfully updates finding properties', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $assignee = User::factory()->create();
    $category = FindingCategory::factory()->create();

    $finding = Finding::factory()->create([
        'status' => FindingStatus::Open,
        'severity' => FindingSeverity::Medium,
    ]);

    $response = $this->actingAs($admin)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::InProgress->value,
        'severity' => FindingSeverity::High->value,
        'assigned_to' => $assignee->id,
        'finding_category_id' => $category->id,
    ]);

    $response->assertRedirect();

    $finding->refresh();

    expect($finding->status)->toBe(FindingStatus::InProgress);
    expect($finding->severity)->toBe(FindingSeverity::High);
    expect($finding->assigned_to)->toBe($assignee->id);
    expect($finding->finding_category_id)->toBe($category->id);
});

// Test 4: Update action validates required resolution_notes when status changes to Resolved
test('update action validates required resolution_notes when status changes to Resolved', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $finding = Finding::factory()->create([
        'status' => FindingStatus::PendingReview,
    ]);

    // Try to change to Resolved without resolution_notes
    $response = $this->actingAs($admin)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::Resolved->value,
    ]);

    $response->assertSessionHasErrors('resolution_notes');

    // Now provide resolution_notes
    $response = $this->actingAs($admin)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::Resolved->value,
        'resolution_notes' => 'Issue has been resolved by replacing the cable.',
    ]);

    $response->assertRedirect();

    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::Resolved);
    expect($finding->resolution_notes)->toBe('Issue has been resolved by replacing the cable.');
    expect($finding->resolved_at)->not->toBeNull();
});

// Test 5: Authorization - only assigned user, admin, or IT Manager can update finding
test('authorization only allows assigned user admin or IT Manager to update finding', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $itManager = User::factory()->create();
    $itManager->assignRole('IT Manager');

    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    $assignedOperator = User::factory()->create();
    $assignedOperator->assignRole('Operator');

    // Create an audit and assign the operator to it
    $audit = Audit::factory()->create();
    $audit->assignees()->attach($assignedOperator->id);

    $finding = Finding::factory()->create([
        'audit_id' => $audit->id,
        'status' => FindingStatus::Open,
        'assigned_to' => $assignedOperator->id,
    ]);

    // Admin can update
    $response = $this->actingAs($admin)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::InProgress->value,
    ]);
    $response->assertRedirect();

    // IT Manager can update
    $finding->update(['status' => FindingStatus::Open]);
    $response = $this->actingAs($itManager)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::InProgress->value,
    ]);
    $response->assertRedirect();

    // Assigned operator can update
    $finding->update(['status' => FindingStatus::Open]);
    $response = $this->actingAs($assignedOperator)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::InProgress->value,
    ]);
    $response->assertRedirect();

    // Non-assigned operator cannot update
    $finding->update(['status' => FindingStatus::Open]);
    $response = $this->actingAs($operator)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::InProgress->value,
    ]);
    $response->assertForbidden();
});

// Test 6: Status transition validation - cannot skip workflow steps without admin
test('status transition validation prevents invalid workflow transitions', function () {
    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create an audit and assign the operator
    $audit = Audit::factory()->create();
    $audit->assignees()->attach($operator->id);

    $finding = Finding::factory()->create([
        'audit_id' => $audit->id,
        'status' => FindingStatus::Open,
        'assigned_to' => $operator->id,
    ]);

    // Cannot go from Open directly to Resolved (must go through workflow)
    $response = $this->actingAs($operator)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::Resolved->value,
        'resolution_notes' => 'Trying to skip steps',
    ]);
    $response->assertSessionHasErrors('status');

    // Valid transition: Open -> InProgress
    $response = $this->actingAs($operator)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::InProgress->value,
    ]);
    $response->assertRedirect();

    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::InProgress);

    // Valid transition: InProgress -> PendingReview
    $response = $this->actingAs($operator)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::PendingReview->value,
    ]);
    $response->assertRedirect();

    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::PendingReview);

    // Valid transition: PendingReview -> Resolved (with notes)
    $response = $this->actingAs($operator)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::Resolved->value,
        'resolution_notes' => 'Issue resolved properly',
    ]);
    $response->assertRedirect();

    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::Resolved);

    // Admin CAN reopen a resolved finding (admin override)
    $response = $this->actingAs($admin)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::Open->value,
    ]);
    $response->assertRedirect();

    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::Open);
});
