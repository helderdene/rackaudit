<?php

use App\Enums\CableType;
use App\Enums\ExpectedConnectionStatus;
use App\Models\Device;
use App\Models\ExpectedConnection;
use App\Models\ImplementationFile;
use App\Models\Port;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('ExpectedConnection model can be created with required fields', function () {
    $implementationFile = ImplementationFile::factory()->xlsx()->approved()->create();
    $sourceDevice = Device::factory()->create();
    $destDevice = Device::factory()->create();
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    $expectedConnection = ExpectedConnection::create([
        'implementation_file_id' => $implementationFile->id,
        'source_device_id' => $sourceDevice->id,
        'source_port_id' => $sourcePort->id,
        'dest_device_id' => $destDevice->id,
        'dest_port_id' => $destPort->id,
        'cable_type' => CableType::Cat6,
        'cable_length' => 3.5,
        'row_number' => 1,
        'status' => ExpectedConnectionStatus::PendingReview,
    ]);

    expect($expectedConnection)->toBeInstanceOf(ExpectedConnection::class);
    expect($expectedConnection->id)->toBeGreaterThan(0);
    expect($expectedConnection->implementation_file_id)->toBe($implementationFile->id);
    expect($expectedConnection->source_device_id)->toBe($sourceDevice->id);
    expect($expectedConnection->source_port_id)->toBe($sourcePort->id);
    expect($expectedConnection->dest_device_id)->toBe($destDevice->id);
    expect($expectedConnection->dest_port_id)->toBe($destPort->id);
    expect($expectedConnection->cable_type)->toBe(CableType::Cat6);
    expect($expectedConnection->cable_length)->toBe('3.50');
    expect($expectedConnection->row_number)->toBe(1);
});

test('ExpectedConnection status field uses enum values correctly', function () {
    // Test all status enum values
    $statuses = [
        ExpectedConnectionStatus::PendingReview,
        ExpectedConnectionStatus::Confirmed,
        ExpectedConnectionStatus::Skipped,
    ];

    foreach ($statuses as $status) {
        $expectedConnection = ExpectedConnection::factory()->create([
            'status' => $status,
        ]);

        expect($expectedConnection->status)->toBe($status);
        expect($expectedConnection->status->value)->toBeString();
        expect($expectedConnection->status->label())->toBeString();
    }

    // Verify specific status values and labels
    expect(ExpectedConnectionStatus::PendingReview->value)->toBe('pending_review');
    expect(ExpectedConnectionStatus::PendingReview->label())->toBe('Pending Review');
    expect(ExpectedConnectionStatus::Confirmed->value)->toBe('confirmed');
    expect(ExpectedConnectionStatus::Confirmed->label())->toBe('Confirmed');
    expect(ExpectedConnectionStatus::Skipped->value)->toBe('skipped');
    expect(ExpectedConnectionStatus::Skipped->label())->toBe('Skipped');
});

test('ExpectedConnection has belongsTo relationship to ImplementationFile', function () {
    $implementationFile = ImplementationFile::factory()->xlsx()->approved()->create();
    $expectedConnection = ExpectedConnection::factory()->create([
        'implementation_file_id' => $implementationFile->id,
    ]);

    expect($expectedConnection->implementationFile)->toBeInstanceOf(ImplementationFile::class);
    expect($expectedConnection->implementationFile->id)->toBe($implementationFile->id);
});

test('ExpectedConnection has belongsTo relationships to Device and Port models', function () {
    $sourceDevice = Device::factory()->create(['name' => 'Source Server']);
    $destDevice = Device::factory()->create(['name' => 'Dest Switch']);
    $sourcePort = Port::factory()->ethernet()->create([
        'device_id' => $sourceDevice->id,
        'label' => 'eth0',
    ]);
    $destPort = Port::factory()->ethernet()->create([
        'device_id' => $destDevice->id,
        'label' => 'port-1',
    ]);

    $expectedConnection = ExpectedConnection::factory()->create([
        'source_device_id' => $sourceDevice->id,
        'source_port_id' => $sourcePort->id,
        'dest_device_id' => $destDevice->id,
        'dest_port_id' => $destPort->id,
    ]);

    // Test device relationships
    expect($expectedConnection->sourceDevice)->toBeInstanceOf(Device::class);
    expect($expectedConnection->sourceDevice->id)->toBe($sourceDevice->id);
    expect($expectedConnection->sourceDevice->name)->toBe('Source Server');

    expect($expectedConnection->destDevice)->toBeInstanceOf(Device::class);
    expect($expectedConnection->destDevice->id)->toBe($destDevice->id);
    expect($expectedConnection->destDevice->name)->toBe('Dest Switch');

    // Test port relationships
    expect($expectedConnection->sourcePort)->toBeInstanceOf(Port::class);
    expect($expectedConnection->sourcePort->id)->toBe($sourcePort->id);
    expect($expectedConnection->sourcePort->label)->toBe('eth0');

    expect($expectedConnection->destPort)->toBeInstanceOf(Port::class);
    expect($expectedConnection->destPort->id)->toBe($destPort->id);
    expect($expectedConnection->destPort->label)->toBe('port-1');
});

test('ExpectedConnection has scope for filtering by status (confirmed only for comparison view)', function () {
    $implementationFile = ImplementationFile::factory()->xlsx()->approved()->create();

    // Create expected connections with different statuses
    ExpectedConnection::factory()->count(3)->create([
        'implementation_file_id' => $implementationFile->id,
        'status' => ExpectedConnectionStatus::Confirmed,
    ]);
    ExpectedConnection::factory()->count(2)->create([
        'implementation_file_id' => $implementationFile->id,
        'status' => ExpectedConnectionStatus::PendingReview,
    ]);
    ExpectedConnection::factory()->create([
        'implementation_file_id' => $implementationFile->id,
        'status' => ExpectedConnectionStatus::Skipped,
    ]);

    // Test confirmed scope returns only confirmed connections
    $confirmedConnections = ExpectedConnection::confirmed()->get();
    expect($confirmedConnections)->toHaveCount(3);
    foreach ($confirmedConnections as $connection) {
        expect($connection->status)->toBe(ExpectedConnectionStatus::Confirmed);
    }

    // Test pendingReview scope
    $pendingConnections = ExpectedConnection::pendingReview()->get();
    expect($pendingConnections)->toHaveCount(2);
    foreach ($pendingConnections as $connection) {
        expect($connection->status)->toBe(ExpectedConnectionStatus::PendingReview);
    }
});

test('ExpectedConnection supports soft deletes for archiving old versions', function () {
    $implementationFile = ImplementationFile::factory()->xlsx()->approved()->create();
    $expectedConnection = ExpectedConnection::factory()->create([
        'implementation_file_id' => $implementationFile->id,
    ]);

    $connectionId = $expectedConnection->id;

    // Soft delete the connection (archiving)
    $expectedConnection->delete();

    // Should not be found with normal query
    expect(ExpectedConnection::find($connectionId))->toBeNull();

    // Should be found with withTrashed
    $trashedConnection = ExpectedConnection::withTrashed()->find($connectionId);
    expect($trashedConnection)->toBeInstanceOf(ExpectedConnection::class);
    expect($trashedConnection->deleted_at)->not->toBeNull();

    // Can be restored if needed
    $trashedConnection->restore();
    expect(ExpectedConnection::find($connectionId))->not->toBeNull();
    expect(ExpectedConnection::find($connectionId)->deleted_at)->toBeNull();
});
