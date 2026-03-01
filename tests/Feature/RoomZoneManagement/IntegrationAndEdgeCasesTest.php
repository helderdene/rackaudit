<?php

use App\Enums\PduPhase;
use App\Enums\PduStatus;
use App\Enums\RoomType;
use App\Enums\RowOrientation;
use App\Enums\RowStatus;
use App\Models\ActivityLog;
use App\Models\Datacenter;
use App\Models\Pdu;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
});

/**
 * Test 1: E2E workflow - Create room, add rows, add PDUs
 *
 * Tests the complete user workflow of creating a room, adding rows to it,
 * and adding PDUs at both room and row levels.
 */
test('E2E create room add rows and add PDUs workflow', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create(['name' => 'Main DC']);

    // Step 1: Create a room
    $roomData = [
        'name' => 'Production Server Room',
        'description' => 'Main production room',
        'square_footage' => 5000.00,
        'type' => RoomType::ServerRoom->value,
    ];

    $response = $this->actingAs($admin)
        ->post("/datacenters/{$datacenter->id}/rooms", $roomData);

    $response->assertRedirect("/datacenters/{$datacenter->id}");

    $room = Room::where('name', 'Production Server Room')->first();
    expect($room)->not->toBeNull();
    expect($room->datacenter_id)->toBe($datacenter->id);

    // Step 2: Add rows to the room
    $row1Data = [
        'name' => 'Row A',
        'position' => 1,
        'orientation' => RowOrientation::HotAisle->value,
        'status' => RowStatus::Active->value,
    ];

    $response = $this->actingAs($admin)
        ->post("/datacenters/{$datacenter->id}/rooms/{$room->id}/rows", $row1Data);

    $response->assertRedirect("/datacenters/{$datacenter->id}/rooms/{$room->id}");

    $row2Data = [
        'name' => 'Row B',
        'position' => 2,
        'orientation' => RowOrientation::ColdAisle->value,
        'status' => RowStatus::Active->value,
    ];

    $response = $this->actingAs($admin)
        ->post("/datacenters/{$datacenter->id}/rooms/{$room->id}/rows", $row2Data);

    $response->assertRedirect("/datacenters/{$datacenter->id}/rooms/{$room->id}");

    $rows = Row::where('room_id', $room->id)->orderBy('position')->get();
    expect($rows)->toHaveCount(2);
    expect($rows[0]->name)->toBe('Row A');
    expect($rows[1]->name)->toBe('Row B');

    // Step 3: Add room-level PDU
    $roomLevelPdu = [
        'name' => 'PDU-ROOM-001',
        'model' => 'APC AP8941',
        'manufacturer' => 'APC',
        'total_capacity_kw' => 17.3,
        'voltage' => 208,
        'phase' => PduPhase::ThreePhase->value,
        'circuit_count' => 42,
        'status' => PduStatus::Active->value,
    ];

    $response = $this->actingAs($admin)
        ->post("/datacenters/{$datacenter->id}/rooms/{$room->id}/pdus", $roomLevelPdu);

    $response->assertRedirect("/datacenters/{$datacenter->id}/rooms/{$room->id}");

    $pduRoom = Pdu::where('name', 'PDU-ROOM-001')->first();
    expect($pduRoom)->not->toBeNull();
    expect($pduRoom->isRoomLevel())->toBeTrue();

    // Step 4: Add row-level PDU
    $rowLevelPdu = [
        'name' => 'PDU-ROW-001',
        'model' => 'Eaton G3',
        'manufacturer' => 'Eaton',
        'total_capacity_kw' => 22.5,
        'voltage' => 480,
        'phase' => PduPhase::Single->value,
        'circuit_count' => 24,
        'status' => PduStatus::Active->value,
        'row_id' => $rows[0]->id,
    ];

    $response = $this->actingAs($admin)
        ->post("/datacenters/{$datacenter->id}/rooms/{$room->id}/pdus", $rowLevelPdu);

    $response->assertRedirect("/datacenters/{$datacenter->id}/rooms/{$room->id}");

    $pduRow = Pdu::where('name', 'PDU-ROW-001')->first();
    expect($pduRow)->not->toBeNull();
    expect($pduRow->isRowLevel())->toBeTrue();
    expect($pduRow->row_id)->toBe($rows[0]->id);
});

