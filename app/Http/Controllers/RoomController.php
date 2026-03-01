<?php

namespace App\Http\Controllers;

use App\Enums\RoomType;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\Datacenter;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RoomController extends Controller
{
    /**
     * Roles that have full access to all rooms.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Display a paginated list of rooms for a datacenter with search filtering.
     */
    public function index(Request $request, Datacenter $datacenter): InertiaResponse
    {
        Gate::authorize('viewAny', Room::class);

        $query = $datacenter->rooms();

        // Search by room name
        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $rooms = $query->orderBy('name')
            ->paginate(15)
            ->through(function (Room $room) {
                return [
                    'id' => $room->id,
                    'name' => $room->name,
                    'description' => $room->description,
                    'square_footage' => $room->square_footage,
                    'type' => $room->type?->value,
                    'type_label' => $room->type?->label(),
                    'row_count' => $room->rows()->count(),
                    'pdu_count' => $room->pdus()->count(),
                ];
            });

        return Inertia::render('Rooms/Index', [
            'datacenter' => [
                'id' => $datacenter->id,
                'name' => $datacenter->name,
            ],
            'rooms' => $rooms,
            'filters' => [
                'search' => $request->input('search', ''),
            ],
            'canCreate' => $request->user()->hasAnyRole(self::ADMIN_ROLES),
        ]);
    }

    /**
     * Show the form for creating a new room.
     */
    public function create(Datacenter $datacenter): InertiaResponse
    {
        Gate::authorize('create', Room::class);

        return Inertia::render('Rooms/Create', [
            'datacenter' => [
                'id' => $datacenter->id,
                'name' => $datacenter->name,
            ],
            'roomTypes' => collect(RoomType::cases())->map(fn (RoomType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values()->all(),
        ]);
    }

    /**
     * Store a newly created room.
     */
    public function store(StoreRoomRequest $request, Datacenter $datacenter): RedirectResponse
    {
        $validated = $request->validated();

        Room::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'square_footage' => $validated['square_footage'] ?? null,
            'type' => $validated['type'],
            'datacenter_id' => $datacenter->id,
        ]);

        return redirect()->route('datacenters.show', $datacenter)
            ->with('success', 'Room created successfully.');
    }

    /**
     * Display the specified room with its rows and PDUs.
     */
    public function show(Datacenter $datacenter, Room $room): InertiaResponse
    {
        Gate::authorize('view', $room);

        $user = request()->user();

        // Get rows ordered by position
        $rows = $room->rows()
            ->orderBy('position')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'position' => $row->position,
                    'orientation' => $row->orientation?->value,
                    'orientation_label' => $row->orientation?->label(),
                    'status' => $row->status?->value,
                    'status_label' => $row->status?->label(),
                    'pdu_count' => $row->pdus()->count(),
                ];
            });

        // Get all PDUs for this room (both room-level and row-level via rows)
        $roomLevelPdus = $room->pdus()->get();
        $rowLevelPdus = $room->rows()
            ->with('pdus')
            ->get()
            ->flatMap(fn ($row) => $row->pdus);

        $pdus = $roomLevelPdus->merge($rowLevelPdus)
            ->map(function ($pdu) {
                $assignmentLevel = $pdu->isRoomLevel() ? 'Room Level' : 'Row: '.$pdu->row->name;

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
                    'room_id' => $pdu->room_id,
                    'row_id' => $pdu->row_id,
                    'assignment_level' => $assignmentLevel,
                ];
            });

        return Inertia::render('Rooms/Show', [
            'datacenter' => [
                'id' => $datacenter->id,
                'name' => $datacenter->name,
            ],
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
                'description' => $room->description,
                'square_footage' => $room->square_footage,
                'type' => $room->type?->value,
                'type_label' => $room->type?->label(),
                'created_at' => $room->created_at,
                'updated_at' => $room->updated_at,
            ],
            'rows' => $rows,
            'pdus' => $pdus,
            'canEdit' => $user->hasAnyRole(self::ADMIN_ROLES),
            'canDelete' => $user->hasAnyRole(self::ADMIN_ROLES),
            'canCreateRow' => $user->hasAnyRole(self::ADMIN_ROLES),
            'canCreatePdu' => $user->hasAnyRole(self::ADMIN_ROLES),
        ]);
    }

    /**
     * Show the form for editing the specified room.
     */
    public function edit(Datacenter $datacenter, Room $room): InertiaResponse
    {
        Gate::authorize('update', $room);

        return Inertia::render('Rooms/Edit', [
            'datacenter' => [
                'id' => $datacenter->id,
                'name' => $datacenter->name,
            ],
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
                'description' => $room->description,
                'square_footage' => $room->square_footage,
                'type' => $room->type?->value,
            ],
            'roomTypes' => collect(RoomType::cases())->map(fn (RoomType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values()->all(),
        ]);
    }

    /**
     * Update the specified room.
     */
    public function update(UpdateRoomRequest $request, Datacenter $datacenter, Room $room): RedirectResponse
    {
        $validated = $request->validated();

        $room->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'square_footage' => $validated['square_footage'] ?? null,
            'type' => $validated['type'],
        ]);

        return redirect()->route('datacenters.rooms.show', [$datacenter, $room])
            ->with('success', 'Room updated successfully.');
    }

    /**
     * Remove the specified room.
     * Cascade deletes rows and PDUs due to database constraints.
     */
    public function destroy(Datacenter $datacenter, Room $room): RedirectResponse
    {
        Gate::authorize('delete', $room);

        $room->delete();

        return redirect()->route('datacenters.show', $datacenter)
            ->with('success', 'Room deleted successfully.');
    }
}
