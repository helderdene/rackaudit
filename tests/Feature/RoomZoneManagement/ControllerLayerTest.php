<?php

use App\Enums\PduPhase;
use App\Enums\PduStatus;
use App\Enums\RoomType;
use App\Enums\RowOrientation;
use App\Enums\RowStatus;
use App\Models\Datacenter;
use App\Models\Pdu;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Disable Inertia page existence check since Vue components are created in Task Group 4
    config(['inertia.testing.ensure_pages_exist' => false]);
});

/**
 * Test 1: RoomController index returns rooms filtered by datacenter
 */
test('RoomController index returns rooms filtered by datacenter', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter1 = Datacenter::factory()->create(['name' => 'DC One']);
    $datacenter2 = Datacenter::factory()->create(['name' => 'DC Two']);

    // Create rooms in different datacenters
    Room::factory()->create([
        'name' => 'Room A',
        'datacenter_id' => $datacenter1->id,
    ]);
    Room::factory()->create([
        'name' => 'Room B',
        'datacenter_id' => $datacenter1->id,
    ]);
    Room::factory()->create([
        'name' => 'Room C',
        'datacenter_id' => $datacenter2->id,
    ]);

    // Test index for datacenter 1 - should return 2 rooms
    $response = $this->actingAs($admin)
        ->get("/datacenters/{$datacenter1->id}/rooms");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rooms/Index')
            ->has('rooms.data', 2)
        );

    // Test index for datacenter 2 - should return 1 room
    $response = $this->actingAs($admin)
        ->get("/datacenters/{$datacenter2->id}/rooms");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rooms.data', 1)
        );
});

/**
 * Test 2: RoomController store creates room with valid data
 */
test('RoomController store creates room with valid data', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();

    $roomData = [
        'name' => 'New Server Room',
        'description' => 'Main server room for production workloads',
        'square_footage' => 2500.50,
        'type' => RoomType::ServerRoom->value,
    ];

    $response = $this->actingAs($admin)
        ->post("/datacenters/{$datacenter->id}/rooms", $roomData);

    $response->assertRedirect("/datacenters/{$datacenter->id}");

    $this->assertDatabaseHas('rooms', [
        'name' => 'New Server Room',
        'description' => 'Main server room for production workloads',
        'datacenter_id' => $datacenter->id,
    ]);
});

/**
 * Test 3: RoomController show returns room with rows and PDUs
 */
test('RoomController show returns room with rows and PDUs', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create([
        'name' => 'Test Room',
        'datacenter_id' => $datacenter->id,
    ]);

    // Create rows for the room
    $row1 = Row::factory()->create([
        'name' => 'Row A',
        'room_id' => $room->id,
        'position' => 1,
    ]);
    $row2 = Row::factory()->create([
        'name' => 'Row B',
        'room_id' => $room->id,
        'position' => 2,
    ]);

    // Create PDUs - one room-level and one row-level
    Pdu::factory()->create([
        'name' => 'PDU-1000',
        'room_id' => $room->id,
        'row_id' => null,
    ]);
    Pdu::factory()->create([
        'name' => 'PDU-2000',
        'room_id' => null,
        'row_id' => $row1->id,
    ]);

    $response = $this->actingAs($admin)
        ->get("/datacenters/{$datacenter->id}/rooms/{$room->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rooms/Show')
            ->has('room')
            ->where('room.name', 'Test Room')
            ->has('rows', 2)
            ->has('pdus', 2)
        );
});

/**
 * Test 4: RowController index returns rows for a room
 */
test('RowController index returns rows for a room ordered by position', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);

    // Create rows with specific positions
    Row::factory()->create([
        'name' => 'Row C',
        'room_id' => $room->id,
        'position' => 3,
    ]);
    Row::factory()->create([
        'name' => 'Row A',
        'room_id' => $room->id,
        'position' => 1,
    ]);
    Row::factory()->create([
        'name' => 'Row B',
        'room_id' => $room->id,
        'position' => 2,
    ]);

    $response = $this->actingAs($admin)
        ->get("/datacenters/{$datacenter->id}/rooms/{$room->id}/rows");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 3)
            // Verify order by position
            ->where('rows.0.name', 'Row A')
            ->where('rows.1.name', 'Row B')
            ->where('rows.2.name', 'Row C')
        );
});

