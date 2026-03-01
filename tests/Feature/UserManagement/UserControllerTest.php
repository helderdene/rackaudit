<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();

    // Disable Inertia page existence check since Vue components are created in Task Group 4
    config(['inertia.testing.ensure_pages_exist' => false]);
});

/**
 * Test 1: User index displays paginated list with search and filters
 */
test('user index displays paginated list with search and filters', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create users with different statuses and roles
    $activeUser = User::factory()->create(['name' => 'John Active', 'status' => 'active']);
    $activeUser->assignRole('Viewer');

    $inactiveUser = User::factory()->create(['name' => 'Jane Inactive', 'status' => 'inactive']);
    $inactiveUser->assignRole('Operator');

    $suspendedUser = User::factory()->create(['name' => 'Bob Suspended', 'status' => 'suspended']);
    $suspendedUser->assignRole('Auditor');

    // Test basic index
    $response = $this->actingAs($admin)->get('/users');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->has('users.data', 4) // admin + 3 users
            ->has('availableRoles')
        );

    // Test search by name
    $response = $this->actingAs($admin)->get('/users?search=John');
    $response->assertInertia(fn (Assert $page) => $page
        ->has('users.data', 1)
        ->where('users.data.0.name', 'John Active')
    );

    // Test filter by status
    $response = $this->actingAs($admin)->get('/users?status=inactive');
    $response->assertInertia(fn (Assert $page) => $page
        ->has('users.data', 1)
        ->where('users.data.0.status', 'inactive')
    );

    // Test filter by role
    $response = $this->actingAs($admin)->get('/users?role=Auditor');
    $response->assertInertia(fn (Assert $page) => $page
        ->has('users.data', 1)
        ->where('users.data.0.name', 'Bob Suspended')
    );
});

/**
 * Test 2: User creation with validation
 */
test('user creation validates required fields and creates user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Test validation errors
    $response = $this->actingAs($admin)
        ->post('/users', []);

    $response->assertSessionHasErrors(['name', 'email', 'password', 'role', 'status']);

    // Test successful creation
    $response = $this->actingAs($admin)
        ->post('/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'Viewer',
            'status' => 'active',
            'datacenter_ids' => [],
        ]);

    $response->assertRedirect('/users');

    $this->assertDatabaseHas('users', [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'status' => 'active',
    ]);

    $newUser = User::where('email', 'newuser@example.com')->first();
    expect($newUser->hasRole('Viewer'))->toBeTrue();
});

/**
 * Test 3: User update works correctly
 */
test('user update validates and updates user correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $targetUser = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
        'status' => 'active',
    ]);
    $targetUser->assignRole('Viewer');

    // Test successful update
    $response = $this->actingAs($admin)
        ->put("/users/{$targetUser->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'password' => null,
            'role' => 'Operator',
            'status' => 'inactive',
            'datacenter_ids' => [],
        ]);

    $response->assertRedirect('/users');

    $targetUser->refresh();
    expect($targetUser->name)->toBe('Updated Name');
    expect($targetUser->email)->toBe('updated@example.com');
    expect($targetUser->status)->toBe('inactive');
    expect($targetUser->hasRole('Operator'))->toBeTrue();
    expect($targetUser->hasRole('Viewer'))->toBeFalse();
});

/**
 * Test 4: Administrator cannot demote themselves
 */
test('administrator cannot demote themselves or change their own status to non-active', function () {
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('Administrator');

    // Try to demote self from Administrator
    $response = $this->actingAs($admin)
        ->put("/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'password' => null,
            'role' => 'Viewer', // Trying to demote
            'status' => 'active',
            'datacenter_ids' => [],
        ]);

    $response->assertForbidden();

    // Verify role was not changed
    $admin->refresh();
    expect($admin->hasRole('Administrator'))->toBeTrue();

    // Try to deactivate self
    $response = $this->actingAs($admin)
        ->put("/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'password' => null,
            'role' => 'Administrator',
            'status' => 'inactive', // Trying to deactivate
            'datacenter_ids' => [],
        ]);

    $response->assertForbidden();

    // Verify status was not changed
    $admin->refresh();
    expect($admin->status)->toBe('active');
});

/**
 * Test 5: Administrator cannot delete themselves
 */
test('administrator cannot delete themselves', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $response = $this->actingAs($admin)
        ->deleteJson("/users/{$admin->id}");

    $response->assertForbidden()
        ->assertJson(['message' => 'You cannot delete your own account.']);

    // Verify user was not deleted
    expect(User::find($admin->id))->not->toBeNull();
});

/**
 * Test 6: User deletion soft deletes the user
 */
test('user deletion soft deletes the user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $targetUser = User::factory()->create();
    $targetUser->assignRole('Viewer');
    $userId = $targetUser->id;

    $response = $this->actingAs($admin)
        ->delete("/users/{$userId}");

    $response->assertRedirect('/users');

    // User should be soft deleted
    expect(User::find($userId))->toBeNull();
    expect(User::withTrashed()->find($userId))->not->toBeNull();
    expect(User::withTrashed()->find($userId)->trashed())->toBeTrue();
});

/**
 * Test 7: Bulk status change works for multiple users
 */
test('bulk status change updates multiple users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $user1 = User::factory()->create(['status' => 'active']);
    $user1->assignRole('Viewer');

    $user2 = User::factory()->create(['status' => 'active']);
    $user2->assignRole('Operator');

    $user3 = User::factory()->create(['status' => 'active']);
    $user3->assignRole('Auditor');

    $response = $this->actingAs($admin)
        ->postJson('/users/bulk-status', [
            'user_ids' => [$user1->id, $user2->id, $user3->id],
            'status' => 'suspended',
        ]);

    $response->assertSuccessful();

    // Verify all users are suspended
    $user1->refresh();
    $user2->refresh();
    $user3->refresh();

    expect($user1->status)->toBe('suspended');
    expect($user2->status)->toBe('suspended');
    expect($user3->status)->toBe('suspended');
});

/**
 * Test 8: Bulk status change prevents including current user
 */
test('bulk status change prevents including current administrator', function () {
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('Administrator');

    $user1 = User::factory()->create(['status' => 'active']);
    $user1->assignRole('Viewer');

    // Try to include self in bulk status change
    $response = $this->actingAs($admin)
        ->postJson('/users/bulk-status', [
            'user_ids' => [$admin->id, $user1->id],
            'status' => 'suspended',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['user_ids']);

    // Verify admin status was not changed
    $admin->refresh();
    expect($admin->status)->toBe('active');
});
