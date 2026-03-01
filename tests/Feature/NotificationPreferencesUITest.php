<?php

use App\Models\User;

describe('notification preferences UI', function () {
    test('preferences page renders with correct form fields for all categories', function () {
        $user = User::factory()->create([
            'notification_preferences' => [
                'audit_assignments' => true,
                'finding_updates' => false,
                'approval_requests' => true,
                'discrepancies' => false,
                'scheduled_reports' => true,
            ],
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('notifications.edit'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('settings/Notifications')
                ->has('preferences')
                ->where('preferences.audit_assignments', true)
                ->where('preferences.finding_updates', false)
                ->where('preferences.approval_requests', true)
                ->where('preferences.discrepancies', false)
                ->where('preferences.scheduled_reports', true)
        );
    });

    test('form submission updates preferences successfully', function () {
        $user = User::factory()->create([
            'notification_preferences' => [
                'audit_assignments' => true,
                'finding_updates' => true,
                'approval_requests' => true,
                'discrepancies' => true,
                'scheduled_reports' => true,
            ],
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('notifications.update'), [
                'audit_assignments' => false,
                'finding_updates' => true,
                'approval_requests' => false,
                'discrepancies' => true,
                'scheduled_reports' => false,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('notifications.edit'));

        $user->refresh();

        expect($user->notification_preferences)->toBe([
            'audit_assignments' => false,
            'finding_updates' => true,
            'approval_requests' => false,
            'discrepancies' => true,
            'scheduled_reports' => false,
        ]);
    });

    test('default preferences are all enabled when user has no preferences set', function () {
        $user = User::factory()->create([
            'notification_preferences' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('notifications.edit'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('settings/Notifications')
                ->has('preferences')
                ->where('preferences.audit_assignments', true)
                ->where('preferences.finding_updates', true)
                ->where('preferences.approval_requests', true)
                ->where('preferences.discrepancies', true)
                ->where('preferences.scheduled_reports', true)
        );
    });

    test('in-app notifications category data is always provided', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('notifications.edit'));

        $response->assertOk();
        // The page should render with all five notification categories
        $response->assertInertia(
            fn ($page) => $page
                ->component('settings/Notifications')
                ->has('preferences', 5)
        );
    });
});
