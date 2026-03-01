<?php

namespace Database\Seeders;

use App\Enums\CableType;
use App\Enums\DeviceLifecycleStatus;
use App\Enums\PortDirection;
use App\Enums\PortStatus;
use App\Enums\PortSubtype;
use App\Enums\PortType;
use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Enums\RoomType;
use App\Enums\RowOrientation;
use App\Enums\RowStatus;
use App\Models\CapacitySnapshot;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Pdu;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeds a realistic mid-size datacenter with complete infrastructure.
 *
 * Creates:
 * - 1 datacenter (Metro Data Center)
 * - 3 rooms (Server Hall A, Server Hall B, Network Core)
 * - 8 rows total
 * - 40 racks
 * - ~200 devices (servers, switches, storage, etc.)
 * - Ports and connections
 * - Power consumption data
 * - 12 weeks of historical capacity snapshots
 */
class MidSizeDatacenterSeeder extends Seeder
{
    private array $deviceTypes = [];

    private int $portCounter = 0;

    public function run(): void
    {
        $this->command->info('Creating mid-size datacenter...');

        // Load device types
        $this->loadDeviceTypes();

        // Create datacenter
        $datacenter = $this->createDatacenter();

        // Create rooms
        $rooms = $this->createRooms($datacenter);

        // Create rows and racks
        $allRacks = [];
        foreach ($rooms as $roomName => $room) {
            $rows = $this->createRows($room, $roomName);
            foreach ($rows as $row) {
                $racks = $this->createRacks($row, $roomName);
                $allRacks = array_merge($allRacks, $racks);
            }
        }

        $this->command->info('Created '.count($allRacks).' racks');

        // Populate racks with devices
        $allDevices = $this->populateRacks($allRacks, $rooms);

        $this->command->info('Created '.count($allDevices).' devices');

        // Create network connections
        $this->createNetworkConnections($allDevices);

        // Create historical capacity snapshots
        $this->createCapacitySnapshots($datacenter);

        $this->command->info('Mid-size datacenter created successfully!');
    }

    private function loadDeviceTypes(): void
    {
        $types = DeviceType::all();
        foreach ($types as $type) {
            $this->deviceTypes[$type->name] = $type;
        }
    }

    private function createDatacenter(): Datacenter
    {
        return Datacenter::create([
            'name' => 'Metro Data Center',
            'address_line_1' => '1250 Technology Drive',
            'city' => 'San Jose',
            'state_province' => 'CA',
            'postal_code' => '95110',
            'country' => 'USA',
            'company_name' => 'Metro DC Holdings LLC',
            'primary_contact_name' => 'John Martinez',
            'primary_contact_email' => 'jmartinez@metrodc.example.com',
            'primary_contact_phone' => '+1 (408) 555-0100',
        ]);
    }

    private function createRooms(Datacenter $datacenter): array
    {
        $roomsData = [
            'Server Hall A' => [
                'description' => 'Primary server hall - Production workloads',
                'type' => RoomType::ServerRoom,
                'square_footage' => 5000,
            ],
            'Server Hall B' => [
                'description' => 'Secondary server hall - Development and staging',
                'type' => RoomType::ServerRoom,
                'square_footage' => 4000,
            ],
            'Network Core' => [
                'description' => 'Core network infrastructure and storage',
                'type' => RoomType::NetworkCloset,
                'square_footage' => 2500,
            ],
        ];

        $rooms = [];
        foreach ($roomsData as $name => $data) {
            $rooms[$name] = Room::create([
                'datacenter_id' => $datacenter->id,
                'name' => $name,
                'description' => $data['description'],
                'type' => $data['type'],
                'square_footage' => $data['square_footage'],
            ]);

            // Create PDU for each room
            Pdu::create([
                'room_id' => $rooms[$name]->id,
                'name' => $name.' PDU-Main',
                'manufacturer' => 'APC',
                'model' => 'Symmetra PX',
                'total_capacity_kw' => $name === 'Network Core' ? 150.0 : 300.0,
                'voltage' => 208,
                'phase' => 'three_phase',
                'circuit_count' => 42,
                'status' => 'active',
            ]);
        }

        return $rooms;
    }

    private function createRows(Room $room, string $roomName): array
    {
        $rowCounts = [
            'Server Hall A' => 3,
            'Server Hall B' => 3,
            'Network Core' => 2,
        ];

        $rows = [];
        $count = $rowCounts[$roomName] ?? 2;

        for ($i = 1; $i <= $count; $i++) {
            $rows[] = Row::create([
                'room_id' => $room->id,
                'name' => "Row {$i}",
                'position' => $i,
                'orientation' => $i % 2 === 0 ? RowOrientation::HotAisle : RowOrientation::ColdAisle,
                'status' => RowStatus::Active,
            ]);
        }

        return $rows;
    }

