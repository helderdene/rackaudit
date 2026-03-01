<?php

use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Models\Datacenter;
use App\Models\Pdu;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Disable Inertia page existence check since Vue components are created in later task groups
    config(['inertia.testing.ensure_pages_exist' => false]);

    // Create hierarchy: Datacenter > Room > Row
    $this->datacenter = Datacenter::factory()->create();
    $this->room = Room::factory()->create(['datacenter_id' => $this->datacenter->id]);
    $this->row = Row::factory()->create(['room_id' => $this->room->id]);

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');
});

/**
 * Test 1: Index returns racks for a row ordered by position
 */
test('index returns racks for a row ordered by position', function () {
    // Create racks at different positions
    $rack3 = Rack::factory()->atPosition(3)->create(['row_id' => $this->row->id, 'name' => 'Rack C']);
    $rack1 = Rack::factory()->atPosition(1)->create(['row_id' => $this->row->id, 'name' => 'Rack A']);
    $rack2 = Rack::factory()->atPosition(2)->create(['row_id' => $this->row->id, 'name' => 'Rack B']);

    // Attach some PDUs to rack1
    $pdu = Pdu::factory()->create(['room_id' => $this->room->id, 'row_id' => null]);
    $rack1->pdus()->attach($pdu->id);

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.index', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Index')
            ->has('racks', 3)
            ->where('racks.0.position', 1)
            ->where('racks.0.name', 'Rack A')
            ->where('racks.0.pdu_count', 1)
            ->where('racks.1.position', 2)
            ->where('racks.1.name', 'Rack B')
            ->where('racks.2.position', 3)
            ->where('racks.2.name', 'Rack C')
            ->has('datacenter')
            ->has('room')
            ->has('row')
            ->has('statusOptions')
            ->has('uHeightOptions')
            ->where('canCreate', true)
        );
});

/**
 * Test 2: Create returns form with PDU options and next position
 */
test('create returns form with PDU options and next position', function () {
    // Create existing racks to verify next position calculation
    Rack::factory()->atPosition(1)->create(['row_id' => $this->row->id]);
    Rack::factory()->atPosition(5)->create(['row_id' => $this->row->id]);

    // Create PDUs available for assignment (room-level and row-level from same row)
    $roomPdu = Pdu::factory()->create(['room_id' => $this->room->id, 'row_id' => null, 'name' => 'Room PDU']);
    $rowPdu = Pdu::factory()->create(['room_id' => null, 'row_id' => $this->row->id, 'name' => 'Row PDU']);

    // Create PDU from different room (should not appear in options)
    $otherRoom = Room::factory()->create(['datacenter_id' => $this->datacenter->id]);
    Pdu::factory()->create(['room_id' => $otherRoom->id, 'row_id' => null, 'name' => 'Other Room PDU']);

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.create', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Create')
            ->where('nextPosition', 6) // max(1,5) + 1 = 6
            ->has('datacenter')
            ->has('room')
            ->has('row')
            ->has('statusOptions')
            ->has('uHeightOptions')
            ->has('pduOptions', 2)
            ->where('pduOptions.0.name', 'Room PDU')
            ->where('pduOptions.1.name', 'Row PDU')
        );
});

/**
 * Test 3: Store creates rack and syncs PDUs
 */
test('store creates rack and syncs PDUs', function () {
    // Create PDUs to assign
    $pdu1 = Pdu::factory()->create(['room_id' => $this->room->id, 'row_id' => null]);
    $pdu2 = Pdu::factory()->create(['room_id' => null, 'row_id' => $this->row->id]);

    $response = $this->actingAs($this->admin)
        ->post(route('datacenters.rooms.rows.racks.store', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
        ]), [
            'name' => 'New Test Rack',
            'position' => 1,
            'u_height' => RackUHeight::U42->value,
            'serial_number' => 'SN-TEST-123',
            'status' => RackStatus::Active->value,
            'pdu_ids' => [$pdu1->id, $pdu2->id],
        ]);

    $response->assertRedirect(route('datacenters.rooms.rows.show', [
        'datacenter' => $this->datacenter->id,
        'room' => $this->room->id,
        'row' => $this->row->id,
    ]));

    // Verify rack was created
    $this->assertDatabaseHas('racks', [
        'name' => 'New Test Rack',
        'position' => 1,
        'u_height' => RackUHeight::U42->value,
        'serial_number' => 'SN-TEST-123',
        'status' => RackStatus::Active->value,
        'row_id' => $this->row->id,
    ]);

    // Verify PDU relationships were synced
    $rack = Rack::where('name', 'New Test Rack')->first();
    expect($rack->pdus)->toHaveCount(2);
    expect($rack->pdus->pluck('id')->toArray())->toContain($pdu1->id);
    expect($rack->pdus->pluck('id')->toArray())->toContain($pdu2->id);
});

/**
 * Test 4: Show returns rack details with assigned PDUs
 */
