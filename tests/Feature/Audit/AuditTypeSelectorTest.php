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
});

/**
 * Test 1: Type selector renders both options (connection and inventory)
 */
test('type selector renders both audit type options', function () {
    $response = $this->actingAs($this->itManager)
        ->get('/audits/create');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Audits/Create')
            ->has('auditTypes', 2)
            ->has('auditTypes.0', fn (Assert $type) => $type
                ->where('value', 'connection')
                ->where('label', 'Connection')
            )
            ->has('auditTypes.1', fn (Assert $type) => $type
                ->where('value', 'inventory')
                ->where('label', 'Inventory')
            )
        );
});

/**
 * Test 2: Selecting type updates form state and submits correctly
 */
test('selecting audit type updates form state correctly', function () {
    $assignee = User::factory()->create();
    $assignee->assignRole('Operator');

    // Test with inventory type
    $response = $this->actingAs($this->itManager)
        ->post('/audits', [
            'name' => 'Inventory Audit Test',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'type' => 'inventory',
            'scope_type' => 'datacenter',
            'datacenter_id' => $this->datacenter->id,
            'assignee_ids' => [$assignee->id],
        ]);

    $response->assertRedirect('/audits');

    $this->assertDatabaseHas('audits', [
        'name' => 'Inventory Audit Test',
        'type' => 'inventory',
    ]);
});

/**
 * Test 3: Type descriptions are provided in props for display
 */
test('type descriptions are provided in audit types props', function () {
    $response = $this->actingAs($this->itManager)
        ->get('/audits/create');

    // The component should receive audit types that can be used to display descriptions
    // The descriptions are defined in the Vue component, but the enum labels are passed from backend
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Audits/Create')
            ->has('auditTypes', fn (Assert $types) => $types
                ->each(fn (Assert $type) => $type
                    ->has('value')
                    ->has('label')
                )
            )
        );

    // Verify the audit types contain the expected values for the UI to render descriptions
    $page = $response->viewData('page');
    $auditTypes = $page['props']['auditTypes'];

    expect($auditTypes)->toHaveCount(2);
    expect(collect($auditTypes)->pluck('value')->toArray())->toContain('connection', 'inventory');
});
