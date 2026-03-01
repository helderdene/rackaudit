<?php

use App\Enums\PduPhase;
use App\Enums\PduStatus;
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
});

/**
 * Test 1: Rows/Create.vue form submits with orientation selection
 */
test('Rows/Create.vue form submits with orientation selection', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $room = Room::factory()->create([
        'name' => 'Server Room A',
        'datacenter_id' => $datacenter->id,
    ]);

    // Test that create page renders with orientation and status options
    $response = $this->actingAs($admin)
        ->get("/datacenters/{$datacenter->id}/rooms/{$room->id}/rows/create");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rows/Create')
            ->has('datacenter')
            ->has('room')
            ->has('orientationOptions')
            ->has('orientationOptions', 2) // HotAisle, ColdAisle
            ->has('statusOptions')
            ->has('statusOptions', 2) // Active, Inactive
            ->has('nextPosition')
        );

    // Test form submission with orientation selection
    $rowData = [
        'name' => 'Row 1',
        'position' => 1,
        'orientation' => RowOrientation::HotAisle->value,
        'status' => RowStatus::Active->value,
    ];

    $response = $this->actingAs($admin)
        ->post("/datacenters/{$datacenter->id}/rooms/{$room->id}/rows", $rowData);

    $response->assertRedirect("/datacenters/{$datacenter->id}/rooms/{$room->id}");

    $this->assertDatabaseHas('rows', [
        'name' => 'Row 1',
        'position' => 1,
        'orientation' => RowOrientation::HotAisle->value,
        'status' => RowStatus::Active->value,
        'room_id' => $room->id,
    ]);
});

/**
 * Test 2: PDU form allows selection between room-level and row-level assignment
 */
test('PDU form allows selection between room-level and row-level assignment', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $room = Room::factory()->create([
        'name' => 'Server Room A',
        'datacenter_id' => $datacenter->id,
    ]);

    // Create rows for assignment selection
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

    // Test that create page renders with rows for assignment
    $response = $this->actingAs($admin)
        ->get("/datacenters/{$datacenter->id}/rooms/{$room->id}/pdus/create");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pdus/Create')
            ->has('datacenter')
            ->has('room')
            ->has('rows', 2)
            ->where('rows.0.id', $row1->id)
            ->where('rows.0.name', 'Row 1')
            ->where('rows.1.id', $row2->id)
            ->where('rows.1.name', 'Row 2')
            ->has('phaseOptions')
            ->has('statusOptions')
        );

    // Test room-level PDU creation (no row_id specified - defaults to room level)
    $roomLevelPdu = [
        'name' => 'PDU-Room-001',
        'model' => 'Model A',
        'manufacturer' => 'Manufacturer A',
        'total_capacity_kw' => 50.5,
        'voltage' => 208,
        'phase' => PduPhase::ThreePhase->value,
        'circuit_count' => 42,
        'status' => PduStatus::Active->value,
        // row_id not provided = room-level assignment
    ];

    $response = $this->actingAs($admin)
        ->post("/datacenters/{$datacenter->id}/rooms/{$room->id}/pdus", $roomLevelPdu);

    $response->assertRedirect("/datacenters/{$datacenter->id}/rooms/{$room->id}");

    $this->assertDatabaseHas('pdus', [
        'name' => 'PDU-Room-001',
        'room_id' => $room->id,
        'row_id' => null,
    ]);

    // Test row-level PDU creation (with row_id)
    $rowLevelPdu = [
        'name' => 'PDU-Row-001',
        'model' => 'Model B',
        'manufacturer' => 'Manufacturer B',
        'total_capacity_kw' => 30.0,
        'voltage' => 480,
        'phase' => PduPhase::Single->value,
        'circuit_count' => 24,
        'status' => PduStatus::Active->value,
        'row_id' => $row1->id,
    ];

    $response = $this->actingAs($admin)
        ->post("/datacenters/{$datacenter->id}/rooms/{$room->id}/pdus", $rowLevelPdu);

    $response->assertRedirect("/datacenters/{$datacenter->id}/rooms/{$room->id}");

    $this->assertDatabaseHas('pdus', [
        'name' => 'PDU-Row-001',
        'room_id' => null,
        'row_id' => $row1->id,
    ]);
});

