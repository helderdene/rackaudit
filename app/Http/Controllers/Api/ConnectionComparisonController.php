<?php

namespace App\Http\Controllers\Api;

use App\Exports\ComparisonExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompareConnectionsRequest;
use App\Http\Resources\ComparisonResultResource;
use App\Models\Datacenter;
use App\Models\ImplementationFile;
use App\Services\ConnectionComparisonService;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * API controller for comparing expected connections against actual connections.
 *
 * Provides endpoints for comparing connections at the implementation file level
 * or aggregated across an entire datacenter. Supports filtering by discrepancy
 * type, device, rack, and acknowledgment status.
 */
class ConnectionComparisonController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ConnectionComparisonService $comparisonService
    ) {}

    /**
     * Compare connections for a specific implementation file.
     *
     * Returns comparison results for all confirmed expected connections
     * in the file, plus any unexpected actual connections involving
     * the same ports.
     */
    public function compareForFile(CompareConnectionsRequest $request, ImplementationFile $file): JsonResponse
    {
        // Ensure file is approved with confirmed connections
        if (! $file->isApproved()) {
            return response()->json([
                'message' => 'Only approved implementation files can be compared.',
            ], 403);
        }

        // Get comparison results
        $results = $this->comparisonService->compareForImplementationFile($file);

        // Apply filters
        $results = $this->applyFilters($request, $results);

        // Get statistics before pagination
        $statistics = $results->getStatistics();

        // Apply pagination if provided
        $limit = $request->integer('limit', 50);
        $offset = $request->integer('offset', 0);
        $paginated = $results->paginate($offset, $limit);

        return response()->json([
            'data' => ComparisonResultResource::collection($paginated['items']),
            'statistics' => $statistics,
            'pagination' => [
                'total' => $paginated['total'],
                'offset' => $paginated['offset'],
                'limit' => $paginated['limit'],
            ],
        ]);
    }

    /**
     * Compare connections for an entire datacenter.
     *
     * Aggregates confirmed expected connections from all approved
     * implementation files and compares against all actual connections.
     * Includes conflict detection for overlapping expectations.
     */
    public function compareForDatacenter(CompareConnectionsRequest $request, Datacenter $datacenter): JsonResponse
    {
        // Get comparison results
        $results = $this->comparisonService->compareForDatacenter($datacenter);

        // Apply filters
        $results = $this->applyFilters($request, $results);

        // Get statistics before pagination
        $statistics = $results->getStatistics();

        // Apply pagination
        $limit = $request->integer('limit', 50);
        $offset = $request->integer('offset', 0);
        $paginated = $results->paginate($offset, $limit);

        return response()->json([
            'data' => ComparisonResultResource::collection($paginated['items']),
            'statistics' => $statistics,
            'pagination' => [
                'total' => $paginated['total'],
                'offset' => $paginated['offset'],
                'limit' => $paginated['limit'],
            ],
        ]);
    }

    /**
     * Export comparison results for a specific implementation file as CSV.
     *
     * Respects the current filter selections and returns a downloadable file.
     */
    public function exportForFile(CompareConnectionsRequest $request, ImplementationFile $file): BinaryFileResponse|JsonResponse
    {
        // Ensure file is approved with confirmed connections
        if (! $file->isApproved()) {
            return response()->json([
                'message' => 'Only approved implementation files can be exported.',
            ], 403);
        }

        // Get comparison results
        $results = $this->comparisonService->compareForImplementationFile($file);

        // Apply filters (no pagination for export - export all filtered results)
        $results = $this->applyFilters($request, $results);

        // Generate filename with timestamp
        $filename = 'comparison_file_'.$file->id.'_'.now()->format('Y-m-d_His').'.csv';

        return Excel::download(new ComparisonExport($results), $filename);
    }

    /**
     * Export comparison results for a datacenter as CSV.
     *
     * Aggregates all approved implementation files and respects filter selections.
     */
    public function exportForDatacenter(CompareConnectionsRequest $request, Datacenter $datacenter): BinaryFileResponse
    {
        // Get comparison results
        $results = $this->comparisonService->compareForDatacenter($datacenter);

        // Apply filters (no pagination for export - export all filtered results)
        $results = $this->applyFilters($request, $results);

        // Generate filename with timestamp
        $filename = 'comparison_datacenter_'.$datacenter->id.'_'.now()->format('Y-m-d_His').'.csv';

        return Excel::download(new ComparisonExport($results), $filename);
    }

    /**
     * Apply request filters to the comparison results.
     */
    protected function applyFilters(CompareConnectionsRequest $request, $results)
    {
        // Filter by discrepancy types
        if ($request->has('discrepancy_type') && ! empty($request->discrepancy_type)) {
            $types = $request->getDiscrepancyTypes();
            $results = $results->filterByDiscrepancyType($types);
        }

        // Filter by device
        if ($request->filled('device_id')) {
            $results = $results->filterByDevice((int) $request->device_id);
        }

        // Filter by rack
        if ($request->filled('rack_id')) {
            $results = $results->filterByRack((int) $request->rack_id);
        }

        // Filter by acknowledgment status
        $showAcknowledged = $request->boolean('show_acknowledged', true);
        $results = $results->filterByAcknowledged($showAcknowledged);

        return $results;
    }
}
