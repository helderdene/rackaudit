<?php

use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Models\Datacenter;
use App\Models\Pdu;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use App\Policies\RackPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('viewAny allows all authenticated users', function () {
    $policy = new RackPolicy();

    // Test all roles can view rack list
    $roles = ['Administrator', 'IT Manager', 'Operator', 'Auditor', 'Viewer'];

    foreach ($roles as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);

        expect($policy->viewAny($user))->toBeTrue("Role {$role} should be able to viewAny racks");
    }
});

test('view allows Admins and IT Managers always', function () {
    $policy = new RackPolicy();

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Administrator can always view
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');
    expect($policy->view($admin, $rack))->toBeTrue('Administrator should always be able to view rack');

    // IT Manager can always view
    $itManager = User::factory()->create();
    $itManager->assignRole('IT Manager');
    expect($policy->view($itManager, $rack))->toBeTrue('IT Manager should always be able to view rack');
});

test('view checks datacenter access for non-admin roles', function () {
    $policy = new RackPolicy();

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Operator without datacenter access cannot view
    $operatorNoAccess = User::factory()->create();
    $operatorNoAccess->assignRole('Operator');
    expect($policy->view($operatorNoAccess, $rack))->toBeFalse('Operator without datacenter access should not view rack');

    // Operator with datacenter access can view
    $operatorWithAccess = User::factory()->create();
    $operatorWithAccess->assignRole('Operator');
    $operatorWithAccess->datacenters()->attach($datacenter->id);
    expect($policy->view($operatorWithAccess, $rack))->toBeTrue('Operator with datacenter access should view rack');

    // Viewer with access can view
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $viewer->datacenters()->attach($datacenter->id);
    expect($policy->view($viewer, $rack))->toBeTrue('Viewer with datacenter access should view rack');

    // Auditor without access cannot view
    $auditor = User::factory()->create();
    $auditor->assignRole('Auditor');
    expect($policy->view($auditor, $rack))->toBeFalse('Auditor without datacenter access should not view rack');
});

test('create update delete restricted to Admin and IT Manager', function () {
    $policy = new RackPolicy();

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Administrator can create/update/delete
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');
    expect($policy->create($admin))->toBeTrue();
    expect($policy->update($admin, $rack))->toBeTrue();
    expect($policy->delete($admin, $rack))->toBeTrue();

    // IT Manager can create/update/delete
    $itManager = User::factory()->create();
    $itManager->assignRole('IT Manager');
    expect($policy->create($itManager))->toBeTrue();
    expect($policy->update($itManager, $rack))->toBeTrue();
    expect($policy->delete($itManager, $rack))->toBeTrue();

    // Operator cannot create/update/delete (even with datacenter access)
    $operator = User::factory()->create();
    $operator->assignRole('Operator');
    $operator->datacenters()->attach($datacenter->id);
    expect($policy->create($operator))->toBeFalse();
    expect($policy->update($operator, $rack))->toBeFalse();
    expect($policy->delete($operator, $rack))->toBeFalse();

    // Viewer cannot create/update/delete
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $viewer->datacenters()->attach($datacenter->id);
    expect($policy->create($viewer))->toBeFalse();
    expect($policy->update($viewer, $rack))->toBeFalse();
    expect($policy->delete($viewer, $rack))->toBeFalse();
});

test('StoreRackRequest validation rules work correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $pdu = Pdu::factory()->create(['room_id' => $room->id, 'row_id' => null]);

    // Valid data passes validation
    $this->actingAs($admin)
        ->postJson(route('datacenters.rooms.rows.racks.store', [
            'datacenter' => $datacenter->id,
            'room' => $room->id,
            'row' => $row->id,
        ]), [
            'name' => 'Test Rack',
            'position' => 1,
            'u_height' => RackUHeight::U42->value,
            'status' => RackStatus::Active->value,
            'serial_number' => 'SN-12345',
            'pdu_ids' => [$pdu->id],
        ])
        ->assertRedirect();

    // Missing required fields fail validation
    $this->actingAs($admin)
        ->postJson(route('datacenters.rooms.rows.racks.store', [
            'datacenter' => $datacenter->id,
            'room' => $room->id,
            'row' => $row->id,
        ]), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'position', 'u_height', 'status']);

    // Invalid enum values fail validation
    $this->actingAs($admin)
        ->postJson(route('datacenters.rooms.rows.racks.store', [
            'datacenter' => $datacenter->id,
            'room' => $room->id,
            'row' => $row->id,
        ]), [
            'name' => 'Test Rack',
            'position' => 1,
            'u_height' => 999,
            'status' => 'invalid_status',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['u_height', 'status']);

    // Invalid PDU ID fails validation
    $this->actingAs($admin)
        ->postJson(route('datacenters.rooms.rows.racks.store', [
            'datacenter' => $datacenter->id,
            'room' => $room->id,
            'row' => $row->id,
        ]), [
            'name' => 'Test Rack',
            'position' => 1,
            'u_height' => RackUHeight::U42->value,
            'status' => RackStatus::Active->value,
            'pdu_ids' => [99999],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pdu_ids.0']);
});

test('UpdateRackRequest validation rules work correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $pdu = Pdu::factory()->create(['room_id' => $room->id, 'row_id' => null]);

    // Valid data passes validation
    $this->actingAs($admin)
        ->putJson(route('datacenters.rooms.rows.racks.update', [
            'datacenter' => $datacenter->id,
            'room' => $room->id,
            'row' => $row->id,
            'rack' => $rack->id,
        ]), [
            'name' => 'Updated Rack',
            'position' => 2,
            'u_height' => RackUHeight::U45->value,
            'status' => RackStatus::Maintenance->value,
            'serial_number' => null,
            'pdu_ids' => [$pdu->id],
        ])
        ->assertRedirect();

    // Missing required fields fail validation
    $this->actingAs($admin)
        ->putJson(route('datacenters.rooms.rows.racks.update', [
            'datacenter' => $datacenter->id,
            'room' => $room->id,
            'row' => $row->id,
            'rack' => $rack->id,
        ]), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'position', 'u_height', 'status']);

    // Invalid position type fails
    $this->actingAs($admin)
        ->putJson(route('datacenters.rooms.rows.racks.update', [
            'datacenter' => $datacenter->id,
            'room' => $room->id,
            'row' => $row->id,
            'rack' => $rack->id,
        ]), [
            'name' => 'Test Rack',
            'position' => 'not_a_number',
            'u_height' => RackUHeight::U42->value,
            'status' => RackStatus::Active->value,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['position']);
});
