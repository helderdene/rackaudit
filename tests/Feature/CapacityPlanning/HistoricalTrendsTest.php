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
 * Test 1: Sparkline data is populated from capacity snapshots
 */
test('sparkline data is populated from capacity snapshots', function () {
    // Create capacity snapshots for the datacenter over 8 weeks
    for ($i = 7; $i >= 0; $i--) {
        CapacitySnapshot::factory()->create([
            'datacenter_id' => $this->datacenter->id,
            'snapshot_date' => now()->subWeeks($i)->format('Y-m-d'),
            'rack_utilization_percent' => 50 + ($i * 5), // Varying utilization 50-85%
            'power_utilization_percent' => 40 + ($i * 3), // Varying power 40-61%
            'total_u_space' => 1000,
            'used_u_space' => 500 + ($i * 50),
        ]);
    }

    $response = $this->actingAs($this->admin)
        ->get('/capacity-reports?datacenter_id='.$this->datacenter->id);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('historicalSnapshots')
            ->has('historicalSnapshots', 8) // All 8 snapshots should be returned
            ->has('historicalSnapshots.0', fn ($snapshot) => $snapshot
                ->has('date')
                ->has('rack_utilization')
                ->has('power_utilization')
            )
        );
});

/**
 * Test 2: Week-over-week trend is calculated correctly
 */
test('week-over-week trend is calculated between current and previous week snapshots', function () {
    // Create snapshots for current week and previous week
    CapacitySnapshot::factory()->create([
        'datacenter_id' => $this->datacenter->id,
        'snapshot_date' => now()->format('Y-m-d'),
        'rack_utilization_percent' => 75.00,
        'power_utilization_percent' => 60.00,
        'total_u_space' => 1000,
        'used_u_space' => 750,
    ]);

    CapacitySnapshot::factory()->create([
        'datacenter_id' => $this->datacenter->id,
        'snapshot_date' => now()->subWeek()->format('Y-m-d'),
        'rack_utilization_percent' => 70.00,
        'power_utilization_percent' => 55.00,
        'total_u_space' => 1000,
        'used_u_space' => 700,
    ]);

    $response = $this->actingAs($this->admin)
        ->get('/capacity-reports?datacenter_id='.$this->datacenter->id);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('historicalSnapshots')
            ->has('historicalSnapshots', 2)
        );

    // Get the page data to verify the trend values more flexibly
    $pageData = $response->viewData('page')['props'];
    $snapshots = $pageData['historicalSnapshots'];

    // Verify the trend data structure includes both snapshots (using float comparison)
    expect(count($snapshots))->toBe(2);
    expect((float) $snapshots[0]['rack_utilization'])->toBe(70.0);
    expect((float) $snapshots[1]['rack_utilization'])->toBe(75.0);
});

/**
 * Test 3: Empty snapshot handling when no historical data exists
 */
test('empty snapshot handling returns empty array when no historical data exists', function () {
    // Don't create any snapshots
    $response = $this->actingAs($this->admin)
        ->get('/capacity-reports?datacenter_id='.$this->datacenter->id);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('historicalSnapshots')
            ->where('historicalSnapshots', []) // Should be empty array
        );
});

/**
 * Test 4: Multiple weeks of trend data display correctly
 */
test('multiple weeks of trend data are ordered chronologically', function () {
    // Create snapshots for 12 weeks of data
    $expectedDates = [];
    for ($i = 11; $i >= 0; $i--) {
        $date = now()->subWeeks($i)->format('Y-m-d');
        $expectedDates[] = $date;

        CapacitySnapshot::factory()->create([
            'datacenter_id' => $this->datacenter->id,
            'snapshot_date' => $date,
            'rack_utilization_percent' => 60 + $i, // Varying utilization
            'power_utilization_percent' => 50 + $i,
            'total_u_space' => 1000,
            'used_u_space' => 600 + ($i * 10),
        ]);
    }

    $response = $this->actingAs($this->admin)
        ->get('/capacity-reports?datacenter_id='.$this->datacenter->id);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('historicalSnapshots', 12) // All 12 weeks returned
            // Verify chronological ordering (oldest to newest)
            ->where('historicalSnapshots.0.date', $expectedDates[0])
            ->where('historicalSnapshots.11.date', $expectedDates[11])
        );

    // Get the page data to verify the trend values
    $pageData = $response->viewData('page')['props'];
    $snapshots = $pageData['historicalSnapshots'];

    // Verify each snapshot has the expected structure
    foreach ($snapshots as $snapshot) {
        expect($snapshot)->toHaveKeys(['date', 'rack_utilization', 'power_utilization']);
        expect($snapshot['rack_utilization'])->toBeNumeric();
    }
});
