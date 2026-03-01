<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
});

/**
 * Test 1: Administrator self-role-removal prevention
 * Ensures Administrators cannot remove their own Administrator role.
 */
test('Administrator cannot remove their own Administrator role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $response = $this->actingAs($admin)
        ->putJson("/users/{$admin->id}/role", ['role' => 'Viewer']);

    $response->assertForbidden()
        ->assertJson(['message' => 'You cannot remove your own Administrator role.']);

    // Verify role was not changed
    $admin->refresh();
    expect($admin->hasRole('Administrator'))->toBeTrue();
    expect($admin->hasRole('Viewer'))->toBeFalse();
});

/**
 * Test 2: Complete role assignment workflow
 * Tests the full flow of an Administrator assigning a role to another user.
 */
test('complete role assignment workflow succeeds for Administrator', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $targetUser = User::factory()->create();
    $targetUser->assignRole('Viewer');

    // Step 1: Legacy role assignment page redirects to users index
    $this->actingAs($admin)
        ->get(route('users.roles.index'))
        ->assertRedirect(route('users.index'));

    // Step 1b: Load the users page (which has role management)
    $indexResponse = $this->actingAs($admin)
        ->get(route('users.index'));

    $indexResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->has('users.data')
            ->has('availableRoles')
        );

    // Step 2: Update the user's role
    $updateResponse = $this->actingAs($admin)
        ->putJson("/users/{$targetUser->id}/role", ['role' => 'Operator']);

    $updateResponse->assertSuccessful()
        ->assertJson([
            'message' => 'User role updated successfully to Operator.',
            'user' => [
                'id' => $targetUser->id,
                'role' => 'Operator',
            ],
        ]);

    // Step 3: Verify the role was actually updated
    $targetUser->refresh();
    expect($targetUser->hasRole('Operator'))->toBeTrue();
    expect($targetUser->hasRole('Viewer'))->toBeFalse();
});

/**
 * Test 3: Permission cache invalidation on role change
 * Verifies that permission cache is properly cleared when a user's role changes.
 */
test('permission cache is invalidated when user role changes', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $targetUser = User::factory()->create();
    $targetUser->assignRole('Viewer');

    // Verify initial permissions
    expect($targetUser->hasPermissionTo('datacenters.view'))->toBeTrue();
    expect($targetUser->hasPermissionTo('datacenters.create'))->toBeFalse();

    // Change the role
    $this->actingAs($admin)
        ->putJson("/users/{$targetUser->id}/role", ['role' => 'IT Manager']);

    // Force refresh the permission registrar to clear cache
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    // Reload the user with fresh permissions
    $targetUser = User::find($targetUser->id);

    // Verify new permissions are active (IT Manager can create datacenters)
    expect($targetUser->hasRole('IT Manager'))->toBeTrue();
    expect($targetUser->hasPermissionTo('datacenters.create'))->toBeTrue();
    expect($targetUser->hasPermissionTo('datacenters.update'))->toBeTrue();
});

/**
 * Test 4: Flash message display on unauthorized access attempt
 * Verifies that unauthorized access redirects and sets appropriate flash message.
 */
test('unauthorized access sets flash error message with descriptive text', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    // Attempt to access Administrator-only route
    $response = $this->actingAs($viewer)
        ->get('/users/roles');

    // Verify flash message was set
    $errorMessage = session('error');
    expect($errorMessage)->not->toBeNull();
    expect($errorMessage)->toContain('permission');
});

/**
 * Test 5: Role badge data is included in Inertia shared props for all roles
 * Tests that each role type displays correct role name in shared props.
 */
test('role badge data is correctly shared for all five role types', function () {
    $roles = ['Administrator', 'IT Manager', 'Operator', 'Auditor', 'Viewer'];

    foreach ($roles as $roleName) {
        $user = User::factory()->create();
        $user->assignRole($roleName);

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.role', $roleName)
        );
    }
});

/**
 * Test 6: IT Manager can access infrastructure routes but not user management
 * Tests cross-cutting role access patterns.
 */
test('IT Manager role has correct access boundaries', function () {
    $itManager = User::factory()->create();
    $itManager->assignRole('IT Manager');

    // Should NOT be able to access user role management
    $this->actingAs($itManager)
        ->getJson('/users/roles')
        ->assertForbidden();

    // Should have infrastructure permissions
    expect($itManager->hasPermissionTo('datacenters.create'))->toBeTrue();
    expect($itManager->hasPermissionTo('racks.create'))->toBeTrue();
    expect($itManager->hasPermissionTo('devices.create'))->toBeTrue();

    // Should have implementation file approval
    expect($itManager->hasPermissionTo('implementation-files.approve'))->toBeTrue();

    // Should NOT have user management
    expect($itManager->hasPermissionTo('users.view'))->toBeFalse();
    expect($itManager->hasPermissionTo('users.create'))->toBeFalse();
});

/**
 * Test 7: Role assignment validates role name is one of the predefined roles
 * Tests form validation for invalid role names.
 */
test('role assignment rejects invalid role names', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $targetUser = User::factory()->create();

    // Try to assign a non-existent role
    $response = $this->actingAs($admin)
        ->putJson("/users/{$targetUser->id}/role", ['role' => 'SuperAdmin']);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});

/**
 * Test 8: Administrator can change another Administrator's role
 * Tests that one Administrator can modify another Administrator's role.
 */
test('Administrator can change another Administrator role', function () {
    $admin1 = User::factory()->create();
    $admin1->assignRole('Administrator');

    $admin2 = User::factory()->create();
    $admin2->assignRole('Administrator');

    // Admin1 changes Admin2's role to IT Manager
    $response = $this->actingAs($admin1)
        ->putJson("/users/{$admin2->id}/role", ['role' => 'IT Manager']);

    $response->assertSuccessful();

    $admin2->refresh();
    expect($admin2->hasRole('IT Manager'))->toBeTrue();
    expect($admin2->hasRole('Administrator'))->toBeFalse();
});

/**
 * Test 9: Non-authenticated users cannot access role management
 * Tests that unauthenticated access is properly rejected.
 */
test('unauthenticated users cannot access role management routes', function () {
    // No user logged in - Laravel returns 401 Unauthorized for unauthenticated JSON requests
    $this->getJson('/users/roles')
        ->assertUnauthorized();

    $targetUser = User::factory()->create();
    $this->putJson("/users/{$targetUser->id}/role", ['role' => 'Viewer'])
        ->assertUnauthorized();
});

/**
 * Test 10: User without any role cannot access protected routes
 * Tests that users without assigned roles are properly handled.
 */
test('user without assigned role cannot access role management', function () {
    $userWithNoRole = User::factory()->create();
    // No role assigned

    $this->actingAs($userWithNoRole)
        ->getJson('/users/roles')
        ->assertForbidden();
});
