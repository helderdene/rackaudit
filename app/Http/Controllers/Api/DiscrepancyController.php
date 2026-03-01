<?php

namespace App\Http\Controllers\Api;

use App\Enums\DiscrepancyStatus;
use App\Enums\DiscrepancyType;
use App\Http\Controllers\Controller;
use App\Http\Resources\DiscrepancyResource;
use App\Http\Resources\DiscrepancySummaryResource;
use App\Jobs\DetectDiscrepanciesJob;
use App\Models\Discrepancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * API controller for managing discrepancy records.
 *
 * Provides endpoints for listing, viewing, acknowledging, resolving,
 * and triggering on-demand detection of discrepancies between expected
 * and actual connections.
 */
class DiscrepancyController extends Controller
{
    /**
     * Display a paginated list of discrepancies with filtering.
     *
     * Supports filtering by:
     * - discrepancy_type: Filter by type (missing, unexpected, mismatched, conflicting, configuration_mismatch)
     * - datacenter_id: Filter by datacenter
     * - room_id: Filter by room
     * - status: Filter by status (open, acknowledged, resolved, in_audit)
     * - date_from, date_to: Filter by date range
     *
     * Supports sorting by:
     * - discrepancy_type, datacenter_id, detected_at
     */
    public function index(Request $request): AnonymousResourceCollection
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

