<?php

namespace App\Http\Controllers;

use App\Enums\RowOrientation;
use App\Enums\RowStatus;
use App\Http\Requests\StoreRowRequest;
use App\Http\Requests\UpdateRowRequest;
use App\Models\Datacenter;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RowController extends Controller
{
    /**
     * Roles that have full access to all rows.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Display a list of rows for a room ordered by position.
     */
    public function index(Request $request, Datacenter $datacenter, Room $room): InertiaResponse
    {
        Gate::authorize('viewAny', Row::class);

        $rows = $room->rows()
            ->orderBy('position')
            ->get()
            ->map(function (Row $row) {
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

        return Inertia::render('Rows/Index', [
            'datacenter' => [
                'id' => $datacenter->id,
                'name' => $datacenter->name,
            ],
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
            ],
            'rows' => $rows,
            'canCreate' => $request->user()->hasAnyRole(self::ADMIN_ROLES),
            'orientationOptions' => collect(RowOrientation::cases())->map(fn (RowOrientation $o) => [
                'value' => $o->value,
                'label' => $o->label(),
            ])->values()->all(),
            'statusOptions' => collect(RowStatus::cases())->map(fn (RowStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->values()->all(),
        ]);
    }

    /**
     * Show the form for creating a new row.
     */
    public function create(Datacenter $datacenter, Room $room): InertiaResponse
    {
        Gate::authorize('create', Row::class);

        // Calculate next position
        $nextPosition = $room->rows()->max('position') + 1;

        return Inertia::render('Rows/Create', [
            'datacenter' => [
                'id' => $datacenter->id,
                'name' => $datacenter->name,
            ],
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
            ],
            'nextPosition' => $nextPosition,
            'orientationOptions' => collect(RowOrientation::cases())->map(fn (RowOrientation $o) => [
                'value' => $o->value,
                'label' => $o->label(),
            ])->values()->all(),
            'statusOptions' => collect(RowStatus::cases())->map(fn (RowStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->values()->all(),
        ]);
    }

    /**
     * Store a newly created row.
     */
    public function store(StoreRowRequest $request, Datacenter $datacenter, Room $room): RedirectResponse
    {
        $validated = $request->validated();

        Row::create([
            'name' => $validated['name'],
            'position' => $validated['position'],
            'orientation' => $validated['orientation'],
            'status' => $validated['status'],
            'room_id' => $room->id,
        ]);

        return redirect()->route('datacenters.rooms.show', [$datacenter, $room])
            ->with('success', 'Row created successfully.');
    }

    /**
     * Display the specified row.
     */
    public function show(Request $request, Datacenter $datacenter, Room $room, Row $row): InertiaResponse
    {
        Gate::authorize('view', $row);

        $user = $request->user();

        $pdus = $row->pdus()
            ->get()
            ->map(function ($pdu) {
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

        return Inertia::render('Rows/Show', [
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
                'position' => $row->position,
                'orientation' => $row->orientation?->value,
                'orientation_label' => $row->orientation?->label(),
                'status' => $row->status?->value,
                'status_label' => $row->status?->label(),
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ],
            'pdus' => $pdus,
            'racks' => $racks,
            'canEdit' => $user->hasAnyRole(self::ADMIN_ROLES),
            'canDelete' => $user->hasAnyRole(self::ADMIN_ROLES),
            'canCreateRack' => $user->hasAnyRole(self::ADMIN_ROLES),
        ]);
    }

    /**
     * Show the form for editing the specified row.
     */
    public function edit(Datacenter $datacenter, Room $room, Row $row): InertiaResponse
    {
        Gate::authorize('update', $row);

        return Inertia::render('Rows/Edit', [
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
                'position' => $row->position,
                'orientation' => $row->orientation?->value,
                'status' => $row->status?->value,
            ],
            'orientationOptions' => collect(RowOrientation::cases())->map(fn (RowOrientation $o) => [
                'value' => $o->value,
                'label' => $o->label(),
            ])->values()->all(),
            'statusOptions' => collect(RowStatus::cases())->map(fn (RowStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->values()->all(),
        ]);
    }

    /**
     * Update the specified row.
     */
    public function update(UpdateRowRequest $request, Datacenter $datacenter, Room $room, Row $row): RedirectResponse
    {
        $validated = $request->validated();

        $row->update([
            'name' => $validated['name'],
            'position' => $validated['position'],
            'orientation' => $validated['orientation'],
            'status' => $validated['status'],
        ]);

        return redirect()->route('datacenters.rooms.show', [$datacenter, $room])
            ->with('success', 'Row updated successfully.');
    }

    /**
     * Remove the specified row.
     * If the row has PDUs, they will be reassigned to room-level or deleted.
     */
    public function destroy(Datacenter $datacenter, Room $room, Row $row): RedirectResponse
    {
        Gate::authorize('delete', $row);

        // Reassign row-level PDUs to room-level before deleting the row
        $row->pdus()->update([
            'row_id' => null,
            'room_id' => $room->id,
        ]);

        $row->delete();

        return redirect()->route('datacenters.rooms.show', [$datacenter, $room])
            ->with('success', 'Row deleted successfully. Any assigned PDUs have been reassigned to room level.');
    }
}
