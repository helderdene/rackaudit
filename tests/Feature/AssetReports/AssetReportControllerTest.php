<?php

use App\Enums\DeviceLifecycleStatus;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Disable Inertia page existence check since Vue components are created in Task Group 3
    config(['inertia.testing.ensure_pages_exist' => false]);
});

/**
 * Test 1: Index page returns correct Inertia response with all props.
 */
test('index page returns correct Inertia response with all props', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create a datacenter with full hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Test Room']);
    $row = Row::factory()->create(['room_id' => $room->id, 'name' => 'Row A']);
    $rack = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack 1']);

    // Create device types
    $serverType = DeviceType::factory()->create(['name' => 'Server']);
    $switchType = DeviceType::factory()->create(['name' => 'Switch']);

    // Create devices with various statuses and warranty dates
    Device::factory()->create([
        'device_type_id' => $serverType->id,
        'rack_id' => $rack->id,
        'start_u' => 1,
        'manufacturer' => 'Dell',
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'warranty_end_date' => now()->addYear(),
    ]);

    Device::factory()->create([
        'device_type_id' => $switchType->id,
        'rack_id' => null,
        'manufacturer' => 'Cisco',
        'lifecycle_status' => DeviceLifecycleStatus::InStock,
        'warranty_end_date' => now()->addDays(15), // Expiring soon
    ]);

    $response = $this->actingAs($admin)->get('/reports/assets');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetReports/Index')
            ->has('metrics')
            ->has('metrics.warrantyStatus')
            ->has('metrics.warrantyStatus.active')
            ->has('metrics.warrantyStatus.expiring_soon')
            ->has('metrics.warrantyStatus.expired')
            ->has('metrics.warrantyStatus.unknown')
            ->has('metrics.lifecycleDistribution')
            ->has('metrics.countsByType')
            ->has('metrics.countsByManufacturer')
            ->has('datacenterOptions')
            ->has('roomOptions')
            ->has('deviceTypeOptions')
            ->has('lifecycleStatusOptions')
            ->has('manufacturerOptions')
            ->has('filters')
        );
});

/**
 * Test 2: Role-based datacenter access (admin vs restricted user).
 */
test('role-based datacenter access filters correctly for admin vs restricted user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    // Create 3 datacenters
    $dc1 = Datacenter::factory()->create(['name' => 'Assigned DC 1']);
    $dc2 = Datacenter::factory()->create(['name' => 'Assigned DC 2']);
    $dc3 = Datacenter::factory()->create(['name' => 'Not Assigned DC']);

    // Assign operator to only 2 datacenters
    $operator->datacenters()->attach([$dc1->id, $dc2->id]);

    // Admin sees all datacenters
    $response = $this->actingAs($admin)->get('/reports/assets');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('datacenterOptions', 3)
        );

    // Operator sees only assigned datacenters
    $response = $this->actingAs($operator)->get('/reports/assets');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('datacenterOptions', 2)
        );
});

/**
 * Test 3: Cascading filter validation (datacenter -> room).
 */
test('cascading filter validation validates room belongs to selected datacenter', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create two datacenters with rooms
    $dc1 = Datacenter::factory()->create(['name' => 'DC 1']);
    $dc2 = Datacenter::factory()->create(['name' => 'DC 2']);

    $room1 = Room::factory()->create(['datacenter_id' => $dc1->id, 'name' => 'Room 1']);
    $room2 = Room::factory()->create(['datacenter_id' => $dc2->id, 'name' => 'Room 2']);

    // Valid combination: DC1 + Room1
    $response = $this->actingAs($admin)->get('/reports/assets?'.http_build_query([
        'datacenter_id' => $dc1->id,
        'room_id' => $room1->id,
    ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.datacenter_id', $dc1->id)
            ->where('filters.room_id', $room1->id)
        );

    // Invalid combination: DC1 + Room2 (room2 belongs to DC2)
    // Room should be reset to null
    $response = $this->actingAs($admin)->get('/reports/assets?'.http_build_query([
        'datacenter_id' => $dc1->id,
        'room_id' => $room2->id,
    ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.datacenter_id', $dc1->id)
            ->where('filters.room_id', null)
        );
});

/**
 * Test 4: Export PDF generates and downloads PDF file.
 */
test('exportPdf generates and downloads PDF file', function () {
    Storage::fake('local');

    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create a device for the report
    $deviceType = DeviceType::factory()->create(['name' => 'Server']);
    Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'manufacturer' => 'Dell',
    ]);

    $response = $this->actingAs($admin)->get('/reports/assets/export/pdf');

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');

    // Verify file was stored
    $files = Storage::disk('local')->files('reports/assets');
    expect($files)->toHaveCount(1);
    expect($files[0])->toContain('asset-report-');
    expect($files[0])->toEndWith('.pdf');
});

/**
 * Test 5: Export CSV generates and downloads CSV file.
 */
test('exportCsv generates and downloads CSV file', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create devices for the export
    $deviceType = DeviceType::factory()->create(['name' => 'Server']);
    Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'manufacturer' => 'Dell',
        'serial_number' => 'SN-TEST-001',
    ]);

    $response = $this->actingAs($admin)->get('/reports/assets/export/csv');

    $response->assertOk();
    $response->assertDownload();

    // Verify content type is CSV
    $contentDisposition = $response->headers->get('content-disposition');
    expect($contentDisposition)->toContain('asset-report-');
    expect($contentDisposition)->toContain('.csv');
});

/**
 * Test 6: Filter parameter validation and sanitization.
 */
test('filter parameter validation and sanitization works correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $deviceType = DeviceType::factory()->create(['name' => 'Server']);

    Device::factory()->create([
        'device_type_id' => $deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'manufacturer' => 'Dell',
    ]);

    // Test with valid filters
    $response = $this->actingAs($admin)->get('/reports/assets?'.http_build_query([
        'datacenter_id' => $datacenter->id,
        'device_type_id' => $deviceType->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed->value,
        'manufacturer' => 'Dell',
        'warranty_start' => '2024-01-01',
        'warranty_end' => '2025-12-31',
    ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.datacenter_id', $datacenter->id)
            ->where('filters.device_type_id', $deviceType->id)
            ->where('filters.lifecycle_status', DeviceLifecycleStatus::Deployed->value)
            ->where('filters.manufacturer', 'Dell')
            ->where('filters.warranty_start', '2024-01-01')
            ->where('filters.warranty_end', '2025-12-31')
        );

    // Test with invalid datacenter_id (non-existent) - should be sanitized to null
    $response = $this->actingAs($admin)->get('/reports/assets?'.http_build_query([
        'datacenter_id' => 99999,
    ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.datacenter_id', null)
        );

    // Test with empty string values - should be sanitized
    $response = $this->actingAs($admin)->get('/reports/assets?'.http_build_query([
        'datacenter_id' => '',
        'room_id' => '',
    ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.datacenter_id', null)
            ->where('filters.room_id', null)
        );
});
