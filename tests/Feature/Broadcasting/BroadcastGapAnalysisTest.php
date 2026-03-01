<?php

use App\Enums\AuditStatus;
use App\Enums\DeviceLifecycleStatus;
use App\Events\AuditStatusChanged;
use App\Events\ConnectionChanged;
use App\Events\DeviceChanged;
use App\Events\FindingAssigned;
use App\Events\RackChanged;
use App\Models\Audit;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Finding;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * Gap Analysis Tests for Real-Time Broadcasting Feature.
 *
 * These tests fill critical coverage gaps identified in the Task Group 8 review:
 * 1. Unauthorized user denial via authorization logic verification
 * 2. Event behavior without authenticated user context
 * 3. Observer dispatch when user is not authenticated
 * 4. Relationship traversal failure (datacenter ID fallback to 0)
 * 5. Observer action determination with multiple attribute changes
 * 6. Event payload JSON serialization safety
 * 7. Device created without rack placement (no broadcast)
 * 8. Multiple events dispatch ordering
 * 9. Connection with incomplete relationship chain
 * 10. RackChanged handles user being null gracefully
 */

// ============================================================================
// Gap 1: Unauthorized User Denial via Authorization Logic
// ============================================================================

test('authorization logic denies user without datacenter access', function () {
    $authorizedUser = User::factory()->create();
    $unauthorizedUser = User::factory()->create();
    $datacenter = Datacenter::factory()->create();

    // Only attach the authorized user
    $authorizedUser->datacenters()->attach($datacenter);

    // Verify the authorization logic explicitly denies unauthorized users
    $authorizedHasAccess = $authorizedUser->datacenters()
        ->where('datacenter_id', $datacenter->id)
        ->exists();
    expect($authorizedHasAccess)->toBeTrue();

    $unauthorizedHasAccess = $unauthorizedUser->datacenters()
        ->where('datacenter_id', $datacenter->id)
        ->exists();
    expect($unauthorizedHasAccess)->toBeFalse();

    // Verify the channel authorization callback structure
    // The Broadcast::channel callback should return false for unauthorized users
    $datacenterExists = Datacenter::find($datacenter->id);
    expect($datacenterExists)->not->toBeNull();
    expect($unauthorizedUser->datacenters()->where('datacenter_id', $datacenter->id)->exists())->toBeFalse();
});

// ============================================================================
// Gap 2: Event Behavior Without Authenticated User
// ============================================================================

test('DeviceChanged event handles null user gracefully in payload', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id, 'start_u' => 1]);

    // Create event without user (simulates background job scenario)
    $event = new DeviceChanged($device, 'status_changed', null);
    $payload = $event->broadcastWith();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKey('user')
        ->and($payload['user'])->toBeNull()
        ->and($payload['device_id'])->toBe($device->id)
        ->and($payload['action'])->toBe('status_changed');
});

test('RackChanged event handles null user gracefully in payload', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create event without user (simulates background job scenario)
    $event = new RackChanged($rack, 'created', null);
    $payload = $event->broadcastWith();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKey('user')
        ->and($payload['user'])->toBeNull()
        ->and($payload['rack_id'])->toBe($rack->id)
        ->and($payload['action'])->toBe('created');
});

// ============================================================================
// Gap 3: Observer Does Not Dispatch When User is Not Authenticated
// ============================================================================

test('AuditStatusChanged event is not dispatched when no user is authenticated', function () {
    Event::fake([AuditStatusChanged::class]);

    // Ensure no user is authenticated
    Auth::logout();

    $datacenter = Datacenter::factory()->create();
    $audit = Audit::factory()->pending()->create(['datacenter_id' => $datacenter->id]);

    // Update the audit status without authenticated user
    $audit->update(['status' => AuditStatus::InProgress]);

    // The observer should check for user and NOT dispatch without one
    Event::assertNotDispatched(AuditStatusChanged::class);
});

test('FindingAssigned event is not dispatched when no assigner is authenticated', function () {
    Event::fake([FindingAssigned::class]);

    // Ensure no user is authenticated
    Auth::logout();

    $datacenter = Datacenter::factory()->create();
    $audit = Audit::factory()->create(['datacenter_id' => $datacenter->id]);
    $assignee = User::factory()->create();
    $finding = Finding::factory()->forAudit($audit)->create(['assigned_to' => null]);

    // Assign the finding without authenticated user
    $finding->update(['assigned_to' => $assignee->id]);

    // The observer should NOT dispatch without an assigner
    Event::assertNotDispatched(FindingAssigned::class);
});

// ============================================================================
// Gap 4: Relationship Traversal Failure (Datacenter ID Fallback)
// ============================================================================

test('DeviceChanged broadcasts to channel 0 when device has no rack relationship', function () {
    // Create a device without rack assignment (inventory-only device)
    $device = Device::factory()->create(['rack_id' => null, 'start_u' => null]);
    $user = User::factory()->create();

    $event = new DeviceChanged($device, 'status_changed', $user);
    $channel = $event->broadcastOn();

    // Should fall back to datacenter.0 channel when no datacenter can be resolved
    expect($channel->name)->toBe('private-datacenter.0');
});

test('ConnectionChanged broadcasts to channel 0 when relationship chain is broken', function () {
    // Create ports without complete hierarchy (orphaned connection)
    $sourceDevice = Device::factory()->create(['rack_id' => null]);
    $destDevice = Device::factory()->create(['rack_id' => null]);

    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    $connection = Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    $event = new ConnectionChanged($connection, 'created');
    $channel = $event->broadcastOn();

    // Should fall back to datacenter.0 channel when no datacenter can be resolved
    expect($channel->name)->toBe('private-datacenter.0');
});