/**
 * Test 2: Room deletion cascades to rows and orphans PDUs
 *
 * When a room is deleted:
 * - Rows are cascade deleted (foreign key constraint)
 * - PDUs are orphaned (room_id and row_id set to null via nullOnDelete)
 *
 * This is the intended database design to preserve PDU data even when
 * the organizational structure changes.
 */
test('room deletion cascades to rows and orphans PDUs', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);

    // Create rows
    $row1 = Row::factory()->create(['room_id' => $room->id, 'position' => 1]);
    $row2 = Row::factory()->create(['room_id' => $room->id, 'position' => 2]);

    // Create PDUs - room-level and row-level
    $roomPdu = Pdu::factory()->create([
        'name' => 'Room PDU',
        'room_id' => $room->id,
        'row_id' => null,
    ]);
    $rowPdu1 = Pdu::factory()->create([
        'name' => 'Row 1 PDU',
        'room_id' => null,
        'row_id' => $row1->id,
    ]);
    $rowPdu2 = Pdu::factory()->create([
        'name' => 'Row 2 PDU',
        'room_id' => null,
        'row_id' => $row2->id,
    ]);

    // Verify initial state
    expect(Room::find($room->id))->not->toBeNull();
    expect(Row::count())->toBe(2);
    expect(Pdu::count())->toBe(3);

    // Delete the room
    $response = $this->actingAs($admin)
        ->delete("/datacenters/{$datacenter->id}/rooms/{$room->id}");

    $response->assertRedirect("/datacenters/{$datacenter->id}");

    // Verify room and rows are deleted
    expect(Room::find($room->id))->toBeNull();
    expect(Row::whereIn('id', [$row1->id, $row2->id])->count())->toBe(0);

    // PDUs are orphaned (not deleted) - foreign keys set to null
    $orphanedPdus = Pdu::whereIn('id', [$roomPdu->id, $rowPdu1->id, $rowPdu2->id])->get();
    expect($orphanedPdus)->toHaveCount(3);

    // Verify all PDUs are now orphaned (both room_id and row_id are null)
    foreach ($orphanedPdus as $pdu) {
        expect($pdu->room_id)->toBeNull();
        expect($pdu->row_id)->toBeNull();
    }
});

/**
 * Test 3: Row deletion with PDUs handles gracefully (PDUs reassigned to room level)
 *
 * When a row is deleted via the controller, its PDUs should be reassigned to room level
 * rather than being orphaned. This is handled in the RowController::destroy method.
 */
test('row deletion reassigns PDUs to room level gracefully', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);

    $row = Row::factory()->create(['room_id' => $room->id, 'position' => 1]);

    // Create row-level PDUs
    $pdu1 = Pdu::factory()->create([
        'name' => 'PDU-1',
        'room_id' => null,
        'row_id' => $row->id,
    ]);
    $pdu2 = Pdu::factory()->create([
        'name' => 'PDU-2',
        'room_id' => null,
        'row_id' => $row->id,
    ]);

    // Verify PDUs are row-level initially
    expect($pdu1->fresh()->isRowLevel())->toBeTrue();
    expect($pdu2->fresh()->isRowLevel())->toBeTrue();

    // Delete the row
    $response = $this->actingAs($admin)
        ->delete("/datacenters/{$datacenter->id}/rooms/{$room->id}/rows/{$row->id}");

    $response->assertRedirect("/datacenters/{$datacenter->id}/rooms/{$room->id}");

    // Verify row is deleted
    expect(Row::find($row->id))->toBeNull();

    // Verify PDUs are now room-level (not deleted)
    $pdu1Fresh = Pdu::find($pdu1->id);
    $pdu2Fresh = Pdu::find($pdu2->id);

    expect($pdu1Fresh)->not->toBeNull();
    expect($pdu2Fresh)->not->toBeNull();
    expect($pdu1Fresh->isRoomLevel())->toBeTrue();
    expect($pdu2Fresh->isRoomLevel())->toBeTrue();
    expect($pdu1Fresh->room_id)->toBe($room->id);
    expect($pdu2Fresh->room_id)->toBe($room->id);
});