        return DiscrepancyResource::collection($query->paginate($perPage));
    }

    /**
     * Display a single discrepancy with full details.
     *
     * Includes related connection, expected connection, and configuration data.
     */
    public function show(Discrepancy $discrepancy): DiscrepancyResource
    {
        $discrepancy->load([
            'datacenter',
            'room',
            'implementationFile',
            'sourcePort.device.rack',
            'destPort.device.rack',
            'connection',
            'expectedConnection',
            'acknowledgedBy',
            'resolvedBy',
        ]);

        return new DiscrepancyResource($discrepancy);
    }

    /**
     * Acknowledge a discrepancy.
     *
     * Updates status to Acknowledged and records the user and timestamp.
     */
    public function acknowledge(Request $request, Discrepancy $discrepancy): JsonResponse
    {
        $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Only allow acknowledging open discrepancies
        if ($discrepancy->status !== DiscrepancyStatus::Open) {
            return response()->json([
                'message' => 'Only open discrepancies can be acknowledged.',
                'current_status' => $discrepancy->status->value,
            ], 422);
        }

        $discrepancy->update([
            'status' => DiscrepancyStatus::Acknowledged,
            'acknowledged_at' => now(),
            'acknowledged_by' => $request->user()->id,
            'description' => $request->filled('notes')
                ? ($discrepancy->description ? $discrepancy->description . "\n\n" : '') . 'Acknowledgment note: ' . $request->input('notes')
                : $discrepancy->description,
        ]);

        $discrepancy->refresh();
        $discrepancy->load(['datacenter', 'acknowledgedBy']);

        return response()->json([
            'data' => new DiscrepancyResource($discrepancy),
            'message' => 'Discrepancy acknowledged successfully.',
        ]);
    }

    /**
     * Resolve a discrepancy.
     *
     * Updates status to Resolved and records the user and timestamp.
     */
    public function resolve(Request $request, Discrepancy $discrepancy): JsonResponse
    {
        $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Allow resolving open or acknowledged discrepancies
        if (! in_array($discrepancy->status, [DiscrepancyStatus::Open, DiscrepancyStatus::Acknowledged])) {
            return response()->json([
                'message' => 'Only open or acknowledged discrepancies can be resolved.',
                'current_status' => $discrepancy->status->value,
            ], 422);
        }

        $discrepancy->update([
            'status' => DiscrepancyStatus::Resolved,
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
            'description' => $request->filled('notes')
                ? ($discrepancy->description ? $discrepancy->description . "\n\n" : '') . 'Resolution note: ' . $request->input('notes')
                : $discrepancy->description,
        ]);

        $discrepancy->refresh();
        $discrepancy->load(['datacenter', 'resolvedBy']);

        return response()->json([
            'data' => new DiscrepancyResource($discrepancy),
            'message' => 'Discrepancy resolved successfully.',
        ]);
    }

    /**
     * Trigger on-demand discrepancy detection.
     *
     * Accepts scope parameters to limit detection:
     * - datacenter_id: Detect for entire datacenter
     * - room_id: Detect for specific room
     * - implementation_file_id: Detect for specific implementation file
     */
    public function detect(Request $request): JsonResponse
    {
        $request->validate([
            'datacenter_id' => ['nullable', 'integer', 'exists:datacenters,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'implementation_file_id' => ['nullable', 'integer', 'exists:implementation_files,id'],
        ]);

        // At least one scope must be provided
        if (! $request->filled('datacenter_id') && ! $request->filled('room_id') && ! $request->filled('implementation_file_id')) {
            return response()->json([
                'message' => 'At least one scope parameter (datacenter_id, room_id, or implementation_file_id) is required.',
            ], 422);
        }

        $job = new DetectDiscrepanciesJob(
            datacenterId: $request->integer('datacenter_id') ?: null,
            roomId: $request->integer('room_id') ?: null,
            implementationFileId: $request->integer('implementation_file_id') ?: null,
        );

        dispatch($job);

        return response()->json([
            'message' => 'Detection job dispatched successfully.',
            'scope' => [
                'datacenter_id' => $request->input('datacenter_id'),
                'room_id' => $request->input('room_id'),
                'implementation_file_id' => $request->input('implementation_file_id'),
            ],
        ], 202);
    }

    /**
     * Get summary statistics for discrepancies.
     *
     * Returns aggregate counts by type and by datacenter.
     */
    public function summary(Request $request): DiscrepancySummaryResource
    {
        $query = Discrepancy::query();

        // Optional datacenter filter
        if ($request->filled('datacenter_id')) {
            $query->forDatacenter((int) $request->input('datacenter_id'));
        }

        // Optional status filter (default to non-resolved)
        if ($request->filled('status')) {
            $status = DiscrepancyStatus::tryFrom($request->input('status'));
            if ($status) {
                $query->where('status', $status);
            }
        } else {
            // By default, exclude resolved discrepancies from summary
            $query->where('status', '!=', DiscrepancyStatus::Resolved);
        }

        // Get total count
        $total = $query->count();

        // Get counts by type
        $byType = Discrepancy::query()
            ->when($request->filled('datacenter_id'), fn ($q) => $q->forDatacenter((int) $request->input('datacenter_id')))
            ->when(! $request->filled('status'), fn ($q) => $q->where('status', '!=', DiscrepancyStatus::Resolved))
            ->when($request->filled('status'), fn ($q) => $q->where('status', DiscrepancyStatus::tryFrom($request->input('status'))))
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

        // Get counts by datacenter
        $byDatacenter = Discrepancy::query()
            ->when(! $request->filled('status'), fn ($q) => $q->where('status', '!=', DiscrepancyStatus::Resolved))
            ->when($request->filled('status'), fn ($q) => $q->where('status', DiscrepancyStatus::tryFrom($request->input('status'))))
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

        return new DiscrepancySummaryResource([
            'total' => $total,
            'by_type' => $byType,
            'by_status' => $byStatus,
            'by_datacenter' => $byDatacenter,
        ]);
    }

    /**
     * Check bulk audit status for multiple discrepancies.
     *
     * Returns import availability for each discrepancy based on their current status.
     * Discrepancies that are Open or Acknowledged can be imported into audits.
     * Discrepancies that are InAudit or Resolved cannot be imported.
     */
    public function bulkStatus(Request $request): JsonResponse
    {
        $request->validate([
            'discrepancy_ids' => ['required', 'array', 'min:1'],
            'discrepancy_ids.*' => ['integer', 'exists:discrepancies,id'],
        ]);

        $discrepancyIds = $request->input('discrepancy_ids');

        $discrepancies = Discrepancy::whereIn('id', $discrepancyIds)
            ->select('id', 'status', 'audit_id')
            ->get();

        $statusData = [];
        foreach ($discrepancies as $discrepancy) {
            $canImport = in_array($discrepancy->status, [
                DiscrepancyStatus::Open,
                DiscrepancyStatus::Acknowledged,
            ]);

            $statusData[(string) $discrepancy->id] = [
                'status' => $discrepancy->status->value,
                'can_import' => $canImport,
                'audit_id' => $discrepancy->audit_id,
            ];
        }

        return response()->json([
            'data' => $statusData,
        ]);
    }
}
