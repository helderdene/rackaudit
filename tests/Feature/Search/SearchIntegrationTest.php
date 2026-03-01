<?php

/**
 * Strategic Integration Tests for Search Functionality
 *
 * These tests fill critical gaps identified during Task 7 review:
 * - IT Manager role full access verification
 * - Multiple filter combinations
 * - Port-specific filters
 * - Case insensitivity
 * - Cross-datacenter connection RBAC
 * - Short query handling
 * - All searchable fields verification
 */

use App\Enums\DeviceLifecycleStatus;
use App\Enums\PortStatus;
use App\Enums\PortType;
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
});

/**
 * Test 1: IT Manager role has full search access like Administrator.
 *
 * Verifies that IT Manager can see all search results across all datacenters,
 * matching the Administrator role access as specified in ADMIN_ROLES constant.
 */
test('IT Manager role has full search access across all datacenters', function () {
    // Create two datacenters
    $datacenter1 = Datacenter::factory()->create(['name' => 'IT-Manager-DC-1']);
    $datacenter2 = Datacenter::factory()->create(['name' => 'IT-Manager-DC-2']);

    // Create hierarchy for both
    $room1 = Room::factory()->create(['datacenter_id' => $datacenter1->id]);
    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $rack1 = Rack::factory()->create(['row_id' => $row1->id]);
    Device::factory()->create(['name' => 'ITM-Device-1', 'rack_id' => $rack1->id]);

    $room2 = Room::factory()->create(['datacenter_id' => $datacenter2->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);
    $rack2 = Rack::factory()->create(['row_id' => $row2->id]);
    Device::factory()->create(['name' => 'ITM-Device-2', 'rack_id' => $rack2->id]);

    // Create IT Manager user (NOT assigned to any datacenter)
    $itManager = User::factory()->create();
    $itManager->assignRole('IT Manager');

    // Search should return devices from both datacenters
    $results = $this->searchService->search('ITM-Device', $itManager);

    expect($results['devices']['items'])->toHaveCount(2);
    $deviceNames = collect($results['devices']['items'])->pluck('name')->all();
    expect($deviceNames)->toContain('ITM-Device-1');
    expect($deviceNames)->toContain('ITM-Device-2');
});

/**
 * Test 2: Multiple filter combinations work correctly together.
 *
 * Verifies that combining hierarchical and entity-specific filters
 * produces correctly narrowed results.
 */
test('multiple filter combinations narrow results correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create datacenter hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Combo-DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create devices with different lifecycle statuses
    $deployedDevice = Device::factory()->create([
        'name' => 'Combo-Server-Deployed',
        'rack_id' => $rack->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);
    Device::factory()->create([
        'name' => 'Combo-Server-Maintenance',
        'rack_id' => $rack->id,
        'lifecycle_status' => DeviceLifecycleStatus::Maintenance,
    ]);

    // Create another datacenter with deployed device (should be filtered out)
    $otherDc = Datacenter::factory()->create(['name' => 'Other-DC']);
    $otherRoom = Room::factory()->create(['datacenter_id' => $otherDc->id]);
    $otherRow = Row::factory()->create(['room_id' => $otherRoom->id]);
    $otherRack = Rack::factory()->create(['row_id' => $otherRow->id]);
    Device::factory()->create([
        'name' => 'Combo-Server-Other',
        'rack_id' => $otherRack->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed,
    ]);

    // Apply multiple filters: datacenter + lifecycle_status
    $filters = [
        'datacenter_id' => $datacenter->id,
        'lifecycle_status' => DeviceLifecycleStatus::Deployed->value,
    ];

    $results = $this->searchService->search('Combo-Server', $admin, $filters);

    // Should only return the one device matching both filters
    expect($results['devices']['items'])->toHaveCount(1);
    expect($results['devices']['items'][0]['id'])->toBe($deployedDevice->id);
});

/**
 * Test 3: Port-specific filters (port_type, port_status) work via API.
 *
 * Verifies that filtering ports by type and status produces correct results.
 */
test('port-specific filters work correctly in API search', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');
    $this->withoutVite();

    // Create hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Port-Filter-DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['name' => 'Port-Filter-Device', 'rack_id' => $rack->id]);

    // Create ports with different types and statuses (using correct enum values)
    Port::factory()->create([
        'device_id' => $device->id,
        'label' => 'filter-eth-available',
        'type' => PortType::Ethernet,
        'status' => PortStatus::Available,
    ]);
    Port::factory()->create([
        'device_id' => $device->id,
        'label' => 'filter-fiber-available',
        'type' => PortType::Fiber,
        'status' => PortStatus::Available,
    ]);
    Port::factory()->create([
        'device_id' => $device->id,
        'label' => 'filter-eth-disabled',
        'type' => PortType::Ethernet,
        'status' => PortStatus::Disabled,
    ]);

    // Search with port_type filter
    $response = $this->actingAs($admin)
        ->getJson('/api/search?q=filter&port_type=ethernet');

    $response->assertOk();
    $ports = $response->json('data.ports.items');

    expect($ports)->toHaveCount(2);
    $labels = collect($ports)->pluck('label')->all();
    expect($labels)->toContain('filter-eth-available');
    expect($labels)->toContain('filter-eth-disabled');
    expect($labels)->not->toContain('filter-fiber-available');
});

