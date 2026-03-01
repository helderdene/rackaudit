<?php

namespace App\Console\Commands;

use App\Models\BulkExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Command to cleanup old export files and records.
 *
 * Deletes export files and BulkExport records older than the specified
 * retention period (default: 7 days).
 */
class CleanupOldExports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exports:cleanup {--days=7 : Number of days to retain exports}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete bulk export files and records older than the specified retention period';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning up bulk exports older than {$days} days (before {$cutoffDate->toDateTimeString()})...");

        // Find old exports
        $oldExports = BulkExport::where('created_at', '<', $cutoffDate)->get();

        $deletedFiles = 0;
        $deletedRecords = 0;

        foreach ($oldExports as $export) {
            // Delete the file if it exists
            if ($export->file_path && Storage::disk('local')->exists($export->file_path)) {
                Storage::disk('local')->delete($export->file_path);
                $deletedFiles++;
            }

            // Delete the database record
            $export->delete();
            $deletedRecords++;
        }

        $this->info("Deleted {$deletedFiles} file(s) and {$deletedRecords} record(s).");

        return Command::SUCCESS;
    }
}
