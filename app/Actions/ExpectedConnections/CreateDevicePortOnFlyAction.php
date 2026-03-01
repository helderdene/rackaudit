<?php

namespace App\Actions\ExpectedConnections;

use App\Enums\DeviceLifecycleStatus;
use App\Enums\PortDirection;
use App\Enums\PortStatus;
use App\Enums\PortSubtype;
use App\Enums\PortType;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\ExpectedConnection;
use App\Models\Port;
use Illuminate\Support\Facades\DB;

/**
 * Action to create device and port on the fly for unrecognized entries.
 *
 * Used during the expected connection review process when fuzzy matching
 * couldn't find a match for a device/port combination. Creates the device
 * (if it doesn't exist) and port, then updates the expected connection.
 */
class CreateDevicePortOnFlyAction
{
    /**
     * Execute the action.
     *
     * @return array{
     *   success: bool,
     *   device: Device,
     *   port: Port,
     *   device_created: bool,
     *   expected_connection: ExpectedConnection
     * }
     */
    public function execute(
        ExpectedConnection $expectedConnection,
        string $deviceName,
        string $portLabel,
        string $target // 'source' or 'dest'
    ): array {
        return DB::transaction(function () use ($expectedConnection, $deviceName, $portLabel, $target) {
            // Check if device with this name already exists
            $device = Device::query()
                ->where('name', $deviceName)
                ->first();

            $deviceCreated = false;

            if (! $device) {
                // Get or create a default device type for unrecognized devices
                $deviceType = $this->getOrCreateDefaultDeviceType();

                // Create new device with minimal required fields
                $device = Device::create([
                    'name' => $deviceName,
                    'device_type_id' => $deviceType->id,
                    'lifecycle_status' => DeviceLifecycleStatus::Deployed,
                    'u_height' => 1, // Default 1U height
                ]);
                $deviceCreated = true;
            }

            // Check if port with this label already exists on the device
            $port = Port::query()
                ->where('device_id', $device->id)
                ->where('label', $portLabel)
                ->first();

            if (! $port) {
                // Create new port on the device with all required fields
                $port = Port::create([
                    'device_id' => $device->id,
                    'label' => $portLabel,
                    'type' => PortType::Ethernet,
                    'subtype' => PortSubtype::Gbe1,
                    'status' => PortStatus::Available,
                    'direction' => PortDirection::Bidirectional,
                ]);
            }

            // Update the expected connection with the new device/port
            if ($target === 'source') {
                $expectedConnection->update([
                    'source_device_id' => $device->id,
                    'source_port_id' => $port->id,
                ]);
            } else {
                $expectedConnection->update([
                    'dest_device_id' => $device->id,
                    'dest_port_id' => $port->id,
                ]);
            }

            // Reload the expected connection with fresh relationships
            $expectedConnection->refresh();
            $expectedConnection->load(['sourceDevice', 'sourcePort', 'destDevice', 'destPort']);

            return [
                'success' => true,
                'device' => $device,
                'port' => $port,
                'device_created' => $deviceCreated,
                'expected_connection' => $expectedConnection,
            ];
        });
    }

    /**
     * Get or create a default device type for devices created on the fly.
     */
    protected function getOrCreateDefaultDeviceType(): DeviceType
    {
        return DeviceType::firstOrCreate(
            ['name' => 'Unknown'],
            [
                'description' => 'Device type for equipment created during connection review',
                'default_u_size' => 1,
            ]
        );
    }
}
