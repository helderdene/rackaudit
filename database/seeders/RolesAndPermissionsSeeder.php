<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * All resources and their available actions.
     *
     * @var array<string, array<string>>
     */
    private array $resourcePermissions = [
        'datacenters' => ['view', 'create', 'update', 'delete'],
        'racks' => ['view', 'create', 'update', 'delete'],
        'devices' => ['view', 'create', 'update', 'delete'],
        'connections' => ['view', 'create', 'update', 'delete'],
        'ports' => ['view', 'create', 'update', 'delete'],
        'audits' => ['view', 'create', 'update', 'delete', 'execute'],
        'findings' => ['view', 'create', 'update', 'delete', 'resolve'],
        'implementation-files' => ['view', 'create', 'update', 'delete', 'approve'],
        'reports' => ['view', 'create', 'update', 'delete'],
        'scheduled-reports' => ['view', 'create', 'update', 'delete'],
        'distribution-lists' => ['view', 'create', 'update', 'delete'],
        'users' => ['view', 'create', 'update', 'delete'],
        'settings' => ['view', 'update'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all permissions
        $this->createPermissions();

        // Create roles with their permissions
        $this->createRoles();

        // Create initial Administrator user if configured
        $this->createAdminUser();
    }

    /**
     * Create all permissions using {resource}.{action} convention.
     */
    private function createPermissions(): void
    {
        foreach ($this->resourcePermissions as $resource => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "{$resource}.{$action}"]);
            }
        }
    }

    /**
     * Create all roles with their respective permissions.
     */
    private function createRoles(): void
    {
        $this->createAdministratorRole();
        $this->createITManagerRole();
        $this->createOperatorRole();
        $this->createAuditorRole();
        $this->createViewerRole();
    }

    /**
     * Administrator role: Full system access including user and settings management.
     */
    private function createAdministratorRole(): void
    {
        $role = Role::firstOrCreate(['name' => 'Administrator']);

        // Administrator gets all permissions
        $role->syncPermissions(Permission::all());
    }

    /**
     * IT Manager role: Infrastructure management, implementation files with approve, reports view.
     * Also has full access to scheduled-reports and distribution-lists.
     * Excludes: users.*, settings.*
     */
    private function createITManagerRole(): void
    {
        $role = Role::firstOrCreate(['name' => 'IT Manager']);

        $permissions = [];

        // Full CRUD for infrastructure resources
        $infrastructureResources = ['datacenters', 'racks', 'devices', 'connections', 'ports'];
        foreach ($infrastructureResources as $resource) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $permissions[] = "{$resource}.{$action}";
            }
        }

        // Implementation files: full CRUD + approve
        foreach (['view', 'create', 'update', 'delete', 'approve'] as $action) {
            $permissions[] = "implementation-files.{$action}";
        }

        // Reports: view only
        $permissions[] = 'reports.view';

        // Scheduled reports: full CRUD
        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $permissions[] = "scheduled-reports.{$action}";
        }

        // Distribution lists: full CRUD
        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $permissions[] = "distribution-lists.{$action}";
        }

        $role->syncPermissions($permissions);
    }

    /**
     * Operator role: Devices, connections, ports CRUD; audits view + execute; datacenter/racks view.
     * Can view and create scheduled-reports and distribution-lists for accessible datacenters.
     * Excludes: implementation-files.approve, reports.*, users.*, settings.*
     */
    private function createOperatorRole(): void
    {
        $role = Role::firstOrCreate(['name' => 'Operator']);

        $permissions = [];

        // Full CRUD for devices, connections, ports
        $operatorCrudResources = ['devices', 'connections', 'ports'];
        foreach ($operatorCrudResources as $resource) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $permissions[] = "{$resource}.{$action}";
            }
        }

        // View only for datacenters and racks
        $permissions[] = 'datacenters.view';
        $permissions[] = 'racks.view';

        // Audits: view and execute only
        $permissions[] = 'audits.view';
        $permissions[] = 'audits.execute';

        // View only for findings (per permission matrix: R for findings)
        $permissions[] = 'findings.view';

        // View only for implementation-files (per permission matrix: R for Operator)
        $permissions[] = 'implementation-files.view';

        // Scheduled reports: view + create (for accessible datacenters)
        $permissions[] = 'scheduled-reports.view';
        $permissions[] = 'scheduled-reports.create';

        // Distribution lists: view + create (for accessible datacenters)
        $permissions[] = 'distribution-lists.view';
        $permissions[] = 'distribution-lists.create';

        $role->syncPermissions($permissions);
    }

    /**
     * Auditor role: Audits CRUD + execute; findings CRUD + resolve; reports view + create; infrastructure view.
     * Can view and create scheduled-reports and distribution-lists for accessible datacenters.
     * Excludes: users.*, settings.*
     */
    private function createAuditorRole(): void
    {
        $role = Role::firstOrCreate(['name' => 'Auditor']);

        $permissions = [];

        // Audits: full CRUD + execute
        foreach (['view', 'create', 'update', 'delete', 'execute'] as $action) {
            $permissions[] = "audits.{$action}";
        }

        // Findings: full CRUD + resolve
        foreach (['view', 'create', 'update', 'delete', 'resolve'] as $action) {
            $permissions[] = "findings.{$action}";
        }

        // Reports: view + create
        $permissions[] = 'reports.view';
        $permissions[] = 'reports.create';

        // View only for infrastructure
        $infrastructureResources = ['datacenters', 'racks', 'devices', 'connections', 'ports'];
        foreach ($infrastructureResources as $resource) {
            $permissions[] = "{$resource}.view";
        }

        // View only for implementation-files (per permission matrix: R for Auditor)
        $permissions[] = 'implementation-files.view';

        // Scheduled reports: view + create (for accessible datacenters)
        $permissions[] = 'scheduled-reports.view';
        $permissions[] = 'scheduled-reports.create';

        // Distribution lists: view + create (for accessible datacenters)
        $permissions[] = 'distribution-lists.view';
        $permissions[] = 'distribution-lists.create';

        $role->syncPermissions($permissions);
    }

    /**
     * Viewer role: View only for all resources except users and settings.
     * Excludes: create, update, delete, execute, resolve, approve actions; users.*, settings.*
     */
    private function createViewerRole(): void
    {
        $role = Role::firstOrCreate(['name' => 'Viewer']);

        $permissions = [];

        // View permissions for all resources except users and settings
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
            'scheduled-reports',
            'distribution-lists',
        ];

        foreach ($viewableResources as $resource) {
            $permissions[] = "{$resource}.view";
        }

        $role->syncPermissions($permissions);
    }

    /**
     * Create initial Administrator user from environment variables.
     * Idempotent: creates user only if not exists.
     */
    private function createAdminUser(): void
    {
        $email = config('auth.admin_email');
        $password = config('auth.admin_password');

        // Skip if no admin credentials configured
        if (empty($email) || empty($password)) {
            return;
        }

        // Check if user already exists
        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => 'Administrator',
                'email' => $email,
                'password' => bcrypt($password),
            ]);
        }

        // Ensure user has Administrator role (idempotent)
        if (! $user->hasRole('Administrator')) {
            $user->assignRole('Administrator');
        }
    }
}
