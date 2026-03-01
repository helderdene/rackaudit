<?php

namespace App\Jobs;

use App\Models\Audit;
use App\Models\User;
use App\Services\AuditReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job for generating audit reports asynchronously.
 *
 * Processes audit report generation in the background for larger audits
 * to prevent timeout issues. Creates a PDF report containing executive
 * summary, findings by severity, and connection comparison results.
 */
class GenerateAuditReportJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $auditId,
        public int $userId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AuditReportService $service): void
    {
        $audit = Audit::find($this->auditId);
        $user = User::find($this->userId);

        if ($audit === null) {
            Log::error('GenerateAuditReportJob: Audit not found', [
                'audit_id' => $this->auditId,
            ]);

            return;
        }

        if ($user === null) {
            Log::error('GenerateAuditReportJob: User not found', [
                'user_id' => $this->userId,
            ]);

            return;
        }

        try {
            $report = $service->generateReport($audit, $user);

            Log::info('GenerateAuditReportJob: Report generated successfully', [
                'audit_id' => $this->auditId,
                'user_id' => $this->userId,
                'report_id' => $report->id,
                'file_path' => $report->file_path,
            ]);
        } catch (\Exception $e) {
            Log::error('GenerateAuditReportJob: Failed to generate report', [
                'audit_id' => $this->auditId,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateAuditReportJob: Job failed permanently', [
            'audit_id' => $this->auditId,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}
