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

    // Create hierarchy: Datacenter > Room > Row
    $this->datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $this->room = Room::factory()->create(['datacenter_id' => $this->datacenter->id, 'name' => 'Test Room']);
    $this->row = Row::factory()->create(['room_id' => $this->room->id, 'name' => 'Test Row']);

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');
});

/**
 * Test 1: Index page renders rack list correctly
 */
test('index page renders rack list with correct data', function () {
    // Create racks with varied status
    $rackActive = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Active Rack',
        'position' => 1,
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
    ]);

    $rackMaintenance = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Maintenance Rack',
        'position' => 2,
        'u_height' => RackUHeight::U45,
        'status' => RackStatus::Maintenance,
    ]);

    // Attach PDUs to one rack
    $pdu = Pdu::factory()->create(['room_id' => $this->room->id, 'row_id' => null]);
    $rackActive->pdus()->attach($pdu->id);

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.index', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Index')
            ->has('racks', 2)
            ->where('racks.0.name', 'Active Rack')
            ->where('racks.0.status_label', 'Active')
            ->where('racks.0.pdu_count', 1)
            ->where('racks.1.name', 'Maintenance Rack')
            ->where('racks.1.status_label', 'Maintenance')
            ->where('datacenter.name', 'Test DC')
            ->where('room.name', 'Test Room')
            ->where('row.name', 'Test Row')
            ->where('canCreate', true)
        );
});

/**
 * Test 2: Show page displays rack details and PDUs
 */
test('show page displays rack details and assigned PDUs', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Detail Test Rack',
        'position' => 1,
        'u_height' => RackUHeight::U48,
        'serial_number' => 'SN-123456',
        'status' => RackStatus::Active,
    ]);

    // Create and attach PDU
    $pdu = Pdu::factory()->create([
        'room_id' => $this->room->id,
        'row_id' => null,
        'name' => 'PDU-01',
        'model' => 'APC Model',
        'total_capacity_kw' => 15.5,
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
            ->where('rack.name', 'Detail Test Rack')
            ->where('rack.u_height_label', '48U')
            ->where('rack.serial_number', 'SN-123456')
            ->where('rack.status_label', 'Active')
            ->has('pdus', 1)
            ->where('pdus.0.name', 'PDU-01')
            ->where('pdus.0.model', 'APC Model')
            ->where('canEdit', true)
            ->where('canDelete', true)
        );
});

/**
 * Test 3: Create page form submission creates rack
 */
test('create page form submission creates rack', function () {
    $pdu = Pdu::factory()->create(['room_id' => $this->room->id, 'row_id' => null]);

    $response = $this->actingAs($this->admin)
        ->post(route('datacenters.rooms.rows.racks.store', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
        ]), [
            'name' => 'New Rack From Form',
            'position' => 1,
            'u_height' => RackUHeight::U42->value,
            'serial_number' => 'SN-FORM-001',
            'status' => RackStatus::Active->value,
            'pdu_ids' => [$pdu->id],
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('racks', [
        'name' => 'New Rack From Form',
        'position' => 1,
        'u_height' => RackUHeight::U42->value,
        'serial_number' => 'SN-FORM-001',
        'status' => RackStatus::Active->value,
        'row_id' => $this->row->id,
    ]);

    $rack = Rack::where('name', 'New Rack From Form')->first();
    expect($rack->pdus->pluck('id')->toArray())->toContain($pdu->id);
});

/**
 * Test 4: Edit page loads existing rack data
 */
test('edit page loads existing rack data', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Editable Rack',
        'position' => 3,
        'u_height' => RackUHeight::U45,
        'serial_number' => 'SN-EDIT-001',
        'status' => RackStatus::Inactive,
    ]);

    $pdu = Pdu::factory()->create(['room_id' => $this->room->id, 'row_id' => null]);
    $rack->pdus()->attach($pdu->id);

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
            ->where('rack.name', 'Editable Rack')
            ->where('rack.position', 3)
            ->where('rack.u_height', RackUHeight::U45->value)
            ->where('rack.serial_number', 'SN-EDIT-001')
            ->where('rack.status', RackStatus::Inactive->value)
            ->has('rack.pdu_ids', 1)
            ->has('pduOptions')
            ->has('statusOptions')
            ->has('uHeightOptions')
        );
});

