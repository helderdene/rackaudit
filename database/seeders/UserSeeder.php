<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * The available roles for users.
     *
     * @var array<string>
     */
    private const ROLES = [
        'Administrator',
        'IT Manager',
        'Operator',
        'Auditor',
        'Viewer',
    ];

    /**
     * Run the database seeds.
     *
     * Creates at least 25 users with variety of statuses and roles
     * for pagination testing and development.
     */
    public function run(): void
    {
        // Create an admin user for development (or update existing)
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'status' => 'active',
                'last_active_at' => now(),
            ]
        );
        if (! $admin->hasRole('Administrator')) {
            $admin->assignRole('Administrator');
        }

        // Create a second admin (for testing self-demotion prevention)
        $admin2 = User::firstOrCreate(
            ['email' => 'admin2@example.com'],
            [
                'name' => 'Secondary Admin',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'status' => 'active',
                'last_active_at' => now()->subHours(2),
            ]
        );
        if (! $admin2->hasRole('Administrator')) {
            $admin2->assignRole('Administrator');
        }

        // Only seed additional users if we haven't already
        if (User::count() > 5) {
            return;
        }

        // Create IT Manager users
        User::factory()->count(3)->create(['status' => 'active'])
            ->each(fn ($user) => $user->assignRole('IT Manager'));

        // Create Operator users with mixed statuses
        $activeOperator = User::firstOrCreate(
            ['email' => 'operator.active@example.com'],
            [
                'name' => 'Active Operator',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'status' => 'active',
                'last_active_at' => now()->subDays(1),
            ]
        );
        if (! $activeOperator->hasRole('Operator')) {
            $activeOperator->assignRole('Operator');
        }

        $inactiveOperator = User::firstOrCreate(
            ['email' => 'operator.inactive@example.com'],
            [
                'name' => 'Inactive Operator',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'status' => 'inactive',
                'last_active_at' => now()->subMonths(3),
            ]
        );
        if (! $inactiveOperator->hasRole('Operator')) {
            $inactiveOperator->assignRole('Operator');
        }

        User::factory()->count(2)->create(['status' => 'active'])
            ->each(fn ($user) => $user->assignRole('Operator'));

        // Create Auditor users
        User::factory()->count(3)->create(['status' => 'active'])
            ->each(fn ($user) => $user->assignRole('Auditor'));

        $suspendedAuditor = User::firstOrCreate(
            ['email' => 'auditor.suspended@example.com'],
            [
                'name' => 'Suspended Auditor',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'status' => 'suspended',
                'last_active_at' => now()->subMonths(1),
            ]
        );
        if (! $suspendedAuditor->hasRole('Auditor')) {
            $suspendedAuditor->assignRole('Auditor');
        }

        // Create Viewer users (the largest group)
        User::factory()->count(8)->create(['status' => 'active'])
            ->each(fn ($user) => $user->assignRole('Viewer'));

        // Create some inactive viewers
        User::factory()->inactive()->count(2)->create()
            ->each(fn ($user) => $user->assignRole('Viewer'));

        // Create some suspended viewers
        User::factory()->suspended()->count(2)->create()
            ->each(fn ($user) => $user->assignRole('Viewer'));

        // Create users who have never logged in
        User::factory()->neverLoggedIn()->count(3)->create(['status' => 'active'])
            ->each(fn ($user) => $user->assignRole('Viewer'));

        // Summary: 25+ users created with:
        // - 2 Administrators (both active)
        // - 3 IT Managers (all active)
        // - 4 Operators (2 active, 1 inactive, 1 active)
        // - 4 Auditors (3 active, 1 suspended)
        // - 15 Viewers (8 active, 2 inactive, 2 suspended, 3 never logged in)
        // Total: 28 users
    }
}
