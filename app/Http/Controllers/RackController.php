<?php

namespace App\Http\Controllers;

use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Http\Requests\StoreRackRequest;
use App\Http\Requests\UpdateRackRequest;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Pdu;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller for managing racks within the datacenter hierarchy.
 *
 * Racks are nested under Datacenter > Room > Row and support
 * many-to-many PDU relationships for power distribution.
 */
class RackController extends Controller
{
    /**
     * Roles that have full access to all racks.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Display a listing of all racks across all datacenters.
     */
    public function allRacks(Request $request): InertiaResponse
    {
        Gate::authorize('viewAny', Rack::class);

        $query = Rack::query()
            ->with(['row.room.datacenter'])
            ->orderBy('name');

        // Filter by status if provided
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Search by name or serial number
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        $racks = $query->paginate(15)->withQueryString();

        $racksData = $racks->through(function (Rack $rack) {
            return [
                'id' => $rack->id,
                'name' => $rack->name,
                'position' => $rack->position,
                'u_height' => $rack->u_height?->value,
                'u_height_label' => $rack->u_height?->label(),
                'serial_number' => $rack->serial_number,
                'status' => $rack->status?->value,
                'status_label' => $rack->status?->label(),
                'pdu_count' => $rack->pdus()->count(),
                'device_count' => $rack->devices()->count(),
                'location' => $rack->row ? [
                    'datacenter_id' => $rack->row->room->datacenter->id,
                    'datacenter_name' => $rack->row->room->datacenter->name,
                    'room_id' => $rack->row->room->id,
                    'room_name' => $rack->row->room->name,
                    'row_id' => $rack->row->id,
                    'row_name' => $rack->row->name,
                ] : null,
            ];
        });

        return Inertia::render('Racks/All', [
            'racks' => [
                'data' => $racksData->items(),
                'links' => $racks->linkCollection()->toArray(),
                'current_page' => $racks->currentPage(),
                'last_page' => $racks->lastPage(),
                'per_page' => $racks->perPage(),
                'total' => $racks->total(),
            ],
            'statusOptions' => collect(RackStatus::cases())->map(fn (RackStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->values()->all(),
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
            ],
            'canCreate' => $request->user()->hasAnyRole(self::ADMIN_ROLES),
        ]);
    }

    /**
     * Display a listing of racks for a row.
     */
    public function index(Request $request, Datacenter $datacenter, Room $room, Row $row): InertiaResponse
    {
        Gate::authorize('viewAny', Rack::class);

        $racks = $row->racks()
            ->orderBy('position')
            ->get()
            ->map(function (Rack $rack) {
                return [
                    'id' => $rack->id,
                    'name' => $rack->name,
                    'position' => $rack->position,
                    'u_height' => $rack->u_height?->value,
                    'u_height_label' => $rack->u_height?->label(),
                    'serial_number' => $rack->serial_number,
                    'status' => $rack->status?->value,
                    'status_label' => $rack->status?->label(),
                    'pdu_count' => $rack->pdus()->count(),
                ];
            });

        return Inertia::render('Racks/Index', [
            'datacenter' => [
                'id' => $datacenter->id,
                'name' => $datacenter->name,
            ],
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
            ],
            'row' => [
                'id' => $row->id,
                'name' => $row->name,
            ],
            'racks' => $racks,
            'canCreate' => $request->user()->hasAnyRole(self::ADMIN_ROLES),
            'statusOptions' => collect(RackStatus::cases())->map(fn (RackStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->values()->all(),
            'uHeightOptions' => collect(RackUHeight::cases())->map(fn (RackUHeight $h) => [
                'value' => $h->value,
                'label' => $h->label(),
            ])->values()->all(),
        ]);
    }

    /**
     * Show the form for creating a new rack.
     */
    public function create(Datacenter $datacenter, Room $room, Row $row): InertiaResponse
    {
        Gate::authorize('create', Rack::class);

        // Calculate next position
        $nextPosition = ($row->racks()->max('position') ?? 0) + 1;

        // Query available PDUs from same room (room-level and row-level from same row)
        $pduOptions = $this->getAvailablePdus($room, $row);

        return Inertia::render('Racks/Create', [
            'datacenter' => [
                'id' => $datacenter->id,
                'name' => $datacenter->name,
            ],
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
            ],
            'row' => [
                'id' => $row->id,
                'name' => $row->name,
            ],
            'nextPosition' => $nextPosition,
            'statusOptions' => collect(RackStatus::cases())->map(fn (RackStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->values()->all(),
            'uHeightOptions' => collect(RackUHeight::cases())->map(fn (RackUHeight $h) => [
                'value' => $h->value,
                'label' => $h->label(),
            ])->values()->all(),
            'pduOptions' => $pduOptions,
        ]);
    }

    /**
     * Store a newly created rack.
     */
    public function store(StoreRackRequest $request, Datacenter $datacenter, Room $room, Row $row): RedirectResponse
    {
        $validated = $request->validated();

        $rack = Rack::create([
            'name' => $validated['name'],
            'position' => $validated['position'],
            'u_height' => $validated['u_height'],
            'serial_number' => $validated['serial_number'] ?? null,
            'status' => $validated['status'],
            'row_id' => $row->id,
            'manufacturer' => $validated['manufacturer'] ?? null,
            'model' => $validated['model'] ?? null,
            'depth' => $validated['depth'] ?? null,
            'installation_date' => $validated['installation_date'] ?? null,
            'location_notes' => $validated['location_notes'] ?? null,
            'specs' => $validated['specs'] ?? null,
        ]);

        // Sync PDU relationships if provided
        if (isset($validated['pdu_ids'])) {
            $rack->pdus()->sync($validated['pdu_ids']);
        }

        return redirect()->route('datacenters.rooms.rows.show', [$datacenter, $room, $row])
            ->with('success', 'Rack created successfully.');
    }

    /**
     * Display the specified rack.
     */
    public function show(Request $request, Datacenter $datacenter, Room $room, Row $row, Rack $rack): InertiaResponse
    {
        Gate::authorize('view', $rack);

        $user = $request->user();

        // Eager load devices with deviceType for the device list
        $rack->load(['devices.deviceType', 'pdus']);

        $pdus = $rack->pdus
            ->map(function (Pdu $pdu) {
                return [
                    'id' => $pdu->id,
                    'name' => $pdu->name,
                    'model' => $pdu->model,
                    'manufacturer' => $pdu->manufacturer,
                    'total_capacity_kw' => $pdu->total_capacity_kw,
                    'voltage' => $pdu->voltage,
                    'phase' => $pdu->phase?->value,
                    'phase_label' => $pdu->phase?->label(),
                    'circuit_count' => $pdu->circuit_count,
                    'status' => $pdu->status?->value,
                    'status_label' => $pdu->status?->label(),
                ];
            });

        // Format devices for device list table, sorted by start_u descending
        $devices = $rack->devices
            ->sortByDesc('start_u')
            ->values()
            ->map(function (Device $device) {
                return [
                    'id' => $device->id,
                    'name' => $device->name,
                    'type' => $device->deviceType?->name ?? 'Unknown',
                    'start_u' => $device->start_u,
                    'u_height' => $device->u_height,
                    'lifecycle_status' => $device->lifecycle_status?->value,
                    'lifecycle_status_label' => $device->lifecycle_status?->label(),
                ];
            });

        // Calculate utilization stats
        $utilization = $this->calculateUtilization($rack);

        // Calculate power metrics
        $powerMetrics = $this->calculatePowerMetrics($rack);

        return Inertia::render('Racks/Show', [
            'datacenter' => [
                'id' => $datacenter->id,
                'name' => $datacenter->name,
            ],
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
            ],
            'row' => [
                'id' => $row->id,
                'name' => $row->name,
            ],
            'rack' => [
                'id' => $rack->id,
                'name' => $rack->name,
                'position' => $rack->position,
                'u_height' => $rack->u_height?->value,
                'u_height_label' => $rack->u_height?->label(),
                'serial_number' => $rack->serial_number,
                'status' => $rack->status?->value,
                'status_label' => $rack->status?->label(),
                'created_at' => $rack->created_at,
                'updated_at' => $rack->updated_at,
                'manufacturer' => $rack->manufacturer,
                'model' => $rack->model,
                'depth' => $rack->depth,
                'installation_date' => $rack->installation_date?->format('Y-m-d'),
                'location_notes' => $rack->location_notes,
                'specs' => $rack->specs,
            ],
            'pdus' => $pdus,
            'devices' => $devices,
            'utilization' => $utilization,
            'powerMetrics' => $powerMetrics,
            'canEdit' => $user->hasAnyRole(self::ADMIN_ROLES),
            'canDelete' => $user->hasAnyRole(self::ADMIN_ROLES),
        ]);
    }

    /**
     * Show the form for editing the specified rack.
     */
    public function edit(Datacenter $datacenter, Room $room, Row $row, Rack $rack): InertiaResponse
    {
        Gate::authorize('update', $rack);

        // Query available PDUs (same as create)
        $pduOptions = $this->getAvailablePdus($room, $row);

        // Get currently assigned PDU IDs
        $selectedPduIds = $rack->pdus()->pluck('pdus.id')->toArray();

        return Inertia::render('Racks/Edit', [
            'datacenter' => [
                'id' => $datacenter->id,
                'name' => $datacenter->name,
            ],
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
            ],
            'row' => [
                'id' => $row->id,
                'name' => $row->name,
            ],
            'rack' => [
                'id' => $rack->id,
                'name' => $rack->name,
                'position' => $rack->position,
                'u_height' => $rack->u_height?->value,
                'serial_number' => $rack->serial_number,
                'status' => $rack->status?->value,
                'pdu_ids' => $selectedPduIds,
                'manufacturer' => $rack->manufacturer,
                'model' => $rack->model,
                'depth' => $rack->depth,
                'installation_date' => $rack->installation_date?->format('Y-m-d'),
                'location_notes' => $rack->location_notes,
                'specs' => $rack->specs,
            ],
            'statusOptions' => collect(RackStatus::cases())->map(fn (RackStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->values()->all(),
            'uHeightOptions' => collect(RackUHeight::cases())->map(fn (RackUHeight $h) => [
                'value' => $h->value,
                'label' => $h->label(),
            ])->values()->all(),
            'pduOptions' => $pduOptions,
        ]);
    }

    /**
     * Update the specified rack.
     */
    public function update(UpdateRackRequest $request, Datacenter $datacenter, Room $room, Row $row, Rack $rack): RedirectResponse
    {
        $validated = $request->validated();

        $rack->update([
            'name' => $validated['name'],
            'position' => $validated['position'],
            'u_height' => $validated['u_height'],
            'serial_number' => $validated['serial_number'] ?? null,
            'status' => $validated['status'],
            'manufacturer' => $validated['manufacturer'] ?? null,
            'model' => $validated['model'] ?? null,
            'depth' => $validated['depth'] ?? null,
            'installation_date' => $validated['installation_date'] ?? null,
            'location_notes' => $validated['location_notes'] ?? null,
            'specs' => $validated['specs'] ?? null,
        ]);

        // Sync PDU relationships
        $rack->pdus()->sync($validated['pdu_ids'] ?? []);

        return redirect()->route('datacenters.rooms.rows.show', [$datacenter, $room, $row])
            ->with('success', 'Rack updated successfully.');
    }

    /**
     * Remove the specified rack.
     * Detaches PDU relationships before deleting (PDUs are not deleted).
     */
    public function destroy(Datacenter $datacenter, Room $room, Row $row, Rack $rack): RedirectResponse
    {
        Gate::authorize('delete', $rack);

        // Detach all PDU relationships (pivot records only, PDUs remain intact)
        $rack->pdus()->detach();

        // Delete the rack
        $rack->delete();

        return redirect()->route('datacenters.rooms.rows.show', [$datacenter, $room, $row])
            ->with('success', 'Rack deleted successfully.');
    }

    /**
     * Display the rack elevation diagram.
     *
     * Returns rack data along with real device data for the elevation view.
     * Placed devices are those assigned to this specific rack.
     * Unplaced devices are those with no rack assignment (available for placement).
     */
    public function elevation(Request $request, Datacenter $datacenter, Room $room, Row $row, Rack $rack): InertiaResponse
    {
        Gate::authorize('view', $rack);

        $devices = $this->getDevicesForElevation($rack);

        return Inertia::render('Racks/Elevation', [
            'datacenter' => [
                'id' => $datacenter->id,
                'name' => $datacenter->name,
            ],
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
            ],
            'row' => [
                'id' => $row->id,
                'name' => $row->name,
            ],
            'rack' => [
                'id' => $rack->id,
                'name' => $rack->name,
                'position' => $rack->position,
                'u_height' => $rack->u_height?->value,
                'u_height_label' => $rack->u_height?->label(),
                'serial_number' => $rack->serial_number,
                'status' => $rack->status?->value,
                'status_label' => $rack->status?->label(),
            ],
            'devices' => $devices,
        ]);
    }

    /**
     * Calculate utilization statistics for a rack.
     *
     * @return array{totalU: int, usedU: float, availableU: float, utilizationPercent: float}
     */
    private function calculateUtilization(Rack $rack): array
    {
        $totalU = $rack->u_height?->value ?? 0;
        $usedU = $rack->devices->sum('u_height');
        $availableU = $totalU - $usedU;
        $utilizationPercent = $totalU > 0 ? round(($usedU / $totalU) * 100, 1) : 0;

        return [
            'totalU' => $totalU,
            'usedU' => $usedU,
            'availableU' => $availableU,
            'utilizationPercent' => $utilizationPercent,
        ];
    }

    /**
     * Calculate power metrics for a rack.
     *
     * Aggregates power_draw_watts from all devices and total_capacity_kw from PDUs.
     *
     * @return array{totalPowerDraw: int, pduCapacity: int, powerUtilizationPercent: float}
     */
    private function calculatePowerMetrics(Rack $rack): array
    {
        // Sum power draw from all devices in the rack
        $totalPowerDraw = (int) $rack->devices->sum('power_draw_watts');

        // Sum PDU capacity (convert kW to watts)
        $pduCapacityKw = $rack->pdus->sum('total_capacity_kw');
        $pduCapacity = (int) ($pduCapacityKw * 1000);

        // Calculate utilization percentage, avoiding division by zero
        $powerUtilizationPercent = $pduCapacity > 0
            ? round(($totalPowerDraw / $pduCapacity) * 100, 1)
            : 0;

        return [
            'totalPowerDraw' => $totalPowerDraw,
            'pduCapacity' => $pduCapacity,
            'powerUtilizationPercent' => $powerUtilizationPercent,
        ];
    }

    /**
     * Get available PDUs for rack assignment.
     * Returns PDUs from the same room (room-level) or same row (row-level).
     *
     * @return array<int, array{id: int, name: string, model: string|null}>
     */
    private function getAvailablePdus(Room $room, Row $row): array
    {
        return Pdu::query()
            ->where(function ($query) use ($room, $row) {
                // Room-level PDUs from the same room
                $query->where('room_id', $room->id)
                    ->whereNull('row_id');
            })
            ->orWhere(function ($query) use ($row) {
                // Row-level PDUs from the same row
                $query->where('row_id', $row->id)
                    ->whereNull('room_id');
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Pdu $pdu) => [
                'id' => $pdu->id,
                'name' => $pdu->name,
                'model' => $pdu->model,
            ])
            ->toArray();
    }

    /**
     * Get devices for the elevation view.
     *
     * Returns both placed devices (assigned to this rack) and unplaced devices
     * (available for placement from inventory).
     *
     * The returned data structure matches the TypeScript interfaces defined in resources/js/types/rooms.ts:
     * - PlaceholderDevice interface for individual devices
     * - DeviceWidth type: 'full' | 'half-left' | 'half-right'
     * - RackFace type: 'front' | 'rear'
     *
     * @return array{placed: array<int, array<string, mixed>>, unplaced: array<int, array<string, mixed>>}
     */
    private function getDevicesForElevation(Rack $rack): array
    {
        // Get devices placed in this specific rack
        $placedDevices = Device::query()
            ->with('deviceType')
            ->where('rack_id', $rack->id)
            ->whereNotNull('start_u')
            ->orderBy('name')
            ->get()
            ->map(fn (Device $device) => $this->formatDeviceForElevation($device, true))
            ->values()
            ->toArray();

        // Get unplaced devices (no rack assignment) for the sidebar
        $unplacedDevices = Device::query()
            ->with('deviceType')
            ->whereNull('rack_id')
            ->orderBy('name')
            ->get()
            ->map(fn (Device $device) => $this->formatDeviceForElevation($device, false))
            ->values()
            ->toArray();

        return [
            'placed' => $placedDevices,
            'unplaced' => $unplacedDevices,
        ];
    }

    /**
     * Format a device for the elevation view.
     *
     * Transforms Device model data to match the PlaceholderDevice TypeScript interface.
     *
     * @return array<string, mixed>
     */
    private function formatDeviceForElevation(Device $device, bool $isPlaced): array
    {
        $data = [
            'id' => (string) $device->id,
            'name' => $device->name,
            'type' => $device->deviceType?->name ?? 'unknown',
            'u_size' => $device->u_height,
            'width' => $this->mapWidthTypeToFrontend($device->width_type?->value),
        ];

        // Include placement information only for placed devices
        if ($isPlaced) {
            $data['start_u'] = $device->start_u;
            $data['face'] = $device->rack_face?->value;
        }

        return $data;
    }

    /**
     * Map backend width_type values to frontend DeviceWidth type.
     *
     * Backend uses: 'full', 'half_left', 'half_right'
     * Frontend uses: 'full', 'half-left', 'half-right'
     */
    private function mapWidthTypeToFrontend(?string $widthType): string
    {
        return match ($widthType) {
            'half_left' => 'half-left',
            'half_right' => 'half-right',
            default => 'full',
        };
    }
}
