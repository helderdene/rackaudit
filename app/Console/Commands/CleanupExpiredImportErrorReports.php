<?php

namespace App\Console\Commands;

use App\Services\ImportErrorReportService;
use Illuminate\Console\Command;

/**
 * Command to cleanup expired import error reports.
 *
 * Deletes error report files older than 24 hours and updates
 * associated BulkImport records to clear the error_report_path.
 */
class CleanupExpiredImportErrorReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'imports:cleanup-errors {--hours=24 : Number of hours to retain error reports}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete import error reports older than the specified retention period';

    /**
     * Execute the console command.
     */
    public function handle(ImportErrorReportService $errorReportService): int
    {
        $hours = (int) $this->option('hours');

        $this->info("Cleaning up import error reports older than {$hours} hours...");

        $deletedCount = $errorReportService->cleanupExpiredReports($hours);

        $this->info("Deleted {$deletedCount} expired error report(s).");

        return Command::SUCCESS;
    }
}
