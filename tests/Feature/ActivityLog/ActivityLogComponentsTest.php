<?php

use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Clear any activity logs created during seeding
    ActivityLog::query()->delete();

    // Disable Inertia page existence check since Vue components are created later
    config(['inertia.testing.ensure_pages_exist' => false]);
});

describe('ActionBadge Component Data', function () {
    it('provides correct variant mapping for created action', function () {
        // The ActionBadge component uses these mappings:
        // created -> success (green), updated -> warning (yellow), deleted -> destructive (red)
        $user = User::withoutEvents(fn () => User::factory()->create());
        $user->assignRole('Administrator');

        $activityLog = ActivityLog::factory()->created()->create([
            'subject_type' => User::class,
            'subject_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('activity-logs.index'));

        $response->assertSuccessful();

        // Verify the activity log data is passed to the component with correct action type
        $response->assertInertia(fn ($page) => $page
            ->component('ActivityLogs/Index')
            ->where('activityLogs.data.0.action', 'created')
        );
    });

    it('provides correct variant mapping for updated action', function () {
        $user = User::withoutEvents(fn () => User::factory()->create());
        $user->assignRole('Administrator');

        $activityLog = ActivityLog::factory()->updated()->create([
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'old_values' => ['name' => 'Old Name'],
            'new_values' => ['name' => 'New Name'],
        ]);

        $response = $this->actingAs($user)->get(route('activity-logs.index'));

        $response->assertSuccessful();

        $response->assertInertia(fn ($page) => $page
            ->component('ActivityLogs/Index')
            ->where('activityLogs.data.0.action', 'updated')
        );
    });

    it('provides correct variant mapping for deleted action', function () {
        $user = User::withoutEvents(fn () => User::factory()->create());
        $user->assignRole('Administrator');

        $activityLog = ActivityLog::factory()->deleted()->create([
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'old_values' => ['name' => 'Deleted User'],
            'new_values' => null,
        ]);

        $response = $this->actingAs($user)->get(route('activity-logs.index'));

        $response->assertSuccessful();

        $response->assertInertia(fn ($page) => $page
            ->component('ActivityLogs/Index')
            ->where('activityLogs.data.0.action', 'deleted')
        );
    });
});

describe('ActivityDetailPanel Component Data', function () {
    it('passes old and new values for display in diff panel', function () {
        $user = User::withoutEvents(fn () => User::factory()->create());
        $user->assignRole('Administrator');

        $oldValues = ['name' => 'John Doe', 'email' => 'john@example.com'];
        $newValues = ['name' => 'Jane Doe', 'email' => 'john@example.com'];

        $activityLog = ActivityLog::factory()->updated()->create([
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);

        $response = $this->actingAs($user)->get(route('activity-logs.index'));

        $response->assertSuccessful();

        $response->assertInertia(fn ($page) => $page
            ->component('ActivityLogs/Index')
            ->has('activityLogs.data.0.old_values')
            ->has('activityLogs.data.0.new_values')
            ->where('activityLogs.data.0.old_values.name', 'John Doe')
            ->where('activityLogs.data.0.new_values.name', 'Jane Doe')
        );
    });

    it('handles null old values for created actions', function () {
        $user = User::withoutEvents(fn () => User::factory()->create());
        $user->assignRole('Administrator');

        $newValues = ['name' => 'New User', 'email' => 'new@example.com'];

        $activityLog = ActivityLog::factory()->created()->create([
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'old_values' => null,
            'new_values' => $newValues,
        ]);

        $response = $this->actingAs($user)->get(route('activity-logs.index'));

        $response->assertSuccessful();

        $response->assertInertia(fn ($page) => $page
            ->component('ActivityLogs/Index')
            ->where('activityLogs.data.0.old_values', null)
            ->where('activityLogs.data.0.new_values.name', 'New User')
        );
    });

    it('handles null new values for deleted actions', function () {
        $user = User::withoutEvents(fn () => User::factory()->create());
        $user->assignRole('Administrator');

        $oldValues = ['name' => 'Deleted User', 'email' => 'deleted@example.com'];

        $activityLog = ActivityLog::factory()->deleted()->create([
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'old_values' => $oldValues,
            'new_values' => null,
        ]);

        $response = $this->actingAs($user)->get(route('activity-logs.index'));

        $response->assertSuccessful();

        $response->assertInertia(fn ($page) => $page
            ->component('ActivityLogs/Index')
            ->where('activityLogs.data.0.old_values.name', 'Deleted User')
            ->where('activityLogs.data.0.new_values', null)
        );
    });
});
