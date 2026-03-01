<?php

use App\Enums\AuditStatus;
use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Finding;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Test 1: Dashboard handles empty state with no datacenters or data gracefully
 * Edge case: Verify dashboard displays correctly when there is no data
 */
test('dashboard handles empty state with no datacenters gracefully', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            // All metrics should show zero values
            ->where('metrics.rackUtilization.value', 0)
            ->where('metrics.deviceCount.value', 0)
            ->where('metrics.pendingAudits.value', 0)
            ->where('metrics.openFindings.value', 0)
            // Datacenter options should be empty
            ->has('datacenterOptions', 0)
            // Recent activity may have entries from user creation
            ->has('recentActivity')
        );
});

/**
 * Test 2: User with single datacenter access sees correct scoped data
 * Edge case: Verify a user with access to exactly one datacenter sees only that datacenter's data
 */
test('user with single datacenter access sees correct scoped data', function () {
    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    // Create two datacenters but only give access to one
    $accessibleDc = Datacenter::factory()->create(['name' => 'Accessible DC']);
    $inaccessibleDc = Datacenter::factory()->create(['name' => 'Inaccessible DC']);

    // Assign operator to only one datacenter
    $operator->datacenters()->attach($accessibleDc);

    // Create infrastructure in both datacenters
    $room1 = Room::factory()->create(['datacenter_id' => $accessibleDc->id]);
    $row1 = Row::factory()->create(['room_id' => $room1->id]);
    $rack1 = Rack::factory()->active()->withUHeight(RackUHeight::U42)->create(['row_id' => $row1->id]);
    Device::factory()->count(3)->placed($rack1, 1)->withUHeight(2)->create();

    $room2 = Room::factory()->create(['datacenter_id' => $inaccessibleDc->id]);
    $row2 = Row::factory()->create(['room_id' => $room2->id]);
    $rack2 = Rack::factory()->active()->withUHeight(RackUHeight::U42)->create(['row_id' => $row2->id]);
    Device::factory()->count(10)->placed($rack2, 1)->withUHeight(2)->create();

    $response = $this->actingAs($operator)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            // Should only see devices from accessible datacenter (3)
            ->where('metrics.deviceCount.value', 3)
            // Should only see one datacenter option
            ->has('datacenterOptions', 1)
            ->where('datacenterOptions.0.name', 'Accessible DC')
        );
});

/**
 * Test 3: Rack utilization calculates correctly with actual data
 * Integration: Verify the utilization percentage formula is correct
 */
test('rack utilization calculates percentage correctly from device u-heights', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    // Create a 42U rack
    $rack = Rack::factory()->active()->withUHeight(RackUHeight::U42)->create(['row_id' => $row->id]);

    // Add devices totaling 21U (50% utilization)
    Device::factory()->placed($rack, 1)->withUHeight(10)->create();
    Device::factory()->placed($rack, 11)->withUHeight(7)->create();
    Device::factory()->placed($rack, 18)->withUHeight(4)->create();

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            // 21U / 42U = 50% (use int comparison as the value may be 50 or 50.0)
            ->where('metrics.rackUtilization.value', fn ($value) => $value == 50)
        );
});

/**
 * Test 4: Past due audits count is calculated correctly
 * Integration: Verify pending audits that are past their due date are counted in pastDue
 */
test('pending audits metric correctly counts past due audits', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();

    // Create 2 pending audits that are past due
    Audit::factory()->count(2)->pending()->create([
        'datacenter_id' => $datacenter->id,
        'due_date' => now()->subDays(5),
    ]);

    // Create 3 pending audits that are not past due
    Audit::factory()->count(3)->pending()->create([
        'datacenter_id' => $datacenter->id,
        'due_date' => now()->addDays(10),
    ]);

    // Create 1 completed audit (should not count)
    Audit::factory()->completed()->create([
        'datacenter_id' => $datacenter->id,
        'due_date' => now()->subDays(2),
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            // 5 pending audits total (2 past due + 3 upcoming)
            ->where('metrics.pendingAudits.value', 5)
            // 2 are past due
            ->where('metrics.pendingAudits.pastDue', 2)
        );
});

/**
 * Test 5: Invalid datacenter filter is ignored for non-accessible datacenter
 * Security: Verify that attempting to filter by an inaccessible datacenter is handled
 */
