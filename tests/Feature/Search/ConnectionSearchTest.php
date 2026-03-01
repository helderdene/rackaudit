<?php

use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use App\Services\SearchService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->searchService = app(SearchService::class);

    // Create admin user for full access
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');
});

test('searching connections by source device name returns matching connections', function () {
    // Create datacenter hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Main DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create source and destination devices
    $sourceDevice = Device::factory()->create([
        'name' => 'WebServer-Alpha',
        'rack_id' => $rack->id,
    ]);
    $destinationDevice = Device::factory()->create([
        'name' => 'Switch-Beta',
        'rack_id' => $rack->id,
    ]);

    // Create ports on each device
    $sourcePort = Port::factory()->ethernet()->create([
        'device_id' => $sourceDevice->id,
        'label' => 'eth0',
    ]);
    $destinationPort = Port::factory()->ethernet()->create([
        'device_id' => $destinationDevice->id,
        'label' => 'port-1',
    ]);

    // Create connection between the ports
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destinationPort->id,
        'cable_color' => 'blue',
    ]);

    // Search by source device name
    $results = $this->searchService->search('WebServer-Alpha', $this->admin);

    expect($results['connections']['items'])->toHaveCount(1);
    expect($results['connections']['items'][0]['source_device_name'])->toBe('WebServer-Alpha');
    expect($results['connections']['items'][0]['matched_fields'])->toContain('source_device_name');
});

test('searching connections by destination device name returns matching connections', function () {
    // Create datacenter hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Main DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create source and destination devices
    $sourceDevice = Device::factory()->create([
        'name' => 'Server-One',
        'rack_id' => $rack->id,
    ]);
    $destinationDevice = Device::factory()->create([
        'name' => 'CoreSwitch-Gamma',
        'rack_id' => $rack->id,
    ]);

    // Create ports on each device
    $sourcePort = Port::factory()->ethernet()->create([
        'device_id' => $sourceDevice->id,
        'label' => 'eth1',
    ]);
    $destinationPort = Port::factory()->ethernet()->create([
        'device_id' => $destinationDevice->id,
        'label' => 'gi0/1',
    ]);

    // Create connection between the ports
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destinationPort->id,
        'cable_color' => 'yellow',
    ]);

    // Search by destination device name
    $results = $this->searchService->search('CoreSwitch-Gamma', $this->admin);

    expect($results['connections']['items'])->toHaveCount(1);
    expect($results['connections']['items'][0]['destination_device_name'])->toBe('CoreSwitch-Gamma');
    expect($results['connections']['items'][0]['matched_fields'])->toContain('destination_device_name');
});

test('searching connections by port label returns matching connections', function () {
    // Create datacenter hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Main DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create devices
    $device1 = Device::factory()->create([
        'name' => 'Device-A',
        'rack_id' => $rack->id,
    ]);
    $device2 = Device::factory()->create([
        'name' => 'Device-B',
        'rack_id' => $rack->id,
    ]);

    // Create ports with distinctive labels
    $sourcePort = Port::factory()->ethernet()->create([
        'device_id' => $device1->id,
        'label' => 'mgmt-unique-port',
    ]);
    $destinationPort = Port::factory()->ethernet()->create([
        'device_id' => $device2->id,
        'label' => 'uplink-01',
    ]);

    // Create connection
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destinationPort->id,
    ]);

    // Search by port label (source)
    $results = $this->searchService->search('mgmt-unique-port', $this->admin);

    expect($results['connections']['items'])->toHaveCount(1);
    expect($results['connections']['items'][0]['source_port_label'])->toBe('mgmt-unique-port');
    expect($results['connections']['items'][0]['matched_fields'])->toContain('source_port_label');

    // Search by port label (destination)
    $results2 = $this->searchService->search('uplink-01', $this->admin);

    expect($results2['connections']['items'])->toHaveCount(1);
    expect($results2['connections']['items'][0]['destination_port_label'])->toBe('uplink-01');
    expect($results2['connections']['items'][0]['matched_fields'])->toContain('destination_port_label');
});

test('connections between X and Y query pattern finds connections between two devices', function () {
    // Create datacenter hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Main DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create the two devices we want to find connections between
    $serverDevice = Device::factory()->create([
        'name' => 'Server-01',
        'rack_id' => $rack->id,
    ]);
    $switchDevice = Device::factory()->create([
        'name' => 'Switch-A',
        'rack_id' => $rack->id,
    ]);

    // Create another device for control (should not match between query)
    $otherDevice = Device::factory()->create([
        'name' => 'OtherDevice',
        'rack_id' => $rack->id,
    ]);

    // Create ports
    $serverPort = Port::factory()->ethernet()->create([
        'device_id' => $serverDevice->id,
        'label' => 'eth0',
    ]);
    $switchPort = Port::factory()->ethernet()->create([
        'device_id' => $switchDevice->id,
        'label' => 'gi0/1',
    ]);
    $otherPort = Port::factory()->ethernet()->create([
        'device_id' => $otherDevice->id,
        'label' => 'eth1',
    ]);
    $otherSwitchPort = Port::factory()->ethernet()->create([
        'device_id' => $switchDevice->id,
        'label' => 'gi0/2',
    ]);

    // Create connection between Server-01 and Switch-A
    $targetConnection = Connection::factory()->create([
        'source_port_id' => $serverPort->id,
        'destination_port_id' => $switchPort->id,
        'cable_color' => 'blue',
        'path_notes' => 'Primary uplink from Server-01 to Switch-A',
    ]);

    // Create another connection between OtherDevice and Switch-A (control)
    Connection::factory()->create([
        'source_port_id' => $otherPort->id,
        'destination_port_id' => $otherSwitchPort->id,
        'cable_color' => 'green',
    ]);

    // Use the searchConnectionsBetween method to find connections between the two devices
    $results = $this->searchService->searchConnectionsBetween('Server-01', 'Switch-A', $this->admin);

    expect($results['items'])->toHaveCount(1);
    expect($results['items'][0]['id'])->toBe($targetConnection->id);
    expect($results['items'][0]['source_device_name'])->toBe('Server-01');
    expect($results['items'][0]['destination_device_name'])->toBe('Switch-A');

    // Also test with device names swapped (connection should be found regardless of direction)
    $resultsReverse = $this->searchService->searchConnectionsBetween('Switch-A', 'Server-01', $this->admin);

    expect($resultsReverse['items'])->toHaveCount(1);
    expect($resultsReverse['items'][0]['id'])->toBe($targetConnection->id);
});