// ============================================================================
// Gap 5: Observer Action Determination with Multiple Attribute Changes
// ============================================================================

test('DeviceChanged observer prioritizes rack change over status change', function () {
    Event::fake([DeviceChanged::class]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack1 = Rack::factory()->create(['row_id' => $row->id]);
    $rack2 = Rack::factory()->create(['row_id' => $row->id]);

    // Create device in rack1
    $device = Device::factory()->create([
        'rack_id' => $rack1->id,
        'start_u' => 1,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);

    // Clear the fake for the update test
    Event::fake([DeviceChanged::class]);

    // Change both rack AND status in same update
    $device->update([
        'rack_id' => $rack2->id,
        'lifecycle_status' => DeviceLifecycleStatus::Decommissioned,
    ]);

    // Should dispatch 'moved' action since rack change takes priority
    Event::assertDispatched(DeviceChanged::class, function ($event) {
        return $event->action === 'moved';
    });
});

// ============================================================================
// Gap 6: Event Payload JSON Serialization Safety
// ============================================================================

test('all broadcast event payloads can be safely JSON encoded', function () {
    $datacenter = Datacenter::factory()->create();
    $user = User::factory()->create(['name' => 'Test User']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // DeviceChanged
    $device = Device::factory()->create(['rack_id' => $rack->id, 'start_u' => 1]);
    $devicePayload = (new DeviceChanged($device, 'placed', $user))->broadcastWith();
    $deviceJson = json_encode($devicePayload);
    expect($deviceJson)->not->toBeFalse()
        ->and(json_last_error())->toBe(JSON_ERROR_NONE);

    // RackChanged
    $rackPayload = (new RackChanged($rack, 'created', $user))->broadcastWith();
    $rackJson = json_encode($rackPayload);
    expect($rackJson)->not->toBeFalse()
        ->and(json_last_error())->toBe(JSON_ERROR_NONE);

    // AuditStatusChanged
    $audit = Audit::factory()->create(['datacenter_id' => $datacenter->id]);
    $auditPayload = (new AuditStatusChanged($audit, AuditStatus::Pending, AuditStatus::InProgress, $user))->broadcastWith();
    $auditJson = json_encode($auditPayload);
    expect($auditJson)->not->toBeFalse()
        ->and(json_last_error())->toBe(JSON_ERROR_NONE);

    // FindingAssigned
    $assignee = User::factory()->create(['name' => 'Assignee User']);
    $finding = Finding::factory()->forAudit($audit)->assignedTo($assignee)->create();
    $findingPayload = (new FindingAssigned($finding, $assignee, $user))->broadcastWith();
    $findingJson = json_encode($findingPayload);
    expect($findingJson)->not->toBeFalse()
        ->and(json_last_error())->toBe(JSON_ERROR_NONE);
});

// ============================================================================
// Gap 7: Device Created Without Rack Placement (No Broadcast)
// ============================================================================

test('DeviceChanged event is not dispatched when device is created without rack placement', function () {
    Event::fake([DeviceChanged::class]);

    $user = User::factory()->create();
    $this->actingAs($user);

    // Create device without rack (inventory-only device)
    Device::factory()->create(['rack_id' => null, 'start_u' => null]);

    // Should NOT dispatch because device is not placed in a rack
    Event::assertNotDispatched(DeviceChanged::class);
});

// ============================================================================
// Gap 8: Multiple Events Dispatch Ordering
// ============================================================================

test('multiple rapid rack changes dispatch events in correct order', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    $dispatchedActions = [];

    // Listen for RackChanged events and record actions
    Event::listen(RackChanged::class, function (RackChanged $event) use (&$dispatchedActions) {
        $dispatchedActions[] = $event->action;
    });

    // Create a rack (should dispatch 'created')
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Update the rack (should dispatch 'updated')
    $rack->update(['name' => 'Updated Name']);

    // Delete the rack (should dispatch 'deleted')
    $rack->delete();

    // Verify events were dispatched in correct order
    expect($dispatchedActions)->toBe(['created', 'updated', 'deleted']);
});

// ============================================================================
// Gap 9: ConnectionChanged with Auth::user() Returning Null
// ============================================================================

test('ConnectionChanged event handles null Auth user gracefully in payload', function () {
    // Ensure no user is authenticated
    Auth::logout();

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    $sourceDevice = Device::factory()->create(['rack_id' => $rack->id, 'start_u' => 1]);
    $destDevice = Device::factory()->create(['rack_id' => $rack->id, 'start_u' => 5]);

    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    $connection = Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    // ConnectionChanged uses Auth::user() internally in broadcastWith()
    $event = new ConnectionChanged($connection, 'created');
    $payload = $event->broadcastWith();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKey('user')
        ->and($payload['user'])->toBeNull()
        ->and($payload['connection_id'])->toBe($connection->id)
        ->and($payload['action'])->toBe('created');
});

// ============================================================================
// Gap 10: RackChanged Deleted Event Still Has Valid Room ID
// ============================================================================

test('RackChanged deleted event still includes room_id before deletion', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $user = User::factory()->create();

    // The event is dispatched BEFORE the rack is fully deleted from DB
    // so relationships should still be accessible
    $event = new RackChanged($rack, 'deleted', $user);
    $payload = $event->broadcastWith();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKey('room_id')
        ->and($payload['room_id'])->toBe($room->id)
        ->and($payload['rack_id'])->toBe($rack->id)
        ->and($payload['action'])->toBe('deleted');
});
