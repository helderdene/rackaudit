<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Run the seeder before each test
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('all five roles are created by seeder', function () {
    $expectedRoles = ['Administrator', 'IT Manager', 'Operator', 'Auditor', 'Viewer'];

    foreach ($expectedRoles as $roleName) {
        expect(Role::where('name', $roleName)->exists())->toBeTrue(
            "Role '{$roleName}' should exist in the database"
        );
    }

    // Verify exactly 5 roles were created
    expect(Role::count())->toBe(5);
});

test('Administrator role has all permissions', function () {
    $adminRole = Role::findByName('Administrator');
    $allPermissions = Permission::all();

    // Administrator should have all permissions in the system
    expect($adminRole->permissions->count())->toBe($allPermissions->count());

    foreach ($allPermissions as $permission) {
        expect($adminRole->hasPermissionTo($permission->name))->toBeTrue(
            "Administrator should have permission '{$permission->name}'"
        );
    }
});

test('IT Manager has correct permission subset', function () {
    $itManagerRole = Role::findByName('IT Manager');

    // Should have infrastructure management (full CRUD)
    $infrastructureResources = ['datacenters', 'racks', 'devices', 'connections', 'ports'];
    foreach ($infrastructureResources as $resource) {
        foreach (['view', 'create', 'update', 'delete'] as $action) {
            expect($itManagerRole->hasPermissionTo("{$resource}.{$action}"))->toBeTrue(
                "IT Manager should have permission '{$resource}.{$action}'"
            );
        }
    }

    // Should have implementation-files with approve
    foreach (['view', 'create', 'update', 'delete', 'approve'] as $action) {
        expect($itManagerRole->hasPermissionTo("implementation-files.{$action}"))->toBeTrue(
            "IT Manager should have permission 'implementation-files.{$action}'"
        );
    }

    // Should have reports.view only
    expect($itManagerRole->hasPermissionTo('reports.view'))->toBeTrue();
    expect($itManagerRole->hasPermissionTo('reports.create'))->toBeFalse();
    expect($itManagerRole->hasPermissionTo('reports.update'))->toBeFalse();
    expect($itManagerRole->hasPermissionTo('reports.delete'))->toBeFalse();

    // Should NOT have users.* or settings.* permissions
    expect($itManagerRole->hasPermissionTo('users.view'))->toBeFalse();
    expect($itManagerRole->hasPermissionTo('settings.view'))->toBeFalse();
});

test('Operator has correct permission subset', function () {
    $operatorRole = Role::findByName('Operator');

    // Should have devices, connections, ports (full CRUD)
    $operatorCrudResources = ['devices', 'connections', 'ports'];
    foreach ($operatorCrudResources as $resource) {
        foreach (['view', 'create', 'update', 'delete'] as $action) {
            expect($operatorRole->hasPermissionTo("{$resource}.{$action}"))->toBeTrue(
                "Operator should have permission '{$resource}.{$action}'"
            );
        }
    }

    // Should have audits.view and audits.execute (but not create, update, delete)
    expect($operatorRole->hasPermissionTo('audits.view'))->toBeTrue();
    expect($operatorRole->hasPermissionTo('audits.execute'))->toBeTrue();
    expect($operatorRole->hasPermissionTo('audits.create'))->toBeFalse();
    expect($operatorRole->hasPermissionTo('audits.update'))->toBeFalse();
    expect($operatorRole->hasPermissionTo('audits.delete'))->toBeFalse();

    // Should have datacenters.view and racks.view only
    expect($operatorRole->hasPermissionTo('datacenters.view'))->toBeTrue();
    expect($operatorRole->hasPermissionTo('datacenters.create'))->toBeFalse();
    expect($operatorRole->hasPermissionTo('racks.view'))->toBeTrue();
    expect($operatorRole->hasPermissionTo('racks.create'))->toBeFalse();

    // Should NOT have implementation-files.approve, reports.*, users.*, settings.*
    expect($operatorRole->hasPermissionTo('implementation-files.approve'))->toBeFalse();
    expect($operatorRole->hasPermissionTo('reports.view'))->toBeFalse();
    expect($operatorRole->hasPermissionTo('users.view'))->toBeFalse();
    expect($operatorRole->hasPermissionTo('settings.view'))->toBeFalse();
});

test('Auditor has correct permission subset', function () {
    $auditorRole = Role::findByName('Auditor');

    // Should have audits (CRUD + execute)
    foreach (['view', 'create', 'update', 'delete', 'execute'] as $action) {
        expect($auditorRole->hasPermissionTo("audits.{$action}"))->toBeTrue(
            "Auditor should have permission 'audits.{$action}'"
        );
    }

    // Should have findings (CRUD + resolve)
    foreach (['view', 'create', 'update', 'delete', 'resolve'] as $action) {
        expect($auditorRole->hasPermissionTo("findings.{$action}"))->toBeTrue(
            "Auditor should have permission 'findings.{$action}'"
        );
    }

    // Should have reports.view and reports.create
    expect($auditorRole->hasPermissionTo('reports.view'))->toBeTrue();
    expect($auditorRole->hasPermissionTo('reports.create'))->toBeTrue();
    expect($auditorRole->hasPermissionTo('reports.update'))->toBeFalse();
    expect($auditorRole->hasPermissionTo('reports.delete'))->toBeFalse();

    // Should have view-only for infrastructure
    $infrastructureResources = ['datacenters', 'racks', 'devices', 'connections', 'ports'];
    foreach ($infrastructureResources as $resource) {
        expect($auditorRole->hasPermissionTo("{$resource}.view"))->toBeTrue(
            "Auditor should have permission '{$resource}.view'"
        );
        expect($auditorRole->hasPermissionTo("{$resource}.create"))->toBeFalse(
            "Auditor should NOT have permission '{$resource}.create'"
        );
    }

    // Should NOT have users.* or settings.* permissions
    expect($auditorRole->hasPermissionTo('users.view'))->toBeFalse();
    expect($auditorRole->hasPermissionTo('settings.view'))->toBeFalse();
});

test('Viewer has only view permissions', function () {
    $viewerRole = Role::findByName('Viewer');

    // All view permissions for main resources (excluding users and settings)
    $viewableResources = [
        'datacenters',
        'racks',
        'devices',
        'connections',
        'ports',
        'audits',
        'findings',
        'implementation-files',
        'reports',
    ];

    foreach ($viewableResources as $resource) {
        expect($viewerRole->hasPermissionTo("{$resource}.view"))->toBeTrue(
            "Viewer should have permission '{$resource}.view'"
        );
    }

    // Should NOT have any create, update, delete, execute, resolve, or approve permissions
    $nonViewActions = ['create', 'update', 'delete', 'execute', 'resolve', 'approve'];
    foreach ($viewerRole->permissions as $permission) {
        $parts = explode('.', $permission->name);
        $action = end($parts);
        expect(in_array($action, $nonViewActions))->toBeFalse(
            "Viewer should NOT have permission '{$permission->name}'"
        );
    }

    // Should NOT have users.* or settings.* permissions
    expect($viewerRole->hasPermissionTo('users.view'))->toBeFalse();
    expect($viewerRole->hasPermissionTo('settings.view'))->toBeFalse();
});
