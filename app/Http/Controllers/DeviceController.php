<?php

namespace App\Http\Controllers;

use App\Enums\CableType;
use App\Enums\DeviceDepth;
use App\Enums\DeviceLifecycleStatus;
use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Http\Requests\PlaceDeviceRequest;
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Http\Resources\PortResource;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller for managing datacenter devices/assets.
 *
 * Devices can be placed within racks via the elevation system or
 * exist as unplaced inventory items. Supports both global and
 * rack-scoped views for device management.
 */
class DeviceController extends Controller
{
    /**
     * Roles that have full access to all devices.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Display a listing of devices.
     * Supports filtering by rack_id, lifecycle_status, and search.
     */
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        Gate::authorize('viewAny', Device::class);

        $query = Device::query()
            ->with(['deviceType', 'rack'])
            ->orderBy('name');

        // Filter by rack_id if provided
        if ($request->has('rack_id')) {
            $rackId = $request->input('rack_id');
            if ($rackId === null || $rackId === 'null') {
                // Get unplaced devices
                $query->whereNull('rack_id');
            } else {
                $query->where('rack_id', $rackId);
            }
        }

        // Filter by lifecycle_status if provided
        if ($request->filled('lifecycle_status')) {
            $query->where('lifecycle_status', $request->input('lifecycle_status'));
        }

        // Search by name, asset_tag, or serial_number
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('asset_tag', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        // Return JSON for API requests, Inertia for web requests
        if ($request->wantsJson() || $request->is('api/*')) {
            $devices = $query->get();

            return response()->json([
                'data' => DeviceResource::collection($devices),
            ]);
        }

        $devices = $query->paginate(15)->withQueryString();

        return Inertia::render('Devices/Index', [
            'devices' => [
                'data' => DeviceResource::collection($devices)->resolve(),
                'links' => $devices->linkCollection()->toArray(),
                'current_page' => $devices->currentPage(),
                'last_page' => $devices->lastPage(),
                'per_page' => $devices->perPage(),
                'total' => $devices->total(),
            ],
            'lifecycleStatusOptions' => $this->getLifecycleStatusOptions(),
            'filters' => [
                'search' => $request->input('search', ''),
                'lifecycle_status' => $request->input('lifecycle_status', ''),
            ],
            'canCreate' => Gate::allows('create', Device::class),
        ]);
    }

    /**
     * Show the form for creating a new device.
     */
    public function create(Request $request): InertiaResponse
    {
        Gate::authorize('create', Device::class);

        return Inertia::render('Devices/Create', [
            'deviceTypeOptions' => $this->getDeviceTypeOptions(),
            'lifecycleStatusOptions' => $this->getLifecycleStatusOptions(),
            'depthOptions' => $this->getDepthOptions(),
            'widthTypeOptions' => $this->getWidthTypeOptions(),
            'rackFaceOptions' => $this->getRackFaceOptions(),
        ]);
    }

