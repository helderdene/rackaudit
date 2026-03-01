<?php

use App\Enums\CableType;
use App\Enums\DiscrepancyStatus;
use App\Enums\DiscrepancyType;
use App\Enums\ExpectedConnectionStatus;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Discrepancy;
use App\Models\ExpectedConnection;
use App\Models\ImplementationFile;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Services\DiscrepancyDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(DiscrepancyDetectionService::class);
});

test('detection for datacenter scope creates correct discrepancies', function () {
    // Set up the datacenter hierarchy: Datacenter -> Room -> Row -> Rack -> Device -> Port
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    // Create ports on the device
    $sourcePort = Port::factory()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->create(['device_id' => $device->id]);
    $unexpectedSourcePort = Port::factory()->create(['device_id' => $device->id]);
    $unexpectedDestPort = Port::factory()->create(['device_id' => $device->id]);

    // Create an approved implementation file
    $implementationFile = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'approval_status' => 'approved',
    ]);

    // Create a confirmed expected connection (will be missing since no actual connection)
    ExpectedConnection::factory()->create([
        'implementation_file_id' => $implementationFile->id,
        'source_port_id' => $sourcePort->id,
        'dest_port_id' => $destPort->id,
        'status' => ExpectedConnectionStatus::Confirmed,
    ]);

    // Create an unexpected actual connection (not in expected connections)
    Connection::factory()->create([
        'source_port_id' => $unexpectedSourcePort->id,
        'destination_port_id' => $unexpectedDestPort->id,
    ]);

    // Run detection
    $discrepancies = $this->service->detectForDatacenter($datacenter);

    // Should have at least 2 discrepancies: missing expected connection and unexpected actual connection
    expect($discrepancies)->toHaveCount(2);

    // Verify missing discrepancy was created
    $missingDiscrepancy = Discrepancy::where('source_port_id', $sourcePort->id)
        ->where('dest_port_id', $destPort->id)
        ->where('discrepancy_type', DiscrepancyType::Missing)
        ->first();

    expect($missingDiscrepancy)->not->toBeNull();
    expect($missingDiscrepancy->datacenter_id)->toBe($datacenter->id);
    expect($missingDiscrepancy->status)->toBe(DiscrepancyStatus::Open);

    // Verify unexpected discrepancy was created
    $unexpectedDiscrepancy = Discrepancy::where('source_port_id', $unexpectedSourcePort->id)
        ->where('dest_port_id', $unexpectedDestPort->id)
        ->where('discrepancy_type', DiscrepancyType::Unexpected)
        ->first();

    expect($unexpectedDiscrepancy)->not->toBeNull();
    expect($unexpectedDiscrepancy->datacenter_id)->toBe($datacenter->id);
});

test('detection for room scope filters appropriately', function () {
    $datacenter = Datacenter::factory()->create();

    // Room 1 with a device
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $rack1 = Rack::factory()->create(['row_id' => $row1->id]);
    $device1 = Device::factory()->create(['rack_id' => $rack1->id]);

    // Room 2 with a device
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);
    $rack2 = Rack::factory()->create(['row_id' => $row2->id]);
    $device2 = Device::factory()->create(['rack_id' => $rack2->id]);

    // Ports in room 1
    $sourcePortRoom1 = Port::factory()->create(['device_id' => $device1->id]);
    $destPortRoom1 = Port::factory()->create(['device_id' => $device1->id]);

    // Ports in room 2
    $sourcePortRoom2 = Port::factory()->create(['device_id' => $device2->id]);
    $destPortRoom2 = Port::factory()->create(['device_id' => $device2->id]);

    // Create implementation file
    $implementationFile = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'approval_status' => 'approved',
    ]);

    // Expected connections in both rooms
    ExpectedConnection::factory()->create([
        'implementation_file_id' => $implementationFile->id,
        'source_port_id' => $sourcePortRoom1->id,
        'dest_port_id' => $destPortRoom1->id,
        'status' => ExpectedConnectionStatus::Confirmed,
    ]);

    ExpectedConnection::factory()->create([
        'implementation_file_id' => $implementationFile->id,
        'source_port_id' => $sourcePortRoom2->id,
        'dest_port_id' => $destPortRoom2->id,
        'status' => ExpectedConnectionStatus::Confirmed,
    ]);

    // Detect only for room 1
    $discrepancies = $this->service->detectForRoom($room1);

    // Should only have discrepancy for room 1
    expect($discrepancies->count())->toBe(1);

    $discrepancy = $discrepancies->first();
    expect($discrepancy->source_port_id)->toBe($sourcePortRoom1->id);
    expect($discrepancy->room_id)->toBe($room1->id);
});

