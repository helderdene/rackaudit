<?php

namespace App\Http\Controllers;

use App\Enums\CableType;
use App\Enums\PortStatus;
use App\Enums\PortType;
use App\Http\Requests\StoreConnectionRequest;
use App\Http\Requests\UpdateConnectionRequest;
use App\Http\Resources\ConnectionDiagramResource;
use App\Http\Resources\ConnectionResource;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for managing connections between device ports.
 *
 * Connections track physical cable links between ports with cable
 * properties (type, length, color) and support logical path derivation
 * through patch panel port pairs. This controller returns JSON responses only.
 */
class ConnectionController extends Controller
{
    /**
     * Display a listing of connections.
     *
     * Supports filtering by device_id, rack_id, and port_type.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Connection::class);

        $query = Connection::with(['sourcePort.device', 'destinationPort.device']);

        // Filter by device ID
        if ($request->has('device_id')) {
            $deviceId = $request->input('device_id');
            $query->whereHas('sourcePort', function ($q) use ($deviceId) {
                $q->where('device_id', $deviceId);
            })->orWhereHas('destinationPort', function ($q) use ($deviceId) {
                $q->where('device_id', $deviceId);
            });
        }

        // Filter by rack ID
        if ($request->has('rack_id')) {
            $rackId = $request->input('rack_id');
            $query->whereHas('sourcePort.device', function ($q) use ($rackId) {
                $q->where('rack_id', $rackId);
            })->orWhereHas('destinationPort.device', function ($q) use ($rackId) {
                $q->where('rack_id', $rackId);
            });
        }

        // Filter by port type
        if ($request->has('port_type')) {
            $portType = $request->input('port_type');
            $query->whereHas('sourcePort', function ($q) use ($portType) {
                $q->where('type', $portType);
            });
        }

        $connections = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'data' => ConnectionResource::collection($connections),
        ]);
    }

    /**
     * Display the connection diagram page with Inertia.
     *
     * Provides all necessary props for the diagram page including filter options.
     */
    public function diagramPage(Request $request): Response
    {
        Gate::authorize('viewAny', Connection::class);

        return Inertia::render('Connections/Diagram', [
            'filterOptions' => $this->getFilterOptions(),
            'deviceTypes' => $this->getDeviceTypeOptions(),
            'portTypeOptions' => $this->getPortTypeOptions(),
            'cableTypeOptions' => self::getCableTypeOptions(),
            'initialFilters' => $this->getInitialFilters($request),
        ]);
    }

    /**
     * Get connection data formatted for diagram visualization.
     *
     * Returns nodes and edges optimized for graph visualization.
     * Supports hierarchical filtering and automatic aggregation:
     * - Device/Rack filter: Shows device-to-device connections
     * - Datacenter/Room/Row filter: Shows rack-to-rack connections
     */
    public function diagram(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Connection::class);

        $query = Connection::with([
            'sourcePort.device.deviceType',
            'sourcePort.device.rack.row.room.datacenter',
            'destinationPort.device.deviceType',
            'destinationPort.device.rack.row.room.datacenter',
        ]);

        // Determine aggregation level based on filter depth
        $aggregationLevel = $this->determineAggregationLevel($request);

        // Get allowed rack IDs for rack-level filtering
        $allowedRackIds = $this->getAllowedRackIds($request);

        // Filter by specific device_id (most specific - shows only connections for that device)
        if ($request->has('device_id')) {
            $deviceId = $request->input('device_id');
            $query->where(function ($q) use ($deviceId) {
                $q->whereHas('sourcePort', function ($q) use ($deviceId) {
                    $q->where('device_id', $deviceId);
                })->orWhereHas('destinationPort', function ($q) use ($deviceId) {
                    $q->where('device_id', $deviceId);
                });
            });
        } else {
            // Apply hierarchical location filters only if no device_id specified
            $this->applyHierarchicalFilters($query, $request);
        }

        // Filter by port type (Ethernet, Fiber, Power)
        if ($request->has('port_type')) {
            $portType = $request->input('port_type');
            $query->whereHas('sourcePort', function ($q) use ($portType) {
                $q->where('type', $portType);
            });
        }

        // Filter by device type
        if ($request->has('device_type_id')) {
            $deviceTypeId = $request->input('device_type_id');
            $query->where(function ($q) use ($deviceTypeId) {
                $q->whereHas('sourcePort.device', function ($q) use ($deviceTypeId) {
                    $q->where('device_type_id', $deviceTypeId);
                })->whereHas('destinationPort.device', function ($q) use ($deviceTypeId) {
                    $q->where('device_type_id', $deviceTypeId);
                });
            });
        }

