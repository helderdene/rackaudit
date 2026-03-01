<?php

/**
 * End-to-end tests for the Connection Comparison feature.
 *
 * These tests cover critical workflows and edge cases identified
 * during the test review and gap analysis (Task Group 10).
 */

use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\ExpectedConnection;
use App\Models\ImplementationFile;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('Administrator');

    // Create datacenter hierarchy
    $this->datacenter = Datacenter::factory()->create();
    $this->room = Room::factory()->create(['datacenter_id' => $this->datacenter->id]);
    $this->row = Row::factory()->create(['room_id' => $this->room->id]);
    $this->rack = Rack::factory()->create(['row_id' => $this->row->id]);
});

/**
 * Test 1: End-to-end workflow for creating a connection from missing status.
 *
 * Verifies that:
 * - A missing expected connection shows in comparison
 * - After creating the actual connection, status updates to matched
 */
it('updates comparison status from missing to matched after creating connection', function () {
    // Create an approved implementation file
    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);

    // Create devices and ports
    $sourceDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $destDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    // Create expected connection (no actual connection exists yet = missing)
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice->id,
            'dest_port_id' => $destPort->id,
        ]);

    // Step 1: Verify the comparison shows "missing" status
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison");

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['discrepancy_type'])->toBe('missing');

    // Step 2: Create the actual connection (simulating user action from comparison view)
    $createResponse = $this->actingAs($this->user)
        ->postJson('/connections', [
            'source_port_id' => $sourcePort->id,
            'destination_port_id' => $destPort->id,
            'cable_type' => 'cat6',
            'cable_length' => 3.0,
        ]);

    $createResponse->assertCreated();

    // Step 3: Verify comparison now shows "matched" status
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison");

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['discrepancy_type'])->toBe('matched');
});

/**
 * Test 2: End-to-end workflow for deleting an unexpected connection.
 *
 * Verifies that:
 * - An unexpected actual connection shows in comparison
 * - After deleting it, the connection is removed from comparison results
 */
it('removes unexpected connection from comparison after deletion', function () {
    // Create an approved implementation file
    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);

    // Create devices and ports for expected connection
    $expectedSourceDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $expectedDestDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $expectedSourcePort = Port::factory()->ethernet()->create(['device_id' => $expectedSourceDevice->id]);
    $expectedDestPort = Port::factory()->ethernet()->create(['device_id' => $expectedDestDevice->id]);

    // Create expected connection (with matching actual - this is matched)
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $expectedSourceDevice->id,
            'source_port_id' => $expectedSourcePort->id,
            'dest_device_id' => $expectedDestDevice->id,
            'dest_port_id' => $expectedDestPort->id,
        ]);

    Connection::factory()->create([
        'source_port_id' => $expectedSourcePort->id,
        'destination_port_id' => $expectedDestPort->id,
    ]);

    // Create an unexpected actual connection (devices in same datacenter but no expected connection)
    $unexpectedSourceDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $unexpectedDestDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $unexpectedSourcePort = Port::factory()->ethernet()->create(['device_id' => $unexpectedSourceDevice->id]);
    $unexpectedDestPort = Port::factory()->ethernet()->create(['device_id' => $unexpectedDestDevice->id]);

    $unexpectedConnection = Connection::factory()->create([
        'source_port_id' => $unexpectedSourcePort->id,
        'destination_port_id' => $unexpectedDestPort->id,
    ]);

    // Step 1: Verify datacenter comparison shows both matched and unexpected
    $response = $this->actingAs($this->user)
        ->getJson("/api/datacenters/{$this->datacenter->id}/connection-comparison");

    $response->assertOk();
    $stats = $response->json('statistics');
    expect($stats['matched'])->toBe(1);
    expect($stats['unexpected'])->toBe(1);

    // Step 2: Delete the unexpected connection
    $deleteResponse = $this->actingAs($this->user)
        ->deleteJson("/connections/{$unexpectedConnection->id}");

    $deleteResponse->assertOk();

    // Step 3: Verify comparison no longer shows the unexpected connection
    $response = $this->actingAs($this->user)
        ->getJson("/api/datacenters/{$this->datacenter->id}/connection-comparison");

    $response->assertOk();
    $stats = $response->json('statistics');
    expect($stats['matched'])->toBe(1);
    expect($stats['unexpected'])->toBe(0);
});

/**
 * Test 3: End-to-end workflow for acknowledging a discrepancy.
 *
 * Verifies that:
 * - Discrepancy can be acknowledged
 * - Acknowledged status shows in comparison results
 * - Show/hide acknowledged filter works
 */
