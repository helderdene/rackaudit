<?php

namespace App\Services;

use App\Enums\AuditType;
use App\Enums\DiscrepancyType;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Models\Audit;
use App\Models\AuditReport;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Service for generating PDF audit reports.
 *
 * Generates comprehensive reports containing executive summaries,
 * findings organized by severity, and connection comparison results
 * (for connection-type audits).
 */
class AuditReportService
{
    /**
     * Generate a PDF report for the given audit.
     */
    public function generateReport(Audit $audit, User $generator): AuditReport
    {
        $audit->load([
            'datacenter',
            'room',
            'findings.assignee',
            'findings.verification.expectedConnection',
            'findings.verification.connection',
            'findings.deviceVerification.device',
        ]);

        $executiveSummary = $this->calculateExecutiveSummary($audit);
        $groupedFindings = $this->groupFindingsBySeverity($audit);
        $connectionComparison = $this->buildConnectionComparisonSummary($audit);

        $pdf = Pdf::loadView('pdf.audit-report', [
            'audit' => $audit,
            'executiveSummary' => $executiveSummary,
            'groupedFindings' => $groupedFindings,
            'connectionComparison' => $connectionComparison,
        ]);

        $pdf->setPaper('a4', 'portrait');

        $filePath = $this->storeReport($pdf, $audit);
        $fileSize = Storage::disk('local')->size($filePath);

        return AuditReport::create([
            'audit_id' => $audit->id,
            'user_id' => $generator->id,
            'file_path' => $filePath,
            'generated_at' => now(),
            'file_size_bytes' => $fileSize,
        ]);
    }

    /**
     * Calculate executive summary metrics for the audit.
     *
     * @return array{
     *     total_findings: int,
     *     resolution_rate: float,
     *     critical_count: int,
     *     date_range: array{start: string|null, end: string|null}
     * }
     */
    public function calculateExecutiveSummary(Audit $audit): array
    {
        $findings = $audit->findings;
        $totalFindings = $findings->count();

        $resolvedCount = $findings->where('status', FindingStatus::Resolved)->count();
        $resolutionRate = $totalFindings > 0
            ? round(($resolvedCount / $totalFindings) * 100, 1)
            : 0.0;

        $criticalCount = $findings->where('severity', FindingSeverity::Critical)->count();

        return [
            'total_findings' => $totalFindings,
            'resolution_rate' => $resolutionRate,
            'critical_count' => $criticalCount,
            'date_range' => [
                'start' => $audit->created_at?->format('M d, Y'),
                'end' => ($audit->completed_at ?? now())->format('M d, Y'),
            ],
        ];
    }

    /**
     * Group audit findings by severity in the correct order.
     *
     * Returns findings grouped by severity with Critical first,
     * followed by High, Medium, and Low. Empty severity sections
     * are omitted from the result.
     *
     * @return array<string, \Illuminate\Support\Collection>
     */
    public function groupFindingsBySeverity(Audit $audit): array
    {
        $severityOrder = [
            FindingSeverity::Critical,
            FindingSeverity::High,
            FindingSeverity::Medium,
            FindingSeverity::Low,
        ];

        $findings = $audit->findings()
            ->with(['assignee', 'verification.expectedConnection', 'verification.connection', 'deviceVerification.device'])
            ->get();

        $grouped = [];

        foreach ($severityOrder as $severity) {
            $severityFindings = $findings->where('severity', $severity);

            if ($severityFindings->isNotEmpty()) {
                $grouped[$severity->value] = $severityFindings;
            }
        }

        return $grouped;
    }

    /**
     * Build connection comparison summary for connection-type audits.
     *
     * Returns null for inventory audits since they don't have
     * connection verifications.
     *
     * @return array{
     *     matched_count: int,
     *     missing_count: int,
     *     unexpected_count: int,
     *     total_count: int
     * }|null
     */
    public function buildConnectionComparisonSummary(Audit $audit): ?array
    {
        if ($audit->type === AuditType::Inventory) {
            return null;
        }

        $verifications = $audit->verifications();

        $matchedCount = (clone $verifications)
            ->where('comparison_status', DiscrepancyType::Matched)
            ->count();

        $missingCount = (clone $verifications)
            ->where('comparison_status', DiscrepancyType::Missing)
            ->count();

        $unexpectedCount = (clone $verifications)
            ->where('comparison_status', DiscrepancyType::Unexpected)
            ->count();

        return [
            'matched_count' => $matchedCount,
            'missing_count' => $missingCount,
            'unexpected_count' => $unexpectedCount,
            'total_count' => $matchedCount + $missingCount + $unexpectedCount,
        ];
    }

    /**
     * Store the generated PDF report to the filesystem.
     */
    protected function storeReport(\Barryvdh\DomPDF\PDF $pdf, Audit $audit): string
    {
        $timestamp = now()->format('YmdHis');
        $filename = "audit-report-{$audit->id}-{$timestamp}.pdf";
        $filePath = "reports/audits/{$filename}";

        Storage::disk('local')->put($filePath, $pdf->output());

        return $filePath;
    }
}