test('show returns rack details with assigned PDUs', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Detail Rack',
        'position' => 1,
        'u_height' => RackUHeight::U45,
        'serial_number' => 'SN-DETAIL',
        'status' => RackStatus::Active,
    ]);

    // Attach PDUs
    $pdu = Pdu::factory()->create([
        'room_id' => $this->room->id,
        'row_id' => null,
        'name' => 'Assigned PDU',
        'model' => 'APC Model',
        'total_capacity_kw' => 10.5,
    ]);
    $rack->pdus()->attach($pdu->id);

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.show', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Show')
            ->where('rack.name', 'Detail Rack')
            ->where('rack.position', 1)
            ->where('rack.u_height', RackUHeight::U45->value)
            ->where('rack.u_height_label', '45U')
            ->where('rack.serial_number', 'SN-DETAIL')
            ->where('rack.status', RackStatus::Active->value)
            ->where('rack.status_label', 'Active')
            ->has('pdus', 1)
            ->where('pdus.0.name', 'Assigned PDU')
            ->where('pdus.0.model', 'APC Model')
            ->where('canEdit', true)
            ->where('canDelete', true)
            ->has('datacenter')
            ->has('room')
            ->has('row')
        );
});

/**
 * Test 5: Edit returns form with current rack data and PDU options
 */
test('edit returns form with current rack data and PDU options', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Edit Rack',
        'position' => 3,
        'u_height' => RackUHeight::U48,
        'serial_number' => 'SN-EDIT',
        'status' => RackStatus::Maintenance,
    ]);

    // Create PDUs and attach one
    $assignedPdu = Pdu::factory()->create(['room_id' => $this->room->id, 'row_id' => null, 'name' => 'Assigned PDU']);
    $availablePdu = Pdu::factory()->create(['room_id' => $this->room->id, 'row_id' => null, 'name' => 'Available PDU']);
    $rack->pdus()->attach($assignedPdu->id);

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.edit', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Edit')
            ->where('rack.name', 'Edit Rack')
            ->where('rack.position', 3)
            ->where('rack.u_height', RackUHeight::U48->value)
            ->where('rack.serial_number', 'SN-EDIT')
            ->where('rack.status', RackStatus::Maintenance->value)
            ->has('rack.pdu_ids', 1)
            ->where('rack.pdu_ids.0', $assignedPdu->id)
            ->has('pduOptions', 2)
            ->has('statusOptions')
            ->has('uHeightOptions')
            ->has('datacenter')
            ->has('room')
            ->has('row')
        );
});

/**
 * Test 6: Update modifies rack and syncs PDUs
 */
test('update modifies rack and syncs PDUs', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Original Name',
        'position' => 1,
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
    ]);

    // Create and attach initial PDU
    $oldPdu = Pdu::factory()->create(['room_id' => $this->room->id, 'row_id' => null]);
    $rack->pdus()->attach($oldPdu->id);

    // Create new PDU to assign
    $newPdu = Pdu::factory()->create(['room_id' => $this->room->id, 'row_id' => null]);

    $response = $this->actingAs($this->admin)
        ->put(route('datacenters.rooms.rows.racks.update', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]), [
            'name' => 'Updated Name',
            'position' => 5,
            'u_height' => RackUHeight::U48->value,
            'serial_number' => 'SN-UPDATED',
            'status' => RackStatus::Inactive->value,
            'pdu_ids' => [$newPdu->id], // Replace old PDU with new one
        ]);

    $response->assertRedirect(route('datacenters.rooms.rows.show', [
        'datacenter' => $this->datacenter->id,
        'room' => $this->room->id,
        'row' => $this->row->id,
    ]));

    // Verify rack was updated
    $rack->refresh();
    expect($rack->name)->toBe('Updated Name');
    expect($rack->position)->toBe(5);
    expect($rack->u_height)->toBe(RackUHeight::U48);
    expect($rack->serial_number)->toBe('SN-UPDATED');
    expect($rack->status)->toBe(RackStatus::Inactive);

    // Verify PDU sync - old PDU removed, new PDU added
    expect($rack->pdus)->toHaveCount(1);
    expect($rack->pdus->first()->id)->toBe($newPdu->id);
});

/**
 * Test 7: Destroy detaches PDUs and deletes rack
 */
test('destroy detaches PDUs and deletes rack', function () {
    $rack = Rack::factory()->create(['row_id' => $this->row->id]);

    // Attach PDUs
    $pdu1 = Pdu::factory()->create(['room_id' => $this->room->id, 'row_id' => null]);
    $pdu2 = Pdu::factory()->create(['room_id' => null, 'row_id' => $this->row->id]);
    $rack->pdus()->attach([$pdu1->id, $pdu2->id]);

    $rackId = $rack->id;

    $response = $this->actingAs($this->admin)
        ->delete(route('datacenters.rooms.rows.racks.destroy', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]));

    $response->assertRedirect(route('datacenters.rooms.rows.show', [
        'datacenter' => $this->datacenter->id,
        'room' => $this->room->id,
        'row' => $this->row->id,
    ]));

    // Verify rack was deleted
    $this->assertDatabaseMissing('racks', ['id' => $rackId]);

    // Verify PDUs still exist (not deleted, just detached)
    $this->assertDatabaseHas('pdus', ['id' => $pdu1->id]);
    $this->assertDatabaseHas('pdus', ['id' => $pdu2->id]);

    // Verify pivot records were removed
    $this->assertDatabaseMissing('pdu_rack', ['rack_id' => $rackId]);
});

/**
 * Test 8: Elevation returns rack U-height data
 */
test('elevation returns rack U-height data', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Elevation Rack',
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Elevation')
            ->where('rack.name', 'Elevation Rack')
            ->where('rack.u_height', RackUHeight::U42->value)
            ->where('rack.u_height_label', '42U')
            ->where('rack.status', RackStatus::Active->value)
            ->where('rack.status_label', 'Active')
            ->has('datacenter')
            ->has('room')
            ->has('row')
        );
});