it('acknowledges discrepancy and respects show_acknowledged filter', function () {
    // Create an approved implementation file
    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);

    // Create a missing expected connection
    $sourceDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $destDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    $expectedConnection = ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $sourceDevice->id,
            'source_port_id' => $sourcePort->id,
            'dest_device_id' => $destDevice->id,
            'dest_port_id' => $destPort->id,
        ]);

    // Step 1: Verify the comparison shows missing and not acknowledged
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison");

    $response->assertOk();
    $data = $response->json('data');
    expect($data[0]['is_acknowledged'])->toBeFalse();

    // Step 2: Acknowledge the discrepancy
    $ackResponse = $this->actingAs($this->user)
        ->postJson('/api/discrepancy-acknowledgments', [
            'expected_connection_id' => $expectedConnection->id,
            'discrepancy_type' => 'missing',
            'notes' => 'Deferred until next maintenance window.',
        ]);

    $ackResponse->assertCreated();

    // Step 3: Verify acknowledged status now shows
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison");

    $response->assertOk();
    $data = $response->json('data');
    expect($data[0]['is_acknowledged'])->toBeTrue();
    expect($data[0]['acknowledgment']['notes'])->toBe('Deferred until next maintenance window.');

    // Step 4: Verify show_acknowledged=false hides acknowledged discrepancies
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison?show_acknowledged=0");

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(0);

    // Step 5: Verify show_acknowledged=true still shows them (default behavior)
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison?show_acknowledged=1");

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);
});

/**
 * Test 4: Edge case - empty comparison (no expected or actual connections).
 *
 * Verifies that:
 * - Comparison handles empty state gracefully
 * - Statistics show all zeros
 */
it('handles empty comparison gracefully with no expected or actual connections', function () {
    // Create an approved implementation file but NO expected connections
    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);

    // Request comparison with no data
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison");

    $response->assertOk();
    $data = $response->json('data');
    $stats = $response->json('statistics');

    // Should return empty results
    expect($data)->toBeArray();
    expect(count($data))->toBe(0);

    // Statistics should all be zero
    expect($stats['total'])->toBe(0);
    expect($stats['matched'])->toBe(0);
    expect($stats['missing'])->toBe(0);
    expect($stats['unexpected'])->toBe(0);
    expect($stats['mismatched'])->toBe(0);
    expect($stats['conflicting'])->toBe(0);
});

/**
 * Test 5: Edge case - all connections matched.
 *
 * Verifies that:
 * - When all expected connections have matching actual connections
 * - Statistics correctly show all matched
 */
it('shows all connections as matched when all expected have actual', function () {
    // Create an approved implementation file
    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);

    // Create 3 expected connections, all with matching actual connections
    for ($i = 0; $i < 3; $i++) {
        $sourceDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
        $destDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
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

        // Create matching actual connection
        Connection::factory()->create([
            'source_port_id' => $sourcePort->id,
            'destination_port_id' => $destPort->id,
        ]);
    }

    // Request comparison
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison");

    $response->assertOk();
    $data = $response->json('data');
    $stats = $response->json('statistics');

    // All should be matched
    expect(count($data))->toBe(3);
    expect($stats['total'])->toBe(3);
    expect($stats['matched'])->toBe(3);
    expect($stats['missing'])->toBe(0);
    expect($stats['unexpected'])->toBe(0);
    expect($stats['mismatched'])->toBe(0);
    expect($stats['conflicting'])->toBe(0);

    // Verify all items have matched type
    foreach ($data as $item) {
        expect($item['discrepancy_type'])->toBe('matched');
    }
});

/**
 * Test 6: Filter combinations work correctly together.
 *
 * Verifies that:
 * - Multiple filter types can be combined
 * - Combined filters narrow results correctly
 */
it('applies multiple filter combinations correctly', function () {
    // Create an approved implementation file
    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);

    // Create two racks for filtering
    $rack1 = $this->rack;
    $rack2 = Rack::factory()->create(['row_id' => $this->row->id]);

    // Create a matched connection in rack1
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

    Connection::factory()->create([
        'source_port_id' => $sourcePort1->id,
        'destination_port_id' => $destPort1->id,
    ]);

    // Create a missing connection in rack2
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

    // Without filters - should return 2 results
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison");

    $response->assertOk();
    expect(count($response->json('data')))->toBe(2);

    // Filter by discrepancy type only (matched)
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison?discrepancy_type[]=matched");

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['discrepancy_type'])->toBe('matched');

    // Filter by rack only (rack2)
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison?rack_id={$rack2->id}");

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['discrepancy_type'])->toBe('missing');

    // Combined: matched + rack1 - should return 1 (matched connection is in rack1)
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison?discrepancy_type[]=matched&rack_id={$rack1->id}");

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['discrepancy_type'])->toBe('matched');

    // Combined: matched + rack2 - should return 0 (matched is in rack1, not rack2)
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison?discrepancy_type[]=matched&rack_id={$rack2->id}");

    $response->assertOk();
    expect(count($response->json('data')))->toBe(0);

    // Combined: missing + rack2 - should return 1 (missing is in rack2)
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison?discrepancy_type[]=missing&rack_id={$rack2->id}");

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['discrepancy_type'])->toBe('missing');
});