/**
 * Test 3: Inline row editing updates position correctly
 */
test('inline row editing updates position correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $room = Room::factory()->create([
        'name' => 'Server Room A',
        'datacenter_id' => $datacenter->id,
    ]);

    // Create rows
    $row1 = Row::factory()->create([
        'name' => 'Row 1',
        'room_id' => $room->id,
        'position' => 1,
        'orientation' => RowOrientation::HotAisle,
        'status' => RowStatus::Active,
    ]);
    $row2 = Row::factory()->create([
        'name' => 'Row 2',
        'room_id' => $room->id,
        'position' => 2,
        'orientation' => RowOrientation::ColdAisle,
        'status' => RowStatus::Active,
    ]);

    // Test edit page renders with correct data
    $response = $this->actingAs($admin)
        ->get("/datacenters/{$datacenter->id}/rooms/{$room->id}/rows/{$row1->id}/edit");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rows/Edit')
            ->has('datacenter')
            ->has('room')
            ->has('row')
            ->where('row.id', $row1->id)
            ->where('row.name', 'Row 1')
            ->where('row.position', 1)
            ->where('row.orientation', RowOrientation::HotAisle->value)
            ->where('row.status', RowStatus::Active->value)
            ->has('orientationOptions')
            ->has('statusOptions')
        );

    // Update row position and orientation
    $updateData = [
        'name' => 'Row 1 Updated',
        'position' => 3,
        'orientation' => RowOrientation::ColdAisle->value,
        'status' => RowStatus::Inactive->value,
    ];

    $response = $this->actingAs($admin)
        ->put("/datacenters/{$datacenter->id}/rooms/{$room->id}/rows/{$row1->id}", $updateData);

    $response->assertRedirect("/datacenters/{$datacenter->id}/rooms/{$room->id}");

    $this->assertDatabaseHas('rows', [
        'id' => $row1->id,
        'name' => 'Row 1 Updated',
        'position' => 3,
        'orientation' => RowOrientation::ColdAisle->value,
        'status' => RowStatus::Inactive->value,
    ]);
});

/**
 * Test 4: PDU list displays assignment level indicator
 */
test('PDU list displays assignment level indicator', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $room = Room::factory()->create([
        'name' => 'Server Room A',
        'datacenter_id' => $datacenter->id,
    ]);

    // Create a row
    $row = Row::factory()->create([
        'name' => 'Row A',
        'room_id' => $room->id,
        'position' => 1,
    ]);

    // Create room-level PDU
    $roomLevelPdu = Pdu::factory()->create([
        'name' => 'PDU-Room-100',
        'room_id' => $room->id,
        'row_id' => null,
        'status' => PduStatus::Active,
    ]);

    // Create row-level PDU
    $rowLevelPdu = Pdu::factory()->create([
        'name' => 'PDU-Row-200',
        'room_id' => null,
        'row_id' => $row->id,
        'status' => PduStatus::Active,
    ]);

    // Test room show page displays PDUs with assignment level
    $response = $this->actingAs($admin)
        ->get("/datacenters/{$datacenter->id}/rooms/{$room->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rooms/Show')
            ->has('pdus', 2)
            ->has('pdus.0.assignment_level')
            ->has('pdus.1.assignment_level')
        );

    // Also test the PDU index page
    $response = $this->actingAs($admin)
        ->get("/datacenters/{$datacenter->id}/rooms/{$room->id}/pdus");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pdus/Index')
            ->has('pdus', 2)
            ->has('rows')
        );

    // Verify the assignment level values by checking the response data
    $pduIndexResponse = $this->actingAs($admin)
        ->get("/datacenters/{$datacenter->id}/rooms/{$room->id}/pdus");

    $pdusData = $pduIndexResponse->viewData('page')['props']['pdus'];

    $roomPdu = collect($pdusData)->firstWhere('name', 'PDU-Room-100');
    $rowPdu = collect($pdusData)->firstWhere('name', 'PDU-Row-200');

    expect($roomPdu['assignment_level'])->toBe('Room Level');
    expect($rowPdu['assignment_level'])->toContain('Row: Row A');
});
