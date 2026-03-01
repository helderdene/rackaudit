<?php

/**
 * Additional strategic tests to fill identified coverage gaps
 * for the Equipment Move Workflow feature.
 *
 * These tests focus on:
 * - Intra-rack moves (same rack, different U position)
 * - Permission edge cases (view restrictions, manager actions)
 * - State transition edge cases (approve/reject/cancel non-pending)
 * - Device move eligibility after rejected/executed moves
 */

use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Models\Device;
use App\Models\EquipmentMove;
use App\Models\Rack;
use App\Models\User;
use App\Services\EquipmentMoveService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->service = app(EquipmentMoveService::class);

    // Create manager user (can approve)
    $this->manager = User::factory()->create();
    $this->manager->assignRole('IT Manager');

    // Create regular user
    $this->regularUser = User::factory()->create();
    $this->regularUser->assignRole('Viewer');

    // Create rack
    $this->rack = Rack::factory()->create(['u_height' => 42]);
});

/**
 * Test 1: Intra-rack move (same rack, different U position) succeeds
 */
test('intra-rack move succeeds when moving device within same rack to different U position', function () {
    $device = Device::factory()->placed($this->rack, 10)->withUHeight(2)->create([
        'rack_face' => DeviceRackFace::Front,
        'width_type' => DeviceWidthType::Full,
    ]);

    // Create move to different position in same rack
    $response = $this->actingAs($this->regularUser)
        ->postJson('/equipment-moves', [
            'device_id' => $device->id,
            'destination_rack_id' => $this->rack->id, // Same rack
            'destination_start_u' => 25, // Different U position
            'destination_rack_face' => DeviceRackFace::Front->value,
            'destination_width_type' => DeviceWidthType::Full->value,
            'operator_notes' => 'Moving up to make room',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending_approval');

    // Verify source and destination are same rack
    $move = EquipmentMove::first();
    expect($move->source_rack_id)->toBe($this->rack->id);
    expect($move->destination_rack_id)->toBe($this->rack->id);
    expect($move->source_start_u)->toBe(10);
    expect($move->destination_start_u)->toBe(25);
});

/**
 * Test 2: Manager can cancel another user's pending move
 */
test('manager can cancel another user pending move', function () {
    $device = Device::factory()->placed($this->rack, 10)->create();

    $move = EquipmentMove::factory()->forDevice($device)->create([
        'destination_rack_id' => Rack::factory()->create()->id,
        'status' => 'pending_approval',
        'requested_by' => $this->regularUser->id,
    ]);

    // Manager cancels the regular user's move
    $response = $this->actingAs($this->manager)
        ->postJson("/equipment-moves/{$move->id}/cancel");

    $response->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

/**
 * Test 3: Cannot approve already executed move
 */
test('cannot approve already executed move', function () {
    $device = Device::factory()->placed($this->rack, 10)->create();
    $destinationRack = Rack::factory()->create();

    $move = EquipmentMove::factory()->executed($this->manager)->forDevice($device)->create([
        'destination_rack_id' => $destinationRack->id,
        'requested_by' => $this->regularUser->id,
    ]);

    $response = $this->actingAs($this->manager)
        ->postJson("/equipment-moves/{$move->id}/approve", [
            'approval_notes' => 'Trying to re-approve',
        ]);

    $response->assertForbidden();
});

/**
 * Test 4: Cannot reject already rejected move
 */
test('cannot reject already rejected move', function () {
    $device = Device::factory()->placed($this->rack, 10)->create();
    $destinationRack = Rack::factory()->create();

    $move = EquipmentMove::factory()->rejected($this->manager, 'Initial rejection')->forDevice($device)->create([
        'destination_rack_id' => $destinationRack->id,
        'requested_by' => $this->regularUser->id,
    ]);

    $response = $this->actingAs($this->manager)
        ->postJson("/equipment-moves/{$move->id}/reject", [
            'approval_notes' => 'Trying to reject again',
        ]);

    $response->assertForbidden();
});

/**
 * Test 5: Device with rejected move can have new pending move
 */
test('device with rejected move can have new pending move created', function () {
    $device = Device::factory()->placed($this->rack, 10)->withUHeight(2)->create();
    $destinationRack = Rack::factory()->create(['u_height' => 42]);

    // Create and reject a move
    EquipmentMove::factory()->rejected($this->manager)->forDevice($device)->create([
        'destination_rack_id' => $destinationRack->id,
        'requested_by' => $this->regularUser->id,
    ]);

    // Should be able to create a new move for same device
    $response = $this->actingAs($this->regularUser)
        ->postJson('/equipment-moves', [
            'device_id' => $device->id,
            'destination_rack_id' => $destinationRack->id,
            'destination_start_u' => 20,
            'destination_rack_face' => DeviceRackFace::Front->value,
            'destination_width_type' => DeviceWidthType::Full->value,
            'operator_notes' => 'Trying again after rejection',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending_approval');
});

/**
 * Test 6: Non-participant cannot view move details (JSON endpoint)
 */
test('non-participant user cannot view move details via JSON endpoint', function () {
    $device = Device::factory()->placed($this->rack, 10)->create();

    $move = EquipmentMove::factory()->forDevice($device)->create([
        'destination_rack_id' => Rack::factory()->create()->id,
        'status' => 'pending_approval',
        'requested_by' => $this->regularUser->id,
    ]);

    // Create another user who is not a participant
    $otherUser = User::factory()->create();
    $otherUser->assignRole('Viewer');

    // Non-participant should be forbidden from viewing JSON details
    $response = $this->actingAs($otherUser)
        ->getJson("/equipment-moves/{$move->id}");

    $response->assertForbidden();
});

/**
 * Test 7: Cannot cancel already executed move
 */
test('cannot cancel already executed move', function () {
    $device = Device::factory()->placed($this->rack, 10)->create();
    $destinationRack = Rack::factory()->create();

    $move = EquipmentMove::factory()->executed($this->manager)->forDevice($device)->create([
        'destination_rack_id' => $destinationRack->id,
        'requested_by' => $this->regularUser->id,
    ]);

    // Requester tries to cancel executed move
    $response = $this->actingAs($this->regularUser)
        ->postJson("/equipment-moves/{$move->id}/cancel");

    $response->assertForbidden();

    // Manager also cannot cancel executed move
    $response = $this->actingAs($this->manager)
        ->postJson("/equipment-moves/{$move->id}/cancel");

    $response->assertForbidden();
});

/**
 * Test 8: Service validates intra-rack move does not collide with self
 */
test('service allows intra-rack move to destination not occupied by self', function () {
    $device = Device::factory()->placed($this->rack, 10)->withUHeight(2)->create([
        'rack_face' => DeviceRackFace::Front,
        'width_type' => DeviceWidthType::Full,
    ]);

    // Validate that the device can move to a new position within same rack
    $canPlace = $this->service->validateDestinationPosition(
        $this->rack->id,
        25,
        DeviceRackFace::Front,
        DeviceWidthType::Full,
        2,
        $device->id // Exclude self from collision detection
    );

    expect($canPlace)->toBeTrue();

    // Validate that destination 10 (current position) with exclusion should work
    $canPlaceAtCurrent = $this->service->validateDestinationPosition(
        $this->rack->id,
        10,
        DeviceRackFace::Front,
        DeviceWidthType::Full,
        2,
        $device->id // Exclude self - should allow staying in place
    );

    expect($canPlaceAtCurrent)->toBeTrue();
});

/**
 * Test 9: Intra-rack move executes correctly and updates device position
 */
test('intra-rack move executes correctly and updates device position', function () {
    $device = Device::factory()->placed($this->rack, 10)->withUHeight(2)->create([
        'rack_face' => DeviceRackFace::Front,
        'width_type' => DeviceWidthType::Full,
    ]);

    // Create intra-rack move
    $move = EquipmentMove::factory()->forDevice($device)->intraRack($this->rack)->create([
        'destination_start_u' => 25,
        'destination_rack_face' => DeviceRackFace::Front,
        'destination_width_type' => DeviceWidthType::Full,
        'status' => 'pending_approval',
        'requested_by' => $this->regularUser->id,
        'connections_snapshot' => [],
    ]);

    // Approve the move
    $response = $this->actingAs($this->manager)
        ->postJson("/equipment-moves/{$move->id}/approve", [
            'approval_notes' => 'Approved intra-rack move',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'executed');

    // Verify device stayed in same rack but moved to new U position
    $device->refresh();
    expect($device->rack_id)->toBe($this->rack->id);
    expect($device->start_u)->toBe(25);
});

/**
 * Test 10: Device with executed move can have new pending move created
 */
test('device with executed move can have new pending move created', function () {
    $destinationRack = Rack::factory()->create(['u_height' => 42]);
    $device = Device::factory()->placed($destinationRack, 20)->withUHeight(2)->create();

    // Create an executed move (device was previously moved)
    EquipmentMove::factory()->executed($this->manager)->forDevice($device)->create([
        'destination_rack_id' => $destinationRack->id,
        'destination_start_u' => 20,
        'requested_by' => $this->regularUser->id,
    ]);

    // Device is now at destinationRack U20
    // Should be able to create a new move for the same device
    $anotherRack = Rack::factory()->create(['u_height' => 42]);

    $response = $this->actingAs($this->regularUser)
        ->postJson('/equipment-moves', [
            'device_id' => $device->id,
            'destination_rack_id' => $anotherRack->id,
            'destination_start_u' => 5,
            'destination_rack_face' => DeviceRackFace::Front->value,
            'destination_width_type' => DeviceWidthType::Full->value,
            'operator_notes' => 'Moving to another rack after previous move',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending_approval');
});
