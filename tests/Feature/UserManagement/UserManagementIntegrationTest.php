<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
});

/**
 * Test 1: Non-administrator users cannot access user management routes
 */
test('non-administrator users cannot access user management routes', function () {
    $viewer = User::factory()->create(['status' => 'active']);
    $viewer->assignRole('Viewer');

    $operator = User::factory()->create(['status' => 'active']);
    $operator->assignRole('Operator');

    // Test as Viewer
    $this->actingAs($viewer)->get('/users')->assertForbidden();
    $this->actingAs($viewer)->get('/users/create')->assertForbidden();
    $this->actingAs($viewer)->post('/users', [])->assertForbidden();

    // Test as Operator
    $this->actingAs($operator)->get('/users')->assertForbidden();
    $this->actingAs($operator)->delete('/users/1')->assertForbidden();
});

/**
 * Test 2: Email uniqueness validation prevents duplicate emails
 */
test('email uniqueness validation prevents duplicate emails', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $existingUser = User::factory()->create([
        'email' => 'existing@example.com',
    ]);
    $existingUser->assignRole('Viewer');

    // Try to create user with duplicate email
    $response = $this->actingAs($admin)
        ->post('/users', [
            'name' => 'New User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'Viewer',
            'status' => 'active',
            'datacenter_ids' => [],
        ]);

    $response->assertSessionHasErrors(['email']);

    // Verify only one user has this email
    expect(User::where('email', 'existing@example.com')->count())->toBe(1);
});

/**
 * Test 3: Search and filter combinations work correctly
 */
test('search and filter combinations work correctly together', function () {
    $admin = User::factory()->create(['name' => 'Admin User']);
    $admin->assignRole('Administrator');

    // Create test users with specific combinations
    $user1 = User::factory()->create([
        'name' => 'John Smith',
        'email' => 'john@example.com',
        'status' => 'active',
    ]);
    $user1->assignRole('Viewer');

    $user2 = User::factory()->create([
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
        'status' => 'inactive',
    ]);
    $user2->assignRole('Viewer');

    $user3 = User::factory()->create([
        'name' => 'Bob Johnson',
        'email' => 'bob@example.com',
        'status' => 'active',
    ]);
    $user3->assignRole('Operator');

    // Search "Smith" + filter status "active" should return only John
    $response = $this->actingAs($admin)->get('/users?search=Smith&status=active');
    $response->assertInertia(fn (Assert $page) => $page
        ->has('users.data', 1)
        ->where('users.data.0.name', 'John Smith')
    );

    // Search "Smith" + filter status "inactive" should return only Jane
    $response = $this->actingAs($admin)->get('/users?search=Smith&status=inactive');
    $response->assertInertia(fn (Assert $page) => $page
        ->has('users.data', 1)
        ->where('users.data.0.name', 'Jane Smith')
    );

    // Search "example.com" + filter role "Operator" should return only Bob
    $response = $this->actingAs($admin)->get('/users?search=bob&role=Operator');
    $response->assertInertia(fn (Assert $page) => $page
        ->has('users.data', 1)
        ->where('users.data.0.name', 'Bob Johnson')
    );
});

/**
 * Test 4: Password update is optional on user edit
 */
test('password update is optional on user edit', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $targetUser = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
    ]);
    $targetUser->assignRole('Viewer');
    $originalPasswordHash = $targetUser->password;

    // Update without password (should keep original)
    $response = $this->actingAs($admin)
        ->put("/users/{$targetUser->id}", [
            'name' => 'Updated Name',
            'email' => 'original@example.com',
            'password' => null,
            'role' => 'Viewer',
            'status' => 'active',
            'datacenter_ids' => [],
        ]);

    $response->assertRedirect('/users');

    $targetUser->refresh();
    expect($targetUser->name)->toBe('Updated Name');
    expect($targetUser->password)->toBe($originalPasswordHash);

    // Update with new password (should change)
    $response = $this->actingAs($admin)
        ->put("/users/{$targetUser->id}", [
            'name' => 'Updated Name',
            'email' => 'original@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'role' => 'Viewer',
            'status' => 'active',
            'datacenter_ids' => [],
        ]);

    $response->assertRedirect('/users');

    $targetUser->refresh();
    expect($targetUser->password)->not->toBe($originalPasswordHash);
});

