<?php

use App\Events\UserManagement\UserCreated;
use App\Events\UserManagement\UserDeleted;
use App\Events\UserManagement\UserRoleChanged;
use App\Events\UserManagement\UserStatusChanged;
use App\Events\UserManagement\UserUpdated;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Test 1: Inactive users cannot login
 */
test('inactive users cannot login', function () {
    $user = User::factory()->inactive()->withoutTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    // User should not be authenticated
    $this->assertGuest();

    // Session should have the status message for inactive account
    $response->assertSessionHas('status', 'Your account is currently inactive. Please contact an administrator.');
});

/**
 * Test 2: Suspended users cannot login
 */
test('suspended users cannot login', function () {
    $user = User::factory()->suspended()->withoutTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    // User should not be authenticated
    $this->assertGuest();

    // Session should have the status message for suspended account
    $response->assertSessionHas('status', 'Your account has been suspended. Please contact an administrator.');
});

/**
 * Test 3: Last active timestamp is updated on successful login
 */
test('last active timestamp is updated on successful login', function () {
    $user = User::factory()->neverLoggedIn()->withoutTwoFactor()->create([
        'status' => 'active',
    ]);

    expect($user->last_active_at)->toBeNull();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();

    $user->refresh();
    expect($user->last_active_at)->not->toBeNull();
    expect($user->last_active_at->isToday())->toBeTrue();
});

/**
 * Test 4: User management events are dispatched during CRUD operations
 */
test('user management events are dispatched during CRUD operations', function () {
    Event::fake([
        UserCreated::class,
        UserUpdated::class,
        UserDeleted::class,
        UserStatusChanged::class,
        UserRoleChanged::class,
    ]);

    // Disable Inertia page existence check since Vue components are created in Task Group 4
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->withoutVite();

    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('Administrator');

    // Test UserCreated event
    $this->actingAs($admin)->post('/users', [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'Viewer',
        'status' => 'active',
        'datacenter_ids' => [],
    ]);

    Event::assertDispatched(UserCreated::class, function ($event) {
        return $event->user->email === 'newuser@example.com'
            && $event->actor !== null;
    });

    $newUser = User::where('email', 'newuser@example.com')->first();

    // Test UserUpdated event (name change)
    $this->actingAs($admin)->put("/users/{$newUser->id}", [
        'name' => 'Updated Name',
        'email' => 'newuser@example.com',
        'password' => null,
        'role' => 'Viewer',
        'status' => 'active',
        'datacenter_ids' => [],
    ]);

    Event::assertDispatched(UserUpdated::class, function ($event) use ($newUser) {
        return $event->user->id === $newUser->id
            && $event->oldValues['name'] === 'New User'
            && $event->newValues['name'] === 'Updated Name';
    });

    // Test UserStatusChanged event
    $this->actingAs($admin)->put("/users/{$newUser->id}", [
        'name' => 'Updated Name',
        'email' => 'newuser@example.com',
        'password' => null,
        'role' => 'Viewer',
        'status' => 'inactive',
        'datacenter_ids' => [],
    ]);

    Event::assertDispatched(UserStatusChanged::class, function ($event) use ($newUser) {
        return $event->user->id === $newUser->id
            && $event->oldStatus === 'active'
            && $event->newStatus === 'inactive';
    });

    // Test UserRoleChanged event
    $this->actingAs($admin)->put("/users/{$newUser->id}", [
        'name' => 'Updated Name',
        'email' => 'newuser@example.com',
        'password' => null,
        'role' => 'Operator',
        'status' => 'inactive',
        'datacenter_ids' => [],
    ]);

    Event::assertDispatched(UserRoleChanged::class, function ($event) use ($newUser) {
        return $event->user->id === $newUser->id
            && $event->oldRole === 'Viewer'
            && $event->newRole === 'Operator';
    });

    // Test UserDeleted event
    $this->actingAs($admin)->delete("/users/{$newUser->id}");

    Event::assertDispatched(UserDeleted::class, function ($event) use ($newUser) {
        return $event->userData['id'] === $newUser->id
            && $event->actor !== null;
    });
});
