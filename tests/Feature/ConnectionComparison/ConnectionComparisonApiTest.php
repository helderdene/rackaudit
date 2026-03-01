<?php

use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\DiscrepancyAcknowledgment;
use App\Models\ExpectedConnection;
use App\Models\ImplementationFile;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('returns correct structure for implementation file comparison endpoint', function () {
    // Create datacenter with hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create an approved implementation file
    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    // Create devices and ports
    $sourceDevice = Device::factory()->create(['rack_id' => $rack->id]);
    $destDevice = Device::factory()->create(['rack_id' => $rack->id]);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    // Create a confirmed expected connection
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice->id,
            'dest_port_id' => $destPort->id,
        ]);

    // Create matching actual connection
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'discrepancy_type',
                    'discrepancy_type_label',
                    'expected_connection',
                    'actual_connection',
                    'source_device',
                    'source_port',
                    'dest_device',
                    'dest_port',
                    'is_acknowledged',
                ],
            ],
            'statistics' => [
                'total',
                'matched',
                'missing',
                'unexpected',
                'mismatched',
                'conflicting',
                'acknowledged',
            ],
        ]);
});

it('returns correct structure for datacenter comparison endpoint', function () {
    // Create datacenter with hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create an approved implementation file
    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    // Create devices and ports
    $sourceDevice = Device::factory()->create(['rack_id' => $rack->id]);
    $destDevice = Device::factory()->create(['rack_id' => $rack->id]);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    // Create a confirmed expected connection
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice->id,
            'dest_port_id' => $destPort->id,
        ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/datacenters/{$datacenter->id}/connection-comparison");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'discrepancy_type',
                    'discrepancy_type_label',
                ],
            ],
            'statistics',
            'pagination' => [
                'total',
                'offset',
                'limit',
            ],
        ]);
});

it('filters comparison results by discrepancy type', function () {
    // Create datacenter with hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    // Create multiple expected connections - some will match, some will be missing
    $sourceDevice1 = Device::factory()->create(['rack_id' => $rack->id]);
    $destDevice1 = Device::factory()->create(['rack_id' => $rack->id]);
    $sourcePort1 = Port::factory()->ethernet()->create(['device_id' => $sourceDevice1->id]);
    $destPort1 = Port::factory()->ethernet()->create(['device_id' => $destDevice1->id]);

    // First expected connection - will be matched
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $sourceDevice1->id,
            'source_port_id' => $sourcePort1->id,
            'dest_device_id' => $destDevice1->id,
            'dest_port_id' => $destPort1->id,
        ]);

    // Create matching actual connection
    Connection::factory()->create([
        'source_port_id' => $sourcePort1->id,
        'destination_port_id' => $destPort1->id,
    ]);

    // Second expected connection - will be missing (no actual connection)
    $sourceDevice2 = Device::factory()->create(['rack_id' => $rack->id]);
    $destDevice2 = Device::factory()->create(['rack_id' => $rack->id]);
    $sourcePort2 = Port::factory()->ethernet()->create(['device_id' => $sourceDevice2->id]);
    $destPort2 = Port::factory()->ethernet()->create(['device_id' => $destDevice2->id]);

    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $sourceDevice2->id,
            'source_port_id' => $sourcePort2->id,
            'dest_device_id' => $destDevice2->id,
            'dest_port_id' => $destPort2->id,
        ]);

    // Filter for missing only
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison?discrepancy_type[]=missing");

    $response->assertOk();
    $data = $response->json('data');

    expect(count($data))->toBe(1);
    expect($data[0]['discrepancy_type'])->toBe('missing');
});

