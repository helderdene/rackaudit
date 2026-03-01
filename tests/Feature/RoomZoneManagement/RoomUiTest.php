<?php

use App\Enums\RoomType;
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
});

/**
 * Test 1: Rooms/Index.vue renders room list correctly
 */
test('Rooms/Index.vue renders room list correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);

    Room::factory()->create([
        'name' => 'Server Room A',
        'type' => RoomType::ServerRoom,
        'datacenter_id' => $datacenter->id,
    ]);
    Room::factory()->create([
        'name' => 'Network Closet B',
        'type' => RoomType::NetworkCloset,
        'datacenter_id' => $datacenter->id,
    ]);

    $response = $this->actingAs($admin)
        ->get("/datacenters/{$datacenter->id}/rooms");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rooms/Index')
            ->has('datacenter')
            ->where('datacenter.name', 'Test DC')
            ->has('rooms.data', 2)
            ->where('rooms.data.0.name', 'Network Closet B')
            ->where('rooms.data.0.type_label', 'Network Closet')
            ->where('rooms.data.1.name', 'Server Room A')
            ->where('rooms.data.1.type_label', 'Server Room')
            ->has('canCreate')
        );
});

/**
 * Test 2: Rooms/Create.vue form submits valid data
 */
test('Rooms/Create.vue form submits valid data', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();

    // Test that create page renders with room type options
    $response = $this->actingAs($admin)
        ->get("/datacenters/{$datacenter->id}/rooms/create");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rooms/Create')
            ->has('datacenter')
            ->has('roomTypes')
            ->has('roomTypes', 5)
        );

    // Test form submission
    $roomData = [
        'name' => 'New Production Room',
        'description' => 'Production server room for critical infrastructure',
        'square_footage' => 3500.75,
        'type' => RoomType::ServerRoom->value,
    ];

    $response = $this->actingAs($admin)
        ->post("/datacenters/{$datacenter->id}/rooms", $roomData);

    $response->assertRedirect("/datacenters/{$datacenter->id}");

    $this->assertDatabaseHas('rooms', [
        'name' => 'New Production Room',
        'description' => 'Production server room for critical infrastructure',
        'datacenter_id' => $datacenter->id,
    ]);
});

/**
 * Test 3: Rooms/Show.vue displays room details with rows and PDUs sections
 */
test('Rooms/Show.vue displays room details with rows and PDUs sections', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create(['name' => 'Main DC']);
    $room = Room::factory()->create([
        'name' => 'Main Server Room',
        'description' => 'Primary production room',
        'square_footage' => 5000,
        'type' => RoomType::ServerRoom,
        'datacenter_id' => $datacenter->id,
    ]);

    // Create rows
    $row1 = Row::factory()->create([
        'name' => 'Row 1',
        'room_id' => $room->id,
        'position' => 1,
    ]);
    $row2 = Row::factory()->create([
        'name' => 'Row 2',
        'room_id' => $room->id,
        'position' => 2,
    ]);

    // Create PDUs - one room-level and one row-level
    Pdu::factory()->create([
        'name' => 'PDU-Room-100',
        'room_id' => $room->id,
        'row_id' => null,
    ]);
    Pdu::factory()->create([
        'name' => 'PDU-Row-200',
        'room_id' => null,
        'row_id' => $row1->id,
    ]);

    $response = $this->actingAs($admin)
        ->get("/datacenters/{$datacenter->id}/rooms/{$room->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rooms/Show')
            ->has('datacenter')
            ->where('datacenter.name', 'Main DC')
            ->has('room')
            ->where('room.name', 'Main Server Room')
            ->where('room.description', 'Primary production room')
            ->where('room.square_footage', '5000.00')
            ->where('room.type_label', 'Server Room')
            ->has('rows', 2)
            ->where('rows.0.name', 'Row 1')
            ->where('rows.1.name', 'Row 2')
            ->has('pdus', 2)
            ->has('canEdit')
            ->has('canDelete')
            ->has('canCreateRow')
            ->has('canCreatePdu')
        );
});

/**
 * Test 4: Search input filters rooms by name
 */
test('search input filters rooms by name', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();

    Room::factory()->create([
        'name' => 'Production Server Room',
        'datacenter_id' => $datacenter->id,
    ]);
    Room::factory()->create([
        'name' => 'Development Lab',
        'datacenter_id' => $datacenter->id,
    ]);
    Room::factory()->create([
        'name' => 'Storage Area',
        'datacenter_id' => $datacenter->id,
    ]);

    // Search for 'Server' - should find 1 result
    $response = $this->actingAs($admin)
        ->get("/datacenters/{$datacenter->id}/rooms?search=Server");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rooms/Index')
            ->has('rooms.data', 1)
            ->where('rooms.data.0.name', 'Production Server Room')
            ->where('filters.search', 'Server')
        );

    // Search for 'Lab' - should find 1 result
    $response = $this->actingAs($admin)
        ->get("/datacenters/{$datacenter->id}/rooms?search=Lab");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rooms.data', 1)
            ->where('rooms.data.0.name', 'Development Lab')
        );

    // No filter - should find all 3
    $response = $this->actingAs($admin)
        ->get("/datacenters/{$datacenter->id}/rooms");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rooms.data', 3)
        );
});
