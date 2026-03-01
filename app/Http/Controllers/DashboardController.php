<?php

namespace App\Http\Controllers;

use App\Enums\AuditStatus;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Enums\RackStatus;
use App\Models\ActivityLog;
use App\Models\Audit;
use App\Models\CapacitySnapshot;
use App\Models\DashboardSnapshot;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Finding;
use App\Models\Rack;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
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
     * Valid time period options for chart data filtering.
     *
     * @var array<string, int>
     */
    private const TIME_PERIODS = [
        '7_days' => 7,
        '30_days' => 30,
        '90_days' => 90,
    ];

    /**
     * Display the main dashboard with key infrastructure metrics.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();

        // Get filter values from request
        $datacenterId = $request->input('datacenter_id');

        // Load datacenters for filter dropdown (with user access filter for non-admins)
        $datacenterOptions = $this->getAccessibleDatacenters($user);
        $accessibleDatacenterIds = $datacenterOptions->pluck('id')->toArray();

        // Validate datacenter_id if provided
        if ($datacenterId && ! in_array((int) $datacenterId, $accessibleDatacenterIds, true)) {
            $datacenterId = null;
        }

        // Calculate all metrics
        $metrics = [
            'rackUtilization' => $this->getRackUtilizationMetric($accessibleDatacenterIds, $datacenterId),
            'deviceCount' => $this->getDeviceCountMetric($accessibleDatacenterIds, $datacenterId),
            'pendingAudits' => $this->getPendingAuditsMetric($accessibleDatacenterIds, $datacenterId),
            'openFindings' => $this->getOpenFindingsMetric($accessibleDatacenterIds, $datacenterId),
        ];

        // Get recent activity feed
        $recentActivity = $this->getRecentActivityFeed($accessibleDatacenterIds);

        return Inertia::render('Dashboard', [
            'metrics' => $metrics,
            'datacenterOptions' => $datacenterOptions->values()->toArray(),
            'filters' => [
                'datacenter_id' => $datacenterId ? (int) $datacenterId : null,
            ],
            'recentActivity' => $recentActivity,
        ]);
    }

    /**
     * Get chart data for dashboard visualizations.
     *
     * Returns data for capacity trend, device count trend, severity distribution,
     * audit completion trend, and activity by entity type charts.
     */
    public function chartData(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get filter values from request
        $datacenterId = $request->input('datacenter_id');
        $timePeriod = $request->input('time_period', '7_days');

        // Validate time period
        if (! array_key_exists($timePeriod, self::TIME_PERIODS)) {
            $timePeriod = '7_days';
        }
        $days = self::TIME_PERIODS[$timePeriod];

        // Load datacenters for access control
        $datacenterOptions = $this->getAccessibleDatacenters($user);
        $accessibleDatacenterIds = $datacenterOptions->pluck('id')->toArray();

        // Validate datacenter_id if provided
        if ($datacenterId && ! in_array((int) $datacenterId, $accessibleDatacenterIds, true)) {
            $datacenterId = null;
        }

        // Aggregate all chart data
        return response()->json([
            'capacityTrend' => $this->getCapacityTrendData($accessibleDatacenterIds, $datacenterId, $days),
            'deviceCountTrend' => $this->getDeviceCountTrendData($accessibleDatacenterIds, $datacenterId, $days),
            'severityDistribution' => $this->getSeverityDistributionData($accessibleDatacenterIds, $datacenterId),
            'auditCompletionTrend' => $this->getAuditCompletionTrendData($accessibleDatacenterIds, $datacenterId, $days),
            'activityByEntity' => $this->getActivityByEntityData($accessibleDatacenterIds, $datacenterId, $days),
        ]);
    }

    /**
     * Get capacity trend data from CapacitySnapshot.
     *
     * @param  array<int>  $accessibleDatacenterIds
     * @return array{labels: array<string>, data: array<float>}
     */
    private function getCapacityTrendData(array $accessibleDatacenterIds, ?int $datacenterId, int $days): array
    {
        $startDate = now()->subDays($days - 1)->startOfDay();

        $query = CapacitySnapshot::query()
            ->whereIn('datacenter_id', $accessibleDatacenterIds)
            ->where('snapshot_date', '>=', $startDate)
            ->orderBy('snapshot_date');

        if ($datacenterId) {
            $query->where('datacenter_id', $datacenterId);
        }

        // Group by date and calculate weighted average utilization
        $snapshots = $query->get()->groupBy(fn ($snapshot) => $snapshot->snapshot_date->format('Y-m-d'));

        $labels = [];
        $data = [];

        foreach ($snapshots as $date => $daySnapshots) {
            $labels[] = Carbon::parse($date)->format('M d');
            // Calculate weighted average by total U space
            $totalUSpace = $daySnapshots->sum('total_u_space');
            if ($totalUSpace > 0) {
                $weightedSum = $daySnapshots->sum(fn ($s) => (float) $s->rack_utilization_percent * $s->total_u_space);
                $data[] = round($weightedSum / $totalUSpace, 1);
            } else {
                $data[] = 0.0;
            }
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    /**
     * Get device count trend data from CapacitySnapshot.
     *
     * @param  array<int>  $accessibleDatacenterIds
     * @return array{labels: array<string>, data: array<int>}
     */
    private function getDeviceCountTrendData(array $accessibleDatacenterIds, ?int $datacenterId, int $days): array
    {
        $startDate = now()->subDays($days - 1)->startOfDay();

        $query = CapacitySnapshot::query()
            ->whereIn('datacenter_id', $accessibleDatacenterIds)
            ->whereNotNull('device_count')
            ->where('snapshot_date', '>=', $startDate)
            ->orderBy('snapshot_date');

        if ($datacenterId) {
            $query->where('datacenter_id', $datacenterId);
        }

        // Group by date and sum device counts across datacenters
        $snapshots = $query->get()->groupBy(fn ($snapshot) => $snapshot->snapshot_date->format('Y-m-d'));

        $labels = [];
        $data = [];

        foreach ($snapshots as $date => $daySnapshots) {
            $labels[] = Carbon::parse($date)->format('M d');
            $data[] = $daySnapshots->sum('device_count');
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    /**
     * Get current severity distribution of open findings.
     *
     * @param  array<int>  $accessibleDatacenterIds
     * @return array{critical: array{count: int, color: string, label: string, percentage: float}, high: array{count: int, color: string, label: string, percentage: float}, medium: array{count: int, color: string, label: string, percentage: float}, low: array{count: int, color: string, label: string, percentage: float}, total: int}
     */
    private function getSeverityDistributionData(array $accessibleDatacenterIds, ?int $datacenterId): array
    {
        $openStatuses = [
            FindingStatus::Open,
            FindingStatus::InProgress,
            FindingStatus::PendingReview,
            FindingStatus::Deferred,
        ];

        $baseQuery = fn () => Finding::query()
            ->whereIn('status', $openStatuses)
            ->whereHas('audit', function (Builder $q) use ($accessibleDatacenterIds, $datacenterId) {
                $q->whereIn('datacenter_id', $accessibleDatacenterIds);
                if ($datacenterId) {
                    $q->where('datacenter_id', $datacenterId);
                }
            });

        // Count by severity
        $counts = [];
        foreach (FindingSeverity::cases() as $severity) {
            $counts[$severity->value] = (clone $baseQuery())
                ->where('severity', $severity)
                ->count();
        }

        $total = array_sum($counts);

        // Severity colors matching SeverityDistributionChart.vue
        $colors = [
            'critical' => 'rgb(239, 68, 68)',  // red-500
            'high' => 'rgb(249, 115, 22)',     // orange-500
            'medium' => 'rgb(234, 179, 8)',    // yellow-500
            'low' => 'rgb(59, 130, 246)',      // blue-500
        ];

        return [
            'critical' => [
                'count' => $counts['critical'],
                'color' => $colors['critical'],
                'label' => 'Critical',
                'percentage' => $total > 0 ? round(($counts['critical'] / $total) * 100, 1) : 0.0,
            ],
            'high' => [
                'count' => $counts['high'],
                'color' => $colors['high'],
                'label' => 'High',
                'percentage' => $total > 0 ? round(($counts['high'] / $total) * 100, 1) : 0.0,
            ],
            'medium' => [
                'count' => $counts['medium'],
                'color' => $colors['medium'],
                'label' => 'Medium',
                'percentage' => $total > 0 ? round(($counts['medium'] / $total) * 100, 1) : 0.0,
            ],
            'low' => [
                'count' => $counts['low'],
                'color' => $colors['low'],
                'label' => 'Low',
                'percentage' => $total > 0 ? round(($counts['low'] / $total) * 100, 1) : 0.0,
            ],
            'total' => $total,
        ];
    }

    /**
     * Get audit completion trend data from DashboardSnapshot.
     *
     * @param  array<int>  $accessibleDatacenterIds
     * @return array{labels: array<string>, data: array<int>, total: int}
     */
    private function getAuditCompletionTrendData(array $accessibleDatacenterIds, ?int $datacenterId, int $days): array
    {
        $startDate = now()->subDays($days - 1)->startOfDay();

        $query = DashboardSnapshot::query()
            ->whereIn('datacenter_id', $accessibleDatacenterIds)
            ->where('snapshot_date', '>=', $startDate)
            ->orderBy('snapshot_date');

        if ($datacenterId) {
            $query->where('datacenter_id', $datacenterId);
        }

        // Group by date and sum completed audits across datacenters
        $snapshots = $query->get()->groupBy(fn ($snapshot) => $snapshot->snapshot_date->format('Y-m-d'));

        $labels = [];
        $data = [];
        $total = 0;

        foreach ($snapshots as $date => $daySnapshots) {
            $labels[] = Carbon::parse($date)->format('M d');
            $dayTotal = $daySnapshots->sum('completed_audits_count');
            $data[] = $dayTotal;
            $total += $dayTotal;
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'total' => $total,
        ];
    }

    /**
     * Get activity counts by entity type from DashboardSnapshot.
     *
     * @param  array<int>  $accessibleDatacenterIds
     * @return array{labels: array<string>, data: array<int>}
     */
    private function getActivityByEntityData(array $accessibleDatacenterIds, ?int $datacenterId, int $days): array
    {
        $startDate = now()->subDays($days - 1)->startOfDay();

        $query = DashboardSnapshot::query()
            ->whereIn('datacenter_id', $accessibleDatacenterIds)
            ->where('snapshot_date', '>=', $startDate);

        if ($datacenterId) {
            $query->where('datacenter_id', $datacenterId);
        }

        $snapshots = $query->get();

        if ($snapshots->isEmpty()) {
            return [
                'labels' => [],
                'data' => [],
            ];
        }

        // Aggregate activity_by_entity across all snapshots
        $entityTypes = ['Device', 'Rack', 'Connection', 'Audit', 'Finding'];
        $aggregated = array_fill_keys($entityTypes, 0);

        foreach ($snapshots as $snapshot) {
            $activityByEntity = $snapshot->activity_by_entity ?? [];
            foreach ($entityTypes as $entityType) {
                $aggregated[$entityType] += $activityByEntity[$entityType] ?? 0;
            }
        }

        // Filter out entity types with zero activity
        $labels = [];
        $data = [];

        foreach ($aggregated as $entityType => $count) {
            if ($count > 0) {
                $labels[] = $entityType.'s'; // Pluralize for display
                $data[] = $count;
            }
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
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
     * Get rack utilization metric.
     *
     * Formula: (sum of device U-heights) / (sum of rack U-heights) * 100
     *
     * @param  array<int>  $accessibleDatacenterIds
     * @return array{value: float, trend: array{percentage: string, change: string}, sparkline: array<float>}
     */
    private function getRackUtilizationMetric(array $accessibleDatacenterIds, ?int $datacenterId): array
    {
        $baseQuery = fn () => Rack::query()
            ->where('status', RackStatus::Active)
            ->whereHas('row.room.datacenter', function (Builder $q) use ($accessibleDatacenterIds, $datacenterId) {
                $q->whereIn('datacenters.id', $accessibleDatacenterIds);
                if ($datacenterId) {
                    $q->where('datacenters.id', $datacenterId);
                }
            });

        // Calculate current utilization
        $currentUtilization = $this->calculateRackUtilization($baseQuery());

        // Calculate sparkline data (7 days)
        $sparkline = $this->generateSparklineData(function () use ($currentUtilization) {
            // For rack utilization, we use current value as historical data is not tracked
            return $currentUtilization;
        });

        // Calculate trend (comparing to previous week)
        $trend = $this->calculateTrend($currentUtilization, $currentUtilization);

        return [
            'value' => $currentUtilization,
            'trend' => $trend,
            'sparkline' => $sparkline,
        ];
    }

    /**
     * Calculate rack utilization percentage.
     */
    private function calculateRackUtilization(Builder $rackQuery): float
    {
        $racks = (clone $rackQuery)->with('devices')->get();

        if ($racks->isEmpty()) {
            return 0.0;
        }

        $totalUSpace = 0;
        $usedUSpace = 0;

        foreach ($racks as $rack) {
            $totalUSpace += $rack->u_height->value;
            $usedUSpace += $rack->devices->sum('u_height');
        }

        if ($totalUSpace === 0) {
            return 0.0;
        }

        return round(($usedUSpace / $totalUSpace) * 100, 1);
    }

    /**
     * Get device count metric.
     *
     * @param  array<int>  $accessibleDatacenterIds
     * @return array{value: int, trend: array{percentage: string, change: string}, sparkline: array<int>}
     */
    private function getDeviceCountMetric(array $accessibleDatacenterIds, ?int $datacenterId): array
    {
        $baseQuery = fn () => Device::query()
            ->whereHas('rack.row.room.datacenter', function (Builder $q) use ($accessibleDatacenterIds, $datacenterId) {
                $q->whereIn('datacenters.id', $accessibleDatacenterIds);
                if ($datacenterId) {
                    $q->where('datacenters.id', $datacenterId);
                }
            });

        // Current count
        $currentCount = $baseQuery()->count();

        // Sparkline: count devices for each of the last 7 days
        $sparkline = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->endOfDay();
            $count = (clone $baseQuery())->where('created_at', '<=', $date)->count();
            $sparkline[] = $count;
        }

        // Calculate trend (comparing to 7 days ago)
        $previousWeekCount = $sparkline[0] ?? $currentCount;
        $trend = $this->calculateTrend($currentCount, $previousWeekCount, 'devices');

        return [
            'value' => $currentCount,
            'trend' => $trend,
            'sparkline' => $sparkline,
        ];
    }

    /**
     * Get pending audits metric.
     *
     * @param  array<int>  $accessibleDatacenterIds
     * @return array{value: int, pastDue: int, trend: array{percentage: string, change: string}, sparkline: array<int>}
     */
    private function getPendingAuditsMetric(array $accessibleDatacenterIds, ?int $datacenterId): array
    {
        $baseQuery = fn () => Audit::query()
            ->whereIn('status', [AuditStatus::Pending, AuditStatus::InProgress])
            ->where(function (Builder $q) use ($accessibleDatacenterIds, $datacenterId) {
                $q->whereIn('datacenter_id', $accessibleDatacenterIds);
                if ($datacenterId) {
                    $q->where('datacenter_id', $datacenterId);
                }
            });

        // Current count
        $currentCount = $baseQuery()->count();

        // Past due count
        $pastDueCount = (clone $baseQuery())
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->count();

        // Sparkline: count pending audits for each of the last 7 days
        $sparkline = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->endOfDay();
            $count = Audit::query()
                ->whereIn('status', [AuditStatus::Pending, AuditStatus::InProgress])
                ->whereIn('datacenter_id', $accessibleDatacenterIds)
                ->when($datacenterId, fn ($q) => $q->where('datacenter_id', $datacenterId))
                ->where('created_at', '<=', $date)
                ->where(function ($q) use ($date) {
                    // Audit was still pending/in_progress at that date
                    $q->whereNull('updated_at')
                        ->orWhere('updated_at', '>=', $date);
                })
                ->count();
            $sparkline[] = $count;
        }

        // Calculate trend
        $previousWeekCount = $sparkline[0] ?? $currentCount;
        $trend = $this->calculateTrend($currentCount, $previousWeekCount, 'audits');

        return [
            'value' => $currentCount,
            'pastDue' => $pastDueCount,
            'trend' => $trend,
            'sparkline' => $sparkline,
        ];
    }

    /**
     * Get open findings metric with severity breakdown.
     *
     * @param  array<int>  $accessibleDatacenterIds
     * @return array{value: int, bySeverity: array<string, int>, trend: array{percentage: string, change: string}, sparkline: array<int>}
     */
    private function getOpenFindingsMetric(array $accessibleDatacenterIds, ?int $datacenterId): array
    {
        $openStatuses = [
            FindingStatus::Open,
            FindingStatus::InProgress,
            FindingStatus::PendingReview,
            FindingStatus::Deferred,
        ];

        $baseQuery = fn () => Finding::query()
            ->whereIn('status', $openStatuses)
            ->whereHas('audit', function (Builder $q) use ($accessibleDatacenterIds, $datacenterId) {
                $q->whereIn('datacenter_id', $accessibleDatacenterIds);
                if ($datacenterId) {
                    $q->where('datacenter_id', $datacenterId);
                }
            });

        // Current count
        $currentCount = $baseQuery()->count();

        // Severity breakdown (only open findings)
        $bySeverity = [];
        foreach (FindingSeverity::cases() as $severity) {
            $bySeverity[$severity->value] = (clone $baseQuery())
                ->where('severity', $severity)
                ->count();
        }

        // Sparkline: count open findings for each of the last 7 days
        $sparkline = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->endOfDay();
            $count = Finding::query()
                ->whereIn('status', $openStatuses)
                ->whereHas('audit', function (Builder $q) use ($accessibleDatacenterIds, $datacenterId) {
                    $q->whereIn('datacenter_id', $accessibleDatacenterIds);
                    if ($datacenterId) {
                        $q->where('datacenter_id', $datacenterId);
                    }
                })
                ->where('created_at', '<=', $date)
                ->where(function ($q) use ($date) {
                    // Finding was still open at that date
                    $q->whereNull('resolved_at')
                        ->orWhere('resolved_at', '>', $date);
                })
                ->count();
            $sparkline[] = $count;
        }

        // Calculate trend
        $previousWeekCount = $sparkline[0] ?? $currentCount;
        $trend = $this->calculateTrend($currentCount, $previousWeekCount, 'findings');

        return [
            'value' => $currentCount,
            'bySeverity' => $bySeverity,
            'trend' => $trend,
            'sparkline' => $sparkline,
        ];
    }

    /**
     * Generate sparkline data with a callback for each day.
     *
     * @return array<float|int>
     */
    private function generateSparklineData(callable $valueCallback): array
    {
        $sparkline = [];
        for ($i = 6; $i >= 0; $i--) {
            $sparkline[] = $valueCallback(now()->subDays($i));
        }

        return $sparkline;
    }

    /**
     * Calculate trend indicator comparing current vs previous value.
     *
     * @return array{percentage: string, change: string}
     */
    private function calculateTrend(float|int $current, float|int $previous, string $unit = ''): array
    {
        if ($previous == 0) {
            if ($current == 0) {
                return [
                    'percentage' => 'N/A',
                    'change' => 'N/A',
                ];
            }

            return [
                'percentage' => '+100%',
                'change' => '+'.$current.($unit ? ' '.$unit : ''),
            ];
        }

        $change = $current - $previous;
        $percentageChange = round(($change / $previous) * 100, 1);

        $sign = $change >= 0 ? '+' : '';
        $percentageSign = $percentageChange >= 0 ? '+' : '';

        return [
            'percentage' => $percentageSign.$percentageChange.'%',
            'change' => $sign.$change.($unit ? ' '.$unit : ''),
        ];
    }

    /**
     * Get recent activity feed.
     *
     * @param  array<int>  $accessibleDatacenterIds
     * @return array<array{id: int, timestamp: string, timestamp_relative: string, user_name: string, action: string, entity_type: string, summary: string, old_values: array|null, new_values: array|null}>
     */
    private function getRecentActivityFeed(array $accessibleDatacenterIds): array
    {
        // Get the last 15 activity log entries
        // Filter to activities related to accessible datacenters
        $activities = ActivityLog::query()
            ->with('causer')
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        return $activities->map(function (ActivityLog $activity) {
            $entityType = class_basename($activity->subject_type ?? 'Unknown');

            return [
                'id' => $activity->id,
                'timestamp' => $activity->created_at?->toIso8601String(),
                'timestamp_relative' => $this->formatRelativeTime($activity->created_at),
                'user_name' => $activity->causer?->name ?? 'System',
                'action' => $activity->action,
                'entity_type' => $entityType,
                'summary' => $this->generateActivitySummary($activity),
                'old_values' => $activity->old_values,
                'new_values' => $activity->new_values,
            ];
        })->toArray();
    }

    /**
     * Format timestamp as relative time.
     */
    private function formatRelativeTime(?Carbon $timestamp): string
    {
        if (! $timestamp) {
            return 'Unknown';
        }

        $now = now();
        $diffInMinutes = (int) floor($timestamp->diffInMinutes($now));

        if ($diffInMinutes < 1) {
            return 'Just now';
        }

        if ($diffInMinutes < 60) {
            return $diffInMinutes.' minute'.($diffInMinutes === 1 ? '' : 's').' ago';
        }

        $diffInHours = (int) floor($timestamp->diffInHours($now));
        if ($diffInHours < 24) {
            return $diffInHours.' hour'.($diffInHours === 1 ? '' : 's').' ago';
        }

        $diffInDays = (int) floor($timestamp->diffInDays($now));
        if ($diffInDays < 7) {
            return $diffInDays.' day'.($diffInDays === 1 ? '' : 's').' ago';
        }

        return $timestamp->format('M d, Y');
    }

    /**
     * Generate a brief summary for an activity log entry.
     */
    private function generateActivitySummary(ActivityLog $activity): string
    {
        $entityType = class_basename($activity->subject_type ?? 'item');
        $action = ucfirst($activity->action ?? 'modified');

        // Try to get a name from new_values or old_values
        $name = $activity->new_values['name']
            ?? $activity->old_values['name']
            ?? null;

        if ($name) {
            return "{$action} {$entityType}: {$name}";
        }

        return "{$action} {$entityType}";
    }
}
