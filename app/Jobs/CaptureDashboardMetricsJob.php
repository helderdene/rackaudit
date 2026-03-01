<?php

namespace App\Jobs;

use App\Enums\AuditStatus;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Models\ActivityLog;
use App\Models\Audit;
use App\Models\DashboardSnapshot;
use App\Models\Datacenter;
use App\Models\Finding;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job for capturing daily dashboard metrics snapshots for all datacenters.
 *
 * Captures audit/finding counts by severity, pending/completed audit counts,
 * and activity metrics grouped by entity type for historical trend analysis.
 */
class CaptureDashboardMetricsJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Open finding statuses that should be counted.
     *
     * @var array<FindingStatus>
     */
    private const OPEN_FINDING_STATUSES = [
        FindingStatus::Open,
        FindingStatus::InProgress,
        FindingStatus::PendingReview,
        FindingStatus::Deferred,
    ];

    /**
     * Entity types to track for activity metrics.
     *
     * @var array<string>
     */
    private const ENTITY_TYPES = [
        'Device' => 'App\\Models\\Device',
        'Rack' => 'App\\Models\\Rack',
        'Connection' => 'App\\Models\\Connection',
        'Audit' => 'App\\Models\\Audit',
        'Finding' => 'App\\Models\\Finding',
    ];

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $datacenters = Datacenter::all();
        $snapshotDate = now()->toDateString();
        $capturedCount = 0;

        foreach ($datacenters as $datacenter) {
            try {
                $this->captureMetricsForDatacenter($datacenter, $snapshotDate);
                $capturedCount++;
            } catch (\Exception $e) {
                Log::error('CaptureDashboardMetricsJob: Failed to capture metrics for datacenter', [
                    'datacenter_id' => $datacenter->id,
                    'datacenter_name' => $datacenter->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('CaptureDashboardMetricsJob: Completed dashboard metrics snapshots', [
            'snapshot_date' => $snapshotDate,
            'datacenters_captured' => $capturedCount,
            'total_datacenters' => $datacenters->count(),
        ]);
    }

    /**
     * Capture all metrics for a single datacenter.
     */
    private function captureMetricsForDatacenter(Datacenter $datacenter, string $snapshotDate): void
    {
        // Get audit counts
        $auditCounts = $this->getAuditCounts($datacenter->id);

        // Get finding counts by severity
        $findingCounts = $this->getFindingCounts($datacenter->id);

        // Get activity counts by entity type
        $activityMetrics = $this->getActivityCounts($snapshotDate);

        DashboardSnapshot::updateOrCreate(
            [
                'datacenter_id' => $datacenter->id,
                'snapshot_date' => $snapshotDate,
            ],
            [
                'pending_audits_count' => $auditCounts['pending'],
                'completed_audits_count' => $auditCounts['completed'],
                'open_findings_count' => $findingCounts['total'],
                'critical_findings_count' => $findingCounts['critical'],
                'high_findings_count' => $findingCounts['high'],
                'medium_findings_count' => $findingCounts['medium'],
                'low_findings_count' => $findingCounts['low'],
                'activity_count' => $activityMetrics['total'],
                'activity_by_entity' => $activityMetrics['by_entity'],
            ]
        );
    }

    /**
     * Get pending and completed audit counts for a datacenter.
     *
     * @return array{pending: int, completed: int}
     */
    private function getAuditCounts(int $datacenterId): array
    {
        $pendingCount = Audit::query()
            ->where('datacenter_id', $datacenterId)
            ->whereIn('status', [AuditStatus::Pending, AuditStatus::InProgress])
            ->count();

        $completedCount = Audit::query()
            ->where('datacenter_id', $datacenterId)
            ->where('status', AuditStatus::Completed)
            ->count();

        return [
            'pending' => $pendingCount,
            'completed' => $completedCount,
        ];
    }

    /**
     * Get open finding counts grouped by severity for a datacenter.
     *
     * @return array{total: int, critical: int, high: int, medium: int, low: int}
     */
    private function getFindingCounts(int $datacenterId): array
    {
        $baseQuery = fn () => Finding::query()
            ->whereIn('status', self::OPEN_FINDING_STATUSES)
            ->whereHas('audit', function (Builder $q) use ($datacenterId) {
                $q->where('datacenter_id', $datacenterId);
            });

        $criticalCount = (clone $baseQuery())
            ->where('severity', FindingSeverity::Critical)
            ->count();

        $highCount = (clone $baseQuery())
            ->where('severity', FindingSeverity::High)
            ->count();

        $mediumCount = (clone $baseQuery())
            ->where('severity', FindingSeverity::Medium)
            ->count();

        $lowCount = (clone $baseQuery())
            ->where('severity', FindingSeverity::Low)
            ->count();

        return [
            'total' => $criticalCount + $highCount + $mediumCount + $lowCount,
            'critical' => $criticalCount,
            'high' => $highCount,
            'medium' => $mediumCount,
            'low' => $lowCount,
        ];
    }

    /**
     * Get activity counts grouped by entity type for the snapshot date.
     *
     * Note: Activity is tracked globally, not per-datacenter, as ActivityLog
     * does not have a direct datacenter relationship.
     *
     * @return array{total: int, by_entity: array<string, int>}
     */
    private function getActivityCounts(string $snapshotDate): array
    {
        $byEntity = [];

        foreach (self::ENTITY_TYPES as $label => $modelClass) {
            $count = ActivityLog::query()
                ->where('subject_type', $modelClass)
                ->whereDate('created_at', $snapshotDate)
                ->count();
            $byEntity[$label] = $count;
        }

        $total = array_sum($byEntity);

        return [
            'total' => $total,
            'by_entity' => $byEntity,
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CaptureDashboardMetricsJob: Job failed permanently', [
            'error' => $exception->getMessage(),
        ]);
    }
}