/**
 * Test 4: PDU reassignment from room level to row level
 *
 * Tests updating a PDU to change its assignment from room-level to row-level
 * and vice versa.
 */
test('PDU reassignment from room level to row level', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id, 'position' => 1]);

    // Create a room-level PDU
    $pdu = Pdu::factory()->create([
        'name' => 'Reassignable PDU',
        'room_id' => $room->id,
        'row_id' => null,
        'phase' => PduPhase::Single,
        'circuit_count' => 24,
        'status' => PduStatus::Active,
    ]);

    expect($pdu->isRoomLevel())->toBeTrue();

    // Update PDU to be row-level
    $updateData = [
        'name' => 'Reassignable PDU',
        'phase' => PduPhase::Single->value,
        'circuit_count' => 24,
        'status' => PduStatus::Active->value,
        'row_id' => $row->id,
    ];

    $response = $this->actingAs($admin)
        ->put("/datacenters/{$datacenter->id}/rooms/{$room->id}/pdus/{$pdu->id}", $updateData);

    $response->assertRedirect("/datacenters/{$datacenter->id}/rooms/{$room->id}");

    $pduFresh = Pdu::find($pdu->id);
    expect($pduFresh->isRowLevel())->toBeTrue();
    expect($pduFresh->row_id)->toBe($row->id);
    expect($pduFresh->room_id)->toBeNull();

    // Now reassign back to room-level
    $updateDataBack = [
        'name' => 'Reassignable PDU',
        'phase' => PduPhase::Single->value,
        'circuit_count' => 24,
        'status' => PduStatus::Active->value,
        'row_id' => null,
    ];

    $response = $this->actingAs($admin)
        ->put("/datacenters/{$datacenter->id}/rooms/{$room->id}/pdus/{$pdu->id}", $updateDataBack);

    $response->assertRedirect("/datacenters/{$datacenter->id}/rooms/{$room->id}");

    $pduFreshAgain = Pdu::find($pdu->id);
    expect($pduFreshAgain->isRoomLevel())->toBeTrue();
    expect($pduFreshAgain->room_id)->toBe($room->id);
    expect($pduFreshAgain->row_id)->toBeNull();
});

/**
 * Test 5: Activity logging captures Room, Row, PDU CRUD events
 *
 * Verifies that the Loggable trait correctly logs create, update, and delete
 * events for Room, Row, and PDU models.
 */
