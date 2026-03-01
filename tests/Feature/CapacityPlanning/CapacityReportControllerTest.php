<?php

use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Models\CapacitySnapshot;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create users with different roles
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    $this->itManager = User::factory()->create();
    $this->itManager->assignRole('IT Manager');

    $this->operator = User::factory()->create();
    $this->operator->assignRole('Operator');

    // Create datacenter hierarchy for testing
    $this->datacenter = Datacenter::factory()->create(['name' => 'Main DC']);
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

    // Assign datacenter access to operator
    $this->operator->datacenters()->attach($this->datacenter->id);

    // Create a secondary datacenter for access control tests
    $this->secondDatacenter = Datacenter::factory()->create(['name' => 'Secondary DC']);

    // Set up Storage fake
    Storage::fake('local');
});

/**
 * Test 1: Index page returns correct Inertia props
 */
test('index page returns correct Inertia props with capacity metrics', function () {
    $response = $this->actingAs($this->admin)
        ->get('/capacity-reports');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CapacityReports/Index')
            ->has('metrics')
            ->has('metrics.u_space')
            ->has('metrics.power')
            ->has('metrics.port_capacity')
            ->has('metrics.racks_approaching_capacity')
            ->has('datacenterOptions')
            ->has('roomOptions')
            ->has('rowOptions')
            ->has('filters')
            ->has('historicalSnapshots')
        );
});

/**
 * Test 2: Datacenter/room/row filter validation works correctly
 */
test('filter validation enforces cascading filter hierarchy', function () {
    // Test valid datacenter filter
    $response = $this->actingAs($this->admin)
        ->get('/capacity-reports?datacenter_id='.$this->datacenter->id);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.datacenter_id', $this->datacenter->id)
        );

    // Test valid room filter (with datacenter)
    $response = $this->actingAs($this->admin)
        ->get('/capacity-reports?datacenter_id='.$this->datacenter->id.'&room_id='.$this->room->id);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.datacenter_id', $this->datacenter->id)
            ->where('filters.room_id', $this->room->id)
        );

    // Test valid row filter (with room and datacenter)
    $response = $this->actingAs($this->admin)
        ->get('/capacity-reports?datacenter_id='.$this->datacenter->id.'&room_id='.$this->room->id.'&row_id='.$this->row->id);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.datacenter_id', $this->datacenter->id)
            ->where('filters.room_id', $this->room->id)
            ->where('filters.row_id', $this->row->id)
        );

    // Test invalid datacenter ID is ignored
    $response = $this->actingAs($this->admin)
        ->get('/capacity-reports?datacenter_id=99999');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.datacenter_id', null)
        );
});

/**
 * Test 3: User access control - admin vs assigned datacenters
 */
test('user access control restricts datacenters based on role and assignments', function () {
    // Admin sees all datacenters
    $response = $this->actingAs($this->admin)
        ->get('/capacity-reports');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('datacenterOptions', 2) // Both datacenters
        );

    // IT Manager sees all datacenters
    $response = $this->actingAs($this->itManager)
        ->get('/capacity-reports');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('datacenterOptions', 2)
        );

    // Operator sees only assigned datacenter
    $response = $this->actingAs($this->operator)
        ->get('/capacity-reports');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('datacenterOptions', 1) // Only assigned datacenter
        );

    // Operator cannot filter by unassigned datacenter
    $response = $this->actingAs($this->operator)
        ->get('/capacity-reports?datacenter_id='.$this->secondDatacenter->id);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.datacenter_id', null) // Invalid datacenter ignored
        );
});

/**
 * Test 4: PDF export endpoint returns downloadable file
 */
test('PDF export endpoint returns downloadable file', function () {
    // First create the directory
    Storage::disk('local')->makeDirectory('reports/capacity');

    $response = $this->actingAs($this->admin)
        ->get('/capacity-reports/export/pdf');

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    $response->assertHeader('content-disposition');
});

/**
 * Test 5: CSV export endpoint returns downloadable file
 */
test('CSV export endpoint returns downloadable file', function () {
    $response = $this->actingAs($this->admin)
        ->get('/capacity-reports/export/csv');

    $response->assertOk();

    // Check the content-disposition header for the filename pattern
    $contentDisposition = $response->headers->get('content-disposition');
    expect($contentDisposition)->toContain('capacity-report-');
    expect($contentDisposition)->toContain('.csv');
});

/**
 * Test 6: Authentication is required for all endpoints
 */
test('authentication is required for all capacity report endpoints', function () {
    // Test index without authentication
    $response = $this->get('/capacity-reports');
    $response->assertRedirect('/login');

    // Test PDF export without authentication
    $response = $this->get('/capacity-reports/export/pdf');
    $response->assertRedirect('/login');

    // Test CSV export without authentication
    $response = $this->get('/capacity-reports/export/csv');
    $response->assertRedirect('/login');
});

/**
 * Test 7: Historical snapshots are included for sparkline data
 */
test('historical snapshots are included for sparkline visualization', function () {
    // Create some historical snapshots with unique dates
    for ($i = 1; $i <= 5; $i++) {
        CapacitySnapshot::factory()->create([
            'datacenter_id' => $this->datacenter->id,
            'snapshot_date' => now()->subWeeks($i)->format('Y-m-d'),
        ]);
    }

    $response = $this->actingAs($this->admin)
        ->get('/capacity-reports?datacenter_id='.$this->datacenter->id);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('historicalSnapshots')
        );
});
