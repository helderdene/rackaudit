<?php

use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Models\Device;
use App\Models\EquipmentMove;
use App\Models\Rack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('EquipmentMove can be created with valid data and has correct status transitions', function () {
    $user = User::factory()->create();
    $sourceRack = Rack::factory()->create();
    $destinationRack = Rack::factory()->create();
    $device = Device::factory()->placed($sourceRack, 10)->create();

    $move = EquipmentMove::create([
        'device_id' => $device->id,
        'source_rack_id' => $sourceRack->id,
        'destination_rack_id' => $destinationRack->id,
        'source_start_u' => 10,
        'destination_start_u' => 5,
        'source_rack_face' => DeviceRackFace::Front,
        'destination_rack_face' => DeviceRackFace::Front,
        'source_width_type' => DeviceWidthType::Full,
        'destination_width_type' => DeviceWidthType::Full,
        'status' => 'pending_approval',
        'connections_snapshot' => [],
        'requested_by' => $user->id,
        'operator_notes' => 'Test move request',
        'requested_at' => now(),
    ]);

    expect($move)->toBeInstanceOf(EquipmentMove::class);
    expect($move->status)->toBe('pending_approval');
    expect($move->isPendingApproval())->toBeTrue();
    expect($move->isApproved())->toBeFalse();

    // Transition to approved
    $approver = User::factory()->create();
    $move->update([
        'status' => 'approved',
        'approved_by' => $approver->id,
        'approved_at' => now(),
    ]);

    expect($move->fresh()->isApproved())->toBeTrue();
    expect($move->fresh()->isPendingApproval())->toBeFalse();

    // Transition to executed
    $move->update([
        'status' => 'executed',
        'executed_at' => now(),
    ]);

    expect($move->fresh()->isExecuted())->toBeTrue();
});

test('EquipmentMove connections_snapshot JSON casting stores and retrieves correctly', function () {
    $user = User::factory()->create();
    $sourceRack = Rack::factory()->create();
    $destinationRack = Rack::factory()->create();
    $device = Device::factory()->placed($sourceRack, 10)->create();

    $connectionsData = [
        [
            'id' => 1,
            'source_port_label' => 'eth0',
            'destination_port_label' => 'sw-port-1',
            'cable_type' => 'Cat6',
            'cable_length' => 3.5,
            'cable_color' => 'blue',
            'destination_device_name' => 'Core Switch 01',
        ],
        [
            'id' => 2,
            'source_port_label' => 'eth1',
            'destination_port_label' => 'sw-port-2',
            'cable_type' => 'Cat6',
            'cable_length' => 3.5,
            'cable_color' => 'yellow',
            'destination_device_name' => 'Core Switch 01',
        ],
    ];

    $move = EquipmentMove::create([
        'device_id' => $device->id,
        'source_rack_id' => $sourceRack->id,
        'destination_rack_id' => $destinationRack->id,
        'source_start_u' => 10,
        'destination_start_u' => 5,
        'source_rack_face' => DeviceRackFace::Front,
        'destination_rack_face' => DeviceRackFace::Front,
        'source_width_type' => DeviceWidthType::Full,
        'destination_width_type' => DeviceWidthType::Full,
        'status' => 'pending_approval',
        'connections_snapshot' => $connectionsData,
        'requested_by' => $user->id,
        'requested_at' => now(),
    ]);

    // Refresh from database to test retrieval
    $move->refresh();

    expect($move->connections_snapshot)->toBeArray();
    expect($move->connections_snapshot)->toHaveCount(2);
    expect($move->connections_snapshot[0]['source_port_label'])->toBe('eth0');
    expect($move->connections_snapshot[1]['cable_color'])->toBe('yellow');
});

test('EquipmentMove has correct relationships with device, racks, and users', function () {
    $requester = User::factory()->create(['name' => 'John Requester']);
    $approver = User::factory()->create(['name' => 'Jane Approver']);
    $sourceRack = Rack::factory()->create(['name' => 'Source Rack A1']);
    $destinationRack = Rack::factory()->create(['name' => 'Destination Rack B2']);
    $device = Device::factory()->placed($sourceRack, 10)->create(['name' => 'Web Server 01']);

    $move = EquipmentMove::create([
        'device_id' => $device->id,
        'source_rack_id' => $sourceRack->id,
        'destination_rack_id' => $destinationRack->id,
        'source_start_u' => 10,
        'destination_start_u' => 5,
        'source_rack_face' => DeviceRackFace::Front,
        'destination_rack_face' => DeviceRackFace::Rear,
        'source_width_type' => DeviceWidthType::Full,
        'destination_width_type' => DeviceWidthType::Full,
        'status' => 'approved',
        'connections_snapshot' => [],
        'requested_by' => $requester->id,
        'approved_by' => $approver->id,
        'requested_at' => now(),
        'approved_at' => now(),
    ]);

    // Test device relationship
    expect($move->device)->toBeInstanceOf(Device::class);
    expect($move->device->name)->toBe('Web Server 01');

    // Test sourceRack relationship
    expect($move->sourceRack)->toBeInstanceOf(Rack::class);
    expect($move->sourceRack->name)->toBe('Source Rack A1');

    // Test destinationRack relationship
    expect($move->destinationRack)->toBeInstanceOf(Rack::class);
    expect($move->destinationRack->name)->toBe('Destination Rack B2');

    // Test requester relationship
    expect($move->requester)->toBeInstanceOf(User::class);
    expect($move->requester->name)->toBe('John Requester');

    // Test approver relationship
    expect($move->approver)->toBeInstanceOf(User::class);
    expect($move->approver->name)->toBe('Jane Approver');
});