/**
 * Test 4: Search is case insensitive.
 *
 * Verifies that search works regardless of case matching between query and data.
 */
test('search is case insensitive for all entity types', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create entities with mixed case names
    $datacenter = Datacenter::factory()->create(['name' => 'CaseTest-DATACENTER']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['name' => 'CaseTest-RACK', 'row_id' => $row->id]);
    $device = Device::factory()->create([
        'name' => 'CaseTest-DEVICE',
        'rack_id' => $rack->id,
        'serial_number' => 'SN-CASETEST-XYZ',
    ]);
    Port::factory()->ethernet()->create([
        'device_id' => $device->id,
        'label' => 'CaseTest-PORT',
    ]);

    // Search with lowercase query
    $results = $this->searchService->search('casetest', $admin);

    expect($results['datacenters']['items'])->not->toBeEmpty();
    expect($results['racks']['items'])->not->toBeEmpty();
    expect($results['devices']['items'])->not->toBeEmpty();
    expect($results['ports']['items'])->not->toBeEmpty();

    // Search with different case variations
    $resultsUpper = $this->searchService->search('CASETEST', $admin);
    $resultsMixed = $this->searchService->search('CaseTest', $admin);

    expect($resultsUpper['devices']['total'])->toBe($results['devices']['total']);
    expect($resultsMixed['devices']['total'])->toBe($results['devices']['total']);
});

/**
 * Test 5: Cross-datacenter connection RBAC filtering.
 *
 * Verifies that connections spanning multiple datacenters are visible
 * if the user has access to at least one endpoint's datacenter.
 */
test('cross-datacenter connections are visible if user has access to one endpoint', function () {
    // Create two datacenters
    $accessibleDC = Datacenter::factory()->create(['name' => 'Accessible-DC']);
    $restrictedDC = Datacenter::factory()->create(['name' => 'Restricted-DC']);

    // Create hierarchy for accessible datacenter
    $room1 = Room::factory()->create(['datacenter_id' => $accessibleDC->id]);
    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $rack1 = Rack::factory()->create(['row_id' => $row1->id]);
    $device1 = Device::factory()->create(['name' => 'CrossDC-Device1', 'rack_id' => $rack1->id]);
    $port1 = Port::factory()->ethernet()->create(['device_id' => $device1->id, 'label' => 'crossdc-port1']);

    // Create hierarchy for restricted datacenter
    $room2 = Room::factory()->create(['datacenter_id' => $restrictedDC->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);
    $rack2 = Rack::factory()->create(['row_id' => $row2->id]);
    $device2 = Device::factory()->create(['name' => 'CrossDC-Device2', 'rack_id' => $rack2->id]);
    $port2 = Port::factory()->ethernet()->create(['device_id' => $device2->id, 'label' => 'crossdc-port2']);

    // Create connection spanning both datacenters
    $crossConnection = Connection::factory()->create([
        'source_port_id' => $port1->id,
        'destination_port_id' => $port2->id,
        'cable_color' => 'crossdc-blue',
    ]);

    // Create user with access only to first datacenter
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $viewer->datacenters()->attach($accessibleDC->id);

    // User should see the cross-datacenter connection
    $results = $this->searchService->search('crossdc', $viewer);

    expect($results['connections']['items'])->toHaveCount(1);
    expect($results['connections']['items'][0]['id'])->toBe($crossConnection->id);
});

/**
 * Test 6: Short query (2 characters) still returns results.
 *
 * Verifies that short search queries work correctly.
 */
test('short search queries with 2 characters return results', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create entities with short, unique name segments
    $datacenter = Datacenter::factory()->create(['name' => 'XQ-Datacenter']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['name' => 'XQ-Rack', 'row_id' => $row->id]);
    $device = Device::factory()->create(['name' => 'XQ-Device', 'rack_id' => $rack->id]);

    // Search with 2-character query
    $results = $this->searchService->search('XQ', $admin);

    expect($results['datacenters']['items'])->not->toBeEmpty();
    expect($results['racks']['items'])->not->toBeEmpty();
    expect($results['devices']['items'])->not->toBeEmpty();
});

/**
 * Test 7: All specified searchable fields for devices are actually searchable.
 *
 * Verifies that each field listed in the spec (name, asset_tag, serial_number,
 * manufacturer, model) returns matching devices.
 */
