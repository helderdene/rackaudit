<?php

use App\Enums\EvidenceType;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Models\Finding;
use App\Models\FindingCategory;
use App\Models\FindingEvidence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Test 1: FindingSeverity enum values and color() method
test('FindingSeverity enum has correct values and color method', function () {
    // Verify all enum cases exist
    $expectedCases = ['Critical', 'High', 'Medium', 'Low'];
    $actualCases = array_map(fn (FindingSeverity $s) => $s->name, FindingSeverity::cases());
    expect($actualCases)->toBe($expectedCases);

    // Verify string values
    expect(FindingSeverity::Critical->value)->toBe('critical');
    expect(FindingSeverity::High->value)->toBe('high');
    expect(FindingSeverity::Medium->value)->toBe('medium');
    expect(FindingSeverity::Low->value)->toBe('low');

    // Verify labels
    expect(FindingSeverity::Critical->label())->toBe('Critical');
    expect(FindingSeverity::High->label())->toBe('High');
    expect(FindingSeverity::Medium->label())->toBe('Medium');
    expect(FindingSeverity::Low->label())->toBe('Low');

    // Verify color() method returns badge color classes
    expect(FindingSeverity::Critical->color())->toContain('red');
    expect(FindingSeverity::High->color())->toContain('orange');
    expect(FindingSeverity::Medium->color())->toContain('yellow');
    expect(FindingSeverity::Low->color())->toContain('blue');
});

// Test 2: Extended FindingStatus enum with new statuses and color() method
test('FindingStatus enum has extended statuses and color method', function () {
    // Verify all enum cases exist (original + new)
    $expectedCases = ['Open', 'InProgress', 'PendingReview', 'Deferred', 'Resolved'];
    $actualCases = array_map(fn (FindingStatus $s) => $s->name, FindingStatus::cases());
    expect($actualCases)->toBe($expectedCases);

    // Verify string values
    expect(FindingStatus::Open->value)->toBe('open');
    expect(FindingStatus::InProgress->value)->toBe('in_progress');
    expect(FindingStatus::PendingReview->value)->toBe('pending_review');
    expect(FindingStatus::Deferred->value)->toBe('deferred');
    expect(FindingStatus::Resolved->value)->toBe('resolved');

    // Verify labels
    expect(FindingStatus::Open->label())->toBe('Open');
    expect(FindingStatus::InProgress->label())->toBe('In Progress');
    expect(FindingStatus::PendingReview->label())->toBe('Pending Review');
    expect(FindingStatus::Deferred->label())->toBe('Deferred');
    expect(FindingStatus::Resolved->label())->toBe('Resolved');

    // Verify color() method exists and returns valid color classes
    expect(FindingStatus::Open->color())->toBeString();
    expect(FindingStatus::Resolved->color())->toBeString();

    // Verify status transition validation
    expect(FindingStatus::Open->canTransitionTo(FindingStatus::InProgress))->toBeTrue();
    expect(FindingStatus::Open->canTransitionTo(FindingStatus::Resolved))->toBeFalse();
    expect(FindingStatus::InProgress->canTransitionTo(FindingStatus::PendingReview))->toBeTrue();
    expect(FindingStatus::PendingReview->canTransitionTo(FindingStatus::Resolved))->toBeTrue();
});

