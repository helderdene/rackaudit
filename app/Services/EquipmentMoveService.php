<?php

namespace App\Services;

use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Models\Connection;
use App\Models\Device;
use App\Models\EquipmentMove;
use App\Models\Rack;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing equipment move workflow.
 *
 * Handles the creation, validation, approval, rejection, and execution
 * of device move requests within the datacenter. Includes collision
 * detection and automatic connection disconnection during moves.
 */
class EquipmentMoveService
{
    /**
     * Create a new move request for a device.
     *
     * Captures the device's current location and all active connections
     * as a snapshot before creating the move request.
     *
     * @param  array<string, mixed>  $destinationData  Must include: rack_id, start_u, rack_face, width_type
     */
    public function createMoveRequest(
        Device $device,
        array $destinationData,
        User $requester,
        ?string $operatorNotes = null
    ): EquipmentMove {
        // Capture connections snapshot before the move
        $connectionsSnapshot = $this->captureConnectionsSnapshot($device);

        return EquipmentMove::create([
            'device_id' => $device->id,
            'source_rack_id' => $device->rack_id,
            'source_start_u' => $device->start_u,
            'source_rack_face' => $device->rack_face,
            'source_width_type' => $device->width_type,
            'destination_rack_id' => $destinationData['rack_id'],
            'destination_start_u' => $destinationData['start_u'],
            'destination_rack_face' => $destinationData['rack_face'],
            'destination_width_type' => $destinationData['width_type'],
            'status' => 'pending_approval',
            'connections_snapshot' => $connectionsSnapshot,
            'requested_by' => $requester->id,
            'operator_notes' => $operatorNotes,
            'requested_at' => now(),
        ]);
    }

    /**
     * Capture all active connections for a device as a snapshot.
     *
     * Returns enriched connection data including port labels, device names,
     * and cable properties for documentation purposes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function captureConnectionsSnapshot(Device $device): array
    {
        $device->load(['ports.connectionAsSource.destinationPort.device', 'ports.connectionAsDestination.sourcePort.device']);

        $connections = [];

        foreach ($device->ports as $port) {
            // Check for connection where this port is the source
            $connection = $port->connectionAsSource;
            if ($connection) {
                $connections[] = $this->formatConnectionForSnapshot($connection, $port, 'source');
            }

            // Check for connection where this port is the destination
            $connection = $port->connectionAsDestination;
            if ($connection) {
                $connections[] = $this->formatConnectionForSnapshot($connection, $port, 'destination');
            }
        }

        return $connections;
    }

    /**
     * Format a connection for the snapshot.
     *
     * @return array<string, mixed>
     */
    protected function formatConnectionForSnapshot(Connection $connection, $devicePort, string $devicePortRole): array
    {
        $remotePort = $devicePortRole === 'source'
            ? $connection->destinationPort
            : $connection->sourcePort;

        $cableTypeLabel = $connection->cable_type?->label() ?? null;

        return [
            'id' => $connection->id,
            'source_port_label' => $devicePort->label,
            'destination_port_label' => $remotePort?->label,
            'destination_device_name' => $remotePort?->device?->name,
            'cable_type' => $cableTypeLabel,
            'cable_length' => $connection->cable_length,
            'cable_color' => $connection->cable_color,
        ];
    }

    /**
     * Check if a device already has a pending move request.
     */
    public function checkDeviceHasPendingMove(Device $device): bool
    {
        return EquipmentMove::forDevice($device->id)
            ->whereStatus('pending_approval')
            ->exists();
    }

