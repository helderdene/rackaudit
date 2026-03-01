<?php

use App\Enums\CableType;
use App\Models\ActivityLog;
use App\Models\Connection;
use App\Models\Device;
use App\Models\Port;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
});

test('full workflow: create connection, update it, and view history timeline', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create(['name' => 'Admin User']));
    $admin->assignRole('Administrator');
    Auth::login($admin);

    // Create devices and ports normally (they need the creating event for asset_tag)
    $sourceDevice = Device::factory()->create(['name' => 'Source Server']);
    $destinationDevice = Device::factory()->create(['name' => 'Destination Switch']);

    $sourcePort = Port::factory()->ethernet()->create([
        'device_id' => $sourceDevice->id,
        'label' => 'eth0',
    ]);
    $destinationPort = Port::factory()->ethernet()->create([
        'device_id' => $destinationDevice->id,
        'label' => 'ge-0/0/1',
    ]);

    // Clear any logs from setup (we only care about connection logs)
    ActivityLog::query()->delete();

    // Step 1: Create a connection
    $connection = Connection::create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destinationPort->id,
        'cable_type' => CableType::Cat6,
        'cable_length' => 2.5,
        'cable_color' => 'blue',
        'path_notes' => 'Initial connection',
    ]);

    // Verify created event was logged
    $createdLog = ActivityLog::where('subject_type', Connection::class)
        ->where('subject_id', $connection->id)
        ->where('action', 'created')
        ->first();

    expect($createdLog)->not->toBeNull()
        ->and($createdLog->new_values['cable_color'])->toBe('blue');

    // Step 2: Update the connection
    $connection->update([
        'cable_color' => 'yellow',
        'path_notes' => 'Updated path notes',
    ]);

    // Verify updated event was logged with full state
    $updatedLog = ActivityLog::where('subject_type', Connection::class)
        ->where('subject_id', $connection->id)
        ->where('action', 'updated')
        ->first();

    expect($updatedLog)->not->toBeNull()
        ->and($updatedLog->old_values['cable_color'])->toBe('blue')
        ->and($updatedLog->new_values['cable_color'])->toBe('yellow')
        // Verify full state is captured (not just changed fields)
        ->and($updatedLog->new_values)->toHaveKey('source_port_id')
        ->and($updatedLog->new_values)->toHaveKey('destination_port_id');

    // Step 3: View the timeline API
    $response = $this->actingAs($admin)->getJson('/connections/' . $connection->id . '/timeline');

    $response->assertOk();

    $data = $response->json('data');
    $actions = array_column($data, 'action');

    // Should contain both created and updated actions
    expect($actions)->toContain('created')
        ->and($actions)->toContain('updated');

    // Find the updated log in response and verify values
    $updatedEntry = collect($data)->firstWhere('action', 'updated');
    expect($updatedEntry['causer_name'])->toBe('Admin User')
        ->and($updatedEntry['old_values']['cable_color'])->toBe('blue')
        ->and($updatedEntry['new_values']['cable_color'])->toBe('yellow');
});

test('restored action appears correctly in timeline after soft-delete recovery', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create(['name' => 'Admin User']));
    $admin->assignRole('Administrator');
    Auth::login($admin);

    $sourcePort = Port::factory()->ethernet()->create();
    $destinationPort = Port::factory()->ethernet()->create();

    // Create connection without events for initial setup
    $connection = Connection::withoutEvents(function () use ($sourcePort, $destinationPort) {
        return Connection::create([
            'source_port_id' => $sourcePort->id,
            'destination_port_id' => $destinationPort->id,
            'cable_type' => CableType::Cat6a,
            'cable_length' => 3.0,
            'cable_color' => 'green',
            'path_notes' => 'Test connection',
        ]);
    });

    // Clear any logs from setup
    ActivityLog::query()->delete();

    // Soft delete the connection
    $connection->delete();

    // Verify deleted event was logged
    $deletedLog = ActivityLog::where('subject_type', Connection::class)
        ->where('subject_id', $connection->id)
        ->where('action', 'deleted')
        ->first();

    expect($deletedLog)->not->toBeNull();

    // Restore the connection
    $connection->restore();

    // Verify restored event was logged
    $restoredLog = ActivityLog::where('subject_type', Connection::class)
        ->where('subject_id', $connection->id)
        ->where('action', 'restored')
        ->first();

    expect($restoredLog)->not->toBeNull()
        ->and($restoredLog->new_values)->toHaveKey('cable_color')
        ->and($restoredLog->new_values['cable_color'])->toBe('green');

    // View the timeline API - should show both deleted and restored actions
    $response = $this->actingAs($admin)->getJson('/connections/' . $connection->id . '/timeline');

    $response->assertOk();

    // Check that both actions are present (most recent first)
    $data = $response->json('data');
    $actions = array_column($data, 'action');

    expect($actions)->toContain('restored')
        ->and($actions)->toContain('deleted');
});

