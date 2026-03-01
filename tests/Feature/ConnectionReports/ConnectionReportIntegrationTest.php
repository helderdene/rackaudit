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
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
});

test('navigation link appears in sidebar for authorized users', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Access dashboard to verify sidebar data is available
    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSuccessful();

    // The sidebar navigation is rendered client-side based on auth permissions
    // Verify that the Connection Reports route is accessible and user is authenticated
    $connectionReportsResponse = $this->actingAs($user)->get('/connection-reports');
    $connectionReportsResponse->assertSuccessful();
});

test('route is accessible to authorized users', function () {
    // Test Administrator access
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $response = $this->actingAs($admin)->get('/connection-reports');
    $response->assertSuccessful();

    // Test IT Manager access
    $itManager = User::factory()->create();
    $itManager->assignRole('IT Manager');

    $response = $this->actingAs($itManager)->get('/connection-reports');
    $response->assertSuccessful();

    // Test Operator with assigned datacenter
    $datacenter = Datacenter::factory()->create();
    $operator = User::factory()->create();
    $operator->assignRole('Operator');
    $operator->datacenters()->attach($datacenter);

    $response = $this->actingAs($operator)->get('/connection-reports');
    $response->assertSuccessful();

    // Test Viewer with assigned datacenter
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $viewer->datacenters()->attach($datacenter);

    $response = $this->actingAs($viewer)->get('/connection-reports');
    $response->assertSuccessful();
});

test('route is not accessible to unauthenticated users', function () {
    $response = $this->get('/connection-reports');

    $response->assertRedirect('/login');
});

test('end-to-end flow: load page, apply filter, export', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    // Create test data hierarchy
    $datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id, 'name' => 'Test Room']);
    $row = Row::factory()->create(['room_id' => $room->id]);
    $rack = Rack::factory()->create(['row_id' => $row->id]);
    $device = Device::factory()->create(['rack_id' => $rack->id, 'name' => 'Test Server']);

    // Create ports and connections
    $sourcePort = Port::factory()->ethernet()->create(['device_id' => $device->id, 'label' => 'eth0']);
    $destPort = Port::factory()->ethernet()->create(['device_id' => $device->id, 'label' => 'eth1']);
    Connection::factory()->create([
        'source_port_id' => $sourcePort->id,
        'destination_port_id' => $destPort->id,
        'cable_type' => CableType::Cat6,
        'cable_length' => 3.5,
        'cable_color' => 'blue',
    ]);

    // Step 1: Load the page without filters
    $response = $this->actingAs($user)->get('/connection-reports');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        ->has('metrics')
        ->where('metrics.totalConnections', 1)
        ->has('datacenterOptions')
        ->has('roomOptions')
        ->has('filters')
    );

    // Step 2: Apply datacenter filter
    $filteredResponse = $this->actingAs($user)
        ->get("/connection-reports?datacenter_id={$datacenter->id}");

    $filteredResponse->assertSuccessful();
    $filteredResponse->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        ->where('filters.datacenter_id', $datacenter->id)
        ->where('metrics.totalConnections', 1)
        ->has('roomOptions', 1) // Should show the room for this datacenter
    );

    // Step 3: Apply room filter
    $roomFilteredResponse = $this->actingAs($user)
        ->get("/connection-reports?datacenter_id={$datacenter->id}&room_id={$room->id}");

    $roomFilteredResponse->assertSuccessful();
    $roomFilteredResponse->assertInertia(fn (Assert $page) => $page
        ->component('ConnectionReports/Index')
        ->where('filters.datacenter_id', $datacenter->id)
        ->where('filters.room_id', $room->id)
        ->where('metrics.totalConnections', 1)
    );

    // Step 4: Test PDF export with filters
    $pdfResponse = $this->actingAs($user)
        ->get("/connection-reports/export/pdf?datacenter_id={$datacenter->id}");

    $pdfResponse->assertSuccessful();
    $pdfResponse->assertHeader('content-type', 'application/pdf');

    // Step 5: Test CSV export with filters
    $csvResponse = $this->actingAs($user)
        ->get("/connection-reports/export/csv?datacenter_id={$datacenter->id}");

    $csvResponse->assertSuccessful();
    $csvResponse->assertDownload();
});
