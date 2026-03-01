<?php

namespace App\Http\Controllers;

use App\Actions\ExpectedConnections\BulkConfirmExpectedConnectionsAction;
use App\Actions\ExpectedConnections\BulkSkipExpectedConnectionsAction;
use App\Actions\ExpectedConnections\CreateDevicePortOnFlyAction;
use App\Enums\ExpectedConnectionStatus;
use App\Http\Requests\BulkConfirmExpectedConnectionsRequest;
use App\Http\Requests\BulkSkipExpectedConnectionsRequest;
use App\Http\Requests\CreateDevicePortOnFlyRequest;
use App\Http\Requests\UpdateExpectedConnectionRequest;
use App\Http\Resources\ExpectedConnectionResource;
use App\Models\ExpectedConnection;
use App\Models\ImplementationFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller for managing expected connections during review.
 *
 * Provides CRUD operations for expected connections parsed from
 * implementation files, including bulk confirmation/skip actions
 * and device/port creation on the fly for unrecognized entries.
 */
class ExpectedConnectionController extends Controller
{
    /**
     * Roles that can manage expected connections.
     *
     * @var array<string>
     */
    private const AUTHORIZED_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Display the connection review page.
     *
     * Renders the Inertia review page for expected connections.
     */
    public function review(Request $request): InertiaResponse
    {
        $this->authorizeRole();

        $implementationFileId = $request->query('implementation_file');

        if (! $implementationFileId) {
            abort(404, 'Implementation file ID is required.');
        }

        $implementationFile = ImplementationFile::find($implementationFileId);

        if (! $implementationFile) {
            abort(404, 'Implementation file not found.');
        }

        return Inertia::render('ExpectedConnections/Review', [
            'implementationFileId' => (int) $implementationFileId,
            'implementationFileName' => $implementationFile->original_name,
            'datacenterId' => $implementationFile->datacenter_id,
        ]);
    }

    /**
     * Display a listing of expected connections for an implementation file.
     *
     * Returns all expected connections with device/port relationships
     * and summary statistics for the review interface.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeRole();

        $implementationFileId = $request->input('implementation_file');

        if (! $implementationFileId) {
            return response()->json([
                'message' => 'Implementation file ID is required.',
            ], 422);
        }

        $implementationFile = ImplementationFile::find($implementationFileId);

        if (! $implementationFile) {
            return response()->json([
                'message' => 'Implementation file not found.',
            ], 404);
        }

        $connections = ExpectedConnection::query()
            ->where('implementation_file_id', $implementationFileId)
            ->with(['sourceDevice', 'sourcePort', 'destDevice', 'destPort'])
            ->orderBy('row_number')
            ->get();

        // Calculate statistics
        $statistics = $this->calculateStatistics($connections);

        return response()->json([
            'data' => ExpectedConnectionResource::collection($connections),
            'statistics' => $statistics,
            'implementation_file' => [
                'id' => $implementationFile->id,
                'original_name' => $implementationFile->original_name,
            ],
        ]);
    }

    /**
     * Display the specified expected connection.
     */
    public function show(ExpectedConnection $expectedConnection): JsonResponse
    {
        $this->authorizeRole();

        $expectedConnection->load(['sourceDevice', 'sourcePort', 'destDevice', 'destPort', 'implementationFile']);

        return response()->json([
            'data' => new ExpectedConnectionResource($expectedConnection),
        ]);
    }