it('filters comparison results by device and rack', function () {
    // Create datacenter with hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack1 = Rack::factory()->create(['row_id' => $row->id]);
    $rack2 = Rack::factory()->create(['row_id' => $row->id]);

    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    // Create expected connections in different racks
    $sourceDevice1 = Device::factory()->create(['rack_id' => $rack1->id]);
    $destDevice1 = Device::factory()->create(['rack_id' => $rack1->id]);
    $sourcePort1 = Port::factory()->ethernet()->create(['device_id' => $sourceDevice1->id]);
    $destPort1 = Port::factory()->ethernet()->create(['device_id' => $destDevice1->id]);

    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $sourceDevice1->id,
            'source_port_id' => $sourcePort1->id,
            'dest_device_id' => $destDevice1->id,
            'dest_port_id' => $destPort1->id,
        ]);

    $sourceDevice2 = Device::factory()->create(['rack_id' => $rack2->id]);
    $destDevice2 = Device::factory()->create(['rack_id' => $rack2->id]);
    $sourcePort2 = Port::factory()->ethernet()->create(['device_id' => $sourceDevice2->id]);
    $destPort2 = Port::factory()->ethernet()->create(['device_id' => $destDevice2->id]);

    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $sourceDevice2->id,
            'source_port_id' => $sourcePort2->id,
            'dest_device_id' => $destDevice2->id,
            'dest_port_id' => $destPort2->id,
        ]);

    // Filter by device_id
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison?device_id={$sourceDevice1->id}");

    $response->assertOk();
    expect(count($response->json('data')))->toBe(1);

    // Filter by rack_id
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison?rack_id={$rack2->id}");

    $response->assertOk();
    expect(count($response->json('data')))->toBe(1);
});

it('paginates comparison results correctly', function () {
    // Create datacenter with hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    // Create a shared device type to avoid unique constraint issues
    $deviceType = DeviceType::factory()->create();

    // Create 5 expected connections using the shared device type
    for ($i = 0; $i < 5; $i++) {
        $sourceDevice = Device::factory()->create([
            'rack_id' => $rack->id,
            'device_type_id' => $deviceType->id,
            'name' => "Source Device {$i}",
        ]);
        $destDevice = Device::factory()->create([
            'rack_id' => $rack->id,
            'device_type_id' => $deviceType->id,
            'name' => "Dest Device {$i}",
        ]);
        $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
        $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

        ExpectedConnection::factory()
            ->confirmed()
            ->forImplementationFile($file)
            ->create([
                'source_device_id' => $sourceDevice->id,
                'source_port_id' => $sourcePort->id,
                'dest_device_id' => $destDevice->id,
                'dest_port_id' => $destPort->id,
            ]);
    }

    // Request with pagination
    $response = $this->actingAs($this->user)
        ->getJson("/api/datacenters/{$datacenter->id}/connection-comparison?limit=2&offset=0");

    $response->assertOk();
    $pagination = $response->json('pagination');

    expect($pagination['total'])->toBe(5);
    expect($pagination['limit'])->toBe(2);
    expect($pagination['offset'])->toBe(0);
    expect(count($response->json('data')))->toBe(2);

    // Request second page
    $response = $this->actingAs($this->user)
        ->getJson("/api/datacenters/{$datacenter->id}/connection-comparison?limit=2&offset=2");

    $response->assertOk();
    expect(count($response->json('data')))->toBe(2);
});

it('prevents access to non-approved files', function () {
    // Create a pending approval implementation file
    $file = ImplementationFile::factory()->xlsx()->pendingApproval()->create();

    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison");

    $response->assertForbidden();
});

it('creates an acknowledgment successfully', function () {
    // Create an expected connection
    $expectedConnection = ExpectedConnection::factory()->confirmed()->create();

    $response = $this->actingAs($this->user)
        ->postJson('/api/discrepancy-acknowledgments', [
            'expected_connection_id' => $expectedConnection->id,
            'discrepancy_type' => 'missing',
            'notes' => 'Acknowledged for testing purposes.',
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'expected_connection_id',
                'connection_id',
                'discrepancy_type',
                'acknowledged_by',
                'acknowledged_at',
                'notes',
            ],
        ]);

    $this->assertDatabaseHas('discrepancy_acknowledgments', [
        'expected_connection_id' => $expectedConnection->id,
        'discrepancy_type' => 'missing',
        'acknowledged_by' => $this->user->id,
        'notes' => 'Acknowledged for testing purposes.',
    ]);
});

it('deletes an acknowledgment successfully', function () {
    // Create an acknowledgment
    $acknowledgment = DiscrepancyAcknowledgment::factory()
        ->missing()
        ->acknowledgedByUser($this->user)
        ->create();

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/discrepancy-acknowledgments/{$acknowledgment->id}");

    $response->assertNoContent();

    $this->assertDatabaseMissing('discrepancy_acknowledgments', [
        'id' => $acknowledgment->id,
    ]);
});
