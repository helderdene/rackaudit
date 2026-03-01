<?php

namespace Database\Seeders;

use App\Models\DeviceType;
use Illuminate\Database\Seeder;

/**
 * Seeds the database with common datacenter device types.
 *
 * Creates 10 standard device types commonly found in datacenters:
 * Server, Switch, Router, Storage, PDU, UPS, Patch Panel, KVM,
 * Console Server, and Blade Chassis.
 */
class DeviceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Only seed if no device types exist
        if (DeviceType::count() > 0) {
            return;
        }

        $deviceTypes = [
            [
                'name' => 'Server',
                'description' => 'Physical or virtual server for compute workloads',
                'default_u_size' => 2,
            ],
            [
                'name' => 'Switch',
                'description' => 'Network switch for LAN/WAN connectivity',
                'default_u_size' => 1,
            ],
            [
                'name' => 'Router',
                'description' => 'Network router for routing traffic between networks',
                'default_u_size' => 1,
            ],
            [
                'name' => 'Storage',
                'description' => 'Storage array, NAS, or SAN device',
                'default_u_size' => 4,
            ],
            [
                'name' => 'PDU',
                'description' => 'Power Distribution Unit for rack power management',
                'default_u_size' => 0,
            ],
            [
                'name' => 'UPS',
                'description' => 'Uninterruptible Power Supply for backup power',
                'default_u_size' => 3,
            ],
            [
                'name' => 'Patch Panel',
                'description' => 'Network patch panel for cable organization',
                'default_u_size' => 1,
            ],
            [
                'name' => 'KVM',
                'description' => 'Keyboard, Video, Mouse switch for server management',
                'default_u_size' => 1,
            ],
            [
                'name' => 'Console Server',
                'description' => 'Serial console server for out-of-band management',
                'default_u_size' => 1,
            ],
            [
                'name' => 'Blade Chassis',
                'description' => 'Blade server chassis housing multiple blade servers',
                'default_u_size' => 10,
            ],
        ];

        foreach ($deviceTypes as $deviceType) {
            DeviceType::create($deviceType);
        }
    }
}
