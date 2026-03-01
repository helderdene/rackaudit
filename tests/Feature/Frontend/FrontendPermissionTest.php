<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    // Mock Vite to avoid manifest issues during testing
    $this->withoutVite();
});

test('user role is shared via Inertia props', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrator');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('auth.user.role')
            ->where('auth.user.role', 'Administrator')
        );
});

test('permissions array is shared via Inertia props', function () {
    $user = User::factory()->create();
    $user->assignRole('Viewer');

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('auth.permissions')
    );

    // Get the props from the response and check permissions manually
    $page = $response->viewData('page');
    $permissions = $page['props']['auth']['permissions'];

    // Convert to array if it's a collection
    if ($permissions instanceof \Illuminate\Support\Collection) {
        $permissions = $permissions->toArray();
    }

    expect($permissions)->toBeArray();
    expect($permissions)->toContain('datacenters.view');
    expect($permissions)->not->toContain('datacenters.create'); // Viewer should not have create permission
});

test('UserInfo component displays role badge via Inertia props', function () {
    $user = User::factory()->create();
    $user->assignRole('IT Manager');

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    // Verify the Inertia response includes role data that the UserInfo component will use
    $response->assertInertia(fn (Assert $page) => $page
        ->has('auth.user.role')
        ->where('auth.user.role', 'IT Manager')
    );
});

test('navigation items are conditionally rendered based on permissions', function () {
    // Test that Viewer can access dashboard (which has navigation)
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    $response = $this->actingAs($viewer)
        ->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('auth.permissions')
    );

    // Get the props from the response and check permissions manually
    $page = $response->viewData('page');
    $viewerPermissions = $page['props']['auth']['permissions'];
    if ($viewerPermissions instanceof \Illuminate\Support\Collection) {
        $viewerPermissions = $viewerPermissions->toArray();
    }

    // Verify the permissions are shared so frontend can conditionally render nav items
    expect($viewerPermissions)->not->toContain('settings.view');
    expect($viewerPermissions)->not->toContain('users.view');

    // Test that Administrator has the required permissions for nav items
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $adminResponse = $this->actingAs($admin)
        ->get(route('dashboard'));

    $adminPage = $adminResponse->viewData('page');
    $adminPermissions = $adminPage['props']['auth']['permissions'];
    if ($adminPermissions instanceof \Illuminate\Support\Collection) {
        $adminPermissions = $adminPermissions->toArray();
    }

    expect($adminPermissions)->toContain('settings.view');
    expect($adminPermissions)->toContain('users.view');
});

test('action buttons are hidden when user lacks permission', function () {
    // Test that permissions array correctly indicates what actions are allowed
    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    $response = $this->actingAs($operator)
        ->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('auth.permissions')
    );

    // Get the props from the response and check permissions manually
    $page = $response->viewData('page');
    $permissions = $page['props']['auth']['permissions'];
    if ($permissions instanceof \Illuminate\Support\Collection) {
        $permissions = $permissions->toArray();
    }

    // Operator can create/update/delete devices
    expect($permissions)->toContain('devices.create');
    expect($permissions)->toContain('devices.update');
    expect($permissions)->toContain('devices.delete');
    // But cannot create/update/delete datacenters
    expect($permissions)->not->toContain('datacenters.create');
    expect($permissions)->not->toContain('datacenters.update');
    expect($permissions)->not->toContain('datacenters.delete');
});

test('role assignment page renders correctly for Administrators', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    // Create some test users with different roles
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    $operator = User::factory()->create();
    $operator->assignRole('Operator');

    // Legacy users/roles route now redirects to users index
    $this->actingAs($admin)
        ->get(route('users.roles.index'))
        ->assertRedirect(route('users.index'));

    // Users index page has role management functionality
    $response = $this->actingAs($admin)
        ->get(route('users.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->has('users.data')
            ->has('availableRoles')
        );
});