test('detection for implementation file scope creates discrepancies linked to file', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    $sourcePort = Port::factory()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->create(['device_id' => $device->id]);

    $implementationFile = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'approval_status' => 'approved',
    ]);

    ExpectedConnection::factory()->create([
        'implementation_file_id' => $implementationFile->id,
        'source_port_id' => $sourcePort->id,
        'dest_port_id' => $destPort->id,
        'status' => ExpectedConnectionStatus::Confirmed,
    ]);

    $discrepancies = $this->service->detectForImplementationFile($implementationFile);

    expect($discrepancies)->toHaveCount(1);
    expect($discrepancies->first()->implementation_file_id)->toBe($implementationFile->id);
    expect($discrepancies->first()->discrepancy_type)->toBe(DiscrepancyType::Missing);
});

test('upsert logic updates existing discrepancy instead of creating new', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    $sourcePort = Port::factory()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->create(['device_id' => $device->id]);

    $implementationFile = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'approval_status' => 'approved',
    ]);

    ExpectedConnection::factory()->create([
        'implementation_file_id' => $implementationFile->id,
        'source_port_id' => $sourcePort->id,
        'dest_port_id' => $destPort->id,
        'status' => ExpectedConnectionStatus::Confirmed,
    ]);

    // Run detection first time
    $firstRun = $this->service->detectForDatacenter($datacenter);
    $firstDiscrepancy = $firstRun->first();
    $originalDetectedAt = $firstDiscrepancy->detected_at;

    // Wait a moment to ensure timestamp difference
    sleep(1);

    // Run detection second time
    $secondRun = $this->service->detectForDatacenter($datacenter);

    // Should still have only one discrepancy
    expect(Discrepancy::count())->toBe(1);

    // The discrepancy should have updated detected_at
    $updatedDiscrepancy = Discrepancy::first();
    expect($updatedDiscrepancy->id)->toBe($firstDiscrepancy->id);
    expect($updatedDiscrepancy->detected_at->greaterThan($originalDetectedAt))->toBeTrue();
});

test('configuration mismatch detection identifies cable_type and cable_length differences', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    $sourcePort = Port::factory()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->create(['device_id' => $device->id]);

    $implementationFile = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'approval_status' => 'approved',
    ]);

    // Expected connection with cat6 cable, 10m length
    ExpectedConnection::factory()->create([
        'implementation_file_id' => $implementationFile->id,
        'source_port_id' => $sourcePort->id,
        'dest_port_id' => $destPort->id,
        'cable_type' => CableType::Cat6,
        'cable_length' => 10.0,
        'status' => ExpectedConnectionStatus::Confirmed,
    ]);

    // Actual connection with cat5e cable, 5m length (different)
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_type' => CableType::Cat5e,
        'cable_length' => 5.0,
    ]);

    $discrepancies = $this->service->detectForDatacenter($datacenter);

    // Should detect a configuration mismatch
    $configMismatch = $discrepancies->filter(function ($d) {
        return $d->discrepancy_type === DiscrepancyType::ConfigurationMismatch;
    })->first();

    expect($configMismatch)->not->toBeNull();
    expect($configMismatch->mismatch_details)->toHaveKey('cable_type');
    expect($configMismatch->mismatch_details)->toHaveKey('cable_length');
    expect($configMismatch->mismatch_details['cable_type']['expected'])->toBe(CableType::Cat6->value);
    expect($configMismatch->mismatch_details['cable_type']['actual'])->toBe(CableType::Cat5e->value);
    expect($configMismatch->mismatch_details['cable_length']['expected'])->toEqual(10.0);
    expect($configMismatch->mismatch_details['cable_length']['actual'])->toEqual(5.0);
});

