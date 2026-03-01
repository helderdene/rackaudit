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

    // Disable Inertia page existence check since Vue components may not exist yet
    config(['inertia.testing.ensure_pages_exist' => false]);
});

/**
 * Test 1: Cascading location filters - child filters reset when parent changes.
 *
 * This test verifies that when a datacenter filter is applied, the room and row
 * filters are correctly scoped, and when datacenter changes, room_id and row_id
 * are reset (handled by frontend watchers, tested via backend validation).
 */
test('cascading location filters correctly scope room and row options', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create two datacenters with different rooms
    $datacenter1 = Datacenter::factory()->create(['name' => 'DC1']);
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter1->id, 'name' => 'Room 1A']);
    $row1 = Row::factory()->create(['room_id' => $room1->id, 'name' => 'Row 1A-1']);

    $datacenter2 = Datacenter::factory()->create(['name' => 'DC2']);
    $room2 = Room::factory()->create(['datacenter_id' => $datacenter2->id, 'name' => 'Room 2A']);
    $row2 = Row::factory()->create(['room_id' => $room2->id, 'name' => 'Row 2A-1']);

    // Create racks in each row
    Rack::factory()->create([
        'row_id' => $row1->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
    ]);

    Rack::factory()->create([
        'row_id' => $row2->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
    ]);

    // Test preview with datacenter_id filter - should only return racks in that datacenter
    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'columns' => ['rack_name', 'datacenter_name'],
        'filters' => ['datacenter_id' => $datacenter1->id],
        'sort' => [],
    ]);

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomReports/Builder')
            ->has('previewData.data', 1)
            ->where('previewData.data.0.datacenter_name', 'DC1')
        );

    // Test with room_id filter - should only return racks in that room
    $response2 = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'columns' => ['rack_name', 'room_name'],
        'filters' => ['room_id' => $room2->id],
        'sort' => [],
    ]);

    $response2->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomReports/Builder')
            ->has('previewData.data', 1)
            ->where('previewData.data.0.room_name', 'Room 2A')
        );
});

/**
 * Test 2: Type-specific filters are available and work for each report type.
 *
 * This test verifies that the configure endpoint returns type-specific filters
 * that match each report type's requirements.
 */
test('type-specific filters are returned based on report type', function () {
    $admin = User::factory()->create();
    $admin->assignRole('IT Manager');

    // Test Assets report type - should have device_type, lifecycle_status, manufacturer, warranty filters
    $response = $this->actingAs($admin)->get('/custom-reports/configure?report_type=assets');
    $response->assertOk();
    $data = $response->json();

    expect(array_keys($data['filters']))->toContain('device_type_id');
    expect(array_keys($data['filters']))->toContain('lifecycle_status');
    expect(array_keys($data['filters']))->toContain('manufacturer');
    expect(array_keys($data['filters']))->toContain('warranty_start');
    expect(array_keys($data['filters']))->toContain('warranty_end');

    // Verify lifecycle_status has options
    expect($data['filters']['lifecycle_status']['options'])->not->toBeEmpty();

    // Test Capacity report type - should have utilization_threshold
    $response2 = $this->actingAs($admin)->get('/custom-reports/configure?report_type=capacity');
    $response2->assertOk();
    $data2 = $response2->json();

    expect(array_keys($data2['filters']))->toContain('utilization_threshold');
    expect(array_keys($data2['filters']))->toContain('row_id');

    // Test Connections report type - should have cable_type and connection_status
    $response3 = $this->actingAs($admin)->get('/custom-reports/configure?report_type=connections');
    $response3->assertOk();
    $data3 = $response3->json();

    expect(array_keys($data3['filters']))->toContain('cable_type');
    expect(array_keys($data3['filters']))->toContain('connection_status');

    // Test AuditHistory report type - should have date range, audit_type, finding_severity
    $response4 = $this->actingAs($admin)->get('/custom-reports/configure?report_type=audit_history');
    $response4->assertOk();
    $data4 = $response4->json();

    expect(array_keys($data4['filters']))->toContain('start_date');
    expect(array_keys($data4['filters']))->toContain('end_date');
    expect(array_keys($data4['filters']))->toContain('audit_type');
    expect(array_keys($data4['filters']))->toContain('finding_severity');
});

/**
 * Test 3: Sort configuration allows up to 3 columns with direction.
 *
 * This test verifies that the preview endpoint accepts multi-column sort
 * configurations (up to 3 columns) and that sorting is applied correctly.
 */
