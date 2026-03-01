<?php

use App\Models\User;

describe('notification preferences API', function () {
    test('GET settings/notifications returns current preferences with Inertia', function () {
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

    test('GET settings/notifications returns default preferences when user has null preferences', function () {
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

    test('PATCH settings/notifications updates preferences successfully', function () {
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

    test('validation rejects invalid category keys', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('notifications.edit'))
            ->patch(route('notifications.update'), [
                'audit_assignments' => true,
                'invalid_category' => true,
            ]);

        $response->assertSessionHasErrors('invalid_category');
    });

    test('validation rejects non-boolean values', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('notifications.edit'))
            ->patch(route('notifications.update'), [
                'audit_assignments' => 'not-a-boolean',
                'finding_updates' => true,
            ]);

        $response->assertSessionHasErrors('audit_assignments');
    });

    test('unauthenticated users cannot access notification preferences', function () {
        $response = $this->get(route('notifications.edit'));

        $response->assertRedirect(route('login'));
    });

    test('unauthenticated users cannot update notification preferences', function () {
        $response = $this->patch(route('notifications.update'), [
            'audit_assignments' => false,
        ]);

        $response->assertRedirect(route('login'));
    });
});
