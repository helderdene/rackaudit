<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkVerifyDevicesRequest;
use App\Http\Requests\DeviceDiscrepantRequest;
use App\Http\Requests\DeviceNotFoundRequest;
use App\Http\Requests\VerifyDeviceRequest;
use App\Http\Resources\AuditDeviceVerificationResource;
use App\Models\Audit;
use App\Models\AuditDeviceVerification;
use App\Services\AuditExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * API controller for managing audit device verifications.
 *
 * Provides endpoints for listing, viewing, verifying, and managing
 * device verifications during inventory audit execution. Supports locking
 * for multi-operator concurrent access.
 */
class AuditDeviceVerificationController extends Controller
{
    public function __construct(
        protected AuditExecutionService $executionService,
    ) {}

    /**
     * List device verifications for an audit with filtering, sorting, and search.
     */
    public function index(Request $request, Audit $audit): AnonymousResourceCollection
    {
        $filters = [
            'device_id' => $request->input('device_id'),
            'room_id' => $request->input('room_id'),
            'rack_id' => $request->input('rack_id'),
            'verification_status' => $request->input('verification_status'),
            'search' => $request->input('search'),
            'sort_by' => $request->input('sort_by', 'rack'),
            'sort_order' => $request->input('sort_order', 'asc'),
            'per_page' => $request->input('per_page', 25),
        ];

        $verifications = $this->executionService->getDeviceVerificationItems($audit, $filters);

        return AuditDeviceVerificationResource::collection($verifications);
    }

    /**
     * Get progress statistics for an inventory audit.
     */
    public function stats(Audit $audit): JsonResponse
    {
        $stats = $this->executionService->getInventoryProgressStats($audit);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Mark a device verification as verified.
     */
    public function verify(
        VerifyDeviceRequest $request,
        Audit $audit,
        AuditDeviceVerification $verification,
    ): JsonResponse {
        // Ensure verification belongs to the audit
        abort_unless($verification->audit_id === $audit->id, 404);

        // Check if locked by another user
        if ($verification->isLocked() && ! $verification->isLockedBy($request->user())) {
            return response()->json([
                'message' => 'This device is currently locked by another user.',
                'locked_by' => $verification->lockedBy?->name,
            ], 423);
        }

        $this->executionService->markDeviceVerified(
            $verification,
            $request->user(),
            $request->input('notes')
        );

        $verification->refresh();
        $verification->load(['verifiedBy']);

        return response()->json([
            'data' => new AuditDeviceVerificationResource($verification),
            'message' => 'Device verified successfully.',
            'audit_status' => $verification->audit->fresh()->status->value,
        ]);
    }

    /**
     * Mark a device verification as not found.
     */
    public function notFound(
        DeviceNotFoundRequest $request,
        Audit $audit,
        AuditDeviceVerification $verification,
    ): JsonResponse {
        // Ensure verification belongs to the audit
        abort_unless($verification->audit_id === $audit->id, 404);

        // Check if locked by another user
        if ($verification->isLocked() && ! $verification->isLockedBy($request->user())) {
            return response()->json([
                'message' => 'This device is currently locked by another user.',
                'locked_by' => $verification->lockedBy?->name,
            ], 423);
        }

        $this->executionService->markDeviceNotFound(
            $verification,
            $request->user(),
            $request->input('notes')
        );

        $verification->refresh();
        $verification->load(['verifiedBy', 'finding']);

        return response()->json([
            'data' => new AuditDeviceVerificationResource($verification),
            'message' => 'Device marked as not found. A finding has been created.',
            'audit_status' => $verification->audit->fresh()->status->value,
            'finding_id' => $verification->finding?->id,
        ]);
    }

    /**
     * Mark a device verification as discrepant.
     */
    public function discrepant(
        DeviceDiscrepantRequest $request,
        Audit $audit,
        AuditDeviceVerification $verification,
    ): JsonResponse {
        // Ensure verification belongs to the audit
        abort_unless($verification->audit_id === $audit->id, 404);

        // Check if locked by another user
        if ($verification->isLocked() && ! $verification->isLockedBy($request->user())) {
            return response()->json([
                'message' => 'This device is currently locked by another user.',
                'locked_by' => $verification->lockedBy?->name,
            ], 423);
        }

        $this->executionService->markDeviceDiscrepant(
            $verification,
            $request->user(),
            $request->input('notes')
        );

        $verification->refresh();
        $verification->load(['verifiedBy', 'finding']);

        return response()->json([
            'data' => new AuditDeviceVerificationResource($verification),
            'message' => 'Device marked as discrepant. A finding has been created.',
            'audit_status' => $verification->audit->fresh()->status->value,
            'finding_id' => $verification->finding?->id,
        ]);
    }

    /**
     * Bulk verify multiple pending device verifications.
     */
    public function bulkVerify(BulkVerifyDevicesRequest $request, Audit $audit): JsonResponse
    {
        // Verify all IDs belong to this audit
        $verificationIds = $request->getVerificationIds();
        $validIds = $audit->deviceVerifications()
            ->whereIn('id', $verificationIds)
            ->pluck('id')
            ->toArray();

        $invalidIds = array_diff($verificationIds, $validIds);

        if (! empty($invalidIds)) {
            return response()->json([
                'message' => 'Some verification IDs do not belong to this audit.',
                'invalid_ids' => $invalidIds,
            ], 422);
        }

        $results = $this->executionService->bulkVerifyDevices($verificationIds, $request->user());

        $audit->refresh();

        return response()->json([
            'message' => 'Bulk verification completed.',
            'results' => [
                'verified_count' => count($results['verified']),
                'verified_ids' => $results['verified'],
                'skipped_locked_count' => count($results['skipped_locked']),
                'skipped_locked_ids' => $results['skipped_locked'],
            ],
            'audit_status' => $audit->status->value,
        ]);
    }

    /**
     * Acquire a lock on a device verification.
     */
    public function lock(Request $request, Audit $audit, AuditDeviceVerification $verification): JsonResponse
    {
        // Ensure verification belongs to the audit
        abort_unless($verification->audit_id === $audit->id, 404);

        $locked = $this->executionService->lockDevice($verification, $request->user());

        if (! $locked) {
            $verification->load('lockedBy');

            return response()->json([
                'message' => 'This device is currently locked by another user.',
                'locked_by' => $verification->lockedBy?->name,
                'locked_at' => $verification->locked_at?->toIso8601String(),
            ], 423);
        }

        $verification->refresh();

        return response()->json([
            'message' => 'Device locked successfully.',
            'locked_until' => now()->addMinutes(AuditDeviceVerification::LOCK_EXPIRATION_MINUTES)->toIso8601String(),
        ]);
    }

    /**
     * Release a lock on a device verification.
     */
    public function unlock(Request $request, Audit $audit, AuditDeviceVerification $verification): Response
    {
        // Ensure verification belongs to the audit
        abort_unless($verification->audit_id === $audit->id, 404);

        // Only allow unlocking if user owns the lock or lock is expired
        if ($verification->isLocked() && ! $verification->isLockedBy($request->user())) {
            abort(403, 'You can only unlock devices that you have locked.');
        }

        $this->executionService->unlockDevice($verification);

        return response()->noContent();
    }
}
