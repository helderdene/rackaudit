<?php

use App\Enums\DiscrepancyStatus;
use App\Enums\DiscrepancyType;
use App\Models\Audit;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Discrepancy;
use App\Models\ExpectedConnection;
use App\Models\Finding;
use App\Models\ImplementationFile;
use App\Models\Port;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('discrepancy can be created with required fields', function () {
    $datacenter = Datacenter::factory()->create();
    $sourcePort = Port::factory()->create();
    $destPort = Port::factory()->create();

    $discrepancy = Discrepancy::factory()->create([
        'datacenter_id' => $datacenter->id,
        'source_port_id' => $sourcePort->id,
        'dest_port_id' => $destPort->id,
        'discrepancy_type' => DiscrepancyType::Missing,
        'status' => DiscrepancyStatus::Open,
        'title' => 'Missing Connection',
        'detected_at' => now(),
    ]);

    expect($discrepancy->datacenter_id)->toBe($datacenter->id);
    expect($discrepancy->source_port_id)->toBe($sourcePort->id);
    expect($discrepancy->dest_port_id)->toBe($destPort->id);
    expect($discrepancy->discrepancy_type)->toBe(DiscrepancyType::Missing);
    expect($discrepancy->status)->toBe(DiscrepancyStatus::Open);
    expect($discrepancy->title)->toBe('Missing Connection');
    expect($discrepancy->detected_at)->not->toBeNull();
});

test('discrepancy status transitions work correctly with timestamps', function () {
    $user = User::factory()->create();
    $discrepancy = Discrepancy::factory()->open()->create();

    // Initially open - no acknowledgment or resolution timestamps
    expect($discrepancy->status)->toBe(DiscrepancyStatus::Open);
    expect($discrepancy->acknowledged_at)->toBeNull();
    expect($discrepancy->acknowledged_by)->toBeNull();
    expect($discrepancy->resolved_at)->toBeNull();
    expect($discrepancy->resolved_by)->toBeNull();

    // Transition to acknowledged
    $discrepancy->update([
        'status' => DiscrepancyStatus::Acknowledged,
        'acknowledged_at' => now(),
        'acknowledged_by' => $user->id,
    ]);

    expect($discrepancy->fresh()->status)->toBe(DiscrepancyStatus::Acknowledged);
    expect($discrepancy->fresh()->acknowledged_at)->not->toBeNull();
    expect($discrepancy->fresh()->acknowledged_by)->toBe($user->id);
    expect($discrepancy->fresh()->resolved_at)->toBeNull();

    // Transition to resolved
    $discrepancy->update([
        'status' => DiscrepancyStatus::Resolved,
        'resolved_at' => now(),
        'resolved_by' => $user->id,
    ]);

    expect($discrepancy->fresh()->status)->toBe(DiscrepancyStatus::Resolved);
    expect($discrepancy->fresh()->resolved_at)->not->toBeNull();
    expect($discrepancy->fresh()->resolved_by)->toBe($user->id);
});

test('discrepancy relationships work correctly', function () {
    $datacenter = Datacenter::factory()->create(['name' => 'Primary DC']);
    $room = Room::factory()->create([
        'name' => 'Server Room A',
        'datacenter_id' => $datacenter->id,
    ]);
    $implementationFile = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
    ]);
    $sourcePort = Port::factory()->create();
    $destPort = Port::factory()->create();
    $connection = Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);
    $expectedConnection = ExpectedConnection::factory()->create([
        'implementation_file_id' => $implementationFile->id,
        'source_port_id' => $sourcePort->id,
        'dest_port_id' => $destPort->id,
    ]);

    $discrepancy = Discrepancy::factory()->create([
        'datacenter_id' => $datacenter->id,
        'room_id' => $room->id,
        'implementation_file_id' => $implementationFile->id,
        'source_port_id' => $sourcePort->id,
        'dest_port_id' => $destPort->id,
        'connection_id' => $connection->id,
        'expected_connection_id' => $expectedConnection->id,
    ]);

    expect($discrepancy->datacenter)->toBeInstanceOf(Datacenter::class);
    expect($discrepancy->datacenter->name)->toBe('Primary DC');

    expect($discrepancy->room)->toBeInstanceOf(Room::class);
    expect($discrepancy->room->name)->toBe('Server Room A');

    expect($discrepancy->implementationFile)->toBeInstanceOf(ImplementationFile::class);
    expect($discrepancy->implementationFile->id)->toBe($implementationFile->id);

    expect($discrepancy->sourcePort)->toBeInstanceOf(Port::class);
    expect($discrepancy->sourcePort->id)->toBe($sourcePort->id);

    expect($discrepancy->destPort)->toBeInstanceOf(Port::class);
    expect($discrepancy->destPort->id)->toBe($destPort->id);

    expect($discrepancy->connection)->toBeInstanceOf(Connection::class);
    expect($discrepancy->connection->id)->toBe($connection->id);

    expect($discrepancy->expectedConnection)->toBeInstanceOf(ExpectedConnection::class);
    expect($discrepancy->expectedConnection->id)->toBe($expectedConnection->id);
});