/**
 * Test 5: Legacy /users/roles redirects to /users
 */
test('legacy users roles route redirects to users index', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $response = $this->actingAs($admin)->get('/users/roles');

    $response->assertRedirect(route('users.index'));
});

/**
 * Test 6: Full user creation flow with role and datacenter assignment
 */
test('full user creation flow assigns role and returns to list', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create user with IT Manager role
    $response = $this->actingAs($admin)
        ->post('/users', [
            'name' => 'New IT Manager',
            'email' => 'itmanager@example.com',
            'password' => 'securepass123',
            'password_confirmation' => 'securepass123',
            'role' => 'IT Manager',
            'status' => 'active',
            'datacenter_ids' => [],
        ]);

    $response->assertRedirect('/users');
    $response->assertSessionHas('success');

    // Verify user was created with correct role
    $newUser = User::where('email', 'itmanager@example.com')->first();
    expect($newUser)->not->toBeNull();
    expect($newUser->hasRole('IT Manager'))->toBeTrue();
    expect($newUser->status)->toBe('active');

    // Verify user appears in the list by checking count increased
    $listResponse = $this->actingAs($admin)->get('/users');
    $listResponse->assertInertia(fn (Assert $page) => $page
        ->has('users.data', 2) // admin + new user
    );

    // Verify specific user is in database
    $this->assertDatabaseHas('users', [
        'email' => 'itmanager@example.com',
        'name' => 'New IT Manager',
    ]);
});

/**
 * Test 7: Bulk status change rejects non-existent user IDs
 */
test('bulk status change rejects non-existent user IDs', function () {
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('Administrator');

    $user1 = User::factory()->create(['status' => 'active']);
    $user1->assignRole('Viewer');

    // Include a non-existent user ID (999999) - should fail validation
    $response = $this->actingAs($admin)
        ->postJson('/users/bulk-status', [
            'user_ids' => [$user1->id, 999999],
            'status' => 'suspended',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['user_ids.1']);

    // Verify user1 was NOT updated since validation failed
    $user1->refresh();
    expect($user1->status)->toBe('active');
});

/**
 * Test 8: Password confirmation validation works on user creation
 */
test('password confirmation validation works on user creation', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Test password mismatch
    $response = $this->actingAs($admin)
        ->post('/users', [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'differentpassword',
            'role' => 'Viewer',
            'status' => 'active',
            'datacenter_ids' => [],
        ]);

    $response->assertSessionHasErrors(['password']);

    // Verify user was NOT created
    expect(User::where('email', 'testuser@example.com')->exists())->toBeFalse();

    // Test password too short
    $response = $this->actingAs($admin)
        ->post('/users', [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'role' => 'Viewer',
            'status' => 'active',
            'datacenter_ids' => [],
        ]);

    $response->assertSessionHasErrors(['password']);
});

/**
 * Test 9: User with empty datacenter assignment works correctly
 */
test('user creation and update works with empty datacenter list', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create user without any datacenters
    $response = $this->actingAs($admin)
        ->post('/users', [
            'name' => 'No Datacenter User',
            'email' => 'nodatacenter@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'Viewer',
            'status' => 'active',
            'datacenter_ids' => [],
        ]);

    $response->assertRedirect('/users');

    $user = User::where('email', 'nodatacenter@example.com')->first();
    expect($user->datacenters)->toBeEmpty();

    // Edit page should render correctly
    $editResponse = $this->actingAs($admin)->get("/users/{$user->id}/edit");
    $editResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('user.datacenter_ids', [])
        );
});

/**
 * Test 10: Pagination works correctly with large user sets
 */
test('pagination returns correct number of users per page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create 20 additional users (21 total including admin)
    User::factory()->count(20)->create(['status' => 'active'])
        ->each(fn ($user) => $user->assignRole('Viewer'));

    // First page should have 15 users (default pagination)
    $response = $this->actingAs($admin)->get('/users');
    $response->assertInertia(fn (Assert $page) => $page
        ->has('users.data', 15)
        ->has('users.links')
    );

    // Second page should have remaining users
    $response = $this->actingAs($admin)->get('/users?page=2');
    $response->assertInertia(fn (Assert $page) => $page
        ->has('users.data', 6)
    );
});