test('activity logging captures Room Row PDU CRUD events', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();

    // Clear any existing logs
    ActivityLog::truncate();

    // Create a room (should log 'created')
    $this->actingAs($admin)
        ->post("/datacenters/{$datacenter->id}/rooms", [
            'name' => 'Logged Room',
            'type' => RoomType::ServerRoom->value,
        ]);

    $room = Room::where('name', 'Logged Room')->first();

    // Verify room creation was logged
    $roomCreatedLog = ActivityLog::where('subject_type', Room::class)
        ->where('subject_id', $room->id)
        ->where('action', 'created')
        ->first();

    expect($roomCreatedLog)->not->toBeNull();
    expect($roomCreatedLog->causer_id)->toBe($admin->id);
    expect($roomCreatedLog->new_values['name'])->toBe('Logged Room');

    // Create a row (should log 'created')
    $this->actingAs($admin)
        ->post("/datacenters/{$datacenter->id}/rooms/{$room->id}/rows", [
            'name' => 'Logged Row',
            'position' => 1,
            'orientation' => RowOrientation::HotAisle->value,
            'status' => RowStatus::Active->value,
        ]);

    $row = Row::where('name', 'Logged Row')->first();

    $rowCreatedLog = ActivityLog::where('subject_type', Row::class)
        ->where('subject_id', $row->id)
        ->where('action', 'created')
        ->first();

    expect($rowCreatedLog)->not->toBeNull();
    expect($rowCreatedLog->causer_id)->toBe($admin->id);

    // Create a PDU (should log 'created')
    $this->actingAs($admin)
        ->post("/datacenters/{$datacenter->id}/rooms/{$room->id}/pdus", [
            'name' => 'Logged PDU',
            'phase' => PduPhase::Single->value,
            'circuit_count' => 24,
            'status' => PduStatus::Active->value,
        ]);

    $pdu = Pdu::where('name', 'Logged PDU')->first();

    $pduCreatedLog = ActivityLog::where('subject_type', Pdu::class)
        ->where('subject_id', $pdu->id)
        ->where('action', 'created')
        ->first();

    expect($pduCreatedLog)->not->toBeNull();
    expect($pduCreatedLog->causer_id)->toBe($admin->id);

    // Update room (should log 'updated')
    $this->actingAs($admin)
        ->put("/datacenters/{$datacenter->id}/rooms/{$room->id}", [
            'name' => 'Updated Room Name',
            'type' => RoomType::NetworkCloset->value,
        ]);

    $roomUpdatedLog = ActivityLog::where('subject_type', Room::class)
        ->where('subject_id', $room->id)
        ->where('action', 'updated')
        ->first();

    expect($roomUpdatedLog)->not->toBeNull();

    // Delete PDU (should log 'deleted')
    $this->actingAs($admin)
        ->delete("/datacenters/{$datacenter->id}/rooms/{$room->id}/pdus/{$pdu->id}");

    $pduDeletedLog = ActivityLog::where('subject_type', Pdu::class)
        ->where('subject_id', $pdu->id)
        ->where('action', 'deleted')
        ->first();

    expect($pduDeletedLog)->not->toBeNull();
});

/**
 * Test 6: Non-admin user access to assigned datacenter's rooms
 *
 * Verifies that Operator/Viewer users can only view rooms in datacenters
 * they are assigned to.
 */
test('non-admin user access to assigned datacenter rooms', function () {
    // Create datacenters
    $datacenter1 = Datacenter::factory()->create(['name' => 'Assigned DC']);
    $datacenter2 = Datacenter::factory()->create(['name' => 'Unassigned DC']);

    // Create rooms in each datacenter
    $room1 = Room::factory()->create([
        'name' => 'Assigned Room',
        'datacenter_id' => $datacenter1->id,
    ]);
    $room2 = Room::factory()->create([
        'name' => 'Unassigned Room',
        'datacenter_id' => $datacenter2->id,
    ]);

    // Create operator user with assignment to datacenter1 only
    $operator = User::factory()->create();
    $operator->assignRole('Operator');
    $operator->datacenters()->attach($datacenter1->id);

    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);

    // Operator can view rooms list for assigned datacenter
    $response = $this->actingAs($operator)
        ->get("/datacenters/{$datacenter1->id}/rooms");

    $response->assertOk();

    // Operator can view room details for assigned datacenter
    $response = $this->actingAs($operator)
        ->get("/datacenters/{$datacenter1->id}/rooms/{$room1->id}");

    $response->assertOk();

    // Operator cannot view room in unassigned datacenter
    $response = $this->actingAs($operator)
        ->get("/datacenters/{$datacenter2->id}/rooms/{$room2->id}");

    $response->assertForbidden();

    // Operator cannot create rooms (CRUD restricted to admin roles)
    $response = $this->actingAs($operator)
        ->post("/datacenters/{$datacenter1->id}/rooms", [
            'name' => 'New Room',
            'type' => RoomType::ServerRoom->value,
        ]);

    $response->assertForbidden();
});