    private function createRacks(Row $row, string $roomName): array
    {
        $racksPerRow = [
            'Server Hall A' => 6,
            'Server Hall B' => 5,
            'Network Core' => 4,
        ];

        $racks = [];
        $count = $racksPerRow[$roomName] ?? 4;
        $prefix = match ($roomName) {
            'Server Hall A' => 'A',
            'Server Hall B' => 'B',
            'Network Core' => 'N',
            default => 'R',
        };

        for ($i = 1; $i <= $count; $i++) {
            $rackName = "{$prefix}{$row->position}-{$i}";
            $uHeight = fake()->randomElement([RackUHeight::U42, RackUHeight::U42, RackUHeight::U48]);

            $racks[] = Rack::create([
                'row_id' => $row->id,
                'name' => $rackName,
                'position' => $i,
                'u_height' => $uHeight,
                'status' => RackStatus::Active,
                'serial_number' => 'RK-'.strtoupper(fake()->bothify('####-????')),
                'power_capacity_watts' => fake()->randomElement([8000, 10000, 12000, 15000]),
            ]);
        }

        return $racks;
    }

    private function populateRacks(array $racks, array $rooms): array
    {
        $allDevices = [];

        foreach ($racks as $rack) {
            $roomName = $this->getRoomNameForRack($rack, $rooms);
            $devices = $this->populateSingleRack($rack, $roomName);
            $allDevices = array_merge($allDevices, $devices);
        }

        return $allDevices;
    }

    private function getRoomNameForRack(Rack $rack, array $rooms): string
    {
        $roomId = $rack->row->room_id;
        foreach ($rooms as $name => $room) {
            if ($room->id === $roomId) {
                return $name;
            }
        }

        return 'Unknown';
    }

    private function populateSingleRack(Rack $rack, string $roomName): array
    {
        $devices = [];
        $currentU = 1;
        $maxU = $rack->u_height->value;

        // Network Core gets different equipment mix
        if ($roomName === 'Network Core') {
            $devices = array_merge($devices, $this->populateNetworkRack($rack, $currentU, $maxU));
        } else {
            $devices = array_merge($devices, $this->populateServerRack($rack, $currentU, $maxU));
        }

        return $devices;
    }

    private function populateServerRack(Rack $rack, int $currentU, int $maxU): array
    {
        $devices = [];

        // Top-of-Rack Switch (1U at top)
        if ($currentU <= $maxU - 1) {
            $devices[] = $this->createDevice($rack, 'Switch', $maxU, 1, 'ToR Switch');
        }

        // Servers fill most of the rack
        $serverU = 1;
        while ($serverU <= $maxU - 4) {
            $uHeight = fake()->randomElement([1, 2, 2, 2, 4]);
            if ($serverU + $uHeight > $maxU - 2) {
                break;
            }

            $serverType = fake()->randomElement(['Web Server', 'App Server', 'Database Server', 'Compute Node']);
            $devices[] = $this->createDevice($rack, 'Server', $serverU, $uHeight, $serverType);
            $serverU += $uHeight;

            // Sometimes add storage
            if (fake()->boolean(15) && $serverU + 4 <= $maxU - 2) {
                $devices[] = $this->createDevice($rack, 'Storage', $serverU, 4, 'Storage Array');
                $serverU += 4;
            }
        }

        return $devices;
    }

    private function populateNetworkRack(Rack $rack, int $currentU, int $maxU): array
    {
        $devices = [];
        $usedU = 1;

        // Core switches at top
        $devices[] = $this->createDevice($rack, 'Switch', $maxU - 1, 2, 'Core Switch');

        // Distribution switches
        $devices[] = $this->createDevice($rack, 'Switch', $maxU - 4, 1, 'Distribution Switch');
        $devices[] = $this->createDevice($rack, 'Switch', $maxU - 5, 1, 'Distribution Switch');

        // Routers
        if (fake()->boolean(60)) {
            $devices[] = $this->createDevice($rack, 'Router', $maxU - 7, 1, 'Edge Router');
        }

        // Patch panels
        for ($i = 0; $i < 3; $i++) {
            $patchU = 5 + ($i * 3);
            if ($patchU <= $maxU - 10) {
                $devices[] = $this->createDevice($rack, 'Patch Panel', $patchU, 1, 'Patch Panel');
            }
        }

        // Storage arrays
        if (fake()->boolean(70)) {
            $devices[] = $this->createDevice($rack, 'Storage', 15, 4, 'SAN Controller');
        }

        // Console server
        $devices[] = $this->createDevice($rack, 'Console Server', 1, 1, 'Console Server');

        return $devices;
    }

