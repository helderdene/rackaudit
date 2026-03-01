<?php

use App\Enums\AuditScopeType;
use App\Enums\AuditType;
use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\ImplementationFile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create users with different roles
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrator');

    $this->itManager = User::factory()->create();
    $this->itManager->assignRole('IT Manager');

    $this->auditor = User::factory()->create();
    $this->auditor->assignRole('Auditor');

    $this->operator = User::factory()->create();
    $this->operator->assignRole('Operator');

    // Create a datacenter for testing
    $this->datacenter = Datacenter::factory()->create();

    // Assign datacenter access to non-admin users
    $this->operator->datacenters()->attach($this->datacenter);
    $this->auditor->datacenters()->attach($this->datacenter);
});

/**
 * Test 1: Create page loads with required data (datacenters and users)
 */
test('create page loads with required data for authorized users', function () {
    // Create an approved implementation file for the datacenter
    $implementationFile = ImplementationFile::factory()
        ->approved()
        ->create(['datacenter_id' => $this->datacenter->id]);

    $response = $this->actingAs($this->itManager)
        ->get('/audits/create');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Create')
            ->has('datacenters')
            ->has('assignableUsers')
        );
});

/**
 * Test 2: Store creates audit and redirects
 */
test('store creates audit and redirects for authorized users', function () {
    // Create an approved implementation file for connection audits
    ImplementationFile::factory()
        ->approved()
        ->create(['datacenter_id' => $this->datacenter->id]);

    $assignee = User::factory()->create();
    $assignee->assignRole('Operator');

    $response = $this->actingAs($this->itManager)
        ->post('/audits', [
            'name' => 'Q1 2025 Connection Audit',
            'description' => 'Quarterly connection verification',
            'due_date' => now()->addDays(14)->format('Y-m-d'),
            'type' => AuditType::Connection->value,
            'scope_type' => AuditScopeType::Datacenter->value,
            'datacenter_id' => $this->datacenter->id,
            'assignee_ids' => [$assignee->id],
        ]);

    $response->assertRedirect('/audits');
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('audits', [
        'name' => 'Q1 2025 Connection Audit',
        'description' => 'Quarterly connection verification',
        'type' => AuditType::Connection->value,
        'scope_type' => AuditScopeType::Datacenter->value,
        'datacenter_id' => $this->datacenter->id,
        'created_by' => $this->itManager->id,
    ]);

    // Verify assignee was attached
    $audit = Audit::where('name', 'Q1 2025 Connection Audit')->first();
    expect($audit->assignees)->toHaveCount(1);
    expect($audit->assignees->first()->id)->toBe($assignee->id);
});

/**
 * Test 3: Store auto-links latest approved implementation file for connection audits
 */
test('store auto-links latest approved implementation file for connection audits', function () {
    // Create multiple implementation files, including approved ones
    $oldApproved = ImplementationFile::factory()
        ->approved()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'created_at' => now()->subDays(10),
        ]);

    $latestApproved = ImplementationFile::factory()
        ->approved()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'created_at' => now()->subDays(1),
        ]);

    $pendingFile = ImplementationFile::factory()
        ->pendingApproval()
        ->create([
            'datacenter_id' => $this->datacenter->id,
            'created_at' => now(),
        ]);

    $assignee = User::factory()->create();
    $assignee->assignRole('Operator');

    $response = $this->actingAs($this->auditor)
        ->post('/audits', [
            'name' => 'Connection Audit with Auto-Link',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'type' => AuditType::Connection->value,
            'scope_type' => AuditScopeType::Datacenter->value,
            'datacenter_id' => $this->datacenter->id,
            'assignee_ids' => [$assignee->id],
        ]);

    $response->assertRedirect('/audits');

    $audit = Audit::where('name', 'Connection Audit with Auto-Link')->first();
    expect($audit)->not->toBeNull();
    expect($audit->implementation_file_id)->toBe($latestApproved->id);
});

/**
 * Test 4: Index page shows user's audits
 */
test('index page shows audits for authorized users', function () {
    // Create audits with the IT Manager assigned
    $audit1 = Audit::factory()->create([
        'name' => 'First Audit',
        'datacenter_id' => $this->datacenter->id,
    ]);
    $audit1->assignees()->attach($this->itManager->id);

    $audit2 = Audit::factory()->create([
        'name' => 'Second Audit',
        'datacenter_id' => $this->datacenter->id,
    ]);
    $audit2->assignees()->attach($this->itManager->id);

    // Create an audit not assigned to the IT Manager (should still be visible to admins)
    $unassignedAudit = Audit::factory()->create([
        'name' => 'Unassigned Audit',
        'datacenter_id' => $this->datacenter->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->get('/audits');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Index')
            ->has('audits.data', 3)
        );
});

/**
 * Test 5: Show page displays audit details
 */
test('show page displays audit details', function () {
    $assignee = User::factory()->create();
    $assignee->assignRole('Operator');

    $audit = Audit::factory()->create([
        'name' => 'Detailed Audit View',
        'description' => 'This is a test audit description',
        'datacenter_id' => $this->datacenter->id,
        'type' => AuditType::Inventory,
        'scope_type' => AuditScopeType::Datacenter,
    ]);
    $audit->assignees()->attach([$assignee->id, $this->operator->id]);

    $response = $this->actingAs($this->itManager)
        ->get("/audits/{$audit->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Show')
            ->has('audit', fn ($page) => $page
                ->where('id', $audit->id)
                ->where('name', 'Detailed Audit View')
                ->where('description', 'This is a test audit description')
                ->has('datacenter')
                ->has('assignees')
                ->etc()
            )
        );
});

/**
 * Test 6: Inventory audits do not require implementation file
 */
test('inventory audits are created without implementation file requirement', function () {
    $assignee = User::factory()->create();
    $assignee->assignRole('Operator');

    // No implementation files created - inventory audit should still work
    $response = $this->actingAs($this->itManager)
        ->post('/audits', [
            'name' => 'Inventory Audit No File',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'type' => AuditType::Inventory->value,
            'scope_type' => AuditScopeType::Datacenter->value,
            'datacenter_id' => $this->datacenter->id,
            'assignee_ids' => [$assignee->id],
        ]);

    $response->assertRedirect('/audits');

    $audit = Audit::where('name', 'Inventory Audit No File')->first();
    expect($audit)->not->toBeNull();
    expect($audit->implementation_file_id)->toBeNull();
});
