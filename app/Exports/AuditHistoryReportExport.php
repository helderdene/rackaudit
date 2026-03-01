<?php

namespace App\Exports;

use App\Enums\AuditStatus;
use App\Enums\AuditType;
use App\Enums\FindingSeverity;
use App\Models\Audit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Export class for Audit History Report data.
 *
 * Exports completed audit data including finding counts by severity
 * and resolution time metrics for analysis in external spreadsheet applications.
 */
class AuditHistoryReportExport extends AbstractDataExport
{
    /**
     * Get the column headers for the export.
     *
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'Audit Name',
            'Type',
            'Datacenter',
            'Completion Date',
            'Total Findings',
            'Critical',
            'High',
            'Medium',
            'Low',
            'Avg Resolution Time (hours)',
        ];
    }

    /**
     * Get the sheet title.
     */
    public function title(): string
    {
        return 'Audit History Report';
    }

    /**
     * Get the query builder for audits with eager loading.
     */
    protected function query(): Builder
    {
        $query = Audit::query()
            ->where('status', AuditStatus::Completed)
            ->with(['datacenter', 'findings']);

        // Apply date range filter
        $dateRange = $this->calculateDateRange(
            $this->filters['time_range_preset'] ?? null,
            $this->filters['start_date'] ?? null,
            $this->filters['end_date'] ?? null
        );

        $query->whereBetween('updated_at', [$dateRange['start'], $dateRange['end']]);

        // Apply accessible datacenter filter
        if (! empty($this->filters['accessible_datacenter_ids'])) {
            $query->whereIn('datacenter_id', $this->filters['accessible_datacenter_ids']);
        }

        // Apply specific datacenter filter
        if (! empty($this->filters['datacenter_id'])) {
            $query->where('datacenter_id', $this->filters['datacenter_id']);
        }

        // Apply audit type filter
        if (! empty($this->filters['audit_type'])) {
            $auditType = AuditType::tryFrom($this->filters['audit_type']);
            if ($auditType !== null) {
                $query->where('type', $auditType);
            }
        }

        return $query->orderByDesc('updated_at');
    }

    /**
     * Transform an Audit model to a row array with calculated metrics.
     *
     * @param  Audit  $audit
     * @return array<mixed>
     */
    protected function transformRow($audit): array
    {
        $findings = $audit->findings;

        // Calculate severity counts
        $critical = $findings->where('severity', FindingSeverity::Critical)->count();
        $high = $findings->where('severity', FindingSeverity::High)->count();
        $medium = $findings->where('severity', FindingSeverity::Medium)->count();
        $low = $findings->where('severity', FindingSeverity::Low)->count();

        // Calculate average resolution time in hours
        $resolvedFindings = $findings->filter(fn ($f) => $f->resolved_at !== null);
        $avgResolutionTimeHours = 'N/A';

        if ($resolvedFindings->isNotEmpty()) {
            $totalMinutes = $resolvedFindings->sum(fn ($f) => $f->getTotalResolutionTime() ?? 0);
            $avgMinutes = $totalMinutes / $resolvedFindings->count();
            $avgResolutionTimeHours = round($avgMinutes / 60, 2);
        }

        return [
            $audit->name,
            $audit->type->label(),
            $audit->datacenter?->name ?? 'Unknown',
            $audit->updated_at?->format('Y-m-d'),
            $findings->count(),
            $critical,
            $high,
            $medium,
            $low,
            $avgResolutionTimeHours,
        ];
    }

    /**
     * Calculate date range from preset or custom dates.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    private function calculateDateRange(?string $preset, ?string $startDate, ?string $endDate): array
    {
        if ($startDate !== null && $endDate !== null) {
            return [
                'start' => Carbon::parse($startDate)->startOfDay(),
                'end' => Carbon::parse($endDate)->endOfDay(),
            ];
        }

        $end = now()->endOfDay();

        $start = match ($preset) {
            '30_days' => now()->subDays(30)->startOfDay(),
            '6_months' => now()->subMonths(6)->startOfDay(),
            default => now()->subMonths(12)->startOfDay(),
        };

        return [
            'start' => $start,
            'end' => $end,
        ];
    }
}