test('discrepancy configuration mismatch JSON storage and retrieval works correctly', function () {
    $expectedConfig = [
        'cable_type' => 'cat6',
        'cable_length' => 5.0,
        'source_port_type' => 'ethernet',
        'dest_port_type' => 'ethernet',
    ];

    $actualConfig = [
        'cable_type' => 'cat5e',
        'cable_length' => 3.5,
        'source_port_type' => 'ethernet',
        'dest_port_type' => 'ethernet',
    ];

    $mismatchDetails = [
        'cable_type' => [
            'expected' => 'cat6',
            'actual' => 'cat5e',
        ],
        'cable_length' => [
            'expected' => 5.0,
            'actual' => 3.5,
        ],
    ];

    $discrepancy = Discrepancy::factory()->configurationMismatch()->create([
        'expected_config' => $expectedConfig,
        'actual_config' => $actualConfig,
        'mismatch_details' => $mismatchDetails,
    ]);

    // Refresh from database to ensure proper serialization/deserialization
    $discrepancy = $discrepancy->fresh();

    expect($discrepancy->expected_config)->toBeArray();
    expect($discrepancy->expected_config['cable_type'])->toBe('cat6');
    // Use toEqual for numeric values to allow type coercion (JSON may store as int)
    expect($discrepancy->expected_config['cable_length'])->toEqual(5.0);

    expect($discrepancy->actual_config)->toBeArray();
    expect($discrepancy->actual_config['cable_type'])->toBe('cat5e');
    expect($discrepancy->actual_config['cable_length'])->toEqual(3.5);

    expect($discrepancy->mismatch_details)->toBeArray();
    expect($discrepancy->mismatch_details['cable_type']['expected'])->toBe('cat6');
    expect($discrepancy->mismatch_details['cable_type']['actual'])->toBe('cat5e');
});

test('discrepancy scope queries filter correctly', function () {
    $datacenter1 = Datacenter::factory()->create();
    $datacenter2 = Datacenter::factory()->create();
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter1->id]);
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter1->id]);

    // Create explicit ports to avoid unique constraint conflicts
    $sourcePort1 = Port::factory()->create();
    $destPort1 = Port::factory()->create();
    $sourcePort2 = Port::factory()->create();
    $destPort2 = Port::factory()->create();
    $sourcePort3 = Port::factory()->create();
    $destPort3 = Port::factory()->create();
    $sourcePort4 = Port::factory()->create();
    $destPort4 = Port::factory()->create();

    // Create discrepancies in different datacenters, rooms, statuses, and types
    Discrepancy::factory()->open()->missing()->create([
        'datacenter_id' => $datacenter1->id,
        'room_id' => $room1->id,
        'source_port_id' => $sourcePort1->id,
        'dest_port_id' => $destPort1->id,
        'detected_at' => now()->subDays(5),
    ]);
    Discrepancy::factory()->acknowledged()->unexpected()->create([
        'datacenter_id' => $datacenter1->id,
        'room_id' => $room2->id,
        'source_port_id' => $sourcePort2->id,
        'dest_port_id' => $destPort2->id,
        'detected_at' => now()->subDays(3),
    ]);
    Discrepancy::factory()->resolved()->mismatched()->create([
        'datacenter_id' => $datacenter2->id,
        'source_port_id' => $sourcePort3->id,
        'dest_port_id' => $destPort3->id,
        'detected_at' => now()->subDays(1),
    ]);
    Discrepancy::factory()->inAudit()->conflicting()->create([
        'datacenter_id' => $datacenter2->id,
        'source_port_id' => $sourcePort4->id,
        'dest_port_id' => $destPort4->id,
        'detected_at' => now(),
    ]);

    // Test status scopes
    expect(Discrepancy::open()->count())->toBe(1);
    expect(Discrepancy::acknowledged()->count())->toBe(1);
    expect(Discrepancy::resolved()->count())->toBe(1);
    expect(Discrepancy::inAudit()->count())->toBe(1);

    // Test datacenter scope
    expect(Discrepancy::forDatacenter($datacenter1->id)->count())->toBe(2);
    expect(Discrepancy::forDatacenter($datacenter2->id)->count())->toBe(2);

    // Test room scope
    expect(Discrepancy::forRoom($room1->id)->count())->toBe(1);
    expect(Discrepancy::forRoom($room2->id)->count())->toBe(1);

    // Test type scope
    expect(Discrepancy::forType(DiscrepancyType::Missing)->count())->toBe(1);
    expect(Discrepancy::forType(DiscrepancyType::Unexpected)->count())->toBe(1);
    expect(Discrepancy::forType(DiscrepancyType::Mismatched)->count())->toBe(1);
    expect(Discrepancy::forType(DiscrepancyType::Conflicting)->count())->toBe(1);

    // Test date range scope
    expect(Discrepancy::detectedBetween(now()->subDays(4), now()->subDays(2))->count())->toBe(1);
    expect(Discrepancy::detectedBetween(now()->subDays(6), now())->count())->toBe(4);
});

test('discrepancy links to audit and finding correctly', function () {
    $audit = Audit::factory()->create();
    $finding = Finding::factory()->create(['audit_id' => $audit->id]);
    $acknowledger = User::factory()->create();
    $resolver = User::factory()->create();

    $discrepancy = Discrepancy::factory()->create([
        'audit_id' => $audit->id,
        'finding_id' => $finding->id,
        'acknowledged_by' => $acknowledger->id,
        'resolved_by' => $resolver->id,
        'status' => DiscrepancyStatus::Resolved,
        'acknowledged_at' => now()->subDay(),
        'resolved_at' => now(),
    ]);

    expect($discrepancy->audit)->toBeInstanceOf(Audit::class);
    expect($discrepancy->audit->id)->toBe($audit->id);

    expect($discrepancy->finding)->toBeInstanceOf(Finding::class);
    expect($discrepancy->finding->id)->toBe($finding->id);

    expect($discrepancy->acknowledgedBy)->toBeInstanceOf(User::class);
    expect($discrepancy->acknowledgedBy->id)->toBe($acknowledger->id);

    expect($discrepancy->resolvedBy)->toBeInstanceOf(User::class);
    expect($discrepancy->resolvedBy->id)->toBe($resolver->id);
});