/**
 * Test 5: RowController store creates row with correct position
 */
test('RowController store creates row with correct position', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);

    $rowData = [
        'name' => 'New Row A',
        'position' => 5,
        'orientation' => RowOrientation::HotAisle->value,
        'status' => RowStatus::Active->value,
    ];

    $response = $this->actingAs($admin)
        ->post("/datacenters/{$datacenter->id}/rooms/{$room->id}/rows", $rowData);

    $response->assertRedirect("/datacenters/{$datacenter->id}/rooms/{$room->id}");

    $this->assertDatabaseHas('rows', [
        'name' => 'New Row A',
        'position' => 5,
        'orientation' => RowOrientation::HotAisle->value,
        'room_id' => $room->id,
    ]);
});

/**
 * Test 6: PduController store creates PDU with room-level assignment
 */
test('PduController store creates PDU with room-level assignment', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);

    $pduData = [
        'name' => 'PDU-Room-001',
        'model' => 'APC AP8941',
        'manufacturer' => 'APC',
        'total_capacity_kw' => 17.3,
        'voltage' => 208,
        'phase' => PduPhase::Single->value,
        'circuit_count' => 24,
        'status' => PduStatus::Active->value,
        'room_id' => $room->id,
        'row_id' => null,
    ];

    $response = $this->actingAs($admin)
        ->post("/datacenters/{$datacenter->id}/rooms/{$room->id}/pdus", $pduData);

    $response->assertRedirect("/datacenters/{$datacenter->id}/rooms/{$room->id}");

    $this->assertDatabaseHas('pdus', [
        'name' => 'PDU-Room-001',
        'room_id' => $room->id,
        'row_id' => null,
    ]);

    $pdu = Pdu::where('name', 'PDU-Room-001')->first();
    expect($pdu->isRoomLevel())->toBeTrue();
});

/**
 * Test 7: PduController store creates PDU with row-level assignment
 */
test('PduController store creates PDU with row-level assignment', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    $pduData = [
        'name' => 'PDU-Row-001',
        'model' => 'Eaton G3',
        'manufacturer' => 'Eaton',
        'total_capacity_kw' => 22.5,
        'voltage' => 208,
        'phase' => PduPhase::ThreePhase->value,
        'circuit_count' => 42,
        'status' => PduStatus::Active->value,
        'room_id' => null,
        'row_id' => $row->id,
    ];

    $response = $this->actingAs($admin)
        ->post("/datacenters/{$datacenter->id}/rooms/{$room->id}/pdus", $pduData);

    $response->assertRedirect("/datacenters/{$datacenter->id}/rooms/{$room->id}");

    $this->assertDatabaseHas('pdus', [
        'name' => 'PDU-Row-001',
        'room_id' => null,
        'row_id' => $row->id,
    ]);

    $pdu = Pdu::where('name', 'PDU-Row-001')->first();
    expect($pdu->isRowLevel())->toBeTrue();
});

/**
 * Test 8: Search functionality filters rooms by name
 */
test('search functionality filters rooms by name', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();

    // Create rooms with different names
    Room::factory()->create([
        'name' => 'Production Server Room',
        'datacenter_id' => $datacenter->id,
    ]);
    Room::factory()->create([
        'name' => 'Development Lab',
        'datacenter_id' => $datacenter->id,
    ]);
    Room::factory()->create([
        'name' => 'Network Operations Center',
        'datacenter_id' => $datacenter->id,
    ]);

    // Search for 'Server'
    $response = $this->actingAs($admin)
        ->get("/datacenters/{$datacenter->id}/rooms?search=Server");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rooms.data', 1)
            ->where('rooms.data.0.name', 'Production Server Room')
        );

    // Search for 'Network'
    $response = $this->actingAs($admin)
        ->get("/datacenters/{$datacenter->id}/rooms?search=Network");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rooms.data', 1)
            ->where('rooms.data.0.name', 'Network Operations Center')
        );

    // Search with no results
    $response = $this->actingAs($admin)
        ->get("/datacenters/{$datacenter->id}/rooms?search=NonexistentRoom");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rooms.data', 0)
        );
});
