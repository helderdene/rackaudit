<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first (before users)
        $this->call(RolesAndPermissionsSeeder::class);

        // Seed users with various roles and statuses for development testing
        $this->call(UserSeeder::class);

        // Seed datacenters for development testing
        $this->call(DatacenterSeeder::class);

        // Seed default finding categories
        $this->call(FindingCategorySeeder::class);
    }
}