    /**
     * Update the specified expected connection.
     *
     * Allows updating device/port mappings and status during review.
     */
    public function update(UpdateExpectedConnectionRequest $request, ExpectedConnection $expectedConnection): JsonResponse
    {
        $validated = $request->validated();

        $updateData = [];

        if (array_key_exists('source_device_id', $validated)) {
            $updateData['source_device_id'] = $validated['source_device_id'];
        }

        if (array_key_exists('source_port_id', $validated)) {
            $updateData['source_port_id'] = $validated['source_port_id'];
        }

        if (array_key_exists('dest_device_id', $validated)) {
            $updateData['dest_device_id'] = $validated['dest_device_id'];
        }

        if (array_key_exists('dest_port_id', $validated)) {
            $updateData['dest_port_id'] = $validated['dest_port_id'];
        }

        if (isset($validated['cable_type'])) {
            $updateData['cable_type'] = $validated['cable_type'];
        }

        if (array_key_exists('cable_length', $validated)) {
            $updateData['cable_length'] = $validated['cable_length'];
        }

        if (isset($validated['status'])) {
            $updateData['status'] = $validated['status'];
        }

        $expectedConnection->update($updateData);
        $expectedConnection->refresh();
        $expectedConnection->load(['sourceDevice', 'sourcePort', 'destDevice', 'destPort']);

        return response()->json([
            'data' => new ExpectedConnectionResource($expectedConnection),
            'message' => 'Expected connection updated successfully.',
        ]);
    }

    /**
     * Bulk confirm expected connections.
     */
    public function bulkConfirm(
        BulkConfirmExpectedConnectionsRequest $request,
        BulkConfirmExpectedConnectionsAction $action
    ): JsonResponse {
        $connectionIds = $request->validated('connection_ids');

        $result = $action->execute($connectionIds);

        return response()->json([
            'confirmed_count' => $result['confirmed_count'],
            'message' => "{$result['confirmed_count']} connection(s) confirmed successfully.",
        ]);
    }

    /**
     * Bulk skip expected connections.
     */
    public function bulkSkip(
        BulkSkipExpectedConnectionsRequest $request,
        BulkSkipExpectedConnectionsAction $action
    ): JsonResponse {
        $connectionIds = $request->validated('connection_ids');

        $result = $action->execute($connectionIds);

        return response()->json([
            'skipped_count' => $result['skipped_count'],
            'message' => "{$result['skipped_count']} connection(s) skipped successfully.",
        ]);
    }

    /**
     * Create device and port on the fly for unrecognized entries.
     */
    public function createDevicePort(
        CreateDevicePortOnFlyRequest $request,
        ExpectedConnection $expectedConnection,
        CreateDevicePortOnFlyAction $action
    ): JsonResponse {
        $validated = $request->validated();

        $result = $action->execute(
            $expectedConnection,
            $validated['device_name'],
            $validated['port_label'],
            $validated['target']
        );

        return response()->json([
            'data' => new ExpectedConnectionResource($result['expected_connection']),
            'created_device' => [
                'id' => $result['device']->id,
                'name' => $result['device']->name,
                'asset_tag' => $result['device']->asset_tag,
                'was_created' => $result['device_created'],
            ],
            'created_port' => [
                'id' => $result['port']->id,
                'label' => $result['port']->label,
            ],
            'message' => 'Device and port created successfully.',
        ]);
    }

    /**
     * Calculate statistics for expected connections.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, ExpectedConnection>  $connections
     * @return array{total: int, pending_review: int, confirmed: int, skipped: int}
     */
    protected function calculateStatistics($connections): array
    {
        $stats = [
            'total' => $connections->count(),
            'pending_review' => 0,
            'confirmed' => 0,
            'skipped' => 0,
        ];

        foreach ($connections as $connection) {
            $status = $connection->status;
            if ($status === ExpectedConnectionStatus::PendingReview) {
                $stats['pending_review']++;
            } elseif ($status === ExpectedConnectionStatus::Confirmed) {
                $stats['confirmed']++;
            } elseif ($status === ExpectedConnectionStatus::Skipped) {
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    /**
     * Authorize that the current user has an authorized role.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function authorizeRole(): void
    {
        $user = auth()->user();

        if (! $user || ! $user->hasAnyRole(self::AUTHORIZED_ROLES)) {
            abort(403, 'You are not authorized to access expected connections.');
        }
    }
}
