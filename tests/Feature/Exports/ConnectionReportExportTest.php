<?php

use App\Enums\CableType;
use App\Exports\ConnectionReportExport;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

test('headings returns correct column names for connection report', function () {
    $export = new ConnectionReportExport;

    $headings = $export->headings();

    expect($headings)->toBe([
        'Source Device',
        'Source Port',
        'Destination Device',
        'Destination Port',
        'Cable Type',
        'Cable Length',
        'Cable Color',
    ]);
});

test('query applies datacenter filter correctly', function () {
    // Create hierarchy for datacenter 1
    $datacenter1 = Datacenter::factory()->create();
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter1->id]);
    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $rack1 = Rack::factory()->create(['row_id' => $row1->id]);
    $device1 = Device::factory()->create(['rack_id' => $rack1->id, 'name' => 'Device DC1']);

    // Create hierarchy for datacenter 2
    $datacenter2 = Datacenter::factory()->create();
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter2->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);
    $rack2 = Rack::factory()->create(['row_id' => $row2->id]);
    $device2 = Device::factory()->create(['rack_id' => $rack2->id, 'name' => 'Device DC2']);

    // Create 2 connections in datacenter 1
    $sourcePort1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    $destPort1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    Connection::factory()->count(2)->create([
        'source_port_id' => $sourcePort1->id,
        'destination_port_id' => $destPort1->id,
    ]);

    // Create 3 connections in datacenter 2
    $sourcePort2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);
    $destPort2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);
    Connection::factory()->count(3)->create([
        'source_port_id' => $sourcePort2->id,
        'destination_port_id' => $destPort2->id,
    ]);

    // Export only connections from datacenter 1
    $export = new ConnectionReportExport(['datacenter_id' => $datacenter1->id]);
    Excel::store($export, 'connections_export.xlsx', 'local');

    $filePath = Storage::disk('local')->path('connections_export.xlsx');
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Count data rows (excluding header)
    $rowCount = 0;
    $row = 2;
    while ($sheet->getCellByColumnAndRow(1, $row)->getValue() !== null) {
        $rowCount++;
        $row++;
    }

    expect($rowCount)->toBe(2);
});

test('query applies room filter correctly', function () {
    // Create hierarchy with 2 rooms in same datacenter
    $datacenter = Datacenter::factory()->create();

    $room1 = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $rack1 = Rack::factory()->create(['row_id' => $row1->id]);
    $device1 = Device::factory()->create(['rack_id' => $rack1->id, 'name' => 'Device Room1']);

    $room2 = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);
    $rack2 = Rack::factory()->create(['row_id' => $row2->id]);
    $device2 = Device::factory()->create(['rack_id' => $rack2->id, 'name' => 'Device Room2']);

    // Create 1 connection in room 1
    $sourcePort1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    $destPort1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    Connection::factory()->create([
        'source_port_id' => $sourcePort1->id,
        'destination_port_id' => $destPort1->id,
    ]);

    // Create 4 connections in room 2
    $sourcePort2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);
    $destPort2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);
    Connection::factory()->count(4)->create([
        'source_port_id' => $sourcePort2->id,
        'destination_port_id' => $destPort2->id,
    ]);

    // Export only connections from room 2
    $export = new ConnectionReportExport([
        'datacenter_id' => $datacenter->id,
        'room_id' => $room2->id,
    ]);
    Excel::store($export, 'connections_room_export.xlsx', 'local');

    $filePath = Storage::disk('local')->path('connections_room_export.xlsx');
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Count data rows (excluding header)
    $rowCount = 0;
    $row = 2;
    while ($sheet->getCellByColumnAndRow(1, $row)->getValue() !== null) {
        $rowCount++;
        $row++;
    }

    expect($rowCount)->toBe(4);
});

test('transformRow formats connection data correctly', function () {
    // Create hierarchy
    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create source and destination devices
    $sourceDevice = Device::factory()->create([
        'rack_id' => $rack->id,
        'name' => 'Source Server',
    ]);
    $destDevice = Device::factory()->create([
        'rack_id' => $rack->id,
        'name' => 'Destination Switch',
    ]);

    // Create ports with specific labels
    $sourcePort = Port::factory()->ethernet()->create([
        'device_id' => $sourceDevice->id,
        'label' => 'eth0',
    ]);
    $destPort = Port::factory()->ethernet()->create([
        'device_id' => $destDevice->id,
        'label' => 'port-24',
    ]);

    // Create connection with specific cable data
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_type' => CableType::Cat6a,
        'cable_length' => 5.50,
        'cable_color' => 'blue',
    ]);

    // Export the connection
    $export = new ConnectionReportExport;
    Excel::store($export, 'connection_data_export.xlsx', 'local');

    $filePath = Storage::disk('local')->path('connection_data_export.xlsx');
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Verify row 2 contains the expected data
    $sourceDeviceName = $sheet->getCellByColumnAndRow(1, 2)->getValue();
    $sourcePortLabel = $sheet->getCellByColumnAndRow(2, 2)->getValue();
    $destDeviceName = $sheet->getCellByColumnAndRow(3, 2)->getValue();
    $destPortLabel = $sheet->getCellByColumnAndRow(4, 2)->getValue();
    $cableType = $sheet->getCellByColumnAndRow(5, 2)->getValue();
    $cableLength = $sheet->getCellByColumnAndRow(6, 2)->getValue();
    $cableColor = $sheet->getCellByColumnAndRow(7, 2)->getValue();

    expect($sourceDeviceName)->toBe('Source Server');
    expect($sourcePortLabel)->toBe('eth0');
    expect($destDeviceName)->toBe('Destination Switch');
    expect($destPortLabel)->toBe('port-24');
    expect($cableType)->toBe('Cat6a');
    expect($cableLength)->toBe('5.50 m');
    expect($cableColor)->toBe('blue');
});
