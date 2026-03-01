<?php

/**
 * Tests for the Audit Dashboard active audit progress bars section.
 *
 * These tests verify that progress bars render correctly for in-progress audits,
 * display accurate progress percentages, show due date warnings, and handle
 * empty states when no in-progress audits exist.
 */

use App\Models\Audit;
use App\Models\AuditConnectionVerification;
use App\Models\AuditDeviceVerification;
use App\Models\Datacenter;
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
 * Test 1: Progress bars render for in-progress audits with correct data structure.
 */
test('progress bars render for in-progress audits with correct data', function () {
    // Create in-progress audits
    $connectionAudit = Audit::factory()
        ->connectionType()
        ->inProgress()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'name' => 'Connection Audit Test',
            'due_date' => now()->addDays(14),
        ]);

    $inventoryAudit = Audit::factory()
        ->inventoryType()
        ->inProgress()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'name' => 'Inventory Audit Test',
            'due_date' => now()->addDays(10),
        ]);

    // Create some verifications for progress calculation
    AuditConnectionVerification::factory()->count(3)->pending()->forAudit($connectionAudit)->create();
    AuditConnectionVerification::factory()->count(2)->verified()->forAudit($connectionAudit)->create();

    AuditDeviceVerification::factory()->count(4)->pending()->forAudit($inventoryAudit)->create();
    AuditDeviceVerification::factory()->count(6)->verified()->forAudit($inventoryAudit)->create();

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('activeAuditProgress', 2)
            ->has('activeAuditProgress.0', fn ($audit) => $audit
                ->has('id')
                ->has('name')
                ->has('datacenter')
                ->has('dueDate')
                ->has('progressPercentage')
                ->has('type')
                ->has('type_label')
                ->has('isOverdue')
                ->has('isDueSoon')
            )
        );
});

/**
 * Test 2: Progress percentage calculates correctly for connection and inventory audits.
 */
test('progress percentage displays correctly for both audit types', function () {
    // Create connection audit with 5 total, 2 completed = 40%
    $connectionAudit = Audit::factory()
        ->connectionType()
        ->inProgress()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'name' => 'Connection Progress Test',
            'due_date' => now()->addDays(14),
        ]);

    AuditConnectionVerification::factory()->count(3)->pending()->forAudit($connectionAudit)->create();
    AuditConnectionVerification::factory()->count(2)->verified()->forAudit($connectionAudit)->create();

    // Create inventory audit with 10 total, 6 completed = 60%
    $inventoryAudit = Audit::factory()
        ->inventoryType()
        ->inProgress()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'name' => 'Inventory Progress Test',
            'due_date' => now()->addDays(10),
        ]);

    AuditDeviceVerification::factory()->count(4)->pending()->forAudit($inventoryAudit)->create();
    AuditDeviceVerification::factory()->count(6)->verified()->forAudit($inventoryAudit)->create();

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('activeAuditProgress', 2)
            // Verify connection audit progress (2/5 = 40%)
            ->where('activeAuditProgress.0.progressPercentage', fn ($value) => abs($value - 40.0) < 0.01)
            ->where('activeAuditProgress.0.type', 'connection')
            // Verify inventory audit progress (6/10 = 60%)
            ->where('activeAuditProgress.1.progressPercentage', fn ($value) => abs($value - 60.0) < 0.01)
            ->where('activeAuditProgress.1.type', 'inventory')
        );
});

/**
 * Test 3: Due date displays with appropriate warning styling indicators.
 */
test('due date displays with appropriate warning styling for overdue and due soon', function () {
    // Create overdue audit (past due date)
    $overdueAudit = Audit::factory()
        ->connectionType()
        ->inProgress()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'name' => 'Overdue Audit',
            'due_date' => now()->subDays(3),
        ]);

    // Create due soon audit (within 7 days)
    $dueSoonAudit = Audit::factory()
        ->connectionType()
        ->inProgress()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'name' => 'Due Soon Audit',
            'due_date' => now()->addDays(5),
        ]);

    // Create normal audit (more than 7 days)
    $normalAudit = Audit::factory()
        ->connectionType()
        ->inProgress()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'name' => 'Normal Audit',
            'due_date' => now()->addDays(30),
        ]);

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('activeAuditProgress', 3)
            // Check overdue audit has isOverdue = true
            ->where('activeAuditProgress.0.isOverdue', true)
            ->where('activeAuditProgress.0.isDueSoon', false)
            // Check due soon audit has isDueSoon = true
            ->where('activeAuditProgress.1.isOverdue', false)
            ->where('activeAuditProgress.1.isDueSoon', true)
            // Check normal audit has both false
            ->where('activeAuditProgress.2.isOverdue', false)
            ->where('activeAuditProgress.2.isDueSoon', false)
        );
});

/**
 * Test 4: Section data is empty when no in-progress audits exist.
 */
test('active audit progress section returns empty array when no in-progress audits', function () {
    // Create audits with statuses other than InProgress
    Audit::factory()->pending()->create(['datacenter_id' => $this->datacenter->id]);
    Audit::factory()->completed()->create(['datacenter_id' => $this->datacenter->id]);
    Audit::factory()->cancelled()->create(['datacenter_id' => $this->datacenter->id]);

    $response = $this->actingAs($this->admin)->get('/audits/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Dashboard')
            ->has('activeAuditProgress', 0)
        );
});
