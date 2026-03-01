<?php

/**
 * Strategic integration tests for Finding Resolution Workflow.
 *
 * These tests fill critical coverage gaps identified during test review:
 * - Complete workflow end-to-end tests
 * - Bulk operations with chunking (100+ findings)
 * - Admin override of workflow restrictions
 * - Due date notification scheduling command
 * - Grandfathered resolution notes
 * - Timeline metrics accuracy
 */

use App\Enums\FindingStatus;
use App\Models\Audit;
use App\Models\Finding;
use App\Models\FindingStatusTransition;
use App\Models\User;
use App\Notifications\FindingAssignedNotification;
use App\Notifications\FindingDueDateApproachingNotification;
use App\Notifications\FindingOverdueNotification;
use App\Notifications\FindingStatusChangedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
});

// Test 1: Complete workflow from Open to Resolved
test('complete workflow transitions finding from Open through In Progress and Pending Review to Resolved', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('IT Manager');

    $assignee = User::factory()->create();

    $finding = Finding::factory()->open()->create([
        'assigned_to' => $assignee->id,
    ]);

    // Step 1: Open -> In Progress (Start Working)
    $response = $this->actingAs($admin)
        ->postJson("/findings/{$finding->id}/transition", [
            'target_status' => FindingStatus::InProgress->value,
            'notes' => 'Starting work on this finding',
        ]);

    $response->assertSuccessful();
    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::InProgress);

    // Step 2: In Progress -> Pending Review (Submit for Review)
    $response = $this->actingAs($admin)
        ->postJson("/findings/{$finding->id}/transition", [
            'target_status' => FindingStatus::PendingReview->value,
            'notes' => 'Ready for review',
        ]);

    $response->assertSuccessful();
    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::PendingReview);

    // Step 3: Pending Review -> Resolved (Approve & Close)
    $response = $this->actingAs($admin)
        ->postJson("/findings/{$finding->id}/transition", [
            'target_status' => FindingStatus::Resolved->value,
            'notes' => 'Issue has been fully resolved and verified',
        ]);

    $response->assertSuccessful();
    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::Resolved);
    expect($finding->resolved_at)->not->toBeNull();
    expect($finding->resolved_by)->toBe($admin->id);

    // Verify all transitions were logged
    expect($finding->statusTransitions)->toHaveCount(3);
    $transitions = $finding->statusTransitions->sortBy('transitioned_at')->values();
    expect($transitions[0]->to_status)->toBe(FindingStatus::InProgress);
    expect($transitions[1]->to_status)->toBe(FindingStatus::PendingReview);
    expect($transitions[2]->to_status)->toBe(FindingStatus::Resolved);
});

// Test 2: Bulk assign with 100+ findings processes in chunks
test('bulk assign with more than 100 findings processes correctly using chunking', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $newAssignee = User::factory()->create();

    // Create 150 findings to test chunking (chunk size is 100)
    $findings = Finding::factory()->count(150)->create();

    $response = $this->actingAs($admin)
        ->postJson('/findings/bulk-assign', [
            'finding_ids' => $findings->pluck('id')->toArray(),
            'assigned_to' => $newAssignee->id,
        ]);

    $response->assertSuccessful();
    $response->assertJsonFragment(['success_count' => 150]);

    // Verify all findings were assigned
    $assignedCount = Finding::where('assigned_to', $newAssignee->id)->count();
    expect($assignedCount)->toBe(150);

    // Verify notifications were sent for each assignment
    Notification::assertSentTimes(FindingAssignedNotification::class, 150);
});

// Test 3: Admin can override workflow restrictions
test('admin can bypass workflow restrictions and transition to any status', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $finding = Finding::factory()->open()->create();

    // Admin can skip directly from Open to Resolved (bypassing workflow)
    $response = $this->actingAs($admin)
        ->from("/findings/{$finding->id}")
        ->put("/findings/{$finding->id}", [
            'status' => FindingStatus::Resolved->value,
            'resolution_notes' => 'Resolved immediately by admin override',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::Resolved);

    // Verify transition was logged
    $this->assertDatabaseHas('finding_status_transitions', [
        'finding_id' => $finding->id,
        'from_status' => FindingStatus::Open->value,
        'to_status' => FindingStatus::Resolved->value,
        'user_id' => $admin->id,
    ]);
});

// Test 4: Due date notification scheduling command sends notifications
test('due date notification command sends approaching and overdue notifications', function () {
    Notification::fake();

    $assignee = User::factory()->create();

    // Create finding due in 2 days (should trigger approaching notification)
    $dueSoonFinding = Finding::factory()
        ->open()
        ->assignedTo($assignee)
        ->withDueDate(now()->addDays(2))
        ->create(['title' => 'Due Soon Finding']);

    // Create overdue finding (should trigger overdue notification)
    $overdueFinding = Finding::factory()
        ->inProgress()
        ->assignedTo($assignee)
        ->withDueDate(now()->subDays(3))
        ->create(['title' => 'Overdue Finding']);

    // Run the command
    $exitCode = Artisan::call('findings:send-due-date-notifications');

    expect($exitCode)->toBe(0);

    // Verify approaching due date notification was sent
    Notification::assertSentTo(
        $assignee,
        FindingDueDateApproachingNotification::class,
        function ($notification) use ($dueSoonFinding) {
            return $notification->finding->id === $dueSoonFinding->id;
        }
    );

    // Verify overdue notification was sent
    Notification::assertSentTo(
        $assignee,
        FindingOverdueNotification::class,
        function ($notification) use ($overdueFinding) {
            return $notification->finding->id === $overdueFinding->id;
        }
    );
});

