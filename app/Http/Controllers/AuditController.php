<?php

namespace App\Http\Controllers;

use App\Enums\AuditScopeType;
use App\Enums\AuditStatus;
use App\Enums\AuditType;
use App\Enums\DeviceVerificationStatus;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Http\Requests\StoreAuditRequest;
use App\Models\Audit;
use App\Models\AuditReport;
use App\Models\Datacenter;
use App\Models\Finding;
use App\Models\ImplementationFile;
use App\Models\User;
use App\Notifications\AuditAssignedNotification;
use App\Notifications\AuditReassignedNotification;
use App\Services\AuditExecutionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class AuditController extends Controller
{
    /**
     * Roles that have full access to all audits.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Roles that can access the audit dashboard.
     *
     * @var array<string>
     */
    private const DASHBOARD_ROLES = [
        'Administrator',
        'IT Manager',
        'Auditor',
    ];

    /**
     * Roles that can be assigned to execute audits.
     *
     * @var array<string>
     */
    private const ASSIGNABLE_ROLES = [
        'Operator',
        'Auditor',
    ];

    public function __construct(
        protected AuditExecutionService $executionService,
    ) {}

    /**
     * Display a paginated list of audits with filters.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $query = Audit::query()
            ->with(['datacenter', 'room', 'assignees', 'creator']);

        // Filter by user access: non-admin users see only audits they are assigned to
        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            $query->whereHas('assignees', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by type
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Search by name
        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $audits = $query->orderByDesc('created_at')
            ->paginate(15)
            ->through(function (Audit $audit) {
                return [
                    'id' => $audit->id,
                    'name' => $audit->name,
                    'description' => $audit->description,
                    'due_date' => $audit->due_date?->format('Y-m-d'),
                    'type' => $audit->type->value,
                    'type_label' => $audit->type->label(),
                    'scope_type' => $audit->scope_type->value,
                    'scope_type_label' => $audit->scope_type->label(),
                    'status' => $audit->status->value,
                    'status_label' => $audit->status->label(),
                    'datacenter' => [
                        'id' => $audit->datacenter->id,
                        'name' => $audit->datacenter->name,
                    ],
                    'room' => $audit->room ? [
                        'id' => $audit->room->id,
                        'name' => $audit->room->name,
                    ] : null,
                    'assignees_count' => $audit->assignees->count(),
                    'created_at' => $audit->created_at?->format('Y-m-d H:i:s'),
                ];
            });

        return Inertia::render('Audits/Index', [
            'audits' => $audits,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
                'type' => $request->input('type', ''),
            ],
            'statusOptions' => collect(AuditStatus::cases())->map(fn ($status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values()->toArray(),
            'typeOptions' => collect(AuditType::cases())->map(fn ($type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values()->toArray(),
            'canCreate' => $request->user()->hasAnyRole(['Administrator', 'IT Manager', 'Auditor']),
        ]);
    }

    /**
     * Display the audit status dashboard with metrics and filtering.
     *
     * Accessible to Administrator, IT Manager, and Auditor roles.
     * Provides overview of audit progress, finding severity distribution,
     * and resolution status metrics with datacenter and time period filtering.
     */
    public function dashboard(Request $request): InertiaResponse|Response
    {
        $user = $request->user();

        // Authorization check: only specific roles can access the dashboard
        if (! $user->hasAnyRole(self::DASHBOARD_ROLES)) {
            abort(Response::HTTP_FORBIDDEN, 'You do not have permission to access the audit dashboard.');
        }

        // Get filter values from request
        $datacenterId = $request->input('datacenter_id');
        $timePeriod = $request->input('time_period', '30_days');

        // Load datacenters for filter dropdown (with user access filter for non-admins)
        $datacentersQuery = Datacenter::query()->orderBy('name');

        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            $assignedDatacenterIds = $user->datacenters()->pluck('datacenters.id');
            $datacentersQuery->whereIn('id', $assignedDatacenterIds);
        }

        $datacenterOptions = $datacentersQuery->get()->map(fn (Datacenter $datacenter) => [
            'id' => $datacenter->id,
            'name' => $datacenter->name,
        ]);

        // Time period filter options
        $timePeriodOptions = [
            ['value' => '30_days', 'label' => 'Last 30 days'],
            ['value' => '90_days', 'label' => 'Last 90 days'],
            ['value' => 'quarter', 'label' => 'This quarter'],
            ['value' => 'year', 'label' => 'This year'],
            ['value' => 'all', 'label' => 'All time'],
        ];

        // Calculate date range based on time period
        $dateRange = $this->getDateRangeForTimePeriod($timePeriod);

        // Build base audit query with filters
        $auditQuery = Audit::query()
            ->when($datacenterId, fn (Builder $q) => $q->where('datacenter_id', $datacenterId))
            ->when($dateRange['start'], fn (Builder $q) => $q->where('created_at', '>=', $dateRange['start']));

        // Build base finding query with filters (via audit relationship)
        $findingQuery = Finding::query()
            ->whereHas('audit', function (Builder $q) use ($datacenterId, $dateRange) {
                $q->when($datacenterId, fn (Builder $q2) => $q2->where('datacenter_id', $datacenterId))
                    ->when($dateRange['start'], fn (Builder $q2) => $q2->where('created_at', '>=', $dateRange['start']));
            });

        // Task 2.2: Audit progress metrics
        $auditMetrics = $this->getAuditProgressMetrics(clone $auditQuery);

        // Task 2.3: Finding severity aggregation
        $severityMetrics = $this->getFindingSeverityMetrics(clone $findingQuery);

        // Task 2.4: Per-audit finding breakdown
        $auditBreakdown = $this->getPerAuditFindingBreakdown(clone $auditQuery);

        // Task 2.5: Resolution status metrics
        $resolutionMetrics = $this->getResolutionStatusMetrics(clone $findingQuery);

        // Task 2.6: Trend data for completion chart
        $trendData = $this->getCompletionTrendData(clone $auditQuery, $timePeriod, $dateRange);

        // Task 2.7: Active audit progress data
        $activeAuditProgress = $this->getActiveAuditProgress(clone $auditQuery);

        return Inertia::render('Audits/Dashboard', [
            'filters' => [
                'datacenter_id' => $datacenterId,
                'time_period' => $timePeriod,
            ],
            'datacenterOptions' => $datacenterOptions,
            'timePeriodOptions' => $timePeriodOptions,
            'auditMetrics' => $auditMetrics,
            'severityMetrics' => $severityMetrics,
            'auditBreakdown' => $auditBreakdown,
            'resolutionMetrics' => $resolutionMetrics,
            'trendData' => $trendData,
            'activeAuditProgress' => $activeAuditProgress,
        ]);
    }

    /**
     * Calculate the date range for the given time period filter.
     *
     * @return array{start: ?Carbon, end: Carbon}
     */
    private function getDateRangeForTimePeriod(string $timePeriod): array
    {
        $now = now();

        return match ($timePeriod) {
            '30_days' => ['start' => $now->copy()->subDays(30)->startOfDay(), 'end' => $now],
            '90_days' => ['start' => $now->copy()->subDays(90)->startOfDay(), 'end' => $now],
            'quarter' => ['start' => $now->copy()->startOfQuarter(), 'end' => $now],
            'year' => ['start' => $now->copy()->startOfYear(), 'end' => $now],
            'all' => ['start' => null, 'end' => $now],
            default => ['start' => $now->copy()->subDays(30)->startOfDay(), 'end' => $now],
        };
    }

    /**
     * Task 2.2: Get audit progress metrics.
     *
     * @return array{total: int, byStatus: array<string, int>, completionPercentage: float, pastDue: int, dueSoon: int}
     */
    private function getAuditProgressMetrics(Builder $query): array
    {
        // Get counts by status using a single query
        $statusCounts = (clone $query)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $total = array_sum($statusCounts);
        $completed = $statusCounts[AuditStatus::Completed->value] ?? 0;

        // Calculate completion percentage
        $completionPercentage = $total > 0 ? round(($completed / $total) * 100, 2) : 0.0;

        // Get by status with all statuses represented
        $byStatus = [];
        foreach (AuditStatus::cases() as $status) {
            $byStatus[$status->value] = $statusCounts[$status->value] ?? 0;
        }

        // Past due: status not Completed and due_date < today
        $pastDue = (clone $query)
            ->where('status', '!=', AuditStatus::Completed)
            ->where('status', '!=', AuditStatus::Cancelled)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->count();

        // Due soon: due_date within next 7 days, not overdue, not completed
        $dueSoon = (clone $query)
            ->where('status', '!=', AuditStatus::Completed)
            ->where('status', '!=', AuditStatus::Cancelled)
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now()->startOfDay())
            ->where('due_date', '<=', now()->addDays(7)->endOfDay())
            ->count();

        return [
            'total' => $total,
            'byStatus' => $byStatus,
            'completionPercentage' => $completionPercentage,
            'pastDue' => $pastDue,
            'dueSoon' => $dueSoon,
        ];
    }

    /**
     * Task 2.3: Get finding severity aggregation.
     *
     * @return array{critical: array, high: array, medium: array, low: array, total: int}
     */
    private function getFindingSeverityMetrics(Builder $query): array
    {
        // Get counts by severity using a single query
        $severityCounts = (clone $query)
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        $total = array_sum($severityCounts);

        // Build metrics with color codes from enum
        $metrics = [];
        foreach (FindingSeverity::cases() as $severity) {
            $count = $severityCounts[$severity->value] ?? 0;
            $metrics[$severity->value] = [
                'count' => $count,
                'color' => $severity->color(),
                'label' => $severity->label(),
                'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0.0,
            ];
        }

        $metrics['total'] = $total;

        return $metrics;
    }

    /**
     * Task 2.4: Get per-audit finding breakdown.
     *
     * @return array<array{id: int, name: string, datacenter: string, status: string, critical: int, high: int, medium: int, low: int, total: int}>
     */
    private function getPerAuditFindingBreakdown(Builder $query): array
    {
        // Get audits with their finding counts by severity using eager loading
        // Note: We filter after get() to be compatible with both MySQL and SQLite
        $audits = (clone $query)
            ->withCount([
                'findings as critical_count' => fn (Builder $q) => $q->where('severity', FindingSeverity::Critical),
                'findings as high_count' => fn (Builder $q) => $q->where('severity', FindingSeverity::High),
                'findings as medium_count' => fn (Builder $q) => $q->where('severity', FindingSeverity::Medium),
                'findings as low_count' => fn (Builder $q) => $q->where('severity', FindingSeverity::Low),
                'findings as total_findings',
            ])
            ->with('datacenter:id,name')
            ->get()
            ->filter(fn (Audit $audit) => $audit->total_findings > 0)
            ->sortByDesc('total_findings')
            ->values();

        return $audits->map(fn (Audit $audit) => [
            'id' => $audit->id,
            'name' => $audit->name,
            'datacenter' => $audit->datacenter->name,
            'status' => $audit->status->value,
            'status_label' => $audit->status->label(),
            'critical' => $audit->critical_count,
            'high' => $audit->high_count,
            'medium' => $audit->medium_count,
            'low' => $audit->low_count,
            'total' => $audit->total_findings,
        ])->toArray();
    }

    /**
     * Task 2.5: Get resolution status metrics.
     *
     * @return array{openCount: int, resolvedCount: int, totalCount: int, resolutionRate: float, averageResolutionTime: ?int, overdueCount: int}
     */
    private function getResolutionStatusMetrics(Builder $query): array
    {
        // Open statuses: Open, InProgress, PendingReview, Deferred
        $openStatuses = [
            FindingStatus::Open->value,
            FindingStatus::InProgress->value,
            FindingStatus::PendingReview->value,
            FindingStatus::Deferred->value,
        ];

        $openCount = (clone $query)->whereIn('status', $openStatuses)->count();
        $resolvedCount = (clone $query)->where('status', FindingStatus::Resolved)->count();
        $totalCount = $openCount + $resolvedCount;

        // Resolution rate
        $resolutionRate = $totalCount > 0 ? round(($resolvedCount / $totalCount) * 100, 2) : 0.0;

        // Average resolution time: calculate mean of getTotalResolutionTime() for resolved findings
        $resolvedFindings = (clone $query)
            ->where('status', FindingStatus::Resolved)
            ->whereNotNull('resolved_at')
            ->get(['created_at', 'resolved_at']);

        $totalResolutionTime = 0;
        $resolvedWithTime = 0;
        foreach ($resolvedFindings as $finding) {
            $resolutionTime = $finding->created_at->diffInMinutes($finding->resolved_at);
            $totalResolutionTime += $resolutionTime;
            $resolvedWithTime++;
        }

        $averageResolutionTime = $resolvedWithTime > 0
            ? (int) round($totalResolutionTime / $resolvedWithTime)
            : null;

        // Overdue findings count using scopeOverdue()
        $overdueCount = (clone $query)->overdue()->count();

        return [
            'openCount' => $openCount,
            'resolvedCount' => $resolvedCount,
            'totalCount' => $totalCount,
            'resolutionRate' => $resolutionRate,
            'averageResolutionTime' => $averageResolutionTime,
            'overdueCount' => $overdueCount,
        ];
    }

    /**
     * Task 2.6: Get trend data for completion chart.
     *
     * @param  array{start: ?Carbon, end: Carbon}  $dateRange
     * @return array<array{period: string, count: int}>
     */
    private function getCompletionTrendData(Builder $query, string $timePeriod, array $dateRange): array
    {
        // Determine granularity based on time period
        $granularity = match ($timePeriod) {
            '30_days' => 'day',
            '90_days' => 'week',
            'quarter', 'year', 'all' => 'month',
            default => 'day',
        };

        // Get completed audits and group them in PHP for database-agnostic approach
        $completedAudits = (clone $query)
            ->where('status', AuditStatus::Completed)
            ->get(['updated_at']);

        // Group by time period
        $trendData = $completedAudits->groupBy(function ($audit) use ($granularity) {
            $date = $audit->updated_at;

            return match ($granularity) {
                'day' => $date->format('Y-m-d'),
                'week' => $date->format('o-\\WW'),
                'month' => $date->format('Y-m'),
                default => $date->format('Y-m-d'),
            };
        })->map->count()->toArray();

        // Fill in missing periods with zero counts
        $result = [];
        $start = $dateRange['start'] ?? now()->subYear();
        $end = $dateRange['end'];

        $current = $start->copy();
        while ($current->lte($end)) {
            $periodKey = match ($granularity) {
                'day' => $current->format('Y-m-d'),
                'week' => $current->format('o-\\WW'),
                'month' => $current->format('Y-m'),
                default => $current->format('Y-m-d'),
            };

            $result[] = [
                'period' => $periodKey,
                'count' => $trendData[$periodKey] ?? 0,
            ];

            $current = match ($granularity) {
                'day' => $current->addDay(),
                'week' => $current->addWeek(),
                'month' => $current->addMonth(),
                default => $current->addDay(),
            };
        }

        return $result;
    }

    /**
     * Task 2.7: Get active audit progress data.
     *
     * @return array<array{id: int, name: string, dueDate: ?string, progressPercentage: float, type: string}>
     */
    private function getActiveAuditProgress(Builder $query): array
    {
        $activeAudits = (clone $query)
            ->where('status', AuditStatus::InProgress)
            ->with('datacenter:id,name')
            ->get();

        return $activeAudits->map(function (Audit $audit) {
            $progressPercentage = 0.0;

            if ($audit->type === AuditType::Connection) {
                // Connection audits: completedVerifications / totalVerifications
                $total = $audit->totalVerifications();
                $completed = $audit->completedVerifications();
                $progressPercentage = $total > 0 ? round(($completed / $total) * 100, 2) : 0.0;
            } elseif ($audit->type === AuditType::Inventory) {
                // Inventory audits: (total - pending) / total
                $total = $audit->totalDeviceVerifications();
                $pending = $audit->pendingDeviceVerifications();
                $completed = $total - $pending;
                $progressPercentage = $total > 0 ? round(($completed / $total) * 100, 2) : 0.0;
            }

            return [
                'id' => $audit->id,
                'name' => $audit->name,
                'datacenter' => $audit->datacenter->name,
                'dueDate' => $audit->due_date?->format('Y-m-d'),
                'progressPercentage' => $progressPercentage,
                'type' => $audit->type->value,
                'type_label' => $audit->type->label(),
                'isOverdue' => $audit->due_date && $audit->due_date->lt(now()->startOfDay()),
                'isDueSoon' => $audit->due_date
                    && $audit->due_date->gte(now()->startOfDay())
                    && $audit->due_date->lte(now()->addDays(7)->endOfDay()),
            ];
        })->toArray();
    }

    /**
     * Show the form for creating a new audit.
     */
    public function create(Request $request): InertiaResponse
    {
        $user = $request->user();

        // Load datacenters (with user access filter for non-admins)
        $datacentersQuery = Datacenter::query()->orderBy('name');

        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            $assignedDatacenterIds = $user->datacenters()->pluck('datacenters.id');
            $datacentersQuery->whereIn('id', $assignedDatacenterIds);
        }

        $datacenters = $datacentersQuery->get()->map(fn (Datacenter $datacenter) => [
            'id' => $datacenter->id,
            'name' => $datacenter->name,
            'formatted_location' => $datacenter->formatted_location,
            'has_approved_implementation_files' => $datacenter->hasApprovedImplementationFiles(),
        ]);

        // Load users who can be assigned (Operators and Auditors)
        $assignableUsers = User::whereHas('roles', function ($query) {
            $query->whereIn('name', self::ASSIGNABLE_ROLES);
        })
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);

        return Inertia::render('Audits/Create', [
            'datacenters' => $datacenters,
            'assignableUsers' => $assignableUsers,
            'auditTypes' => collect(AuditType::cases())->map(fn ($type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values()->toArray(),
            'scopeTypes' => collect(AuditScopeType::cases())->map(fn ($scope) => [
                'value' => $scope->value,
                'label' => $scope->label(),
            ])->values()->toArray(),
        ]);
    }

    /**
     * Store a newly created audit in storage.
     */
    public function store(StoreAuditRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Auto-find latest approved implementation file for connection audits
        $implementationFileId = null;
        if ($validated['type'] === AuditType::Connection->value) {
            $implementationFile = ImplementationFile::where('datacenter_id', $validated['datacenter_id'])
                ->where('approval_status', 'approved')
                ->orderByDesc('created_at')
                ->first();

            if ($implementationFile) {
                $implementationFileId = $implementationFile->id;
            }
        }

        // Create the audit
        $audit = Audit::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'],
            'type' => $validated['type'],
            'scope_type' => $validated['scope_type'],
            'status' => AuditStatus::Pending,
            'datacenter_id' => $validated['datacenter_id'],
            'room_id' => $validated['room_id'] ?? null,
            'implementation_file_id' => $implementationFileId,
            'created_by' => $request->user()->id,
        ]);

        // Attach assignees and send notifications
        if (! empty($validated['assignee_ids'])) {
            $audit->assignees()->attach($validated['assignee_ids']);

            // Load the datacenter for notification data
            $audit->load('datacenter');

            // Notify each assigned user
            $assignees = User::whereIn('id', $validated['assignee_ids'])->get();
            foreach ($assignees as $assignee) {
                $assignee->notify(new AuditAssignedNotification($audit));
            }
        }

        // Attach racks if scope type is racks
        if ($validated['scope_type'] === AuditScopeType::Racks->value && ! empty($validated['rack_ids'])) {
            $audit->racks()->attach($validated['rack_ids']);
        }

        // Attach devices if provided (only for racks scope)
        if (! empty($validated['device_ids'])) {
            $audit->devices()->attach($validated['device_ids']);
        }

        return redirect()->route('audits.index')
            ->with('success', 'Audit created successfully.');
    }

    /**
     * Display the specified audit.
     */
    public function show(Request $request, Audit $audit): InertiaResponse
    {
        $audit->load(['datacenter', 'room', 'implementationFile', 'assignees', 'creator', 'racks', 'devices', 'reports.generator']);

        $user = $request->user();

        // Determine if user can execute the audit
        $isAssignee = $audit->assignees->contains('id', $user->id);
        $isCreator = $audit->created_by === $user->id;
        $isAdmin = $user->hasAnyRole(self::ADMIN_ROLES);
        $canExecute = $isAssignee || $isCreator || $isAdmin;

        // Connection audit entry points
        $isConnectionAudit = $audit->type === AuditType::Connection;
        $canStartConnectionAudit = $isConnectionAudit
            && $audit->status === AuditStatus::Pending
            && $canExecute;
        $canContinueConnectionAudit = $isConnectionAudit
            && $audit->status === AuditStatus::InProgress
            && $canExecute;

        // Inventory audit entry points
        $isInventoryAudit = $audit->type === AuditType::Inventory;
        $canStartInventoryAudit = $isInventoryAudit
            && $audit->status === AuditStatus::Pending
            && $canExecute;
        $canContinueInventoryAudit = $isInventoryAudit
            && $audit->status === AuditStatus::InProgress
            && $canExecute;

        // Get progress stats based on audit type
        $progressStats = null;
        if ($audit->status === AuditStatus::InProgress || $audit->status === AuditStatus::Completed) {
            if ($isConnectionAudit) {
                $progressStats = $this->executionService->getProgressStats($audit);
            } elseif ($isInventoryAudit) {
                $progressStats = $this->executionService->getInventoryProgressStats($audit);
            }
        }

        // Determine if user can generate reports
        // Reports can only be generated for in_progress or completed audits
        $canGenerateReport = in_array($audit->status, [AuditStatus::InProgress, AuditStatus::Completed])
            && $canExecute;

        // Format reports data for the frontend
        $reports = $audit->reports
            ->sortByDesc('generated_at')
            ->values()
            ->map(fn (AuditReport $report) => [
                'id' => $report->id,
                'generated_at' => $report->generated_at?->format('M d, Y H:i'),
                'generated_at_iso' => $report->generated_at?->toIso8601String(),
                'generator_name' => $report->generator?->name,
                'file_size_bytes' => $report->file_size_bytes,
                'file_size_formatted' => $this->formatFileSize($report->file_size_bytes),
                'download_url' => route('reports.download', $report->id),
            ])
            ->toArray();

        return Inertia::render('Audits/Show', [
            'audit' => [
                'id' => $audit->id,
                'name' => $audit->name,
                'description' => $audit->description,
                'due_date' => $audit->due_date?->format('Y-m-d'),
                'type' => $audit->type->value,
                'type_label' => $audit->type->label(),
                'scope_type' => $audit->scope_type->value,
                'scope_type_label' => $audit->scope_type->label(),
                'status' => $audit->status->value,
                'status_label' => $audit->status->label(),
                'datacenter' => [
                    'id' => $audit->datacenter->id,
                    'name' => $audit->datacenter->name,
                    'formatted_location' => $audit->datacenter->formatted_location,
                ],
                'room' => $audit->room ? [
                    'id' => $audit->room->id,
                    'name' => $audit->room->name,
                ] : null,
                'implementation_file' => $audit->implementationFile ? [
                    'id' => $audit->implementationFile->id,
                    'original_name' => $audit->implementationFile->original_name,
                    'version_number' => $audit->implementationFile->version_number,
                ] : null,
                'assignees' => $audit->assignees->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])->toArray(),
                'creator' => $audit->creator ? [
                    'id' => $audit->creator->id,
                    'name' => $audit->creator->name,
                ] : null,
                'racks_count' => $audit->racks->count(),
                'devices_count' => $audit->devices->count(),
                'created_at' => $audit->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $audit->updated_at?->format('Y-m-d H:i:s'),
            ],
            // Connection audit entry point props
            'can_start_audit' => $canStartConnectionAudit,
            'can_continue_audit' => $canContinueConnectionAudit,
            // Inventory audit entry point props
            'can_start_inventory_audit' => $canStartInventoryAudit,
            'can_continue_inventory_audit' => $canContinueInventoryAudit,
            'progress_stats' => $progressStats,
            // Report generation props
            'can_generate_report' => $canGenerateReport,
            'reports' => $reports,
        ]);
    }

    /**
     * Format file size in human-readable format.
     */
    private function formatFileSize(?int $bytes): string
    {
        if ($bytes === null || $bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = $bytes;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 1).' '.$units[$i];
    }

    /**
     * Start execution of an audit.
     *
     * Transitions audit status to InProgress and prepares verification items if needed.
     * Redirects to the appropriate execution page based on audit type.
     */
    public function startExecution(Audit $audit): RedirectResponse
    {
        // Handle connection audits
        if ($audit->type === AuditType::Connection) {
            if ($audit->status !== AuditStatus::Pending && $audit->status !== AuditStatus::InProgress) {
                return redirect()->route('audits.show', $audit)
                    ->with('error', 'This audit cannot be started. It may already be completed or cancelled.');
            }

            // Prepare verification items if this is the first time starting
            if ($audit->status === AuditStatus::Pending) {
                $this->executionService->prepareVerificationItems($audit);
            }

            return redirect()->route('audits.execute', $audit);
        }

        // Handle inventory audits
        if ($audit->type === AuditType::Inventory) {
            if ($audit->status !== AuditStatus::Pending && $audit->status !== AuditStatus::InProgress) {
                return redirect()->route('audits.show', $audit)
                    ->with('error', 'This audit cannot be started. It may already be completed or cancelled.');
            }

            // Prepare device verification items if this is the first time starting
            if ($audit->status === AuditStatus::Pending) {
                $this->executionService->prepareDeviceVerificationItems($audit);
            }

            return redirect()->route('audits.inventory-execute', $audit);
        }

        return redirect()->route('audits.show', $audit)
            ->with('error', 'Unknown audit type.');
    }

    /**
     * Display the connection audit execution page.
     */
    public function execute(Request $request, Audit $audit): InertiaResponse
    {
        // Validate audit type
        if ($audit->type !== AuditType::Connection) {
            abort(404, 'Only connection audits can be executed through this interface.');
        }

        $audit->load(['datacenter', 'implementationFile', 'assignees']);

        $progressStats = $this->executionService->getProgressStats($audit);

        return Inertia::render('Audits/Execute', [
            'audit' => [
                'id' => $audit->id,
                'name' => $audit->name,
                'status' => $audit->status->value,
                'status_label' => $audit->status->label(),
                'type' => $audit->type->value,
                'type_label' => $audit->type->label(),
                'datacenter' => [
                    'id' => $audit->datacenter->id,
                    'name' => $audit->datacenter->name,
                ],
                'implementation_file' => $audit->implementationFile ? [
                    'id' => $audit->implementationFile->id,
                    'original_name' => $audit->implementationFile->original_name,
                ] : null,
            ],
            'progress_stats' => $progressStats,
            'discrepancy_types' => collect(\App\Enums\DiscrepancyType::cases())->map(fn ($type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values()->toArray(),
            'verification_statuses' => collect(\App\Enums\VerificationStatus::cases())->map(fn ($status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values()->toArray(),
        ]);
    }

    /**
     * Display the inventory audit execution page.
     */
    public function inventoryExecute(Request $request, Audit $audit): InertiaResponse
    {
        // Validate audit type
        if ($audit->type !== AuditType::Inventory) {
            abort(404, 'Only inventory audits can be executed through this interface.');
        }

        $audit->load(['datacenter', 'room', 'assignees']);

        // Prepare device verification items if not already done
        if (! $audit->deviceVerifications()->exists()) {
            $this->executionService->prepareDeviceVerificationItems($audit);
        }

        $progressStats = $this->executionService->getInventoryProgressStats($audit);

        // Get rooms for filtering (for datacenter-level audits)
        $rooms = [];
        if ($audit->scope_type === AuditScopeType::Datacenter) {
            $rooms = $audit->datacenter->rooms()
                ->orderBy('name')
                ->get()
                ->map(fn ($room) => [
                    'id' => $room->id,
                    'name' => $room->name,
                ])
                ->toArray();
        }

        return Inertia::render('Audits/InventoryExecute', [
            'audit' => [
                'id' => $audit->id,
                'name' => $audit->name,
                'status' => $audit->status->value,
                'status_label' => $audit->status->label(),
                'type' => $audit->type->value,
                'type_label' => $audit->type->label(),
                'scope_type' => $audit->scope_type->value,
                'scope_type_label' => $audit->scope_type->label(),
                'datacenter' => [
                    'id' => $audit->datacenter->id,
                    'name' => $audit->datacenter->name,
                ],
                'room' => $audit->room ? [
                    'id' => $audit->room->id,
                    'name' => $audit->room->name,
                ] : null,
            ],
            'progress_stats' => $progressStats,
            'rooms' => $rooms,
            'verification_statuses' => collect(DeviceVerificationStatus::cases())->map(fn ($status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values()->toArray(),
        ]);
    }

    /**
     * Show the form for editing the specified audit.
     * Note: Editing is out of scope for this feature.
     */
    public function edit(string $id): void
    {
        abort(404, 'Audit editing is not yet implemented.');
    }

    /**
     * Update the specified audit in storage.
     * Note: Updating is out of scope for this feature.
     */
    public function update(Request $request, string $id): void
    {
        abort(404, 'Audit updating is not yet implemented.');
    }

    /**
     * Remove the specified audit from storage.
     * Note: Deletion is out of scope for this feature.
     */
    public function destroy(string $id): void
    {
        abort(404, 'Audit deletion is not yet implemented.');
    }

    /**
     * Sync assignees for an audit and dispatch appropriate notifications.
     *
     * Dispatches AuditAssignedNotification for newly added assignees
     * and AuditReassignedNotification for removed assignees.
     *
     * @param  array<int>  $newAssigneeIds
     */
    public function syncAssigneesWithNotifications(Audit $audit, array $newAssigneeIds): void
    {
        // Get current assignee IDs before sync
        $currentAssigneeIds = $audit->assignees()->pluck('users.id')->toArray();

        // Calculate added and removed assignees
        $addedIds = array_diff($newAssigneeIds, $currentAssigneeIds);
        $removedIds = array_diff($currentAssigneeIds, $newAssigneeIds);

        // Sync the assignees
        $audit->assignees()->sync($newAssigneeIds);

        // Load datacenter for notification data if needed
        if (! empty($addedIds) || ! empty($removedIds)) {
            $audit->load('datacenter');
        }

        // Notify newly added assignees
        if (! empty($addedIds)) {
            $addedUsers = User::whereIn('id', $addedIds)->get();
            foreach ($addedUsers as $user) {
                $user->notify(new AuditAssignedNotification($audit));
            }
        }

        // Notify removed assignees
        if (! empty($removedIds)) {
            $removedUsers = User::whereIn('id', $removedIds)->get();
            foreach ($removedUsers as $user) {
                $user->notify(new AuditReassignedNotification($audit));
            }
        }
    }
}
