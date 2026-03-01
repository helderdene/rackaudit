<?php

/**
 * Tests for ComparisonFilters.vue component functionality
 *
 * These tests verify that the filter controls work correctly for the comparison view,
 * including multi-select for discrepancy types, device/rack dropdowns, and URL persistence.
 */

use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
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

    // Create datacenter hierarchy
    $this->datacenter = Datacenter::factory()->create();
    $this->room = Room::factory()->create(['datacenter_id' => $this->datacenter->id]);
    $this->row = Row::factory()->create(['room_id' => $this->room->id]);
    $this->rack1 = Rack::factory()->create(['row_id' => $this->row->id, 'name' => 'Rack A']);
    $this->rack2 = Rack::factory()->create(['row_id' => $this->row->id, 'name' => 'Rack B']);

    // Create approved implementation file
    $this->file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);

    // Create device type
    $this->deviceType = DeviceType::factory()->create();

    // Create devices in different racks
    $this->device1 = Device::factory()->create([
        'rack_id' => $this->rack1->id,
        'device_type_id' => $this->deviceType->id,
        'name' => 'Device Alpha',
    ]);
    $this->device2 = Device::factory()->create([
        'rack_id' => $this->rack1->id,
        'device_type_id' => $this->deviceType->id,
        'name' => 'Device Beta',
    ]);
    $this->device3 = Device::factory()->create([
        'rack_id' => $this->rack2->id,
        'device_type_id' => $this->deviceType->id,
        'name' => 'Device Gamma',
    ]);
    $this->device4 = Device::factory()->create([
        'rack_id' => $this->rack2->id,
        'device_type_id' => $this->deviceType->id,
        'name' => 'Device Delta',
    ]);

    // Create ports
    $this->port1 = Port::factory()->ethernet()->create(['device_id' => $this->device1->id]);
    $this->port2 = Port::factory()->ethernet()->create(['device_id' => $this->device2->id]);
    $this->port3 = Port::factory()->ethernet()->create(['device_id' => $this->device3->id]);
    $this->port4 = Port::factory()->ethernet()->create(['device_id' => $this->device4->id]);
});

it('filters by multiple discrepancy types via multi-select', function () {
    // Create expected connections with different discrepancy types

    // Create a matched connection (expected + actual)
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($this->file)
        ->create([
            'source_device_id' => $this->device1->id,
            'source_port_id' => $this->port1->id,
            'dest_device_id' => $this->device2->id,
            'dest_port_id' => $this->port2->id,
        ]);

    Connection::factory()->create([
        'source_port_id' => $this->port1->id,
        'destination_port_id' => $this->port2->id,
    ]);

    // Create a missing connection (expected but no actual)
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($this->file)
        ->create([
            'source_device_id' => $this->device3->id,
            'source_port_id' => $this->port3->id,
            'dest_device_id' => $this->device4->id,
            'dest_port_id' => $this->port4->id,
        ]);

    // Test filtering by matched only
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$this->file->id}/comparison?discrepancy_type[]=matched");

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['discrepancy_type'])->toBe('matched');

    // Test filtering by missing only
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$this->file->id}/comparison?discrepancy_type[]=missing");

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['discrepancy_type'])->toBe('missing');

    // Test filtering by multiple types (matched and missing)
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$this->file->id}/comparison?discrepancy_type[]=matched&discrepancy_type[]=missing");

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(2);

    $types = array_column($data, 'discrepancy_type');
    expect($types)->toContain('matched');
    expect($types)->toContain('missing');
});

it('populates device filter dropdown correctly from involved devices', function () {
    // Create expected connection with specific devices
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($this->file)
        ->create([
            'source_device_id' => $this->device1->id,
            'source_port_id' => $this->port1->id,
            'dest_device_id' => $this->device2->id,
            'dest_port_id' => $this->port2->id,
        ]);

    // Filter by device_id should return only connections involving that device
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$this->file->id}/comparison?device_id={$this->device1->id}");

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);

    // The comparison result should include the device we filtered by
    $result = $data[0];
    expect($result['source_device']['id'])->toBe($this->device1->id);

    // Filter by a different device not in the comparison should return no results
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$this->file->id}/comparison?device_id={$this->device3->id}");

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(0);
});

it('populates rack filter dropdown and filters correctly', function () {
    // Create expected connections in different racks
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($this->file)
        ->create([
            'source_device_id' => $this->device1->id,
            'source_port_id' => $this->port1->id,
            'dest_device_id' => $this->device2->id,
            'dest_port_id' => $this->port2->id,
        ]);

    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($this->file)
        ->create([
            'source_device_id' => $this->device3->id,
            'source_port_id' => $this->port3->id,
            'dest_device_id' => $this->device4->id,
            'dest_port_id' => $this->port4->id,
        ]);

    // Filter by rack1 should return only connections in rack1
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$this->file->id}/comparison?rack_id={$this->rack1->id}");

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);

    // Filter by rack2 should return only connections in rack2
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$this->file->id}/comparison?rack_id={$this->rack2->id}");

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);

    // Without filter should return all
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$this->file->id}/comparison");

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(2);
});

it('persists filter state in URL query parameters', function () {
    // Create two expected connections: one matched, one missing
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($this->file)
        ->create([
            'source_device_id' => $this->device1->id,
            'source_port_id' => $this->port1->id,
            'dest_device_id' => $this->device2->id,
            'dest_port_id' => $this->port2->id,
        ]);

    // Create matching actual connection
    Connection::factory()->create([
        'source_port_id' => $this->port1->id,
        'destination_port_id' => $this->port2->id,
    ]);

    // Create a missing connection (in rack2)
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($this->file)
        ->create([
            'source_device_id' => $this->device3->id,
            'source_port_id' => $this->port3->id,
            'dest_device_id' => $this->device4->id,
            'dest_port_id' => $this->port4->id,
        ]);

    // Test filtering by matched type and rack1 - should return the matched connection
    $url = "/api/implementation-files/{$this->file->id}/comparison?"
        . 'discrepancy_type[]=matched'
        . "&rack_id={$this->rack1->id}";

    $response = $this->actingAs($this->user)->getJson($url);

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['discrepancy_type'])->toBe('matched');

    // Test filtering by missing type and rack2 - should return the missing connection
    $url = "/api/implementation-files/{$this->file->id}/comparison?"
        . 'discrepancy_type[]=missing'
        . "&rack_id={$this->rack2->id}";

    $response = $this->actingAs($this->user)->getJson($url);

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['discrepancy_type'])->toBe('missing');

    // Test filtering by missing type and rack1 - should return no results
    // (the matched connection is in rack1, but we're filtering for missing)
    $url = "/api/implementation-files/{$this->file->id}/comparison?"
        . 'discrepancy_type[]=missing'
        . "&rack_id={$this->rack1->id}";

    $response = $this->actingAs($this->user)->getJson($url);

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(0);

    // Test that combinations of filters work correctly
    // Filtering for both matched and missing in all racks should return both
    $url = "/api/implementation-files/{$this->file->id}/comparison?"
        . 'discrepancy_type[]=matched&discrepancy_type[]=missing';

    $response = $this->actingAs($this->user)->getJson($url);

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(2);
});
