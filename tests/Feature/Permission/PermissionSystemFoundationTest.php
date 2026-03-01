<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('User model uses HasRoles trait', function () {
    $user = User::factory()->create();

    expect(class_uses_recursive($user))
        ->toContain('Spatie\Permission\Traits\HasRoles');
});

test('roles can be created and assigned to users', function () {
    $role = Role::create(['name' => 'test-role']);
    $user = User::factory()->create();

    $user->assignRole('test-role');

    expect($user->hasRole('test-role'))->toBeTrue();
    expect($user->roles->pluck('name')->toArray())->toContain('test-role');
});

test('permissions can be assigned to roles', function () {
    $role = Role::create(['name' => 'test-role']);
    $permission = Permission::create(['name' => 'test-permission']);

    $role->givePermissionTo('test-permission');

    expect($role->hasPermissionTo('test-permission'))->toBeTrue();
    expect($role->permissions->pluck('name')->toArray())->toContain('test-permission');
});

test('user permission checks work correctly', function () {
    $role = Role::create(['name' => 'test-role']);
    $permission = Permission::create(['name' => 'test-permission']);
    $otherPermission = Permission::create(['name' => 'other-permission']);
    $role->givePermissionTo($permission);

    $user = User::factory()->create();
    $user->assignRole('test-role');

    expect($user->hasPermissionTo('test-permission'))->toBeTrue();
    expect($user->can('test-permission'))->toBeTrue();
    // User should not have a permission that was not assigned to their role
    expect($user->hasPermissionTo('other-permission'))->toBeFalse();
});
