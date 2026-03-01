<?php

use App\Enums\CableType;
use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Models\Connection;
use App\Models\Device;
use App\Models\EquipmentMove;
use App\Models\Port;
use App\Models\Rack;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create manager user
    $this->manager = User::factory()->create();
    $this->manager->assignRole('IT Manager');

    // Create regular user
    $this->regularUser = User::factory()->create();
    $this->regularUser->assignRole('Viewer');

    // Create racks
    $this->sourceRack = Rack::factory()->create(['u_height' => 42]);
    $this->destinationRack = Rack::factory()->create(['u_height' => 42]);
});

/**
 * Test 1: Equipment moves index page renders with proper Inertia data
 */
test('equipment moves index page renders with status options and filters', function () {
    $device = Device::factory()->placed($this->sourceRack, 10)->create();

    EquipmentMove::factory()->forDevice($device)->create([
        'destination_rack_id' => $this->destinationRack->id,
        'status' => 'pending_approval',
        'requested_by' => $this->regularUser->id,
    ]);

    $response = $this->actingAs($this->manager)
        ->get('/equipment-moves');

    $response->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('EquipmentMoves/Index')
            ->has('moves')
            ->has('moves.data', 1)
            ->has('statusOptions')
            ->has('filters')
            ->where('filters.status', '')
            ->where('canCreate', true)
        );
});

/**
 * Test 2: Equipment moves show page renders move details with connections snapshot
 */
test('equipment moves show page renders move details with connections snapshot', function () {
    $device = Device::factory()->placed($this->sourceRack, 10)->create();

    $move = EquipmentMove::factory()->forDevice($device)->create([
        'destination_rack_id' => $this->destinationRack->id,
        'destination_start_u' => 15,
        'status' => 'pending_approval',
        'requested_by' => $this->regularUser->id,
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
        ->get("/equipment-moves/{$move->id}");

    $response->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('EquipmentMoves/Show')
            ->has('move')
            ->where('move.id', $move->id)
            ->where('move.status', 'pending_approval')
            ->where('move.connections_snapshot', fn ($snapshot) => count($snapshot) === 1)
        );
});

/**
 * Test 3: Device with pending move cannot be submitted for another move (form validation)
 */
test('device with pending move is rejected by store endpoint', function () {
    $device = Device::factory()->placed($this->sourceRack, 10)->create();

    // Create existing pending move
    EquipmentMove::factory()->forDevice($device)->create([
        'destination_rack_id' => $this->destinationRack->id,
        'status' => 'pending_approval',
        'requested_by' => $this->regularUser->id,
    ]);

    // Attempt to create another move for same device
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

/**
 * Test 4: Move with connections captures connection snapshot on creation
 */
test('move creation captures connections snapshot for device with connections', function () {
    // Create device with ports
    $device = Device::factory()->placed($this->sourceRack, 10)->withUHeight(2)->create();
    $port1 = Port::factory()->for($device)->create(['label' => 'eth0']);

    // Create destination device with port
    $destinationDevice = Device::factory()->placed($this->destinationRack, 20)->create();
    $port2 = Port::factory()->for($destinationDevice)->create(['label' => 'sw-port-1']);

    // Create connection using valid CableType enum value
    Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
        'cable_type' => CableType::FiberSm,
        'cable_color' => 'yellow',
    ]);

    $response = $this->actingAs($this->regularUser)
        ->postJson('/equipment-moves', [
            'device_id' => $device->id,
            'destination_rack_id' => $this->destinationRack->id,
            'destination_start_u' => 5,
            'destination_rack_face' => DeviceRackFace::Front->value,
            'destination_width_type' => DeviceWidthType::Full->value,
            'operator_notes' => 'Moving to new rack',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending_approval');

    // Verify connections snapshot was captured
    $move = EquipmentMove::first();
    expect($move->connections_snapshot)->toBeArray();
    expect($move->connections_snapshot)->toHaveCount(1);
    expect($move->connections_snapshot[0]['source_port_label'])->toBe('eth0');
});

/**
 * Test 5: Collision detection prevents placing device at occupied position
 */
test('collision detection prevents placing device at occupied position', function () {
    // Create device at destination
    $existingDevice = Device::factory()->placed($this->destinationRack, 5)->withUHeight(4)->create([
        'rack_face' => DeviceRackFace::Front,
        'width_type' => DeviceWidthType::Full,
    ]);

    // Create device to move
    $deviceToMove = Device::factory()->placed($this->sourceRack, 10)->withUHeight(2)->create();

    // Attempt to move to position that overlaps with existing device
    $response = $this->actingAs($this->regularUser)
        ->postJson('/equipment-moves', [
            'device_id' => $deviceToMove->id,
            'destination_rack_id' => $this->destinationRack->id,
            'destination_start_u' => 6, // Would overlap with device at 5-8
            'destination_rack_face' => DeviceRackFace::Front->value,
            'destination_width_type' => DeviceWidthType::Full->value,
        ]);

    $response->assertUnprocessable()
        ->assertJsonPath('errors.destination_start_u.0', fn ($message) => str_contains($message, 'conflicts'));
});

/**
 * Test 6: Index page with filters returns filtered results
 */
test('equipment moves index page filters by status correctly', function () {
    $device1 = Device::factory()->placed($this->sourceRack, 10)->create();
    $device2 = Device::factory()->placed($this->sourceRack, 20)->create();

    EquipmentMove::factory()->forDevice($device1)->create([
        'destination_rack_id' => $this->destinationRack->id,
        'status' => 'pending_approval',
        'requested_by' => $this->regularUser->id,
    ]);

    EquipmentMove::factory()->executed()->forDevice($device2)->create([
        'destination_rack_id' => $this->destinationRack->id,
        'requested_by' => $this->manager->id,
    ]);

    // Filter by pending_approval status
    $response = $this->actingAs($this->manager)
        ->get('/equipment-moves?status=pending_approval');

    $response->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('EquipmentMoves/Index')
            ->has('moves.data', 1)
            ->where('moves.data.0.status', 'pending_approval')
            ->where('filters.status', 'pending_approval')
        );
});
