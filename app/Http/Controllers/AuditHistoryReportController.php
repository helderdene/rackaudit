<?php

namespace App\Http\Controllers;

use App\Enums\AuditStatus;
use App\Enums\AuditType;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Exports\AuditHistoryReportExport;
use App\Http\Requests\AuditHistoryReportRequest;
use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\Finding;
use App\Services\AuditHistoryReportService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for managing audit history reports.
 *
 * Provides historical audit trends, finding counts by severity,
 * resolution time metrics, and export capabilities (PDF/CSV).
 */
class AuditHistoryReportController extends Controller
{
    /**
     * Roles that have full access to all datacenters.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected AuditHistoryReportService $reportService
    ) {}

    /**
     * Display the audit history reports page.
     */
    public function index(AuditHistoryReportRequest $request): InertiaResponse
    {
        $user = $request->user();

        // Get accessible datacenters based on user role
        $datacenterOptions = $this->getAccessibleDatacenters($user);
        $accessibleDatacenterIds = $datacenterOptions->pluck('id')->toArray();

        // Get and validate filter values
        $datacenterId = $this->validateDatacenterId(
            $request->input('datacenter_id'),
            $accessibleDatacenterIds
        );

        // Get time range filter values
        $timeRangePreset = $request->input('time_range_preset');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Calculate date range from preset or custom dates (default: last 12 months)
        $dateRange = $this->calculateDateRange($timeRangePreset, $startDate, $endDate);

        // Get audit type filter
        $auditType = $request->input('audit_type');

        // Build base query for completed audits
        $baseQuery = $this->buildBaseQuery(
            $accessibleDatacenterIds,
            $datacenterId,
            $auditType,
            $dateRange['start'],
            $dateRange['end']
        );

        // Calculate metrics
        $metrics = $this->calculateMetrics($baseQuery, $dateRange);

        // Get finding trend data
        $findingTrendData = $this->getFindingTrendData($baseQuery, $dateRange);

        // Get resolution time trend data
        $resolutionTimeTrendData = $this->getResolutionTimeTrendData($baseQuery, $dateRange);

        // Get paginated audit history
        $sortBy = $request->input('sort_by', 'completion_date');
        $sortDirection = $request->input('sort_direction', 'desc');
        $audits = $this->getPaginatedAuditHistory($baseQuery, $sortBy, $sortDirection);

        // Build audit type options
        $auditTypeOptions = collect(AuditType::cases())->map(fn (AuditType $type) => [
            'value' => $type->value,
            'label' => $type->label(),
        ])->toArray();

        return Inertia::render('AuditHistoryReports/Index', [
            'metrics' => $metrics,
            'datacenterOptions' => $datacenterOptions->values()->toArray(),
            'auditTypeOptions' => $auditTypeOptions,
            'filters' => [
                'time_range_preset' => $timeRangePreset,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'datacenter_id' => $datacenterId,
                'audit_type' => $auditType,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ],
            'findingTrendData' => $findingTrendData,
            'resolutionTimeTrendData' => $resolutionTimeTrendData,
            'audits' => $audits,
        ]);
    }

