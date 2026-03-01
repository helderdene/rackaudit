<?php

namespace App\Http\Controllers;

use App\Enums\PortDirection;
use App\Enums\PortStatus;
use App\Enums\PortSubtype;
use App\Enums\PortType;
use App\Enums\PortVisualFace;
use App\Http\Requests\BulkStorePortRequest;
use App\Http\Requests\PairPortRequest;
use App\Http\Requests\StorePortRequest;
use App\Http\Requests\UpdatePortRequest;
use App\Http\Resources\PortResource;
use App\Models\Device;
use App\Models\Port;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Controller for managing ports on devices.
 *
 * Ports are nested resources under devices. All port operations
 * require the user to have permission to update the parent device.
 * This controller returns JSON responses only (no Inertia pages).
 */
class PortController extends Controller
{
    /**
     * Display a listing of ports for a specific device.
     */
    public function index(Device $device): JsonResponse
    {
        Gate::authorize('view', $device);

        $ports = $device->ports()->orderBy('label')->get();

        return response()->json([
            'data' => PortResource::collection($ports),
        ]);
    }

    /**
     * Get ports with connection info for diagram drill-down.
     *
     * Returns all ports for a device with their connection details,
     * optimized for port-level diagram visualization.
     */
    public function diagram(Device $device): JsonResponse
    {
        Gate::authorize('view', $device);

        $ports = $device->ports()
            ->with([
                'connectionAsSource.destinationPort.device',
                'connectionAsDestination.sourcePort.device',
                'pairedPort',
            ])
            ->orderBy('label')
            ->get();

        $data = $ports->map(function ($port) {
            // Get the connection (either as source or destination)
            $connection = $port->connectionAsSource ?? $port->connectionAsDestination;
            $connectionData = null;

            if ($connection) {
                // Determine remote port and device based on connection direction
                $isSource = $port->connectionAsSource !== null;
                $remotePort = $isSource ? $connection->destinationPort : $connection->sourcePort;
                $remoteDevice = $remotePort?->device;

                $connectionData = [
                    'id' => $connection->id,
                    'cable_type' => $connection->cable_type?->value,
                    'cable_type_label' => $connection->cable_type?->label(),
                    'cable_color' => $connection->cable_color,
                    'cable_length' => $connection->cable_length,
                    'path_notes' => $connection->path_notes,
                    'remote_port' => $remotePort ? [
                        'id' => $remotePort->id,
                        'label' => $remotePort->label,
                        'type' => $remotePort->type?->value,
                    ] : null,
                    'remote_device' => $remoteDevice ? [
                        'id' => $remoteDevice->id,
                        'name' => $remoteDevice->name,
                        'asset_tag' => $remoteDevice->asset_tag,
                    ] : null,
                ];
            }

            return [
                'id' => $port->id,
                'label' => $port->label,
                'type' => $port->type?->value,
                'type_label' => $port->type?->label(),
                'subtype' => $port->subtype?->value,
                'subtype_label' => $port->subtype?->label(),
                'status' => $port->status?->value,
                'status_label' => $port->status?->label(),
                'direction' => $port->direction?->value,
                'direction_label' => $port->direction?->label(),
                'paired_port_id' => $port->paired_port_id,
                'paired_port_label' => $port->pairedPort?->label,
                'connection' => $connectionData,
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Store a newly created port for the device.
     */
    public function store(StorePortRequest $request, Device $device): JsonResponse
    {
        $validated = $request->validated();

        // Get default direction if not provided
        $type = PortType::from($validated['type']);
        $direction = isset($validated['direction'])
            ? PortDirection::from($validated['direction'])
            : PortDirection::defaultForType($type);

        $port = $device->ports()->create([
            'label' => $validated['label'],
            'type' => $type,
            'subtype' => PortSubtype::from($validated['subtype']),
            'status' => isset($validated['status'])
                ? PortStatus::from($validated['status'])
                : PortStatus::Available,
            'direction' => $direction,
            'position_slot' => $validated['position_slot'] ?? null,
            'position_row' => $validated['position_row'] ?? null,
            'position_column' => $validated['position_column'] ?? null,
            'visual_x' => $validated['visual_x'] ?? null,
            'visual_y' => $validated['visual_y'] ?? null,
            'visual_face' => isset($validated['visual_face'])
                ? PortVisualFace::from($validated['visual_face'])
                : null,
        ]);

        return response()->json([
            'data' => new PortResource($port),
            'message' => 'Port created successfully.',
        ], 201);
    }

    /**
     * Display the specified port.
     */
    public function show(Device $device, Port $port): JsonResponse
    {
        Gate::authorize('view', $device);

        // Verify the port belongs to the device
        if ($port->device_id !== $device->id) {
            abort(404, 'Port not found for this device.');
        }

        $port->load('pairedPort');

        return response()->json([
            'data' => new PortResource($port),
        ]);
    }

    /**
     * Update the specified port.
     */
    public function update(UpdatePortRequest $request, Device $device, Port $port): JsonResponse
    {
        // Verify the port belongs to the device
        if ($port->device_id !== $device->id) {
            abort(404, 'Port not found for this device.');
        }

        $validated = $request->validated();

        $type = PortType::from($validated['type']);

        $port->update([
            'label' => $validated['label'],
            'type' => $type,
            'subtype' => PortSubtype::from($validated['subtype']),
            'status' => isset($validated['status'])
                ? PortStatus::from($validated['status'])
                : $port->status,
            'direction' => isset($validated['direction'])
                ? PortDirection::from($validated['direction'])
                : $port->direction,
            'position_slot' => $validated['position_slot'] ?? $port->position_slot,
            'position_row' => $validated['position_row'] ?? $port->position_row,
            'position_column' => $validated['position_column'] ?? $port->position_column,
            'visual_x' => $validated['visual_x'] ?? $port->visual_x,
            'visual_y' => $validated['visual_y'] ?? $port->visual_y,
            'visual_face' => isset($validated['visual_face'])
                ? PortVisualFace::from($validated['visual_face'])
                : $port->visual_face,
        ]);

        return response()->json([
            'data' => new PortResource($port->fresh()),
            'message' => 'Port updated successfully.',
        ]);
    }

    /**
     * Remove the specified port.
     */
    public function destroy(Device $device, Port $port): JsonResponse
    {
        Gate::authorize('update', $device);

        // Verify the port belongs to the device
        if ($port->device_id !== $device->id) {
            abort(404, 'Port not found for this device.');
        }

        $port->delete();

        return response()->json([
            'message' => 'Port deleted successfully.',
        ]);
    }

    /**
     * Bulk create ports for the device.
     *
     * Creates multiple ports from a template pattern with prefix and number range.
     * All ports are created in a single database transaction for atomicity.
     */
    public function bulk(BulkStorePortRequest $request, Device $device): JsonResponse
    {
        $validated = $request->validated();

        $type = PortType::from($validated['type']);
        $subtype = PortSubtype::from($validated['subtype']);
        $direction = isset($validated['direction'])
            ? PortDirection::from($validated['direction'])
            : PortDirection::defaultForType($type);

        $ports = [];

        DB::transaction(function () use ($device, $validated, $type, $subtype, $direction, &$ports) {
            $prefix = $validated['prefix'];
            $startNumber = (int) $validated['start_number'];
            $endNumber = (int) $validated['end_number'];

            for ($i = $startNumber; $i <= $endNumber; $i++) {
                $port = $device->ports()->create([
                    'label' => $prefix . $i,
                    'type' => $type,
                    'subtype' => $subtype,
                    'status' => PortStatus::Available,
                    'direction' => $direction,
                ]);

                $ports[] = $port;
            }
        });

        return response()->json([
            'data' => PortResource::collection($ports),
            'message' => count($ports) . ' ports created successfully.',
        ], 201);
    }

    /**
     * Pair this port with another port on the same device.
     *
     * Creates a bidirectional pairing between two ports, typically used
     * for patch panel front/back port relationships.
     */
    public function pair(PairPortRequest $request, Device $device, Port $port): JsonResponse
    {
        // Verify the port belongs to the device
        if ($port->device_id !== $device->id) {
            abort(404, 'Port not found for this device.');
        }

        $validated = $request->validated();
        $pairedPortId = $validated['paired_port_id'];

        DB::transaction(function () use ($port, $pairedPortId) {
            // Set bidirectional pairing (A->B and B->A)
            $port->update(['paired_port_id' => $pairedPortId]);
            Port::where('id', $pairedPortId)->update(['paired_port_id' => $port->id]);
        });

        $port->load('pairedPort');

        return response()->json([
            'data' => new PortResource($port->fresh()),
            'message' => 'Ports paired successfully.',
        ]);
    }

    /**
     * Remove the pairing from this port and its paired port.
     *
     * Removes the bidirectional pairing atomically.
     */
    public function unpair(Device $device, Port $port): JsonResponse
    {
        Gate::authorize('update', $device);

        // Verify the port belongs to the device
        if ($port->device_id !== $device->id) {
            abort(404, 'Port not found for this device.');
        }

        // Check if port is actually paired
        if ($port->paired_port_id === null) {
            return response()->json([
                'message' => 'Port is not paired.',
            ], 422);
        }

        $pairedPortId = $port->paired_port_id;

        DB::transaction(function () use ($port, $pairedPortId) {
            // Remove pairing from both ports
            $port->update(['paired_port_id' => null]);
            Port::where('id', $pairedPortId)->update(['paired_port_id' => null]);
        });

        return response()->json([
            'data' => new PortResource($port->fresh()),
            'message' => 'Port pairing removed successfully.',
        ]);
    }

    /**
     * Get port type options for dropdown.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function getTypeOptions(): array
    {
        return collect(PortType::cases())
            ->map(fn (PortType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get port subtype options for dropdown, optionally filtered by type.
     *
     * @return array<int, array{value: string, label: string, type: string}>
     */
    public static function getSubtypeOptions(?PortType $type = null): array
    {
        $subtypes = $type ? PortSubtype::forType($type) : PortSubtype::cases();

        return collect($subtypes)
            ->map(function (PortSubtype $subtype) {
                // Find which type this subtype belongs to
                $parentType = null;
                foreach (PortType::cases() as $type) {
                    if (in_array($subtype, PortSubtype::forType($type), true)) {
                        $parentType = $type;
                        break;
                    }
                }

                return [
                    'value' => $subtype->value,
                    'label' => $subtype->label(),
                    'type' => $parentType?->value,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get port status options for dropdown.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function getStatusOptions(): array
    {
        return collect(PortStatus::cases())
            ->map(fn (PortStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get port direction options for dropdown, optionally filtered by type.
     *
     * @return array<int, array{value: string, label: string, type: string|null}>
     */
    public static function getDirectionOptions(?PortType $type = null): array
    {
        $directions = $type ? PortDirection::forType($type) : PortDirection::cases();

        return collect($directions)
            ->map(function (PortDirection $direction) {
                // Determine which types this direction is valid for
                $validForTypes = [];
                foreach (PortType::cases() as $type) {
                    if (in_array($direction, PortDirection::forType($type), true)) {
                        $validForTypes[] = $type->value;
                    }
                }

                return [
                    'value' => $direction->value,
                    'label' => $direction->label(),
                    'types' => $validForTypes,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get port visual face options for dropdown.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function getVisualFaceOptions(): array
    {
        return collect(PortVisualFace::cases())
            ->map(fn (PortVisualFace $face) => [
                'value' => $face->value,
                'label' => $face->label(),
            ])
            ->values()
            ->toArray();
    }
}