test('filtering by inaccessible datacenter returns null filter', function () {
    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    // Create two datacenters
    $accessibleDc = Datacenter::factory()->create(['name' => 'Accessible DC']);
    $inaccessibleDc = Datacenter::factory()->create(['name' => 'Inaccessible DC']);

    // Assign operator to only one datacenter
    $operator->datacenters()->attach($accessibleDc);

    // Try to filter by the inaccessible datacenter
    $response = $this->actingAs($operator)->get(route('dashboard', [
        'datacenter_id' => $inaccessibleDc->id,
    ]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            // Filter should be ignored/reset to null
            ->where('filters.datacenter_id', null)
        );
});

/**
 * Test 6: IT Manager role has full datacenter access like Administrator
 * Permission: Verify IT Manager is treated as admin role for datacenter access
 */
test('it manager role has full access to all datacenters', function () {
    $itManager = User::factory()->create();
    $itManager->assignRole('IT Manager');

    // Create multiple datacenters (IT Manager not explicitly assigned)
    $datacenter1 = Datacenter::factory()->create(['name' => 'DC Alpha']);
    $datacenter2 = Datacenter::factory()->create(['name' => 'DC Beta']);
    $datacenter3 = Datacenter::factory()->create(['name' => 'DC Gamma']);

    // Create devices in all datacenters
    foreach ([$datacenter1, $datacenter2, $datacenter3] as $dc) {
        $room = Room::factory()->create(['datacenter_id' => $dc->id]);
        $row = Row::factory()->create(['room_id' => $room->id]);
        $rack = Rack::factory()->active()->withUHeight(RackUHeight::U42)->create(['row_id' => $row->id]);
        Device::factory()->count(4)->placed($rack, 1)->withUHeight(1)->create();
    }

    $response = $this->actingAs($itManager)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            // Should see all 12 devices (4 per datacenter x 3)
            ->where('metrics.deviceCount.value', 12)
            // Should see all 3 datacenters in options
            ->has('datacenterOptions', 3)
        );
});

/**
 * Test 7: Inactive racks are excluded from utilization calculation
 * Business rule: Only active racks should be included in rack utilization metric
 */
test('inactive racks are excluded from rack utilization', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create();
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    // Create an active rack with devices
    $activeRack = Rack::factory()->active()->withUHeight(RackUHeight::U42)->create(['row_id' => $row->id]);
    Device::factory()->placed($activeRack, 1)->withUHeight(21)->create();

    // Create an inactive rack with devices (should be ignored for utilization)
    $inactiveRack = Rack::factory()->create([
        'row_id' => $row->id,
        'status' => RackStatus::Inactive,
        'u_height' => RackUHeight::U42,
    ]);
    Device::factory()->placed($inactiveRack, 1)->withUHeight(42)->create();

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            // Should only count active rack: 21U / 42U = 50%
            // If inactive was included it would be (21+42)/(42+42) = 75%
            ->where('metrics.rackUtilization.value', fn ($value) => $value == 50)
            // Device count includes all devices regardless of rack status
            ->where('metrics.deviceCount.value', 2)
        );
});

/**
 * Test 8: Complete dashboard flow with all metrics populated
 * Integration: End-to-end test verifying all metrics work together with real data
 */
test('complete dashboard flow displays all metrics correctly with full data', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Set up complete infrastructure
    $datacenter = Datacenter::factory()->create(['name' => 'Main Datacenter']);
    $room = Room::factory()->create(['datacenter_id' => $datacenter->id]);
    $row = Row::factory()->create(['room_id' => $room->id]);

    // Create 2 active racks
    $rack1 = Rack::factory()->active()->withUHeight(RackUHeight::U42)->create(['row_id' => $row->id]);
    $rack2 = Rack::factory()->active()->withUHeight(RackUHeight::U42)->create(['row_id' => $row->id]);

    // Add 6 devices (3 per rack)
    Device::factory()->count(3)->placed($rack1, 1)->withUHeight(4)->create();
    Device::factory()->count(3)->placed($rack2, 1)->withUHeight(4)->create();

    // Create a completed audit (not pending) for findings
    $completedAudit = Audit::factory()->completed()->create([
        'datacenter_id' => $datacenter->id,
    ]);

    // Create a pending audit
    $pendingAudit = Audit::factory()->pending()->create([
        'datacenter_id' => $datacenter->id,
        'due_date' => now()->addDays(7),
    ]);

    // Create open findings on the completed audit (to avoid factory creating extra audits)
    // Use direct create with audit_id to avoid factory side effects
    Finding::factory()->count(2)->critical()->open()->create([
        'audit_id' => $completedAudit->id,
        'audit_connection_verification_id' => null,
    ]);
    Finding::factory()->count(1)->high()->open()->create([
        'audit_id' => $completedAudit->id,
        'audit_connection_verification_id' => null,
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            // Verify all metrics are present and correctly calculated
            // 24U used (6 devices * 4U each) / 84U total (2 racks * 42U) = 28.57%
            ->where('metrics.rackUtilization.value', fn ($value) => $value > 28 && $value < 29)
            ->where('metrics.deviceCount.value', 6)
            // Only 1 pending audit (not the completed one)
            ->where('metrics.pendingAudits.value', 1)
            ->where('metrics.pendingAudits.pastDue', 0)
            ->where('metrics.openFindings.value', 3)
            ->where('metrics.openFindings.bySeverity.critical', 2)
            ->where('metrics.openFindings.bySeverity.high', 1)
            // Verify datacenter options
            ->has('datacenterOptions', 1)
            ->where('datacenterOptions.0.name', 'Main Datacenter')
            // Verify sparklines have 7 values each
            ->has('metrics.rackUtilization.sparkline', 7)
            ->has('metrics.deviceCount.sparkline', 7)
            ->has('metrics.pendingAudits.sparkline', 7)
            ->has('metrics.openFindings.sparkline', 7)
        );
});
