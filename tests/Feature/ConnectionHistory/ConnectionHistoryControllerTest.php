<?php

use App\Models\ActivityLog;
use App\Models\Connection;
use App\Models\Port;
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

/**
 * Helper to create a connection with proper port setup.
 */
function createTestConnection(): Connection
{
    $sourcePort = Port::factory()->ethernet()->create();
    $destinationPort = Port::factory()->ethernet()->create();

    return Connection::withoutEvents(fn () => Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destinationPort->id,
    ]));
}

test('index returns paginated connection activity logs', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create());
    $admin->assignRole('Administrator');

    // Create a connection for the activity logs
    $connection = createTestConnection();

    // Create connection-specific activity logs
    ActivityLog::factory()->count(30)->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
    ]);

    // Create non-connection activity logs (should be excluded)
    ActivityLog::factory()->count(5)->create([
        'subject_type' => User::class,
    ]);

    $response = $this->actingAs($admin)->get('/connections/history');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ConnectionHistory/Index')
            ->has('activityLogs.data', 25) // 25 per page, only connection logs
            ->has('activityLogs.links')
        );
});

test('index filters by date range, user, and action type', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create());
    $admin->assignRole('Administrator');

    $user1 = User::withoutEvents(fn () => User::factory()->create());
    $connection = createTestConnection();

    // Create logs with different dates, users, and actions
    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $user1->id,
        'action' => 'created',
        'created_at' => now()->subDays(10),
    ]);
    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $user1->id,
        'action' => 'updated',
        'created_at' => now()->subDays(5),
    ]);
    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $admin->id,
        'action' => 'deleted',
        'created_at' => now(),
    ]);

    // Filter by date range
    $response = $this->actingAs($admin)->get('/connections/history?start_date=' . now()->subDays(6)->toDateString());
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('activityLogs.data', 2)
        );

    // Filter by user_id
    $response = $this->actingAs($admin)->get('/connections/history?user_id=' . $user1->id);
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('activityLogs.data', 2)
        );

    // Filter by action type
    $response = $this->actingAs($admin)->get('/connections/history?action=updated');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('activityLogs.data', 1)
        );
});

test('index search works across old_values and new_values JSON columns', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create());
    $admin->assignRole('Administrator');

    $connection = createTestConnection();

    // Create logs with searchable content
    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'new_values' => ['cable_color' => 'blue', 'cable_type' => 'Cat6'],
    ]);

    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'old_values' => ['cable_color' => 'yellow'],
        'new_values' => ['cable_color' => 'green'],
    ]);

    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'old_values' => ['path_notes' => 'Floor 1'],
        'new_values' => ['path_notes' => 'Floor 2'],
    ]);

    // Search for 'blue' - should find the first log
    $response = $this->actingAs($admin)->get('/connections/history?search=blue');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('activityLogs.data', 1)
        );

    // Search for 'yellow' in old_values
    $response = $this->actingAs($admin)->get('/connections/history?search=yellow');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('activityLogs.data', 1)
        );

    // Search for 'Floor' - should find the third log (matches both old and new values)
    $response = $this->actingAs($admin)->get('/connections/history?search=Floor');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('activityLogs.data', 1)
        );
});

test('auditor role has full read access to connection history', function () {
    $auditor = User::withoutEvents(fn () => User::factory()->create());
    $auditor->assignRole('Auditor');

    $otherUser = User::withoutEvents(fn () => User::factory()->create());
    $connection = createTestConnection();

    // Create logs by different users
    ActivityLog::factory()->count(3)->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $otherUser->id,
    ]);

    ActivityLog::factory()->count(2)->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $auditor->id,
    ]);

    // Auditor should see all connection history logs (full read access)
    $response = $this->actingAs($auditor)->get('/connections/history');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ConnectionHistory/Index')
            ->has('activityLogs.data', 5) // All 5 logs, not just their own
        );
});

test('timeline endpoint returns chronological entries for specific connection', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create());
    $admin->assignRole('Administrator');

    $connection = createTestConnection();
    $otherConnection = createTestConnection();

    // Create logs for the target connection at different times
    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'action' => 'created',
        'created_at' => now()->subDays(3),
    ]);
    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'action' => 'updated',
        'created_at' => now()->subDays(2),
    ]);
    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'action' => 'updated',
        'created_at' => now()->subDay(),
    ]);

    // Create logs for other connection (should NOT be included)
    ActivityLog::factory()->count(5)->create([
        'subject_type' => Connection::class,
        'subject_id' => $otherConnection->id,
    ]);

    $response = $this->actingAs($admin)->getJson('/connections/' . $connection->id . '/timeline');

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.action', 'updated') // Most recent first (chronological desc)
        ->assertJsonPath('data.2.action', 'created'); // Oldest last
});

test('timeline endpoint supports load more pagination', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create());
    $admin->assignRole('Administrator');

    $connection = createTestConnection();

    // Create 15 activity logs for the connection
    for ($i = 0; $i < 15; $i++) {
        ActivityLog::factory()->create([
            'subject_type' => Connection::class,
            'subject_id' => $connection->id,
            'created_at' => now()->subMinutes(15 - $i),
        ]);
    }

    // First request should return initial 10 entries
    $response = $this->actingAs($admin)->getJson('/connections/' . $connection->id . '/timeline');

    $response->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.has_more', true);

    // Request with page 2 should return remaining 5 entries
    $response = $this->actingAs($admin)->getJson('/connections/' . $connection->id . '/timeline?page=2');

    $response->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.has_more', false);
});

test('connection history includes causer with role information', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create(['name' => 'John Admin']));
    $admin->assignRole('Administrator');

    $connection = createTestConnection();

    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->get('/connections/history');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activityLogs.data.0.causer_name', 'John Admin')
            ->where('activityLogs.data.0.causer_role', 'Administrator')
        );
});
