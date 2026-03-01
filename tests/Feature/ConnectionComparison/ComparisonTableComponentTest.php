<?php

use App\Enums\DiscrepancyType;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
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
    $this->datacenter = Datacenter::factory()->create();
    $this->room = Room::factory()->create(['datacenter_id' => $this->datacenter->id]);
    $this->row = Row::factory()->create(['room_id' => $this->room->id]);
    $this->rack = Rack::factory()->create(['row_id' => $this->row->id]);
    $this->file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);
});

it('renders all discrepancy types with correct data structure in API response', function () {
    // Create devices and ports for matched connection
    $sourceDevice1 = Device::factory()->create(['rack_id' => $this->rack->id, 'name' => 'Server-Match']);
    $destDevice1 = Device::factory()->create(['rack_id' => $this->rack->id, 'name' => 'Switch-Match']);
    $sourcePort1 = Port::factory()->ethernet()->create(['device_id' => $sourceDevice1->id, 'label' => 'eth0']);
    $destPort1 = Port::factory()->ethernet()->create(['device_id' => $destDevice1->id, 'label' => 'port1']);

    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($this->file)
        ->create([
            'source_device_id' => $sourceDevice1->id,
            'source_port_id' => $sourcePort1->id,
            'dest_device_id' => $destDevice1->id,
            'dest_port_id' => $destPort1->id,
        ]);

    Connection::factory()->create([
        'source_port_id' => $sourcePort1->id,
        'destination_port_id' => $destPort1->id,
    ]);

    // Create devices and ports for missing connection
    $sourceDevice2 = Device::factory()->create(['rack_id' => $this->rack->id, 'name' => 'Server-Missing']);
    $destDevice2 = Device::factory()->create(['rack_id' => $this->rack->id, 'name' => 'Switch-Missing']);
    $sourcePort2 = Port::factory()->ethernet()->create(['device_id' => $sourceDevice2->id, 'label' => 'eth1']);
    $destPort2 = Port::factory()->ethernet()->create(['device_id' => $destDevice2->id, 'label' => 'port2']);

    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($this->file)
        ->create([
            'source_device_id' => $sourceDevice2->id,
            'source_port_id' => $sourcePort2->id,
            'dest_device_id' => $destDevice2->id,
            'dest_port_id' => $destPort2->id,
        ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$this->file->id}/comparison");

    $response->assertOk();
    $data = $response->json('data');

    // Should have at least 2 results (matched and missing)
    expect(count($data))->toBeGreaterThanOrEqual(2);

    // Each result should have the required structure for ComparisonTable component
    foreach ($data as $item) {
        expect($item)->toHaveKeys([
            'discrepancy_type',
            'discrepancy_type_label',
            'source_device',
            'source_port',
            'dest_device',
            'dest_port',
            'is_acknowledged',
        ]);
    }

    // Verify we have matched and missing types
    $types = array_column($data, 'discrepancy_type');
    expect($types)->toContain('matched');
    expect($types)->toContain('missing');
});

it('returns correct row styling data based on discrepancy type', function () {
    // Create a matched connection
    $sourceDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $destDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($this->file)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice->id,
            'dest_port_id' => $destPort->id,
        ]);

    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$this->file->id}/comparison");

    $response->assertOk();
    $data = $response->json('data');

    // Find the matched result
    $matchedResult = collect($data)->firstWhere('discrepancy_type', 'matched');
    expect($matchedResult)->not->toBeNull();
    expect($matchedResult['discrepancy_type_label'])->toBe('Matched');

    // Verify DiscrepancyType enum provides all labels
    foreach (DiscrepancyType::cases() as $type) {
        expect($type->label())->toBeString();
        expect($type->label())->not->toBeEmpty();
    }
});

it('returns expected vs actual value display data for mismatched connections', function () {
    // Create a mismatched connection (source matches, destination differs)
    $sourceDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $destDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $wrongDestDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id, 'label' => 'eth0']);
    $expectedDestPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id, 'label' => 'expected-port']);
    $actualDestPort = Port::factory()->ethernet()->create(['device_id' => $wrongDestDevice->id, 'label' => 'actual-port']);

    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($this->file)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice->id,
            'dest_port_id' => $expectedDestPort->id,
        ]);

    // Create actual connection with different destination
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $actualDestPort->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$this->file->id}/comparison");

    $response->assertOk();
    $data = $response->json('data');

    // Find the mismatched result
    $mismatchedResult = collect($data)->firstWhere('discrepancy_type', 'mismatched');
    expect($mismatchedResult)->not->toBeNull();

    // Verify expected and actual values are present for comparison display
    expect($mismatchedResult['expected_connection'])->not->toBeNull();
    expect($mismatchedResult['actual_connection'])->not->toBeNull();
    expect($mismatchedResult['dest_port'])->not->toBeNull();
});

