<?php

namespace App\Http\Controllers;

use App\Enums\PduPhase;
use App\Enums\PduStatus;
use App\Http\Requests\StorePduRequest;
use App\Http\Requests\UpdatePduRequest;
use App\Models\Datacenter;
use App\Models\Pdu;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PduController extends Controller
{
    /**
     * Roles that have full access to all PDUs.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Display a list of PDUs for a room (including row-level PDUs).
     */
    public function index(Request $request, Datacenter $datacenter, Room $room): InertiaResponse
    {
        Gate::authorize('viewAny', Pdu::class);

        // Get room-level PDUs
        $roomLevelPdus = $room->pdus()->get();

        // Get row-level PDUs via the room's rows
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

        // Get rows for assignment dropdown
        $rows = $room->rows()
            ->orderBy('position')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
            ]);

        return Inertia::render('Pdus/Index', [
            'datacenter' => [
                'id' => $datacenter->id,
                'name' => $datacenter->name,
            ],
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
            ],
            'pdus' => $pdus,
            'rows' => $rows,
            'canCreate' => $request->user()->hasAnyRole(self::ADMIN_ROLES),
            'phaseOptions' => collect(PduPhase::cases())->map(fn (PduPhase $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ])->values()->all(),
            'statusOptions' => collect(PduStatus::cases())->map(fn (PduStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->values()->all(),
        ]);
    }

    /**
     * Show the form for creating a new PDU.
     */
    public function create(Datacenter $datacenter, Room $room): InertiaResponse
    {
        Gate::authorize('create', Pdu::class);

        // Get rows for assignment dropdown
        $rows = $room->rows()
            ->orderBy('position')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
            ]);

        return Inertia::render('Pdus/Create', [
            'datacenter' => [
                'id' => $datacenter->id,
                'name' => $datacenter->name,
            ],
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
            ],
            'rows' => $rows,
            'phaseOptions' => collect(PduPhase::cases())->map(fn (PduPhase $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ])->values()->all(),
            'statusOptions' => collect(PduStatus::cases())->map(fn (PduStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->values()->all(),
        ]);
    }

    /**
     * Store a newly created PDU.
     */
    public function store(StorePduRequest $request, Datacenter $datacenter, Room $room): RedirectResponse
    {
        $validated = $request->validated();

        // Determine assignment: if row_id is provided, set room_id to null
        $roomId = ! empty($validated['row_id']) ? null : $room->id;
        $rowId = $validated['row_id'] ?? null;

        Pdu::create([
            'name' => $validated['name'],
            'model' => $validated['model'] ?? null,
            'manufacturer' => $validated['manufacturer'] ?? null,
            'total_capacity_kw' => $validated['total_capacity_kw'] ?? null,
            'voltage' => $validated['voltage'] ?? null,
            'phase' => $validated['phase'],
            'circuit_count' => $validated['circuit_count'],
            'status' => $validated['status'],
            'room_id' => $roomId,
            'row_id' => $rowId,
        ]);

        return redirect()->route('datacenters.rooms.show', [$datacenter, $room])
            ->with('success', 'PDU created successfully.');
    }

    /**
     * Display the specified PDU.
     */
    public function show(Datacenter $datacenter, Room $room, Pdu $pdu): InertiaResponse
    {
        Gate::authorize('view', $pdu);

        $user = request()->user();

        $assignmentLevel = $pdu->isRoomLevel() ? 'Room Level' : 'Row: '.$pdu->row->name;

        return Inertia::render('Pdus/Show', [
            'datacenter' => [
                'id' => $datacenter->id,
                'name' => $datacenter->name,
            ],
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
            ],
            'pdu' => [
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
                'created_at' => $pdu->created_at,
                'updated_at' => $pdu->updated_at,
            ],
            'canEdit' => $user->hasAnyRole(self::ADMIN_ROLES),
            'canDelete' => $user->hasAnyRole(self::ADMIN_ROLES),
        ]);
    }

    /**
     * Show the form for editing the specified PDU.
     */
    public function edit(Datacenter $datacenter, Room $room, Pdu $pdu): InertiaResponse
    {
        Gate::authorize('update', $pdu);

        // Get rows for assignment dropdown
        $rows = $room->rows()
            ->orderBy('position')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
            ]);

        return Inertia::render('Pdus/Edit', [
            'datacenter' => [
                'id' => $datacenter->id,
                'name' => $datacenter->name,
            ],
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
            ],
            'pdu' => [
                'id' => $pdu->id,
                'name' => $pdu->name,
                'model' => $pdu->model,
                'manufacturer' => $pdu->manufacturer,
                'total_capacity_kw' => $pdu->total_capacity_kw,
                'voltage' => $pdu->voltage,
                'phase' => $pdu->phase?->value,
                'circuit_count' => $pdu->circuit_count,
                'status' => $pdu->status?->value,
                'room_id' => $pdu->room_id,
                'row_id' => $pdu->row_id,
            ],
            'rows' => $rows,
            'phaseOptions' => collect(PduPhase::cases())->map(fn (PduPhase $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ])->values()->all(),
            'statusOptions' => collect(PduStatus::cases())->map(fn (PduStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->values()->all(),
        ]);
    }

    /**
     * Update the specified PDU.
     */
    public function update(UpdatePduRequest $request, Datacenter $datacenter, Room $room, Pdu $pdu): RedirectResponse
    {
        $validated = $request->validated();

        // Determine assignment: if row_id is provided, set room_id to null
        $roomId = ! empty($validated['row_id']) ? null : $room->id;
        $rowId = $validated['row_id'] ?? null;

        $pdu->update([
            'name' => $validated['name'],
            'model' => $validated['model'] ?? null,
            'manufacturer' => $validated['manufacturer'] ?? null,
            'total_capacity_kw' => $validated['total_capacity_kw'] ?? null,
            'voltage' => $validated['voltage'] ?? null,
            'phase' => $validated['phase'],
            'circuit_count' => $validated['circuit_count'],
            'status' => $validated['status'],
            'room_id' => $roomId,
            'row_id' => $rowId,
        ]);

        return redirect()->route('datacenters.rooms.show', [$datacenter, $room])
            ->with('success', 'PDU updated successfully.');
    }

    /**
     * Remove the specified PDU.
     */
    public function destroy(Datacenter $datacenter, Room $room, Pdu $pdu): RedirectResponse
    {
        Gate::authorize('delete', $pdu);

        $pdu->delete();

        return redirect()->route('datacenters.rooms.show', [$datacenter, $room])
            ->with('success', 'PDU deleted successfully.');
    }
}
