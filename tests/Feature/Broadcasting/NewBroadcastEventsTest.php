<?php

use App\Enums\AuditStatus;
use App\Enums\DeviceLifecycleStatus;
use App\Events\AuditStatusChanged;
use App\Events\DeviceChanged;
use App\Events\FindingAssigned;
use App\Events\RackChanged;
use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Finding;
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
 * Helper to create a device with complete datacenter hierarchy.
 * Device -> Rack -> Row -> Room -> Datacenter
 */
function createDeviceWithDatacenter(Datacenter $datacenter): Device
{
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    return Device::factory()->create(['rack_id' => $rack->id, 'start_u' => 1]);
}

/**
 * Helper to create a rack with complete datacenter hierarchy.
 * Rack -> Row -> Room -> Datacenter
 */
function createRackWithDatacenter(Datacenter $datacenter): Rack
{
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    return Rack::factory()->create(['row_id' => $row->id]);
}

// ============================================================================
// DeviceChanged Event Tests
// ============================================================================

test('DeviceChanged event implements ShouldBroadcast', function () {
    $datacenter = Datacenter::factory()->create();
    $device = createDeviceWithDatacenter($datacenter);
    $user = User::factory()->create();

    $event = new DeviceChanged($device, 'placed', $user);

    expect($event)->toBeInstanceOf(ShouldBroadcast::class);
});

test('DeviceChanged event broadcasts on correct datacenter channel', function () {
    $datacenter = Datacenter::factory()->create();
    $device = createDeviceWithDatacenter($datacenter);
    $user = User::factory()->create();

    $event = new DeviceChanged($device, 'placed', $user);
    $channel = $event->broadcastOn();

    expect($channel)->toBeInstanceOf(PrivateChannel::class)
        ->and($channel->name)->toBe('private-datacenter.'.$datacenter->id);
});

test('DeviceChanged broadcastWith returns correct payload structure', function () {
    $datacenter = Datacenter::factory()->create();
    $device = createDeviceWithDatacenter($datacenter);
    $user = User::factory()->create();

    $event = new DeviceChanged($device, 'status_changed', $user);
    $payload = $event->broadcastWith();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKeys(['device_id', 'rack_id', 'action', 'user', 'timestamp'])
        ->and($payload['device_id'])->toBe($device->id)
        ->and($payload['rack_id'])->toBe($device->rack_id)
        ->and($payload['action'])->toBe('status_changed')
        ->and($payload['user'])->toHaveKeys(['id', 'name'])
        ->and($payload['user']['id'])->toBe($user->id)
        ->and($payload['timestamp'])->toBeString();
});

test('DeviceChanged broadcastAs returns correct event name', function () {
    $datacenter = Datacenter::factory()->create();
    $device = createDeviceWithDatacenter($datacenter);

    $event = new DeviceChanged($device, 'moved');

    expect($event->broadcastAs())->toBe('device.changed');
});

// ============================================================================
// RackChanged Event Tests
// ============================================================================

test('RackChanged event implements ShouldBroadcast', function () {
    $datacenter = Datacenter::factory()->create();
    $rack = createRackWithDatacenter($datacenter);
    $user = User::factory()->create();

    $event = new RackChanged($rack, 'created', $user);

    expect($event)->toBeInstanceOf(ShouldBroadcast::class);
});

test('RackChanged event broadcasts on correct datacenter channel', function () {
    $datacenter = Datacenter::factory()->create();
    $rack = createRackWithDatacenter($datacenter);
    $user = User::factory()->create();

    $event = new RackChanged($rack, 'created', $user);
    $channel = $event->broadcastOn();

    expect($channel)->toBeInstanceOf(PrivateChannel::class)
        ->and($channel->name)->toBe('private-datacenter.'.$datacenter->id);
});

