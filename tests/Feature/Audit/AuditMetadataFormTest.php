<?php

use App\Models\Datacenter;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    $this->itManager = User::factory()->create();
    $this->itManager->assignRole('IT Manager');

    $this->datacenter = Datacenter::factory()->create();

    // Create assignable users (Operators and Auditors)
    $this->operator1 = User::factory()->create([
        'name' => 'John Operator',
        'email' => 'john.operator@example.com',
        'status' => 'active',
    ]);
    $this->operator1->assignRole('Operator');

    $this->operator2 = User::factory()->create([
        'name' => 'Jane Auditor',
        'email' => 'jane.auditor@example.com',
        'status' => 'active',
    ]);
    $this->operator2->assignRole('Auditor');

    // Inactive user should not appear
    $this->inactiveOperator = User::factory()->create([
        'name' => 'Inactive User',
        'status' => 'inactive',
    ]);
    $this->inactiveOperator->assignRole('Operator');
});

/**
 * Test 1: Metadata form fields render with validation requirements
 */
test('metadata form fields render with validation requirements', function () {
    $response = $this->actingAs($this->itManager)
        ->get('/audits/create');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Audits/Create')
            // Verify audit types and scope types are present for full form
            ->has('auditTypes')
            ->has('scopeTypes')
            ->has('datacenters')
            ->has('assignableUsers')
        );

    // Test validation for required metadata fields
    $validationResponse = $this->actingAs($this->itManager)
        ->post('/audits', [
            // Missing name, due_date, assignee_ids
            'type' => 'inventory',
            'scope_type' => 'datacenter',
            'datacenter_id' => $this->datacenter->id,
        ]);

    $validationResponse->assertSessionHasErrors([
        'name' => 'The audit name is required.',
        'due_date' => 'The due date is required.',
        'assignee_ids' => 'Please assign at least one user to this audit.',
    ]);
});

/**
 * Test 2: Date picker validates due_date correctly (past dates rejected)
 */
test('date picker validates due_date correctly', function () {
    // Test that past dates are rejected
    $pastDateResponse = $this->actingAs($this->itManager)
        ->post('/audits', [
            'name' => 'Past Date Test',
            'description' => 'Testing past date validation',
            'due_date' => now()->subDay()->format('Y-m-d'),
            'type' => 'inventory',
            'scope_type' => 'datacenter',
            'datacenter_id' => $this->datacenter->id,
            'assignee_ids' => [$this->operator1->id],
        ]);

    $pastDateResponse->assertSessionHasErrors([
        'due_date' => 'The due date must be today or a future date.',
    ]);

    // Test that today's date is accepted
    $todayResponse = $this->actingAs($this->itManager)
        ->post('/audits', [
            'name' => 'Today Date Test',
            'description' => 'Testing today date validation',
            'due_date' => now()->format('Y-m-d'),
            'type' => 'inventory',
            'scope_type' => 'datacenter',
            'datacenter_id' => $this->datacenter->id,
            'assignee_ids' => [$this->operator1->id],
        ]);

    $todayResponse->assertRedirect('/audits');

    // Test that future date is accepted
    $futureDateResponse = $this->actingAs($this->itManager)
        ->post('/audits', [
            'name' => 'Future Date Test',
            'description' => 'Testing future date validation',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'type' => 'inventory',
            'scope_type' => 'datacenter',
            'datacenter_id' => $this->datacenter->id,
            'assignee_ids' => [$this->operator1->id],
        ]);

    $futureDateResponse->assertRedirect('/audits');

    // Verify audits were created with correct due dates
    $todayAudit = \App\Models\Audit::where('name', 'Today Date Test')->first();
    expect($todayAudit)->not->toBeNull()
        ->and($todayAudit->due_date->format('Y-m-d'))->toBe(now()->format('Y-m-d'));

    $futureAudit = \App\Models\Audit::where('name', 'Future Date Test')->first();
    expect($futureAudit)->not->toBeNull()
        ->and($futureAudit->due_date->format('Y-m-d'))->toBe(now()->addDays(30)->format('Y-m-d'));
});

/**
 * Test 3: Assignee multi-select loads users from API correctly
 */
test('assignee multi-select loads users from API correctly', function () {
    // Test the assignable users API endpoint
    $response = $this->actingAs($this->itManager)
        ->getJson('/api/audits/assignable-users');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email'],
            ],
        ]);

    // Verify the response contains the active operators/auditors
    $users = $response->json('data');
    $userIds = array_column($users, 'id');
    $userEmails = array_column($users, 'email');

    expect($userIds)->toContain($this->operator1->id)
        ->and($userIds)->toContain($this->operator2->id)
        // Inactive user should not be included
        ->and($userIds)->not->toContain($this->inactiveOperator->id);

    expect($userEmails)->toContain('john.operator@example.com')
        ->and($userEmails)->toContain('jane.auditor@example.com');

    // Test creating audit with multiple assignees
    $createResponse = $this->actingAs($this->itManager)
        ->post('/audits', [
            'name' => 'Multi Assignee Test',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'type' => 'inventory',
            'scope_type' => 'datacenter',
            'datacenter_id' => $this->datacenter->id,
            'assignee_ids' => [$this->operator1->id, $this->operator2->id],
        ]);

    $createResponse->assertRedirect('/audits');

    // Verify audit was created with both assignees
    $audit = \App\Models\Audit::where('name', 'Multi Assignee Test')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->assignees)->toHaveCount(2)
        ->and($audit->assignees->pluck('id')->toArray())
        ->toContain($this->operator1->id)
        ->toContain($this->operator2->id);

    // Test that at least one assignee is required
    $noAssigneeResponse = $this->actingAs($this->itManager)
        ->post('/audits', [
            'name' => 'No Assignee Test',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'type' => 'inventory',
            'scope_type' => 'datacenter',
            'datacenter_id' => $this->datacenter->id,
            'assignee_ids' => [],
        ]);

    $noAssigneeResponse->assertSessionHasErrors(['assignee_ids']);
});