/**
 * Test 7: Position reordering maintains integrity
 *
 * Tests that row positions can be updated without conflicts and that
 * the ordering is maintained correctly after updates.
 */
test('position reordering maintains integrity', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);

    // Create rows with positions 1, 2, 3
    $row1 = Row::factory()->create([
        'name' => 'Row A',
        'room_id' => $room->id,
        'position' => 1,
        'orientation' => RowOrientation::HotAisle,
        'status' => RowStatus::Active,
    ]);
    $row2 = Row::factory()->create([
        'name' => 'Row B',
        'room_id' => $room->id,
        'position' => 2,
        'orientation' => RowOrientation::ColdAisle,
        'status' => RowStatus::Active,
    ]);
    $row3 = Row::factory()->create([
        'name' => 'Row C',
        'room_id' => $room->id,
        'position' => 3,
        'orientation' => RowOrientation::HotAisle,
        'status' => RowStatus::Active,
    ]);

    // Move row3 to position 1 (reorder)
    $response = $this->actingAs($admin)
        ->put("/datacenters/{$datacenter->id}/rooms/{$room->id}/rows/{$row3->id}", [
            'name' => 'Row C',
            'position' => 1,
            'orientation' => RowOrientation::HotAisle->value,
            'status' => RowStatus::Active->value,
        ]);

    $response->assertRedirect("/datacenters/{$datacenter->id}/rooms/{$room->id}");

    // Verify row3 now has position 1
    expect(Row::find($row3->id)->position)->toBe(1);

    // Note: The current implementation allows duplicate positions
    // This is acceptable for manual position management

    // Move row1 to position 5 (gap in sequence is allowed)
    $response = $this->actingAs($admin)
        ->put("/datacenters/{$datacenter->id}/rooms/{$room->id}/rows/{$row1->id}", [
            'name' => 'Row A',
            'position' => 5,
            'orientation' => RowOrientation::HotAisle->value,
            'status' => RowStatus::Active->value,
        ]);

    $response->assertRedirect("/datacenters/{$datacenter->id}/rooms/{$room->id}");

    expect(Row::find($row1->id)->position)->toBe(5);

    // Verify ordering by position works correctly
    $orderedRows = $room->rows()->orderBy('position')->get();
    expect($orderedRows[0]->id)->toBe($row3->id); // position 1
    expect($orderedRows[1]->id)->toBe($row2->id); // position 2
    expect($orderedRows[2]->id)->toBe($row1->id); // position 5
});

/**
 * Test 8: Room update functionality
 *
 * Tests that room details can be updated correctly.
 */
test('room update functionality works correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create([
        'name' => 'Original Room Name',
        'description' => 'Original description',
        'square_footage' => 1000.00,
        'type' => RoomType::ServerRoom,
        'datacenter_id' => $datacenter->id,
    ]);

    // Update the room
    $updateData = [
        'name' => 'Updated Room Name',
        'description' => 'Updated description with more details',
        'square_footage' => 2500.75,
        'type' => RoomType::NetworkCloset->value,
    ];

    $response = $this->actingAs($admin)
        ->put("/datacenters/{$datacenter->id}/rooms/{$room->id}", $updateData);

    $response->assertRedirect("/datacenters/{$datacenter->id}/rooms/{$room->id}");

    // Verify updates
    $updatedRoom = Room::find($room->id);
    expect($updatedRoom->name)->toBe('Updated Room Name');
    expect($updatedRoom->description)->toBe('Updated description with more details');
    expect($updatedRoom->square_footage)->toBe('2500.75');
    expect($updatedRoom->type)->toBe(RoomType::NetworkCloset);

    // Ensure datacenter_id was not changed
    expect($updatedRoom->datacenter_id)->toBe($datacenter->id);
});