test('RackChanged broadcastWith returns correct payload structure', function () {
    $datacenter = Datacenter::factory()->create();
    $rack = createRackWithDatacenter($datacenter);
    $user = User::factory()->create();

    // Get the room_id through the relationship chain
    $roomId = $rack->row->room->id;

    $event = new RackChanged($rack, 'updated', $user);
    $payload = $event->broadcastWith();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKeys(['rack_id', 'room_id', 'action', 'user', 'timestamp'])
        ->and($payload['rack_id'])->toBe($rack->id)
        ->and($payload['room_id'])->toBe($roomId)
        ->and($payload['action'])->toBe('updated')
        ->and($payload['user'])->toHaveKeys(['id', 'name'])
        ->and($payload['timestamp'])->toBeString();
});

test('RackChanged broadcastAs returns correct event name', function () {
    $datacenter = Datacenter::factory()->create();
    $rack = createRackWithDatacenter($datacenter);

    $event = new RackChanged($rack, 'deleted');

    expect($event->broadcastAs())->toBe('rack.changed');
});

// ============================================================================
// AuditStatusChanged Event Tests
// ============================================================================

test('AuditStatusChanged event implements ShouldBroadcast', function () {
    $datacenter = Datacenter::factory()->create();
    $audit = Audit::factory()->create(['datacenter_id' => $datacenter->id]);
    $user = User::factory()->create();

    $event = new AuditStatusChanged(
        $audit,
        AuditStatus::Pending,
        AuditStatus::InProgress,
        $user
    );

    expect($event)->toBeInstanceOf(ShouldBroadcast::class);
});

test('AuditStatusChanged event broadcasts on correct datacenter channel', function () {
    $datacenter = Datacenter::factory()->create();
    $audit = Audit::factory()->create(['datacenter_id' => $datacenter->id]);
    $user = User::factory()->create();

    $event = new AuditStatusChanged(
        $audit,
        AuditStatus::Pending,
        AuditStatus::InProgress,
        $user
    );
    $channel = $event->broadcastOn();

    expect($channel)->toBeInstanceOf(PrivateChannel::class)
        ->and($channel->name)->toBe('private-datacenter.'.$datacenter->id);
});

test('AuditStatusChanged broadcastWith returns correct payload structure', function () {
    $datacenter = Datacenter::factory()->create();
    $audit = Audit::factory()->create(['datacenter_id' => $datacenter->id]);
    $user = User::factory()->create();

    $event = new AuditStatusChanged(
        $audit,
        AuditStatus::Pending,
        AuditStatus::InProgress,
        $user
    );
    $payload = $event->broadcastWith();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKeys(['audit_id', 'old_status', 'new_status', 'user', 'timestamp'])
        ->and($payload['audit_id'])->toBe($audit->id)
        ->and($payload['old_status'])->toBe(AuditStatus::Pending->value)
        ->and($payload['new_status'])->toBe(AuditStatus::InProgress->value)
        ->and($payload['user'])->toHaveKeys(['id', 'name'])
        ->and($payload['user']['id'])->toBe($user->id)
        ->and($payload['timestamp'])->toBeString();
});

test('AuditStatusChanged broadcastAs returns correct event name', function () {
    $datacenter = Datacenter::factory()->create();
    $audit = Audit::factory()->create(['datacenter_id' => $datacenter->id]);
    $user = User::factory()->create();

    $event = new AuditStatusChanged(
        $audit,
        AuditStatus::InProgress,
        AuditStatus::Completed,
        $user
    );

    expect($event->broadcastAs())->toBe('audit.status_changed');
});

// ============================================================================
// FindingAssigned Event Tests
// ============================================================================

test('FindingAssigned event implements ShouldBroadcast', function () {
    $datacenter = Datacenter::factory()->create();
    $audit = Audit::factory()->create(['datacenter_id' => $datacenter->id]);
    $assignee = User::factory()->create();
    $assigner = User::factory()->create();
    $finding = Finding::factory()->forAudit($audit)->assignedTo($assignee)->create();

    $event = new FindingAssigned($finding, $assignee, $assigner);

    expect($event)->toBeInstanceOf(ShouldBroadcast::class);
});

