<?php

namespace App\Http\Controllers;

use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Http\Requests\BulkFindingAssignRequest;
use App\Http\Requests\BulkFindingStatusRequest;
use App\Http\Requests\QuickTransitionRequest;
use App\Http\Requests\UpdateFindingRequest;
use App\Models\Finding;
use App\Models\FindingCategory;
use App\Models\FindingStatusTransition;
use App\Models\User;
use App\Notifications\FindingAssignedNotification;
use App\Notifications\FindingReassignedNotification;
use App\Notifications\FindingStatusChangedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class FindingController extends Controller
{
    /**
     * Roles that have full access to all findings.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Chunk size for bulk operations.
     */
    private const BULK_CHUNK_SIZE = 100;

    /**
     * Display a paginated list of findings with filters.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $query = Finding::query()
            ->with(['audit', 'assignee', 'category']);

        // Filter by user access: non-admin users see only findings from audits they are assigned to
        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            $query->whereHas('audit.assignees', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $statusEnum = FindingStatus::tryFrom($status);
            if ($statusEnum) {
                $query->filterByStatus($statusEnum);
            }
        }

        // Filter by severity
        if ($severity = $request->input('severity')) {
            $severityEnum = FindingSeverity::tryFrom($severity);
            if ($severityEnum) {
                $query->filterBySeverity($severityEnum);
            }
        }

        // Filter by category
        if ($categoryId = $request->input('category')) {
            $query->filterByCategory((int) $categoryId);
        }

        // Filter by audit
        if ($auditId = $request->input('audit_id')) {
            $query->where('audit_id', $auditId);
        }

        // Filter by assignee
        if ($assigneeId = $request->input('assigned_to')) {
            $query->filterByAssignee((int) $assigneeId);
        }

        // Filter by due date status
        if ($dueDateStatus = $request->input('due_date_status')) {
            match ($dueDateStatus) {
                'overdue' => $query->overdue(),
                'due_soon' => $query->dueSoon(),
                'no_due_date' => $query->noDueDate(),
                default => null,
            };
        }

        // Search by title or description
        if ($search = $request->input('search')) {
            $query->searchByTitleOrDescription($search);
        }

        $findings = $query->orderByDesc('created_at')
            ->paginate(15)
            ->through(function (Finding $finding) {
                return [
                    'id' => $finding->id,
                    'title' => $finding->title,
                    'description' => $finding->description,
                    'status' => $finding->status->value,
                    'status_label' => $finding->status->label(),
                    'status_color' => $finding->status->color(),
                    'severity' => $finding->severity->value,
                    'severity_label' => $finding->severity->label(),
                    'severity_color' => $finding->severity->color(),
                    'due_date' => $finding->due_date?->format('Y-m-d'),
                    'is_overdue' => $finding->isOverdue(),
                    'is_due_soon' => $finding->isDueSoon(),
                    'audit' => $finding->audit ? [
                        'id' => $finding->audit->id,
                        'name' => $finding->audit->name,
                    ] : null,
                    'assignee' => $finding->assignee ? [
                        'id' => $finding->assignee->id,
                        'name' => $finding->assignee->name,
                        'email' => $finding->assignee->email,
                    ] : null,
                    'category' => $finding->category ? [
                        'id' => $finding->category->id,
                        'name' => $finding->category->name,
                    ] : null,
                    'created_at' => $finding->created_at?->format('Y-m-d H:i:s'),
                ];
            });

        // Prepare filter options
        $statusOptions = collect(FindingStatus::cases())->map(fn ($status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ])->values()->toArray();

        $severityOptions = collect(FindingSeverity::cases())->map(fn ($severity) => [
            'value' => $severity->value,
            'label' => $severity->label(),
        ])->values()->toArray();

        $categoryOptions = FindingCategory::orderedByDefault()
            ->get()
            ->map(fn (FindingCategory $category) => [
                'value' => $category->id,
                'label' => $category->name,
            ])->toArray();

        // Get assignable users (Operators and Auditors, or all active users for admins)
        $assigneeOptions = User::where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'value' => $user->id,
                'label' => $user->name,
            ])->toArray();

        // Due date filter options
        $dueDateOptions = [
            ['value' => 'overdue', 'label' => 'Overdue'],
            ['value' => 'due_soon', 'label' => 'Due Soon'],
            ['value' => 'no_due_date', 'label' => 'No Due Date'],
        ];

        return Inertia::render('Findings/Index', [
            'findings' => $findings,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
                'severity' => $request->input('severity', ''),
                'category' => $request->input('category', ''),
                'audit_id' => $request->input('audit_id', ''),
                'assigned_to' => $request->input('assigned_to', ''),
                'due_date_status' => $request->input('due_date_status', ''),
            ],
            'statusOptions' => $statusOptions,
            'severityOptions' => $severityOptions,
            'categoryOptions' => $categoryOptions,
            'assigneeOptions' => $assigneeOptions,
            'dueDateOptions' => $dueDateOptions,
        ]);
    }

    /**
     * Display the specified finding.
     */
    public function show(Request $request, Finding $finding): InertiaResponse
    {
        $user = $request->user();

        // Authorization check: non-admin users can only see findings from their audits
        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            if (! $finding->audit?->assignees()->where('user_id', $user->id)->exists()) {
                abort(403, 'You do not have access to view this finding.');
            }
        }

        $finding->load(['audit.datacenter', 'assignee', 'category', 'evidence', 'resolvedBy', 'statusTransitions.user']);

        // Prepare filter options for the edit form
        $statusOptions = collect(FindingStatus::cases())->map(fn ($status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ])->values()->toArray();

        $severityOptions = collect(FindingSeverity::cases())->map(fn ($severity) => [
            'value' => $severity->value,
            'label' => $severity->label(),
        ])->values()->toArray();

        $categoryOptions = FindingCategory::orderedByDefault()
            ->get()
            ->map(fn (FindingCategory $category) => [
                'value' => $category->id,
                'label' => $category->name,
            ])->toArray();

        $assigneeOptions = User::where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'value' => $user->id,
                'label' => $user->name,
            ])->toArray();

        // Determine allowed status transitions for the current user
        $allowedTransitions = $this->getAllowedStatusTransitions($finding, $user);

        // Check if user can edit this finding
        $canEdit = $this->canUserEditFinding($finding, $user);

        // Get quick actions based on current status
        $quickActions = $this->getQuickActions($finding, $user);

        // Calculate time metrics
        $timeMetrics = [
            'time_to_first_response' => $finding->getTimeToFirstResponse(),
            'total_resolution_time' => $finding->getTotalResolutionTime(),
        ];

        // Format status transitions for timeline
        $statusTransitions = $finding->statusTransitions
            ->sortByDesc('transitioned_at')
            ->map(fn ($transition) => [
                'id' => $transition->id,
                'from_status' => $transition->from_status->value,
                'from_status_label' => $transition->from_status->label(),
                'to_status' => $transition->to_status->value,
                'to_status_label' => $transition->to_status->label(),
                'user' => $transition->user ? [
                    'id' => $transition->user->id,
                    'name' => $transition->user->name,
                ] : null,
                'notes' => $transition->notes,
                'transitioned_at' => $transition->transitioned_at?->format('Y-m-d H:i:s'),
            ])->values()->toArray();

        return Inertia::render('Findings/Show', [
            'finding' => [
                'id' => $finding->id,
                'title' => $finding->title,
                'description' => $finding->description,
                'discrepancy_type' => $finding->discrepancy_type?->value,
                'discrepancy_type_label' => $finding->discrepancy_type?->label(),
                'status' => $finding->status->value,
                'status_label' => $finding->status->label(),
                'status_color' => $finding->status->color(),
                'severity' => $finding->severity->value,
                'severity_label' => $finding->severity->label(),
                'severity_color' => $finding->severity->color(),
                'resolution_notes' => $finding->resolution_notes,
                'resolved_at' => $finding->resolved_at?->format('Y-m-d H:i:s'),
                'resolved_by' => $finding->resolvedBy ? [
                    'id' => $finding->resolvedBy->id,
                    'name' => $finding->resolvedBy->name,
                ] : null,
                'due_date' => $finding->due_date?->format('Y-m-d'),
                'is_overdue' => $finding->isOverdue(),
                'is_due_soon' => $finding->isDueSoon(),
                'audit' => $finding->audit ? [
                    'id' => $finding->audit->id,
                    'name' => $finding->audit->name,
                    'datacenter' => $finding->audit->datacenter ? [
                        'id' => $finding->audit->datacenter->id,
                        'name' => $finding->audit->datacenter->name,
                    ] : null,
                ] : null,
                'assignee' => $finding->assignee ? [
                    'id' => $finding->assignee->id,
                    'name' => $finding->assignee->name,
                    'email' => $finding->assignee->email,
                ] : null,
                'category' => $finding->category ? [
                    'id' => $finding->category->id,
                    'name' => $finding->category->name,
                    'description' => $finding->category->description,
                ] : null,
                'evidence' => $finding->evidence->map(fn ($evidence) => [
                    'id' => $evidence->id,
                    'type' => $evidence->type->value,
                    'content' => $evidence->content,
                    'file_path' => $evidence->file_path,
                    'original_filename' => $evidence->original_filename,
                    'mime_type' => $evidence->mime_type,
                    'created_at' => $evidence->created_at?->format('Y-m-d H:i:s'),
                ])->toArray(),
                'created_at' => $finding->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $finding->updated_at?->format('Y-m-d H:i:s'),
            ],
            'statusOptions' => $statusOptions,
            'severityOptions' => $severityOptions,
            'categoryOptions' => $categoryOptions,
            'assigneeOptions' => $assigneeOptions,
            'allowedTransitions' => $allowedTransitions,
            'canEdit' => $canEdit,
            'quickActions' => $quickActions,
            'statusTransitions' => $statusTransitions,
            'timeMetrics' => $timeMetrics,
        ]);
    }

    /**
     * Update the specified finding.
     */
    public function update(UpdateFindingRequest $request, Finding $finding): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Track original values for notifications
        $originalStatus = $finding->status;
        $originalAssigneeId = $finding->assigned_to;

        // Track if status is changing to Resolved
        $isResolvingNow = isset($validated['status'])
            && $validated['status'] === FindingStatus::Resolved->value
            && $finding->status !== FindingStatus::Resolved;

        // Track if status is changing
        $isStatusChanging = isset($validated['status'])
            && $validated['status'] !== $finding->status->value;

        // Track if assignee is changing
        $isAssigneeChanging = isset($validated['assigned_to'])
            && $validated['assigned_to'] !== $finding->assigned_to;

        // Update the finding
        $finding->fill($validated);

        // Set resolved_at and resolved_by when resolving
        if ($isResolvingNow) {
            $finding->resolved_at = now();
            $finding->resolved_by = $user->id;
        }

        // Clear resolved_at and resolved_by if reopening
        if (isset($validated['status']) && $validated['status'] !== FindingStatus::Resolved->value && $originalStatus === FindingStatus::Resolved) {
            $finding->resolved_at = null;
            $finding->resolved_by = null;
        }

        $finding->save();

        // Create status transition record if status changed
        if ($isStatusChanging) {
            $newStatus = FindingStatus::from($validated['status']);
            $this->createStatusTransition($finding, $originalStatus, $newStatus, $user);

            // Dispatch notification to assignee if they exist and are not the one making the change
            if ($finding->assignee && $finding->assigned_to !== $user->id) {
                $finding->assignee->notify(new FindingStatusChangedNotification($finding, $originalStatus, $newStatus));
            }
        }

        // Handle assignment change notifications
        if ($isAssigneeChanging) {
            $this->handleAssignmentChangeNotifications($finding, $originalAssigneeId, $validated['assigned_to']);
        }

        return redirect()->back()->with('success', 'Finding updated successfully.');
    }

    /**
     * Quick transition a finding to a new status.
     */
    public function transition(QuickTransitionRequest $request, Finding $finding): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $originalStatus = $finding->status;
        $targetStatus = FindingStatus::from($validated['target_status']);

        // Update finding status
        $finding->status = $targetStatus;

        // Handle resolution
        if ($targetStatus === FindingStatus::Resolved) {
            $finding->resolved_at = now();
            $finding->resolved_by = $user->id;
            if (! empty($validated['notes'])) {
                $finding->resolution_notes = $validated['notes'];
            }
        }

        // Clear resolution data if reopening
        if ($originalStatus === FindingStatus::Resolved && $targetStatus !== FindingStatus::Resolved) {
            $finding->resolved_at = null;
            $finding->resolved_by = null;
        }

        $finding->save();

        // Create status transition record
        $this->createStatusTransition($finding, $originalStatus, $targetStatus, $user, $validated['notes'] ?? null);

        // Dispatch notification to assignee if they exist and are not the one making the change
        if ($finding->assignee && $finding->assigned_to !== $user->id) {
            $finding->assignee->notify(new FindingStatusChangedNotification($finding, $originalStatus, $targetStatus));
        }

        return response()->json([
            'success' => true,
            'message' => 'Finding status updated successfully.',
            'finding' => [
                'id' => $finding->id,
                'status' => $finding->status->value,
                'status_label' => $finding->status->label(),
                'status_color' => $finding->status->color(),
            ],
        ]);
    }

    /**
     * Bulk assign findings to a user.
     */
    public function bulkAssign(BulkFindingAssignRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $findingIds = $validated['finding_ids'];
        $newAssigneeId = $validated['assigned_to'];

        $newAssignee = User::find($newAssigneeId);
        $successCount = 0;
        $failures = [];

        // Process in chunks
        $chunks = array_chunk($findingIds, self::BULK_CHUNK_SIZE);

        foreach ($chunks as $chunk) {
            $findings = Finding::with('assignee')->whereIn('id', $chunk)->get();

            foreach ($findings as $finding) {
                try {
                    $originalAssigneeId = $finding->assigned_to;

                    // Skip if already assigned to the same user
                    if ($originalAssigneeId === $newAssigneeId) {
                        $successCount++;

                        continue;
                    }

                    $finding->assigned_to = $newAssigneeId;
                    $finding->save();

                    // Handle notifications
                    $this->handleAssignmentChangeNotifications($finding, $originalAssigneeId, $newAssigneeId);

                    $successCount++;
                } catch (\Exception $e) {
                    $failures[] = [
                        'finding_id' => $finding->id,
                        'title' => $finding->title,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$successCount} finding(s) assigned successfully.",
            'success_count' => $successCount,
            'failure_count' => count($failures),
            'failures' => $failures,
        ]);
    }

    /**
     * Bulk change status for multiple findings.
     */
    public function bulkStatus(BulkFindingStatusRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $findingIds = $validated['finding_ids'];
        $targetStatus = FindingStatus::from($validated['status']);

        $successCount = 0;
        $failures = [];

        // Process in chunks
        $chunks = array_chunk($findingIds, self::BULK_CHUNK_SIZE);

        foreach ($chunks as $chunk) {
            $findings = Finding::with('assignee')->whereIn('id', $chunk)->get();

            foreach ($findings as $finding) {
                try {
                    $originalStatus = $finding->status;

                    // Skip if already at target status
                    if ($originalStatus === $targetStatus) {
                        $successCount++;

                        continue;
                    }

                    // Update finding status
                    $finding->status = $targetStatus;

                    // Handle resolution
                    if ($targetStatus === FindingStatus::Resolved) {
                        $finding->resolved_at = now();
                        $finding->resolved_by = $user->id;
                    }

                    // Clear resolution data if reopening
                    if ($originalStatus === FindingStatus::Resolved && $targetStatus !== FindingStatus::Resolved) {
                        $finding->resolved_at = null;
                        $finding->resolved_by = null;
                    }

                    $finding->save();

                    // Create status transition record
                    $this->createStatusTransition($finding, $originalStatus, $targetStatus, $user);

                    // Dispatch notification to assignee if they exist and are not the one making the change
                    if ($finding->assignee && $finding->assigned_to !== $user->id) {
                        $finding->assignee->notify(new FindingStatusChangedNotification($finding, $originalStatus, $targetStatus));
                    }

                    $successCount++;
                } catch (\Exception $e) {
                    $failures[] = [
                        'finding_id' => $finding->id,
                        'title' => $finding->title,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$successCount} finding(s) status updated successfully.",
            'success_count' => $successCount,
            'failure_count' => count($failures),
            'failures' => $failures,
        ]);
    }

    /**
     * Create a status transition record.
     */
    private function createStatusTransition(
        Finding $finding,
        FindingStatus $fromStatus,
        FindingStatus $toStatus,
        User $user,
        ?string $notes = null
    ): FindingStatusTransition {
        return FindingStatusTransition::create([
            'finding_id' => $finding->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'user_id' => $user->id,
            'notes' => $notes,
            'transitioned_at' => now(),
        ]);
    }

    /**
     * Handle notifications for assignment changes.
     */
    private function handleAssignmentChangeNotifications(Finding $finding, ?int $oldAssigneeId, ?int $newAssigneeId): void
    {
        // Notify new assignee
        if ($newAssigneeId) {
            $newAssignee = User::find($newAssigneeId);
            if ($newAssignee) {
                $newAssignee->notify(new FindingAssignedNotification($finding));

                // Notify old assignee about reassignment
                if ($oldAssigneeId && $oldAssigneeId !== $newAssigneeId) {
                    $oldAssignee = User::find($oldAssigneeId);
                    if ($oldAssignee) {
                        $oldAssignee->notify(new FindingReassignedNotification($finding, $newAssignee));
                    }
                }
            }
        }
    }

    /**
     * Get allowed status transitions for a user.
     *
     * @return array<string, string>
     */
    private function getAllowedStatusTransitions(Finding $finding, User $user): array
    {
        $currentStatus = $finding->status;
        $isAdmin = $user->hasAnyRole(self::ADMIN_ROLES);

        // Admins can transition to any status
        if ($isAdmin) {
            return collect(FindingStatus::cases())
                ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
                ->toArray();
        }

        // Regular users can only use valid workflow transitions
        $allowedStatuses = collect(FindingStatus::cases())
            ->filter(fn ($status) => $currentStatus->canTransitionTo($status) || $status === $currentStatus)
            ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
            ->toArray();

        return $allowedStatuses;
    }

    /**
     * Get quick actions available for the current finding status.
     *
     * @return array<array{action: string, label: string, status: string, variant: string}>
     */
    private function getQuickActions(Finding $finding, User $user): array
    {
        $currentStatus = $finding->status;
        $isAdmin = $user->hasAnyRole(self::ADMIN_ROLES);
        $actions = [];

        // Start Working: Open -> In Progress
        if ($currentStatus === FindingStatus::Open) {
            $actions[] = [
                'action' => 'start_working',
                'label' => 'Start Working',
                'status' => FindingStatus::InProgress->value,
                'variant' => 'primary',
            ];
        }

        // Submit for Review: In Progress -> Pending Review
        if ($currentStatus === FindingStatus::InProgress) {
            $actions[] = [
                'action' => 'submit_for_review',
                'label' => 'Submit for Review',
                'status' => FindingStatus::PendingReview->value,
                'variant' => 'primary',
            ];
        }

        // Approve & Close: Pending Review -> Resolved (requires resolution notes)
        if ($currentStatus === FindingStatus::PendingReview) {
            $actions[] = [
                'action' => 'approve_and_close',
                'label' => 'Approve & Close',
                'status' => FindingStatus::Resolved->value,
                'variant' => 'success',
                'requires_notes' => true,
            ];
        }

        // Defer: Open or In Progress -> Deferred
        if (in_array($currentStatus, [FindingStatus::Open, FindingStatus::InProgress])) {
            $actions[] = [
                'action' => 'defer',
                'label' => 'Defer',
                'status' => FindingStatus::Deferred->value,
                'variant' => 'secondary',
            ];
        }

        // Reopen: Deferred -> Open, or Resolved -> Open (admins only for Resolved)
        if ($currentStatus === FindingStatus::Deferred) {
            $actions[] = [
                'action' => 'reopen',
                'label' => 'Reopen',
                'status' => FindingStatus::Open->value,
                'variant' => 'warning',
            ];
        }

        if ($currentStatus === FindingStatus::Resolved && $isAdmin) {
            $actions[] = [
                'action' => 'reopen',
                'label' => 'Reopen',
                'status' => FindingStatus::Open->value,
                'variant' => 'warning',
            ];
        }

        return $actions;
    }

    /**
     * Check if a user can edit a finding.
     */
    private function canUserEditFinding(Finding $finding, User $user): bool
    {
        // Admins and IT Managers can always edit
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }

        // User is assigned to this finding
        if ($finding->assigned_to === $user->id) {
            return true;
        }

        // User is assigned to the parent audit
        if ($finding->audit && $finding->audit->assignees()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return false;
    }
}