        // Filter by verified status
        if ($request->has('verified')) {
            // Currently all connections are considered verified
            // This filter is a placeholder for future verification tracking
        }

        $connections = $query->orderBy('created_at', 'desc')->get();

        $diagramData = new ConnectionDiagramResource($connections, $aggregationLevel, $allowedRackIds);

        return response()->json([
            'data' => $diagramData->toArray(),
        ]);
    }

    /**
     * Get allowed rack IDs based on hierarchical filters.
     * Used to constrain which racks appear in rack-level aggregation.
     *
     * @return \Illuminate\Support\Collection|null
     */
    protected function getAllowedRackIds(Request $request)
    {
        // For device or rack level filtering, no constraint needed
        if ($request->has('device_id') || $request->has('rack_id')) {
            return null;
        }

        // Filter by row_id
        if ($request->has('row_id')) {
            $rowId = $request->input('row_id');

            return Rack::where('row_id', $rowId)->pluck('id');
        }

        // Filter by room_id
        if ($request->has('room_id')) {
            $roomId = $request->input('room_id');
            $rowIds = Row::where('room_id', $roomId)->pluck('id');

            return Rack::whereIn('row_id', $rowIds)->pluck('id');
        }

        // Filter by datacenter_id
        if ($request->has('datacenter_id')) {
            $datacenterId = $request->input('datacenter_id');
            $roomIds = Room::where('datacenter_id', $datacenterId)->pluck('id');
            $rowIds = Row::whereIn('room_id', $roomIds)->pluck('id');

            return Rack::whereIn('row_id', $rowIds)->pluck('id');
        }

        // No filter - allow all racks
        return null;
    }

    /**
     * Determine the aggregation level based on filter depth.
     *
     * - device_id or rack_id: Show device-to-device connections
     * - datacenter_id, room_id, or row_id: Show rack-to-rack connections
     * - No filter: Show rack-to-rack connections (high-level overview)
     */
    protected function determineAggregationLevel(Request $request): string
    {
        // Device or rack filter → show device-level connections
        if ($request->has('device_id') || $request->has('rack_id')) {
            return 'device';
        }

        // Datacenter, room, or row filter → show rack-level connections
        // No filter also defaults to rack level for a high-level overview
        return 'rack';
    }

    /**
     * Apply hierarchical location filters to connection query.
     *
     * Supports filtering by datacenter_id, room_id, row_id, rack_id.
     * Implements cascading filter logic consistent with HierarchicalPortSelector.
     */
    protected function applyHierarchicalFilters($query, Request $request): void
    {
        // Filter by rack_id (most specific)
        if ($request->has('rack_id')) {
            $rackId = $request->input('rack_id');
            $query->where(function ($q) use ($rackId) {
                $q->whereHas('sourcePort.device', function ($q) use ($rackId) {
                    $q->where('rack_id', $rackId);
                })->orWhereHas('destinationPort.device', function ($q) use ($rackId) {
                    $q->where('rack_id', $rackId);
                });
            });

            return;
        }

        // Filter by row_id
        if ($request->has('row_id')) {
            $rowId = $request->input('row_id');
            $rackIds = Rack::where('row_id', $rowId)->pluck('id');
            $query->where(function ($q) use ($rackIds) {
                $q->whereHas('sourcePort.device', function ($q) use ($rackIds) {
                    $q->whereIn('rack_id', $rackIds);
                })->orWhereHas('destinationPort.device', function ($q) use ($rackIds) {
                    $q->whereIn('rack_id', $rackIds);
                });
            });

            return;
        }

        // Filter by room_id
        if ($request->has('room_id')) {
            $roomId = $request->input('room_id');
            $rowIds = Row::where('room_id', $roomId)->pluck('id');
            $rackIds = Rack::whereIn('row_id', $rowIds)->pluck('id');
            $query->where(function ($q) use ($rackIds) {
                $q->whereHas('sourcePort.device', function ($q) use ($rackIds) {
                    $q->whereIn('rack_id', $rackIds);
                })->orWhereHas('destinationPort.device', function ($q) use ($rackIds) {
                    $q->whereIn('rack_id', $rackIds);
                });
            });

            return;
        }

        // Filter by datacenter_id (least specific)
        if ($request->has('datacenter_id')) {
            $datacenterId = $request->input('datacenter_id');
            $roomIds = Room::where('datacenter_id', $datacenterId)->pluck('id');
            $rowIds = Row::whereIn('room_id', $roomIds)->pluck('id');
            $rackIds = Rack::whereIn('row_id', $rowIds)->pluck('id');
            $query->where(function ($q) use ($rackIds) {
                $q->whereHas('sourcePort.device', function ($q) use ($rackIds) {
                    $q->whereIn('rack_id', $rackIds);
                })->orWhereHas('destinationPort.device', function ($q) use ($rackIds) {
                    $q->whereIn('rack_id', $rackIds);
                });
            });
        }
    }

    /**
     * Roles that have full access to all datacenters.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Get hierarchical filter options for the diagram page.
     *
     * Filters options based on user's assigned datacenters.
     * Administrators and IT Managers see all datacenters.
     *
     * @return array{datacenters: array, rooms: array, rows: array, racks: array}
     */
    protected function getFilterOptions(): array
    {
        $user = auth()->user();
        $hasFullAccess = $user->hasAnyRole(self::ADMIN_ROLES);

        // Get accessible datacenter IDs
        $accessibleDatacenterIds = $hasFullAccess
            ? null
            : $user->datacenters()->pluck('datacenters.id');

        // Filter datacenters
        $datacenterQuery = Datacenter::orderBy('name');
        if (! $hasFullAccess) {
            $datacenterQuery->whereIn('id', $accessibleDatacenterIds);
        }
        $datacenters = $datacenterQuery->get()
            ->map(fn ($dc) => [
                'value' => $dc->id,
                'label' => $dc->name,
            ])
            ->values()
            ->toArray();

        // Filter rooms by accessible datacenters
        $roomQuery = Room::orderBy('name');
        if (! $hasFullAccess) {
            $roomQuery->whereIn('datacenter_id', $accessibleDatacenterIds);
        }
        $rooms = $roomQuery->get()
            ->map(fn ($room) => [
                'value' => $room->id,
                'label' => $room->name,
                'datacenter_id' => $room->datacenter_id,
            ])
            ->values()
            ->toArray();

        // Get accessible room IDs for row filtering
        $accessibleRoomIds = collect($rooms)->pluck('value');

        // Filter rows by accessible rooms
        $rowQuery = Row::orderBy('name');
        if (! $hasFullAccess) {
            $rowQuery->whereIn('room_id', $accessibleRoomIds);
        }
        $rows = $rowQuery->get()
            ->map(fn ($row) => [
                'value' => $row->id,
                'label' => $row->name,
                'room_id' => $row->room_id,
            ])
            ->values()
            ->toArray();

        // Get accessible row IDs for rack filtering
        $accessibleRowIds = collect($rows)->pluck('value');

        // Filter racks by accessible rows
        $rackQuery = Rack::orderBy('name');
        if (! $hasFullAccess) {
            $rackQuery->whereIn('row_id', $accessibleRowIds);
        }
        $racks = $rackQuery->get()
            ->map(fn ($rack) => [
                'value' => $rack->id,
                'label' => $rack->name,
                'row_id' => $rack->row_id,
            ])
            ->values()
            ->toArray();

        return [
            'datacenters' => $datacenters,
            'rooms' => $rooms,
            'rows' => $rows,
            'racks' => $racks,
        ];
    }

    /**
     * Get device type options for filtering.
     *
     * @return array<int, array{value: int, label: string}>
     */
    protected function getDeviceTypeOptions(): array
    {
        return DeviceType::orderBy('name')
            ->get()
            ->map(fn ($type) => [
                'value' => $type->id,
                'label' => $type->name,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get port type options from PortType enum.
     *
     * @return array<int, array{value: string, label: string}>
     */
    protected function getPortTypeOptions(): array
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
     * Get initial filter values from request query parameters.
     *
     * @return array<string, mixed>
     */
    protected function getInitialFilters(Request $request): array
    {
        return [
            'datacenter_id' => $request->input('datacenter_id') ? (int) $request->input('datacenter_id') : null,
            'room_id' => $request->input('room_id') ? (int) $request->input('room_id') : null,
            'row_id' => $request->input('row_id') ? (int) $request->input('row_id') : null,
            'rack_id' => $request->input('rack_id') ? (int) $request->input('rack_id') : null,
            'device_id' => $request->input('device_id') ? (int) $request->input('device_id') : null,
            'device_type_id' => $request->input('device_type_id') ? (int) $request->input('device_type_id') : null,
            'port_type' => $request->input('port_type'),
            'verified' => $request->has('verified') ? (bool) $request->input('verified') : null,
        ];
    }

    /**
     * Store a newly created connection.
     *
     * Creates the connection and updates both port statuses to Connected.
     */
    public function store(StoreConnectionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $connection = DB::transaction(function () use ($validated) {
            // Create the connection
            $connection = Connection::create([
                'source_port_id' => $validated['source_port_id'],
                'destination_port_id' => $validated['destination_port_id'],
                'cable_type' => CableType::from($validated['cable_type']),
                'cable_length' => $validated['cable_length'],
                'cable_color' => $validated['cable_color'] ?? null,
                'path_notes' => $validated['path_notes'] ?? null,
            ]);

            // Update source port status to Connected
            Port::where('id', $validated['source_port_id'])
                ->update(['status' => PortStatus::Connected]);

            // Update destination port status to Connected
            Port::where('id', $validated['destination_port_id'])
                ->update(['status' => PortStatus::Connected]);

            return $connection;
        });

        $connection->load(['sourcePort.device', 'destinationPort.device']);

        return response()->json([
            'data' => new ConnectionResource($connection),
            'message' => 'Connection created successfully.',
        ], 201);
    }

    /**
     * Display the specified connection.
     */
    public function show(Connection $connection): Response
    {
        Gate::authorize('view', $connection);

        $connection->load(['sourcePort.device.rack', 'destinationPort.device.rack']);

        return Inertia::render('Connections/Show', [
            'connection' => [
                'id' => $connection->id,
                'cable_type' => $connection->cable_type->value,
                'cable_type_label' => $connection->cable_type->label(),
                'cable_length' => $connection->cable_length,
                'cable_color' => $connection->cable_color,
                'path_notes' => $connection->path_notes,
                'created_at' => $connection->created_at->toISOString(),
                'updated_at' => $connection->updated_at->toISOString(),
                'source_port' => [
                    'id' => $connection->sourcePort->id,
                    'label' => $connection->sourcePort->label,
                    'type' => $connection->sourcePort->type->value,
                    'type_label' => $connection->sourcePort->type->label(),
                    'status' => $connection->sourcePort->status->value,
                    'status_label' => $connection->sourcePort->status->label(),
                ],
                'destination_port' => [
                    'id' => $connection->destinationPort->id,
                    'label' => $connection->destinationPort->label,
                    'type' => $connection->destinationPort->type->value,
                    'type_label' => $connection->destinationPort->type->label(),
                    'status' => $connection->destinationPort->status->value,
                    'status_label' => $connection->destinationPort->status->label(),
                ],
                'source_device' => [
                    'id' => $connection->sourcePort->device->id,
                    'name' => $connection->sourcePort->device->name,
                    'asset_tag' => $connection->sourcePort->device->asset_tag,
                    'rack_id' => $connection->sourcePort->device->rack_id,
                    'rack_name' => $connection->sourcePort->device->rack?->name,
                ],
                'destination_device' => [
                    'id' => $connection->destinationPort->device->id,
                    'name' => $connection->destinationPort->device->name,
                    'asset_tag' => $connection->destinationPort->device->asset_tag,
                    'rack_id' => $connection->destinationPort->device->rack_id,
                    'rack_name' => $connection->destinationPort->device->rack?->name,
                ],
            ],
        ]);
    }

    /**
     * Update the specified connection.
     *
     * Only cable properties can be updated. Source and destination
     * ports cannot be changed after creation.
     */
    public function update(UpdateConnectionRequest $request, Connection $connection): JsonResponse
    {
        $validated = $request->validated();

        $updateData = [];

        if (isset($validated['cable_type'])) {
            $updateData['cable_type'] = CableType::from($validated['cable_type']);
        }

        if (isset($validated['cable_length'])) {
            $updateData['cable_length'] = $validated['cable_length'];
        }

        if (array_key_exists('cable_color', $validated)) {
            $updateData['cable_color'] = $validated['cable_color'];
        }

        if (array_key_exists('path_notes', $validated)) {
            $updateData['path_notes'] = $validated['path_notes'];
        }

        $connection->update($updateData);

        $connection->load(['sourcePort.device', 'destinationPort.device']);

        return response()->json([
            'data' => new ConnectionResource($connection->fresh()),
            'message' => 'Connection updated successfully.',
        ]);
    }

    /**
     * Remove the specified connection.
     *
     * Soft deletes the connection and updates both port statuses to Available.
     */
    public function destroy(Connection $connection): JsonResponse
    {
        Gate::authorize('delete', $connection);

        DB::transaction(function () use ($connection) {
            // Store port IDs before deletion
            $sourcePortId = $connection->source_port_id;
            $destPortId = $connection->destination_port_id;

            // Soft delete the connection
            $connection->delete();

            // Update source port status to Available
            Port::where('id', $sourcePortId)
                ->update(['status' => PortStatus::Available]);

            // Update destination port status to Available
            Port::where('id', $destPortId)
                ->update(['status' => PortStatus::Available]);
        });

        return response()->json([
            'message' => 'Connection deleted successfully.',
        ]);
    }

    /**
     * Get cable type options for dropdown.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function getCableTypeOptions(): array
    {
        return collect(CableType::cases())
            ->map(fn (CableType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])
            ->values()
            ->toArray();
    }
}