test('FindingAssigned event broadcasts on correct datacenter channel', function () {
    $datacenter = Datacenter::factory()->create();
    $audit = Audit::factory()->create(['datacenter_id' => $datacenter->id]);
    $assignee = User::factory()->create();
    $assigner = User::factory()->create();
    $finding = Finding::factory()->forAudit($audit)->assignedTo($assignee)->create();

    $event = new FindingAssigned($finding, $assignee, $assigner);
    $channel = $event->broadcastOn();

    expect($channel)->toBeInstanceOf(PrivateChannel::class)
        ->and($channel->name)->toBe('private-datacenter.'.$datacenter->id);
});

test('FindingAssigned broadcastWith returns correct payload structure', function () {
    $datacenter = Datacenter::factory()->create();
    $audit = Audit::factory()->create(['datacenter_id' => $datacenter->id]);
    $assignee = User::factory()->create();
    $assigner = User::factory()->create();
    $finding = Finding::factory()->forAudit($audit)->assignedTo($assignee)->create();

    $event = new FindingAssigned($finding, $assignee, $assigner);
    $payload = $event->broadcastWith();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKeys(['finding_id', 'assignee', 'assigner', 'timestamp'])
        ->and($payload['finding_id'])->toBe($finding->id)
        ->and($payload['assignee'])->toHaveKeys(['id', 'name'])
        ->and($payload['assignee']['id'])->toBe($assignee->id)
        ->and($payload['assigner'])->toHaveKeys(['id', 'name'])
        ->and($payload['assigner']['id'])->toBe($assigner->id)
        ->and($payload['timestamp'])->toBeString();
});

test('FindingAssigned broadcastAs returns correct event name', function () {
    $datacenter = Datacenter::factory()->create();
    $audit = Audit::factory()->create(['datacenter_id' => $datacenter->id]);
    $assignee = User::factory()->create();
    $assigner = User::factory()->create();
    $finding = Finding::factory()->forAudit($audit)->assignedTo($assignee)->create();

    $event = new FindingAssigned($finding, $assignee, $assigner);

    expect($event->broadcastAs())->toBe('finding.assigned');
});

// ============================================================================
// Consistent Payload Structure Tests
// ============================================================================

