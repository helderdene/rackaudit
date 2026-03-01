<?php

use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Clear any activity logs created during seeding
    ActivityLog::query()->delete();

    // Disable Inertia page existence check
    config(['inertia.testing.ensure_pages_exist' => false]);
});

describe('ActivityLogs/Index Page', function () {
    it('renders table with activity log data', function () {
        $admin = User::withoutEvents(fn () => User::factory()->create());
        $admin->assignRole('Administrator');

        // Create activity logs with various actions
        ActivityLog::factory()->created()->create([
            'subject_type' => User::class,
            'subject_id' => $admin->id,
            'causer_id' => $admin->id,
            'new_values' => ['name' => 'Test User'],
        ]);

        ActivityLog::factory()->updated()->create([
            'subject_type' => User::class,
            'subject_id' => $admin->id,
            'causer_id' => $admin->id,
            'old_values' => ['name' => 'Old Name'],
            'new_values' => ['name' => 'New Name'],
        ]);

        $response = $this->actingAs($admin)->get(route('activity-logs.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ActivityLogs/Index')
                ->has('activityLogs.data', 2)
                ->has('activityLogs.data.0', fn (Assert $log) => $log
                    ->has('id')
                    ->has('action')
                    ->has('subject_type')
                    ->has('subject_id')
                    ->has('causer_name')
                    ->has('created_at')
                    ->has('old_values')
                    ->has('new_values')
                    ->etc()
                )
                ->has('availableActions', 3)
                ->has('availableSubjectTypes')
                ->has('users')
                ->has('filters')
            );
    });

    it('passes filter values back for state preservation', function () {
        $admin = User::withoutEvents(fn () => User::factory()->create());
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->get(route('activity-logs.index', [
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'action' => 'created',
            'search' => 'test search',
        ]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ActivityLogs/Index')
                ->where('filters.start_date', '2024-01-01')
                ->where('filters.end_date', '2024-12-31')
                ->where('filters.action', 'created')
                ->where('filters.search', 'test search')
            );
    });

    it('provides pagination data for navigation', function () {
        $admin = User::withoutEvents(fn () => User::factory()->create());
        $admin->assignRole('Administrator');

        // Create more than 25 logs to trigger pagination
        ActivityLog::factory()->count(30)->create([
            'causer_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('activity-logs.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ActivityLogs/Index')
                ->has('activityLogs.data', 25) // First page has 25 items
                ->has('activityLogs.links')
                ->where('activityLogs.current_page', 1)
                ->where('activityLogs.last_page', 2)
                ->where('activityLogs.total', 30)
                ->where('activityLogs.per_page', 25)
            );

        // Navigate to second page
        $response = $this->actingAs($admin)->get(route('activity-logs.index', ['page' => 2]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ActivityLogs/Index')
                ->has('activityLogs.data', 5) // Second page has remaining 5 items
                ->where('activityLogs.current_page', 2)
            );
    });

    it('provides users list for admin filter dropdown', function () {
        $admin = User::withoutEvents(fn () => User::factory()->create(['name' => 'Admin User']));
        $admin->assignRole('Administrator');

        $user1 = User::withoutEvents(fn () => User::factory()->create(['name' => 'Alice Smith']));
        $user2 = User::withoutEvents(fn () => User::factory()->create(['name' => 'Bob Johnson']));

        $response = $this->actingAs($admin)->get(route('activity-logs.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ActivityLogs/Index')
                ->has('users', 3) // 3 users total
                ->has('users.0', fn (Assert $user) => $user
                    ->has('id')
                    ->has('name')
                )
            );
    });
});
