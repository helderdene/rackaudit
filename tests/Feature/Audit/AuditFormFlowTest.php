<?php

use App\Enums\AuditScopeType;
use App\Enums\AuditStatus;
use App\Enums\AuditType;
use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\ImplementationFile;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create users with different roles
    $this->itManager = User::factory()->create();
    $this->itManager->assignRole('IT Manager');

    $this->operator = User::factory()->create();
    $this->operator->assignRole('Operator');

    // Create a datacenter for testing
    $this->datacenter = Datacenter::factory()->create();

    // Create rooms for the datacenter
    $this->room = Room::factory()->create([
        'datacenter_id' => $this->datacenter->id,
    ]);
});

/**
 * Test 1: Create page renders with all components
 */
test('create page renders with all required data for form components', function () {
    // Create an approved implementation file
    ImplementationFile::factory()
        ->approved()
        ->create(['datacenter_id' => $this->datacenter->id]);

    $response = $this->actingAs($this->itManager)
        ->get('/audits/create');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Create')
            ->has('datacenters')
            ->has('assignableUsers')
            ->has('auditTypes')
            ->has('scopeTypes')
            // Verify audit types contain expected values
            ->where('auditTypes.0.value', 'connection')
            ->where('auditTypes.0.label', 'Connection')
            ->where('auditTypes.1.value', 'inventory')
            ->where('auditTypes.1.label', 'Inventory')
            // Verify scope types contain expected values
            ->where('scopeTypes.0.value', 'datacenter')
            ->where('scopeTypes.1.value', 'room')
            ->where('scopeTypes.2.value', 'racks')
        );
});

/**
 * Test 2: Form submission creates audit successfully
 */
test('form submission creates audit with all required fields and relationships', function () {
    // Create approved implementation file for connection audit
    $implementationFile = ImplementationFile::factory()
        ->approved()
        ->create(['datacenter_id' => $this->datacenter->id]);

    $response = $this->actingAs($this->itManager)
        ->post('/audits', [
            'name' => 'Full Form Flow Test Audit',
            'description' => 'Testing the complete form submission flow',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'type' => AuditType::Connection->value,
            'scope_type' => AuditScopeType::Datacenter->value,
            'datacenter_id' => $this->datacenter->id,
            'assignee_ids' => [$this->operator->id],
        ]);

    $response->assertRedirect('/audits');
    $response->assertSessionHas('success');

    // Verify audit was created with correct data
    $audit = Audit::where('name', 'Full Form Flow Test Audit')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->description)->toBe('Testing the complete form submission flow')
        ->and($audit->type)->toBe(AuditType::Connection)
        ->and($audit->scope_type)->toBe(AuditScopeType::Datacenter)
        ->and($audit->status)->toBe(AuditStatus::Pending)
        ->and($audit->datacenter_id)->toBe($this->datacenter->id)
        ->and($audit->implementation_file_id)->toBe($implementationFile->id)
        ->and($audit->created_by)->toBe($this->itManager->id);

    // Verify assignees were attached
    expect($audit->assignees)->toHaveCount(1);
    expect($audit->assignees->first()->id)->toBe($this->operator->id);
});

/**
 * Test 3: Validation errors display correctly
 */
test('validation errors are returned for invalid form submissions', function () {
    $response = $this->actingAs($this->itManager)
        ->post('/audits', [
            // Missing all required fields
        ]);

    $response->assertSessionHasErrors([
        'name',
        'due_date',
        'type',
        'scope_type',
        'datacenter_id',
        'assignee_ids',
    ]);
});

/**
 * Test 4: Redirect after successful creation
 */
test('successful creation redirects to audit index with success message', function () {
    // Create approved implementation file
    ImplementationFile::factory()
        ->approved()
        ->create(['datacenter_id' => $this->datacenter->id]);

    $response = $this->actingAs($this->itManager)
        ->post('/audits', [
            'name' => 'Redirect Test Audit',
            'due_date' => now()->addDays(14)->format('Y-m-d'),
            'type' => AuditType::Inventory->value,
            'scope_type' => AuditScopeType::Room->value,
            'datacenter_id' => $this->datacenter->id,
            'room_id' => $this->room->id,
            'assignee_ids' => [$this->operator->id],
        ]);

    $response->assertRedirect('/audits');
    $response->assertSessionHas('success', 'Audit created successfully.');

    // Follow the redirect and verify audit appears in list
    $indexResponse = $this->actingAs($this->itManager)
        ->get('/audits');

    $indexResponse->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audits/Index')
            ->has('audits.data', fn ($data) => $data
                ->first(fn ($audit) => $audit
                    ->where('name', 'Redirect Test Audit')
                    ->etc()
                )
            )
        );
});
