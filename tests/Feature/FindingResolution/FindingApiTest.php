<?php

use App\Enums\FindingStatus;
use App\Models\Audit;
use App\Models\Finding;
use App\Models\FindingStatusTransition;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
});

test('POST /findings/{finding}/transition creates status transition and updates finding', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $finding = Finding::factory()->open()->create();

    $response = $this->actingAs($user)
        ->postJson("/findings/{$finding->id}/transition", [
            'target_status' => FindingStatus::InProgress->value,
            'notes' => 'Starting work on this finding',
        ]);

    $response->assertSuccessful();

    // Verify finding status was updated
    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::InProgress);

    // Verify transition was logged
    $this->assertDatabaseHas('finding_status_transitions', [
        'finding_id' => $finding->id,
        'from_status' => FindingStatus::Open->value,
        'to_status' => FindingStatus::InProgress->value,
        'user_id' => $user->id,
        'notes' => 'Starting work on this finding',
    ]);
});

test('POST /findings/{finding}/transition enforces workflow rules for non-admins', function () {
    $user = User::factory()->create();
    $user->assignRole('Operator');

    // Create an audit and assign the user to it so they have access
    $audit = Audit::factory()->create();
    $audit->assignees()->attach($user->id);

    $finding = Finding::factory()->open()->forAudit($audit)->create();

    // Try to skip directly to Resolved (not allowed in workflow)
    $response = $this->actingAs($user)
        ->postJson("/findings/{$finding->id}/transition", [
            'target_status' => FindingStatus::Resolved->value,
            'notes' => 'This transition should fail',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['target_status']);

    // Verify finding status was NOT updated
    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::Open);
});

test('POST /findings/bulk-assign assigns multiple findings to a user', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('IT Manager');

    $newAssignee = User::factory()->create();

    $findings = Finding::factory()->count(3)->create();

    $response = $this->actingAs($admin)
        ->postJson('/findings/bulk-assign', [
            'finding_ids' => $findings->pluck('id')->toArray(),
            'assigned_to' => $newAssignee->id,
        ]);

    $response->assertSuccessful();
    $response->assertJsonFragment(['success_count' => 3]);

    // Verify all findings were assigned
    foreach ($findings as $finding) {
        $this->assertDatabaseHas('findings', [
            'id' => $finding->id,
            'assigned_to' => $newAssignee->id,
        ]);
    }
});

test('POST /findings/bulk-status changes status for multiple findings', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $findings = Finding::factory()->count(3)->open()->create();

    $response = $this->actingAs($admin)
        ->postJson('/findings/bulk-status', [
            'finding_ids' => $findings->pluck('id')->toArray(),
            'status' => FindingStatus::InProgress->value,
        ]);

    $response->assertSuccessful();
    $response->assertJsonFragment(['success_count' => 3]);

    // Verify all findings had status changed
    foreach ($findings as $finding) {
        $this->assertDatabaseHas('findings', [
            'id' => $finding->id,
            'status' => FindingStatus::InProgress->value,
        ]);
    }

    // Verify transitions were logged
    expect(FindingStatusTransition::count())->toBe(3);
});

test('GET /findings filters by due date status', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create findings with different due date statuses
    Finding::factory()->count(2)->overdue()->create();
    Finding::factory()->count(3)->dueSoon()->create();
    Finding::factory()->count(2)->noDueDate()->create();

    // Test overdue filter
    $response = $this->actingAs($user)
        ->get('/findings?due_date_status=overdue');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Findings/Index')
            ->has('findings.data', 2)
        );

    // Test due_soon filter
    $response = $this->actingAs($user)
        ->get('/findings?due_date_status=due_soon');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Findings/Index')
            ->has('findings.data', 3)
        );

    // Test no_due_date filter
    $response = $this->actingAs($user)
        ->get('/findings?due_date_status=no_due_date');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Findings/Index')
            ->has('findings.data', 2)
        );
});

test('PUT /findings/{finding} validates minimum resolution notes length when resolving', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $finding = Finding::factory()->pendingReview()->create();

    // Try to resolve with short notes (less than 10 characters)
    $response = $this->actingAs($admin)
        ->putJson("/findings/{$finding->id}", [
            'status' => FindingStatus::Resolved->value,
            'resolution_notes' => 'Short',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['resolution_notes']);

    // Verify finding status was NOT updated
    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::PendingReview);
});

test('PUT /findings/{finding} creates FindingStatusTransition on status change', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $finding = Finding::factory()->open()->create();

    $response = $this->actingAs($user)
        ->from("/findings/{$finding->id}")
        ->put("/findings/{$finding->id}", [
            'status' => FindingStatus::InProgress->value,
        ]);

    // Update returns a redirect back, so assert redirect
    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Verify transition was logged
    $this->assertDatabaseHas('finding_status_transitions', [
        'finding_id' => $finding->id,
        'from_status' => FindingStatus::Open->value,
        'to_status' => FindingStatus::InProgress->value,
        'user_id' => $user->id,
    ]);
});

test('PUT /findings/{finding} dispatches notification on assignment change', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('IT Manager');

    $newAssignee = User::factory()->create();

    $finding = Finding::factory()->create(['assigned_to' => null]);

    $response = $this->actingAs($admin)
        ->from("/findings/{$finding->id}")
        ->put("/findings/{$finding->id}", [
            'assigned_to' => $newAssignee->id,
        ]);

    // Update returns a redirect back, so assert redirect
    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Verify notification was dispatched to new assignee
    Notification::assertSentTo(
        $newAssignee,
        \App\Notifications\FindingAssignedNotification::class
    );
});
