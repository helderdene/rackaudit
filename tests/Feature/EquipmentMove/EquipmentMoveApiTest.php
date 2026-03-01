<?php

use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Models\Device;
use App\Models\EquipmentMove;
use App\Models\Rack;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create manager user (can approve)
    $this->manager = User::factory()->create();
    $this->manager->assignRole('IT Manager');

    // Create regular user (can create but not approve)
    $this->regularUser = User::factory()->create();
    $this->regularUser->assignRole('Viewer');

    // Create racks
    $this->sourceRack = Rack::factory()->create(['u_height' => 42]);
    $this->destinationRack = Rack::factory()->create(['u_height' => 42]);
});

/**
 * Test 1: POST /equipment-moves creates a move request
 */
test('store creates move request and returns resource', function () {
    $device = Device::factory()->placed($this->sourceRack, 10)->withUHeight(2)->create();

    $response = $this->actingAs($this->regularUser)
        ->postJson('/equipment-moves', [
            'device_id' => $device->id,
            'destination_rack_id' => $this->destinationRack->id,
            'destination_start_u' => 5,
            'destination_rack_face' => DeviceRackFace::Front->value,
            'destination_width_type' => DeviceWidthType::Full->value,
            'operator_notes' => 'Moving for maintenance window',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending_approval')
        ->assertJsonPath('data.device.id', $device->id)
        ->assertJsonPath('data.destination_rack.id', $this->destinationRack->id)
        ->assertJsonPath('data.destination_start_u', 5);

    $this->assertDatabaseHas('equipment_moves', [
        'device_id' => $device->id,
        'destination_rack_id' => $this->destinationRack->id,
        'status' => 'pending_approval',
        'requested_by' => $this->regularUser->id,
    ]);
});

/**
 * Test 2: GET /equipment-moves returns paginated list with filters (JSON endpoint)
 */
test('index returns paginated moves with filters', function () {
    $device = Device::factory()->placed($this->sourceRack, 10)->create();

    // Create moves with different statuses
    EquipmentMove::factory()->forDevice($device)->create([
        'destination_rack_id' => $this->destinationRack->id,
        'status' => 'pending_approval',
        'requested_by' => $this->regularUser->id,
    ]);

    EquipmentMove::factory()->executed()->create([
        'destination_rack_id' => $this->destinationRack->id,
        'requested_by' => $this->manager->id,
    ]);

    // Test index without filters (JSON request)
    $response = $this->actingAs($this->manager)
        ->getJson('/equipment-moves');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'status',
                    'device',
                    'source_rack',
                    'destination_rack',
                    'requester',
                ],
            ],
            'links',
            'meta',
        ]);

    // Test filtering by status
    $response = $this->actingAs($this->manager)
        ->getJson('/equipment-moves?status=pending_approval');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'pending_approval');
});

/**
 * Test 3: GET /equipment-moves/{id} returns move details with relationships
 */
