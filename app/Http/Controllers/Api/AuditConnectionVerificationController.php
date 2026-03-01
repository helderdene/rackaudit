<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkVerifyRequest;
use App\Http\Requests\MarkDiscrepantRequest;
use App\Http\Requests\VerifyConnectionRequest;
use App\Http\Resources\AuditConnectionVerificationResource;
use App\Models\Audit;
use App\Models\AuditConnectionVerification;
use App\Services\AuditExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * API controller for managing audit connection verifications.
 *
 * Provides endpoints for listing, viewing, verifying, and managing
 * connection verifications during audit execution. Supports locking
 * for multi-operator concurrent access.
 */
class AuditConnectionVerificationController extends Controller
{
    public function __construct(
        protected AuditExecutionService $executionService,
    ) {}

    /**
     * List verifications for an audit with filtering, sorting, and search.
     */
    public function index(Request $request, Audit $audit): AnonymousResourceCollection
    {
        $filters = [
            'comparison_status' => $request->input('comparison_status'),
            'verification_status' => $request->input('verification_status'),
            'search' => $request->input('search'),
            'sort_by' => $request->input('sort_by', 'id'),
            'sort_order' => $request->input('sort_order', 'asc'),
            'per_page' => $request->input('per_page', 25),
        ];

        $verifications = $this->executionService->getVerificationItems($audit, $filters);

        return AuditConnectionVerificationResource::collection($verifications);
    }

    /**
     * Get a single verification's details.
     */
    public function show(Audit $audit, AuditConnectionVerification $verification): AuditConnectionVerificationResource
    {
        // Ensure verification belongs to the audit
        abort_unless($verification->audit_id === $audit->id, 404);

        $verification->load([
            'expectedConnection.sourcePort.device',
            'expectedConnection.destPort.device',
            'expectedConnection.sourceDevice',
            'expectedConnection.destDevice',
            'connection.sourcePort.device',
            'connection.destinationPort.device',
            'verifiedBy',
            'lockedBy',
        ]);

        return new AuditConnectionVerificationResource($verification);
    }

    /**
     * Mark a verification as verified.
     */
    public function verify(
        VerifyConnectionRequest $request,
        Audit $audit,
        AuditConnectionVerification $verification,
    ): JsonResponse {
        // Ensure verification belongs to the audit
        abort_unless($verification->audit_id === $audit->id, 404);

        // Check if locked by another user
        if ($verification->isLocked() && ! $verification->isLockedBy($request->user())) {
            return response()->json([
                'message' => 'This connection is currently locked by another user.',
                'locked_by' => $verification->lockedBy?->name,
            ], 423);
        }

        $this->executionService->markVerified(
            $verification,
            $request->user(),
            $request->input('notes')
        );

        $verification->refresh();
        $verification->load(['verifiedBy']);

        return response()->json([
            'data' => new AuditConnectionVerificationResource($verification),
            'message' => 'Connection verified successfully.',
            'audit_status' => $verification->audit->fresh()->status->value,
        ]);
    }

    /**
     * Mark a verification as discrepant.
     */
    public function discrepant(
        MarkDiscrepantRequest $request,
        Audit $audit,
        AuditConnectionVerification $verification,
    ): JsonResponse {
        // Ensure verification belongs to the audit
        abort_unless($verification->audit_id === $audit->id, 404);

        // Check if locked by another user
        if ($verification->isLocked() && ! $verification->isLockedBy($request->user())) {
            return response()->json([
                'message' => 'This connection is currently locked by another user.',
                'locked_by' => $verification->lockedBy?->name,
            ], 423);
        }

        $this->executionService->markDiscrepant(
            $verification,
            $request->user(),
            $request->getDiscrepancyType(),
            $request->input('notes')
        );

        $verification->refresh();
        $verification->load(['verifiedBy', 'finding']);

        return response()->json([
            'data' => new AuditConnectionVerificationResource($verification),
            'message' => 'Connection marked as discrepant. A finding has been created.',
            'audit_status' => $verification->audit->fresh()->status->value,
            'finding_id' => $verification->finding?->id,
        ]);
    }

    /**
     * Acquire a lock on a verification.
     */
    public function lock(Request $request, Audit $audit, AuditConnectionVerification $verification): JsonResponse
    {
        // Ensure verification belongs to the audit
        abort_unless($verification->audit_id === $audit->id, 404);

        $locked = $this->executionService->lockConnection($verification, $request->user());

        if (! $locked) {
            $verification->load('lockedBy');

            return response()->json([
                'message' => 'This connection is currently locked by another user.',
                'locked_by' => $verification->lockedBy?->name,
                'locked_at' => $verification->locked_at?->toIso8601String(),
            ], 423);
        }

        $verification->refresh();

        return response()->json([
            'message' => 'Connection locked successfully.',
            'locked_until' => now()->addMinutes(AuditConnectionVerification::LOCK_EXPIRATION_MINUTES)->toIso8601String(),
        ]);
    }

    /**
     * Release a lock on a verification.
     */
    public function unlock(Request $request, Audit $audit, AuditConnectionVerification $verification): Response
    {
        // Ensure verification belongs to the audit
        abort_unless($verification->audit_id === $audit->id, 404);

        // Only allow unlocking if user owns the lock or lock is expired
        if ($verification->isLocked() && ! $verification->isLockedBy($request->user())) {
            abort(403, 'You can only unlock connections that you have locked.');
        }

        $this->executionService->unlockConnection($verification);

        return response()->noContent();
    }

    /**
     * Bulk verify multiple matched connections.
     */
    public function bulkVerify(BulkVerifyRequest $request, Audit $audit): JsonResponse
    {
        // Verify all IDs belong to this audit
        $verificationIds = $request->getVerificationIds();
        $validIds = $audit->verifications()
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

        $results = $this->executionService->bulkVerify($verificationIds, $request->user());

        $audit->refresh();

        return response()->json([
            'message' => 'Bulk verification completed.',
            'results' => [
                'verified_count' => count($results['verified']),
                'verified_ids' => $results['verified'],
                'skipped_locked_count' => count($results['skipped_locked']),
                'skipped_locked_ids' => $results['skipped_locked'],
                'skipped_not_matched_count' => count($results['skipped_not_matched']),
                'skipped_not_matched_ids' => $results['skipped_not_matched'],
            ],
            'audit_status' => $audit->status->value,
        ]);
    }

    /**
     * Get progress statistics for an audit.
     */
    public function stats(Audit $audit): JsonResponse
    {
        $stats = $this->executionService->getProgressStats($audit);

        return response()->json([
            'data' => $stats,
        ]);
    }
}
