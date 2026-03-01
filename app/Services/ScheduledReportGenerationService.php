<?php

namespace App\Services;

use App\Enums\ReportFormat;
use App\Models\ReportSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * Service for generating scheduled reports.
 *
 * Handles report generation for scheduled reports by delegating to CustomReportBuilderService.
 * Applies the creator's datacenter access permissions at generation time and determines
 * the appropriate format (PDF or CSV) based on the schedule configuration.
 */
class ScheduledReportGenerationService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        protected CustomReportBuilderService $reportBuilder
    ) {}

    /**
     * Generate a report for the given schedule.
     *
     * Determines the format (PDF/CSV) and calls the appropriate generation method.
     * Applies the creator's datacenter access permissions to filter the data.
     *
     * @return string File path to the generated report
     */
    public function generateReport(ReportSchedule $schedule): string
    {
        $schedule->loadMissing(['user', 'user.datacenters']);

        $reportType = $schedule->report_type;
        $config = $schedule->report_configuration;

        // Extract configuration
        $columns = $config['columns'] ?? [];
        $filters = $config['filters'] ?? [];
        $sort = $config['sort'] ?? [];
        $groupBy = $config['group_by'] ?? null;

        // Apply datacenter access restrictions based on creator's permissions
        $filters = $this->applyDatacenterAccessRestrictions($schedule, $filters);

        // Generate report based on format
        return match ($schedule->format) {
            ReportFormat::PDF => $this->reportBuilder->generatePdfReport(
                $reportType,
                $columns,
                $filters,
                $sort,
                $groupBy,
                $schedule->user
            ),
            ReportFormat::CSV => $this->reportBuilder->generateCsvReport(
                $reportType,
                $columns,
                $filters,
                $sort,
                $groupBy,
                $schedule->user
            ),
        };
    }

    /**
     * Get the file size of a generated report.
     *
     * @return int File size in bytes
     */
    public function getReportFileSize(string $filePath): int
    {
        if (! Storage::disk('local')->exists($filePath)) {
            return 0;
        }

        return Storage::disk('local')->size($filePath);
    }

    /**
     * Apply datacenter access restrictions based on the schedule creator's permissions.
     *
     * If no datacenter filter is specified, restricts to only datacenters the user has access to.
     * If a datacenter filter is specified, validates it's within the user's accessible datacenters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function applyDatacenterAccessRestrictions(ReportSchedule $schedule, array $filters): array
    {
        $user = $schedule->user;

        // Get user's accessible datacenter IDs
        $accessibleDatacenterIds = $user->datacenters()->pluck('datacenters.id')->toArray();

        // If user has no datacenter access, return filters that will produce empty results
        if (empty($accessibleDatacenterIds)) {
            // Set an impossible filter that will yield no results
            $filters['datacenter_id'] = -1;

            return $filters;
        }

        // If a specific datacenter filter is set, validate it's accessible
        if (! empty($filters['datacenter_id'])) {
            if (! in_array($filters['datacenter_id'], $accessibleDatacenterIds)) {
                // User doesn't have access to this datacenter, set impossible filter
                $filters['datacenter_id'] = -1;
            }
            // If valid, keep the existing filter

            return $filters;
        }

        // No datacenter filter set - apply user's accessible datacenters restriction
        // We use a special key that the report builder can interpret
        $filters['accessible_datacenter_ids'] = $accessibleDatacenterIds;

        return $filters;
    }

    /**
     * Build filter description for the scheduled report.
     *
     * @param  array<string, mixed>  $filters
     */
    public function buildFilterDescription(array $filters): string
    {
        return $this->reportBuilder->buildFilterDescription($filters);
    }
}
