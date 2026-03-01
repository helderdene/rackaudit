<?php

namespace App\Http\Controllers;

use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Http\Requests\ApproveEquipmentMoveRequest;
use App\Http\Requests\RejectEquipmentMoveRequest;
use App\Http\Requests\StoreEquipmentMoveRequest;
use App\Http\Resources\EquipmentMoveResource;
use App\Models\Device;
use App\Models\EquipmentMove;
use App\Services\EquipmentMoveReportService;
use App\Services\EquipmentMoveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller for managing equipment move requests.
 *
 * Handles the complete lifecycle of device moves including creation,
 * viewing, approval/rejection, cancellation workflows, and PDF work order
 * generation.
 */
class EquipmentMoveController extends Controller
{
    public function __construct(
        protected EquipmentMoveService $moveService,
        protected EquipmentMoveReportService $reportService
    ) {}

    /**
     * Display a listing of equipment moves.
     *
     * Supports filtering by status, device_id, rack_id, and date range.
     */
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        Gate::authorize('viewAny', EquipmentMove::class);

        $query = EquipmentMove::query()
            ->with([
                'device.deviceType',
                'sourceRack.row.room.datacenter',
                'destinationRack.row.room.datacenter',
                'requester',
                'approver',
            ])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->filled('status')) {
            $query->whereStatus($request->input('status'));
        }

        // Filter by device_id
        if ($request->filled('device_id')) {
            $query->forDevice((int) $request->input('device_id'));
        }

        // Filter by rack_id (source or destination)
        if ($request->filled('rack_id')) {
            $query->forRack((int) $request->input('rack_id'));
        }

        // Filter by date range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->requestedBetween(
                $request->input('start_date'),
                $request->input('end_date')
            );
        }

        // Return JSON for API requests
        if ($request->wantsJson() || $request->is('api/*')) {
            $moves = $query->paginate(15)->withQueryString();

            return response()->json([
                'data' => EquipmentMoveResource::collection($moves)->resolve(),
                'links' => $moves->linkCollection()->toArray(),
                'meta' => [
                    'current_page' => $moves->currentPage(),
                    'last_page' => $moves->lastPage(),
                    'per_page' => $moves->perPage(),
                    'total' => $moves->total(),
                ],
            ]);
        }

        $moves = $query->paginate(15)->withQueryString();

        return Inertia::render('EquipmentMoves/Index', [
            'moves' => [
                'data' => EquipmentMoveResource::collection($moves)->resolve(),
                'links' => $moves->linkCollection()->toArray(),
                'current_page' => $moves->currentPage(),
                'last_page' => $moves->lastPage(),
                'per_page' => $moves->perPage(),
                'total' => $moves->total(),
            ],
            'statusOptions' => $this->getStatusOptions(),
            'filters' => [
                'status' => $request->input('status', ''),
                'device_id' => $request->input('device_id', ''),
                'rack_id' => $request->input('rack_id', ''),
                'start_date' => $request->input('start_date', ''),
                'end_date' => $request->input('end_date', ''),
            ],
            'canCreate' => Gate::allows('create', EquipmentMove::class),
        ]);
    }

    /**
     * Store a newly created equipment move request.
     */
    public function store(StoreEquipmentMoveRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $device = Device::findOrFail($validated['device_id']);

        // Validate destination position availability
        $isPositionValid = $this->moveService->validateDestinationPosition(
            (int) $validated['destination_rack_id'],
            (int) $validated['destination_start_u'],
            DeviceRackFace::from($validated['destination_rack_face']),
            DeviceWidthType::from($validated['destination_width_type']),
            (int) ceil($device->u_height),
            $device->id // Exclude the device itself for collision detection
        );

        if (! $isPositionValid) {
            return response()->json([
                'message' => 'The destination position is not available.',
                'errors' => [
                    'destination_start_u' => ['The selected position conflicts with existing equipment or exceeds rack bounds.'],
                ],
            ], 422);
        }

        $move = $this->moveService->createMoveRequest(
            $device,
            [
                'rack_id' => $validated['destination_rack_id'],
                'start_u' => $validated['destination_start_u'],
                'rack_face' => DeviceRackFace::from($validated['destination_rack_face']),
                'width_type' => DeviceWidthType::from($validated['destination_width_type']),
            ],
            $request->user(),
            $validated['operator_notes'] ?? null
        );

        $move->load([
            'device.deviceType',
            'sourceRack.row.room.datacenter',
            'destinationRack.row.room.datacenter',
            'requester',
        ]);

        return response()->json([
            'data' => new EquipmentMoveResource($move),
            'message' => 'Move request created successfully.',
        ], 201);
    }

    /**
     * Display the specified equipment move.
     */
    public function show(Request $request, EquipmentMove $equipmentMove): InertiaResponse|JsonResponse
    {
        Gate::authorize('view', $equipmentMove);

        $equipmentMove->load([
            'device.deviceType',
            'sourceRack.row.room.datacenter',
            'destinationRack.row.room.datacenter',
            'requester',
            'approver',
        ]);

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'data' => new EquipmentMoveResource($equipmentMove),
            ]);
        }

        return Inertia::render('EquipmentMoves/Show', [
            'move' => (new EquipmentMoveResource($equipmentMove))->resolve(),
        ]);
    }

    /**
     * Approve a pending equipment move request.
     *
     * Approving a move will automatically execute it, updating the device
     * placement and disconnecting all active connections.
     */
    public function approve(ApproveEquipmentMoveRequest $request, EquipmentMove $equipmentMove): JsonResponse
    {
        $validated = $request->validated();

        $success = $this->moveService->approveMove(
            $equipmentMove,
            $request->user(),
            $validated['approval_notes'] ?? null
        );

        if (! $success) {
            return response()->json([
                'message' => 'Failed to approve the move request. It may have already been processed.',
            ], 422);
        }

        $equipmentMove->refresh();
        $equipmentMove->load([
            'device.deviceType',
            'sourceRack.row.room.datacenter',
            'destinationRack.row.room.datacenter',
            'requester',
            'approver',
        ]);

        return response()->json([
            'data' => new EquipmentMoveResource($equipmentMove),
            'message' => 'Move request approved and executed successfully.',
        ]);
    }

    /**
     * Reject a pending equipment move request.
     *
     * Rejection requires notes explaining the reason for rejection.
     */
    public function reject(RejectEquipmentMoveRequest $request, EquipmentMove $equipmentMove): JsonResponse
    {
        $validated = $request->validated();

        $success = $this->moveService->rejectMove(
            $equipmentMove,
            $request->user(),
            $validated['approval_notes']
        );

        if (! $success) {
            return response()->json([
                'message' => 'Failed to reject the move request. It may have already been processed.',
            ], 422);
        }

        $equipmentMove->refresh();
        $equipmentMove->load([
            'device.deviceType',
            'sourceRack.row.room.datacenter',
            'destinationRack.row.room.datacenter',
            'requester',
            'approver',
        ]);

        return response()->json([
            'data' => new EquipmentMoveResource($equipmentMove),
            'message' => 'Move request rejected.',
        ]);
    }

    /**
     * Cancel a pending equipment move request.
     *
     * Requesters can cancel their own moves, managers can cancel any.
     */
    public function cancel(Request $request, EquipmentMove $equipmentMove): JsonResponse
    {
        Gate::authorize('cancel', $equipmentMove);

        $success = $this->moveService->cancelMove($equipmentMove, $request->user());

        if (! $success) {
            return response()->json([
                'message' => 'Failed to cancel the move request. It may have already been processed.',
            ], 422);
        }

        $equipmentMove->refresh();
        $equipmentMove->load([
            'device.deviceType',
            'sourceRack.row.room.datacenter',
            'destinationRack.row.room.datacenter',
            'requester',
            'approver',
        ]);

        return response()->json([
            'data' => new EquipmentMoveResource($equipmentMove),
            'message' => 'Move request cancelled.',
        ]);
    }

    /**
     * Download the work order PDF for an equipment move.
     *
     * Generates a printable PDF work order containing device details,
     * source and destination locations, connection information, and
     * signature fields for datacenter floor operations.
     */
    public function downloadWorkOrder(Request $request, EquipmentMove $equipmentMove): Response
    {
        Gate::authorize('downloadWorkOrder', $equipmentMove);

        $pdf = $this->reportService->streamWorkOrder($equipmentMove, $request->user());

        $filename = "move-work-order-{$equipmentMove->id}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Get status options for filter dropdown.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getStatusOptions(): array
    {
        return [
            ['value' => 'pending_approval', 'label' => 'Pending Approval'],
            ['value' => 'approved', 'label' => 'Approved'],
            ['value' => 'rejected', 'label' => 'Rejected'],
            ['value' => 'executed', 'label' => 'Executed'],
            ['value' => 'cancelled', 'label' => 'Cancelled'],
        ];
    }
}
