<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Disable Inertia page existence check during test file creation
    // Once Vue components are created, this can be removed
    config(['inertia.testing.ensure_pages_exist' => false]);
});

/**
 * Test 1: User list page renders with correct data structure
 */
test('user list page renders with data and filter props', function () {
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('Administrator');

    // Create users with different statuses for list display
    $activeUser = User::factory()->create([
        'name' => 'Active User',
        'email' => 'active@example.com',
        'status' => 'active',
        'last_active_at' => now()->subHours(2),
    ]);
    $activeUser->assignRole('Viewer');

    $inactiveUser = User::factory()->create([
        'name' => 'Inactive User',
        'status' => 'inactive',
        'last_active_at' => null,
    ]);
    $inactiveUser->assignRole('Operator');

    $response = $this->actingAs($admin)->get('/users');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->has('users.data', 3)
            ->has('users.links')
            ->has('availableRoles')
            ->has('filters', fn (Assert $filters) => $filters
                ->has('search')
                ->has('status')
                ->has('role')
            )
            ->where('users.data.0.id', fn ($id) => is_int($id))
            ->where('users.data.0.name', fn ($name) => is_string($name))
            ->where('users.data.0.email', fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->where('users.data.0.status', fn ($status) => in_array($status, ['active', 'inactive', 'suspended']))
            ->where('users.data.0.role', fn ($role) => is_string($role))
        );
});

/**
 * Test 2: User creation form page renders with available options
 */
test('user creation form page renders with roles and datacenters', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $response = $this->actingAs($admin)->get('/users/create');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Create')
            ->has('availableRoles', 5) // Administrator, IT Manager, Operator, Auditor, Viewer
            ->has('datacenters')
            ->where('availableRoles.0', 'Administrator')
            ->where('availableRoles.4', 'Viewer')
        );
});

/**
 * Test 3: User edit page renders with pre-populated user values
 */
test('user edit page renders with pre-populated user values', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $targetUser = User::factory()->create([
        'name' => 'Test User',
        'email' => 'testuser@example.com',
        'status' => 'active',
    ]);
    $targetUser->assignRole('Operator');

    $response = $this->actingAs($admin)->get("/users/{$targetUser->id}/edit");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Edit')
            ->has('user', fn (Assert $user) => $user
                ->where('id', $targetUser->id)
                ->where('name', 'Test User')
                ->where('email', 'testuser@example.com')
                ->where('role', 'Operator')
                ->where('status', 'active')
                ->has('datacenter_ids')
            )
            ->has('availableRoles', 5)
            ->has('datacenters')
        );
});

/**
 * Test 4: User creation form submission creates user and redirects
 */
test('user creation form submission creates user and redirects to index', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $response = $this->actingAs($admin)
        ->post('/users', [
            'name' => 'New Form User',
            'email' => 'newformuser@example.com',
            'password' => 'securepassword',
            'password_confirmation' => 'securepassword',
            'role' => 'Auditor',
            'status' => 'active',
            'datacenter_ids' => [],
        ]);

    $response->assertRedirect('/users');

    $this->assertDatabaseHas('users', [
        'name' => 'New Form User',
        'email' => 'newformuser@example.com',
        'status' => 'active',
    ]);

    $newUser = User::where('email', 'newformuser@example.com')->first();
    expect($newUser)->not->toBeNull();
    expect($newUser->hasRole('Auditor'))->toBeTrue();
});

/**
 * Test 5: Delete user endpoint works with proper confirmation flow
 */
test('delete user endpoint removes user and redirects', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $targetUser = User::factory()->create([
        'name' => 'User To Delete',
        'email' => 'todelete@example.com',
    ]);
    $targetUser->assignRole('Viewer');
    $userId = $targetUser->id;

    $response = $this->actingAs($admin)
        ->delete("/users/{$userId}");

    $response->assertRedirect('/users');

    // User should be soft deleted
    expect(User::find($userId))->toBeNull();
    expect(User::withTrashed()->find($userId))->not->toBeNull();
});

/**
 * Test 6: Bulk status change works through API endpoint
 */
test('bulk status change endpoint updates selected users', function () {
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('Administrator');

    $users = User::factory()->count(3)->create(['status' => 'active']);
    foreach ($users as $user) {
        $user->assignRole('Viewer');
    }

    $userIds = $users->pluck('id')->toArray();

    $response = $this->actingAs($admin)
        ->postJson('/users/bulk-status', [
            'user_ids' => $userIds,
            'status' => 'inactive',
        ]);

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'User statuses updated successfully.',
            'updated_count' => 3,
        ]);

    foreach ($users as $user) {
        $user->refresh();
        expect($user->status)->toBe('inactive');
    }
});
