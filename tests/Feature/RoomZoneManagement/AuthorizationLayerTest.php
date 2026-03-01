<?php

use App\Http\Requests\StorePduRequest;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\StoreRowRequest;
use App\Models\Datacenter;
use App\Models\Pdu;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use App\Policies\PduPolicy;
use App\Policies\RoomPolicy;
use App\Policies\RowPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Test 1: RoomPolicy viewAny allows all authenticated users
 */
test('RoomPolicy viewAny allows all authenticated users', function () {
    $policy = new RoomPolicy;

    // Test with all role types
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $itManager = User::factory()->create();
    $itManager->assignRole('IT Manager');

    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    $auditor = User::factory()->create();
    $auditor->assignRole('Auditor');

    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    expect($policy->viewAny($admin))->toBeTrue();
    expect($policy->viewAny($itManager))->toBeTrue();
    expect($policy->viewAny($operator))->toBeTrue();
    expect($policy->viewAny($auditor))->toBeTrue();
    expect($policy->viewAny($viewer))->toBeTrue();
});

/**
 * Test 2: RoomPolicy view checks parent Datacenter access for non-admin users
 */
test('RoomPolicy view checks parent Datacenter access for non-admin users', function () {
    $policy = new RoomPolicy;

    // Create datacenter and room
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);

    // Admin can view all rooms
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');
    expect($policy->view($admin, $room))->toBeTrue();

    // IT Manager can view all rooms
    $itManager = User::factory()->create();
    $itManager->assignRole('IT Manager');
    expect($policy->view($itManager, $room))->toBeTrue();

    // Operator without datacenter assignment cannot view
    $operator = User::factory()->create();
    $operator->assignRole('Operator');
    expect($policy->view($operator, $room))->toBeFalse();

    // Operator with datacenter assignment can view
    $operator->datacenters()->attach($datacenter->id);
    expect($policy->view($operator, $room))->toBeTrue();

    // Viewer without datacenter assignment cannot view
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    expect($policy->view($viewer, $room))->toBeFalse();

    // Viewer with datacenter assignment can view
    $viewer->datacenters()->attach($datacenter->id);
    expect($policy->view($viewer, $room))->toBeTrue();
});

/**
 * Test 3: RoomPolicy create/update/delete restricted to Administrator and IT Manager
 */
test('RoomPolicy create update delete restricted to Administrator and IT Manager', function () {
    $policy = new RoomPolicy;

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);

    // Administrator can create, update, delete
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');
    expect($policy->create($admin))->toBeTrue();
    expect($policy->update($admin, $room))->toBeTrue();
    expect($policy->delete($admin, $room))->toBeTrue();

    // IT Manager can create, update, delete
    $itManager = User::factory()->create();
    $itManager->assignRole('IT Manager');
    expect($policy->create($itManager))->toBeTrue();
    expect($policy->update($itManager, $room))->toBeTrue();
    expect($policy->delete($itManager, $room))->toBeTrue();

    // Operator cannot create, update, delete (even if assigned)
    $operator = User::factory()->create();
    $operator->assignRole('Operator');
    $operator->datacenters()->attach($datacenter->id);
    expect($policy->create($operator))->toBeFalse();
    expect($policy->update($operator, $room))->toBeFalse();
    expect($policy->delete($operator, $room))->toBeFalse();

    // Viewer cannot create, update, delete
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $viewer->datacenters()->attach($datacenter->id);
    expect($policy->create($viewer))->toBeFalse();
    expect($policy->update($viewer, $room))->toBeFalse();
    expect($policy->delete($viewer, $room))->toBeFalse();
});

/**
 * Test 4: RowPolicy inherits authorization from parent Room
 */
