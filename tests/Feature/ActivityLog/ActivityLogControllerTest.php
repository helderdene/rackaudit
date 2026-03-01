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

    // Disable Inertia page existence check since Vue components are created later
    config(['inertia.testing.ensure_pages_exist' => false]);
});

test('index returns paginated activity logs for Administrator', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create());
    $admin->assignRole('Administrator');

    // Create activity logs
    ActivityLog::factory()->count(30)->create();

    $response = $this->actingAs($admin)->get('/activity-logs');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ActivityLogs/Index')
            ->has('activityLogs.data', 25) // 25 per page
            ->has('activityLogs.links')
        );
});

test('index applies role-based filtering for Viewer role', function () {
    $viewer = User::withoutEvents(fn () => User::factory()->create());
    $viewer->assignRole('Viewer');

    $otherUser = User::withoutEvents(fn () => User::factory()->create());

    // Create logs by the viewer
    ActivityLog::factory()->count(3)->create(['causer_id' => $viewer->id]);

    // Create logs by another user (viewer should NOT see these)
    ActivityLog::factory()->count(5)->create(['causer_id' => $otherUser->id]);

    $response = $this->actingAs($viewer)->get('/activity-logs');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ActivityLogs/Index')
            ->has('activityLogs.data', 3) // Viewer should only see their own logs
        );
});

test('index filters by date range correctly', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create());
    $admin->assignRole('Administrator');

    // Create logs with different dates
    ActivityLog::factory()->create(['created_at' => now()->subDays(10)]);
    ActivityLog::factory()->create(['created_at' => now()->subDays(5)]);
    ActivityLog::factory()->create(['created_at' => now()->subDays(2)]);
    ActivityLog::factory()->create(['created_at' => now()]);

    // Filter by start_date
    $response = $this->actingAs($admin)->get('/activity-logs?start_date='.now()->subDays(6)->toDateString());
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('activityLogs.data', 3)
        );

    // Filter by end_date
    $response = $this->actingAs($admin)->get('/activity-logs?end_date='.now()->subDays(3)->toDateString());
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('activityLogs.data', 2)
        );

    // Filter by both start_date and end_date
    $response = $this->actingAs($admin)->get('/activity-logs?start_date='.now()->subDays(7)->toDateString().'&end_date='.now()->subDays(1)->toDateString());
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('activityLogs.data', 2)
        );
});

test('index filters by action type correctly', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create());
    $admin->assignRole('Administrator');

    // Create logs with different action types
    ActivityLog::factory()->created()->count(2)->create();
    ActivityLog::factory()->updated()->count(3)->create();
    ActivityLog::factory()->deleted()->count(1)->create();

    // Filter by 'created' action
    $response = $this->actingAs($admin)->get('/activity-logs?action=created');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('activityLogs.data', 2)
        );

    // Filter by 'updated' action
    $response = $this->actingAs($admin)->get('/activity-logs?action=updated');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('activityLogs.data', 3)
        );

    // Filter by 'deleted' action
    $response = $this->actingAs($admin)->get('/activity-logs?action=deleted');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('activityLogs.data', 1)
        );
});

test('index filters by user_id correctly', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create());
    $admin->assignRole('Administrator');

    $user1 = User::withoutEvents(fn () => User::factory()->create());
    $user2 = User::withoutEvents(fn () => User::factory()->create());

    // Create logs by different users
    ActivityLog::factory()->count(3)->create(['causer_id' => $user1->id]);
    ActivityLog::factory()->count(2)->create(['causer_id' => $user2->id]);

    // Filter by user_id
    $response = $this->actingAs($admin)->get('/activity-logs?user_id='.$user1->id);
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('activityLogs.data', 3)
        );
});

test('index search works across old_values and new_values', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create());
    $admin->assignRole('Administrator');

    // Create logs with searchable content in new_values
    ActivityLog::factory()->create([
        'new_values' => ['name' => 'John Doe', 'email' => 'john@example.com'],
    ]);

    // Create logs with searchable content in old_values
    ActivityLog::factory()->create([
        'old_values' => ['name' => 'Jane Smith'],
        'new_values' => ['name' => 'Jane Updated'],
    ]);

    // Create log that should not match
    ActivityLog::factory()->create([
        'old_values' => ['status' => 'active'],
        'new_values' => ['status' => 'inactive'],
    ]);

    // Search for 'John'
    $response = $this->actingAs($admin)->get('/activity-logs?search=John');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('activityLogs.data', 1)
        );

    // Search for 'Jane'
    $response = $this->actingAs($admin)->get('/activity-logs?search=Jane');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('activityLogs.data', 1)
        );
});
