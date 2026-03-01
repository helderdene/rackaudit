<?php

namespace App\Services;

use App\Enums\AuditStatus;
use App\Enums\AuditType;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\Finding;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Service for generating audit history PDF reports.
 *
 * Generates comprehensive reports containing executive summaries,
 * finding counts by severity, resolution time metrics, and
 * trend data for completed audits.
 */
class AuditHistoryReportService
{
    /**
     * Generate a PDF report for audit history.
     *
     * @param  array{
     *     time_range_preset?: string|null,
     *     start_date?: string|null,
     *     end_date?: string|null,
     *     datacenter_id?: int|null,
     *     audit_type?: string|null,
     *     accessible_datacenter_ids?: array<int>
     * }  $filters
     */
    public function generatePdfReport(array $filters, User $generator): string
    {
        // Calculate date range
        $dateRange = $this->calculateDateRange(
            $filters['time_range_preset'] ?? null,
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null
        );

        // Build query for completed audits
        $accessibleDatacenterIds = $filters['accessible_datacenter_ids'] ?? [];
        $datacenterId = $filters['datacenter_id'] ?? null;
        $auditType = $filters['audit_type'] ?? null;

        $query = Audit::query()
            ->where('status', AuditStatus::Completed)
            ->whereBetween('updated_at', [$dateRange['start'], $dateRange['end']]);

        if (! empty($accessibleDatacenterIds)) {
            $query->whereIn('datacenter_id', $accessibleDatacenterIds);
        }

        if ($datacenterId !== null) {
            $query->where('datacenter_id', $datacenterId);
        }

        if ($auditType !== null) {
            $auditTypeEnum = AuditType::tryFrom($auditType);
            if ($auditTypeEnum !== null) {
                $query->where('type', $auditTypeEnum);
            }
        }

        // Get audits with findings
        $audits = $query->with(['datacenter', 'findings'])->get();

        // Calculate metrics
        $metrics = $this->calculateMetrics($audits);

        // Get top audits for table
        $topAudits = $this->getTopAudits($audits);

        // Build filter scope description
        $filterScope = $this->buildFilterScope($filters, $dateRange);

        $pdf = Pdf::loadView('pdf.audit-history-report', [
            'metrics' => $metrics,
            'audits' => $topAudits,
            'filterScope' => $filterScope,
            'generatedBy' => $generator->name,
            'generatedAt' => now(),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $this->storeReport($pdf);
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

    /**
     * Calculate summary metrics from audits.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $audits
     * @return array{
     *     totalAudits: int,
     *     totalFindings: int,
     *     bySeverity: array<string, int>,
     *     avgResolutionTime: string,
     *     avgFirstResponse: string
     * }
     */
    private function calculateMetrics($audits): array
    {
        $auditIds = $audits->pluck('id')->toArray();
        $findings = Finding::whereIn('audit_id', $auditIds)->get();

        // Severity breakdown
        $bySeverity = [];
        foreach (FindingSeverity::cases() as $severity) {
            $bySeverity[$severity->value] = $findings->where('severity', $severity)->count();
        }

        // Average resolution time
        $resolvedFindings = $findings->filter(fn ($f) => $f->resolved_at !== null && $f->status === FindingStatus::Resolved);
        $avgResolutionTime = 'N/A';

        if ($resolvedFindings->isNotEmpty()) {
            $totalMinutes = $resolvedFindings->sum(fn ($f) => $f->getTotalResolutionTime() ?? 0);
            $avgMinutes = $totalMinutes / $resolvedFindings->count();
            $avgResolutionTime = $this->formatMinutes($avgMinutes);
        }

        // Average first response time
        $avgFirstResponse = 'N/A';
        $findingsWithResponse = $findings->filter(fn ($f) => $f->getTimeToFirstResponse() !== null);

        if ($findingsWithResponse->isNotEmpty()) {
            $totalMinutes = $findingsWithResponse->sum(fn ($f) => $f->getTimeToFirstResponse() ?? 0);
            $avgMinutes = $totalMinutes / $findingsWithResponse->count();
            $avgFirstResponse = $this->formatMinutes($avgMinutes);
        }

        return [
            'totalAudits' => $audits->count(),
            'totalFindings' => $findings->count(),
            'bySeverity' => $bySeverity,
            'avgResolutionTime' => $avgResolutionTime,
            'avgFirstResponse' => $avgFirstResponse,
        ];
    }

    /**
     * Get top audits for the PDF table.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $audits
     * @return \Illuminate\Support\Collection
     */
    private function getTopAudits($audits)
    {
        return $audits
            ->sortByDesc('updated_at')
            ->take(20)
            ->map(function ($audit) {
                $findings = $audit->findings;

                $severityCounts = [
                    'critical' => $findings->where('severity', FindingSeverity::Critical)->count(),
                    'high' => $findings->where('severity', FindingSeverity::High)->count(),
                    'medium' => $findings->where('severity', FindingSeverity::Medium)->count(),
                    'low' => $findings->where('severity', FindingSeverity::Low)->count(),
                ];

                $resolvedFindings = $findings->filter(fn ($f) => $f->resolved_at !== null);
                $avgResolutionTime = 'N/A';

                if ($resolvedFindings->isNotEmpty()) {
                    $totalMinutes = $resolvedFindings->sum(fn ($f) => $f->getTotalResolutionTime() ?? 0);
                    $avgMinutes = $totalMinutes / $resolvedFindings->count();
                    $avgResolutionTime = $this->formatMinutes($avgMinutes);
                }

                return [
                    'name' => $audit->name,
                    'type' => $audit->type->label(),
                    'datacenter' => $audit->datacenter?->name ?? 'Unknown',
                    'completion_date' => $audit->updated_at?->format('M d, Y'),
                    'total_findings' => $findings->count(),
                    'severity_counts' => $severityCounts,
                    'avg_resolution_time' => $avgResolutionTime,
                ];
            });
    }

    /**
     * Build filter scope description.
     *
     * @param  array{start: Carbon, end: Carbon}  $dateRange
     */
    private function buildFilterScope(array $filters, array $dateRange): string
    {
        $parts = [];

        // Time range
        $parts[] = 'Period: ' . $dateRange['start']->format('M d, Y') . ' - ' . $dateRange['end']->format('M d, Y');

        // Datacenter
        if (! empty($filters['datacenter_id'])) {
            $datacenter = Datacenter::find($filters['datacenter_id']);
            if ($datacenter) {
                $parts[] = 'Datacenter: ' . $datacenter->name;
            }
        } else {
            $parts[] = 'Datacenter: All';
        }

        // Audit type
        if (! empty($filters['audit_type'])) {
            $auditType = AuditType::tryFrom($filters['audit_type']);
            if ($auditType) {
                $parts[] = 'Audit Type: ' . $auditType->label();
            }
        } else {
            $parts[] = 'Audit Type: All';
        }

        return implode(' | ', $parts);
    }

    /**
     * Format minutes to human-readable format.
     */
    private function formatMinutes(float $minutes): string
    {
        if ($minutes <= 0) {
            return 'N/A';
        }

        $hours = $minutes / 60;

        if ($hours < 1) {
            return round($minutes) . ' min';
        }

        if ($hours < 24) {
            return round($hours, 1) . ' hours';
        }

        $days = $hours / 24;

        return round($days, 1) . ' days';
    }

    /**
     * Store the generated PDF report.
     */
    private function storeReport(\Barryvdh\DomPDF\PDF $pdf): string
    {
        $timestamp = now()->format('YmdHis');
        $filename = "audit-history-report-{$timestamp}.pdf";
        $filePath = "reports/audit-history/{$filename}";

        Storage::disk('local')->put($filePath, $pdf->output());

        return $filePath;
    }
}