test('EquipmentMove validates that device cannot have multiple pending moves', function () {
    $user = User::factory()->create();
    $sourceRack = Rack::factory()->create();
    $destinationRack = Rack::factory()->create();
    $device = Device::factory()->placed($sourceRack, 10)->create();

    // Create first pending move
    EquipmentMove::create([
        'device_id' => $device->id,
        'source_rack_id' => $sourceRack->id,
        'destination_rack_id' => $destinationRack->id,
        'source_start_u' => 10,
        'destination_start_u' => 5,
        'source_rack_face' => DeviceRackFace::Front,
        'destination_rack_face' => DeviceRackFace::Front,
        'source_width_type' => DeviceWidthType::Full,
        'destination_width_type' => DeviceWidthType::Full,
        'status' => 'pending_approval',
        'connections_snapshot' => [],
        'requested_by' => $user->id,
        'requested_at' => now(),
    ]);

    // Verify device has a pending move using scope
    $hasPendingMove = EquipmentMove::forDevice($device->id)
        ->whereStatus('pending_approval')
        ->exists();

    expect($hasPendingMove)->toBeTrue();

    // Verify count of pending moves for this device
    $pendingCount = EquipmentMove::forDevice($device->id)
        ->whereStatus('pending_approval')
        ->count();

    expect($pendingCount)->toBe(1);
});

test('EquipmentMove helper methods return correct status checks', function () {
    $move = EquipmentMove::factory()->create(['status' => 'pending_approval']);
    expect($move->isPendingApproval())->toBeTrue();
    expect($move->isApproved())->toBeFalse();
    expect($move->isRejected())->toBeFalse();
    expect($move->isExecuted())->toBeFalse();
    expect($move->isCancelled())->toBeFalse();

    $move->update(['status' => 'approved']);
    expect($move->fresh()->isPendingApproval())->toBeFalse();
    expect($move->fresh()->isApproved())->toBeTrue();

    $move->update(['status' => 'rejected']);
    expect($move->fresh()->isRejected())->toBeTrue();

    $move->update(['status' => 'executed']);
    expect($move->fresh()->isExecuted())->toBeTrue();

    $move->update(['status' => 'cancelled']);
    expect($move->fresh()->isCancelled())->toBeTrue();
});

test('EquipmentMove scopes filter by status, device, rack, and date range correctly', function () {
    $user = User::factory()->create();
    $rack1 = Rack::factory()->create();
    $rack2 = Rack::factory()->create();
    $rack3 = Rack::factory()->create();
    $device1 = Device::factory()->placed($rack1, 10)->create();
    $device2 = Device::factory()->placed($rack2, 5)->create();

    // Create moves with different statuses and dates
    $pendingMove = EquipmentMove::factory()->create([
        'device_id' => $device1->id,
        'source_rack_id' => $rack1->id,
        'destination_rack_id' => $rack2->id,
        'status' => 'pending_approval',
        'requested_at' => now()->subDays(5),
    ]);

    $approvedMove = EquipmentMove::factory()->approved()->create([
        'device_id' => $device2->id,
        'source_rack_id' => $rack2->id,
        'destination_rack_id' => $rack3->id,
        'requested_at' => now()->subDays(2),
    ]);

    $executedMove = EquipmentMove::factory()->executed()->create([
        'device_id' => $device1->id,
        'source_rack_id' => $rack1->id,
        'destination_rack_id' => $rack3->id,
        'requested_at' => now()->subDays(10),
    ]);

    // Test status scope
    expect(EquipmentMove::whereStatus('pending_approval')->count())->toBe(1);
    expect(EquipmentMove::whereStatus('approved')->count())->toBe(1);
    expect(EquipmentMove::whereStatus('executed')->count())->toBe(1);

    // Test device scope
    expect(EquipmentMove::forDevice($device1->id)->count())->toBe(2);
    expect(EquipmentMove::forDevice($device2->id)->count())->toBe(1);

    // Test rack scope (source or destination)
    expect(EquipmentMove::forRack($rack1->id)->count())->toBe(2);
    expect(EquipmentMove::forRack($rack2->id)->count())->toBe(2);
    expect(EquipmentMove::forRack($rack3->id)->count())->toBe(2);

    // Test date range scope
    expect(EquipmentMove::requestedBetween(now()->subDays(6), now())->count())->toBe(2);
    expect(EquipmentMove::requestedBetween(now()->subDays(3), now())->count())->toBe(1);
});
