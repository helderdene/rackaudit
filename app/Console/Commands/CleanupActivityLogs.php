<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

class CleanupActivityLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activity:cleanup {--days=365 : Number of days to retain logs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete activity logs older than the specified retention period';

    /**
     * The chunk size for processing deletions.
     */
    protected int $chunkSize = 1000;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning up activity logs older than {$days} days (before {$cutoffDate->toDateString()})...");

        $totalDeleted = 0;

        // Process deletions in chunks to avoid memory issues
        do {
            $deleted = ActivityLog::query()
                ->where('created_at', '<', $cutoffDate)
                ->limit($this->chunkSize)
                ->delete();

            $totalDeleted += $deleted;

            if ($deleted > 0) {
                $this->info("Deleted {$deleted} records in this chunk...");
            }
        } while ($deleted === $this->chunkSize);

        $this->info("Deleted {$totalDeleted} activity log record(s).");

        return Command::SUCCESS;
    }
}