it('returns conflict indicator data for conflicting rows', function () {
    // Create two approved implementation files for the same datacenter
    $file1 = $this->file;
    $file2 = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);

    // Create a shared source port
    $sourceDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $destDevice1 = Device::factory()->create(['rack_id' => $this->rack->id]);
    $destDevice2 = Device::factory()->create(['rack_id' => $this->rack->id]);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort1 = Port::factory()->ethernet()->create(['device_id' => $destDevice1->id, 'label' => 'dest-1']);
    $destPort2 = Port::factory()->ethernet()->create(['device_id' => $destDevice2->id, 'label' => 'dest-2']);

    // File 1 expects source to connect to dest-1
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file1)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice1->id,
            'dest_port_id' => $destPort1->id,
        ]);

    // File 2 expects same source to connect to dest-2 (conflict!)
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file2)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice2->id,
            'dest_port_id' => $destPort2->id,
        ]);

    // Use datacenter comparison which should detect conflicts
    $response = $this->actingAs($this->user)
        ->getJson("/api/datacenters/{$this->datacenter->id}/connection-comparison");

    $response->assertOk();
    $data = $response->json('data');

    // Find any conflicting results
    $conflictingResults = collect($data)->where('discrepancy_type', 'conflicting');
    expect($conflictingResults->count())->toBeGreaterThan(0);

    // Verify conflict_info is present
    $conflictResult = $conflictingResults->first();
    expect($conflictResult)->toHaveKey('conflict_info');
});

it('returns acknowledged status with muted styling data', function () {
    // Create a missing expected connection
    $sourceDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $destDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    $expectedConnection = ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($this->file)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice->id,
            'dest_port_id' => $destPort->id,
        ]);

    // Create acknowledgment for this discrepancy
    DiscrepancyAcknowledgment::factory()
        ->missing()
        ->acknowledgedByUser($this->user)
        ->create([
            'expected_connection_id' => $expectedConnection->id,
            'notes' => 'Acknowledged for testing.',
        ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$this->file->id}/comparison");

    $response->assertOk();
    $data = $response->json('data');

    // Find the acknowledged result
    $acknowledgedResult = collect($data)->firstWhere('is_acknowledged', true);
    expect($acknowledgedResult)->not->toBeNull();

    // Verify acknowledgment data is present
    expect($acknowledgedResult['acknowledgment'])->not->toBeNull();
    expect($acknowledgedResult['acknowledgment']['notes'])->toBe('Acknowledged for testing.');
});

it('returns action button visibility data based on discrepancy type', function () {
    // Create devices for multiple discrepancy types
    $sourceDevice1 = Device::factory()->create(['rack_id' => $this->rack->id]);
    $destDevice1 = Device::factory()->create(['rack_id' => $this->rack->id]);
    $sourcePort1 = Port::factory()->ethernet()->create(['device_id' => $sourceDevice1->id]);
    $destPort1 = Port::factory()->ethernet()->create(['device_id' => $destDevice1->id]);

    $sourceDevice2 = Device::factory()->create(['rack_id' => $this->rack->id]);
    $destDevice2 = Device::factory()->create(['rack_id' => $this->rack->id]);
    $sourcePort2 = Port::factory()->ethernet()->create(['device_id' => $sourceDevice2->id]);
    $destPort2 = Port::factory()->ethernet()->create(['device_id' => $destDevice2->id]);

    // Create missing expected connection (should show "Create Connection" button)
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($this->file)
        ->create([
            'source_device_id' => $sourceDevice1->id,
            'source_port_id' => $sourcePort1->id,
            'dest_device_id' => $destDevice1->id,
            'dest_port_id' => $destPort1->id,
        ]);

    // Create matched expected connection (should show "View Details" button)
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($this->file)
        ->create([
            'source_device_id' => $sourceDevice2->id,
            'source_port_id' => $sourcePort2->id,
            'dest_device_id' => $destDevice2->id,
            'dest_port_id' => $destPort2->id,
        ]);

    Connection::factory()->create([
        'source_port_id' => $sourcePort2->id,
        'destination_port_id' => $destPort2->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$this->file->id}/comparison");

    $response->assertOk();
    $data = $response->json('data');

    // Verify missing type has expected_connection data for "Create Connection" action
    $missingResult = collect($data)->firstWhere('discrepancy_type', 'missing');
    expect($missingResult)->not->toBeNull();
    expect($missingResult['expected_connection'])->not->toBeNull();

    // Verify matched type has actual_connection data for "View Details" action
    $matchedResult = collect($data)->firstWhere('discrepancy_type', 'matched');
    expect($matchedResult)->not->toBeNull();
    expect($matchedResult['actual_connection'])->not->toBeNull();
});
