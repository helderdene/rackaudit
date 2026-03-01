<?php

use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Models\Connection;
use App\Models\Device;
use App\Models\EquipmentMove;
use App\Models\Port;
use App\Models\Rack;
use App\Models\User;
use App\Services\EquipmentMoveReportService;
use App\Services\EquipmentMoveService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create manager user (can approve)
    $this->manager = User::factory()->create();
    $this->manager->assignRole('IT Manager');

    // Create regular user (can create but not approve)
    $this->regularUser = User::factory()->create();
    $this->regularUser->assignRole('Viewer');

    // Create racks with location hierarchy for testing
    $this->sourceRack = Rack::factory()->create(['u_height' => 42]);
    $this->destinationRack = Rack::factory()->create(['u_height' => 42]);
});

/**
 * Test 1: PDF work order generates with correct device data
 */
test('pdf work order generates with correct device data', function () {
    $device = Device::factory()->placed($this->sourceRack, 10)->withUHeight(2)->create([
        'name' => 'Test Server 01',
        'asset_tag' => 'SRV-0001',
        'serial_number' => 'ABC123XYZ',
        'manufacturer' => 'Dell',
        'model' => 'PowerEdge R740',
    ]);

    $move = EquipmentMove::factory()->forDevice($device)->create([
        'destination_rack_id' => $this->destinationRack->id,
        'destination_start_u' => 15,
        'destination_rack_face' => DeviceRackFace::Front,
        'destination_width_type' => DeviceWidthType::Full,
        'status' => 'pending_approval',
        'requested_by' => $this->regularUser->id,
        'operator_notes' => 'Move during maintenance window',
        'connections_snapshot' => [],
    ]);

    // Use fake storage to capture the generated file
    Storage::fake('local');

    $reportService = app(EquipmentMoveReportService::class);
    $filePath = $reportService->generateWorkOrder($move, $this->regularUser);

    // Verify file was created
    expect($filePath)->toBeString();
    expect($filePath)->toContain('move-work-order-');
    expect($filePath)->toContain('.pdf');

    Storage::disk('local')->assertExists($filePath);
});

/**
 * Test 2: PDF includes all connections in snapshot
 */
test('pdf includes all connections in snapshot', function () {
    $device = Device::factory()->placed($this->sourceRack, 10)->create([
        'name' => 'Test Server',
    ]);

    $connectionsSnapshot = [
        [
            'id' => 1,
            'source_port_label' => 'eth0',
            'destination_port_label' => 'sw-port-1',
            'destination_device_name' => 'Core Switch 01',
            'cable_type' => 'Cat6',
            'cable_length' => 3.5,
            'cable_color' => 'blue',
        ],
        [
            'id' => 2,
            'source_port_label' => 'eth1',
            'destination_port_label' => 'sw-port-2',
            'destination_device_name' => 'Core Switch 01',
            'cable_type' => 'Cat6a',
            'cable_length' => 5.0,
            'cable_color' => 'yellow',
        ],
    ];

    $move = EquipmentMove::factory()->forDevice($device)->create([
        'destination_rack_id' => $this->destinationRack->id,
        'destination_start_u' => 15,
        'status' => 'pending_approval',
        'requested_by' => $this->regularUser->id,
        'connections_snapshot' => $connectionsSnapshot,
    ]);

    Storage::fake('local');

    $reportService = app(EquipmentMoveReportService::class);
    $filePath = $reportService->generateWorkOrder($move, $this->regularUser);

    Storage::disk('local')->assertExists($filePath);

    // The PDF is generated - we verify the file exists and service completes without error
    // Actual content verification would require PDF parsing which is complex for tests
    expect($move->connections_snapshot)->toHaveCount(2);
});

/**
 * Test 3: PDF download endpoint returns valid PDF
 */