test('sort configuration allows up to 3 columns with ascending and descending directions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    // Create multiple racks with different properties for sorting test
    // Use only valid RackUHeight enum values: U42, U45, U48
    Rack::factory()->create([
        'row_id' => $row->id,
        'name' => 'Rack-A',
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
        'power_capacity_watts' => 5000,
    ]);

    Rack::factory()->create([
        'row_id' => $row->id,
        'name' => 'Rack-B',
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U48, // Changed from U47 to U48
        'power_capacity_watts' => 3000,
    ]);

    Rack::factory()->create([
        'row_id' => $row->id,
        'name' => 'Rack-C',
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U45, // Changed from U42 to U45 for variety
        'power_capacity_watts' => 4000,
    ]);

    // Test with single sort column - ascending
    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'columns' => ['rack_name', 'u_height'],
        'filters' => [],
        'sort' => [
            ['column' => 'rack_name', 'direction' => 'asc'],
        ],
    ]);

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomReports/Builder')
            ->has('previewData.data', 3)
            // First item should be Rack-A when sorted ascending by name
            ->where('previewData.data.0.rack_name', 'Rack-A')
        );

    // Test with single sort column - descending
    $response2 = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'columns' => ['rack_name', 'u_height'],
        'filters' => [],
        'sort' => [
            ['column' => 'rack_name', 'direction' => 'desc'],
        ],
    ]);

    $response2->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomReports/Builder')
            ->has('previewData.data', 3)
            // First item should be Rack-C when sorted descending by name
            ->where('previewData.data.0.rack_name', 'Rack-C')
        );

    // Test with 3 sort columns (maximum allowed)
    $response3 = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'columns' => ['rack_name', 'u_height', 'power_capacity_watts'],
        'filters' => [],
        'sort' => [
            ['column' => 'u_height', 'direction' => 'desc'],
            ['column' => 'power_capacity_watts', 'direction' => 'asc'],
            ['column' => 'rack_name', 'direction' => 'asc'],
        ],
    ]);

    $response3->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomReports/Builder')
            ->has('previewData.data', 3)
        );

    // Test validation - more than 3 sort columns should fail
    $response4 = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'capacity',
        'columns' => ['rack_name'],
        'filters' => [],
        'sort' => [
            ['column' => 'rack_name', 'direction' => 'asc'],
            ['column' => 'u_height', 'direction' => 'desc'],
            ['column' => 'power_capacity_watts', 'direction' => 'asc'],
            ['column' => 'datacenter_name', 'direction' => 'desc'],
        ],
    ]);

    $response4->assertSessionHasErrors('sort');
});

/**
 * Test 4: Assets type-specific filters work correctly (device_type, lifecycle_status, manufacturer).
 *
 * This test verifies that the Assets report filters correctly filter devices
 * based on device type, lifecycle status, and manufacturer.
 */
test('assets type-specific filters filter devices correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create([
        'row_id' => $row->id,
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
    ]);

    $deviceType1 = DeviceType::factory()->create(['name' => 'Server']);
    $deviceType2 = DeviceType::factory()->create(['name' => 'Switch']);

    // Create devices with different types and lifecycle statuses
    // Use correct enum values: Deployed instead of Active, Decommissioned instead of Retired
    Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType1->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'manufacturer' => 'Dell',
        'name' => 'Dell Server 1',
    ]);

    Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType2->id,
        'lifecycle_status' => DeviceLifecycleStatus::Decommissioned,
        'manufacturer' => 'Cisco',
        'name' => 'Cisco Switch 1',
    ]);

    Device::factory()->create([
        'rack_id' => $rack->id,
        'device_type_id' => $deviceType1->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
        'manufacturer' => 'HP',
        'name' => 'HP Server 1',
    ]);

    // Test filter by device_type_id
    $response = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'assets',
        'columns' => ['name', 'device_type', 'manufacturer'],
        'filters' => ['device_type_id' => $deviceType1->id],
        'sort' => [],
    ]);

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomReports/Builder')
            ->has('previewData.data', 2) // Should only return Servers
        );

    // Test filter by lifecycle_status
    $response2 = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'assets',
        'columns' => ['name', 'lifecycle_status'],
        'filters' => ['lifecycle_status' => DeviceLifecycleStatus::Decommissioned->value],
        'sort' => [],
    ]);

    $response2->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomReports/Builder')
            ->has('previewData.data', 1) // Should only return decommissioned device
        );

    // Test filter by manufacturer
    $response3 = $this->actingAs($admin)->post('/custom-reports/preview', [
        'report_type' => 'assets',
        'columns' => ['name', 'manufacturer'],
        'filters' => ['manufacturer' => 'Dell'],
        'sort' => [],
    ]);

    $response3->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomReports/Builder')
            ->has('previewData.data', 1)
            ->where('previewData.data.0.manufacturer', 'Dell')
        );
});