    private function createDevice(Rack $rack, string $typeName, int $startU, int $uHeight, string $namePrefix): Device
    {
        $deviceType = $this->deviceTypes[$typeName] ?? $this->deviceTypes['Server'];
        $number = str_pad((string) fake()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT);

        $powerDraw = match ($typeName) {
            'Server' => fake()->numberBetween(300, 800),
            'Switch' => fake()->numberBetween(100, 400),
            'Router' => fake()->numberBetween(150, 350),
            'Storage' => fake()->numberBetween(500, 1500),
            'Patch Panel' => null,
            'Console Server' => fake()->numberBetween(50, 100),
            default => fake()->numberBetween(100, 500),
        };

        $device = Device::create([
            'name' => "{$namePrefix}-{$number}",
            'device_type_id' => $deviceType->id,
            'rack_id' => $rack->id,
            'start_u' => $startU,
            'u_height' => $uHeight,
            'lifecycle_status' => DeviceLifecycleStatus::Deployed,
            'serial_number' => strtoupper(fake()->bothify('SN-????-####-????')),
            'manufacturer' => fake()->randomElement(['Dell', 'HP', 'Cisco', 'Juniper', 'Lenovo', 'Arista']),
            'model' => fake()->bothify('Model-???-####'),
            'purchase_date' => fake()->dateTimeBetween('-3 years', '-6 months'),
            'warranty_end_date' => fake()->dateTimeBetween('+6 months', '+3 years'),
            'power_draw_watts' => $powerDraw,
        ]);

        // Create ports for the device
        $this->createPortsForDevice($device, $typeName);

        return $device;
    }

    private function createPortsForDevice(Device $device, string $typeName): void
    {
        $portConfigs = match ($typeName) {
            'Server' => [
                ['type' => PortType::Ethernet, 'count' => fake()->numberBetween(2, 4), 'prefix' => 'eth'],
                ['type' => PortType::Power, 'count' => 2, 'prefix' => 'psu'],
            ],
            'Switch' => [
                ['type' => PortType::Ethernet, 'count' => fake()->numberBetween(24, 48), 'prefix' => 'Gi0/'],
                ['type' => PortType::Fiber, 'count' => fake()->numberBetween(2, 8), 'prefix' => 'Te0/'],
                ['type' => PortType::Power, 'count' => 2, 'prefix' => 'psu'],
            ],
            'Router' => [
                ['type' => PortType::Ethernet, 'count' => 4, 'prefix' => 'Gi0/'],
                ['type' => PortType::Fiber, 'count' => 4, 'prefix' => 'Te0/'],
                ['type' => PortType::Power, 'count' => 2, 'prefix' => 'psu'],
            ],
            'Storage' => [
                ['type' => PortType::Ethernet, 'count' => 2, 'prefix' => 'mgmt'],
                ['type' => PortType::Fiber, 'count' => 4, 'prefix' => 'fc'],
                ['type' => PortType::Power, 'count' => 2, 'prefix' => 'psu'],
            ],
            'Patch Panel' => [
                ['type' => PortType::Ethernet, 'count' => 24, 'prefix' => 'port'],
            ],
            'Console Server' => [
                ['type' => PortType::Ethernet, 'count' => 16, 'prefix' => 'console'],
                ['type' => PortType::Power, 'count' => 1, 'prefix' => 'psu'],
            ],
            default => [
                ['type' => PortType::Ethernet, 'count' => 2, 'prefix' => 'eth'],
                ['type' => PortType::Power, 'count' => 1, 'prefix' => 'psu'],
            ],
        };

        foreach ($portConfigs as $config) {
            for ($i = 1; $i <= $config['count']; $i++) {
                $subtype = match ($config['type']) {
                    PortType::Ethernet => fake()->randomElement([PortSubtype::Gbe1, PortSubtype::Gbe10]),
                    PortType::Fiber => fake()->randomElement([PortSubtype::Lc, PortSubtype::Sc]),
                    PortType::Power => fake()->randomElement([PortSubtype::C13, PortSubtype::C14]),
                };

                Port::create([
                    'device_id' => $device->id,
                    'label' => "{$config['prefix']}{$i}",
                    'type' => $config['type'],
                    'subtype' => $subtype,
                    'status' => PortStatus::Available,
                    'direction' => PortDirection::defaultForType($config['type']),
                ]);

                $this->portCounter++;
            }
        }
    }