test('all new events have consistent payload structure with timestamp', function () {
    $datacenter = Datacenter::factory()->create();
    $user = User::factory()->create();

    // DeviceChanged
    $device = createDeviceWithDatacenter($datacenter);
    $deviceEvent = new DeviceChanged($device, 'placed', $user);
    $devicePayload = $deviceEvent->broadcastWith();
    expect($devicePayload)->toHaveKey('timestamp')
        ->and($devicePayload['timestamp'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');

    // RackChanged
    $rack = createRackWithDatacenter($datacenter);
    $rackEvent = new RackChanged($rack, 'created', $user);
    $rackPayload = $rackEvent->broadcastWith();
    expect($rackPayload)->toHaveKey('timestamp')
        ->and($rackPayload['timestamp'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');

    // AuditStatusChanged
    $audit = Audit::factory()->create(['datacenter_id' => $datacenter->id]);
    $auditEvent = new AuditStatusChanged($audit, AuditStatus::Pending, AuditStatus::InProgress, $user);
    $auditPayload = $auditEvent->broadcastWith();
    expect($auditPayload)->toHaveKey('timestamp')
        ->and($auditPayload['timestamp'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');

    // FindingAssigned
    $assignee = User::factory()->create();
    $finding = Finding::factory()->forAudit($audit)->assignedTo($assignee)->create();
    $findingEvent = new FindingAssigned($finding, $assignee, $user);
    $findingPayload = $findingEvent->broadcastWith();
    expect($findingPayload)->toHaveKey('timestamp')
        ->and($findingPayload['timestamp'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
});

test('all new events have consistent user information structure', function () {
    $datacenter = Datacenter::factory()->create();
    $user = User::factory()->create(['name' => 'Test User']);

    // DeviceChanged
    $device = createDeviceWithDatacenter($datacenter);
    $devicePayload = (new DeviceChanged($device, 'placed', $user))->broadcastWith();
    expect($devicePayload['user'])->toBe([
        'id' => $user->id,
        'name' => 'Test User',
    ]);

    // RackChanged
    $rack = createRackWithDatacenter($datacenter);
    $rackPayload = (new RackChanged($rack, 'created', $user))->broadcastWith();
    expect($rackPayload['user'])->toBe([
        'id' => $user->id,
        'name' => 'Test User',
    ]);

    // AuditStatusChanged
    $audit = Audit::factory()->create(['datacenter_id' => $datacenter->id]);
    $auditPayload = (new AuditStatusChanged($audit, AuditStatus::Pending, AuditStatus::InProgress, $user))->broadcastWith();
    expect($auditPayload['user'])->toBe([
        'id' => $user->id,
        'name' => 'Test User',
    ]);

    // FindingAssigned (assigner)
    $assignee = User::factory()->create(['name' => 'Assignee User']);
    $finding = Finding::factory()->forAudit($audit)->assignedTo($assignee)->create();
    $findingPayload = (new FindingAssigned($finding, $assignee, $user))->broadcastWith();
    expect($findingPayload['assigner'])->toBe([
        'id' => $user->id,
        'name' => 'Test User',
    ]);
});

// ============================================================================
// Observer Dispatching Tests
// ============================================================================

test('DeviceChanged event is dispatched when device is placed in a rack', function () {
    Event::fake([DeviceChanged::class]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create device with rack assignment (placed)
    Device::factory()->create(['rack_id' => $rack->id, 'start_u' => 1]);

    Event::assertDispatched(DeviceChanged::class, function ($event) {
        return $event->action === 'placed';
    });
});

test('RackChanged event is dispatched when rack is created', function () {
    Event::fake([RackChanged::class]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    Rack::factory()->create(['row_id' => $row->id]);

    Event::assertDispatched(RackChanged::class, function ($event) {
        return $event->action === 'created';
    });
});

test('AuditStatusChanged event is dispatched when audit status changes', function () {
    Event::fake([AuditStatusChanged::class]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $datacenter = Datacenter::factory()->create();
    $audit = Audit::factory()->pending()->create(['datacenter_id' => $datacenter->id]);

    // Update the audit status
    $audit->update(['status' => AuditStatus::InProgress]);

    Event::assertDispatched(AuditStatusChanged::class, function ($event) {
        return $event->oldStatus === AuditStatus::Pending
            && $event->newStatus === AuditStatus::InProgress;
    });
});

test('FindingAssigned event is dispatched when finding is assigned', function () {
    Event::fake([FindingAssigned::class]);

    $assigner = User::factory()->create();
    $assignee = User::factory()->create();
    $this->actingAs($assigner);

    $datacenter = Datacenter::factory()->create();
    $audit = Audit::factory()->create(['datacenter_id' => $datacenter->id]);
    $finding = Finding::factory()->forAudit($audit)->create(['assigned_to' => null]);

    // Assign the finding
    $finding->update(['assigned_to' => $assignee->id]);

    Event::assertDispatched(FindingAssigned::class, function ($event) use ($assignee) {
        return $event->assignee->id === $assignee->id;
    });
});

test('DeviceChanged event dispatched with removed action when device is unplaced', function () {
    Event::fake([DeviceChanged::class]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $datacenter = Datacenter::factory()->create();
    $device = createDeviceWithDatacenter($datacenter);

    // Clear the fake to ignore the 'placed' event from creation
    Event::fake([DeviceChanged::class]);

    // Unplace the device
    $device->update(['rack_id' => null, 'start_u' => null]);

    Event::assertDispatched(DeviceChanged::class, function ($event) {
        return $event->action === 'removed';
    });
});

test('RackChanged event dispatched with updated action when rack is modified', function () {
    Event::fake([RackChanged::class]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $datacenter = Datacenter::factory()->create();
    $rack = createRackWithDatacenter($datacenter);

    // Clear the fake to ignore the 'created' event
    Event::fake([RackChanged::class]);

    // Update the rack
    $rack->update(['name' => 'Updated Rack Name']);

    Event::assertDispatched(RackChanged::class, function ($event) {
        return $event->action === 'updated';
    });
});
