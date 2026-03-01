<?php

/**
 * Tests for the Per-Audit Breakdown Table on the Audit Dashboard.
 *
 * These tests verify that the breakdown table displays audit data correctly,
 * including columns, severity counts, row navigation, and sorting functionality.
 */

use App\Enums\AuditStatus;
use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\Finding;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create admin user for dashboard access
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    // Create datacenter for testing
    $this->datacenter = Datacenter::factory()->create(['name' => 'Test DC']);
});

/**
 * Test 1: Breakdown table renders with correct audit data
 */
test('breakdown table renders with audit data including all columns', function () {
    $audit = Audit::factory()->inProgress()->create([
        'name' => 'Q4 Connection Audit',
        'datacenter_id' => $this->datacenter->id,
    ]);

    // Create findings with different severities
    Finding::factory()->count(3)->critical()->forAudit($audit)->create();
    Finding::factory()->count(5)->high()->forAudit($audit)->create();
    Finding::factory()->count(8)->medium()->forAudit($audit)->create();
    Finding::factory()->count(2)->low()->forAudit($audit)->create();

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('auditBreakdown', 1)
            ->has('auditBreakdown.0', fn ($item) => $item
                ->where('id', $audit->id)
                ->where('name', 'Q4 Connection Audit')
                ->where('datacenter', 'Test DC')
                ->where('status', 'in_progress')
                ->where('status_label', AuditStatus::InProgress->label())
                ->where('critical', 3)
                ->where('high', 5)
                ->where('medium', 8)
                ->where('low', 2)
                ->where('total', 18)
            )
        );
});

/**
 * Test 2: Table columns display correctly with proper structure
 */
test('breakdown table data includes all required columns for display', function () {
    $audit = Audit::factory()->completed()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);
    Finding::factory()->critical()->forAudit($audit)->create();

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('auditBreakdown.0', fn ($item) => $item
                // Verify all required columns are present
                ->has('id')
                ->has('name')
                ->has('datacenter')
                ->has('status')
                ->has('status_label')
                ->has('critical')
                ->has('high')
                ->has('medium')
                ->has('low')
                ->has('total')
            )
        );
});

/**
 * Test 3: Audit ID is available for row click navigation to audit show page
 */
test('breakdown table provides audit IDs for row navigation', function () {
    $audit1 = Audit::factory()->create(['datacenter_id' => $this->datacenter->id]);
    $audit2 = Audit::factory()->create(['datacenter_id' => $this->datacenter->id]);

    Finding::factory()->critical()->forAudit($audit1)->create();
    Finding::factory()->high()->forAudit($audit2)->create();

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('auditBreakdown', 2)
            // Verify each audit has a valid ID for navigation
            ->where('auditBreakdown.0.id', fn ($id) => is_int($id) && $id > 0)
            ->where('auditBreakdown.1.id', fn ($id) => is_int($id) && $id > 0)
        );
});

/**
 * Test 4: Breakdown table is sorted by total findings descending
 */
test('breakdown table is sorted by total findings descending', function () {
    // Create audits with different finding counts
    $auditSmall = Audit::factory()->create([
        'name' => 'Small Audit',
        'datacenter_id' => $this->datacenter->id,
    ]);
    $auditLarge = Audit::factory()->create([
        'name' => 'Large Audit',
        'datacenter_id' => $this->datacenter->id,
    ]);
    $auditMedium = Audit::factory()->create([
        'name' => 'Medium Audit',
        'datacenter_id' => $this->datacenter->id,
    ]);

    // Small audit: 2 findings
    Finding::factory()->count(2)->critical()->forAudit($auditSmall)->create();

    // Large audit: 10 findings
    Finding::factory()->count(10)->high()->forAudit($auditLarge)->create();

    // Medium audit: 5 findings
    Finding::factory()->count(5)->medium()->forAudit($auditMedium)->create();

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('auditBreakdown', 3)
            // First should be Large Audit (10 findings)
            ->where('auditBreakdown.0.name', 'Large Audit')
            ->where('auditBreakdown.0.total', 10)
            // Second should be Medium Audit (5 findings)
            ->where('auditBreakdown.1.name', 'Medium Audit')
            ->where('auditBreakdown.1.total', 5)
            // Third should be Small Audit (2 findings)
            ->where('auditBreakdown.2.name', 'Small Audit')
            ->where('auditBreakdown.2.total', 2)
        );
});

/**
 * Test 5: Zero severity counts are included in breakdown data
 */
test('breakdown table displays zero counts for severity levels with no findings', function () {
    $audit = Audit::factory()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);

    // Create only critical findings - other severities should be 0
    Finding::factory()->count(3)->critical()->forAudit($audit)->create();

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('auditBreakdown', 1)
            // Verify zero counts for severity levels with no findings
            ->where('auditBreakdown.0.critical', 3)
            ->where('auditBreakdown.0.high', 0)
            ->where('auditBreakdown.0.medium', 0)
            ->where('auditBreakdown.0.low', 0)
            ->where('auditBreakdown.0.total', 3)
        );
});
