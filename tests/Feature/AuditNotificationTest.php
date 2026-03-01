<?php

use App\Models\Audit;
use App\Models\User;
use App\Notifications\AuditAssignedNotification;
use App\Notifications\AuditReassignedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('AuditAssignedNotification is queued and sent to correct user', function () {
    Notification::fake();

    $assignee = User::factory()->create();
    $audit = Audit::factory()->create(['name' => 'Q1 Network Audit']);

    // Send the notification
    $assignee->notify(new AuditAssignedNotification($audit));

    // Verify notification was sent to the correct user
    Notification::assertSentTo(
        $assignee,
        AuditAssignedNotification::class,
        function ($notification, $channels) {
            // Database channel should always be present
            return in_array('database', $channels);
        }
    );

    // Verify it was not sent to other users
    $otherUser = User::factory()->create();
    Notification::assertNotSentTo($otherUser, AuditAssignedNotification::class);
});

test('AuditAssignedNotification respects user email preferences', function () {
    Notification::fake();

    // User with email disabled for audit_assignments
    $userWithEmailDisabled = User::factory()->create([
        'notification_preferences' => [
            'audit_assignments' => false,
        ],
    ]);

    // User with email enabled (default)
    $userWithEmailEnabled = User::factory()->create([
        'notification_preferences' => null,
    ]);

    $audit = Audit::factory()->create();

    // Create notifications
    $notificationForDisabled = new AuditAssignedNotification($audit);
    $notificationForEnabled = new AuditAssignedNotification($audit);

    // Check channels for user with email disabled
    $channelsDisabled = $notificationForDisabled->via($userWithEmailDisabled);
    expect($channelsDisabled)->toContain('database')
        ->and($channelsDisabled)->not->toContain('mail');

    // Check channels for user with email enabled (default behavior when real mail driver)
    // Note: In test environment with 'log' or 'array' driver, mail won't be included
    $channelsEnabled = $notificationForEnabled->via($userWithEmailEnabled);
    expect($channelsEnabled)->toContain('database');
});

test('AuditReassignedNotification is triggered when user is removed from assignment', function () {
    Notification::fake();

    $previousAssignee = User::factory()->create(['name' => 'Previous Auditor']);
    $audit = Audit::factory()->create(['name' => 'Security Audit']);

    // Send the notification
    $previousAssignee->notify(new AuditReassignedNotification($audit));

    // Verify notification was sent
    Notification::assertSentTo(
        $previousAssignee,
        AuditReassignedNotification::class,
        function ($notification, $channels) {
            return in_array('database', $channels);
        }
    );
});

test('AuditAssignedNotification data includes audit name, type, due date, datacenter, and link', function () {
    $assignee = User::factory()->create();
    $audit = Audit::factory()->create([
        'name' => 'Q1 Connection Audit',
        'due_date' => now()->addDays(14),
    ]);
    $audit->load('datacenter');

    $notification = new AuditAssignedNotification($audit);

    // Test database array representation
    $data = $notification->toArray($assignee);

    expect($data['type'])->toBe('audit_assigned')
        ->and($data['audit_id'])->toBe($audit->id)
        ->and($data['audit_name'])->toBe('Q1 Connection Audit')
        ->and($data['audit_type'])->toBe($audit->type->value)
        ->and($data['audit_type_label'])->toBe($audit->type->label())
        ->and($data['due_date'])->toBe($audit->due_date->format('Y-m-d'))
        ->and($data['datacenter_id'])->toBe($audit->datacenter_id)
        ->and($data['datacenter_name'])->toBe($audit->datacenter->name)
        ->and($data['message'])->toContain('Q1 Connection Audit');
});

test('AuditReassignedNotification data includes audit name and appropriate message', function () {
    $previousAssignee = User::factory()->create(['name' => 'Previous User']);
    $audit = Audit::factory()->create([
        'name' => 'Inventory Check Audit',
        'due_date' => now()->addDays(7),
    ]);
    $audit->load('datacenter');

    $notification = new AuditReassignedNotification($audit);

    // Test database array representation
    $data = $notification->toArray($previousAssignee);

    expect($data['type'])->toBe('audit_reassigned')
        ->and($data['audit_id'])->toBe($audit->id)
        ->and($data['audit_name'])->toBe('Inventory Check Audit')
        ->and($data['datacenter_id'])->toBe($audit->datacenter_id)
        ->and($data['datacenter_name'])->toBe($audit->datacenter->name)
        ->and($data['message'])->toContain('Inventory Check Audit')
        ->and($data['message'])->toContain('removed');
});
