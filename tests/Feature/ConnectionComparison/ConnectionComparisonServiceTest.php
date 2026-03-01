<?php

use App\DTOs\ComparisonResult;
use App\DTOs\ComparisonResultCollection;
use App\Enums\DiscrepancyType;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\ExpectedConnection;
use App\Models\ImplementationFile;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Services\ConnectionComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new ConnectionComparisonService;
});

it('detects exact match when source and destination ports match', function () {
    // Create an implementation file with a confirmed expected connection
    $file = ImplementationFile::factory()->xlsx()->approved()->create();

    $sourceDevice = Device::factory()->create();
    $destDevice = Device::factory()->create();
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    // Create expected connection
    $expectedConnection = ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice->id,
            'dest_port_id' => $destPort->id,
        ]);

    // Create matching actual connection
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    // Run comparison
    $results = $this->service->compareForImplementationFile($file);

    expect($results)->toBeInstanceOf(ComparisonResultCollection::class)
        ->and($results->count())->toBe(1)
        ->and($results->first())->toBeInstanceOf(ComparisonResult::class)
        ->and($results->first()->discrepancyType)->toBe(DiscrepancyType::Matched);
});

it('detects bidirectional match where A to B matches B to A', function () {
    // Create an implementation file with a confirmed expected connection
    $file = ImplementationFile::factory()->xlsx()->approved()->create();

    $sourceDevice = Device::factory()->create();
    $destDevice = Device::factory()->create();
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    // Create expected connection: A -> B
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice->id,
            'dest_port_id' => $destPort->id,
        ]);

    // Create actual connection in reverse: B -> A
    Connection::factory()->create([
        'source_port_id' => $destPort->id,
        'destination_port_id' => $sourcePort->id,
    ]);

    // Run comparison
    $results = $this->service->compareForImplementationFile($file);

    expect($results->count())->toBe(1)
        ->and($results->first()->discrepancyType)->toBe(DiscrepancyType::Matched);
});

it('detects expected but missing connection', function () {
    // Create an implementation file with a confirmed expected connection
    $file = ImplementationFile::factory()->xlsx()->approved()->create();

    $sourceDevice = Device::factory()->create();
    $destDevice = Device::factory()->create();
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    // Create expected connection but NO actual connection
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice->id,
            'dest_port_id' => $destPort->id,
        ]);

    // Run comparison - no actual connection exists
    $results = $this->service->compareForImplementationFile($file);

    expect($results->count())->toBe(1)
        ->and($results->first()->discrepancyType)->toBe(DiscrepancyType::Missing)
        ->and($results->first()->actualConnection)->toBeNull()
        ->and($results->first()->expectedConnection)->not->toBeNull();
});

it('detects actual but unexpected connection', function () {
    // Create datacenter with room -> row -> rack -> device hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $device->id]);

    $otherDevice = Device::factory()->create(['rack_id' => $rack->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $otherDevice->id]);

    // Create an approved file but with NO expected connections for these ports
    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    // Create a different expected connection (not involving our ports)
    $unrelatedDevice1 = Device::factory()->create(['rack_id' => $rack->id]);
    $unrelatedDevice2 = Device::factory()->create(['rack_id' => $rack->id]);
    $unrelatedPort1 = Port::factory()->ethernet()->create(['device_id' => $unrelatedDevice1->id]);
    $unrelatedPort2 = Port::factory()->ethernet()->create(['device_id' => $unrelatedDevice2->id]);

    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $unrelatedDevice1->id,
            'source_port_id' => $unrelatedPort1->id,
            'dest_device_id' => $unrelatedDevice2->id,
            'dest_port_id' => $unrelatedPort2->id,
        ]);

    // Create actual connection for unrelated ports
    Connection::factory()->create([
        'source_port_id' => $unrelatedPort1->id,
        'destination_port_id' => $unrelatedPort2->id,
    ]);

    // Create an unexpected actual connection (not in any expected connections)
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    // Run comparison for datacenter
    $results = $this->service->compareForDatacenter($datacenter);

    // Should find: 1 matched + 1 unexpected
    $unexpected = $results->filterByDiscrepancyType(DiscrepancyType::Unexpected);
    expect($unexpected->count())->toBe(1)
        ->and($unexpected->first()->expectedConnection)->toBeNull()
        ->and($unexpected->first()->actualConnection)->not->toBeNull();
});

it('detects partial match where source port matches but destination differs', function () {
    $file = ImplementationFile::factory()->xlsx()->approved()->create();

    $sourceDevice = Device::factory()->create();
    $destDevice = Device::factory()->create();
    $wrongDestDevice = Device::factory()->create();

    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $expectedDestPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);
    $actualDestPort = Port::factory()->ethernet()->create(['device_id' => $wrongDestDevice->id]);

    // Create expected connection: source -> expectedDest
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice->id,
            'dest_port_id' => $expectedDestPort->id,
        ]);

    // Create actual connection: source -> actualDest (DIFFERENT destination)
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $actualDestPort->id,
    ]);

    // Run comparison
    $results = $this->service->compareForImplementationFile($file);

    expect($results->count())->toBe(1)
        ->and($results->first()->discrepancyType)->toBe(DiscrepancyType::Mismatched)
        ->and($results->first()->expectedDestPort->id)->toBe($expectedDestPort->id)
        ->and($results->first()->actualDestPort->id)->toBe($actualDestPort->id);
});

