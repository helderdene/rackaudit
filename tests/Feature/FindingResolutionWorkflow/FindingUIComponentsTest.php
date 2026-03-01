<?php

/**
 * Tests for Finding Resolution Workflow UI Components.
 *
 * These tests verify the UI components render correctly and data
 * is properly passed from the backend for the finding resolution workflow.
 */

use App\Enums\DiscrepancyType;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Models\Audit;
use App\Models\Finding;
use App\Models\FindingStatusTransition;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
});

test('quick actions render based on current finding status', function () {
    $admin = User::factory()->create();
    $admin->assignRole('IT Manager');

    $audit = Audit::factory()->create();

    // Test Open status shows "Start Working" and "Defer" actions
    $openFinding = Finding::create([
        'audit_id' => $audit->id,
        'discrepancy_type' => DiscrepancyType::Missing,
        'title' => 'Open Finding',
        'status' => FindingStatus::Open,
        'severity' => FindingSeverity::High,
    ]);

    $response = $this->actingAs($admin)->get("/findings/{$openFinding->id}");
    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('quickActions', fn ($actions) => $actions
                ->where('0.action', 'start_working')
                ->where('0.status', FindingStatus::InProgress->value)
                ->where('1.action', 'defer')
                ->where('1.status', FindingStatus::Deferred->value)
            )
        );

    // Test In Progress status shows "Submit for Review" and "Defer" actions
    $inProgressFinding = Finding::create([
        'audit_id' => $audit->id,
        'discrepancy_type' => DiscrepancyType::Missing,
        'title' => 'In Progress Finding',
        'status' => FindingStatus::InProgress,
        'severity' => FindingSeverity::High,
    ]);

    $response = $this->actingAs($admin)->get("/findings/{$inProgressFinding->id}");
    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('quickActions', fn ($actions) => $actions
                ->where('0.action', 'submit_for_review')
                ->where('0.status', FindingStatus::PendingReview->value)
                ->where('1.action', 'defer')
                ->where('1.status', FindingStatus::Deferred->value)
            )
        );

    // Test Pending Review status shows "Approve & Close" action
    $pendingReviewFinding = Finding::create([
        'audit_id' => $audit->id,
        'discrepancy_type' => DiscrepancyType::Missing,
        'title' => 'Pending Review Finding',
        'status' => FindingStatus::PendingReview,
        'severity' => FindingSeverity::High,
    ]);

    $response = $this->actingAs($admin)->get("/findings/{$pendingReviewFinding->id}");
    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('quickActions', fn ($actions) => $actions
                ->where('0.action', 'approve_and_close')
                ->where('0.status', FindingStatus::Resolved->value)
                ->where('0.requires_notes', true)
            )
        );
});

test('findings index includes due date and due date status indicators', function () {
    $admin = User::factory()->create();
    $admin->assignRole('IT Manager');

    $audit = Audit::factory()->create();

    // Create finding with overdue due date
    $overdueFinding = Finding::create([
        'audit_id' => $audit->id,
        'discrepancy_type' => DiscrepancyType::Missing,
        'title' => 'Overdue Finding',
        'status' => FindingStatus::Open,
        'severity' => FindingSeverity::High,
        'due_date' => now()->subDays(5)->format('Y-m-d'),
    ]);

    // Create finding due soon (within 3 days)
    $dueSoonFinding = Finding::create([
        'audit_id' => $audit->id,
        'discrepancy_type' => DiscrepancyType::Missing,
        'title' => 'Due Soon Finding',
        'status' => FindingStatus::InProgress,
        'severity' => FindingSeverity::Medium,
        'due_date' => now()->addDays(2)->format('Y-m-d'),
    ]);

    $response = $this->actingAs($admin)->get('/findings');
    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Findings/Index')
            ->has('findings.data', 2)
            ->has('dueDateOptions')
            ->where('findings.data.0.is_overdue', fn ($val) => is_bool($val))
            ->where('findings.data.0.is_due_soon', fn ($val) => is_bool($val))
        );
});

test('bulk selection toolbar data is available on index page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('IT Manager');

    $audit = Audit::factory()->create();

    // Create multiple findings
    for ($i = 0; $i < 3; $i++) {
        Finding::create([
            'audit_id' => $audit->id,
            'discrepancy_type' => DiscrepancyType::Missing,
            'title' => "Finding {$i}",
            'status' => FindingStatus::Open,
            'severity' => FindingSeverity::Medium,
        ]);
    }

    $response = $this->actingAs($admin)->get('/findings');
    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Findings/Index')
            ->has('findings.data', 3)
            ->has('statusOptions')
            ->has('assigneeOptions')
        );
});

test('workflow progress indicator data is available on show page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('IT Manager');

    $audit = Audit::factory()->create();

    $finding = Finding::create([
        'audit_id' => $audit->id,
        'discrepancy_type' => DiscrepancyType::Missing,
        'title' => 'Test Finding',
        'status' => FindingStatus::InProgress,
        'severity' => FindingSeverity::High,
    ]);

    $response = $this->actingAs($admin)->get("/findings/{$finding->id}");
    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Findings/Show')
            ->where('finding.status', FindingStatus::InProgress->value)
            ->where('finding.status_label', 'In Progress')
            ->has('quickActions')
            ->has('statusTransitions')
            ->has('timeMetrics')
        );
});

test('resolution notes validation hint is enforced when resolving', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $audit = Audit::factory()->create();

    $finding = Finding::create([
        'audit_id' => $audit->id,
        'discrepancy_type' => DiscrepancyType::Missing,
        'title' => 'Test Finding',
        'status' => FindingStatus::PendingReview,
        'severity' => FindingSeverity::High,
    ]);

    // Test with resolution notes less than 10 characters
    $response = $this->actingAs($admin)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::Resolved->value,
        'resolution_notes' => 'Short',
    ]);

    $response->assertSessionHasErrors('resolution_notes');

    // Test with resolution notes exactly 10 characters
    $response = $this->actingAs($admin)->put("/findings/{$finding->id}", [
        'status' => FindingStatus::Resolved->value,
        'resolution_notes' => 'Fixed now!',
    ]);

    $response->assertRedirect();
    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::Resolved);
});

test('status transition timeline is populated with transition history', function () {
    $admin = User::factory()->create();
    $admin->assignRole('IT Manager');

    $audit = Audit::factory()->create();

    $finding = Finding::create([
        'audit_id' => $audit->id,
        'discrepancy_type' => DiscrepancyType::Missing,
        'title' => 'Test Finding',
        'status' => FindingStatus::InProgress,
        'severity' => FindingSeverity::High,
    ]);

    // Create some status transitions
    FindingStatusTransition::create([
        'finding_id' => $finding->id,
        'from_status' => FindingStatus::Open,
        'to_status' => FindingStatus::InProgress,
        'user_id' => $admin->id,
        'notes' => 'Starting work on this finding',
        'transitioned_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($admin)->get("/findings/{$finding->id}");
    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Findings/Show')
            ->has('statusTransitions', 1)
            ->where('statusTransitions.0.from_status', FindingStatus::Open->value)
            ->where('statusTransitions.0.to_status', FindingStatus::InProgress->value)
            ->where('statusTransitions.0.notes', 'Starting work on this finding')
            ->has('statusTransitions.0.user')
        );
});
