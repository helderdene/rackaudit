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

    // Disable Inertia page existence check since Vue components are created in Task Group 3
    config(['inertia.testing.ensure_pages_exist' => false]);
});

/**
 * Test 1: Datacenter index returns paginated list with search filtering
 */
test('datacenter index returns paginated list with search filtering', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create datacenters with different names and cities
    Datacenter::factory()->create([
        'name' => 'NYC Primary Data Center',
        'city' => 'New York',
        'primary_contact_name' => 'John Smith',
    ]);

    Datacenter::factory()->create([
        'name' => 'LA Secondary Data Center',
        'city' => 'Los Angeles',
        'primary_contact_name' => 'Jane Doe',
    ]);

    Datacenter::factory()->create([
        'name' => 'Chicago Backup',
        'city' => 'Chicago',
        'primary_contact_name' => 'Bob Wilson',
    ]);

    // Test basic index
    $response = $this->actingAs($admin)->get('/datacenters');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Datacenters/Index')
            ->has('datacenters.data', 3)
        );

    // Test search by name
    $response = $this->actingAs($admin)->get('/datacenters?search=NYC');
    $response->assertInertia(fn (Assert $page) => $page
        ->has('datacenters.data', 1)
        ->where('datacenters.data.0.name', 'NYC Primary Data Center')
    );

    // Test search by city
    $response = $this->actingAs($admin)->get('/datacenters?search=Chicago');
    $response->assertInertia(fn (Assert $page) => $page
        ->has('datacenters.data', 1)
        ->where('datacenters.data.0.city', 'Chicago')
    );

    // Test search by contact name
    $response = $this->actingAs($admin)->get('/datacenters?search=Jane');
    $response->assertInertia(fn (Assert $page) => $page
        ->has('datacenters.data', 1)
        ->where('datacenters.data.0.primary_contact_name', 'Jane Doe')
    );
});

/**
 * Test 2: Index filters by user access (non-admin sees only assigned datacenters)
 */
test('index filters datacenters by user access for non-admin users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    // Create 3 datacenters
    $dc1 = Datacenter::factory()->create(['name' => 'Assigned DC 1']);
    $dc2 = Datacenter::factory()->create(['name' => 'Assigned DC 2']);
    $dc3 = Datacenter::factory()->create(['name' => 'Not Assigned DC']);

    // Assign operator to only 2 datacenters
    $operator->datacenters()->attach([$dc1->id, $dc2->id]);

    // Admin sees all datacenters
    $response = $this->actingAs($admin)->get('/datacenters');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('datacenters.data', 3)
        );

    // Operator sees only assigned datacenters
    $response = $this->actingAs($operator)->get('/datacenters');
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('datacenters.data', 2)
        );
});

/**
 * Test 3: Create returns form data with Inertia response
 */
test('create returns form page with Inertia response', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $response = $this->actingAs($admin)->get('/datacenters/create');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Datacenters/Create')
        );
});

/**
 * Test 4: Store creates datacenter with valid data
 */
test('store creates datacenter with valid data', function () {
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
        'secondary_contact_name' => 'Jane Doe',
        'secondary_contact_email' => 'jane@example.com',
        'secondary_contact_phone' => '+1-555-987-6543',
    ];

    $response = $this->actingAs($admin)
        ->post('/datacenters', $datacenterData);

    $response->assertRedirect('/datacenters');

    $this->assertDatabaseHas('datacenters', [
        'name' => 'New Test Datacenter',
        'city' => 'San Francisco',
        'primary_contact_email' => 'john@example.com',
    ]);
});

/**
 * Test 5: Store validation rejects invalid data (missing required fields)
 */
test('store validation rejects invalid data and missing required fields', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Test with empty data
    $response = $this->actingAs($admin)
        ->post('/datacenters', []);

    $response->assertSessionHasErrors([
        'name',
        'address_line_1',
        'city',
        'state_province',
        'postal_code',
        'country',
        'primary_contact_name',
        'primary_contact_email',
        'primary_contact_phone',
    ]);

    // Test with invalid email format
    $response = $this->actingAs($admin)
        ->post('/datacenters', [
            'name' => 'Test DC',
            'address_line_1' => '123 Main St',
            'city' => 'Test City',
            'state_province' => 'TS',
            'postal_code' => '12345',
            'country' => 'USA',
            'primary_contact_name' => 'Test Contact',
            'primary_contact_email' => 'invalid-email',
            'primary_contact_phone' => '+1-555-123-4567',
        ]);

    $response->assertSessionHasErrors(['primary_contact_email']);
});

/**
 * Test 6: Show returns datacenter details
 */
test('show returns datacenter details', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create([
        'name' => 'Test Datacenter',
        'city' => 'Boston',
        'primary_contact_name' => 'Test Contact',
    ]);

    $response = $this->actingAs($admin)->get("/datacenters/{$datacenter->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Datacenters/Show')
            ->has('datacenter')
            ->where('datacenter.name', 'Test Datacenter')
            ->where('datacenter.city', 'Boston')
            ->where('datacenter.primary_contact_name', 'Test Contact')
        );
});

/**
 * Test 7: Update modifies existing datacenter
 */
test('update modifies existing datacenter', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $datacenter = Datacenter::factory()->create([
        'name' => 'Original Name',
        'city' => 'Original City',
    ]);

    $response = $this->actingAs($admin)
        ->put("/datacenters/{$datacenter->id}", [
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

    $response->assertRedirect('/datacenters');

    $datacenter->refresh();
    expect($datacenter->name)->toBe('Updated Name');
    expect($datacenter->city)->toBe('Updated City');
});

/**
 * Test 8: Destroy removes datacenter with proper authorization check
 */
test('destroy removes datacenter with proper authorization check', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    $datacenter = Datacenter::factory()->create(['name' => 'To Be Deleted']);
    $datacenterId = $datacenter->id;

    // Operator cannot delete
    $response = $this->actingAs($operator)
        ->delete("/datacenters/{$datacenterId}");

    $response->assertForbidden();
    expect(Datacenter::find($datacenterId))->not->toBeNull();

    // Administrator can delete
    $response = $this->actingAs($admin)
        ->delete("/datacenters/{$datacenterId}");

    $response->assertRedirect('/datacenters');
    expect(Datacenter::find($datacenterId))->toBeNull();
});
