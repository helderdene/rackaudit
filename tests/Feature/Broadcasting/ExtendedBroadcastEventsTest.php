<?php

use App\Events\ConnectionChanged;
use App\Events\FindingResolved;
use App\Events\ImplementationFileApproved;
use App\Models\Audit;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Finding;
use App\Models\ImplementationFile;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * Helper to create a connection with a complete datacenter hierarchy.
 * Connection -> Port -> Device -> Rack -> Row -> Room -> Datacenter
 */
function createConnectionWithDatacenter(Datacenter $datacenter): Connection
{
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    $sourceDevice = Device::factory()->create(['rack_id' => $rack->id, 'start_u' => 1]);
    $destDevice = Device::factory()->create(['rack_id' => $rack->id, 'start_u' => 5]);

    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    return Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);
}

test('ConnectionChanged event implements ShouldBroadcast', function () {
    $datacenter = Datacenter::factory()->create();
    $connection = createConnectionWithDatacenter($datacenter);

    $event = new ConnectionChanged($connection, 'created');

    expect($event)->toBeInstanceOf(ShouldBroadcast::class);
});

test('ConnectionChanged event broadcasts on correct datacenter channel', function () {
    $datacenter = Datacenter::factory()->create();
    $connection = createConnectionWithDatacenter($datacenter);

    $event = new ConnectionChanged($connection, 'created');
    $channel = $event->broadcastOn();

    expect($channel)->toBeInstanceOf(PrivateChannel::class)
        ->and($channel->name)->toBe('private-datacenter.'.$datacenter->id);
});

test('ConnectionChanged broadcastWith returns minimal payload', function () {
    $datacenter = Datacenter::factory()->create();
    $connection = createConnectionWithDatacenter($datacenter);
    $user = User::factory()->create();

    // Set the authenticated user for the event context
    $this->actingAs($user);

    $event = new ConnectionChanged($connection, 'updated');
    $payload = $event->broadcastWith();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKeys(['connection_id', 'action', 'user', 'timestamp'])
        ->and($payload['connection_id'])->toBe($connection->id)
        ->and($payload['action'])->toBe('updated')
        ->and($payload['timestamp'])->toBeString();
});

test('ConnectionChanged broadcastAs returns correct event name', function () {
    $datacenter = Datacenter::factory()->create();
    $connection = createConnectionWithDatacenter($datacenter);

    $event = new ConnectionChanged($connection, 'created');

    expect($event->broadcastAs())->toBe('connection.changed');
});

test('ConnectionChanged event for deleted connection includes entity context', function () {
    $datacenter = Datacenter::factory()->create();
    $connection = createConnectionWithDatacenter($datacenter);

    $event = new ConnectionChanged($connection, 'deleted');
    $payload = $event->broadcastWith();

    expect($payload)->toHaveKey('action')
        ->and($payload['action'])->toBe('deleted')
        ->and($payload['connection_id'])->toBe($connection->id);
});

test('ImplementationFileApproved event implements ShouldBroadcast', function () {
    $user = User::factory()->create();
    $datacenter = Datacenter::factory()->create();
    $file = ImplementationFile::factory()
        ->approved($user)
        ->create(['datacenter_id' => $datacenter->id]);

    $event = new ImplementationFileApproved($file);

    expect($event)->toBeInstanceOf(ShouldBroadcast::class);
});

test('ImplementationFileApproved event broadcasts correctly', function () {
    $approver = User::factory()->create();
    $datacenter = Datacenter::factory()->create();
    $file = ImplementationFile::factory()
        ->approved($approver)
        ->create(['datacenter_id' => $datacenter->id]);

    $event = new ImplementationFileApproved($file);

    // Test broadcastOn returns correct channel
    $channel = $event->broadcastOn();
    expect($channel)->toBeInstanceOf(PrivateChannel::class)
        ->and($channel->name)->toBe('private-datacenter.'.$datacenter->id);

    // Test broadcastWith includes file name and approver
    $payload = $event->broadcastWith();
    expect($payload)->toBeArray()
        ->and($payload)->toHaveKeys(['file_id', 'file_name', 'approver', 'timestamp'])
        ->and($payload['file_id'])->toBe($file->id)
        ->and($payload['file_name'])->toBe($file->original_name);

    // Test broadcastAs returns correct event name
    expect($event->broadcastAs())->toBe('implementation_file.approved');
});

test('FindingResolved event implements ShouldBroadcast', function () {
    $user = User::factory()->create();
    $datacenter = Datacenter::factory()->create();
    $audit = Audit::factory()->create(['datacenter_id' => $datacenter->id]);
    $finding = Finding::factory()
        ->forAudit($audit)
        ->resolved($user)
        ->create();

    $event = new FindingResolved($finding, $user);

    expect($event)->toBeInstanceOf(ShouldBroadcast::class);
});

test('FindingResolved event broadcasts correctly', function () {
    $resolver = User::factory()->create();
    $datacenter = Datacenter::factory()->create();
    $audit = Audit::factory()->create(['datacenter_id' => $datacenter->id]);
    $finding = Finding::factory()
        ->forAudit($audit)
        ->withTitle('Missing connection between Server A and Switch B')
        ->resolved($resolver)
        ->create();

    $event = new FindingResolved($finding, $resolver);

    // Test broadcastOn returns correct channel
    $channel = $event->broadcastOn();
    expect($channel)->toBeInstanceOf(PrivateChannel::class)
        ->and($channel->name)->toBe('private-datacenter.'.$datacenter->id);

    // Test broadcastWith includes finding title and resolver
    $payload = $event->broadcastWith();
    expect($payload)->toBeArray()
        ->and($payload)->toHaveKeys(['finding_id', 'title', 'resolver', 'timestamp'])
        ->and($payload['finding_id'])->toBe($finding->id)
        ->and($payload['title'])->toBe($finding->title)
        ->and($payload['resolver'])->toHaveKeys(['id', 'name'])
        ->and($payload['resolver']['id'])->toBe($resolver->id)
        ->and($payload['resolver']['name'])->toBe($resolver->name);

    // Test broadcastAs returns correct event name
    expect($event->broadcastAs())->toBe('finding.resolved');
});

test('all extended events have correct broadcastAs names', function () {
    $datacenter = Datacenter::factory()->create();
    $user = User::factory()->create();

    // ConnectionChanged event
    $connection = createConnectionWithDatacenter($datacenter);
    $connectionEvent = new ConnectionChanged($connection, 'created');
    expect($connectionEvent->broadcastAs())->toBe('connection.changed');

    // ImplementationFileApproved event
    $file = ImplementationFile::factory()
        ->approved($user)
        ->create(['datacenter_id' => $datacenter->id]);
    $fileEvent = new ImplementationFileApproved($file);
    expect($fileEvent->broadcastAs())->toBe('implementation_file.approved');

    // FindingResolved event
    $audit = Audit::factory()->create(['datacenter_id' => $datacenter->id]);
    $finding = Finding::factory()->forAudit($audit)->resolved($user)->create();
    $findingEvent = new FindingResolved($finding, $user);
    expect($findingEvent->broadcastAs())->toBe('finding.resolved');
});
