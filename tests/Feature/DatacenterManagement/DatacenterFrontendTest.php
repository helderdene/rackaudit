<?php

use App\Models\Datacenter;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
});

/**
 * Test 1: Index page renders datacenter list with correct columns
 */
test('index page renders datacenter list with correct columns', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create([
        'name' => 'Test Datacenter',
        'city' => 'New York',
        'country' => 'United States',
        'primary_contact_name' => 'John Doe',
        'primary_contact_email' => 'john@example.com',
        'primary_contact_phone' => '+1-555-123-4567',
    ]);

    $response = $this->actingAs($admin)->get('/datacenters');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Datacenters/Index')
            ->has('datacenters.data', 1)
            ->has('datacenters.data.0', fn (Assert $dc) => $dc
                ->where('id', $datacenter->id)
                ->where('name', 'Test Datacenter')
                ->where('city', 'New York')
                ->where('country', 'United States')
                ->where('primary_contact_name', 'John Doe')
                ->where('primary_contact_email', 'john@example.com')
                ->where('primary_contact_phone', '+1-555-123-4567')
                ->has('formatted_location')
                ->etc()
            )
            ->has('filters')
            ->has('canCreate')
        );
});

/**
 * Test 2: Index page search filters results
 */
test('index page search filters results correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    Datacenter::factory()->create([
        'name' => 'NYC Primary Data Center',
        'city' => 'New York',
        'primary_contact_name' => 'Alice Smith',
    ]);

    Datacenter::factory()->create([
        'name' => 'LA Secondary Data Center',
        'city' => 'Los Angeles',
        'primary_contact_name' => 'Bob Jones',
    ]);

    // Test that search by name works
    $response = $this->actingAs($admin)->get('/datacenters?search=NYC');
    $response->assertInertia(fn (Assert $page) => $page
        ->has('datacenters.data', 1)
        ->where('datacenters.data.0.name', 'NYC Primary Data Center')
        ->where('filters.search', 'NYC')
    );

    // Test that search by city works
    $response = $this->actingAs($admin)->get('/datacenters?search=Angeles');
    $response->assertInertia(fn (Assert $page) => $page
        ->has('datacenters.data', 1)
        ->where('datacenters.data.0.city', 'Los Angeles')
    );

    // Test that search by contact name works
    $response = $this->actingAs($admin)->get('/datacenters?search=Alice');
    $response->assertInertia(fn (Assert $page) => $page
        ->has('datacenters.data', 1)
        ->where('datacenters.data.0.primary_contact_name', 'Alice Smith')
    );
});

/**
 * Test 3: Create form submits with valid data
 */
test('create form submits with valid data', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenterData = [
        'name' => 'New Test Datacenter',
        'address_line_1' => '123 Main Street',
        'address_line_2' => 'Suite 500',
        'city' => 'San Francisco',
        'state_province' => 'CA',
        'postal_code' => '94105',
        'country' => 'United States',
        'company_name' => 'Test Company',
        'primary_contact_name' => 'John Doe',
        'primary_contact_email' => 'john@example.com',
        'primary_contact_phone' => '+1-555-123-4567',
    ];

    $response = $this->actingAs($admin)
        ->post('/datacenters', $datacenterData);

    $response->assertRedirect('/datacenters')
        ->assertSessionHas('success');

    $this->assertDatabaseHas('datacenters', [
        'name' => 'New Test Datacenter',
        'city' => 'San Francisco',
        'state_province' => 'CA',
        'primary_contact_email' => 'john@example.com',
    ]);
});

/**
 * Test 4: Show page displays all datacenter information
 */
test('show page displays all datacenter information', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create([
        'name' => 'Detail View Datacenter',
        'address_line_1' => '456 Tech Blvd',
        'address_line_2' => 'Floor 10',
        'city' => 'Boston',
        'state_province' => 'MA',
        'postal_code' => '02108',
        'country' => 'United States',
        'company_name' => 'Tech Corp',
        'primary_contact_name' => 'Jane Smith',
        'primary_contact_email' => 'jane@techcorp.com',
        'primary_contact_phone' => '+1-617-555-0000',
        'secondary_contact_name' => 'Bob Wilson',
        'secondary_contact_email' => 'bob@techcorp.com',
        'secondary_contact_phone' => '+1-617-555-0001',
    ]);

    $response = $this->actingAs($admin)->get("/datacenters/{$datacenter->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Datacenters/Show')
            ->has('datacenter', fn (Assert $dc) => $dc
                ->where('id', $datacenter->id)
                ->where('name', 'Detail View Datacenter')
                ->where('address_line_1', '456 Tech Blvd')
                ->where('address_line_2', 'Floor 10')
                ->where('city', 'Boston')
                ->where('state_province', 'MA')
                ->where('postal_code', '02108')
                ->where('country', 'United States')
                ->where('company_name', 'Tech Corp')
                ->where('primary_contact_name', 'Jane Smith')
                ->where('primary_contact_email', 'jane@techcorp.com')
                ->where('primary_contact_phone', '+1-617-555-0000')
                ->where('secondary_contact_name', 'Bob Wilson')
                ->where('secondary_contact_email', 'bob@techcorp.com')
                ->where('secondary_contact_phone', '+1-617-555-0001')
                ->has('formatted_address')
                ->has('formatted_location')
                ->etc()
            )
            ->has('canEdit')
            ->has('canDelete')
        );
});

/**
 * Test 5: Edit form loads existing data and submits changes
 */
test('edit form loads existing data and submits changes', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create([
        'name' => 'Original Name',
        'address_line_1' => '100 Original St',
        'city' => 'Original City',
        'state_province' => 'OC',
        'postal_code' => '12345',
        'country' => 'USA',
        'primary_contact_name' => 'Original Contact',
        'primary_contact_email' => 'original@example.com',
        'primary_contact_phone' => '+1-555-000-0000',
    ]);

    // Check that edit page loads with existing data
    $response = $this->actingAs($admin)->get("/datacenters/{$datacenter->id}/edit");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Datacenters/Edit')
            ->has('datacenter', fn (Assert $dc) => $dc
                ->where('id', $datacenter->id)
                ->where('name', 'Original Name')
                ->where('city', 'Original City')
                ->etc()
            )
        );

    // Submit changes
    $response = $this->actingAs($admin)->put("/datacenters/{$datacenter->id}", [
        'name' => 'Updated Name',
        'address_line_1' => $datacenter->address_line_1,
        'city' => 'Updated City',
        'state_province' => $datacenter->state_province,
        'postal_code' => $datacenter->postal_code,
        'country' => $datacenter->country,
        'primary_contact_name' => $datacenter->primary_contact_name,
        'primary_contact_email' => $datacenter->primary_contact_email,
        'primary_contact_phone' => $datacenter->primary_contact_phone,
    ]);

    $response->assertRedirect('/datacenters')
        ->assertSessionHas('success');

    $datacenter->refresh();
    expect($datacenter->name)->toBe('Updated Name');
    expect($datacenter->city)->toBe('Updated City');
});

/**
 * Test 6: Delete confirmation dialog works
 */
test('delete removes datacenter after confirmation', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create([
        'name' => 'To Be Deleted',
    ]);
    $datacenterId = $datacenter->id;

    // Verify datacenter exists
    expect(Datacenter::find($datacenterId))->not->toBeNull();

    // Delete the datacenter
    $response = $this->actingAs($admin)->delete("/datacenters/{$datacenterId}");

    $response->assertRedirect('/datacenters')
        ->assertSessionHas('success');

    // Verify datacenter is removed
    expect(Datacenter::find($datacenterId))->toBeNull();
});
