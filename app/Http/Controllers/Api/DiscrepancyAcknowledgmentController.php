<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcknowledgeDiscrepancyRequest;
use App\Models\DiscrepancyAcknowledgment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * API controller for managing discrepancy acknowledgments.
 *
 * Provides endpoints for creating and deleting acknowledgments that mark
 * connection discrepancies as reviewed. Acknowledgments allow users to
 * defer resolution while tracking which discrepancies have been evaluated.
 */
class DiscrepancyAcknowledgmentController extends Controller
{
    /**
     * Store a new discrepancy acknowledgment.
     *
     * Creates an acknowledgment record to mark a discrepancy as reviewed.
     * Requires either expected_connection_id or connection_id along with
     * the discrepancy type.
     */
    public function store(AcknowledgeDiscrepancyRequest $request): JsonResponse
    {
        $acknowledgment = DiscrepancyAcknowledgment::create([
            'expected_connection_id' => $request->input('expected_connection_id'),
            'connection_id' => $request->input('connection_id'),
            'discrepancy_type' => $request->getDiscrepancyType(),
            'acknowledged_by' => $request->user()->id,
            'acknowledged_at' => now(),
            'notes' => $request->input('notes'),
        ]);

        // Load the acknowledgedBy relationship for the response
        $acknowledgment->load('acknowledgedBy');

        return response()->json([
            'data' => [
                'id' => $acknowledgment->id,
                'expected_connection_id' => $acknowledgment->expected_connection_id,
                'connection_id' => $acknowledgment->connection_id,
                'discrepancy_type' => $acknowledgment->discrepancy_type->value,
                'discrepancy_type_label' => $acknowledgment->discrepancy_type->label(),
                'acknowledged_by' => $acknowledgment->acknowledged_by,
                'acknowledged_by_name' => $acknowledgment->acknowledgedBy?->name,
                'acknowledged_at' => $acknowledgment->acknowledged_at?->toIso8601String(),
                'notes' => $acknowledgment->notes,
            ],
            'message' => 'Discrepancy acknowledged successfully.',
        ], 201);
    }

    /**
     * Remove the specified discrepancy acknowledgment.
     *
     * Deletes an acknowledgment record, removing the reviewed status
     * from the associated discrepancy.
     */
    public function destroy(DiscrepancyAcknowledgment $acknowledgment): Response
    {
        $acknowledgment->delete();

        return response()->noContent();
    }
}
