<?php

use App\Models\ActivityLog;
use App\Models\Connection;
use App\Models\Port;
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

/**
 * Helper to create a connection with proper port setup.
 */
function createTestConnectionWithPorts(): Connection
{
    $sourcePort = Port::factory()->ethernet()->create();
    $destinationPort = Port::factory()->ethernet()->create();

    return Connection::withoutEvents(fn () => Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destinationPort->id,
    ]));
}

test('connection history index page renders with timeline entries', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create());
    $admin->assignRole('Administrator');

    $connection = createTestConnectionWithPorts();

    // Create activity logs with different actions
    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $admin->id,
        'action' => 'created',
        'new_values' => ['cable_color' => 'blue', 'cable_type' => 'cat6'],
        'created_at' => now()->subDays(2),
    ]);

    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $admin->id,
        'action' => 'updated',
        'old_values' => ['cable_color' => 'blue'],
        'new_values' => ['cable_color' => 'green'],
        'created_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($admin)->get('/connections/history');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ConnectionHistory/Index')
            ->has('activityLogs.data', 2)
            ->where('activityLogs.data.0.action', 'updated')
            ->where('activityLogs.data.1.action', 'created')
        );
});

test('connection history displays color-coded action badges for created, updated, and deleted', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create());
    $admin->assignRole('Administrator');

    $connection = createTestConnectionWithPorts();

    // Create logs with all three action types
    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $admin->id,
        'action' => 'created',
        'new_values' => ['cable_color' => 'blue'],
        'created_at' => now()->subDays(3),
    ]);

    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $admin->id,
        'action' => 'updated',
        'old_values' => ['cable_color' => 'blue'],
        'new_values' => ['cable_color' => 'green'],
        'created_at' => now()->subDays(2),
    ]);

    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $admin->id,
        'action' => 'deleted',
        'old_values' => ['cable_color' => 'green'],
        'created_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($admin)->get('/connections/history');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activityLogs.data', 3)
            ->has('availableActions', 4) // created, updated, deleted, restored
            ->where('activityLogs.data.0.action', 'deleted')
            ->where('activityLogs.data.1.action', 'updated')
            ->where('activityLogs.data.2.action', 'created')
        );
});

test('connection history timeline includes old and new values for expandable display', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create());
    $admin->assignRole('Administrator');

    $connection = createTestConnectionWithPorts();

    // Create log with detailed old_values and new_values
    // Note: Using integer for cable_length as JSON conversion may affect float precision
    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $admin->id,
        'action' => 'updated',
        'old_values' => [
            'cable_color' => 'blue',
            'cable_length' => 1,
            'path_notes' => 'Old path',
        ],
        'new_values' => [
            'cable_color' => 'green',
            'cable_length' => 2,
            'path_notes' => 'New path',
        ],
    ]);

    $response = $this->actingAs($admin)->get('/connections/history');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activityLogs.data', 1)
            ->where('activityLogs.data.0.old_values.cable_color', 'blue')
            ->where('activityLogs.data.0.new_values.cable_color', 'green')
            ->where('activityLogs.data.0.old_values.cable_length', 1)
            ->where('activityLogs.data.0.new_values.cable_length', 2)
            ->where('activityLogs.data.0.old_values.path_notes', 'Old path')
            ->where('activityLogs.data.0.new_values.path_notes', 'New path')
        );
});

test('connection history displays relative timestamps with created_at datetime', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create());
    $admin->assignRole('Administrator');

    $connection = createTestConnectionWithPorts();

    $exactTime = now()->subHours(2);

    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $admin->id,
        'action' => 'created',
        'created_at' => $exactTime,
    ]);

    $response = $this->actingAs($admin)->get('/connections/history');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activityLogs.data', 1)
            ->has('activityLogs.data.0.created_at')
        );
});

test('connection timeline API returns user context with name, role, and IP', function () {
    $itManager = User::withoutEvents(fn () => User::factory()->create(['name' => 'Jane Manager']));
    $itManager->assignRole('IT Manager');

    $connection = createTestConnectionWithPorts();

    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $itManager->id,
        'action' => 'updated',
        'ip_address' => '192.168.1.100',
    ]);

    $response = $this->actingAs($itManager)->getJson('/connections/'.$connection->id.'/timeline');

    $response->assertOk()
        ->assertJsonPath('data.0.causer_name', 'Jane Manager')
        ->assertJsonPath('data.0.causer_role', 'IT Manager')
        ->assertJsonPath('data.0.ip_address', '192.168.1.100');
});

test('connection history handles null causer showing System for automated changes', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create());
    $admin->assignRole('Administrator');

    $connection = createTestConnectionWithPorts();

    // Create log with null causer (automated/system change)
    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => null,
        'action' => 'updated',
        'old_values' => ['cable_color' => 'blue'],
        'new_values' => ['cable_color' => 'green'],
    ]);

    $response = $this->actingAs($admin)->get('/connections/history');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activityLogs.data', 1)
            ->where('activityLogs.data.0.causer_name', 'System')
            ->where('activityLogs.data.0.causer_role', null)
        );
});