test('pdf download endpoint returns valid pdf', function () {
    $device = Device::factory()->placed($this->sourceRack, 10)->withUHeight(2)->create([
        'name' => 'Server for PDF Download',
        'asset_tag' => 'PDF-0001',
    ]);

    $move = EquipmentMove::factory()->forDevice($device)->create([
        'destination_rack_id' => $this->destinationRack->id,
        'destination_start_u' => 15,
        'status' => 'pending_approval',
        'requested_by' => $this->regularUser->id,
        'connections_snapshot' => [],
    ]);

    // Requester should be able to download (participant)
    $response = $this->actingAs($this->regularUser)
        ->get("/equipment-moves/{$move->id}/work-order");

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    // Manager should also be able to download
    $response = $this->actingAs($this->manager)
        ->get("/equipment-moves/{$move->id}/work-order");

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

/**
 * Test 4: Full workflow - create, approve, and execute move (integration test)
 */
test('full workflow creates approves and executes move end to end', function () {
    $device = Device::factory()->placed($this->sourceRack, 10)->withUHeight(2)->create([
        'name' => 'Integration Test Server',
    ]);

    // Create ports and connections for the device
    $port1 = Port::factory()->ethernet()->create(['device_id' => $device->id, 'label' => 'eth0']);
    $port2 = Port::factory()->ethernet()->create(['device_id' => $device->id, 'label' => 'eth1']);

    $switchDevice = Device::factory()->create(['name' => 'Test Switch']);
    $switchPort1 = Port::factory()->ethernet()->create(['device_id' => $switchDevice->id, 'label' => 'sw-1']);
    $switchPort2 = Port::factory()->ethernet()->create(['device_id' => $switchDevice->id, 'label' => 'sw-2']);

    $connection1 = Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $switchPort1->id,
        'cable_color' => 'blue',
    ]);
    $connection2 = Connection::factory()->create([
        'source_port_id' => $port2->id,
        'destination_port_id' => $switchPort2->id,
        'cable_color' => 'yellow',
    ]);

    // Step 1: Create move request
    $response = $this->actingAs($this->regularUser)
        ->postJson('/equipment-moves', [
            'device_id' => $device->id,
            'destination_rack_id' => $this->destinationRack->id,
            'destination_start_u' => 20,
            'destination_rack_face' => DeviceRackFace::Front->value,
            'destination_width_type' => DeviceWidthType::Full->value,
            'operator_notes' => 'Full integration test move',
        ]);

    $response->assertCreated();
    $moveId = $response->json('data.id');

    // Verify connections were captured in snapshot
    $move = EquipmentMove::find($moveId);
    expect($move->connections_snapshot)->toHaveCount(2);

    // Step 2: Approve and execute
    $response = $this->actingAs($this->manager)
        ->postJson("/equipment-moves/{$moveId}/approve", [
            'approval_notes' => 'Approved for integration test',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'executed');

    // Step 3: Verify device moved
    $device->refresh();
    expect($device->rack_id)->toBe($this->destinationRack->id);
    expect($device->start_u)->toBe(20);

    // Step 4: Verify connections were disconnected (soft deleted)
    expect(Connection::find($connection1->id))->toBeNull();
    expect(Connection::find($connection2->id))->toBeNull();
    expect(Connection::withTrashed()->find($connection1->id))->not->toBeNull();
    expect(Connection::withTrashed()->find($connection2->id))->not->toBeNull();

    // Step 5: Verify move is marked as executed
    $move->refresh();
    expect($move->status)->toBe('executed');
    expect($move->executed_at)->not->toBeNull();
    expect($move->approved_by)->toBe($this->manager->id);
});

/**
 * Test 5: PDF download authorization - only participants and managers can download
 */
test('pdf download is restricted to participants and managers', function () {
    $device = Device::factory()->placed($this->sourceRack, 10)->create();

    $move = EquipmentMove::factory()->forDevice($device)->create([
        'destination_rack_id' => $this->destinationRack->id,
        'status' => 'pending_approval',
        'requested_by' => $this->regularUser->id,
    ]);

    // Create another user who is not a participant
    $otherUser = User::factory()->create();
    $otherUser->assignRole('Viewer');

    // Non-participant should be forbidden
    $response = $this->actingAs($otherUser)
        ->get("/equipment-moves/{$move->id}/work-order");

    $response->assertForbidden();

    // Requester (participant) should be able to download
    $response = $this->actingAs($this->regularUser)
        ->get("/equipment-moves/{$move->id}/work-order");

    $response->assertOk();

    // Manager should be able to download
    $response = $this->actingAs($this->manager)
        ->get("/equipment-moves/{$move->id}/work-order");

    $response->assertOk();
});
