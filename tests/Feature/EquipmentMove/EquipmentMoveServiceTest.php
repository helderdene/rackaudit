<?php

use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Models\Connection;
use App\Models\Device;
use App\Models\EquipmentMove;
use App\Models\Port;
use App\Models\Rack;
use App\Models\User;
use App\Services\EquipmentMoveService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(EquipmentMoveService::class);
});

test('createMoveRequest creates move with connections snapshot captured', function () {
    $user = User::factory()->create();
    $sourceRack = Rack::factory()->create();
    $destinationRack = Rack::factory()->create();
    $device = Device::factory()->placed($sourceRack, 10)->withUHeight(2)->create();

    // Create ports on the device
    $port1 = Port::factory()->ethernet()->create(['device_id' => $device->id, 'label' => 'eth0']);
    $port2 = Port::factory()->ethernet()->create(['device_id' => $device->id, 'label' => 'eth1']);

    // Create connections to those ports
    $switchDevice = Device::factory()->create(['name' => 'Core Switch 01']);
    $switchPort1 = Port::factory()->ethernet()->create(['device_id' => $switchDevice->id, 'label' => 'sw-port-1']);
    $switchPort2 = Port::factory()->ethernet()->create(['device_id' => $switchDevice->id, 'label' => 'sw-port-2']);

    Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $switchPort1->id,
        'cable_color' => 'blue',
    ]);

    Connection::factory()->create([
        'source_port_id' => $port2->id,
        'destination_port_id' => $switchPort2->id,
        'cable_color' => 'yellow',
    ]);

    $destinationData = [
        'rack_id' => $destinationRack->id,
        'start_u' => 5,
        'rack_face' => DeviceRackFace::Front,
        'width_type' => DeviceWidthType::Full,
    ];

    $move = $this->service->createMoveRequest($device, $destinationData, $user, 'Test move notes');

    expect($move)->toBeInstanceOf(EquipmentMove::class);
    expect($move->status)->toBe('pending_approval');
    expect($move->device_id)->toBe($device->id);
    expect($move->source_rack_id)->toBe($sourceRack->id);
    expect($move->destination_rack_id)->toBe($destinationRack->id);
    expect($move->destination_start_u)->toBe(5);
    expect($move->requested_by)->toBe($user->id);
    expect($move->operator_notes)->toBe('Test move notes');

    // Check connections snapshot was captured
    expect($move->connections_snapshot)->toBeArray();
    expect($move->connections_snapshot)->toHaveCount(2);
});

test('checkDeviceHasPendingMove returns true when device has pending move', function () {
    $sourceRack = Rack::factory()->create();
    $destinationRack = Rack::factory()->create();
    $device = Device::factory()->placed($sourceRack, 10)->create();

    // Create a pending move for the device
    EquipmentMove::factory()->create([
        'device_id' => $device->id,
        'source_rack_id' => $sourceRack->id,
        'destination_rack_id' => $destinationRack->id,
        'status' => 'pending_approval',
    ]);

    expect($this->service->checkDeviceHasPendingMove($device))->toBeTrue();
});

test('checkDeviceHasPendingMove returns false when device has no pending move', function () {
    $sourceRack = Rack::factory()->create();
    $device = Device::factory()->placed($sourceRack, 10)->create();

    expect($this->service->checkDeviceHasPendingMove($device))->toBeFalse();

    // Create an executed move (not pending)
    EquipmentMove::factory()->executed()->create([
        'device_id' => $device->id,
    ]);

    expect($this->service->checkDeviceHasPendingMove($device))->toBeFalse();
});

test('validateDestinationPosition detects collision with existing devices', function () {
    $rack = Rack::factory()->create(['u_height' => 42]);

    // Place a device at U10-U12 (3U device)
    Device::factory()->placed($rack, 10)->withUHeight(3)->create();

    // Full width device should collide at U10
    expect($this->service->validateDestinationPosition(
        $rack->id,
        10,
        DeviceRackFace::Front,
        DeviceWidthType::Full,
        2
    ))->toBeFalse();

    // Should be able to place at U15
    expect($this->service->validateDestinationPosition(
        $rack->id,
        15,
        DeviceRackFace::Front,
        DeviceWidthType::Full,
        2
    ))->toBeTrue();

    // Should be able to place at rear (different face)
    expect($this->service->validateDestinationPosition(
        $rack->id,
        10,
        DeviceRackFace::Rear,
        DeviceWidthType::Full,
        2
    ))->toBeTrue();
});

test('validateDestinationPosition handles half-width devices correctly', function () {
    $rack = Rack::factory()->create(['u_height' => 42]);

    // Place a half-left device at U10
    Device::factory()->placed($rack, 10)->withUHeight(1)->halfLeft()->create();

    // Half-left should collide
    expect($this->service->validateDestinationPosition(
        $rack->id,
        10,
        DeviceRackFace::Front,
        DeviceWidthType::HalfLeft,
        1
    ))->toBeFalse();

    // Half-right should NOT collide (opposite side)
    expect($this->service->validateDestinationPosition(
        $rack->id,
        10,
        DeviceRackFace::Front,
        DeviceWidthType::HalfRight,
        1
    ))->toBeTrue();

    // Full width should collide
    expect($this->service->validateDestinationPosition(
        $rack->id,
        10,
        DeviceRackFace::Front,
        DeviceWidthType::Full,
        1
    ))->toBeFalse();
});

