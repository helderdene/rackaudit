<?php

use App\Enums\PortDirection;
use App\Enums\PortStatus;
use App\Enums\PortSubtype;
use App\Enums\PortType;
use App\Models\Device;
use App\Models\Port;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('index returns ports for a specific device', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();

    // Create ports for this device
    $port1 = Port::factory()->create([
        'device_id' => $device->id,
        'label' => 'eth0',
    ]);
    $port2 = Port::factory()->create([
        'device_id' => $device->id,
        'label' => 'eth1',
    ]);

    // Create a port for a different device
    $otherDevice = Device::factory()->create();
    Port::factory()->create([
        'device_id' => $otherDevice->id,
        'label' => 'other-port',
    ]);

    $response = $this->actingAs($user)
        ->getJson("/devices/{$device->id}/ports");

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'data');
    $response->assertJsonFragment(['label' => 'eth0']);
    $response->assertJsonFragment(['label' => 'eth1']);
    $response->assertJsonMissing(['label' => 'other-port']);
});

test('store creates port with valid data', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();

    $portData = [
        'label' => 'eth0',
        'type' => PortType::Ethernet->value,
        'subtype' => PortSubtype::Gbe10->value,
        'status' => PortStatus::Available->value,
        'direction' => PortDirection::Bidirectional->value,
    ];

    $response = $this->actingAs($user)
        ->postJson("/devices/{$device->id}/ports", $portData);

    $response->assertCreated();
    $response->assertJsonFragment(['label' => 'eth0']);
    $response->assertJsonFragment(['type' => 'ethernet']);
    $response->assertJsonFragment(['subtype' => 'gbe10']);

    $this->assertDatabaseHas('ports', [
        'device_id' => $device->id,
        'label' => 'eth0',
        'type' => 'ethernet',
        'subtype' => 'gbe10',
    ]);
});

test('store validates subtype matches parent type', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();

    // Try to create Ethernet port with Fiber subtype
    $portData = [
        'label' => 'eth0',
        'type' => PortType::Ethernet->value,
        'subtype' => PortSubtype::Lc->value, // LC is a Fiber subtype
        'direction' => PortDirection::Bidirectional->value,
    ];

    $response = $this->actingAs($user)
        ->postJson("/devices/{$device->id}/ports", $portData);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['subtype']);
});

test('update modifies port successfully', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();
    $port = Port::factory()->create([
        'device_id' => $device->id,
        'label' => 'eth0',
        'status' => PortStatus::Available,
    ]);

    $updateData = [
        'label' => 'eth0-updated',
        'type' => PortType::Ethernet->value,
        'subtype' => PortSubtype::Gbe10->value,
        'status' => PortStatus::Connected->value,
        'direction' => PortDirection::Uplink->value,
    ];

    $response = $this->actingAs($user)
        ->putJson("/devices/{$device->id}/ports/{$port->id}", $updateData);

    $response->assertSuccessful();
    $response->assertJsonFragment(['label' => 'eth0-updated']);
    $response->assertJsonFragment(['status' => 'connected']);

    $this->assertDatabaseHas('ports', [
        'id' => $port->id,
        'label' => 'eth0-updated',
        'status' => 'connected',
    ]);
});

test('destroy removes port', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();
    $port = Port::factory()->create([
        'device_id' => $device->id,
        'label' => 'eth0',
    ]);

    $response = $this->actingAs($user)
        ->deleteJson("/devices/{$device->id}/ports/{$port->id}");

    $response->assertSuccessful();
    $response->assertJsonFragment(['message' => 'Port deleted successfully.']);

    $this->assertDatabaseMissing('ports', [
        'id' => $port->id,
    ]);
});

test('bulk store creates multiple ports from template', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();

    $bulkData = [
        'prefix' => 'eth',
        'start_number' => 1,
        'end_number' => 5,
        'type' => PortType::Ethernet->value,
        'subtype' => PortSubtype::Gbe10->value,
        'direction' => PortDirection::Bidirectional->value,
    ];

    $response = $this->actingAs($user)
        ->postJson("/devices/{$device->id}/ports/bulk", $bulkData);

    $response->assertCreated();
    $response->assertJsonCount(5, 'data');

    // Verify all labels were created
    $this->assertDatabaseHas('ports', ['device_id' => $device->id, 'label' => 'eth1']);
    $this->assertDatabaseHas('ports', ['device_id' => $device->id, 'label' => 'eth2']);
    $this->assertDatabaseHas('ports', ['device_id' => $device->id, 'label' => 'eth3']);
    $this->assertDatabaseHas('ports', ['device_id' => $device->id, 'label' => 'eth4']);
    $this->assertDatabaseHas('ports', ['device_id' => $device->id, 'label' => 'eth5']);
});

test('unauthorized users cannot create/update/delete ports', function () {
    $user = User::factory()->create();
    $user->assignRole('Viewer'); // Viewer role should not have edit access

    $device = Device::factory()->create();
    $port = Port::factory()->create([
        'device_id' => $device->id,
        'label' => 'eth0',
    ]);

    $portData = [
        'label' => 'eth1',
        'type' => PortType::Ethernet->value,
        'subtype' => PortSubtype::Gbe10->value,
        'direction' => PortDirection::Bidirectional->value,
    ];

    // Test create
    $response = $this->actingAs($user)
        ->postJson("/devices/{$device->id}/ports", $portData);
    $response->assertForbidden();

    // Test update
    $response = $this->actingAs($user)
        ->putJson("/devices/{$device->id}/ports/{$port->id}", $portData);
    $response->assertForbidden();

    // Test delete
    $response = $this->actingAs($user)
        ->deleteJson("/devices/{$device->id}/ports/{$port->id}");
    $response->assertForbidden();

    // Test bulk create
    $response = $this->actingAs($user)
        ->postJson("/devices/{$device->id}/ports/bulk", [
            'prefix' => 'eth',
            'start_number' => 1,
            'end_number' => 5,
            'type' => PortType::Ethernet->value,
            'subtype' => PortSubtype::Gbe10->value,
        ]);
    $response->assertForbidden();
});

test('ports are scoped to their parent device', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device1 = Device::factory()->create();
    $device2 = Device::factory()->create();

    $port = Port::factory()->create([
        'device_id' => $device1->id,
        'label' => 'eth0',
    ]);

    // Try to access port from device1 via device2's route
    $response = $this->actingAs($user)
        ->getJson("/devices/{$device2->id}/ports/{$port->id}");

    $response->assertNotFound();

    // Try to update port from device1 via device2's route
    $response = $this->actingAs($user)
        ->putJson("/devices/{$device2->id}/ports/{$port->id}", [
            'label' => 'hacked',
            'type' => PortType::Ethernet->value,
            'subtype' => PortSubtype::Gbe10->value,
            'direction' => PortDirection::Bidirectional->value,
        ]);

    $response->assertNotFound();

    // Try to delete port from device1 via device2's route
    $response = $this->actingAs($user)
        ->deleteJson("/devices/{$device2->id}/ports/{$port->id}");

    $response->assertNotFound();

    // Verify the port still exists unchanged
    $this->assertDatabaseHas('ports', [
        'id' => $port->id,
        'label' => 'eth0',
        'device_id' => $device1->id,
    ]);
});