/**
 * Test 7: Deleting a matched connection updates status to missing.
 *
 * Verifies the reverse of Test 1:
 * - A matched connection exists
 * - After deleting the actual connection, status becomes missing
 */
it('updates comparison status from matched to missing after deleting connection', function () {
    // Create an approved implementation file
    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);

    // Create devices and ports
    $sourceDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $destDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    // Create expected connection
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
    $connection = Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    // Step 1: Verify the comparison shows "matched" status
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison");

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['discrepancy_type'])->toBe('matched');

    // Step 2: Delete the actual connection
    $deleteResponse = $this->actingAs($this->user)
        ->deleteJson("/connections/{$connection->id}");

    $deleteResponse->assertOk();

    // Step 3: Verify comparison now shows "missing" status
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison");

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['discrepancy_type'])->toBe('missing');
});

/**
 * Test 8: Datacenter comparison with empty file (file exists but no confirmed connections).
 *
 * Verifies that:
 * - Approved files without confirmed connections are handled gracefully
 * - Only unexpected actuals in the datacenter would show
 */
it('handles datacenter comparison when approved files have no confirmed connections', function () {
    // Create an approved implementation file with NO confirmed expected connections
    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);

    // Create an actual connection in the datacenter (will be unexpected)
    $sourceDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $destDevice = Device::factory()->create(['rack_id' => $this->rack->id]);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id]);

    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    // Request datacenter comparison
    $response = $this->actingAs($this->user)
        ->getJson("/api/datacenters/{$this->datacenter->id}/connection-comparison");

    $response->assertOk();
    $data = $response->json('data');
    $stats = $response->json('statistics');

    // Should show the unexpected connection
    expect(count($data))->toBe(1);
    expect($data[0]['discrepancy_type'])->toBe('unexpected');
    expect($stats['unexpected'])->toBe(1);
    expect($stats['matched'])->toBe(0);
    expect($stats['missing'])->toBe(0);
});

/**
 * Test 9: Device filter works correctly across comparison.
 *
 * Verifies that:
 * - Filtering by device_id returns only connections involving that device
 * - Works for both source and destination devices
 */
it('filters comparison by device correctly for both source and destination', function () {
    // Create an approved implementation file
    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);

    // Create three connections: Device A -> B, Device C -> D, Device A -> D
    $deviceA = Device::factory()->create(['rack_id' => $this->rack->id, 'name' => 'Device A']);
    $deviceB = Device::factory()->create(['rack_id' => $this->rack->id, 'name' => 'Device B']);
    $deviceC = Device::factory()->create(['rack_id' => $this->rack->id, 'name' => 'Device C']);
    $deviceD = Device::factory()->create(['rack_id' => $this->rack->id, 'name' => 'Device D']);

    $portA = Port::factory()->ethernet()->create(['device_id' => $deviceA->id]);
    $portB = Port::factory()->ethernet()->create(['device_id' => $deviceB->id]);
    $portC = Port::factory()->ethernet()->create(['device_id' => $deviceC->id]);
    $portD1 = Port::factory()->ethernet()->create(['device_id' => $deviceD->id]);
    $portA2 = Port::factory()->ethernet()->create(['device_id' => $deviceA->id]);
    $portD2 = Port::factory()->ethernet()->create(['device_id' => $deviceD->id]);

    // Connection 1: A -> B
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $deviceA->id,
            'source_port_id' => $portA->id,
            'dest_device_id' => $deviceB->id,
            'dest_port_id' => $portB->id,
        ]);

    // Connection 2: C -> D
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $deviceC->id,
            'source_port_id' => $portC->id,
            'dest_device_id' => $deviceD->id,
            'dest_port_id' => $portD1->id,
        ]);

    // Connection 3: A -> D
    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $deviceA->id,
            'source_port_id' => $portA2->id,
            'dest_device_id' => $deviceD->id,
            'dest_port_id' => $portD2->id,
        ]);

    // Filter by Device A - should return 2 (A->B and A->D)
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison?device_id={$deviceA->id}");

    $response->assertOk();
    expect(count($response->json('data')))->toBe(2);

    // Filter by Device D - should return 2 (C->D and A->D, Device D is destination)
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison?device_id={$deviceD->id}");

    $response->assertOk();
    expect(count($response->json('data')))->toBe(2);

    // Filter by Device B - should return 1 (only A->B)
    $response = $this->actingAs($this->user)
        ->getJson("/api/implementation-files/{$file->id}/comparison?device_id={$deviceB->id}");

    $response->assertOk();
    expect(count($response->json('data')))->toBe(1);
});
