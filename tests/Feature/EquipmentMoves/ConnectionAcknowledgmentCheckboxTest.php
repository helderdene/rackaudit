<?php

use App\Enums\CableType;
use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Models\Connection;
use App\Models\Device;
use App\Models\Port;
use App\Models\Rack;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create manager user
    $this->manager = User::factory()->create();
    $this->manager->assignRole('IT Manager');

    // Create racks
    $this->sourceRack = Rack::factory()->create(['u_height' => 42]);
    $this->destinationRack = Rack::factory()->create(['u_height' => 42]);
});

/**
 * Test 1: Device search API returns connections data needed for checkbox display
 */
test('device search API returns connections array when device has connections', function () {
    // Create device with ports
    $device = Device::factory()->placed($this->sourceRack, 10)->withUHeight(2)->create([
        'name' => 'Database Server Test',
    ]);
    $port1 = Port::factory()->for($device)->create(['label' => 'eth0']);

    // Create destination device with port
    $destinationDevice = Device::factory()->placed($this->destinationRack, 20)->create([
        'name' => 'Switch Test',
    ]);
    $port2 = Port::factory()->for($destinationDevice)->create(['label' => 'sw-port-1']);

    // Create connection
    Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
        'cable_type' => CableType::Cat6,
        'cable_color' => 'blue',
    ]);

    $response = $this->actingAs($this->manager)
        ->getJson('/api/devices/search?q=Database');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'connections',
                ],
            ],
        ]);

    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['connections'])->toHaveCount(1);
    expect($data[0]['connections'][0]['source_port_label'])->toBe('eth0');
    expect($data[0]['connections'][0]['destination_port_label'])->toBe('sw-port-1');
    expect($data[0]['connections'][0]['destination_device_name'])->toBe('Switch Test');
});

/**
 * Test 2: Device search API returns empty connections array when device has no connections
 */
test('device search API returns empty connections array when device has no connections', function () {
    // Create device without connections
    $device = Device::factory()->placed($this->sourceRack, 10)->create([
        'name' => 'Standalone Server Test',
    ]);

    $response = $this->actingAs($this->manager)
        ->getJson('/api/devices/search?q=Standalone');

    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['connections'])->toBeArray();
    expect($data[0]['connections'])->toHaveCount(0);
});

/**
 * Test 3: Move request can be created for device with connections
 * This validates the full flow works when checkbox would be checked
 */
test('move request creation succeeds for device with connections', function () {
    // Create device with ports and connection
    $device = Device::factory()->placed($this->sourceRack, 10)->withUHeight(2)->create([
        'name' => 'Database Server With Conn',
    ]);
    $port1 = Port::factory()->for($device)->create(['label' => 'eth0']);

    // Create destination device with port
    $destinationDevice = Device::factory()->placed($this->destinationRack, 20)->create();
    $port2 = Port::factory()->for($destinationDevice)->create(['label' => 'sw-1']);

    // Create connection
    Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
        'cable_type' => CableType::FiberSm,
    ]);

    // Create move request (this is what happens after user acknowledges checkbox)
    $response = $this->actingAs($this->manager)
        ->postJson('/equipment-moves', [
            'device_id' => $device->id,
            'destination_rack_id' => $this->destinationRack->id,
            'destination_start_u' => 5,
            'destination_rack_face' => DeviceRackFace::Front->value,
            'destination_width_type' => DeviceWidthType::Full->value,
            'operator_notes' => 'Test move with connections acknowledged',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending_approval');

    // Verify connections snapshot was captured
    $move = \App\Models\EquipmentMove::first();
    expect($move->connections_snapshot)->toBeArray();
    expect($move->connections_snapshot)->toHaveCount(1);
});

/**
 * Test 4: Move request can be created for device without connections
 * This validates the flow works when no checkbox acknowledgment is needed
 */
test('move request creation succeeds for device without connections', function () {
    // Create device without connections
    $device = Device::factory()->placed($this->sourceRack, 10)->withUHeight(2)->create([
        'name' => 'Standalone Device',
    ]);

    // Create move request (no checkbox acknowledgment needed)
    $response = $this->actingAs($this->manager)
        ->postJson('/equipment-moves', [
            'device_id' => $device->id,
            'destination_rack_id' => $this->destinationRack->id,
            'destination_start_u' => 5,
            'destination_rack_face' => DeviceRackFace::Front->value,
            'destination_width_type' => DeviceWidthType::Full->value,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending_approval');

    // Verify empty connections snapshot
    $move = \App\Models\EquipmentMove::first();
    expect($move->connections_snapshot)->toBeArray();
    expect($move->connections_snapshot)->toHaveCount(0);
});

/**
 * Test 5: Connections data structure matches frontend expectations
 */
test('connections data structure includes all required fields for checkbox display', function () {
    // Create device with full connection details
    $device = Device::factory()->placed($this->sourceRack, 10)->create([
        'name' => 'Server With Full Connection',
    ]);
    $port1 = Port::factory()->for($device)->create(['label' => 'port-1']);

    $destDevice = Device::factory()->placed($this->destinationRack, 5)->create([
        'name' => 'Remote Switch',
    ]);
    $port2 = Port::factory()->for($destDevice)->create(['label' => 'ge-0/0/1']);

    Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
        'cable_type' => CableType::Cat6a,
        'cable_length' => 2.5,
        'cable_color' => 'yellow',
    ]);

    $response = $this->actingAs($this->manager)
        ->getJson('/api/devices/search?q=Server With Full');

    $response->assertOk();

    $data = $response->json('data');
    $connection = $data[0]['connections'][0];

    // Verify all fields needed by ConnectionReviewStep.vue are present
    expect($connection)->toHaveKey('id');
    expect($connection)->toHaveKey('source_port_label');
    expect($connection)->toHaveKey('destination_port_label');
    expect($connection)->toHaveKey('destination_device_name');
    expect($connection)->toHaveKey('cable_type');
    expect($connection)->toHaveKey('cable_length');
    expect($connection)->toHaveKey('cable_color');

    expect($connection['source_port_label'])->toBe('port-1');
    expect($connection['destination_port_label'])->toBe('ge-0/0/1');
    expect($connection['destination_device_name'])->toBe('Remote Switch');
    expect($connection['cable_color'])->toBe('yellow');
});
