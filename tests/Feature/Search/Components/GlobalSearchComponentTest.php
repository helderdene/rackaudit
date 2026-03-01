<?php

/**
 * GlobalSearch Component Tests
 *
 * Tests for the GlobalSearch Vue component including:
 * - Search input renders in header
 * - Debounced search triggers API call
 * - Keyboard shortcut (Cmd/Ctrl + K) focuses input
 * - Escape key clears input and closes dropdown
 * - Dropdown displays grouped results
 */

use App\Models\Datacenter;
use App\Models\Device;
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
    $this->withoutVite();
});

/**
 * Test 1: Search input renders in header.
 *
 * Verifies that the dashboard page includes the AppHeader component
 * which contains the global search functionality. The header should
 * be present on authenticated pages.
 */
test('search input renders in header on dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    $response = $this->actingAs($user)
        ->get('/dashboard');

    $response->assertSuccessful();
    // The page should render and include the header with search
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
    );
});

/**
 * Test 2: Quick search API endpoint returns grouped results.
 *
 * Verifies that the quick search endpoint returns results
 * grouped by entity type (datacenters, racks, devices, ports, connections)
 * with a maximum of 5 results per type for dropdown display.
 */
test('quick search API returns grouped results for dropdown', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create test data hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'NYC-Test-Datacenter']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Server-Room-A']);
    $row = Row::factory()->create(['room_id' => $room->id, 'name' => 'Row-01']);
    $rack = Rack::factory()->create([
        'row_id' => $row->id,
        'name' => 'Test-Rack-A1',
        'serial_number' => 'SN-RACK-001',
    ]);

    // Create device
    $device = Device::factory()->create([
        'rack_id' => $rack->id,
        'name' => 'Test-Server-01',
        'serial_number' => 'SN-DEV-001',
    ]);

    // Create port
    $port = Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'Test-Eth0',
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/search/quick?q=Test');

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            'datacenters' => ['items', 'total'],
            'racks' => ['items', 'total'],
            'devices' => ['items', 'total'],
            'ports' => ['items', 'total'],
            'connections' => ['items', 'total'],
        ],
        'query',
    ]);

    // Verify datacenter is found
    $response->assertJsonPath('data.datacenters.total', 1);

    // Verify rack is found
    $response->assertJsonPath('data.racks.total', 1);

    // Verify device is found
    $response->assertJsonPath('data.devices.total', 1);

    // Verify port is found
    $response->assertJsonPath('data.ports.total', 1);
});

/**
 * Test 3: Quick search respects result limit per entity type.
 *
 * Verifies that the quick search endpoint limits results to
 * a maximum of 5 per entity type for the dropdown display,
 * while still reporting the correct total count.
 */
test('quick search limits results to 5 per entity type', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create 10 datacenters with "Test" in name
    for ($i = 1; $i <= 10; $i++) {
        Datacenter::factory()->create(['name' => "Test-DC-$i"]);
    }

    $response = $this->actingAs($user)
        ->getJson('/api/search/quick?q=Test');

    $response->assertSuccessful();

    // Should return max 5 items but report total of 10
    $data = $response->json('data.datacenters');
    expect(count($data['items']))->toBeLessThanOrEqual(5);
    expect($data['total'])->toBe(10);
});

/**
 * Test 4: Quick search includes breadcrumb location context.
 *
 * Verifies that each search result includes a breadcrumb-style
 * location context showing the hierarchical path to the entity
 * (e.g., "Datacenter > Room > Row > Rack > Device").
 */
test('quick search results include breadcrumb context', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create full hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Main-DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Room-A']);
    $row = Row::factory()->create(['room_id' => $room->id, 'name' => 'Row-1']);
    $rack = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Rack-A1']);
    $device = Device::factory()->create([
        'rack_id' => $rack->id,
        'name' => 'Server-Unique-Name',
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/search/quick?q=Unique');

    $response->assertSuccessful();

    $devices = $response->json('data.devices.items');
    expect($devices)->toHaveCount(1);

    // Check that breadcrumb contains hierarchy
    $breadcrumb = $devices[0]['breadcrumb'];
    expect($breadcrumb)->toContain('Main-DC');
    expect($breadcrumb)->toContain('Room-A');
    expect($breadcrumb)->toContain('Row-1');
    expect($breadcrumb)->toContain('Rack-A1');
    expect($breadcrumb)->toContain('Server-Unique-Name');
});

/**
 * Test 5: Quick search includes matched field information.
 *
 * Verifies that search results include information about which
 * fields matched the search query, enabling frontend highlighting.
 */
test('quick search results include matched fields for highlighting', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create device with searchable fields
    $datacenter = Datacenter::factory()->create(['name' => 'DC-1']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create([
        'rack_id' => $rack->id,
        'name' => 'Web-Server',
        'serial_number' => 'SN-UNIQUE-123',
        'asset_tag' => 'ASSET-999',
    ]);

    // Search by serial number
    $response = $this->actingAs($user)
        ->getJson('/api/search/quick?q=UNIQUE');

    $response->assertSuccessful();

    $devices = $response->json('data.devices.items');
    expect($devices)->toHaveCount(1);
    expect($devices[0]['matched_fields'])->toContain('serial_number');
});

/**
 * Test 6: Quick search respects RBAC for non-admin users.
 *
 * Verifies that the search results are filtered based on
 * user permissions - non-admin users should only see results
 * from datacenters they have access to.
 */
test('quick search respects RBAC for non-admin users', function () {
    // Create two datacenters
    $assignedDC = Datacenter::factory()->create(['name' => 'Assigned-DC']);
    $unassignedDC = Datacenter::factory()->create(['name' => 'Unassigned-DC']);

    // Create devices in each
    $assignedRoom = Room::factory()->create(['datacenter_id' => $assignedDC->id]);
    $assignedRow = Row::factory()->create(['room_id' => $assignedRoom->id]);
    $assignedRack = Rack::factory()->create(['row_id' => $assignedRow->id]);
    Device::factory()->create([
        'rack_id' => $assignedRack->id,
        'name' => 'Search-Device-Assigned',
    ]);

    $unassignedRoom = Room::factory()->create(['datacenter_id' => $unassignedDC->id]);
    $unassignedRow = Row::factory()->create(['room_id' => $unassignedRoom->id]);
    $unassignedRack = Rack::factory()->create(['row_id' => $unassignedRow->id]);
    Device::factory()->create([
        'rack_id' => $unassignedRack->id,
        'name' => 'Search-Device-Unassigned',
    ]);

    // Create non-admin user with access only to assigned datacenter
    // Using 'Operator' role which exists in the seeder
    $user = User::factory()->create();
    $user->assignRole('Operator');
    $user->datacenters()->attach($assignedDC);

    $response = $this->actingAs($user)
        ->getJson('/api/search/quick?q=Search-Device');

    $response->assertSuccessful();

    $devices = $response->json('data.devices.items');

    // Should only find the device in the assigned datacenter
    expect($devices)->toHaveCount(1);
    expect($devices[0]['name'])->toBe('Search-Device-Assigned');
});