    /**
     * Export audit history report as PDF.
     */
    public function exportPdf(AuditHistoryReportRequest $request): StreamedResponse|BinaryFileResponse
    {
        $user = $request->user();

        // Get accessible datacenters based on user role
        $datacenterOptions = $this->getAccessibleDatacenters($user);
        $accessibleDatacenterIds = $datacenterOptions->pluck('id')->toArray();

        // Get and validate filter values
        $datacenterId = $this->validateDatacenterId(
            $request->input('datacenter_id'),
            $accessibleDatacenterIds
        );

        $filters = [
            'time_range_preset' => $request->input('time_range_preset'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'datacenter_id' => $datacenterId,
            'audit_type' => $request->input('audit_type'),
            'accessible_datacenter_ids' => $accessibleDatacenterIds,
        ];

        // Generate the PDF report
        $filePath = $this->reportService->generatePdfReport($filters, $user);

        $filename = basename($filePath);

        return Storage::disk('local')->download($filePath, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Export audit history report as CSV.
     */
    public function exportCsv(AuditHistoryReportRequest $request): BinaryFileResponse
    {
        $user = $request->user();

        // Get accessible datacenters based on user role
        $datacenterOptions = $this->getAccessibleDatacenters($user);
        $accessibleDatacenterIds = $datacenterOptions->pluck('id')->toArray();

        // Get and validate filter values
        $datacenterId = $this->validateDatacenterId(
            $request->input('datacenter_id'),
            $accessibleDatacenterIds
        );

        $filters = [
            'time_range_preset' => $request->input('time_range_preset'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'datacenter_id' => $datacenterId,
            'audit_type' => $request->input('audit_type'),
            'accessible_datacenter_ids' => $accessibleDatacenterIds,
        ];

        $timestamp = now()->format('Y-m-d-His');
        $filename = "audit-history-report-{$timestamp}.csv";

        return Excel::download(new AuditHistoryReportExport($filters), $filename);
    }

    /**
     * Get datacenters accessible by the user.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function getAccessibleDatacenters($user): Collection
    {
        $query = Datacenter::query()->orderBy('name');

        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            $assignedDatacenterIds = $user->datacenters()->pluck('datacenters.id');
            $query->whereIn('id', $assignedDatacenterIds);
        }

        return $query->get()->map(fn (Datacenter $datacenter) => [
            'id' => $datacenter->id,
            'name' => $datacenter->name,
        ]);
    }

    /**
     * Validate and return datacenter ID if it's in the accessible list.
     *
     * @param  array<int>  $accessibleIds
     */
    private function validateDatacenterId(mixed $datacenterId, array $accessibleIds): ?int
    {
        if ($datacenterId === null || $datacenterId === '') {
            return null;
        }

        $id = (int) $datacenterId;

        return in_array($id, $accessibleIds, true) ? $id : null;
    }

    /**
     * Calculate date range from preset or custom dates.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    private function calculateDateRange(?string $preset, ?string $startDate, ?string $endDate): array
    {
        // If custom dates are provided, use them
        if ($startDate !== null && $endDate !== null) {
            return [
                'start' => Carbon::parse($startDate)->startOfDay(),
                'end' => Carbon::parse($endDate)->endOfDay(),
            ];
        }

        // Otherwise, use preset (default: 12 months)
        $end = now()->endOfDay();

        $start = match ($preset) {
            '30_days' => now()->subDays(30)->startOfDay(),
            '6_months' => now()->subMonths(6)->startOfDay(),
            default => now()->subMonths(12)->startOfDay(), // 12_months is default
        };

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Build base query for completed audits with filters.
     *
     * @param  array<int>  $accessibleDatacenterIds
     */
    private function buildBaseQuery(
        array $accessibleDatacenterIds,
        ?int $datacenterId,
        ?string $auditType,
        Carbon $startDate,
        Carbon $endDate
    ): Builder {
        $query = Audit::query()
            ->where('status', AuditStatus::Completed)
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->whereIn('datacenter_id', $accessibleDatacenterIds);

        if ($datacenterId !== null) {
            $query->where('datacenter_id', $datacenterId);
        }

        if ($auditType !== null) {
            $auditTypeEnum = AuditType::tryFrom($auditType);
            if ($auditTypeEnum !== null) {
                $query->where('type', $auditTypeEnum);
            }
        }

        return $query;
    }

    /**
     * Calculate summary metrics for the report.
     *
     * @param  array{start: Carbon, end: Carbon}  $dateRange
     * @return array{
     *     totalAuditsCompleted: array{value: int, sparkline: array<int>},
     *     totalFindings: array{value: int, bySeverity: array<string, int>},
     *     avgResolutionTime: array{value: float|null, formatted: string},
     *     avgTimeToFirstResponse: array{value: float|null, formatted: string}
     * }
     */
    private function calculateMetrics(Builder $baseQuery, array $dateRange): array
    {
        // Get all completed audit IDs in the period
        $auditIds = (clone $baseQuery)->pluck('id')->toArray();

        // Total Audits Completed with sparkline
        $totalAudits = count($auditIds);
        $sparkline = $this->generateAuditSparklineData($baseQuery, $dateRange);

        // Total Findings with severity breakdown
        $findingsQuery = Finding::query()->whereIn('audit_id', $auditIds);
        $totalFindings = (clone $findingsQuery)->count();

        $bySeverity = [];
        foreach (FindingSeverity::cases() as $severity) {
            $bySeverity[$severity->value] = (clone $findingsQuery)
                ->where('severity', $severity)
                ->count();
        }

        // Avg Resolution Time for resolved findings
        $resolvedFindings = (clone $findingsQuery)
            ->whereNotNull('resolved_at')
            ->where('status', FindingStatus::Resolved)
            ->get();

        $avgResolutionTime = null;
        if ($resolvedFindings->isNotEmpty()) {
            $totalMinutes = $resolvedFindings->sum(fn ($finding) => $finding->getTotalResolutionTime() ?? 0);
            $avgResolutionTime = $totalMinutes / $resolvedFindings->count();
        }

        // Avg Time to First Response
        $findingsWithTransitions = (clone $findingsQuery)
            ->with(['statusTransitions' => function ($query) {
                $query->where('from_status', FindingStatus::Open)
                    ->where('to_status', FindingStatus::InProgress)
                    ->orderBy('transitioned_at', 'asc');
            }])
            ->get();

        $avgFirstResponse = null;
        $findingsWithFirstResponse = $findingsWithTransitions->filter(
            fn ($finding) => $finding->getTimeToFirstResponse() !== null
        );

        if ($findingsWithFirstResponse->isNotEmpty()) {
            $totalMinutes = $findingsWithFirstResponse->sum(fn ($finding) => $finding->getTimeToFirstResponse() ?? 0);
            $avgFirstResponse = $totalMinutes / $findingsWithFirstResponse->count();
        }

        return [
            'totalAuditsCompleted' => [
                'value' => $totalAudits,
                'sparkline' => $sparkline,
            ],
            'totalFindings' => [
                'value' => $totalFindings,
                'bySeverity' => $bySeverity,
            ],
            'avgResolutionTime' => [
                'value' => $avgResolutionTime,
                'formatted' => $this->formatMinutesToHumanReadable($avgResolutionTime),
            ],
            'avgTimeToFirstResponse' => [
                'value' => $avgFirstResponse,
                'formatted' => $this->formatMinutesToHumanReadable($avgFirstResponse),
            ],
        ];
    }

    /**
     * Generate sparkline data for audit counts.
     *
     * @param  array{start: Carbon, end: Carbon}  $dateRange
     * @return array<int>
     */
    private function generateAuditSparklineData(Builder $baseQuery, array $dateRange): array
    {
        $sparkline = [];
        $diffInDays = $dateRange['start']->diffInDays($dateRange['end']);

        // Use weekly intervals for 30-day view, monthly for longer views
        if ($diffInDays <= 30) {
            // Weekly sparkline (last 4 weeks)
            for ($i = 3; $i >= 0; $i--) {
                $weekStart = now()->subWeeks($i)->startOfWeek();
                $weekEnd = now()->subWeeks($i)->endOfWeek();

                $count = (clone $baseQuery)
                    ->whereBetween('updated_at', [$weekStart, $weekEnd])
                    ->count();

                $sparkline[] = $count;
            }
        } else {
            // Monthly sparkline (last 6 months or 12 months)
            $months = $diffInDays > 180 ? 12 : 6;
            for ($i = $months - 1; $i >= 0; $i--) {
                $monthStart = now()->subMonths($i)->startOfMonth();
                $monthEnd = now()->subMonths($i)->endOfMonth();

                $count = (clone $baseQuery)
                    ->whereBetween('updated_at', [$monthStart, $monthEnd])
                    ->count();

                $sparkline[] = $count;
            }
        }

        return $sparkline;
    }

    /**
     * Get finding trend data aggregated by time period.
     *
     * @param  array{start: Carbon, end: Carbon}  $dateRange
     * @return array<array{period: string, critical: int, high: int, medium: int, low: int}>
     */
    private function getFindingTrendData(Builder $baseQuery, array $dateRange): array
    {
        $auditIds = (clone $baseQuery)->pluck('id')->toArray();
        $trendData = [];
        $diffInDays = $dateRange['start']->diffInDays($dateRange['end']);

        // Use weekly intervals for 30-day view, monthly for longer views
        if ($diffInDays <= 30) {
            // Weekly grouping
            for ($i = 3; $i >= 0; $i--) {
                $weekStart = now()->subWeeks($i)->startOfWeek();
                $weekEnd = now()->subWeeks($i)->endOfWeek();

                $periodData = $this->getFindingCountsBySeverity($auditIds, $weekStart, $weekEnd);
                $periodData['period'] = $weekStart->format('M d');

                $trendData[] = $periodData;
            }
        } else {
            // Monthly grouping
            $months = $diffInDays > 180 ? 12 : 6;
            for ($i = $months - 1; $i >= 0; $i--) {
                $monthStart = now()->subMonths($i)->startOfMonth();
                $monthEnd = now()->subMonths($i)->endOfMonth();

                $periodData = $this->getFindingCountsBySeverity($auditIds, $monthStart, $monthEnd);
                $periodData['period'] = $monthStart->format('M Y');

                $trendData[] = $periodData;
            }
        }

        return $trendData;
    }

    /**
     * Get finding counts by severity for a time period.
     *
     * @param  array<int>  $auditIds
     * @return array{critical: int, high: int, medium: int, low: int}
     */
    private function getFindingCountsBySeverity(array $auditIds, Carbon $start, Carbon $end): array
    {
        $query = Finding::query()
            ->whereIn('audit_id', $auditIds)
            ->whereBetween('created_at', [$start, $end]);

        return [
            'critical' => (clone $query)->where('severity', FindingSeverity::Critical)->count(),
            'high' => (clone $query)->where('severity', FindingSeverity::High)->count(),
            'medium' => (clone $query)->where('severity', FindingSeverity::Medium)->count(),
            'low' => (clone $query)->where('severity', FindingSeverity::Low)->count(),
        ];
    }

    /**
     * Get resolution time trend data aggregated by time period.
     *
     * @param  array{start: Carbon, end: Carbon}  $dateRange
     * @return array<array{period: string, avg_resolution_time: float|null, avg_first_response: float|null}>
     */
    private function getResolutionTimeTrendData(Builder $baseQuery, array $dateRange): array
    {
        $auditIds = (clone $baseQuery)->pluck('id')->toArray();
        $trendData = [];
        $diffInDays = $dateRange['start']->diffInDays($dateRange['end']);

        // Use weekly intervals for 30-day view, monthly for longer views
        if ($diffInDays <= 30) {
            // Weekly grouping
            for ($i = 3; $i >= 0; $i--) {
                $weekStart = now()->subWeeks($i)->startOfWeek();
                $weekEnd = now()->subWeeks($i)->endOfWeek();

                $periodData = $this->getResolutionTimeMetrics($auditIds, $weekStart, $weekEnd);
                $periodData['period'] = $weekStart->format('M d');

                $trendData[] = $periodData;
            }
        } else {
            // Monthly grouping
            $months = $diffInDays > 180 ? 12 : 6;
            for ($i = $months - 1; $i >= 0; $i--) {
                $monthStart = now()->subMonths($i)->startOfMonth();
                $monthEnd = now()->subMonths($i)->endOfMonth();

                $periodData = $this->getResolutionTimeMetrics($auditIds, $monthStart, $monthEnd);
                $periodData['period'] = $monthStart->format('M Y');

                $trendData[] = $periodData;
            }
        }

        return $trendData;
    }

    /**
     * Get average resolution time and first response time for a time period.
     *
     * @param  array<int>  $auditIds
     * @return array{avg_resolution_time: float|null, avg_first_response: float|null}
     */
    private function getResolutionTimeMetrics(array $auditIds, Carbon $start, Carbon $end): array
    {
        // Get resolved findings in this period
        $resolvedFindings = Finding::query()
            ->whereIn('audit_id', $auditIds)
            ->whereNotNull('resolved_at')
            ->where('status', FindingStatus::Resolved)
            ->whereBetween('resolved_at', [$start, $end])
            ->get();

        $avgResolutionTime = null;
        if ($resolvedFindings->isNotEmpty()) {
            $totalMinutes = $resolvedFindings->sum(fn ($finding) => $finding->getTotalResolutionTime() ?? 0);
            $avgResolutionTime = round($totalMinutes / $resolvedFindings->count(), 2);
        }

        // Get first response times for findings that transitioned in this period
        $findingsWithTransitions = Finding::query()
            ->whereIn('audit_id', $auditIds)
            ->with(['statusTransitions' => function ($query) use ($start, $end) {
                $query->where('from_status', FindingStatus::Open)
                    ->where('to_status', FindingStatus::InProgress)
                    ->whereBetween('transitioned_at', [$start, $end])
                    ->orderBy('transitioned_at', 'asc');
            }])
            ->whereHas('statusTransitions', function ($query) use ($start, $end) {
                $query->where('from_status', FindingStatus::Open)
                    ->where('to_status', FindingStatus::InProgress)
                    ->whereBetween('transitioned_at', [$start, $end]);
            })
            ->get();

        $avgFirstResponse = null;
        if ($findingsWithTransitions->isNotEmpty()) {
            $totalMinutes = $findingsWithTransitions->sum(fn ($finding) => $finding->getTimeToFirstResponse() ?? 0);
            $avgFirstResponse = round($totalMinutes / $findingsWithTransitions->count(), 2);
        }

        return [
            'avg_resolution_time' => $avgResolutionTime,
            'avg_first_response' => $avgFirstResponse,
        ];
    }

    /**
     * Get paginated audit history data for the table.
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    private function getPaginatedAuditHistory(Builder $baseQuery, string $sortBy, string $sortDirection)
    {
        $query = (clone $baseQuery)
            ->with(['datacenter', 'findings']);

        // Handle sorting
        switch ($sortBy) {
            case 'total_findings':
                $query->withCount('findings')
                    ->orderBy('findings_count', $sortDirection);
                break;

            case 'avg_resolution_time':
                // Sort by completion date as proxy since avg resolution requires calculation
                $query->orderBy('updated_at', $sortDirection);
                break;

            case 'completion_date':
            default:
                $query->orderBy('updated_at', $sortDirection);
                break;
        }

        $paginated = $query->paginate(15);

        // Transform the data to include calculated fields
        $paginated->getCollection()->transform(function ($audit) {
            $findings = $audit->findings;

            // Calculate severity counts
            $severityCounts = [
                'critical' => $findings->where('severity', FindingSeverity::Critical)->count(),
                'high' => $findings->where('severity', FindingSeverity::High)->count(),
                'medium' => $findings->where('severity', FindingSeverity::Medium)->count(),
                'low' => $findings->where('severity', FindingSeverity::Low)->count(),
            ];

            // Calculate average resolution time
            $resolvedFindings = $findings->filter(fn ($f) => $f->resolved_at !== null);
            $avgResolutionTime = null;

            if ($resolvedFindings->isNotEmpty()) {
                $totalMinutes = $resolvedFindings->sum(fn ($f) => $f->getTotalResolutionTime() ?? 0);
                $avgResolutionTime = $totalMinutes / $resolvedFindings->count();
            }

            return [
                'id' => $audit->id,
                'name' => $audit->name,
                'type' => $audit->type->value,
                'type_label' => $audit->type->label(),
                'datacenter_id' => $audit->datacenter_id,
                'datacenter_name' => $audit->datacenter?->name ?? 'Unknown',
                'completion_date' => $audit->updated_at?->toIso8601String(),
                'completion_date_formatted' => $audit->updated_at?->format('M d, Y'),
                'total_findings' => $findings->count(),
                'severity_counts' => $severityCounts,
                'avg_resolution_time' => $avgResolutionTime,
                'avg_resolution_time_formatted' => $this->formatMinutesToHumanReadable($avgResolutionTime),
            ];
        });

        return $paginated;
    }

    /**
     * Format minutes to human-readable format (hours/days).
     */
    private function formatMinutesToHumanReadable(?float $minutes): string
    {
        if ($minutes === null || $minutes <= 0) {
            return 'N/A';
        }

        $hours = $minutes / 60;

        if ($hours < 1) {
            return round($minutes).' min';
        }

        if ($hours < 24) {
            return round($hours, 1).' hours';
        }

        $days = $hours / 24;

        return round($days, 1).' days';
    }
}
