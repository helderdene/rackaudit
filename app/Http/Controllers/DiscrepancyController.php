<?php

namespace App\Http\Controllers;

use App\Enums\DiscrepancyStatus;
use App\Enums\DiscrepancyType;
use App\Models\Datacenter;
use App\Models\Discrepancy;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller for Discrepancy Inertia pages.
 *
 * Provides the discrepancy dashboard with filterable, sortable table
 * of discrepancies along with summary statistics and filter options.
 */
class DiscrepancyController extends Controller
{
    /**
     * Display the discrepancy dashboard.
     *
     * Returns an Inertia page with:
     * - Paginated, filtered list of discrepancies
     * - Summary statistics by type and datacenter
     * - Filter options (types, statuses, datacenters)
     * - Current filter state for persistence
     */
    public function index(Request $request): InertiaResponse
    {
        $query = Discrepancy::query()
            ->with([
                'datacenter',
                'room',
                'sourcePort.device.rack',
                'destPort.device.rack',
            ]);

        // Filter by discrepancy type
        if ($request->filled('discrepancy_type')) {
            $type = DiscrepancyType::tryFrom($request->input('discrepancy_type'));
            if ($type) {
                $query->forType($type);
            }
        }

        // Filter by datacenter
        if ($request->filled('datacenter_id')) {
            $query->forDatacenter((int) $request->input('datacenter_id'));
        }

        // Filter by room
        if ($request->filled('room_id')) {
            $query->forRoom((int) $request->input('room_id'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $status = DiscrepancyStatus::tryFrom($request->input('status'));
            if ($status) {
                $query->where('status', $status);
            }
        }

        // Filter by date range
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $from = $request->input('date_from')
                ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay()
                : null;
            $to = $request->input('date_to')
                ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay()
                : null;

            if ($from && $to) {
                $query->detectedBetween($from, $to);
            } elseif ($from) {
                $query->where('detected_at', '>=', $from);
            } elseif ($to) {
                $query->where('detected_at', '<=', $to);
            }
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'detected_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSortFields = ['discrepancy_type', 'datacenter_id', 'detected_at', 'status', 'created_at'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('detected_at', 'desc');
        }

        // Pagination
        $perPage = min((int) $request->input('per_page', 25), 100);

        $discrepancies = $query->paginate($perPage)->through(function (Discrepancy $discrepancy) {
            return [
                'id' => $discrepancy->id,
                'discrepancy_type' => $discrepancy->discrepancy_type->value,
                'discrepancy_type_label' => $discrepancy->discrepancy_type->label(),
                'status' => $discrepancy->status->value,
                'status_label' => $discrepancy->status->label(),
                'title' => $discrepancy->title,
                'description' => $discrepancy->description,
                'detected_at' => $discrepancy->detected_at?->format('Y-m-d H:i:s'),
                'datacenter' => $discrepancy->datacenter ? [
                    'id' => $discrepancy->datacenter->id,
                    'name' => $discrepancy->datacenter->name,
                ] : null,
                'room' => $discrepancy->room ? [
                    'id' => $discrepancy->room->id,
                    'name' => $discrepancy->room->name,
                ] : null,
                'source_port' => $this->transformPort($discrepancy->sourcePort),
                'dest_port' => $this->transformPort($discrepancy->destPort),
                'expected_config' => $discrepancy->expected_config,
                'actual_config' => $discrepancy->actual_config,
                'mismatch_details' => $discrepancy->mismatch_details,
            ];
        });

        // Build summary statistics
        $summary = $this->buildSummary($request);

        // Get datacenters for filter dropdown
        $datacenters = Datacenter::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Datacenter $dc) => [
                'id' => $dc->id,
                'name' => $dc->name,
            ]);

        // Get rooms for filter dropdown (filtered by datacenter if selected)
        $rooms = collect();
        if ($request->filled('datacenter_id')) {
            $rooms = Room::query()
                ->where('datacenter_id', (int) $request->input('datacenter_id'))
                ->orderBy('name')
                ->get()
                ->map(fn (Room $room) => [
                    'id' => $room->id,
                    'name' => $room->name,
                ]);
        }

        return Inertia::render('Discrepancies/Index', [
            'discrepancies' => $discrepancies,
            'summary' => $summary,
            'filters' => [
                'discrepancy_type' => $request->input('discrepancy_type', ''),
                'datacenter_id' => $request->input('datacenter_id', ''),
                'room_id' => $request->input('room_id', ''),
                'status' => $request->input('status', ''),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
            'datacenters' => $datacenters,
            'rooms' => $rooms,
            'typeOptions' => collect(DiscrepancyType::cases())
                ->filter(fn ($type) => $type !== DiscrepancyType::Matched)
                ->map(fn ($type) => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ])
                ->values()
                ->toArray(),
            'statusOptions' => collect(DiscrepancyStatus::cases())->map(fn ($status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values()->toArray(),
        ]);
    }

    /**
     * Build summary statistics for the dashboard.
     *
     * @return array<string, mixed>
     */
    protected function buildSummary(Request $request): array
    {
        $baseQuery = Discrepancy::query()
            ->where('status', '!=', DiscrepancyStatus::Resolved);

        // Apply datacenter filter to summary if set
        if ($request->filled('datacenter_id')) {
            $baseQuery->forDatacenter((int) $request->input('datacenter_id'));
        }

        // Get total count
        $total = (clone $baseQuery)->count();

        // Get counts by type
        $byType = Discrepancy::query()
            ->where('status', '!=', DiscrepancyStatus::Resolved)
            ->when($request->filled('datacenter_id'), fn ($q) => $q->forDatacenter((int) $request->input('datacenter_id')))
            ->select('discrepancy_type', DB::raw('COUNT(*) as count'))
            ->groupBy('discrepancy_type')
            ->pluck('count', 'discrepancy_type')
            ->toArray();

        // Get counts by status
        $byStatus = Discrepancy::query()
            ->when($request->filled('datacenter_id'), fn ($q) => $q->forDatacenter((int) $request->input('datacenter_id')))
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Get counts by datacenter (only for non-resolved)
        $byDatacenter = Discrepancy::query()
            ->where('status', '!=', DiscrepancyStatus::Resolved)
            ->join('datacenters', 'discrepancies.datacenter_id', '=', 'datacenters.id')
            ->select('datacenters.id', 'datacenters.name', DB::raw('COUNT(*) as count'))
            ->groupBy('datacenters.id', 'datacenters.name')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'count' => $item->count,
            ])
            ->toArray();

        return [
            'total' => $total,
            'by_type' => $byType,
            'by_status' => $byStatus,
            'by_datacenter' => $byDatacenter,
        ];
    }

    /**
     * Transform a port model to array representation.
     *
     * @return array<string, mixed>|null
     */
    protected function transformPort(?\App\Models\Port $port): ?array
    {
        if (! $port) {
            return null;
        }

        return [
            'id' => $port->id,
            'label' => $port->label,
            'type' => $port->type?->value,
            'type_label' => $port->type?->label(),
            'device_id' => $port->device_id,
            'device' => $port->relationLoaded('device') && $port->device ? [
                'id' => $port->device->id,
                'name' => $port->device->name,
                'asset_tag' => $port->device->asset_tag,
                'rack' => $port->device->relationLoaded('rack') && $port->device->rack ? [
                    'id' => $port->device->rack->id,
                    'name' => $port->device->rack->name,
                ] : null,
            ] : null,
        ];
    }
}