test('approveMove updates status and executes the move', function () {
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $sourceRack = Rack::factory()->create();
    $destinationRack = Rack::factory()->create();
    $device = Device::factory()->placed($sourceRack, 10)->withUHeight(2)->create();

    $move = EquipmentMove::factory()->create([
        'device_id' => $device->id,
        'source_rack_id' => $sourceRack->id,
        'source_start_u' => 10,
        'source_rack_face' => DeviceRackFace::Front,
        'source_width_type' => DeviceWidthType::Full,
        'destination_rack_id' => $destinationRack->id,
        'destination_start_u' => 5,
        'destination_rack_face' => DeviceRackFace::Front,
        'destination_width_type' => DeviceWidthType::Full,
        'status' => 'pending_approval',
        'requested_by' => $requester->id,
        'connections_snapshot' => [],
    ]);

    $result = $this->service->approveMove($move, $approver, 'Approved for execution');

    expect($result)->toBeTrue();

    $move->refresh();
    expect($move->status)->toBe('executed');
    expect($move->approved_by)->toBe($approver->id);
    expect($move->approval_notes)->toBe('Approved for execution');
    expect($move->approved_at)->not->toBeNull();
    expect($move->executed_at)->not->toBeNull();

    // Verify device was moved
    $device->refresh();
    expect($device->rack_id)->toBe($destinationRack->id);
    expect($device->start_u)->toBe(5);
});

test('rejectMove updates status with rejection notes', function () {
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $sourceRack = Rack::factory()->create();
    $device = Device::factory()->placed($sourceRack, 10)->create();

    $move = EquipmentMove::factory()->create([
        'device_id' => $device->id,
        'source_rack_id' => $sourceRack->id,
        'status' => 'pending_approval',
        'requested_by' => $requester->id,
    ]);

    $result = $this->service->rejectMove($move, $approver, 'Destination rack is reserved for other equipment');

    expect($result)->toBeTrue();

    $move->refresh();
    expect($move->status)->toBe('rejected');
    expect($move->approved_by)->toBe($approver->id);
    expect($move->approval_notes)->toBe('Destination rack is reserved for other equipment');
    expect($move->approved_at)->not->toBeNull();
    expect($move->executed_at)->toBeNull();

    // Verify device was NOT moved
    $device->refresh();
    expect($device->rack_id)->toBe($sourceRack->id);
    expect($device->start_u)->toBe(10);
});

test('executeMove disconnects all device connections with soft delete', function () {
    $user = User::factory()->create();
    $sourceRack = Rack::factory()->create();
    $destinationRack = Rack::factory()->create();
    $device = Device::factory()->placed($sourceRack, 10)->create();

    // Create ports and connections
    $port1 = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    $port2 = Port::factory()->ethernet()->create(['device_id' => $device->id]);

    $switchDevice = Device::factory()->create();
    $switchPort1 = Port::factory()->ethernet()->create(['device_id' => $switchDevice->id]);
    $switchPort2 = Port::factory()->ethernet()->create(['device_id' => $switchDevice->id]);

    $connection1 = Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $switchPort1->id,
    ]);

    $connection2 = Connection::factory()->create([
        'source_port_id' => $port2->id,
        'destination_port_id' => $switchPort2->id,
    ]);

    // Capture the snapshot before the move
    $snapshot = $this->service->captureConnectionsSnapshot($device);

    $move = EquipmentMove::factory()->approved()->create([
        'device_id' => $device->id,
        'source_rack_id' => $sourceRack->id,
        'destination_rack_id' => $destinationRack->id,
        'destination_start_u' => 5,
        'destination_rack_face' => DeviceRackFace::Front,
        'destination_width_type' => DeviceWidthType::Full,
        'connections_snapshot' => $snapshot,
    ]);

    $result = $this->service->executeMove($move);

    expect($result)->toBeTrue();

    // Connections should be soft deleted
    expect(Connection::find($connection1->id))->toBeNull();
    expect(Connection::find($connection2->id))->toBeNull();

    // But should exist with trashed
    expect(Connection::withTrashed()->find($connection1->id))->not->toBeNull();
    expect(Connection::withTrashed()->find($connection2->id))->not->toBeNull();

    // Device should be at new location
    $device->refresh();
    expect($device->rack_id)->toBe($destinationRack->id);
    expect($device->start_u)->toBe(5);

    // Move should be marked as executed
    $move->refresh();
    expect($move->status)->toBe('executed');
    expect($move->executed_at)->not->toBeNull();
});

test('cancelMove cancels pending move and preserves device location', function () {
    $user = User::factory()->create();
    $sourceRack = Rack::factory()->create();
    $destinationRack = Rack::factory()->create();
    $device = Device::factory()->placed($sourceRack, 10)->create();

    $move = EquipmentMove::factory()->create([
        'device_id' => $device->id,
        'source_rack_id' => $sourceRack->id,
        'destination_rack_id' => $destinationRack->id,
        'status' => 'pending_approval',
        'requested_by' => $user->id,
    ]);

    $result = $this->service->cancelMove($move, $user);

    expect($result)->toBeTrue();

    $move->refresh();
    expect($move->status)->toBe('cancelled');

    // Device should remain at original location
    $device->refresh();
    expect($device->rack_id)->toBe($sourceRack->id);
    expect($device->start_u)->toBe(10);
});
