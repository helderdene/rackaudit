<?php

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
});

/**
 * Helper function to get content from a download response.
 * Uses output buffering to capture streamed content.
 */
function getExportContent($response): string
{
    ob_start();
    $response->sendContent();

    return ob_get_clean();
}

/**
 * Helper function to parse CSV lines from export content.
 * Handles BOM, separator line, Windows line endings, and semicolon delimiter.
 */
function parseExportLines(string $content): array
{
    // Remove BOM if present
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

    // Split by Windows line endings
    $lines = preg_split('/\r?\n/', $content);

    // Filter out empty lines and the sep= line
    $lines = array_filter($lines, function ($line) {
        $trimmed = trim($line);

        return $trimmed !== '' && ! str_starts_with($trimmed, 'sep=');
    });

    return array_values($lines);
}

/**
 * Helper function to parse CSV row using semicolon delimiter.
 */
function parseCsvRow(string $row): array
{
    return str_getcsv($row, ';', '"');
}

it('exports CSV with correct headers', function () {
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
        ->get("/api/implementation-files/{$file->id}/comparison/export");

    $response->assertOk();

    // Check Content-Disposition header for download filename
    $contentDisposition = $response->headers->get('Content-Disposition');
    expect($contentDisposition)->toContain('attachment');
    expect($contentDisposition)->toContain('.csv');

    // Verify headers in the CSV content
    $content = getExportContent($response);
    $lines = parseExportLines($content);
    $headers = parseCsvRow($lines[0]);

    expect($headers)->toContain('Source Device');
    expect($headers)->toContain('Source Port');
    expect($headers)->toContain('Dest Device');
    expect($headers)->toContain('Dest Port');
    expect($headers)->toContain('Expected Cable Type');
    expect($headers)->toContain('Actual Cable Type');
    expect($headers)->toContain('Discrepancy Type');
    expect($headers)->toContain('Acknowledged');
    expect($headers)->toContain('Notes');
});

it('exports CSV with all comparison data fields', function () {
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
    $sourceDevice = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Server-001']);
    $destDevice = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Switch-001']);
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $sourceDevice->id, 'label' => 'eth0']);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $destDevice->id, 'label' => 'port1']);

    // Create a confirmed expected connection (matched)
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

    // Create a missing expected connection
    $sourceDevice2 = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Server-002']);
    $destDevice2 = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Switch-002']);
    $sourcePort2 = Port::factory()->ethernet()->create(['device_id' => $sourceDevice2->id, 'label' => 'eth1']);
    $destPort2 = Port::factory()->ethernet()->create(['device_id' => $destDevice2->id, 'label' => 'port2']);

    ExpectedConnection::factory()
        ->confirmed()
        ->forImplementationFile($file)
        ->create([
            'source_device_id' => $sourceDevice2->id,
            'source_port_id' => $sourcePort2->id,
            'dest_device_id' => $destDevice2->id,
            'dest_port_id' => $destPort2->id,
        ]);

    $response = $this->actingAs($this->user)
        ->get("/api/implementation-files/{$file->id}/comparison/export");

    $response->assertOk();

    // Verify data rows
    $content = getExportContent($response);
    $lines = parseExportLines($content);

    // Should have header + 2 data rows
    expect(count($lines))->toBeGreaterThanOrEqual(3);

    // Verify matched row contains correct data
    expect($content)->toContain('Server-001');
    expect($content)->toContain('eth0');
    expect($content)->toContain('Switch-001');
    expect($content)->toContain('port1');
    expect($content)->toContain('Matched');

    // Verify missing row contains correct data
    expect($content)->toContain('Server-002');
    expect($content)->toContain('Missing');
});

it('exports CSV respecting current filter selections', function () {
    // Create datacenter with hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create an approved implementation file
    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    // Create a matched connection
    $sourceDevice1 = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Matched-Device']);
    $destDevice1 = Device::factory()->create(['rack_id' => $rack->id]);
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

    // Create a missing connection
    $sourceDevice2 = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Missing-Device']);
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

    // Export with filter for only 'missing' discrepancy type
    $response = $this->actingAs($this->user)
        ->get("/api/implementation-files/{$file->id}/comparison/export?discrepancy_type[]=missing");

    $response->assertOk();

    $content = getExportContent($response);

    // Should only contain missing, not matched
    expect($content)->toContain('Missing');
    expect($content)->toContain('Missing-Device');
    expect($content)->not->toContain('Matched-Device');
});

it('exports CSV including acknowledged status', function () {
    // Create datacenter with hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create an approved implementation file
    $file = ImplementationFile::factory()->xlsx()->approved()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    // Create a missing expected connection
    $sourceDevice = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Ack-Device']);
    $destDevice = Device::factory()->create(['rack_id' => $rack->id]);
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

    // Create acknowledgment for this expected connection
    DiscrepancyAcknowledgment::factory()
        ->missing()
        ->acknowledgedByUser($this->user)
        ->create([
            'expected_connection_id' => $expectedConnection->id,
            'notes' => 'Deferred until next maintenance window.',
        ]);

    $response = $this->actingAs($this->user)
        ->get("/api/implementation-files/{$file->id}/comparison/export");

    $response->assertOk();

    $content = getExportContent($response);

    // Should show acknowledged status
    expect($content)->toContain('Yes');
    expect($content)->toContain('Deferred until next maintenance window.');
});
