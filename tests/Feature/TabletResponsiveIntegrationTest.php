<?php

/**
 * Tablet Responsive Integration Tests
 *
 * Integration tests for cross-component tablet responsive behavior:
 * - Navigation between pages with sidebar in collapsed state
 * - Complete audit execution workflow on tablet viewport
 * - Rack elevation page accessibility with sidebar state
 * - Dashboard to audit navigation flow
 *
 * These tests verify that tablet responsive features work correctly
 * across page navigations and component interactions.
 */

use App\Enums\AuditScopeType;
use App\Enums\AuditType;
use App\Enums\DeviceVerificationStatus;
use App\Models\Audit;
use App\Models\AuditDeviceVerification;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    // Create datacenter hierarchy
    $this->datacenter = Datacenter::factory()->create(['name' => 'Integration Test DC']);
    $this->room = Room::factory()->create(['datacenter_id' => $this->datacenter->id]);
    $this->row = Row::factory()->create(['room_id' => $this->room->id]);
    $this->rack = Rack::factory()->create(['row_id' => $this->row->id]);
});

/**
 * Test 1: Cross-page navigation with sidebar collapsed state persists correctly.
 *
 * Verifies that when navigating from dashboard to rack elevation page,
 * the sidebar collapsed state (simulating tablet viewport) is maintained.
 */
test('sidebar collapsed state persists across dashboard to elevation navigation', function () {
    // Start on dashboard with collapsed sidebar (simulating tablet auto-collapse)
    $dashboardResponse = $this->actingAs($this->admin)
        ->withUnencryptedCookie('sidebar_state', 'false')
        ->get('/dashboard');

    $dashboardResponse->assertSuccessful();
    $dashboardResponse->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->where('sidebarOpen', false)
    );

    // Navigate to rack elevation page - sidebar state should persist
    $elevationResponse = $this->actingAs($this->admin)
        ->withUnencryptedCookie('sidebar_state', 'false')
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $this->rack->id,
        ]));

    $elevationResponse->assertSuccessful();
    $elevationResponse->assertInertia(fn (Assert $page) => $page
        ->component('Racks/Elevation')
        ->where('sidebarOpen', false)
    );
});

/**
 * Test 2: Complete audit execution flow with filter and verify actions.
 *
 * Verifies the complete tablet workflow: view audit, filter devices,
 * verify a device, and confirm updated progress stats.
 */
test('complete inventory audit execution workflow on tablet', function () {
    // Create an inventory audit in progress
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Inventory,
        'scope_type' => AuditScopeType::Datacenter,
        'datacenter_id' => $this->datacenter->id,
    ]);

    // Assign admin to audit
    $audit->assignees()->attach($this->admin);

    // Create device verifications
    $device = Device::factory()->create([
        'rack_id' => $this->rack->id,
        'name' => 'Test Server for Tablet Workflow',
    ]);

    $verification = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->forDevice($device)
        ->pending()
        ->create();

    // Step 1: Load inventory execute page with collapsed sidebar
    $executeResponse = $this->actingAs($this->admin)
        ->withUnencryptedCookie('sidebar_state', 'false')
        ->get("/audits/{$audit->id}/inventory-execute");

    $executeResponse->assertSuccessful();
    $executeResponse->assertInertia(fn (Assert $page) => $page
        ->component('Audits/InventoryExecute')
        ->where('sidebarOpen', false)
        ->has('progress_stats')
    );

    // Step 2: Fetch device verifications via API (simulating tablet card view data load)
    $apiResponse = $this->actingAs($this->admin)
        ->getJson("/api/audits/{$audit->id}/device-verifications");

    $apiResponse->assertSuccessful();
    $apiResponse->assertJsonPath('data.0.id', $verification->id);
    $apiResponse->assertJsonPath('data.0.verification_status', 'pending');

    // Step 3: Verify the device (simulating touch action on tablet)
    $verifyResponse = $this->actingAs($this->admin)
        ->postJson("/api/audits/{$audit->id}/device-verifications/{$verification->id}/verify", [
            'notes' => 'Verified via tablet integration test',
        ]);

    $verifyResponse->assertSuccessful();

    // Step 4: Confirm verification status updated
    $verification->refresh();
    expect($verification->verification_status)->toBe(DeviceVerificationStatus::Verified);
});

