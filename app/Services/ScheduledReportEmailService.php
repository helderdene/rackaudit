<?php

namespace App\Services;

use App\Mail\ScheduledReportMailable;
use App\Models\ReportSchedule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Service for sending scheduled report emails.
 *
 * Handles email delivery of generated reports to distribution list members.
 * Includes report name, timestamp, and filter summary in the email body.
 * Respects configurable attachment size limits.
 */
class ScheduledReportEmailService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        protected ScheduledReportGenerationService $reportService
    ) {}

    /**
     * Send a report to all members of the schedule's distribution list.
     *
     * @throws \RuntimeException If the file exceeds the maximum attachment size
     */
    public function sendReport(ReportSchedule $schedule, string $filePath): void
    {
        $schedule->loadMissing(['distributionList', 'distributionList.members']);

        // Check attachment size limit
        $this->validateAttachmentSize($filePath);

        // Get distribution list members
        $members = $schedule->distributionList?->members ?? collect();

        if ($members->isEmpty()) {
            throw new \RuntimeException('Distribution list has no valid recipients');
        }

        // Build filter description for the email body
        $config = $schedule->report_configuration;
        $filters = $config['filters'] ?? [];
        $filterDescription = $this->reportService->buildFilterDescription($filters);

        // Get the full file path for attachment
        $fullFilePath = Storage::disk('local')->path($filePath);

        // Send email to each member
        foreach ($members as $member) {
            Mail::to($member->email)->send(new ScheduledReportMailable(
                schedule: $schedule,
                filePath: $fullFilePath,
                filterDescription: $filterDescription
            ));
        }
    }

    /**
     * Get the count of recipients in the distribution list.
     */
    public function getRecipientsCount(ReportSchedule $schedule): int
    {
        $schedule->loadMissing(['distributionList', 'distributionList.members']);

        return $schedule->distributionList?->members?->count() ?? 0;
    }

    /**
     * Validate that the attachment size is within the configured limit.
     *
     * @throws \RuntimeException If the file exceeds the maximum size
     */
    protected function validateAttachmentSize(string $filePath): void
    {
        $maxSizeMb = config('scheduled-reports.max_attachment_size_mb', 25);
        $maxSizeBytes = $maxSizeMb * 1024 * 1024;

        if (! Storage::disk('local')->exists($filePath)) {
            throw new \RuntimeException("Report file not found: {$filePath}");
        }

        $fileSize = Storage::disk('local')->size($filePath);

        if ($fileSize > $maxSizeBytes) {
            $fileSizeMb = round($fileSize / (1024 * 1024), 2);
            throw new \RuntimeException(
                "Report file exceeds maximum attachment size. Size: {$fileSizeMb}MB, Limit: {$maxSizeMb}MB"
            );
        }
    }
}