it('detects conflicts when multiple files specify different destinations for same source', function () {
    // Create datacenter with hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create devices and ports
    $sourceDevice = Device::factory()->create(['rack_id' => $rack->id]);
    $destDevice1 = Device::factory()->create(['rack_id' => $rack->id]);
    $destDevice2 = Device::factory()->create(['rack_id' => $rack->id]);

    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort1 = Port::factory()->ethernet()->create(['device_id' => $destDevice1->id]);
    $destPort2 = Port::factory()->ethernet()->create(['device_id' => $destDevice2->id]);

    // Create two approved files with conflicting expected connections
    $file1 = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);
    $file2 = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    // File 1: source -> dest1
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file1)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice1->id,
            'dest_port_id' => $destPort1->id,
        ]);

    // File 2: source -> dest2 (CONFLICT - same source, different dest)
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file2)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice2->id,
            'dest_port_id' => $destPort2->id,
        ]);

    // Run comparison for datacenter
    $results = $this->service->compareForDatacenter($datacenter);

    // Both should be marked as conflicting
    $conflicts = $results->filterByDiscrepancyType(DiscrepancyType::Conflicting);
    expect($conflicts->count())->toBe(2);

    // Check that conflict info is populated
    $first = $conflicts->first();
    expect($first->conflictInfo)->not->toBeNull()
        ->and($first->conflictInfo['conflict_count'])->toBe(2)
        ->and($first->conflictInfo['other_expectations'])->toHaveCount(1);
});

it('filters results by implementation file', function () {
    // Create datacenter with two approved files
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    $file1 = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);
    $file2 = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    // Create expected connections for each file
    $device1 = Device::factory()->create(['rack_id' => $rack->id]);
    $device2 = Device::factory()->create(['rack_id' => $rack->id]);
    $port1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    $port2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);

    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file1)
        ->create([
            'source_device_id' => $device1->id,
            'source_port_id' => $port1->id,
            'dest_device_id' => $device2->id,
            'dest_port_id' => $port2->id,
        ]);

    $device3 = Device::factory()->create(['rack_id' => $rack->id]);
    $device4 = Device::factory()->create(['rack_id' => $rack->id]);
    $port3 = Port::factory()->ethernet()->create(['device_id' => $device3->id]);
    $port4 = Port::factory()->ethernet()->create(['device_id' => $device4->id]);

    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file2)
        ->create([
            'source_device_id' => $device3->id,
            'source_port_id' => $port3->id,
            'dest_device_id' => $device4->id,
            'dest_port_id' => $port4->id,
        ]);

    // Compare for file1 only
    $results1 = $this->service->compareForImplementationFile($file1);
    expect($results1->count())->toBe(1);

    // Compare for file2 only
    $results2 = $this->service->compareForImplementationFile($file2);
    expect($results2->count())->toBe(1);

    // Compare for entire datacenter should include both
    $resultsAll = $this->service->compareForDatacenter($datacenter);
    expect($resultsAll->count())->toBe(2);
});

it('aggregates comparisons across datacenter', function () {
    // Create datacenter with multiple approved files
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    $file1 = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);
    $file2 = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    // Create 3 expected connections across both files
    for ($i = 0; $i < 2; $i++) {
        $sourceDevice = Device::factory()->create(['rack_id' => $rack->id]);
        $destDevice = Device::factory()->create(['rack_id' => $rack->id]);
        $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
        $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

        ExpectedConnection::factory()
            ->confirmed()
            ->forImplementationFile($file1)
            ->create([
                'source_device_id' => $sourceDevice->id,
                'source_port_id' => $sourcePort->id,
                'dest_device_id' => $destDevice->id,
                'dest_port_id' => $destPort->id,
            ]);

        // Create matching actual connection for first one only
        if ($i === 0) {
            Connection::factory()->create([
                'source_port_id' => $sourcePort->id,
                'destination_port_id' => $destPort->id,
            ]);
        }
    }

    // Create one more expected connection in file2
    $sourceDevice = Device::factory()->create(['rack_id' => $rack->id]);
    $destDevice = Device::factory()->create(['rack_id' => $rack->id]);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file2)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice->id,
            'dest_port_id' => $destPort->id,
        ]);

    // Run datacenter-wide comparison
    $results = $this->service->compareForDatacenter($datacenter);

    // Should have 3 results: 1 matched, 2 missing
    expect($results->count())->toBe(3);

    $stats = $results->getStatistics();
    expect($stats['matched'])->toBe(1)
        ->and($stats['missing'])->toBe(2);
});