// Test 3: FindingCategory model basic CRUD operations
test('FindingCategory model CRUD operations work correctly', function () {
    // Create
    $category = FindingCategory::create([
        'name' => 'Hardware Failure',
        'description' => 'Issues related to hardware malfunctions',
        'is_default' => false,
    ]);

    expect($category)->toBeInstanceOf(FindingCategory::class);
    expect($category->name)->toBe('Hardware Failure');
    expect($category->description)->toBe('Issues related to hardware malfunctions');
    expect($category->is_default)->toBeFalse();

    // Read
    $retrievedCategory = FindingCategory::find($category->id);
    expect($retrievedCategory->name)->toBe('Hardware Failure');

    // Update
    $category->update(['name' => 'Equipment Failure']);
    $category->refresh();
    expect($category->name)->toBe('Equipment Failure');

    // Delete
    $category->delete();
    expect(FindingCategory::find($category->id))->toBeNull();

    // Verify unique constraint on name
    FindingCategory::create(['name' => 'Unique Test', 'is_default' => false]);
    expect(fn () => FindingCategory::create(['name' => 'Unique Test', 'is_default' => false]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

// Test 4: FindingEvidence model with file and text types
test('FindingEvidence model handles file and text types correctly', function () {
    $finding = Finding::factory()->create();

    // Create text evidence
    $textEvidence = FindingEvidence::create([
        'finding_id' => $finding->id,
        'type' => EvidenceType::Text,
        'content' => 'This is a detailed note about the finding.',
        'file_path' => null,
        'original_filename' => null,
        'mime_type' => null,
    ]);

    expect($textEvidence->type)->toBe(EvidenceType::Text);
    expect($textEvidence->content)->toBe('This is a detailed note about the finding.');
    expect($textEvidence->file_path)->toBeNull();

    // Create file evidence
    $fileEvidence = FindingEvidence::create([
        'finding_id' => $finding->id,
        'type' => EvidenceType::File,
        'content' => null,
        'file_path' => 'finding-evidence/1/screenshot.png',
        'original_filename' => 'screenshot.png',
        'mime_type' => 'image/png',
    ]);

    expect($fileEvidence->type)->toBe(EvidenceType::File);
    expect($fileEvidence->file_path)->toBe('finding-evidence/1/screenshot.png');
    expect($fileEvidence->original_filename)->toBe('screenshot.png');
    expect($fileEvidence->mime_type)->toBe('image/png');

    // Verify relationship back to finding
    expect($textEvidence->finding)->toBeInstanceOf(Finding::class);
    expect($textEvidence->finding->id)->toBe($finding->id);
});

// Test 5: Finding model new relationships (assignee, category, evidence)
test('Finding model has correct relationships for assignee, category, and evidence', function () {
    $user = User::factory()->create();
    $category = FindingCategory::factory()->create();

    $finding = Finding::factory()->create([
        'assigned_to' => $user->id,
        'finding_category_id' => $category->id,
    ]);

    // Create evidence for the finding
    FindingEvidence::factory()->count(2)->create(['finding_id' => $finding->id]);

    $finding->refresh();

    // Test assignee relationship
    expect($finding->assignee)->toBeInstanceOf(User::class);
    expect($finding->assignee->id)->toBe($user->id);

    // Test category relationship
    expect($finding->category)->toBeInstanceOf(FindingCategory::class);
    expect($finding->category->id)->toBe($category->id);

    // Test evidence relationship
    expect($finding->evidence)->toHaveCount(2);
    expect($finding->evidence->first())->toBeInstanceOf(FindingEvidence::class);

    // Test nullable relationships
    $findingWithoutAssignee = Finding::factory()->create([
        'assigned_to' => null,
        'finding_category_id' => null,
    ]);

    expect($findingWithoutAssignee->assignee)->toBeNull();
    expect($findingWithoutAssignee->category)->toBeNull();
});

// Test 6: Finding model scopes (filterByStatus, filterBySeverity, filterByCategory, filterByAssignee)
test('Finding model filter scopes work correctly', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $category1 = FindingCategory::factory()->create();
    $category2 = FindingCategory::factory()->create();

    // Create findings with various attributes
    Finding::factory()->create([
        'status' => FindingStatus::Open,
        'severity' => FindingSeverity::Critical,
        'assigned_to' => $user1->id,
        'finding_category_id' => $category1->id,
        'title' => 'Critical missing cable',
        'description' => 'Network cable not found',
    ]);

    Finding::factory()->create([
        'status' => FindingStatus::InProgress,
        'severity' => FindingSeverity::Medium,
        'assigned_to' => $user2->id,
        'finding_category_id' => $category2->id,
        'title' => 'Medium issue',
        'description' => 'Something else',
    ]);

    Finding::factory()->create([
        'status' => FindingStatus::Resolved,
        'severity' => FindingSeverity::Low,
        'assigned_to' => $user1->id,
        'finding_category_id' => $category1->id,
        'title' => 'Resolved finding',
        'description' => 'Already fixed',
    ]);

    // Test filterByStatus
    expect(Finding::filterByStatus(FindingStatus::Open)->count())->toBe(1);
    expect(Finding::filterByStatus(FindingStatus::InProgress)->count())->toBe(1);
    expect(Finding::filterByStatus(FindingStatus::Resolved)->count())->toBe(1);

    // Test filterBySeverity
    expect(Finding::filterBySeverity(FindingSeverity::Critical)->count())->toBe(1);
    expect(Finding::filterBySeverity(FindingSeverity::Medium)->count())->toBe(1);
    expect(Finding::filterBySeverity(FindingSeverity::Low)->count())->toBe(1);

    // Test filterByCategory
    expect(Finding::filterByCategory($category1->id)->count())->toBe(2);
    expect(Finding::filterByCategory($category2->id)->count())->toBe(1);

    // Test filterByAssignee
    expect(Finding::filterByAssignee($user1->id)->count())->toBe(2);
    expect(Finding::filterByAssignee($user2->id)->count())->toBe(1);

    // Test searchByTitleOrDescription
    expect(Finding::searchByTitleOrDescription('Critical')->count())->toBe(1);
    expect(Finding::searchByTitleOrDescription('cable')->count())->toBe(1);
    expect(Finding::searchByTitleOrDescription('finding')->count())->toBe(1);

    // Test chained scopes
    expect(
        Finding::filterByStatus(FindingStatus::Open)
            ->filterBySeverity(FindingSeverity::Critical)
            ->count()
    )->toBe(1);
});