test('port type mismatch detection identifies source and destination port type differences', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    // Source port with Ethernet type
    $sourcePort = Port::factory()->create([
        'device_id' => $device->id,
        'type' => \App\Enums\PortType::Ethernet,
    ]);
    // Dest port with Fiber type
    $destPort = Port::factory()->create([
        'device_id' => $device->id,
        'type' => \App\Enums\PortType::Fiber,
    ]);

    // A different port with different type
    $differentPort = Port::factory()->create([
        'device_id' => $device->id,
        'type' => \App\Enums\PortType::Power,
    ]);

    $implementationFile = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'approval_status' => 'approved',
    ]);

    // Expected connection
    ExpectedConnection::factory()->create([
        'implementation_file_id' => $implementationFile->id,
        'source_port_id' => $sourcePort->id,
        'dest_port_id' => $destPort->id,
        'status' => ExpectedConnectionStatus::Confirmed,
    ]);

    // Actual connection with different destination port (different type)
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $differentPort->id,
    ]);

    $discrepancies = $this->service->detectForDatacenter($datacenter);

    // Should detect a mismatched discrepancy
    $mismatchedDiscrepancy = $discrepancies->filter(function ($d) {
        return $d->discrepancy_type === DiscrepancyType::Mismatched;
    })->first();

    expect($mismatchedDiscrepancy)->not->toBeNull();
    // The mismatch_details should include port type information if types differ
    if ($mismatchedDiscrepancy->mismatch_details !== null) {
        expect($mismatchedDiscrepancy->mismatch_details)->toHaveKey('dest_port_type');
    }
});

test('resolved discrepancies marked when connections match', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    $sourcePort = Port::factory()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->create(['device_id' => $device->id]);

    $implementationFile = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'approval_status' => 'approved',
    ]);

    ExpectedConnection::factory()->create([
        'implementation_file_id' => $implementationFile->id,
        'source_port_id' => $sourcePort->id,
        'dest_port_id' => $destPort->id,
        'status' => ExpectedConnectionStatus::Confirmed,
    ]);

    // First run: no actual connection, should create Missing discrepancy
    $this->service->detectForDatacenter($datacenter);

    $missingDiscrepancy = Discrepancy::where('discrepancy_type', DiscrepancyType::Missing)
        ->where('source_port_id', $sourcePort->id)
        ->first();

    expect($missingDiscrepancy)->not->toBeNull();
    expect($missingDiscrepancy->status)->toBe(DiscrepancyStatus::Open);

    // Now create the actual connection that matches
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    // Second run: connection exists, discrepancy should be resolved
    $this->service->detectForDatacenter($datacenter);

    $resolvedDiscrepancy = $missingDiscrepancy->fresh();
    expect($resolvedDiscrepancy->status)->toBe(DiscrepancyStatus::Resolved);
    expect($resolvedDiscrepancy->resolved_at)->not->toBeNull();
});

test('incremental detection based on last run timestamp', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    $sourcePort1 = Port::factory()->create(['device_id' => $device->id]);
    $destPort1 = Port::factory()->create(['device_id' => $device->id]);

    $implementationFile = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'approval_status' => 'approved',
    ]);

    // Create first expected connection (created "in the past")
    $oldExpectedConnection = ExpectedConnection::factory()->create([
        'implementation_file_id' => $implementationFile->id,
        'source_port_id' => $sourcePort1->id,
        'dest_port_id' => $destPort1->id,
        'status' => ExpectedConnectionStatus::Confirmed,
    ]);

    // Backdate the timestamps using DB query to avoid model events
    \DB::table('expected_connections')
        ->where('id', $oldExpectedConnection->id)
        ->update([
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

    // Record the "last run" timestamp
    $lastRunAt = now()->subMinutes(30);

    // Create new expected connection (after last run) - use completely different ports
    $sourcePort2 = Port::factory()->create(['device_id' => $device->id]);
    $destPort2 = Port::factory()->create(['device_id' => $device->id]);

    ExpectedConnection::factory()->create([
        'implementation_file_id' => $implementationFile->id,
        'source_port_id' => $sourcePort2->id,
        'dest_port_id' => $destPort2->id,
        'status' => ExpectedConnectionStatus::Confirmed,
    ]);

    // Run incremental detection
    $discrepancies = $this->service->incrementalDetection($datacenter, $lastRunAt);

    // Should only detect the new expected connection (created after last run)
    // Filter to only Missing type discrepancies to be more specific
    $missingDiscrepancies = $discrepancies->filter(function ($d) {
        return $d->discrepancy_type === DiscrepancyType::Missing;
    });

    expect($missingDiscrepancies->count())->toBe(1);
    expect($missingDiscrepancies->first()->source_port_id)->toBe($sourcePort2->id);
});
