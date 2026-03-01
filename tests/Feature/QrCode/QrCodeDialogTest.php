<?php

use App\Models\Device;
use App\Models\Rack;
use App\Models\Row;
use App\Models\Room;
use App\Models\Datacenter;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    // Create datacenter hierarchy
    $this->datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $this->room = Room::factory()->create([
        'datacenter_id' => $this->datacenter->id,
        'name' => 'Test Room',
    ]);
    $this->row = Row::factory()->create([
        'room_id' => $this->room->id,
        'name' => 'Test Row',
    ]);
    $this->rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Test Rack',
        'serial_number' => 'SN-RACK-001',
        'u_height' => 42,
    ]);
});

/**
 * Test 1: Device show page includes required props for QR code generation
 */
test('device show page includes required props for QR code', function () {
    $device = Device::factory()->create([
        'name' => 'Test Device',
        'asset_tag' => 'ASSET-001',
    ]);

    $response = $this->actingAs($this->admin)
        ->get("/devices/{$device->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Devices/Show')
            ->has('device', fn (Assert $deviceData) => $deviceData
                ->has('id')
                ->has('name')
                ->has('asset_tag')
                ->etc()
            )
        );

    // Verify specific data needed for QR code generation
    $page = $response->viewData('page');
    $deviceProp = $page['props']['device'];

    expect($deviceProp['id'])->toBe($device->id);
    expect($deviceProp['name'])->toBe('Test Device');
    expect($deviceProp['asset_tag'])->toBe('ASSET-001');
});

/**
 * Test 2: Rack show page includes required props for QR code generation
 */
test('rack show page includes required props for QR code', function () {
    $response = $this->actingAs($this->admin)
        ->get("/datacenters/{$this->datacenter->id}/rooms/{$this->room->id}/rows/{$this->row->id}/racks/{$this->rack->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Show')
            ->has('rack', fn (Assert $rackData) => $rackData
                ->has('id')
                ->has('name')
                ->has('serial_number')
                ->etc()
            )
        );

    // Verify specific data needed for QR code generation
    $page = $response->viewData('page');
    $rackProp = $page['props']['rack'];

    expect($rackProp['id'])->toBe($this->rack->id);
    expect($rackProp['name'])->toBe('Test Rack');
    expect($rackProp['serial_number'])->toBe('SN-RACK-001');
});

/**
 * Test 3: Device show page has correct route for QR code encoding
 */
test('device show route follows expected format for QR codes', function () {
    $device = Device::factory()->create(['name' => 'QR Test Device']);

    // Verify the route exists and returns expected structure
    $response = $this->actingAs($this->admin)
        ->get("/devices/{$device->id}");

    $response->assertOk();

    // Verify route name is consistent
    expect(route('devices.show', $device->id))->toBe(url("/devices/{$device->id}"));
});

/**
 * Test 4: Rack show page has correct route for QR code encoding
 */
test('rack show route follows expected format for QR codes', function () {
    $response = $this->actingAs($this->admin)
        ->get("/datacenters/{$this->datacenter->id}/rooms/{$this->room->id}/rows/{$this->row->id}/racks/{$this->rack->id}");

    $response->assertOk();

    // Verify route name is consistent
    $expectedUrl = url("/datacenters/{$this->datacenter->id}/rooms/{$this->room->id}/rows/{$this->row->id}/racks/{$this->rack->id}");
    expect(route('datacenters.rooms.rows.racks.show', [
        'datacenter' => $this->datacenter->id,
        'room' => $this->room->id,
        'row' => $this->row->id,
        'rack' => $this->rack->id,
    ]))->toBe($expectedUrl);
});

/**
 * Test 5: Device with auto-generated asset_tag is included in props
 */
test('device with auto-generated asset_tag is included in props', function () {
    // Create device without explicit asset_tag - it will be auto-generated
    $device = Device::factory()->create([
        'name' => 'Device With Generated Asset Tag',
    ]);

    $response = $this->actingAs($this->admin)
        ->get("/devices/{$device->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Devices/Show')
            ->has('device', fn (Assert $deviceData) => $deviceData
                ->where('name', 'Device With Generated Asset Tag')
                ->has('asset_tag') // asset_tag should be auto-generated
                ->etc()
            )
        );

    // Verify the auto-generated asset_tag is a string
    $page = $response->viewData('page');
    $deviceProp = $page['props']['device'];
    expect($deviceProp['asset_tag'])->toBeString();
    expect($deviceProp['asset_tag'])->not->toBeEmpty();
});

/**
 * Test 6: Rack with missing serial_number handles gracefully
 */
test('rack without serial_number includes null for secondary label', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Rack Without Serial',
        'serial_number' => null,
        'u_height' => 42,
    ]);

    $response = $this->actingAs($this->admin)
        ->get("/datacenters/{$this->datacenter->id}/rooms/{$this->room->id}/rows/{$this->row->id}/racks/{$rack->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Show')
            ->has('rack', fn (Assert $rackData) => $rackData
                ->where('serial_number', null)
                ->etc()
            )
        );
});