    private function createNetworkConnections(array $devices): void
    {
        $this->command->info('Creating network connections...');

        // Group devices by type
        $switches = collect($devices)->filter(fn ($d) => str_contains($d->name, 'Switch'));
        $servers = collect($devices)->filter(fn ($d) => str_contains($d->name, 'Server') || str_contains($d->name, 'Node'));
        $storage = collect($devices)->filter(fn ($d) => str_contains($d->name, 'Storage') || str_contains($d->name, 'SAN'));

        $connectionCount = 0;

        // Connect servers to their ToR switches
        foreach ($servers as $server) {
            $torSwitch = $switches->first(fn ($s) => $s->rack_id === $server->rack_id);
            if ($torSwitch) {
                $connectionCount += $this->connectDevices($server, $torSwitch, PortType::Ethernet, CableType::Cat6);
            }
        }

        // Connect storage to switches via fiber
        foreach ($storage as $storageDevice) {
            $nearbySwitch = $switches->random();
            $connectionCount += $this->connectDevices($storageDevice, $nearbySwitch, PortType::Fiber, CableType::FiberMm);
        }

        // Connect ToR switches to core switches
        $coreSwitches = $switches->filter(fn ($s) => str_contains($s->name, 'Core'));
        $torSwitches = $switches->filter(fn ($s) => str_contains($s->name, 'ToR'));

        if ($coreSwitches->isNotEmpty()) {
            foreach ($torSwitches as $tor) {
                $core = $coreSwitches->random();
                $connectionCount += $this->connectDevices($tor, $core, PortType::Fiber, CableType::FiberSm);
            }
        }

        $this->command->info("Created {$connectionCount} connections");
    }

    private function connectDevices(Device $source, Device $dest, PortType $portType, CableType $cableType): int
    {
        $sourcePorts = Port::where('device_id', $source->id)
            ->where('type', $portType)
            ->where('status', PortStatus::Available)
            ->limit(2)
            ->get();

        $destPorts = Port::where('device_id', $dest->id)
            ->where('type', $portType)
            ->where('status', PortStatus::Available)
            ->limit(2)
            ->get();

        $count = 0;
        $pairCount = min($sourcePorts->count(), $destPorts->count());

        for ($i = 0; $i < $pairCount; $i++) {
            $sourcePort = $sourcePorts[$i];
            $destPort = $destPorts[$i];

            Connection::create([
                'source_port_id' => $sourcePort->id,
                'destination_port_id' => $destPort->id,
                'cable_type' => $cableType,
                'cable_length' => fake()->randomFloat(1, 0.5, 10),
                'cable_color' => fake()->randomElement(['blue', 'yellow', 'green', 'orange', 'white']),
            ]);

            $sourcePort->update(['status' => PortStatus::Connected]);
            $destPort->update(['status' => PortStatus::Connected]);
            $count++;
        }

        return $count;
    }

    private function createCapacitySnapshots(Datacenter $datacenter): void
    {
        $this->command->info('Creating historical capacity snapshots...');

        // Create 12 weeks of historical snapshots
        for ($week = 12; $week >= 0; $week--) {
            $snapshotDate = Carbon::now()->subWeeks($week)->startOfWeek();

            // Simulate gradual increase in utilization over time
            $baseUtilization = 55 + ($week * -0.5) + fake()->numberBetween(-3, 3);
            $baseUtilization = max(40, min(85, $baseUtilization));

            $basePowerUtilization = 45 + ($week * -0.3) + fake()->numberBetween(-2, 2);
            $basePowerUtilization = max(30, min(75, $basePowerUtilization));

            CapacitySnapshot::create([
                'datacenter_id' => $datacenter->id,
                'snapshot_date' => $snapshotDate,
                'rack_utilization_percent' => round($baseUtilization, 2),
                'power_utilization_percent' => round($basePowerUtilization, 2),
                'total_u_space' => 1680, // 40 racks * ~42U average
                'used_u_space' => (int) round(1680 * ($baseUtilization / 100)),
                'total_power_capacity' => 440000, // ~11kW per rack * 40 racks
                'total_power_consumption' => (int) round(440000 * ($basePowerUtilization / 100)),
                'port_stats' => [
                    'ethernet' => ['total' => 2400, 'connected' => (int) (2400 * 0.6), 'available' => (int) (2400 * 0.4)],
                    'fiber' => ['total' => 320, 'connected' => (int) (320 * 0.45), 'available' => (int) (320 * 0.55)],
                    'power' => ['total' => 400, 'connected' => (int) (400 * 0.85), 'available' => (int) (400 * 0.15)],
                ],
            ]);
        }

        $this->command->info('Created 13 weeks of capacity snapshots');
    }
}