    /**
     * Validate if a destination position is available in a rack.
     *
     * Implements collision detection logic based on useRackElevation.ts patterns.
     * Checks for conflicts with existing devices considering width types and rack faces.
     */
    public function validateDestinationPosition(
        int $rackId,
        int $startU,
        DeviceRackFace $face,
        DeviceWidthType $widthType,
        int $uSize,
        ?int $excludeDeviceId = null
    ): bool {
        $rack = Rack::find($rackId);
        if (! $rack) {
            return false;
        }

        $rackHeight = $rack->u_height instanceof \App\Enums\RackUHeight
            ? $rack->u_height->value
            : (int) $rack->u_height;

        $endU = $startU + $uSize - 1;

        // Check rack bounds
        if ($startU < 1 || $endU > $rackHeight) {
            return false;
        }

        // Get occupation map for the rack face
        $occupationMap = $this->getOccupationMap($rackId, $face, $excludeDeviceId);

        // Check each U position the device would occupy
        for ($u = $startU; $u <= $endU; $u++) {
            if (! isset($occupationMap[$u])) {
                continue; // Position is free
            }

            $occupied = $occupationMap[$u];

            // Check for conflicts based on width type
            if ($widthType === DeviceWidthType::Full) {
                // Full-width devices conflict with any existing device
                if ($occupied['full'] || $occupied['halfLeft'] || $occupied['halfRight']) {
                    return false;
                }
            } elseif ($widthType === DeviceWidthType::HalfLeft) {
                // Half-left conflicts with full or another half-left
                if ($occupied['full'] || $occupied['halfLeft']) {
                    return false;
                }
            } elseif ($widthType === DeviceWidthType::HalfRight) {
                // Half-right conflicts with full or another half-right
                if ($occupied['full'] || $occupied['halfRight']) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Build an occupation map for a rack face.
     *
     * Returns an array mapping U positions to occupation status by width type.
     *
     * @return array<int, array{full: bool, halfLeft: bool, halfRight: bool}>
     */
    protected function getOccupationMap(int $rackId, DeviceRackFace $face, ?int $excludeDeviceId = null): array
    {
        $query = Device::where('rack_id', $rackId)
            ->where('rack_face', $face)
            ->whereNotNull('start_u');

        if ($excludeDeviceId !== null) {
            $query->where('id', '!=', $excludeDeviceId);
        }

        $devices = $query->get();
        $map = [];

        foreach ($devices as $device) {
            $uHeight = (int) ceil($device->u_height);

            for ($u = $device->start_u; $u < $device->start_u + $uHeight; $u++) {
                if (! isset($map[$u])) {
                    $map[$u] = ['full' => false, 'halfLeft' => false, 'halfRight' => false];
                }

                if ($device->width_type === DeviceWidthType::Full) {
                    $map[$u]['full'] = true;
                } elseif ($device->width_type === DeviceWidthType::HalfLeft) {
                    $map[$u]['halfLeft'] = true;
                } elseif ($device->width_type === DeviceWidthType::HalfRight) {
                    $map[$u]['halfRight'] = true;
                }
            }
        }

        return $map;
    }

    /**
     * Approve a pending move request and execute the move.
     *
     * Updates the move status, disconnects all device connections,
     * and updates the device's rack placement.
     */
    public function approveMove(EquipmentMove $move, User $approver, ?string $notes = null): bool
    {
        if (! $move->isPendingApproval()) {
            return false;
        }

        return DB::transaction(function () use ($move, $approver, $notes) {
            $move->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
                'approval_notes' => $notes,
                'approved_at' => now(),
            ]);

            // Execute the move immediately after approval
            return $this->executeMove($move);
        });
    }

    /**
     * Reject a pending move request.
     *
     * Updates the move status to rejected with the provided notes.
     * The device remains at its current location.
     */
    public function rejectMove(EquipmentMove $move, User $approver, string $notes): bool
    {
        if (! $move->isPendingApproval()) {
            return false;
        }

        $move->update([
            'status' => 'rejected',
            'approved_by' => $approver->id,
            'approval_notes' => $notes,
            'approved_at' => now(),
        ]);

        return true;
    }

    /**
     * Cancel a pending move request.
     *
     * Updates the move status to cancelled.
     * The device remains at its current location.
     */
    public function cancelMove(EquipmentMove $move, User $user): bool
    {
        if (! $move->isPendingApproval()) {
            return false;
        }

        $move->update([
            'status' => 'cancelled',
        ]);

        return true;
    }

    /**
     * Execute an approved move.
     *
     * Disconnects all device connections using soft delete,
     * then updates the device's rack placement to the destination.
     */
    public function executeMove(EquipmentMove $move): bool
    {
        if (! $move->isApproved() && $move->status !== 'approved') {
            return false;
        }

        return DB::transaction(function () use ($move) {
            // Disconnect all connections for the device
            $this->disconnectDeviceConnections($move->device);

            // Update device placement
            $move->device->update([
                'rack_id' => $move->destination_rack_id,
                'start_u' => $move->destination_start_u,
                'rack_face' => $move->destination_rack_face,
                'width_type' => $move->destination_width_type,
            ]);

            // Mark move as executed
            $move->update([
                'status' => 'executed',
                'executed_at' => now(),
            ]);

            return true;
        });
    }

    /**
     * Disconnect all connections for a device using soft delete.
     *
     * Preserves connection history via SoftDeletes trait on Connection model.
     */
    protected function disconnectDeviceConnections(Device $device): void
    {
        $device->load(['ports.connectionAsSource', 'ports.connectionAsDestination']);

        foreach ($device->ports as $port) {
            // Soft delete connection where this port is the source
            if ($port->connectionAsSource) {
                $port->connectionAsSource->delete();
            }

            // Soft delete connection where this port is the destination
            if ($port->connectionAsDestination) {
                $port->connectionAsDestination->delete();
            }
        }
    }
}