test('show returns move details with all relationships', function () {
    $device = Device::factory()->placed($this->sourceRack, 10)->create();

    $move = EquipmentMove::factory()->forDevice($device)->create([
        'destination_rack_id' => $this->destinationRack->id,
        'destination_start_u' => 15,
        'status' => 'pending_approval',
        'requested_by' => $this->regularUser->id,
        'operator_notes' => 'Test notes',
        'connections_snapshot' => [
            [
                'source_port_label' => 'eth0',
                'destination_port_label' => 'sw-1',
                'cable_type' => 'Cat6',
                'cable_color' => 'blue',
            ],
        ],
    ]);

    $response = $this->actingAs($this->regularUser)
        ->getJson("/equipment-moves/{$move->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $move->id)
        ->assertJsonPath('data.status', 'pending_approval')
        ->assertJsonPath('data.device.id', $device->id)
        ->assertJsonPath('data.source_rack.id', $this->sourceRack->id)
        ->assertJsonPath('data.destination_rack.id', $this->destinationRack->id)
        ->assertJsonPath('data.operator_notes', 'Test notes')
        ->assertJsonPath('data.requester.id', $this->regularUser->id)
        ->assertJsonCount(1, 'data.connections_snapshot');
});

/**
 * Test 4: POST /equipment-moves/{id}/approve approves and executes move
 */
test('approve approves and executes move for managers', function () {
    $device = Device::factory()->placed($this->sourceRack, 10)->withUHeight(2)->create();

    $move = EquipmentMove::factory()->forDevice($device)->create([
        'destination_rack_id' => $this->destinationRack->id,
        'destination_start_u' => 5,
        'destination_rack_face' => DeviceRackFace::Front,
        'destination_width_type' => DeviceWidthType::Full,
        'status' => 'pending_approval',
        'requested_by' => $this->regularUser->id,
        'connections_snapshot' => [],
    ]);

    $response = $this->actingAs($this->manager)
        ->postJson("/equipment-moves/{$move->id}/approve", [
            'approval_notes' => 'Approved for next maintenance window',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'executed')
        ->assertJsonPath('data.approver.id', $this->manager->id);

    // Verify device was moved
    $device->refresh();
    expect($device->rack_id)->toBe($this->destinationRack->id);
    expect($device->start_u)->toBe(5);
});

/**
 * Test 5: POST /equipment-moves/{id}/reject rejects move with required notes
 */
test('reject rejects move with required notes', function () {
    $device = Device::factory()->placed($this->sourceRack, 10)->create();

    $move = EquipmentMove::factory()->forDevice($device)->create([
        'destination_rack_id' => $this->destinationRack->id,
        'status' => 'pending_approval',
        'requested_by' => $this->regularUser->id,
    ]);

    // Attempt rejection without notes should fail
    $response = $this->actingAs($this->manager)
        ->postJson("/equipment-moves/{$move->id}/reject", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['approval_notes']);

    // Rejection with notes should succeed
    $response = $this->actingAs($this->manager)
        ->postJson("/equipment-moves/{$move->id}/reject", [
            'approval_notes' => 'Destination rack is reserved',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.approval_notes', 'Destination rack is reserved');

    // Verify device was NOT moved
    $device->refresh();
    expect($device->rack_id)->toBe($this->sourceRack->id);
});

/**
 * Test 6: POST /equipment-moves/{id}/cancel cancels pending move
 */
test('cancel cancels pending move by requester or manager', function () {
    $device = Device::factory()->placed($this->sourceRack, 10)->create();

    $move = EquipmentMove::factory()->forDevice($device)->create([
        'destination_rack_id' => $this->destinationRack->id,
        'status' => 'pending_approval',
        'requested_by' => $this->regularUser->id,
    ]);

    // Requester can cancel their own move
    $response = $this->actingAs($this->regularUser)
        ->postJson("/equipment-moves/{$move->id}/cancel");

    $response->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    // Verify device was NOT moved
    $device->refresh();
    expect($device->rack_id)->toBe($this->sourceRack->id);
});

/**
 * Test 7: Only managers can approve moves (authorization)
 */
test('only managers can approve moves', function () {
    $device = Device::factory()->placed($this->sourceRack, 10)->create();

    $move = EquipmentMove::factory()->forDevice($device)->create([
        'destination_rack_id' => $this->destinationRack->id,
        'status' => 'pending_approval',
        'requested_by' => $this->regularUser->id,
    ]);

    // Regular user should be forbidden from approving
    $response = $this->actingAs($this->regularUser)
        ->postJson("/equipment-moves/{$move->id}/approve", [
            'approval_notes' => 'Trying to self-approve',
        ]);

    $response->assertForbidden();

    // Move status should remain unchanged
    $move->refresh();
    expect($move->status)->toBe('pending_approval');
});

/**
 * Test 8: Validation errors return proper format
 */
test('validation errors return proper format', function () {
    // Missing required fields
    $response = $this->actingAs($this->regularUser)
        ->postJson('/equipment-moves', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['device_id', 'destination_rack_id', 'destination_start_u', 'destination_rack_face', 'destination_width_type']);

    // Device in pending move
    $device = Device::factory()->placed($this->sourceRack, 10)->create();

    EquipmentMove::factory()->forDevice($device)->create([
        'destination_rack_id' => $this->destinationRack->id,
        'status' => 'pending_approval',
        'requested_by' => $this->regularUser->id,
    ]);

    $response = $this->actingAs($this->regularUser)
        ->postJson('/equipment-moves', [
            'device_id' => $device->id,
            'destination_rack_id' => $this->destinationRack->id,
            'destination_start_u' => 5,
            'destination_rack_face' => DeviceRackFace::Front->value,
            'destination_width_type' => DeviceWidthType::Full->value,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['device_id']);
});