// Test 5: Grandfathered resolution notes - existing short notes still work
test('grandfathered resolution notes allow resolving with existing short notes', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create a finding that was previously resolved with short notes (grandfathered)
    $finding = Finding::factory()->create([
        'status' => FindingStatus::PendingReview,
        'resolution_notes' => 'Short', // Less than 10 characters (grandfathered)
    ]);

    // Try to resolve without providing new notes - should use existing notes
    $response = $this->actingAs($admin)
        ->from("/findings/{$finding->id}")
        ->put("/findings/{$finding->id}", [
            'status' => FindingStatus::Resolved->value,
            // No resolution_notes provided - using existing
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::Resolved);
    expect($finding->resolution_notes)->toBe('Short');
});

// Test 6: Timeline metrics accuracy - time calculations are correct
test('timeline metrics accurately calculate time to first response and total resolution time', function () {
    $user = User::factory()->create();

    // Create finding with known timestamps
    $createdAt = now()->subDays(7);
    $firstResponseAt = now()->subDays(6); // 1 day to first response
    $resolvedAt = now()->subDays(1); // 6 days total resolution time

    $finding = Finding::factory()->create([
        'status' => FindingStatus::Resolved,
        'created_at' => $createdAt,
        'resolved_at' => $resolvedAt,
    ]);

    // Manually set created_at (factory may override)
    Finding::withoutEvents(function () use ($finding, $createdAt) {
        $finding->update(['created_at' => $createdAt]);
    });

    // Create the first response transition
    FindingStatusTransition::create([
        'finding_id' => $finding->id,
        'from_status' => FindingStatus::Open,
        'to_status' => FindingStatus::InProgress,
        'user_id' => $user->id,
        'transitioned_at' => $firstResponseAt,
    ]);

    $finding->refresh();

    // Time to first response should be approximately 1 day (1440 minutes)
    $timeToFirstResponse = $finding->getTimeToFirstResponse();
    expect($timeToFirstResponse)->not->toBeNull();
    // Allow some tolerance for test execution time
    expect($timeToFirstResponse)->toBeGreaterThanOrEqual(1430); // ~23.8 hours
    expect($timeToFirstResponse)->toBeLessThanOrEqual(1450); // ~24.2 hours

    // Total resolution time should be approximately 6 days (8640 minutes)
    $totalResolutionTime = $finding->getTotalResolutionTime();
    expect($totalResolutionTime)->not->toBeNull();
    expect($totalResolutionTime)->toBeGreaterThanOrEqual(8600); // ~143.3 hours
    expect($totalResolutionTime)->toBeLessThanOrEqual(8680); // ~144.7 hours
});

// Test 7: Admin can reopen resolved findings and clears resolution data
test('admin can reopen resolved findings which clears resolution timestamp and user', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $resolvedFinding = Finding::factory()
        ->resolved()
        ->create();

    // Ensure finding has resolution data set
    expect($resolvedFinding->status)->toBe(FindingStatus::Resolved);
    expect($resolvedFinding->resolved_at)->not->toBeNull();
    expect($resolvedFinding->resolved_by)->not->toBeNull();

    // Admin reopens resolved finding
    $response = $this->actingAs($admin)
        ->from("/findings/{$resolvedFinding->id}")
        ->put("/findings/{$resolvedFinding->id}", [
            'status' => FindingStatus::Open->value,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $resolvedFinding->refresh();
    expect($resolvedFinding->status)->toBe(FindingStatus::Open);
    expect($resolvedFinding->resolved_at)->toBeNull();
    expect($resolvedFinding->resolved_by)->toBeNull();

    // Verify transition was logged
    $this->assertDatabaseHas('finding_status_transitions', [
        'finding_id' => $resolvedFinding->id,
        'from_status' => FindingStatus::Resolved->value,
        'to_status' => FindingStatus::Open->value,
        'user_id' => $admin->id,
    ]);
});

// Test 8: Bulk status change creates transitions for each finding
test('bulk status change creates individual status transitions for each finding', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $assignee = User::factory()->create();

    // Create multiple findings at different statuses
    $findings = collect([
        Finding::factory()->open()->assignedTo($assignee)->create(),
        Finding::factory()->open()->assignedTo($assignee)->create(),
        Finding::factory()->inProgress()->assignedTo($assignee)->create(),
    ]);

    // Bulk transition to In Progress (one is already there, so only 2 should transition)
    $response = $this->actingAs($admin)
        ->postJson('/findings/bulk-status', [
            'finding_ids' => $findings->pluck('id')->toArray(),
            'status' => FindingStatus::InProgress->value,
        ]);

    $response->assertSuccessful();
    $response->assertJsonFragment(['success_count' => 3]);

    // Verify transitions were created only for the 2 that changed
    $transitionCount = FindingStatusTransition::whereIn('finding_id', $findings->pluck('id'))->count();
    expect($transitionCount)->toBe(2);

    // Verify all findings are now In Progress
    foreach ($findings as $finding) {
        $finding->refresh();
        expect($finding->status)->toBe(FindingStatus::InProgress);
    }

    // Verify notifications were sent for the 2 that changed (not the one already in progress)
    Notification::assertSentTimes(FindingStatusChangedNotification::class, 2);
});
