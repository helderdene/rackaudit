<?php

use App\Enums\FindingStatus;
use App\Models\Discrepancy;
use App\Models\Finding;
use App\Models\ImplementationFile;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Notifications\DiscrepancyThresholdNotification;
use App\Notifications\FindingAssignedNotification;
use App\Notifications\FindingDueDateApproachingNotification;
use App\Notifications\FindingOverdueNotification;
use App\Notifications\FindingReassignedNotification;
use App\Notifications\FindingStatusChangedNotification;
use App\Notifications\ImplementationFileApprovedNotification;
use App\Notifications\ImplementationFileAwaitingApprovalNotification;
use App\Notifications\NewDiscrepancyNotification;
use App\Notifications\ScheduledReportFailedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

describe('notification classes respect user email preferences', function () {
    test('FindingAssignedNotification respects finding_updates preference', function () {
        $userDisabled = User::factory()->create([
            'notification_preferences' => ['finding_updates' => false],
        ]);
        $userEnabled = User::factory()->create([
            'notification_preferences' => ['finding_updates' => true],
        ]);

        $finding = Finding::factory()->create();
        $notification = new FindingAssignedNotification($finding);

        // Database should always be included
        expect($notification->via($userDisabled))->toContain('database');
        expect($notification->via($userEnabled))->toContain('database');

        // Mail should not be included when preference is disabled
        expect($notification->via($userDisabled))->not->toContain('mail');
    });

    test('FindingReassignedNotification respects finding_updates preference', function () {
        $userDisabled = User::factory()->create([
            'notification_preferences' => ['finding_updates' => false],
        ]);

        $finding = Finding::factory()->create();
        $newAssignee = User::factory()->create();
        $notification = new FindingReassignedNotification($finding, $newAssignee);

        expect($notification->via($userDisabled))->toContain('database');
        expect($notification->via($userDisabled))->not->toContain('mail');
    });

    test('FindingStatusChangedNotification respects finding_updates preference', function () {
        $userDisabled = User::factory()->create([
            'notification_preferences' => ['finding_updates' => false],
        ]);

        $finding = Finding::factory()->create();
        $notification = new FindingStatusChangedNotification(
            $finding,
            FindingStatus::Open,
            FindingStatus::InProgress
        );

        expect($notification->via($userDisabled))->toContain('database');
        expect($notification->via($userDisabled))->not->toContain('mail');
    });

    test('FindingDueDateApproachingNotification respects finding_updates preference', function () {
        $userDisabled = User::factory()->create([
            'notification_preferences' => ['finding_updates' => false],
        ]);

        $finding = Finding::factory()->create(['due_date' => now()->addDays(3)]);
        $notification = new FindingDueDateApproachingNotification($finding);

        expect($notification->via($userDisabled))->toContain('database');
        expect($notification->via($userDisabled))->not->toContain('mail');
    });

    test('FindingOverdueNotification respects finding_updates preference', function () {
        $userDisabled = User::factory()->create([
            'notification_preferences' => ['finding_updates' => false],
        ]);

        $finding = Finding::factory()->create(['due_date' => now()->subDays(1)]);
        $notification = new FindingOverdueNotification($finding);

        expect($notification->via($userDisabled))->toContain('database');
        expect($notification->via($userDisabled))->not->toContain('mail');
    });

    test('ImplementationFileAwaitingApprovalNotification respects approval_requests preference', function () {
        $userDisabled = User::factory()->create([
            'notification_preferences' => ['approval_requests' => false],
        ]);

        $implementationFile = ImplementationFile::factory()->create();
        $notification = new ImplementationFileAwaitingApprovalNotification($implementationFile);

        expect($notification->via($userDisabled))->toContain('database');
        expect($notification->via($userDisabled))->not->toContain('mail');
    });

    test('ImplementationFileApprovedNotification respects approval_requests preference', function () {
        $userDisabled = User::factory()->create([
            'notification_preferences' => ['approval_requests' => false],
        ]);

        $implementationFile = ImplementationFile::factory()->create();
        $approver = User::factory()->create();
        $notification = new ImplementationFileApprovedNotification($implementationFile, $approver);

        expect($notification->via($userDisabled))->toContain('database');
        expect($notification->via($userDisabled))->not->toContain('mail');
    });

    test('NewDiscrepancyNotification respects discrepancies preference', function () {
        $userDisabled = User::factory()->create([
            'notification_preferences' => ['discrepancies' => false],
        ]);

        $discrepancy = Discrepancy::factory()->create();
        $notification = new NewDiscrepancyNotification($discrepancy);

        expect($notification->via($userDisabled))->toContain('database');
        expect($notification->via($userDisabled))->not->toContain('mail');
    });

    test('DiscrepancyThresholdNotification respects discrepancies preference', function () {
        $userDisabled = User::factory()->create([
            'notification_preferences' => ['discrepancies' => false],
        ]);

        $discrepancies = Collection::make([Discrepancy::factory()->create()]);
        $summary = ['total_count' => 15, 'datacenter_name' => 'Test DC'];
        $notification = new DiscrepancyThresholdNotification($discrepancies, $summary);

        expect($notification->via($userDisabled))->toContain('database');
        expect($notification->via($userDisabled))->not->toContain('mail');
    });

    test('ScheduledReportFailedNotification respects scheduled_reports preference', function () {
        $userDisabled = User::factory()->create([
            'notification_preferences' => ['scheduled_reports' => false],
        ]);

        $schedule = ReportSchedule::factory()->create();
        $notification = new ScheduledReportFailedNotification($schedule, 'Test error');

        expect($notification->via($userDisabled))->toContain('database');
        expect($notification->via($userDisabled))->not->toContain('mail');
    });
});

describe('end-to-end preference workflow', function () {
    test('user changes preference and notification respects new setting', function () {
        // Start with user who has email enabled (default)
        $user = User::factory()->create([
            'notification_preferences' => null,
        ]);

        expect($user->hasEmailEnabledFor('finding_updates'))->toBeTrue();

        // Update preference to disable finding_updates emails
        $this
            ->actingAs($user)
            ->patch(route('notifications.update'), [
                'audit_assignments' => true,
                'finding_updates' => false,
                'approval_requests' => true,
                'discrepancies' => true,
                'scheduled_reports' => true,
            ]);

        $user->refresh();

        // Verify preference was updated
        expect($user->hasEmailEnabledFor('finding_updates'))->toBeFalse();

        // Create a notification and verify it respects the new preference
        $finding = Finding::factory()->create();
        $notification = new FindingAssignedNotification($finding);

        // Mail channel should not be included for this user
        expect($notification->via($user))->toContain('database');
        expect($notification->via($user))->not->toContain('mail');
    });
});
