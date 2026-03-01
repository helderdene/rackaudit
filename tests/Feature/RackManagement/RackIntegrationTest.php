<?php

/**
 * Strategic Integration Tests for Rack Management
 *
 * These tests fill critical gaps in test coverage focusing on:
 * - Edge cases (rack without PDUs, null serial number)
 * - Authorization boundaries (unauthorized user access)
 * - Flash message verification
 * - Cascade behavior (row deletion)
 * - Complete user workflows
 */

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

    config(['inertia.testing.ensure_pages_exist' => false]);

    // Create hierarchy: Datacenter > Room > Row
    $this->datacenter = Datacenter::factory()->create(['name' => 'Integration DC']);
    $this->room = Room::factory()->create(['datacenter_id' => $this->datacenter->id, 'name' => 'Integration Room']);
    $this->row = Row::factory()->create(['room_id' => $this->room->id, 'name' => 'Integration Row']);

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');
});

/**
 * Test 1: Store creates rack without PDUs successfully (edge case)
 */
test('store creates rack without PDUs successfully', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('datacenters.rooms.rows.racks.store', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
        ]), [
            'name' => 'Rack Without PDUs',
            'position' => 1,
            'u_height' => RackUHeight::U42->value,
            'status' => RackStatus::Active->value,
            // No pdu_ids provided
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Rack created successfully.');

    $this->assertDatabaseHas('racks', [
        'name' => 'Rack Without PDUs',
        'row_id' => $this->row->id,
    ]);

    $rack = Rack::where('name', 'Rack Without PDUs')->first();
    expect($rack->pdus)->toHaveCount(0);
});

/**
 * Test 2: Store creates rack with null serial number
 */
test('store creates rack with null serial number', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('datacenters.rooms.rows.racks.store', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
        ]), [
            'name' => 'Rack No Serial',
            'position' => 1,
            'u_height' => RackUHeight::U45->value,
            'status' => RackStatus::Active->value,
            'serial_number' => null,
        ]);

    $response->assertRedirect();

    $rack = Rack::where('name', 'Rack No Serial')->first();
    expect($rack)->not->toBeNull();
    expect($rack->serial_number)->toBeNull();
});

/**
 * Test 3: Operator cannot create racks (authorization boundary)
 */
test('operator cannot create racks', function () {
    $operator = User::factory()->create();
    $operator->assignRole('Operator');
    $operator->datacenters()->attach($this->datacenter->id);

    $response = $this->actingAs($operator)
        ->post(route('datacenters.rooms.rows.racks.store', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
        ]), [
            'name' => 'Unauthorized Rack',
            'position' => 1,
            'u_height' => RackUHeight::U42->value,
            'status' => RackStatus::Active->value,
        ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('racks', ['name' => 'Unauthorized Rack']);
});

/**
 * Test 4: Operator cannot update racks (authorization boundary)
 */
test('operator cannot update racks', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Original Rack Name',
    ]);

    $operator = User::factory()->create();
    $operator->assignRole('Operator');
    $operator->datacenters()->attach($this->datacenter->id);

    $response = $this->actingAs($operator)
        ->put(route('datacenters.rooms.rows.racks.update', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]), [
            'name' => 'Hacked Rack Name',
            'position' => 1,
            'u_height' => RackUHeight::U42->value,
            'status' => RackStatus::Active->value,
        ]);

    $response->assertForbidden();

    $rack->refresh();
    expect($rack->name)->toBe('Original Rack Name');
});

/**
 * Test 5: Operator cannot delete racks (authorization boundary)
 */
test('operator cannot delete racks', function () {
    $rack = Rack::factory()->create(['row_id' => $this->row->id]);

    $operator = User::factory()->create();
    $operator->assignRole('Operator');
    $operator->datacenters()->attach($this->datacenter->id);

    $rackId = $rack->id;

    $response = $this->actingAs($operator)
        ->delete(route('datacenters.rooms.rows.racks.destroy', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]));

    $response->assertForbidden();
    $this->assertDatabaseHas('racks', ['id' => $rackId]);
});

/**
 * Test 6: Flash message appears after rack update
 */
test('flash message appears after rack update', function () {
    $rack = Rack::factory()->create(['row_id' => $this->row->id]);

    $response = $this->actingAs($this->admin)
        ->put(route('datacenters.rooms.rows.racks.update', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]), [
            'name' => 'Updated Rack',
            'position' => 1,
            'u_height' => RackUHeight::U42->value,
            'status' => RackStatus::Active->value,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Rack updated successfully.');
});

/**
 * Test 7: Flash message appears after rack deletion
 */
test('flash message appears after rack deletion', function () {
    $rack = Rack::factory()->create(['row_id' => $this->row->id]);

    $response = $this->actingAs($this->admin)
        ->delete(route('datacenters.rooms.rows.racks.destroy', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Rack deleted successfully.');
});

/**
 * Test 8: Row deletion cascades to delete all racks
 */
test('row deletion cascades to delete all racks', function () {
    // Create racks in the row
    $rack1 = Rack::factory()->create(['row_id' => $this->row->id, 'name' => 'Rack 1']);
    $rack2 = Rack::factory()->create(['row_id' => $this->row->id, 'name' => 'Rack 2']);

    // Attach PDUs to verify pivot cleanup
    $pdu = Pdu::factory()->create(['room_id' => $this->room->id, 'row_id' => null]);
    $rack1->pdus()->attach($pdu->id);

    $rowId = $this->row->id;
    $rack1Id = $rack1->id;
    $rack2Id = $rack2->id;

    // Delete the row
    $this->row->delete();

    // Verify racks are deleted (cascade from foreign key)
    $this->assertDatabaseMissing('racks', ['id' => $rack1Id]);
    $this->assertDatabaseMissing('racks', ['id' => $rack2Id]);

    // Verify PDU still exists (just the pivot is gone)
    $this->assertDatabaseHas('pdus', ['id' => $pdu->id]);
    $this->assertDatabaseMissing('pdu_rack', ['rack_id' => $rack1Id]);
});

/**
 * Test 9: Show page displays rack with no PDUs correctly
 */
test('show page displays rack with no PDUs correctly', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Rack No PDUs',
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
    ]);
    // No PDUs attached

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
            ->where('rack.name', 'Rack No PDUs')
            ->has('pdus', 0)
        );
});

/**
 * Test 10: Update can clear all PDUs from rack
 */
test('update can clear all PDUs from rack', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Rack With PDUs',
    ]);

    // Attach PDUs
    $pdu1 = Pdu::factory()->create(['room_id' => $this->room->id, 'row_id' => null]);
    $pdu2 = Pdu::factory()->create(['room_id' => $this->room->id, 'row_id' => null]);
    $rack->pdus()->attach([$pdu1->id, $pdu2->id]);

    expect($rack->pdus)->toHaveCount(2);

    // Update with empty pdu_ids array
    $response = $this->actingAs($this->admin)
        ->put(route('datacenters.rooms.rows.racks.update', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]), [
            'name' => 'Rack With PDUs',
            'position' => $rack->position,
            'u_height' => $rack->u_height->value,
            'status' => $rack->status->value,
            'pdu_ids' => [], // Clear all PDUs
        ]);

    $response->assertRedirect();

    $rack->refresh();
    expect($rack->pdus)->toHaveCount(0);

    // PDUs should still exist, just not attached
    $this->assertDatabaseHas('pdus', ['id' => $pdu1->id]);
    $this->assertDatabaseHas('pdus', ['id' => $pdu2->id]);
});
