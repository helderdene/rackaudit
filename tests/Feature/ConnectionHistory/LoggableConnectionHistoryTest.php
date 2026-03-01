<?php

use App\Enums\CableType;
use App\Models\ActivityLog;
use App\Models\Connection;
use App\Models\Device;
use App\Models\Port;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

test('connection update logs full state snapshot with all attributes', function () {
    $user = User::factory()->create();
    Auth::login($user);

    // Create source and destination devices with ports
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

    // Create connection without triggering events for initial setup
    $connection = Connection::withoutEvents(function () use ($sourcePort, $destinationPort) {
        return Connection::create([
            'source_port_id' => $sourcePort->id,
            'destination_port_id' => $destinationPort->id,
            'cable_type' => CableType::Cat6,
            'cable_length' => 2.5,
            'cable_color' => 'blue',
            'path_notes' => 'Original notes',
        ]);
    });

    // Update the connection - this triggers the updated event
    $connection->update([
        'cable_color' => 'yellow',
    ]);

    // Find the update activity log
    $activityLog = ActivityLog::where('subject_type', Connection::class)
        ->where('subject_id', $connection->id)
        ->where('action', 'updated')
        ->first();

    expect($activityLog)->not->toBeNull();

    // Should have full state in old_values (all attributes, not just changed)
    expect($activityLog->old_values)->toHaveKey('source_port_id');
    expect($activityLog->old_values)->toHaveKey('destination_port_id');
    expect($activityLog->old_values)->toHaveKey('cable_type');
    expect($activityLog->old_values)->toHaveKey('cable_length');
    expect($activityLog->old_values)->toHaveKey('cable_color');
    expect($activityLog->old_values['cable_color'])->toBe('blue');

    // Should have full state in new_values (all attributes, not just changed)
    expect($activityLog->new_values)->toHaveKey('source_port_id');
    expect($activityLog->new_values)->toHaveKey('destination_port_id');
    expect($activityLog->new_values)->toHaveKey('cable_type');
    expect($activityLog->new_values)->toHaveKey('cable_length');
    expect($activityLog->new_values)->toHaveKey('cable_color');
    expect($activityLog->new_values['cable_color'])->toBe('yellow');
});

test('restored event is logged when soft-deleted connection is recovered', function () {
    $user = User::factory()->create();
    Auth::login($user);

    $sourcePort = Port::factory()->ethernet()->create();
    $destinationPort = Port::factory()->ethernet()->create();

    // Create connection without triggering events
    $connection = Connection::withoutEvents(function () use ($sourcePort, $destinationPort) {
        return Connection::create([
            'source_port_id' => $sourcePort->id,
            'destination_port_id' => $destinationPort->id,
            'cable_type' => CableType::Cat6,
            'cable_length' => 3.0,
            'cable_color' => 'green',
        ]);
    });

    // Soft delete without triggering events
    Connection::withoutEvents(function () use ($connection) {
        $connection->delete();
    });

    // Restore the connection - this should trigger the restored event
    $connection->restore();

    // Find the restore activity log
    $activityLog = ActivityLog::where('subject_type', Connection::class)
        ->where('subject_id', $connection->id)
        ->where('action', 'restored')
        ->first();

    expect($activityLog)->not->toBeNull();
    expect($activityLog->action)->toBe('restored');
    expect($activityLog->causer_id)->toBe($user->id);

    // new_values should contain the full restored connection state
    expect($activityLog->new_values)->toHaveKey('source_port_id');
    expect($activityLog->new_values)->toHaveKey('destination_port_id');
    expect($activityLog->new_values)->toHaveKey('cable_type');
    expect($activityLog->new_values)->toHaveKey('cable_length');
    expect($activityLog->new_values)->toHaveKey('cable_color');
});

test('connection snapshots include resolved port labels and device names', function () {
    $user = User::factory()->create();
    Auth::login($user);

    // Create source and destination devices with ports
    $sourceDevice = Device::factory()->create(['name' => 'Web Server 01']);
    $destinationDevice = Device::factory()->create(['name' => 'Core Switch 01']);

    $sourcePort = Port::factory()->ethernet()->create([
        'device_id' => $sourceDevice->id,
        'label' => 'eth0',
    ]);
    $destinationPort = Port::factory()->ethernet()->create([
        'device_id' => $destinationDevice->id,
        'label' => 'ge-0/0/24',
    ]);

    // Create connection - triggers created event
    $connection = Connection::create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destinationPort->id,
        'cable_type' => CableType::Cat6a,
        'cable_length' => 5.0,
        'cable_color' => 'blue',
    ]);

    // Find the created activity log
    $activityLog = ActivityLog::where('subject_type', Connection::class)
        ->where('subject_id', $connection->id)
        ->where('action', 'created')
        ->first();

    expect($activityLog)->not->toBeNull();

    // Should have enriched port and device information
    expect($activityLog->new_values)->toHaveKey('source_port_label');
    expect($activityLog->new_values['source_port_label'])->toBe('eth0');
    expect($activityLog->new_values)->toHaveKey('source_device_name');
    expect($activityLog->new_values['source_device_name'])->toBe('Web Server 01');

    expect($activityLog->new_values)->toHaveKey('destination_port_label');
    expect($activityLog->new_values['destination_port_label'])->toBe('ge-0/0/24');
    expect($activityLog->new_values)->toHaveKey('destination_device_name');
    expect($activityLog->new_values['destination_device_name'])->toBe('Core Switch 01');
});

test('cable type enum is stored with human-readable label', function () {
    $user = User::factory()->create();
    Auth::login($user);

    $sourcePort = Port::factory()->ethernet()->create();
    $destinationPort = Port::factory()->ethernet()->create();

    // Create connection with Cat6a cable type
    $connection = Connection::create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destinationPort->id,
        'cable_type' => CableType::Cat6a,
        'cable_length' => 2.0,
        'cable_color' => 'yellow',
    ]);

    // Find the created activity log
    $activityLog = ActivityLog::where('subject_type', Connection::class)
        ->where('subject_id', $connection->id)
        ->where('action', 'created')
        ->first();

    expect($activityLog)->not->toBeNull();

    // Should have cable_type value and human-readable label
    expect($activityLog->new_values)->toHaveKey('cable_type');
    expect($activityLog->new_values)->toHaveKey('cable_type_label');
    expect($activityLog->new_values['cable_type_label'])->toBe('Cat6a');
});

test('other models using Loggable trait maintain original behavior', function () {
    $user = User::factory()->create();
    Auth::login($user);

    // Use Device model which also uses Loggable trait but should NOT have full state logging
    // Use factory to create device without events (avoids asset_tag requirement during test)
    $device = Device::factory()->create([
        'name' => 'Test Server',
        'manufacturer' => 'Dell',
        'model' => 'PowerEdge R740',
    ]);

    // Clear activity logs from device creation
    ActivityLog::where('subject_type', Device::class)->delete();

    // Update the device
    $device->update([
        'name' => 'Updated Server',
    ]);

    // Find the update activity log
    $activityLog = ActivityLog::where('subject_type', Device::class)
        ->where('subject_id', $device->id)
        ->where('action', 'updated')
        ->first();

    expect($activityLog)->not->toBeNull();

    // For non-Connection models, old_values should only have changed fields
    expect($activityLog->old_values)->toHaveKey('name');
    expect($activityLog->new_values)->toHaveKey('name');

    // Should NOT have all fillable attributes - only the changed one
    expect($activityLog->old_values)->not->toHaveKey('manufacturer');
    expect($activityLog->new_values)->not->toHaveKey('manufacturer');
});
