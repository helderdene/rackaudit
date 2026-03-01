<?php

/**
 * Audit Execution Tablet Optimization Tests
 *
 * Tests for tablet viewport optimizations in audit execution pages:
 * - Filter controls stack vertically on tablet viewport in Execute.vue
 * - Filter controls stack vertically on tablet viewport in InventoryExecute.vue
 * - Card-based view displays on tablet/mobile in DeviceVerificationTable
 * - Table view displays on desktop (lg+) in DeviceVerificationTable
 * - QR scanner overlay is larger on tablet viewports
 * - Haptic feedback triggers on successful scan (if Vibration API available)
 *
 * Note: These tests verify the backend data structures and document expected
 * frontend responsive behavior. The Vue components use Tailwind CSS responsive
 * classes which execute client-side.
 */

use App\Enums\AuditScopeType;
use App\Enums\AuditType;
use App\Enums\DeviceVerificationStatus;
use App\Models\Audit;
use App\Models\AuditDeviceVerification;
use App\Models\Device;
use App\Models\Rack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    // Create a user for testing
    $this->user = User::factory()->create();
});

/**
 * Test 1: Connection audit Execute page provides all required filter data for responsive layout.
 *
 * Verifies that the Execute page returns all necessary data for filter controls
 * that will stack vertically on tablet using flex-col layout classes.
 */
test('connection audit execute page provides filter data for tablet stacked layout', function () {
    // Create a connection audit that is in progress
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Connection,
    ]);

    // Assign user to audit
    $audit->assignees()->attach($this->user);

    $response = $this->actingAs($this->user)
        ->get("/audits/{$audit->id}/execute");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Audits/Execute')
        ->has('audit')
        ->where('audit.id', $audit->id)
        // Verify filter options are provided for dropdown controls
        ->has('discrepancy_types')
        ->has('verification_statuses')
        // Verify progress stats for progress card
        ->has('progress_stats')
    );
});

/**
 * Test 2: Inventory audit InventoryExecute page provides all filter and room data.
 *
 * Verifies that the InventoryExecute page returns rooms for cascading filters
 * that expand to full width on tablet viewport.
 */
test('inventory audit execute page provides room filter data for full-width dropdowns', function () {
    // Create an inventory audit that is in progress
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Inventory,
        'scope_type' => AuditScopeType::Datacenter,
    ]);

    // Assign user to audit
    $audit->assignees()->attach($this->user);

    // Create device for verification
    AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->create();

    $response = $this->actingAs($this->user)
        ->get("/audits/{$audit->id}/inventory-execute");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Audits/InventoryExecute')
        ->has('audit')
        ->where('audit.id', $audit->id)
        // Verify rooms are provided for cascading filter
        ->has('rooms')
        // Verify verification statuses for status filter
        ->has('verification_statuses')
        // Verify progress stats for progress card
        ->has('progress_stats')
    );
});

/**
 * Test 3: Device verifications API returns all required fields for card view display.
 *
 * Verifies that the API returns all necessary device data that will be displayed
 * in both table view (desktop) and card view (tablet/mobile).
 */
test('device verifications API returns complete data for card and table views', function () {
    // Create an inventory audit
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Inventory,
        'scope_type' => AuditScopeType::Datacenter,
    ]);

    // Create device with complete information for display
    $device = Device::factory()->create([
        'name' => 'Test Server 001',
        'asset_tag' => 'ASSET-12345',
        'serial_number' => 'SN-67890',
        'manufacturer' => 'Dell',
        'model' => 'PowerEdge R750',
        'u_height' => 2,
        'start_u' => 10,
    ]);

    AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->forDevice($device)
        ->pending()
        ->create();

    $response = $this->actingAs($this->user)
        ->getJson("/api/audits/{$audit->id}/device-verifications");

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'verification_status',
                'verification_status_label',
                'device' => [
                    'id',
                    'name',
                    'asset_tag',
                    'serial_number',
                    'manufacturer',
                    'model',
                    'u_height',
                    'start_u',
                ],
                'rack',
                'room',
                'is_locked',
            ],
        ],
        'meta' => [
            'current_page',
            'last_page',
            'total',
        ],
    ]);
});

/**
 * Test 4: Action button functionality works for touch-friendly verification workflow.
 *
 * Verifies that the verify action endpoint works correctly, supporting the
 * touch-friendly action buttons with minimum 44x44px tap targets.
 */
test('verify action works for touch-friendly action buttons', function () {
    // Create an inventory audit
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Inventory,
        'scope_type' => AuditScopeType::Datacenter,
    ]);

    // Create device and verification
    $verification = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->create();

    // Verify the device (simulating touch action on action button)
    $response = $this->actingAs($this->user)
        ->postJson("/api/audits/{$audit->id}/device-verifications/{$verification->id}/verify", [
            'notes' => 'Verified on tablet',
        ]);

    $response->assertSuccessful();

    // Verify the status was updated
    $verification->refresh();
    expect($verification->verification_status)->toBe(DeviceVerificationStatus::Verified);
});

/**
 * Test 5: Pagination returns page metadata for touch-friendly pagination controls.
 *
 * Verifies that the API returns proper pagination data that will be used
 * by touch-friendly Previous/Next buttons with adequate sizing.
 */
test('pagination API returns metadata for touch-friendly pagination controls', function () {
    // Create an inventory audit
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Inventory,
        'scope_type' => AuditScopeType::Datacenter,
    ]);

    // Create a single rack to use for all devices (avoids unique name constraint issues)
    $rack = Rack::factory()->create();

    // Create multiple devices on the same rack to trigger pagination
    for ($i = 0; $i < 30; $i++) {
        $device = Device::factory()->create([
            'rack_id' => $rack->id,
        ]);

        AuditDeviceVerification::factory()
            ->forAudit($audit)
            ->forDevice($device)
            ->pending()
            ->create();
    }

    // Request first page with 25 per page
    $response = $this->actingAs($this->user)
        ->getJson("/api/audits/{$audit->id}/device-verifications?page=1&per_page=25");

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data',
        'meta' => [
            'current_page',
            'last_page',
            'per_page',
            'total',
        ],
    ]);

    // Verify pagination values
    $response->assertJsonPath('meta.current_page', 1);
    $response->assertJsonPath('meta.last_page', 2);
    $response->assertJsonPath('meta.total', 30);
});

/**
 * Test 6: Bulk verify supports batch operations for efficient tablet workflow.
 *
 * Verifies that bulk verify works correctly, supporting efficient touch-based
 * selection and batch verification on tablet devices.
 */
test('bulk verify supports batch operations for tablet workflow', function () {
    // Create an inventory audit
    $audit = Audit::factory()->inProgress()->create([
        'type' => AuditType::Inventory,
        'scope_type' => AuditScopeType::Datacenter,
    ]);

    // Create multiple device verifications
    $verifications = AuditDeviceVerification::factory()
        ->forAudit($audit)
        ->pending()
        ->count(5)
        ->create();

    $verificationIds = $verifications->pluck('id')->toArray();

    // Bulk verify (simulating multi-selection on tablet)
    $response = $this->actingAs($this->user)
        ->postJson("/api/audits/{$audit->id}/device-verifications/bulk-verify", [
            'verification_ids' => $verificationIds,
        ]);

    $response->assertSuccessful();
    $response->assertJsonPath('results.verified_count', 5);

    // Verify all were updated
    foreach ($verificationIds as $id) {
        $verification = AuditDeviceVerification::find($id);
        expect($verification->verification_status)->toBe(DeviceVerificationStatus::Verified);
    }
});