    /**
     * Store a newly created device.
     */
    public function store(StoreDeviceRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $device = Device::create([
            'name' => $validated['name'],
            'device_type_id' => $validated['device_type_id'],
            'lifecycle_status' => $validated['lifecycle_status'],
            'serial_number' => $validated['serial_number'] ?? null,
            'manufacturer' => $validated['manufacturer'] ?? null,
            'model' => $validated['model'] ?? null,
            'purchase_date' => $validated['purchase_date'] ?? null,
            'warranty_start_date' => $validated['warranty_start_date'] ?? null,
            'warranty_end_date' => $validated['warranty_end_date'] ?? null,
            'u_height' => $validated['u_height'],
            'depth' => $validated['depth'],
            'width_type' => $validated['width_type'],
            'rack_face' => $validated['rack_face'],
            'rack_id' => $validated['rack_id'] ?? null,
            'start_u' => $validated['start_u'] ?? null,
            'specs' => $validated['specs'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($request->wantsJson()) {
            $device->load(['deviceType', 'rack']);

            return response()->json([
                'data' => new DeviceResource($device),
                'message' => 'Device created successfully.',
            ], 201);
        }

        return redirect()->route('devices.index')
            ->with('success', 'Device created successfully.');
    }

    /**
     * Display the specified device.
     *
     * Includes ports data with connection information, port enum options,
     * hierarchical filter options, and cable type options for the
     * PortsSection and connection dialog components.
     */
    public function show(Request $request, Device $device): InertiaResponse|JsonResponse
    {
        Gate::authorize('view', $device);

        $device->load(['deviceType', 'rack.row.room', 'ports']);

        if ($request->wantsJson()) {
            return response()->json([
                'data' => new DeviceResource($device),
            ]);
        }

        // Load ports with connection data eagerly loaded
        $ports = $device->ports()
            ->with([
                'connectionAsSource.destinationPort.device',
                'connectionAsDestination.sourcePort.device',
            ])
            ->orderBy('label')
            ->get();

        return Inertia::render('Devices/Show', [
            'device' => (new DeviceResource($device))->resolve(),
            'canEdit' => Gate::allows('update', $device),
            'canDelete' => Gate::allows('delete', $device),
            'ports' => PortResource::collection($ports)->resolve(),
            'portTypeOptions' => PortController::getTypeOptions(),
            'portSubtypeOptions' => PortController::getSubtypeOptions(),
            'portStatusOptions' => PortController::getStatusOptions(),
            'portDirectionOptions' => PortController::getDirectionOptions(),
            'filterOptions' => $this->getConnectionFilterOptions(),
            'cableTypeOptions' => $this->getCableTypeOptions(),
        ]);
    }

    /**
     * Show the form for editing the specified device.
     */
    public function edit(Device $device): InertiaResponse
    {
        Gate::authorize('update', $device);

        $device->load(['deviceType', 'rack']);

        return Inertia::render('Devices/Edit', [
            'device' => (new DeviceResource($device))->resolve(),
            'deviceTypeOptions' => $this->getDeviceTypeOptions(),
            'lifecycleStatusOptions' => $this->getLifecycleStatusOptions(),
            'depthOptions' => $this->getDepthOptions(),
            'widthTypeOptions' => $this->getWidthTypeOptions(),
            'rackFaceOptions' => $this->getRackFaceOptions(),
        ]);
    }

    /**
     * Update the specified device.
     * Note: asset_tag is immutable and cannot be changed.
     */
    public function update(UpdateDeviceRequest $request, Device $device): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $device->update([
            'name' => $validated['name'],
            'device_type_id' => $validated['device_type_id'],
            'lifecycle_status' => $validated['lifecycle_status'],
            'serial_number' => $validated['serial_number'] ?? null,
            'manufacturer' => $validated['manufacturer'] ?? null,
            'model' => $validated['model'] ?? null,
            'purchase_date' => $validated['purchase_date'] ?? null,
            'warranty_start_date' => $validated['warranty_start_date'] ?? null,
            'warranty_end_date' => $validated['warranty_end_date'] ?? null,
            'u_height' => $validated['u_height'],
            'depth' => $validated['depth'],
            'width_type' => $validated['width_type'],
            'rack_face' => $validated['rack_face'],
            'rack_id' => $validated['rack_id'] ?? null,
            'start_u' => $validated['start_u'] ?? null,
            'specs' => $validated['specs'] ?? $device->specs,
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($request->wantsJson()) {
            $device->load(['deviceType', 'rack']);

            return response()->json([
                'data' => new DeviceResource($device),
                'message' => 'Device updated successfully.',
            ]);
        }

        return redirect()->route('devices.index')
            ->with('success', 'Device updated successfully.');
    }

    /**
     * Remove the specified device.
     */
    public function destroy(Request $request, Device $device): RedirectResponse|JsonResponse
    {
        Gate::authorize('delete', $device);

        $device->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Device deleted successfully.',
            ]);
        }

        return redirect()->route('devices.index')
            ->with('success', 'Device deleted successfully.');
    }

    /**
     * Place a device in a rack at a specific position.
     *
     * Validates collision detection to prevent overlapping devices.
     */
    public function place(PlaceDeviceRequest $request, Device $device): JsonResponse
    {
        $validated = $request->validated();

        $device->update([
            'rack_id' => $validated['rack_id'],
            'start_u' => $validated['start_u'],
            'rack_face' => $validated['face'],
            'width_type' => $validated['width_type'],
        ]);

        $device->load(['deviceType', 'rack']);

        return response()->json([
            'data' => new DeviceResource($device),
            'message' => 'Device placed successfully.',
        ]);
    }

    /**
     * Remove a device from its rack (unplace).
     *
     * Clears rack_id and start_u fields, returning the device to inventory.
     */
    public function unplace(Device $device): JsonResponse
    {
        Gate::authorize('update', $device);

        $device->update([
            'rack_id' => null,
            'start_u' => null,
        ]);

        $device->load(['deviceType', 'rack']);

        return response()->json([
            'data' => new DeviceResource($device),
            'message' => 'Device removed from rack successfully.',
        ]);
    }

    /**
     * Get device type options for dropdown.
     *
     * @return array<int, array{id: int, name: string, default_u_size: int}>
     */
    private function getDeviceTypeOptions(): array
    {
        return DeviceType::query()
            ->orderBy('name')
            ->get()
            ->map(fn (DeviceType $dt) => [
                'id' => $dt->id,
                'name' => $dt->name,
                'default_u_size' => $dt->default_u_size,
            ])
            ->toArray();
    }

    /**
     * Get lifecycle status options for dropdown.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getLifecycleStatusOptions(): array
    {
        return collect(DeviceLifecycleStatus::cases())
            ->map(fn (DeviceLifecycleStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get depth options for dropdown.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getDepthOptions(): array
    {
        return collect(DeviceDepth::cases())
            ->map(fn (DeviceDepth $d) => [
                'value' => $d->value,
                'label' => $d->label(),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get width type options for dropdown.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getWidthTypeOptions(): array
    {
        return collect(DeviceWidthType::cases())
            ->map(fn (DeviceWidthType $w) => [
                'value' => $w->value,
                'label' => $w->label(),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get rack face options for dropdown.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getRackFaceOptions(): array
    {
        return collect(DeviceRackFace::cases())
            ->map(fn (DeviceRackFace $f) => [
                'value' => $f->value,
                'label' => $f->label(),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get hierarchical filter options for connection dialogs.
     *
     * Returns datacenters, rooms, rows, and racks with parent references
     * for cascading filter functionality.
     *
     * @return array<string, array<int, array{value: int, label: string}>>
     */
    private function getConnectionFilterOptions(): array
    {
        return [
            'datacenters' => Datacenter::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Datacenter $dc) => [
                    'value' => $dc->id,
                    'label' => $dc->name,
                ])
                ->toArray(),
            'rooms' => Room::query()
                ->with('datacenter')
                ->orderBy('name')
                ->get()
                ->map(fn (Room $room) => [
                    'value' => $room->id,
                    'label' => $room->name,
                    'datacenter_id' => $room->datacenter_id,
                ])
                ->toArray(),
            'rows' => Row::query()
                ->with('room')
                ->orderBy('name')
                ->get()
                ->map(fn (Row $row) => [
                    'value' => $row->id,
                    'label' => $row->name,
                    'room_id' => $row->room_id,
                    'datacenter_id' => $row->room?->datacenter_id,
                ])
                ->toArray(),
            'racks' => Rack::query()
                ->with('row.room')
                ->orderBy('name')
                ->get()
                ->map(fn (Rack $rack) => [
                    'value' => $rack->id,
                    'label' => $rack->name,
                    'row_id' => $rack->row_id,
                    'room_id' => $rack->row?->room_id,
                    'datacenter_id' => $rack->row?->room?->datacenter_id,
                ])
                ->toArray(),
        ];
    }

    /**
     * Get cable type options for connection dialogs.
     *
     * Returns all cable types with their labels and compatible port types.
     *
     * @return array<int, array{value: string, label: string, port_types: array<string>}>
     */
    private function getCableTypeOptions(): array
    {
        return collect(CableType::cases())
            ->map(fn (CableType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'port_types' => $this->getPortTypesForCableType($type),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get the port types compatible with a given cable type.
     *
     * @return array<string>
     */
    private function getPortTypesForCableType(CableType $cableType): array
    {
        return match ($cableType) {
            CableType::Cat5e, CableType::Cat6, CableType::Cat6a => ['ethernet'],
            CableType::FiberSm, CableType::FiberMm => ['fiber'],
            CableType::PowerC13, CableType::PowerC14, CableType::PowerC19, CableType::PowerC20 => ['power'],
        };
    }
}