/**
 * Test 5: Navigation breadcrumbs are correct
 */
test('breadcrumbs include correct hierarchy in index', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.index', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Index')
            ->where('datacenter.id', $this->datacenter->id)
            ->where('datacenter.name', 'Test DC')
            ->where('room.id', $this->room->id)
            ->where('room.name', 'Test Room')
            ->where('row.id', $this->row->id)
            ->where('row.name', 'Test Row')
        );
});

/**
 * Test 6: Elevation link accessible from Show page (elevation route works)
 * Note: Elevation.vue component is created in Task Group 6, so we disable page existence check
 */
test('elevation route is accessible from rack show context', function () {
    // Disable Inertia page existence check since Elevation.vue is created in Task Group 6
    config(['inertia.testing.ensure_pages_exist' => false]);

    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Elevation Test Rack',
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
            ->where('rack.name', 'Elevation Test Rack')
            ->where('rack.u_height', RackUHeight::U42->value)
        );
});

/**
 * Test 7: Row show page displays racks section with correct data
 * This tests Task 7.1: Rows/Show.vue displays racks in the row
 */
test('row show page displays racks section', function () {
    // Create racks in the row
    $rackActive = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Rack A',
        'position' => 1,
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
    ]);

    $rackInactive = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Rack B',
        'position' => 2,
        'u_height' => RackUHeight::U45,
        'status' => RackStatus::Inactive,
    ]);

    // Attach PDU to one rack to verify pdu_count mapping
    $pdu = Pdu::factory()->create(['room_id' => $this->room->id, 'row_id' => null]);
    $rackActive->pdus()->attach($pdu->id);

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.show', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rows/Show')
            // Verify row data
            ->where('row.name', 'Test Row')
            // Verify racks array exists with correct count
            ->has('racks', 2)
            // Verify first rack data (ordered by position)
            ->where('racks.0.name', 'Rack A')
            ->where('racks.0.position', 1)
            ->where('racks.0.u_height', RackUHeight::U42->value)
            ->where('racks.0.u_height_label', '42U')
            ->where('racks.0.status', RackStatus::Active->value)
            ->where('racks.0.status_label', 'Active')
            ->where('racks.0.pdu_count', 1)
            // Verify second rack data
            ->where('racks.1.name', 'Rack B')
            ->where('racks.1.position', 2)
            ->where('racks.1.status_label', 'Inactive')
            // Verify canCreateRack flag is passed
            ->where('canCreateRack', true)
        );
});

/**
 * Test 8: Complete navigation flow from Row to Rack to Elevation
 * This tests Task 7.3: Verify complete navigation flow
 */
test('complete navigation flow from row to rack to elevation', function () {
    // Create rack in the row
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Navigation Test Rack',
        'position' => 1,
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
    ]);

    // Step 1: Visit Row show page
    $rowShowResponse = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.show', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
        ]));

    $rowShowResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rows/Show')
            ->has('racks', 1)
            ->where('racks.0.id', $rack->id)
        );

    // Step 2: Visit Rack show page (simulating clicking rack name link)
    $rackShowResponse = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.show', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]));

    $rackShowResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Show')
            ->where('rack.name', 'Navigation Test Rack')
            ->where('row.id', $this->row->id)
            ->where('row.name', 'Test Row')
        );

    // Step 3: Visit Elevation page (simulating clicking View Elevation button)
    $elevationResponse = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]));

    $elevationResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Elevation')
            ->where('rack.id', $rack->id)
            ->where('rack.u_height', RackUHeight::U42->value)
        );
});

/**
 * Test 9: Add Rack button visible on Row show page for authorized users
 */
test('add rack button accessible from row show page', function () {
    // Create a non-admin user (Operator) who has datacenter access
    $operator = User::factory()->create();
    $operator->assignRole('Operator');
    // Assign datacenter access so they can view the row
    $operator->datacenters()->attach($this->datacenter->id);

    // Admin should see canCreateRack = true
    $adminResponse = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.show', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
        ]));

    $adminResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rows/Show')
            ->where('canCreateRack', true)
        );

    // Operator should see canCreateRack = false (only Admin/IT Manager can create)
    $operatorResponse = $this->actingAs($operator)
        ->get(route('datacenters.rooms.rows.show', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
        ]));

    $operatorResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rows/Show')
            ->where('canCreateRack', false)
        );
});
