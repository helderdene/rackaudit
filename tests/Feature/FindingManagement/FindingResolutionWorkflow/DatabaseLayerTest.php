<?php

use App\Enums\FindingStatus;
use App\Models\Finding;
use App\Models\FindingStatusTransition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Test 1: FindingStatusTransition model creation and relationships
test('FindingStatusTransition model creation and relationships work correctly', function () {
    $user = User::factory()->create();
    $finding = Finding::factory()->inProgress()->create();

    $transition = FindingStatusTransition::create([
        'finding_id' => $finding->id,
        'from_status' => FindingStatus::Open,
        'to_status' => FindingStatus::InProgress,
        'user_id' => $user->id,
        'notes' => 'Started working on this finding',
        'transitioned_at' => now(),
    ]);

    expect($transition)->toBeInstanceOf(FindingStatusTransition::class);
    expect($transition->finding_id)->toBe($finding->id);
    expect($transition->from_status)->toBe(FindingStatus::Open);
    expect($transition->to_status)->toBe(FindingStatus::InProgress);
    expect($transition->user_id)->toBe($user->id);
    expect($transition->notes)->toBe('Started working on this finding');
    expect($transition->transitioned_at)->not->toBeNull();

    // Test relationships
    expect($transition->finding)->toBeInstanceOf(Finding::class);
    expect($transition->finding->id)->toBe($finding->id);
    expect($transition->user)->toBeInstanceOf(User::class);
    expect($transition->user->id)->toBe($user->id);

    // Test nullable notes
    $transitionWithoutNotes = FindingStatusTransition::create([
        'finding_id' => $finding->id,
        'from_status' => FindingStatus::InProgress,
        'to_status' => FindingStatus::PendingReview,
        'user_id' => $user->id,
        'transitioned_at' => now(),
    ]);

    expect($transitionWithoutNotes->notes)->toBeNull();
});

// Test 2: Finding model due_date handling (overdue, due_soon scopes)
test('Finding model due_date scopes work correctly', function () {
    // Create finding that is overdue (past due date)
    $overdueFinding = Finding::factory()->create([
        'due_date' => now()->subDays(5),
    ]);

    // Create finding that is due soon (within 3 days)
    $dueSoonFinding = Finding::factory()->create([
        'due_date' => now()->addDays(2),
    ]);

    // Create finding with no due date
    $noDueDateFinding = Finding::factory()->create([
        'due_date' => null,
    ]);

    // Create finding with due date far in the future (not overdue, not due soon)
    $futureFinding = Finding::factory()->create([
        'due_date' => now()->addDays(30),
    ]);

    // Test overdue scope
    $overdueFindings = Finding::overdue()->get();
    expect($overdueFindings)->toHaveCount(1);
    expect($overdueFindings->first()->id)->toBe($overdueFinding->id);

    // Test due soon scope
    $dueSoonFindings = Finding::dueSoon()->get();
    expect($dueSoonFindings)->toHaveCount(1);
    expect($dueSoonFindings->first()->id)->toBe($dueSoonFinding->id);

    // Test no due date scope
    $noDueDateFindings = Finding::noDueDate()->get();
    expect($noDueDateFindings)->toHaveCount(1);
    expect($noDueDateFindings->first()->id)->toBe($noDueDateFinding->id);
});

// Test 3: Finding model due_date accessor methods (isOverdue, isDueSoon)
test('Finding model isOverdue and isDueSoon accessors work correctly', function () {
    // Overdue finding
    $overdueFinding = Finding::factory()->create([
        'due_date' => now()->subDays(5),
    ]);
    expect($overdueFinding->isOverdue())->toBeTrue();
    expect($overdueFinding->isDueSoon())->toBeFalse();

    // Due soon finding (2 days from now)
    $dueSoonFinding = Finding::factory()->create([
        'due_date' => now()->addDays(2),
    ]);
    expect($dueSoonFinding->isOverdue())->toBeFalse();
    expect($dueSoonFinding->isDueSoon())->toBeTrue();

    // Due today finding (should be due soon, not overdue)
    $dueTodayFinding = Finding::factory()->create([
        'due_date' => now()->startOfDay(),
    ]);
    expect($dueTodayFinding->isOverdue())->toBeFalse();
    expect($dueTodayFinding->isDueSoon())->toBeTrue();

    // Due in exactly 3 days (edge case - should be due soon)
    $dueIn3DaysFinding = Finding::factory()->create([
        'due_date' => now()->addDays(3),
    ]);
    expect($dueIn3DaysFinding->isOverdue())->toBeFalse();
    expect($dueIn3DaysFinding->isDueSoon())->toBeTrue();

    // Due in 4 days (should not be due soon)
    $dueIn4DaysFinding = Finding::factory()->create([
        'due_date' => now()->addDays(4),
    ]);
    expect($dueIn4DaysFinding->isOverdue())->toBeFalse();
    expect($dueIn4DaysFinding->isDueSoon())->toBeFalse();

    // No due date finding
    $noDueDateFinding = Finding::factory()->create([
        'due_date' => null,
    ]);
    expect($noDueDateFinding->isOverdue())->toBeFalse();
    expect($noDueDateFinding->isDueSoon())->toBeFalse();
});