test('RowPolicy inherits authorization from parent Room', function () {
    $policy = new RowPolicy;

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    // Admin can view/create/update/delete rows
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');
    expect($policy->viewAny($admin))->toBeTrue();
    expect($policy->view($admin, $row))->toBeTrue();
    expect($policy->create($admin))->toBeTrue();
    expect($policy->update($admin, $row))->toBeTrue();
    expect($policy->delete($admin, $row))->toBeTrue();

    // Operator with datacenter assignment can view but not modify
    $operator = User::factory()->create();
    $operator->assignRole('Operator');
    $operator->datacenters()->attach($datacenter->id);
    expect($policy->viewAny($operator))->toBeTrue();
    expect($policy->view($operator, $row))->toBeTrue();
    expect($policy->create($operator))->toBeFalse();
    expect($policy->update($operator, $row))->toBeFalse();
    expect($policy->delete($operator, $row))->toBeFalse();

    // Operator without assignment cannot view
    $unassignedOperator = User::factory()->create();
    $unassignedOperator->assignRole('Operator');
    expect($policy->view($unassignedOperator, $row))->toBeFalse();
});

/**
 * Test 5: PduPolicy inherits authorization from parent Room
 */
test('PduPolicy inherits authorization from parent Room', function () {
    $policy = new PduPolicy;

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    // Room-level PDU
    $roomLevelPdu = Pdu::factory()->create([
        'room_id' => $room->id,
        'row_id' => null,
    ]);

    // Row-level PDU
    $rowLevelPdu = Pdu::factory()->create([
        'room_id' => null,
        'row_id' => $row->id,
    ]);

    // Admin can view/create/update/delete PDUs
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');
    expect($policy->viewAny($admin))->toBeTrue();
    expect($policy->view($admin, $roomLevelPdu))->toBeTrue();
    expect($policy->view($admin, $rowLevelPdu))->toBeTrue();
    expect($policy->create($admin))->toBeTrue();
    expect($policy->update($admin, $roomLevelPdu))->toBeTrue();
    expect($policy->update($admin, $rowLevelPdu))->toBeTrue();
    expect($policy->delete($admin, $roomLevelPdu))->toBeTrue();
    expect($policy->delete($admin, $rowLevelPdu))->toBeTrue();

    // Operator with datacenter assignment can view but not modify
    $operator = User::factory()->create();
    $operator->assignRole('Operator');
    $operator->datacenters()->attach($datacenter->id);
    expect($policy->viewAny($operator))->toBeTrue();
    expect($policy->view($operator, $roomLevelPdu))->toBeTrue();
    expect($policy->view($operator, $rowLevelPdu))->toBeTrue();
    expect($policy->create($operator))->toBeFalse();
    expect($policy->update($operator, $roomLevelPdu))->toBeFalse();
    expect($policy->update($operator, $rowLevelPdu))->toBeFalse();
    expect($policy->delete($operator, $roomLevelPdu))->toBeFalse();
    expect($policy->delete($operator, $rowLevelPdu))->toBeFalse();

    // Operator without assignment cannot view
    $unassignedOperator = User::factory()->create();
    $unassignedOperator->assignRole('Operator');
    expect($policy->view($unassignedOperator, $roomLevelPdu))->toBeFalse();
    expect($policy->view($unassignedOperator, $rowLevelPdu))->toBeFalse();
});

/**
 * Test 6: Form Request authorization rejects unauthorized users
 */
test('Form Request authorization rejects unauthorized users', function () {
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);

    // Create users with different roles
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    // Test StoreRoomRequest authorization
    $storeRoomRequest = new StoreRoomRequest;
    $storeRoomRequest->setUserResolver(fn () => $admin);
    expect($storeRoomRequest->authorize())->toBeTrue();

    $storeRoomRequest->setUserResolver(fn () => $operator);
    expect($storeRoomRequest->authorize())->toBeFalse();

    // Test StoreRowRequest authorization
    $storeRowRequest = new StoreRowRequest;
    $storeRowRequest->setUserResolver(fn () => $admin);
    expect($storeRowRequest->authorize())->toBeTrue();

    $storeRowRequest->setUserResolver(fn () => $operator);
    expect($storeRowRequest->authorize())->toBeFalse();

    // Test StorePduRequest authorization
    $storePduRequest = new StorePduRequest;
    $storePduRequest->setUserResolver(fn () => $admin);
    expect($storePduRequest->authorize())->toBeTrue();

    $storePduRequest->setUserResolver(fn () => $operator);
    expect($storePduRequest->authorize())->toBeFalse();
});
