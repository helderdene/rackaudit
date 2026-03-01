<?php

use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Test 1: Activity feed renders activity list in dashboard response
 * Verifies that the recentActivity prop contains activity log entries with expected structure
 */
test('activity feed renders activity list with correct data structure', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Clear logs created by user factory
    ActivityLog::query()->delete();

    // Create test activity logs
    ActivityLog::factory()->count(5)->create([
        'causer_id' => $admin->id,
        'subject_type' => 'App\\Models\\Datacenter',
        'subject_id' => 1,
        'action' => 'created',
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('recentActivity', 5)
            ->has('recentActivity.0', fn ($activity) => $activity
                ->has('id')
                ->has('timestamp')
                ->has('timestamp_relative')
                ->has('user_name')
                ->has('action')
                ->has('entity_type')
                ->has('summary')
                ->has('old_values')
                ->has('new_values')
            )
        );
});

/**
 * Test 2: Activity feed displays timestamps in relative format
 * Verifies that relative timestamps are correctly formatted for different time ranges
 */
test('activity feed displays timestamps in relative format', function () {
    $admin = User::factory()->create(['name' => 'Test Admin']);
    $admin->assignRole('Administrator');

    // Clear logs created by user factory
    ActivityLog::query()->delete();

    // Create activity log just now (within 1 minute)
    ActivityLog::factory()->create([
        'causer_id' => $admin->id,
        'subject_type' => 'App\\Models\\Datacenter',
        'subject_id' => 1,
        'action' => 'created',
        'created_at' => now(),
    ]);

    // Create activity log 30 minutes ago
    ActivityLog::factory()->create([
        'causer_id' => $admin->id,
        'subject_type' => 'App\\Models\\Device',
        'subject_id' => 2,
        'action' => 'updated',
        'created_at' => now()->subMinutes(30),
    ]);

    // Create activity log 5 hours ago
    ActivityLog::factory()->create([
        'causer_id' => $admin->id,
        'subject_type' => 'App\\Models\\Rack',
        'subject_id' => 3,
        'action' => 'deleted',
        'created_at' => now()->subHours(5),
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('recentActivity', 3)
            // Check that first activity (most recent) has "Just now"
            ->where('recentActivity.0.timestamp_relative', 'Just now')
            // Check that second activity shows minutes ago
            ->where('recentActivity.1.timestamp_relative', '30 minutes ago')
            // Check that third activity shows hours ago
            ->where('recentActivity.2.timestamp_relative', '5 hours ago')
        );
});

/**
 * Test 3: Activity feed is limited to 15 most recent entries
 * Verifies that the feed caps at 15 entries ordered by most recent first
 */
test('activity feed is limited to 15 most recent entries', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Clear logs created by user factory
    ActivityLog::query()->delete();

    // Create 20 activity logs
    ActivityLog::factory()->count(20)->create([
        'causer_id' => $admin->id,
        'subject_type' => 'App\\Models\\Datacenter',
        'subject_id' => 1,
        'action' => 'created',
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            // Should only have 15 entries, not 20
            ->has('recentActivity', 15)
        );
});