// Test 4: statusTransitions relationship on Finding
test('Finding model statusTransitions relationship works correctly', function () {
    $user = User::factory()->create();
    $finding = Finding::factory()->create([
        'status' => FindingStatus::Resolved,
    ]);

    // Create multiple status transitions
    FindingStatusTransition::create([
        'finding_id' => $finding->id,
        'from_status' => FindingStatus::Open,
        'to_status' => FindingStatus::InProgress,
        'user_id' => $user->id,
        'transitioned_at' => now()->subHours(3),
    ]);

    FindingStatusTransition::create([
        'finding_id' => $finding->id,
        'from_status' => FindingStatus::InProgress,
        'to_status' => FindingStatus::PendingReview,
        'user_id' => $user->id,
        'transitioned_at' => now()->subHours(2),
    ]);

    FindingStatusTransition::create([
        'finding_id' => $finding->id,
        'from_status' => FindingStatus::PendingReview,
        'to_status' => FindingStatus::Resolved,
        'user_id' => $user->id,
        'notes' => 'Issue resolved',
        'transitioned_at' => now()->subHour(),
    ]);

    $finding->refresh();

    expect($finding->statusTransitions)->toHaveCount(3);
    expect($finding->statusTransitions->first())->toBeInstanceOf(FindingStatusTransition::class);

    // Verify the transitions are loaded with correct data
    $transitions = $finding->statusTransitions->sortBy('transitioned_at')->values();
    expect($transitions[0]->to_status)->toBe(FindingStatus::InProgress);
    expect($transitions[1]->to_status)->toBe(FindingStatus::PendingReview);
    expect($transitions[2]->to_status)->toBe(FindingStatus::Resolved);
});

// Test 5: Time metrics calculations (time_to_first_response, total_resolution_time)
test('Finding model time metrics calculations work correctly', function () {
    $user = User::factory()->create();
    $createdAt = now()->subDays(5);
    $firstResponseAt = now()->subDays(4); // 1 day to first response
    $resolvedAt = now()->subDay(); // 4 days total resolution time

    $finding = Finding::factory()->create([
        'status' => FindingStatus::Resolved,
        'created_at' => $createdAt,
        'resolved_at' => $resolvedAt,
    ]);

    // Create the transition to InProgress (first response)
    FindingStatusTransition::create([
        'finding_id' => $finding->id,
        'from_status' => FindingStatus::Open,
        'to_status' => FindingStatus::InProgress,
        'user_id' => $user->id,
        'transitioned_at' => $firstResponseAt,
    ]);

    $finding->refresh();

    // Time to first response should be approximately 1 day
    $timeToFirstResponse = $finding->getTimeToFirstResponse();
    expect($timeToFirstResponse)->not->toBeNull();
    expect($timeToFirstResponse)->toBeGreaterThanOrEqual(23 * 60); // At least 23 hours in minutes
    expect($timeToFirstResponse)->toBeLessThanOrEqual(25 * 60); // At most 25 hours in minutes

    // Total resolution time should be approximately 4 days
    $totalResolutionTime = $finding->getTotalResolutionTime();
    expect($totalResolutionTime)->not->toBeNull();
    expect($totalResolutionTime)->toBeGreaterThanOrEqual(3 * 24 * 60); // At least 3 days in minutes
    expect($totalResolutionTime)->toBeLessThanOrEqual(5 * 24 * 60); // At most 5 days in minutes

    // Test with finding that has no first response yet
    $noResponseFinding = Finding::factory()->open()->create();
    expect($noResponseFinding->getTimeToFirstResponse())->toBeNull();

    // Test with finding that is not resolved yet
    $unresolvedFinding = Finding::factory()->inProgress()->create();
    expect($unresolvedFinding->getTotalResolutionTime())->toBeNull();
});

// Test 6: FindingFactory due_date states
test('FindingFactory due_date states work correctly', function () {
    // Test overdue state
    $overdueFinding = Finding::factory()->overdue()->create();
    expect($overdueFinding->due_date)->not->toBeNull();
    expect($overdueFinding->isOverdue())->toBeTrue();

    // Test dueSoon state
    $dueSoonFinding = Finding::factory()->dueSoon()->create();
    expect($dueSoonFinding->due_date)->not->toBeNull();
    expect($dueSoonFinding->isDueSoon())->toBeTrue();
    expect($dueSoonFinding->isOverdue())->toBeFalse();

    // Test noDueDate state
    $noDueDateFinding = Finding::factory()->noDueDate()->create();
    expect($noDueDateFinding->due_date)->toBeNull();
});
