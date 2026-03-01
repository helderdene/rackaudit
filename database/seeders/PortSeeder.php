<?php

namespace Database\Seeders;

use App\Enums\PortDirection;
use App\Enums\PortStatus;
use App\Enums\PortSubtype;
use App\Enums\PortType;
use App\Enums\PortVisualFace;
use App\Models\Device;
use App\Models\Port;
use Illuminate\Database\Seeder;

/**
 * Seeds the database with sample port records for development.
 *
 * Creates ports for existing devices with a variety of types,
 * subtypes, and statuses to demonstrate port management features.
 */
class PortSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Only seed if no ports exist
        if (Port::count() > 0) {
            return;
        }

        // Get existing devices
        $devices = Device::all();

        if ($devices->isEmpty()) {
            return;
        }

        foreach ($devices as $device) {
            $this->createPortsForDevice($device);
        }
    }

    /**
     * Create ports for a device based on its name/type.
     */
    private function createPortsForDevice(Device $device): void
    {
        $name = strtolower($device->name);

        // Determine port configuration based on device name
        if (str_contains($name, 'switch')) {
            $this->createSwitchPorts($device);
        } elseif (str_contains($name, 'router')) {
            $this->createRouterPorts($device);
        } elseif (str_contains($name, 'server')) {
            $this->createServerPorts($device);
        } elseif (str_contains($name, 'patch panel')) {
            $this->createPatchPanelPorts($device);
        } elseif (str_contains($name, 'pdu')) {
            $this->createPduPorts($device);
        } else {
            // Default: create basic Ethernet ports
            $this->createBasicPorts($device);
        }
    }

    /**
     * Create ports for a network switch.
     */
    private function createSwitchPorts(Device $device): void
    {
        // 48 x 1GbE downlink ports
        for ($i = 1; $i <= 48; $i++) {
            Port::create([
                'device_id' => $device->id,
                'label' => 'Gi1/0/' . $i,
                'type' => PortType::Ethernet,
                'subtype' => PortSubtype::Gbe1,
                'status' => $i <= 24 ? PortStatus::Connected : PortStatus::Available,
                'direction' => PortDirection::Downlink,
                'position_row' => $i <= 24 ? 1 : 2,
                'position_column' => $i <= 24 ? $i : $i - 24,
                'visual_face' => PortVisualFace::Front,
            ]);
        }

        // 4 x 10GbE uplink ports
        for ($i = 1; $i <= 4; $i++) {
            Port::create([
                'device_id' => $device->id,
                'label' => 'Te1/0/' . $i,
                'type' => PortType::Ethernet,
                'subtype' => PortSubtype::Gbe10,
                'status' => $i <= 2 ? PortStatus::Connected : PortStatus::Available,
                'direction' => PortDirection::Uplink,
                'position_row' => 3,
                'position_column' => $i,
                'visual_face' => PortVisualFace::Front,
            ]);
        }

        // 2 x Power ports
        $this->createPowerPorts($device, 2);
    }

    /**
     * Create ports for a router.
     */
    private function createRouterPorts(Device $device): void
    {
        // 4 x 10GbE ports
        for ($i = 0; $i <= 3; $i++) {
            Port::create([
                'device_id' => $device->id,
                'label' => 'TenGigabitEthernet0/' . $i,
                'type' => PortType::Ethernet,
                'subtype' => PortSubtype::Gbe10,
                'status' => $i < 2 ? PortStatus::Connected : PortStatus::Available,
                'direction' => PortDirection::Bidirectional,
                'visual_face' => PortVisualFace::Front,
            ]);
        }

        // 2 x Fiber ports
        for ($i = 0; $i <= 1; $i++) {
            Port::create([
                'device_id' => $device->id,
                'label' => 'Fiber0/' . $i,
                'type' => PortType::Fiber,
                'subtype' => PortSubtype::Lc,
                'status' => PortStatus::Available,
                'direction' => PortDirection::Bidirectional,
                'visual_face' => PortVisualFace::Rear,
            ]);
        }

        // 2 x Power ports
        $this->createPowerPorts($device, 2);
    }

    /**
     * Create ports for a server.
     */
    private function createServerPorts(Device $device): void
    {
        // 4 x 1GbE management/production ports
        for ($i = 0; $i <= 3; $i++) {
            Port::create([
                'device_id' => $device->id,
                'label' => 'eth' . $i,
                'type' => PortType::Ethernet,
                'subtype' => PortSubtype::Gbe1,
                'status' => $i < 2 ? PortStatus::Connected : PortStatus::Available,
                'direction' => PortDirection::Bidirectional,
                'visual_face' => PortVisualFace::Rear,
            ]);
        }

        // 1 x IPMI/iLO port
        Port::create([
            'device_id' => $device->id,
            'label' => 'ipmi',
            'type' => PortType::Ethernet,
            'subtype' => PortSubtype::Gbe1,
            'status' => PortStatus::Connected,
            'direction' => PortDirection::Bidirectional,
            'visual_face' => PortVisualFace::Rear,
        ]);

        // 2 x Power ports
        $this->createPowerPorts($device, 2);
    }

    /**
     * Create ports for a patch panel.
     */
    private function createPatchPanelPorts(Device $device): void
    {
        // 24 x Fiber LC ports (front and rear)
        for ($i = 1; $i <= 24; $i++) {
            // Front port
            Port::create([
                'device_id' => $device->id,
                'label' => 'Port ' . $i . ' (Front)',
                'type' => PortType::Fiber,
                'subtype' => PortSubtype::Lc,
                'status' => $i <= 12 ? PortStatus::Connected : PortStatus::Available,
                'direction' => PortDirection::Bidirectional,
                'position_row' => 1,
                'position_column' => $i,
                'visual_face' => PortVisualFace::Front,
            ]);

            // Rear port
            Port::create([
                'device_id' => $device->id,
                'label' => 'Port ' . $i . ' (Rear)',
                'type' => PortType::Fiber,
                'subtype' => PortSubtype::Lc,
                'status' => $i <= 12 ? PortStatus::Connected : PortStatus::Available,
                'direction' => PortDirection::Bidirectional,
                'position_row' => 1,
                'position_column' => $i,
                'visual_face' => PortVisualFace::Rear,
            ]);
        }
    }

    /**
     * Create ports for a PDU.
     */
    private function createPduPorts(Device $device): void
    {
        // Input power port (C20)
        Port::create([
            'device_id' => $device->id,
            'label' => 'Power Input',
            'type' => PortType::Power,
            'subtype' => PortSubtype::C20,
            'status' => PortStatus::Connected,
            'direction' => PortDirection::Input,
            'visual_face' => PortVisualFace::Rear,
        ]);

        // 12 x Output power ports (C13)
        for ($i = 1; $i <= 12; $i++) {
            Port::create([
                'device_id' => $device->id,
                'label' => 'Outlet ' . $i,
                'type' => PortType::Power,
                'subtype' => PortSubtype::C13,
                'status' => $i <= 8 ? PortStatus::Connected : PortStatus::Available,
                'direction' => PortDirection::Output,
                'position_row' => 1,
                'position_column' => $i,
                'visual_face' => PortVisualFace::Front,
            ]);
        }
    }

    /**
     * Create basic Ethernet and power ports.
     */
    private function createBasicPorts(Device $device): void
    {
        // 2 x 1GbE ports
        for ($i = 0; $i <= 1; $i++) {
            Port::create([
                'device_id' => $device->id,
                'label' => 'eth' . $i,
                'type' => PortType::Ethernet,
                'subtype' => PortSubtype::Gbe1,
                'status' => $i === 0 ? PortStatus::Connected : PortStatus::Available,
                'direction' => PortDirection::Bidirectional,
                'visual_face' => PortVisualFace::Rear,
            ]);
        }

        // 2 x Power ports
        $this->createPowerPorts($device, 2);
    }

    /**
     * Create power input ports for a device.
     */
    private function createPowerPorts(Device $device, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Port::create([
                'device_id' => $device->id,
                'label' => 'PSU-' . chr(64 + $i), // PSU-A, PSU-B, etc.
                'type' => PortType::Power,
                'subtype' => PortSubtype::C14,
                'status' => $i === 1 ? PortStatus::Connected : PortStatus::Available,
                'direction' => PortDirection::Input,
                'visual_face' => PortVisualFace::Rear,
            ]);
        }
    }
}