/**
 * Test 3: Navigation from audit list to execute maintains sidebar state.
 *
 * Verifies that navigating from audits index to execute page
 * preserves collapsed sidebar state for tablet users.
 */
test('audit list to execute navigation preserves collapsed sidebar state', function () {
    // Create an in-progress audit
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Inventory,
        'scope_type' => AuditScopeType::Datacenter,
        'datacenter_id' => $this->datacenter->id,
    ]);

    $audit->assignees()->attach($this->admin);

    // Create at least one device verification
    AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->create();

    // Load audits index with collapsed sidebar
    $indexResponse = $this->actingAs($this->admin)
        ->withUnencryptedCookie('sidebar_state', 'false')
        ->get('/audits');

    $indexResponse->assertSuccessful();
    $indexResponse->assertInertia(fn (Assert $page) => $page
        ->where('sidebarOpen', false)
    );

    // Navigate to execute page
    $executeResponse = $this->actingAs($this->admin)
        ->withUnencryptedCookie('sidebar_state', 'false')
        ->get("/audits/{$audit->id}/inventory-execute");

    $executeResponse->assertSuccessful();
    $executeResponse->assertInertia(fn (Assert $page) => $page
        ->component('Audits/InventoryExecute')
        ->where('sidebarOpen', false)
    );
});

/**
 * Test 4: Rack elevation provides all required data for vertical stacking layout.
 *
 * Verifies that when accessing rack elevation with collapsed sidebar (tablet mode),
 * all necessary data for front/rear vertical stacking is provided.
 */
test('rack elevation page provides complete data for tablet vertical layout', function () {
    // Create devices on both faces for complete elevation view
    $frontDevice = Device::factory()->create([
        'rack_id' => $this->rack->id,
        'name' => 'Front Device',
        'start_u' => 1,
        'u_height' => 2,
    ]);

    $rearDevice = Device::factory()->create([
        'rack_id' => $this->rack->id,
        'name' => 'Rear Device',
        'start_u' => 5,
        'u_height' => 2,
    ]);

    // Access elevation page with tablet-simulated collapsed sidebar
    $response = $this->actingAs($this->admin)
        ->withUnencryptedCookie('sidebar_state', 'false')
        ->get(route('datacenters.rooms.rows.racks.elevation', [
            'datacenter' => $this->datacenter->id,
            'room' => $this->room->id,
            'row' => $this->row->id,
            'rack' => $this->rack->id,
        ]));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Racks/Elevation')
        ->where('sidebarOpen', false)
        // Verify rack data for height calculations
        ->has('rack.u_height')
        ->has('rack.name')
        // Verify devices data exists for both elevation views
        ->has('devices.placed')
        ->has('devices.unplaced')
        // Verify navigation breadcrumb data
        ->has('datacenter.name')
        ->has('room.name')
        ->has('row.name')
    );
});

/**
 * Test 5: Dashboard filter with datacenter selection provides complete metrics.
 *
 * Verifies that filtering dashboard by datacenter returns proper metric
 * data structure for tablet 2-column grid display.
 */
test('dashboard datacenter filter provides complete metrics for tablet grid', function () {
    // Create a second datacenter for filter testing
    $secondDc = Datacenter::factory()->create(['name' => 'Second DC']);

    // Access dashboard with datacenter filter
    $response = $this->actingAs($this->admin)
        ->withUnencryptedCookie('sidebar_state', 'false')
        ->get('/dashboard?datacenter_id='.$this->datacenter->id);

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->where('sidebarOpen', false)
        // Verify all metric cards have complete data
        ->has('metrics.rackUtilization.value')
        ->has('metrics.rackUtilization.sparkline')
        ->has('metrics.deviceCount.value')
        ->has('metrics.deviceCount.sparkline')
        ->has('metrics.pendingAudits.value')
        ->has('metrics.openFindings.value')
        // Verify filter state - use integer comparison as the backend returns integer
        ->where('filters.datacenter_id', $this->datacenter->id)
        // Verify datacenter options available
        ->has('datacenterOptions', 2)
    );
});