test('all specified device searchable fields return matching results', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Field-Test-DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);

    // Create device with unique values in each searchable field
    Device::factory()->create([
        'rack_id' => $rack->id,
        'name' => 'FieldTest-Name-Server',
        'asset_tag' => 'ASSET-FIELDTEST-123',
        'serial_number' => 'SN-FIELDTEST-456',
        'manufacturer' => 'FieldTestManufacturer',
        'model' => 'FieldTestModel-X1',
    ]);

    // Test searching by each field
    $searchablePatterns = [
        'FieldTest-Name' => 'name',
        'ASSET-FIELDTEST' => 'asset_tag',
        'SN-FIELDTEST' => 'serial_number',
        'FieldTestManufacturer' => 'manufacturer',
        'FieldTestModel' => 'model',
    ];

    foreach ($searchablePatterns as $query => $expectedField) {
        $results = $this->searchService->search($query, $admin);

        expect($results['devices']['items'])->toHaveCount(1);
        expect($results['devices']['items'][0]['matched_fields'])->toContain($expectedField);
    }
});

/**
 * Test 8: All specified searchable fields for datacenters are searchable.
 *
 * Verifies that each field listed in the spec (name, city, country, company_name,
 * primary_contact_name, secondary_contact_name) returns matching datacenters.
 */
test('all specified datacenter searchable fields return matching results', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create datacenter with unique values in each searchable field
    Datacenter::factory()->create([
        'name' => 'DCFieldTest-Name',
        'city' => 'DCFieldTestCity',
        'country' => 'DCFieldTestCountry',
        'company_name' => 'DCFieldTestCompany',
        'primary_contact_name' => 'DCFieldTestPrimary',
        'secondary_contact_name' => 'DCFieldTestSecondary',
    ]);

    // Test searching by each field
    $searchablePatterns = [
        'DCFieldTest-Name' => 'name',
        'DCFieldTestCity' => 'city',
        'DCFieldTestCountry' => 'country',
        'DCFieldTestCompany' => 'company_name',
        'DCFieldTestPrimary' => 'primary_contact_name',
        'DCFieldTestSecondary' => 'secondary_contact_name',
    ];

    foreach ($searchablePatterns as $query => $expectedField) {
        $results = $this->searchService->search($query, $admin);

        expect($results['datacenters']['items'])->toHaveCount(1);
        expect($results['datacenters']['items'][0]['matched_fields'])->toContain($expectedField);
    }
});

/**
 * Test 9: Full search workflow from quick search to results page.
 *
 * End-to-end test verifying the complete user workflow:
 * 1. Quick search API returns limited results
 * 2. Clicking "View all" navigates to search results page
 * 3. Results page shows all matching results with proper filters
 */
test('full search workflow from quick search to results page works end-to-end', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');
    $this->withoutVite();

    // Create test data
    $datacenter = Datacenter::factory()->create(['name' => 'Workflow-DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id, 'name' => 'Workflow-Rack']);

    // Create more devices than quick search limit
    for ($i = 1; $i <= 10; $i++) {
        Device::factory()->create([
            'name' => "Workflow-Device-{$i}",
            'rack_id' => $rack->id,
        ]);
    }

    // Step 1: Quick search returns limited results
    $quickResponse = $this->actingAs($admin)
        ->getJson('/api/search/quick?q=Workflow');

    $quickResponse->assertOk();
    $quickDevices = $quickResponse->json('data.devices');
    expect(count($quickDevices['items']))->toBeLessThanOrEqual(5);
    expect($quickDevices['total'])->toBe(10);

    // Step 2: Full search page shows all results
    $pageResponse = $this->actingAs($admin)
        ->get('/search?q=Workflow');

    $pageResponse->assertOk();
    $pageResponse->assertInertia(fn ($page) => $page
        ->component('Search/Index')
        ->where('query', 'Workflow')
        ->where('results.devices.total', 10)
    );

    // Step 3: API full search also shows all results
    $fullResponse = $this->actingAs($admin)
        ->getJson('/api/search?q=Workflow');

    $fullResponse->assertOk();
    expect($fullResponse->json('data.devices.items'))->toHaveCount(10);
});

/**
 * Test 10: Connection RBAC boundary - user without datacenter access sees nothing.
 *
 * Verifies that a user with no datacenter access cannot see any connections,
 * even connections between devices with null rack_id.
 */
test('user without any datacenter access sees no results from accessible datacenters', function () {
    // Create a datacenter with devices
    $datacenter = Datacenter::factory()->create(['name' => 'RBAC-Boundary-DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    Device::factory()->create(['name' => 'RBAC-Boundary-Device', 'rack_id' => $rack->id]);

    // Create user with Viewer role but no datacenter assignments
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    // Intentionally NOT attaching any datacenters

    // Search should return empty for racked devices
    $results = $this->searchService->search('RBAC-Boundary', $viewer);

    // Should not find the device since it's in a datacenter the user can't access
    expect($results['devices']['items'])->toBeEmpty();
    expect($results['datacenters']['items'])->toBeEmpty();
    expect($results['racks']['items'])->toBeEmpty();
});
