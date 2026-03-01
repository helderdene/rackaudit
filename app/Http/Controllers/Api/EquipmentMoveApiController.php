<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\EquipmentMove;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API Controller for equipment move wizard support endpoints.
 *
 * Provides device search, location hierarchy, and rack device data
 * for the move wizard frontend components.
 */
class EquipmentMoveApiController extends Controller
{
    /**
     * Search devices by name, asset tag, or serial number.
     *
     * Returns devices with their connections and pending move status.
     */
    public function searchDevices(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        $limit = (int) $request->input('limit', 10);

        if (strlen($query) < 2) {
            return response()->json(['data' => []]);
        }

        $devices = Device::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('asset_tag', 'like', "%{$query}%")
                    ->orWhere('serial_number', 'like', "%{$query}%");
            })
            ->whereNotNull('rack_id') // Only devices that are placed
            ->with(['deviceType', 'rack.row.room.datacenter'])
            ->limit($limit)
            ->get();

        $deviceData = $devices->map(function (Device $device) {
            // Get active connections for this device
            $connections = Connection::query()
                ->where(function ($q) use ($device) {
                    $q->whereHas('sourcePort', function ($pq) use ($device) {
                        $pq->where('device_id', $device->id);
                    })
                        ->orWhereHas('destinationPort', function ($pq) use ($device) {
                            $pq->where('device_id', $device->id);
                        });
                })
                ->with(['sourcePort.device', 'destinationPort.device'])
                ->get()
                ->map(function (Connection $conn) use ($device) {
                    // Determine which port belongs to this device and which is remote
                    $isSourceDevice = $conn->sourcePort?->device_id === $device->id;
                    $localPort = $isSourceDevice ? $conn->sourcePort : $conn->destinationPort;
                    $remotePort = $isSourceDevice ? $conn->destinationPort : $conn->sourcePort;

                    return [
                        'id' => $conn->id,
                        'source_port_label' => $localPort?->label ?? 'Unknown',
                        'destination_port_label' => $remotePort?->label ?? 'Unknown',
                        'destination_device_name' => $remotePort?->device?->name ?? 'Unknown Device',
                        'cable_type' => $conn->cable_type,
                        'cable_length' => $conn->cable_length,
                        'cable_color' => $conn->cable_color,
                    ];
                });

            // Check for pending moves
            $hasPendingMove = EquipmentMove::query()
                ->where('device_id', $device->id)
                ->where('status', 'pending_approval')
                ->exists();

            // Build location path
            $locationPath = collect([
                $device->rack?->row?->room?->datacenter?->name,
                $device->rack?->row?->room?->name,
                $device->rack?->row?->name,
                $device->rack?->name,
            ])->filter()->implode(' > ');

            return [
                'id' => $device->id,
                'name' => $device->name,
                'asset_tag' => $device->asset_tag,
                'serial_number' => $device->serial_number,
                'manufacturer' => $device->manufacturer,
                'model' => $device->model,
                'u_height' => $device->u_height,
                'width_type' => $device->width_type?->value,
                'width_type_label' => $device->width_type?->label(),
                'rack_face' => $device->rack_face?->value,
                'rack_face_label' => $device->rack_face?->label(),
                'start_u' => $device->start_u,
                'lifecycle_status' => $device->lifecycle_status?->value,
                'lifecycle_status_label' => $device->lifecycle_status?->label(),
                'device_type' => $device->deviceType ? [
                    'id' => $device->deviceType->id,
                    'name' => $device->deviceType->name,
                ] : null,
                'rack' => $device->rack ? [
                    'id' => $device->rack->id,
                    'name' => $device->rack->name,
                ] : null,
                'location_path' => $locationPath ?: null,
                'connections' => $connections->values()->all(),
                'has_pending_move' => $hasPendingMove,
            ];
        });

        return response()->json(['data' => $deviceData]);
    }

    /**
     * Get location hierarchy for destination picker.
     *
     * Returns all datacenters, rooms, rows, and racks.
     */
    public function locationHierarchy(): JsonResponse
    {
        $datacenters = Datacenter::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $rooms = Room::query()
            ->orderBy('name')
            ->get(['id', 'name', 'datacenter_id']);

        $rows = Row::query()
            ->orderBy('name')
            ->get(['id', 'name', 'room_id']);

        $racks = Rack::query()
            ->orderBy('position')
            ->get(['id', 'name', 'row_id', 'u_height', 'position'])
            ->map(function (Rack $rack) {
                return [
                    'id' => $rack->id,
                    'name' => $rack->name,
                    'row_id' => $rack->row_id,
                    'u_height' => $rack->u_height instanceof \App\Enums\RackUHeight
                        ? $rack->u_height->value
                        : $rack->u_height,
                    'position' => $rack->position,
                ];
            });

        return response()->json([
            'datacenters' => $datacenters,
            'rooms' => $rooms,
            'rows' => $rows,
            'racks' => $racks,
        ]);
    }

    /**
     * Get devices placed in a specific rack.
     *
     * Returns device placement information for collision detection.
     */
    public function rackDevices(Rack $rack): JsonResponse
    {
        $devices = Device::query()
            ->where('rack_id', $rack->id)
            ->whereNotNull('start_u')
            ->get()
            ->map(function (Device $device) {
                return [
                    'id' => $device->id,
                    'name' => $device->name,
                    'start_u' => $device->start_u,
                    'u_height' => $device->u_height,
                    'rack_face' => $device->rack_face?->value ?? 'front',
                    'width_type' => $device->width_type?->value ?? 'full',
                ];
            });

        return response()->json([
            'rack' => [
                'id' => $rack->id,
                'name' => $rack->name,
                'u_height' => $rack->u_height instanceof \App\Enums\RackUHeight
                    ? $rack->u_height->value
                    : $rack->u_height,
            ],
            'devices' => $devices,
        ]);
    }
}
