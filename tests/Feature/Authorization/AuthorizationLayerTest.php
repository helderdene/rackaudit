<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('Administrator can access all routes', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Test access to users index route (Administrator only)
    $this->actingAs($admin)
        ->get('/users')
        ->assertSuccessful();

    // Test that legacy /users/roles redirects to /users
    $this->actingAs($admin)
        ->get('/users/roles')
        ->assertRedirect(route('users.index'));

    // Test access to role assignment route
    $targetUser = User::factory()->create();
    $this->actingAs($admin)
        ->putJson("/users/{$targetUser->id}/role", ['role' => 'Viewer'])
        ->assertSuccessful();
});

test('unauthorized role returns 403 Forbidden', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    // Viewer should not be able to access user management routes
    $this->actingAs($viewer)
        ->getJson('/users/roles')
        ->assertForbidden();
});

test('permission middleware blocks access correctly', function () {
    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    // Operator should not be able to access user management routes
    $this->actingAs($operator)
        ->getJson('/users/roles')
        ->assertForbidden();

    // Operator should not be able to assign roles
    $targetUser = User::factory()->create();
    $this->actingAs($operator)
        ->putJson("/users/{$targetUser->id}/role", ['role' => 'Viewer'])
        ->assertForbidden();
});

test('Viewer cannot perform create/update/delete actions', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    // Viewer cannot update a user's role
    $targetUser = User::factory()->create();
    $this->actingAs($viewer)
        ->putJson("/users/{$targetUser->id}/role", ['role' => 'Operator'])
        ->assertForbidden();

    // Check that Viewer does not have the permission
    expect($viewer->hasPermissionTo('users.update'))->toBeFalse();
    expect($viewer->hasPermissionTo('users.create'))->toBeFalse();
    expect($viewer->hasPermissionTo('users.delete'))->toBeFalse();
});

test('unauthorized access redirects to dashboard with flash message', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    // Attempt to access a protected route (non-JSON to trigger the flash behavior)
    $this->actingAs($viewer)
        ->get('/users/roles');

    // Check flash session contains error message
    expect(session('error'))->not->toBeNull();
    expect(session('error'))->toContain('You do not have permission');
});

test('unauthorized access attempts are logged', function () {
    Log::spy();

    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    // Attempt to access a protected route
    $this->actingAs($viewer)
        ->getJson('/users/roles');

    // Verify logging occurred
    Log::shouldHaveReceived('warning')
        ->withArgs(function ($message, $context) use ($viewer) {
            return str_contains($message, 'Unauthorized access attempt') &&
                   isset($context['user_id']) &&
                   $context['user_id'] === $viewer->id &&
                   isset($context['route']) &&
                   isset($context['timestamp']);
        });
});
