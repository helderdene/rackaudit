<?php

use App\Enums\CableType;
use App\Models\Connection;
use App\Models\Device;
use App\Models\Port;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('POST /devices/{device}/ports/{port}/pair creates bidirectional pairing', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();

    $portA = Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'front-1',
    ]);
    $portB = Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'back-1',
    ]);

    $response = $this->actingAs($user)
        ->postJson("/devices/{$device->id}/ports/{$portA->id}/pair", [
            'paired_port_id' => $portB->id,
        ]);

    $response->assertSuccessful();
    $response->assertJsonFragment(['message' => 'Ports paired successfully.']);

    // Verify bidirectional pairing
    $this->assertDatabaseHas('ports', [
        'id' => $portA->id,
        'paired_port_id' => $portB->id,
    ]);
    $this->assertDatabaseHas('ports', [
        'id' => $portB->id,
        'paired_port_id' => $portA->id,
    ]);
});

test('pairing fails when ports are on different devices', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device1 = Device::factory()->create();
    $device2 = Device::factory()->create();

    $portA = Port::factory()->ethernet()->create([
        'device_id' => $device1->id,
        'label' => 'front-1',
    ]);
    $portB = Port::factory()->ethernet()->create([
        'device_id' => $device2->id,
        'label' => 'back-1',
    ]);

    $response = $this->actingAs($user)
        ->postJson("/devices/{$device1->id}/ports/{$portA->id}/pair", [
            'paired_port_id' => $portB->id,
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['paired_port_id']);
});

test('pairing fails when either port is already paired', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();

    $portA = Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'front-1',
    ]);
    $portB = Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'back-1',
    ]);
    $portC = Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'front-2',
    ]);

    // Create existing pairing between A and B
    $portA->update(['paired_port_id' => $portB->id]);
    $portB->update(['paired_port_id' => $portA->id]);

    // Try to pair C with A (which is already paired)
    $response = $this->actingAs($user)
        ->postJson("/devices/{$device->id}/ports/{$portC->id}/pair", [
            'paired_port_id' => $portA->id,
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['paired_port_id']);
});

test('pairing fails when port is being paired with itself', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();

    $port = Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'front-1',
    ]);

    $response = $this->actingAs($user)
        ->postJson("/devices/{$device->id}/ports/{$port->id}/pair", [
            'paired_port_id' => $port->id,
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['paired_port_id']);
});

test('DELETE /devices/{device}/ports/{port}/pair removes pairing from both ports', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $device = Device::factory()->create();

    $portA = Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'front-1',
    ]);
    $portB = Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'back-1',
    ]);

    // Create bidirectional pairing
    $portA->update(['paired_port_id' => $portB->id]);
    $portB->update(['paired_port_id' => $portA->id]);

    $response = $this->actingAs($user)
        ->deleteJson("/devices/{$device->id}/ports/{$portA->id}/pair");

    $response->assertSuccessful();
    $response->assertJsonFragment(['message' => 'Port pairing removed successfully.']);

    // Verify both ports are no longer paired
    $this->assertDatabaseHas('ports', [
        'id' => $portA->id,
        'paired_port_id' => null,
    ]);
    $this->assertDatabaseHas('ports', [
        'id' => $portB->id,
        'paired_port_id' => null,
    ]);
});

test('logical path traversal works through paired ports', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    // Create devices: Server, Patch Panel, Switch
    $server = Device::factory()->create(['name' => 'Server']);
    $patchPanel = Device::factory()->create(['name' => 'Patch Panel']);
    $switch = Device::factory()->create(['name' => 'Switch']);

    // Server has one port
    $serverPort = Port::factory()->ethernet()->create([
        'device_id' => $server->id,
        'label' => 'eth0',
    ]);

    // Patch panel has front and back ports (paired)
    $patchFront = Port::factory()->ethernet()->create([
        'device_id' => $patchPanel->id,
        'label' => 'front-1',
    ]);
    $patchBack = Port::factory()->ethernet()->create([
        'device_id' => $patchPanel->id,
        'label' => 'back-1',
    ]);

    // Create bidirectional pairing on patch panel
    $patchFront->update(['paired_port_id' => $patchBack->id]);
    $patchBack->update(['paired_port_id' => $patchFront->id]);

    // Switch has one port
    $switchPort = Port::factory()->ethernet()->create([
        'device_id' => $switch->id,
        'label' => 'port-1',
    ]);

    // Create connection: Server Port -> Patch Panel Front
    $connection = Connection::factory()->create([
        'source_port_id' => $serverPort->id,
        'destination_port_id' => $patchFront->id,
        'cable_type' => CableType::Cat6,
    ]);

    // Load relationships for path derivation
    $connection->load(['sourcePort.pairedPort', 'destinationPort.pairedPort']);

    // Get logical path
    $path = $connection->getLogicalPath();

    // Path should include the paired back port of the destination patch panel
    expect($path)->toHaveCount(3);
    expect($path[0]->id)->toBe($serverPort->id);
    expect($path[1]->id)->toBe($patchFront->id);
    expect($path[2]->id)->toBe($patchBack->id);
});
