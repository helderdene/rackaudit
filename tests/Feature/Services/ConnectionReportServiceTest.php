<?php

use App\Enums\CableType;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use App\Services\ConnectionReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('generatePdfReport creates valid PDF file', function () {
    Storage::fake('local');

    $service = app(ConnectionReportService::class);
    $user = User::factory()->create();

    // Create datacenter hierarchy with connections
    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Server Room 1']);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    // Create connections
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    Connection::factory()->count(3)->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_type' => CableType::Cat6,
        'cable_length' => 5.0,
        'cable_color' => 'blue',
    ]);

    $filters = [
        'datacenter_id' => $datacenter->id,
    ];

    $filePath = $service->generatePdfReport($filters, $user);

    // Verify file path format
    expect($filePath)->toStartWith('reports/connections/');
    expect($filePath)->toEndWith('.pdf');
    expect($filePath)->toContain('connection-report-');

    // Verify file was stored
    Storage::disk('local')->assertExists($filePath);
});

test('buildFilterScope returns correct description', function () {
    $service = app(ConnectionReportService::class);

    // Test with no filters
    $scope = $service->buildFilterScope([]);
    expect($scope)->toBe('All Connections');

    // Test with datacenter filter
    $datacenter = Datacenter::factory()->create(['name' => 'Primary DC']);
    $scope = $service->buildFilterScope(['datacenter_id' => $datacenter->id]);
    expect($scope)->toContain('Datacenter: Primary DC');

    // Test with room filter (includes datacenter name)
    $room = Room::factory()->create([
        'datacenter_id' => $datacenter->id,
        'name' => 'Network Room',
    ]);
    $scope = $service->buildFilterScope(['room_id' => $room->id]);
    expect($scope)->toContain('Room: Network Room');
    expect($scope)->toContain('Datacenter: Primary DC');
});

test('getConnectionInventory returns formatted connection data', function () {
    $service = app(ConnectionReportService::class);

    // Create datacenter hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $sourceDevice = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Server-01']);
    $destDevice = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Switch-01']);

    // Create ports and connection
    $sourcePort = Port::factory()->ethernet()->create([
        'device_id' => $sourceDevice->id,
        'label' => 'eth0',
    ]);
    $destPort = Port::factory()->ethernet()->create([
        'device_id' => $destDevice->id,
        'label' => 'port1',
    ]);
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_type' => CableType::Cat6,
        'cable_length' => 3.5,
        'cable_color' => 'yellow',
    ]);

    $inventory = $service->getConnectionInventory($datacenter->id);

    expect($inventory)->toHaveCount(1);

    $connection = $inventory->first();
    expect($connection)->toHaveKeys([
        'source_device',
        'source_port',
        'destination_device',
        'destination_port',
        'cable_type',
        'cable_length',
        'cable_color',
    ]);
    expect($connection['source_device'])->toBe('Server-01');
    expect($connection['source_port'])->toBe('eth0');
    expect($connection['destination_device'])->toBe('Switch-01');
    expect($connection['destination_port'])->toBe('port1');
    expect($connection['cable_type'])->toBe('Cat6');
    expect($connection['cable_length'])->toBe('3.50');
    expect($connection['cable_color'])->toBe('yellow');
});

test('storeReport saves to correct path', function () {
    Storage::fake('local');

    $service = app(ConnectionReportService::class);
    $user = User::factory()->create();

    // Create minimal datacenter hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id]);

    // Create at least one connection
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $device->id]);
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
    ]);

    $filePath = $service->generatePdfReport([], $user);

    // Verify stored in correct directory
    expect($filePath)->toStartWith('reports/connections/');

    // Verify filename format: connection-report-{YmdHis}.pdf
    $filename = basename($filePath);
    expect($filename)->toMatch('/^connection-report-\d{14}\.pdf$/');

    // Verify file exists in storage
    Storage::disk('local')->assertExists($filePath);
});
