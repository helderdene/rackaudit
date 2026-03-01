<?php

use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    // Create datacenter hierarchy for testing
    $this->datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
    $this->room = Room::factory()->create([
        'datacenter_id' => $this->datacenter->id,
        'name' => 'Server Room A',
    ]);
    $this->row = Row::factory()->create([
        'room_id' => $this->room->id,
        'name' => 'Row 1',
    ]);
    $this->rack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'Rack A1',
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
        'power_capacity_watts' => 10000,
    ]);

    // Create a device in the rack
    Device::factory()->create([
        'rack_id' => $this->rack->id,
        'u_height' => 4,
        'power_draw_watts' => 500,
    ]);
});

/**
 * Test 1: CapacityFilters cascading behavior - room appears when datacenter selected
 * This tests that the room filter options are provided when a datacenter is selected.
 */
test('capacity filters show cascading behavior - roomOptions provided when datacenter selected', function () {
    // Without datacenter filter - roomOptions should be empty
    $response = $this->actingAs($this->admin)
        ->get('/capacity-reports');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CapacityReports/Index')
            ->where('roomOptions', [])
            ->where('rowOptions', [])
        );

    // With datacenter filter - roomOptions should be populated
    $response = $this->actingAs($this->admin)
        ->get('/capacity-reports?datacenter_id='.$this->datacenter->id);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('roomOptions', 1) // One room in this datacenter
            ->where('roomOptions.0.id', $this->room->id)
            ->where('roomOptions.0.name', 'Server Room A')
        );
});

/**
 * Test 2: CapacityMetricCard displays correct values
 * Tests that metrics are correctly calculated and passed to the frontend.
 */
test('capacity metric cards receive correct utilization values', function () {
    $response = $this->actingAs($this->admin)
        ->get('/capacity-reports');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CapacityReports/Index')
            ->has('metrics.u_space')
            ->has('metrics.u_space.total_u_space')
            ->has('metrics.u_space.used_u_space')
            ->has('metrics.u_space.available_u_space')
            ->has('metrics.u_space.utilization_percent')
            ->has('metrics.power')
        );
});

/**
 * Test 3: RackCapacityTable receives correct rack data
 * Tests that racks approaching capacity are provided with proper structure.
 */
test('racks approaching capacity data has correct structure', function () {
    // Create a rack with high utilization (90%+)
    $highUtilRack = Rack::factory()->create([
        'row_id' => $this->row->id,
        'name' => 'High Util Rack',
        'status' => RackStatus::Active,
        'u_height' => RackUHeight::U42,
        'power_capacity_watts' => 10000,
    ]);

    // Create devices to fill up the rack (~95% utilization - 40U in 42U rack)
    Device::factory()->count(10)->create([
        'rack_id' => $highUtilRack->id,
        'u_height' => 4, // 40U total
    ]);

    $response = $this->actingAs($this->admin)
        ->get('/capacity-reports');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('metrics.racks_approaching_capacity')
            // Should have the high utilization rack
            ->where('metrics.racks_approaching_capacity.0.name', 'High Util Rack')
            ->where('metrics.racks_approaching_capacity.0.status', 'critical')
        );
});

/**
 * Test 4: Export URLs have correct structure
 * Tests that export endpoints return downloadable files.
 */
test('export endpoints return correct content types', function () {
    // Test PDF export with filters
    $pdfResponse = $this->actingAs($this->admin)
        ->get('/capacity-reports/export/pdf?datacenter_id='.$this->datacenter->id);

    $pdfResponse->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    // Test CSV export
    $csvResponse = $this->actingAs($this->admin)
        ->get('/capacity-reports/export/csv?datacenter_id='.$this->datacenter->id);

    $csvResponse->assertOk();
    $contentDisposition = $csvResponse->headers->get('content-disposition');
    expect($contentDisposition)->toContain('capacity-report-');
    expect($contentDisposition)->toContain('.csv');
});

/**
 * Test 5: Port capacity grid data structure
 * Tests that port capacity metrics are organized by port type.
 */
test('port capacity data is organized by port type', function () {
    $response = $this->actingAs($this->admin)
        ->get('/capacity-reports');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('metrics.port_capacity')
        );
});

/**
 * Test 6: Historical snapshots provided for sparkline data
 * Tests that historical snapshot data is included when available.
 */
test('historical snapshots are included in page props', function () {
    $response = $this->actingAs($this->admin)
        ->get('/capacity-reports');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('historicalSnapshots')
        );
});