/**
 * Test 6: Connection audit execute page provides required data for tablet layout.
 *
 * Verifies that connection audit execute page returns all filter options
 * and progress stats needed for tablet stacked filter layout.
 */
test('connection audit execute page provides data for tablet stacked filters', function () {
    // Create a connection audit
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Connection,
        'datacenter_id' => $this->datacenter->id,
    ]);

    $audit->assignees()->attach($this->admin);

    // Access execute page with tablet collapsed sidebar
    $response = $this->actingAs($this->admin)
        ->withUnencryptedCookie('sidebar_state', 'false')
        ->get("/audits/{$audit->id}/execute");

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Audits/Execute')
        ->where('sidebarOpen', false)
        // Verify filter options for stacked layout
        ->has('discrepancy_types')
        ->has('verification_statuses')
        // Verify progress stats for progress card
        ->has('progress_stats')
        // Verify audit data
        ->has('audit.id')
        ->where('audit.type', 'connection')
    );
});

/**
 * Test 7: Multi-page workflow with audit show and execute navigation.
 *
 * Verifies that navigating from audit show (details) page to execute
 * page maintains tablet sidebar state and provides consistent data.
 */
test('audit show to execute navigation maintains tablet layout state', function () {
    // Create an inventory audit with verifications
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Inventory,
        'scope_type' => AuditScopeType::Datacenter,
        'datacenter_id' => $this->datacenter->id,
    ]);

    $audit->assignees()->attach($this->admin);

    AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->count(3)
        ->create();

    // Step 1: View audit show page
    $showResponse = $this->actingAs($this->admin)
        ->withUnencryptedCookie('sidebar_state', 'false')
        ->get("/audits/{$audit->id}");

    $showResponse->assertSuccessful();
    $showResponse->assertInertia(fn (Assert $page) => $page
        ->component('Audits/Show')
        ->where('sidebarOpen', false)
        ->has('audit')
        ->where('audit.id', $audit->id)
    );

    // Step 2: Navigate to execute page
    $executeResponse = $this->actingAs($this->admin)
        ->withUnencryptedCookie('sidebar_state', 'false')
        ->get("/audits/{$audit->id}/inventory-execute");

    $executeResponse->assertSuccessful();
    $executeResponse->assertInertia(fn (Assert $page) => $page
        ->component('Audits/InventoryExecute')
        ->where('sidebarOpen', false)
        ->has('audit')
        ->where('audit.id', $audit->id)
        // Verify same audit data is accessible
        ->has('progress_stats')
    );
});

/**
 * Test 8: Bulk verification workflow works correctly for tablet batch operations.
 *
 * Verifies that bulk verify works with multiple selections, supporting
 * touch-friendly batch verification on tablet devices.
 */
test('bulk verification workflow supports tablet batch operations', function () {
    // Create an inventory audit
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Inventory,
        'scope_type' => AuditScopeType::Datacenter,
        'datacenter_id' => $this->datacenter->id,
    ]);

    // Create multiple device verifications
    $verifications = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->count(5)
        ->create();

    // Select subset of verifications (simulating tablet multi-select)
    $selectedIds = $verifications->take(3)->pluck('id')->toArray();

    // Bulk verify selected items
    $bulkResponse = $this->actingAs($this->admin)
        ->postJson("/api/audits/{$audit->id}/device-verifications/bulk-verify", [
            'verification_ids' => $selectedIds,
        ]);

    $bulkResponse->assertSuccessful();
    $bulkResponse->assertJsonPath('results.verified_count', 3);

    // Verify selected items were updated
    foreach ($selectedIds as $id) {
        $verification = AuditDeviceVerification::find($id);
        expect($verification->verification_status)->toBe(DeviceVerificationStatus::Verified);
    }

    // Verify non-selected items remain pending
    $remainingIds = $verifications->skip(3)->pluck('id')->toArray();
    foreach ($remainingIds as $id) {
        $verification = AuditDeviceVerification::find($id);
        expect($verification->verification_status)->toBe(DeviceVerificationStatus::Pending);
    }
});
