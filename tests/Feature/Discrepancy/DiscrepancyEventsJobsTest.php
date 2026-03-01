<?php

use App\Enums\DiscrepancyStatus;
use App\Enums\DiscrepancyType;
use App\Enums\ExpectedConnectionStatus;
use App\Events\ConnectionChanged;
use App\Events\ExpectedConnectionConfirmed;
use App\Events\ImplementationFileApproved;
use App\Jobs\DetectDiscrepanciesJob;
use App\Listeners\DetectDiscrepanciesForConnection;
use App\Listeners\DetectDiscrepanciesForExpectedConnection;
use App\Listeners\DetectDiscrepanciesForImplementationFile;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('connection created event triggers limited detection', function () {
    Queue::fake();
    Event::fake([ConnectionChanged::class]);

    // Set up datacenter hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    $sourcePort = Port::factory()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->create(['device_id' => $device->id]);

    // Create a connection which should trigger the event
    $connection = Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    // Manually dispatch the event as we're testing the listener behavior
    $event = new ConnectionChanged($connection, 'created');

    $listener = new DetectDiscrepanciesForConnection;
    $listener->handle($event);

    // The listener should queue a detection job
    Queue::assertPushed(DetectDiscrepanciesJob::class, function ($job) use ($connection) {
        return $job->connectionId === $connection->id;
    });
});

test('connection updated event triggers detection for affected ports', function () {
    Queue::fake();

    // Set up datacenter hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    $sourcePort = Port::factory()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->create(['device_id' => $device->id]);

    $connection = Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    // Dispatch update event
    $event = new ConnectionChanged($connection, 'updated');

    $listener = new DetectDiscrepanciesForConnection;
    $listener->handle($event);

    // Should queue detection job for the affected connection
    Queue::assertPushed(DetectDiscrepanciesJob::class, function ($job) use ($connection) {
        return $job->connectionId === $connection->id;
    });
});

test('implementation file approved event triggers full file detection', function () {
    Queue::fake();

    $datacenter = Datacenter::factory()->create();
    $implementationFile = ImplementationFile::factory()->create([
        'datacenter_id' => $datacenter->id,
        'approval_status' => 'approved',
    ]);

    // Dispatch the approved event
    $event = new ImplementationFileApproved($implementationFile);

    $listener = new DetectDiscrepanciesForImplementationFile;
    $listener->handle($event);

    // Should queue detection job for the implementation file
    Queue::assertPushed(DetectDiscrepanciesJob::class, function ($job) use ($implementationFile) {
        return $job->implementationFileId === $implementationFile->id;
    });
});

test('expected connection confirmed event triggers detection', function () {
    Queue::fake();

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

    $expectedConnection = ExpectedConnection::factory()->create([
        'implementation_file_id' => $implementationFile->id,
        'source_port_id' => $sourcePort->id,
        'dest_port_id' => $destPort->id,
        'status' => ExpectedConnectionStatus::Confirmed,
    ]);

    // Dispatch the confirmed event
    $event = new ExpectedConnectionConfirmed($expectedConnection);

    $listener = new DetectDiscrepanciesForExpectedConnection;
    $listener->handle($event);

    // Should queue detection job for the expected connection
    Queue::assertPushed(DetectDiscrepanciesJob::class, function ($job) use ($expectedConnection) {
        return $job->expectedConnectionId === $expectedConnection->id;
    });
});

test('detect discrepancies job runs detection for correct scope', function () {
    // Set up datacenter hierarchy
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

    // Ensure no discrepancies exist before running job
    expect(Discrepancy::count())->toBe(0);

    // Run the job with datacenter scope
    $job = new DetectDiscrepanciesJob(datacenterId: $datacenter->id);
    $job->handle(app(\App\Services\DiscrepancyDetectionService::class));

    // Should have created a discrepancy (missing connection)
    expect(Discrepancy::count())->toBe(1);

    $discrepancy = Discrepancy::first();
    expect($discrepancy->datacenter_id)->toBe($datacenter->id);
    expect($discrepancy->discrepancy_type)->toBe(DiscrepancyType::Missing);
    expect($discrepancy->status)->toBe(DiscrepancyStatus::Open);
});

test('detect discrepancies job works with implementation file scope', function () {
    // Set up datacenter hierarchy
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

    // Run the job with implementation file scope
    $job = new DetectDiscrepanciesJob(implementationFileId: $implementationFile->id);
    $job->handle(app(\App\Services\DiscrepancyDetectionService::class));

    // Should have created a discrepancy linked to the file
    expect(Discrepancy::count())->toBe(1);

    $discrepancy = Discrepancy::first();
    expect($discrepancy->implementation_file_id)->toBe($implementationFile->id);
});

test('scheduled job configuration works correctly', function () {
    // Verify config file exists and has correct structure
    $config = config('discrepancies');

    expect($config)->toBeArray();
    expect($config)->toHaveKey('schedule');
    expect($config['schedule'])->toHaveKey('enabled');
    expect($config['schedule'])->toHaveKey('time');
    expect($config['schedule']['time'])->toBe('02:00');
});
