<?php

use App\Enums\CableType;
use App\Models\Connection;
use App\Models\Device;
use App\Models\Port;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Connection model can be created with valid data', function () {
    $device1 = Device::factory()->create();
    $device2 = Device::factory()->create();

    $sourcePort = Port::factory()->ethernet()->create([
        'device_id' => $device1->id,
        'label' => 'eth0',
    ]);
    $destinationPort = Port::factory()->ethernet()->create([
        'device_id' => $device2->id,
        'label' => 'eth1',
    ]);

    $connection = Connection::create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destinationPort->id,
        'cable_type' => CableType::Cat6,
        'cable_length' => 2.5,
        'cable_color' => 'blue',
        'path_notes' => 'Runs under floor tiles',
    ]);

    expect($connection)->toBeInstanceOf(Connection::class);
    expect($connection->id)->toBeGreaterThan(0);
    expect($connection->cable_type)->toBe(CableType::Cat6);
    expect($connection->cable_length)->toBe('2.50');
    expect($connection->cable_color)->toBe('blue');
    expect($connection->path_notes)->toBe('Runs under floor tiles');
});

test('Connection model has sourcePort relationship', function () {
    $device = Device::factory()->create();
    $sourcePort = Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'eth0',
    ]);
    $destinationPort = Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'eth1',
    ]);

    $connection = Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destinationPort->id,
    ]);

    expect($connection->sourcePort)->toBeInstanceOf(Port::class);
    expect($connection->sourcePort->id)->toBe($sourcePort->id);
});

test('Connection model has destinationPort relationship', function () {
    $device = Device::factory()->create();
    $sourcePort = Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'eth0',
    ]);
    $destinationPort = Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'eth1',
    ]);

    $connection = Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destinationPort->id,
    ]);

    expect($connection->destinationPort)->toBeInstanceOf(Port::class);
    expect($connection->destinationPort->id)->toBe($destinationPort->id);
});

test('Port model has pairedPort relationship', function () {
    $device = Device::factory()->create();

    $frontPort = Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'front-1',
    ]);
    $backPort = Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'back-1',
        'paired_port_id' => $frontPort->id,
    ]);

    // Update front port to point back
    $frontPort->update(['paired_port_id' => $backPort->id]);

    expect($frontPort->pairedPort)->toBeInstanceOf(Port::class);
    expect($frontPort->pairedPort->id)->toBe($backPort->id);
    expect($backPort->pairedPort->id)->toBe($frontPort->id);
});

test('Port model has connection accessor that returns connection regardless of direction', function () {
    $device1 = Device::factory()->create();
    $device2 = Device::factory()->create();

    $port1 = Port::factory()->ethernet()->create([
        'device_id' => $device1->id,
        'label' => 'eth0',
    ]);
    $port2 = Port::factory()->ethernet()->create([
        'device_id' => $device2->id,
        'label' => 'eth1',
    ]);

    $connection = Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
    ]);

    // port1 is source, should find connection
    expect($port1->connection)->toBeInstanceOf(Connection::class);
    expect($port1->connection->id)->toBe($connection->id);

    // port2 is destination, should also find connection
    expect($port2->connection)->toBeInstanceOf(Connection::class);
    expect($port2->connection->id)->toBe($connection->id);
});

test('Connection uses soft deletes', function () {
    $connection = Connection::factory()->create();

    $connectionId = $connection->id;
    $connection->delete();

    // Should not be found with normal query
    expect(Connection::find($connectionId))->toBeNull();

    // Should be found with withTrashed
    $trashedConnection = Connection::withTrashed()->find($connectionId);
    expect($trashedConnection)->toBeInstanceOf(Connection::class);
    expect($trashedConnection->deleted_at)->not->toBeNull();
});

test('Connection cascades delete when source port is deleted', function () {
    $device = Device::factory()->create();
    $sourcePort = Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'source-port',
    ]);
    $destinationPort = Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'destination-port',
    ]);

    $connection = Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destinationPort->id,
    ]);

    $connectionId = $connection->id;

    // Delete the source port
    $sourcePort->delete();

    // Connection should be deleted (cascade)
    expect(Connection::withTrashed()->find($connectionId))->toBeNull();
});
