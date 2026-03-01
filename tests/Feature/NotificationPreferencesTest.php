<?php

use App\Models\User;

describe('notification preferences data model', function () {
    test('notification_preferences attribute is cast to array', function () {
        $user = User::factory()->create([
            'notification_preferences' => [
                'audit_assignments' => true,
                'finding_updates' => false,
            ],
        ]);

        $user->refresh();

        expect($user->notification_preferences)->toBeArray();
        expect($user->notification_preferences['audit_assignments'])->toBeTrue();
        expect($user->notification_preferences['finding_updates'])->toBeFalse();
    });

    test('hasEmailEnabledFor returns correct boolean for each category', function () {
        $user = User::factory()->create([
            'notification_preferences' => [
                'audit_assignments' => true,
                'finding_updates' => false,
                'approval_requests' => true,
                'discrepancies' => false,
                'scheduled_reports' => true,
            ],
        ]);

        expect($user->hasEmailEnabledFor('audit_assignments'))->toBeTrue();
        expect($user->hasEmailEnabledFor('finding_updates'))->toBeFalse();
        expect($user->hasEmailEnabledFor('approval_requests'))->toBeTrue();
        expect($user->hasEmailEnabledFor('discrepancies'))->toBeFalse();
        expect($user->hasEmailEnabledFor('scheduled_reports'))->toBeTrue();
    });

    test('default preferences are applied when notification_preferences column is null', function () {
        $user = User::factory()->create([
            'notification_preferences' => null,
        ]);

        // Default is opt-out model: all categories enabled
        expect($user->hasEmailEnabledFor('audit_assignments'))->toBeTrue();
        expect($user->hasEmailEnabledFor('finding_updates'))->toBeTrue();
        expect($user->hasEmailEnabledFor('approval_requests'))->toBeTrue();
        expect($user->hasEmailEnabledFor('discrepancies'))->toBeTrue();
        expect($user->hasEmailEnabledFor('scheduled_reports'))->toBeTrue();
    });

    test('hasEmailEnabledFor returns default true for unknown categories when preferences is null', function () {
        $user = User::factory()->create([
            'notification_preferences' => null,
        ]);

        // Unknown categories should default to enabled (opt-out model)
        expect($user->hasEmailEnabledFor('some_future_category'))->toBeTrue();
    });

    test('hasEmailEnabledFor returns default true for categories not explicitly set', function () {
        $user = User::factory()->create([
            'notification_preferences' => [
                'audit_assignments' => false,
                // finding_updates not set explicitly
            ],
        ]);

        // Explicitly set to false
        expect($user->hasEmailEnabledFor('audit_assignments'))->toBeFalse();
        // Not set, should default to true (opt-out model)
        expect($user->hasEmailEnabledFor('finding_updates'))->toBeTrue();
    });

    test('discrepancy_notifications field still works for backward compatibility', function () {
        $user = User::factory()->create([
            'discrepancy_notifications' => 'all',
        ]);

        expect($user->wantsAllDiscrepancyNotifications())->toBeTrue();
        expect($user->wantsThresholdOnlyDiscrepancyNotifications())->toBeFalse();
        expect($user->wantsNoDiscrepancyNotifications())->toBeFalse();

        $user->discrepancy_notifications = 'threshold_only';
        expect($user->wantsThresholdOnlyDiscrepancyNotifications())->toBeTrue();

        $user->discrepancy_notifications = 'none';
        expect($user->wantsNoDiscrepancyNotifications())->toBeTrue();
    });
});