test('connection history filters work with multiple criteria combined', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create(['name' => 'Admin User']));
    $admin->assignRole('Administrator');

    $operator = User::withoutEvents(fn () => User::factory()->create(['name' => 'Operator User']));
    $operator->assignRole('Operator');

    $sourcePort = Port::factory()->ethernet()->create();
    $destinationPort = Port::factory()->ethernet()->create();

    $connection = Connection::withoutEvents(fn () => Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destinationPort->id,
    ]));

    // Clear any logs from setup
    ActivityLog::query()->delete();

    // Create activity logs with varying attributes
    // Log 1: Admin created 10 days ago
    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $admin->id,
        'action' => 'created',
        'created_at' => now()->subDays(10),
    ]);

    // Log 2: Operator updated 3 days ago (matches all criteria)
    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $operator->id,
        'action' => 'updated',
        'created_at' => now()->subDays(3),
    ]);

    // Log 3: Admin updated 2 days ago (matches date and action, not user)
    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $admin->id,
        'action' => 'updated',
        'created_at' => now()->subDays(2),
    ]);

    // Log 4: Operator deleted 1 day ago (matches date and user, not action)
    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $operator->id,
        'action' => 'deleted',
        'created_at' => now()->subDay(),
    ]);

    // Filter by: last 5 days + operator user + updated action
    $response = $this->actingAs($admin)->get(
        '/connections/history?start_date=' . now()->subDays(5)->toDateString() .
        '&user_id=' . $operator->id .
        '&action=updated'
    );

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activityLogs.data', 1) // Only Log 2 matches all criteria
            ->where('activityLogs.data.0.action', 'updated')
            ->where('activityLogs.data.0.causer_name', 'Operator User')
        );
});

test('enriched attributes are present in timeline for historical context', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create(['name' => 'Admin User']));
    $admin->assignRole('Administrator');
    Auth::login($admin);

    // Create devices with specific names for verification
    $sourceDevice = Device::factory()->create(['name' => 'Web Server Alpha']);
    $destinationDevice = Device::factory()->create(['name' => 'Core Switch Beta']);

    $sourcePort = Port::factory()->ethernet()->create([
        'device_id' => $sourceDevice->id,
        'label' => 'eth1',
    ]);
    $destinationPort = Port::factory()->ethernet()->create([
        'device_id' => $destinationDevice->id,
        'label' => 'port24',
    ]);

    // Clear any logs from setup
    ActivityLog::query()->delete();

    // Create connection - should capture enriched attributes
    $connection = Connection::create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destinationPort->id,
        'cable_type' => CableType::FiberSm,
        'cable_length' => 10.0,
        'cable_color' => 'orange',
    ]);

    // View timeline and verify enriched attributes are present
    $response = $this->actingAs($admin)->getJson('/connections/' . $connection->id . '/timeline');

    $response->assertOk();

    $data = $response->json('data.0');

    // Verify enriched port labels and device names
    expect($data['new_values'])->toHaveKey('source_port_label')
        ->and($data['new_values']['source_port_label'])->toBe('eth1')
        ->and($data['new_values'])->toHaveKey('source_device_name')
        ->and($data['new_values']['source_device_name'])->toBe('Web Server Alpha')
        ->and($data['new_values'])->toHaveKey('destination_port_label')
        ->and($data['new_values']['destination_port_label'])->toBe('port24')
        ->and($data['new_values'])->toHaveKey('destination_device_name')
        ->and($data['new_values']['destination_device_name'])->toBe('Core Switch Beta')
        // Verify cable type label
        ->and($data['new_values'])->toHaveKey('cable_type_label')
        ->and($data['new_values']['cable_type_label'])->toBe('Fiber SM');
});

test('export with search filter produces correct results', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create());
    $admin->assignRole('Administrator');

    $sourcePort = Port::factory()->ethernet()->create();
    $destinationPort = Port::factory()->ethernet()->create();

    $connection = Connection::withoutEvents(fn () => Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destinationPort->id,
    ]));

    // Clear any logs from setup
    ActivityLog::query()->delete();

    // Create logs with different searchable content
    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $admin->id,
        'action' => 'created',
        'new_values' => ['cable_color' => 'orange', 'path_notes' => 'Floor 1 rack A'],
    ]);

    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $admin->id,
        'action' => 'updated',
        'old_values' => ['path_notes' => 'Floor 1 rack A'],
        'new_values' => ['path_notes' => 'Floor 2 rack B'],
    ]);

    ActivityLog::factory()->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $admin->id,
        'action' => 'updated',
        'old_values' => ['cable_color' => 'orange'],
        'new_values' => ['cable_color' => 'blue'],
    ]);

    // Export with search filter for 'rack B'
    $response = $this->actingAs($admin)->postJson('/connections/history/export', [
        'format' => 'csv',
        'search' => 'rack B',
    ]);

    $response->assertCreated();

    // Verify the search filter was stored in the export record
    $export = \App\Models\BulkExport::first();
    expect($export->filters)->toBeArray()
        ->and($export->filters['search'])->toBe('rack B');
});

test('viewer role sees only their own connection history logs', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create(['name' => 'Admin User']));
    $admin->assignRole('Administrator');

    $viewer = User::withoutEvents(fn () => User::factory()->create(['name' => 'Viewer User']));
    $viewer->assignRole('Viewer');

    $sourcePort = Port::factory()->ethernet()->create();
    $destinationPort = Port::factory()->ethernet()->create();

    $connection = Connection::withoutEvents(fn () => Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destinationPort->id,
    ]));

    // Clear any logs from setup
    ActivityLog::query()->delete();

    // Create logs by admin
    ActivityLog::factory()->count(3)->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $admin->id,
    ]);

    // Create logs by viewer
    ActivityLog::factory()->count(2)->create([
        'subject_type' => Connection::class,
        'subject_id' => $connection->id,
        'causer_id' => $viewer->id,
    ]);

    // Viewer should only see their own logs
    $response = $this->actingAs($viewer)->get('/connections/history');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ConnectionHistory/Index')
            ->has('activityLogs.data', 2) // Only viewer's logs
        );
});
