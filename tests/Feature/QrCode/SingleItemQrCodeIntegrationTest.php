<?php

use App\Enums\DeviceLifecycleStatus;
use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Create hierarchy: Datacenter > Room > Row > Rack
    $this->datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $this->room = Room::factory()->create(['datacenter_id' => $this->datacenter->id, 'name' => 'Test Room']);
    $this->row = Row::factory()->create(['room_id' => $this->room->id, 'name' => 'Test Row']);
    $this->rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Default Rack',
        'position' => 1,
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
    ]);

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    // Create viewer user
    $this->viewer = User::factory()->create();
    $this->viewer->assignRole('Viewer');

    // Create device type
    $this->deviceType = DeviceType::factory()->create([
        'name' => 'Server',
        'default_u_size' => 2,
    ]);
});

/**
 * Test 1: Device Show page renders with device data for QR code button
 * The QR code button should have access to device.id, device.name, and device.asset_tag
 */
test('device show page provides props needed for QR code dialog', function () {
    $device = Device::factory()->create([
        'name' => 'Web Server 01',
        'device_type_id' => $this->deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'asset_tag' => 'ASSET-20241225-00001',
    ]);

    $response = $this->actingAs($this->admin)->get("/devices/{$device->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Devices/Show')
            ->where('device.id', $device->id)
            ->where('device.name', 'Web Server 01')
            ->where('device.asset_tag', 'ASSET-20241225-00001')
        );
});

/**
 * Test 2: Rack Show page provides props needed for QR code dialog
 * The QR code button should have access to rack.id, rack.name, and rack.serial_number
 */
test('rack show page provides props needed for QR code dialog', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Test Rack A1',
        'position' => 2,
        'u_height' => RackUHeight::U42,
        'serial_number' => 'SN-RACK-001',
        'status' => RackStatus::Active,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.show', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Show')
            ->where('rack.id', $rack->id)
            ->where('rack.name', 'Test Rack A1')
            ->where('rack.serial_number', 'SN-RACK-001')
        );
});

/**
 * Test 3: Rack Show page provides serial_number as null when not set
 */
test('rack show page handles missing serial number gracefully', function () {
    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Rack Without Serial',
        'position' => 3,
        'u_height' => RackUHeight::U42,
        'serial_number' => null,
        'status' => RackStatus::Active,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('datacenters.rooms.rows.racks.show', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Show')
            ->where('rack.name', 'Rack Without Serial')
            ->where('rack.serial_number', null)
        );
});

/**
 * Test 4: Device Show page is accessible to users with view permission (Viewer role)
 * Viewer role requires the device to be placed in a rack within their assigned datacenter
 */
test('device show page accessible to viewer role users with datacenter access', function () {
    // Viewer needs datacenter access to view devices placed in racks
    $this->viewer->datacenters()->attach($this->datacenter->id);

    // Place the device in a rack within the viewer's assigned datacenter
    $device = Device::factory()->create([
        'name' => 'Viewer Test Device',
        'device_type_id' => $this->deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'rack_id' => $this->rack->id,
        'start_u' => 1,
    ]);

    $response = $this->actingAs($this->viewer)->get("/devices/{$device->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Devices/Show')
            ->where('device.id', $device->id)
            ->where('device.name', 'Viewer Test Device')
        );
});

/**
 * Test 5: Rack Show page is accessible to users with view permission (Viewer role with datacenter access)
 */
test('rack show page accessible to viewer role users with datacenter access', function () {
    // Viewer needs datacenter access to view racks
    $this->viewer->datacenters()->attach($this->datacenter->id);

    $rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Viewer Access Rack',
        'position' => 4,
        'u_height' => RackUHeight::U42,
        'status' => RackStatus::Active,
    ]);

    $response = $this->actingAs($this->viewer)
        ->get(route('datacenters.rooms.rows.racks.show', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $rack->id,
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Racks/Show')
            ->where('rack.name', 'Viewer Access Rack')
        );
});
