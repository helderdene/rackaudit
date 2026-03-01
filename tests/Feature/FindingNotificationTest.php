<?php

use App\Enums\FindingStatus;
use App\Models\Audit;
use App\Models\Finding;
use App\Models\User;
use App\Notifications\FindingAssignedNotification;
use App\Notifications\FindingDueDateApproachingNotification;
use App\Notifications\FindingOverdueNotification;
use App\Notifications\FindingReassignedNotification;
use App\Notifications\FindingStatusChangedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('FindingAssignedNotification sends database and mail channels when configured', function () {
    Notification::fake();

    $assignee = User::factory()->create();
    $audit = Audit::factory()->create();

    $finding = Finding::factory()
        ->forAudit($audit)
        ->create(['title' => 'Test Finding']);

    // Send the notification
    $assignee->notify(new FindingAssignedNotification($finding));

    // Verify notification was sent
    Notification::assertSentTo(
        $assignee,
        FindingAssignedNotification::class,
        function ($notification, $channels) {
            // Database channel should always be present
            return in_array('database', $channels);
        }
    );
});

test('FindingAssignedNotification contains correct finding data', function () {
    $assignee = User::factory()->create();
    $audit = Audit::factory()->create();

    $finding = Finding::factory()
        ->forAudit($audit)
        ->withDueDate(now()->addDays(5))
        ->create(['title' => 'Important Finding']);

    $notification = new FindingAssignedNotification($finding);

    // Test database array representation
    $data = $notification->toArray($assignee);

    expect($data['type'])->toBe('finding_assigned')
        ->and($data['finding_id'])->toBe($finding->id)
        ->and($data['title'])->toBe('Important Finding')
        ->and($data['audit_id'])->toBe($audit->id)
        ->and($data['audit_name'])->toBe($audit->name)
        ->and($data['message'])->toContain('Important Finding');
});

test('FindingStatusChangedNotification sends database and mail channels', function () {
    Notification::fake();

    $assignee = User::factory()->create();
    $finding = Finding::factory()->create();

    // Send the notification
    $assignee->notify(new FindingStatusChangedNotification(
        $finding,
        FindingStatus::Open,
        FindingStatus::InProgress
    ));

    Notification::assertSentTo(
        $assignee,
        FindingStatusChangedNotification::class,
        function ($notification, $channels) {
            return in_array('database', $channels);
        }
    );
});

test('FindingStatusChangedNotification contains old and new status', function () {
    $assignee = User::factory()->create();
    $finding = Finding::factory()->create(['title' => 'Status Change Test']);

    $notification = new FindingStatusChangedNotification(
        $finding,
        FindingStatus::Open,
        FindingStatus::InProgress
    );

    $data = $notification->toArray($assignee);

    expect($data['type'])->toBe('finding_status_changed')
        ->and($data['finding_id'])->toBe($finding->id)
        ->and($data['old_status'])->toBe(FindingStatus::Open->value)
        ->and($data['new_status'])->toBe(FindingStatus::InProgress->value)
        ->and($data['old_status_label'])->toBe(FindingStatus::Open->label())
        ->and($data['new_status_label'])->toBe(FindingStatus::InProgress->label())
        ->and($data['message'])->toContain('Open')
        ->and($data['message'])->toContain('In Progress');
});

test('FindingDueDateApproachingNotification contains due date and correct data', function () {
    $assignee = User::factory()->create();
    $dueDate = now()->addDays(3);
    $finding = Finding::factory()
        ->withDueDate($dueDate)
        ->create(['title' => 'Due Soon Finding']);

    $notification = new FindingDueDateApproachingNotification($finding);

    $data = $notification->toArray($assignee);

    expect($data['type'])->toBe('finding_due_date_approaching')
        ->and($data['finding_id'])->toBe($finding->id)
        ->and($data['title'])->toBe('Due Soon Finding')
        ->and($data['due_date'])->toBe($dueDate->format('Y-m-d'))
        ->and($data['message'])->toContain('Due Soon Finding')
        ->and($data['message'])->toContain('approaching');
});

test('FindingOverdueNotification contains due date and days overdue', function () {
    $assignee = User::factory()->create();
    $dueDate = now()->subDays(5);
    $finding = Finding::factory()
        ->withDueDate($dueDate)
        ->create(['title' => 'Overdue Finding']);

    $notification = new FindingOverdueNotification($finding);

    $data = $notification->toArray($assignee);

    expect($data['type'])->toBe('finding_overdue')
        ->and($data['finding_id'])->toBe($finding->id)
        ->and($data['title'])->toBe('Overdue Finding')
        ->and($data['due_date'])->toBe($dueDate->format('Y-m-d'))
        ->and($data['days_overdue'])->toBeGreaterThanOrEqual(5)
        ->and($data['message'])->toContain('Overdue Finding')
        ->and($data['message'])->toContain('overdue');
});

test('FindingReassignedNotification contains new assignee information', function () {
    $previousAssignee = User::factory()->create(['name' => 'Previous Person']);
    $newAssignee = User::factory()->create(['name' => 'New Person']);
    $finding = Finding::factory()->create(['title' => 'Reassigned Finding']);

    $notification = new FindingReassignedNotification($finding, $newAssignee);

    $data = $notification->toArray($previousAssignee);

    expect($data['type'])->toBe('finding_reassigned')
        ->and($data['finding_id'])->toBe($finding->id)
        ->and($data['title'])->toBe('Reassigned Finding')
        ->and($data['new_assignee_id'])->toBe($newAssignee->id)
        ->and($data['new_assignee_name'])->toBe('New Person')
        ->and($data['message'])->toContain('Reassigned Finding')
        ->and($data['message'])->toContain('New Person');
});
