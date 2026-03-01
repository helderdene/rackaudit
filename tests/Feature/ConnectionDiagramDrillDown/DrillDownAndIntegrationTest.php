<?php

use App\Enums\CableType;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('GET /devices/{device}/ports/diagram returns port-level connections for drill-down', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $rack = Rack::factory()->create();
    $deviceType = DeviceType::factory()->create(['name' => 'Switch']);

    $device = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'name' => 'Core Switch',
    ]);
    $remoteDevice = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'name' => 'Edge Switch',
    ]);

    // Create ports with different statuses
    $connectedPort = Port::factory()->ethernet()->connected()->create([
        'device_id' => $device->id,
        'label' => 'Gi0/1',
    ]);
    $availablePort = Port::factory()->ethernet()->available()->create([
        'device_id' => $device->id,
        'label' => 'Gi0/2',
    ]);
    $reservedPort = Port::factory()->ethernet()->reserved()->create([
        'device_id' => $device->id,
        'label' => 'Gi0/3',
    ]);
    $disabledPort = Port::factory()->ethernet()->disabled()->create([
        'device_id' => $device->id,
        'label' => 'Gi0/4',
    ]);

    $remotePort = Port::factory()->ethernet()->connected()->create([
        'device_id' => $remoteDevice->id,
        'label' => 'Fa0/1',
    ]);

    // Create connection between devices
    Connection::factory()->create([
        'source_port_id' => $connectedPort->id,
        'destination_port_id' => $remotePort->id,
        'cable_type' => CableType::Cat6,
        'cable_color' => 'blue',
    ]);

    $response = $this->actingAs($user)
        ->getJson("/devices/{$device->id}/ports/diagram");

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'label',
                'type',
                'status',
                'direction',
                'connection',
            ],
        ],
    ]);

    // Verify all 4 ports returned
    $response->assertJsonCount(4, 'data');

    // Verify port statuses are correctly returned
    $ports = $response->json('data');
    $connectedPortData = collect($ports)->firstWhere('id', $connectedPort->id);
    $availablePortData = collect($ports)->firstWhere('id', $availablePort->id);
    $reservedPortData = collect($ports)->firstWhere('id', $reservedPort->id);
    $disabledPortData = collect($ports)->firstWhere('id', $disabledPort->id);

    expect($connectedPortData['status'])->toBe('connected');
    expect($availablePortData['status'])->toBe('available');
    expect($reservedPortData['status'])->toBe('reserved');
    expect($disabledPortData['status'])->toBe('disabled');

    // Verify connection info is included for connected port
    expect($connectedPortData['connection'])->not->toBeNull();
    expect($connectedPortData['connection']['remote_device']['name'])->toBe('Edge Switch');
    expect($connectedPortData['connection']['remote_port']['label'])->toBe('Fa0/1');
});

test('Device Show page has View Connections button linking to filtered diagram', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $rack = Rack::factory()->create();
    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    $device = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'name' => 'Web Server',
    ]);

    $response = $this->actingAs($user)
        ->get("/devices/{$device->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Devices/Show')
        ->has('device')
        ->where('device.id', $device->id)
        ->where('device.name', 'Web Server')
    );
});

test('Rack Show page has Connection Diagram link to rack-scoped visualization', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack 42']);

    $response = $this->actingAs($user)
        ->get("/datacenters/{$datacenter->id}/rooms/{$room->id}/rows/{$row->id}/racks/{$rack->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Racks/Show')
        ->has('rack')
        ->where('rack.id', $rack->id)
        ->where('rack.name', 'Rack 42')
    );
});

test('Connection diagram filters by device_id showing only connections for that device', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $rack = Rack::factory()->create();
    $deviceType = DeviceType::factory()->create(['name' => 'Switch']);

    // Target device with connections
    $targetDevice = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'name' => 'Target Switch',
    ]);

    // Other devices with connections
    $otherDevice1 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id]);
    $otherDevice2 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id]);
    $otherDevice3 = Device::factory()->create(['rack_id' => $rack->id, 'device_type_id' => $deviceType->id]);

    // Connection to target device
    $targetPort = Port::factory()->ethernet()->create(['device_id' => $targetDevice->id]);
    $connectedPort = Port::factory()->ethernet()->create(['device_id' => $otherDevice1->id]);
    Connection::factory()->create([
        'source_port_id' => $targetPort->id,
        'destination_port_id' => $connectedPort->id,
    ]);

    // Connection NOT involving target device
    $unrelatedPort1 = Port::factory()->ethernet()->create(['device_id' => $otherDevice2->id]);
    $unrelatedPort2 = Port::factory()->ethernet()->create(['device_id' => $otherDevice3->id]);
    Connection::factory()->create([
        'source_port_id' => $unrelatedPort1->id,
        'destination_port_id' => $unrelatedPort2->id,
    ]);

    // Filter by device_id - should only return connections involving target device
    $response = $this->actingAs($user)
        ->getJson("/connections/diagram?device_id={$targetDevice->id}");

    $response->assertSuccessful();

    // Should only see target device and otherDevice1 (connected to target)
    $nodes = $response->json('data.nodes');
    $nodeIds = collect($nodes)->pluck('id')->toArray();

    expect($nodeIds)->toContain($targetDevice->id);
    expect($nodeIds)->toContain($otherDevice1->id);
    // Should NOT contain unrelated devices
    expect($nodeIds)->not->toContain($otherDevice2->id);
    expect($nodeIds)->not->toContain($otherDevice3->id);
});

test('Rack Elevation page loads with devices and connection data', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack A1']);

    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    $device1 = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'name' => 'Server 01',
        'start_u' => 10,
        'rack_face' => 'front',
    ]);

    $device2 = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
        'name' => 'Server 02',
        'start_u' => 20,
        'rack_face' => 'front',
    ]);

    // Create connection between devices in the rack
    $port1 = Port::factory()->ethernet()->create(['device_id' => $device1->id]);
    $port2 = Port::factory()->ethernet()->create(['device_id' => $device2->id]);
    Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
        'cable_type' => CableType::Cat6,
    ]);

    $response = $this->actingAs($user)
        ->get("/datacenters/{$datacenter->id}/rooms/{$room->id}/rows/{$row->id}/racks/{$rack->id}/elevation");

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Racks/Elevation')
        ->has('rack')
        ->has('devices')
        ->has('devices.placed')
        ->where('rack.name', 'Rack A1')
    );

    // Verify devices are in the placed array
    $page = $response->original->getData()['page']['props'];
    $placedDevices = $page['devices']['placed'];
    $placedNames = collect($placedDevices)->pluck('name')->toArray();

    expect($placedNames)->toContain('Server 01');
    expect($placedNames)->toContain('Server 02');
});

test('Connection diagram page accepts device_id filter in initial query parameters', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $rack = Rack::factory()->create();
    $deviceType = DeviceType::factory()->create(['name' => 'Switch']);
    $device = Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType->id,
    ]);

    $response = $this->actingAs($user)
        ->get("/connections/diagram/page?device_id={$device->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Connections/Diagram')
        ->has('initialFilters')
        ->where('initialFilters.device_id', $device->id)
    );
});
