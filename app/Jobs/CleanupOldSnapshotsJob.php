<?php

namespace App\Jobs;

use App\Models\CapacitySnapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job for cleaning up old capacity snapshots.
 *
 * Deletes CapacitySnapshot records older than 52 weeks (1 year)
 * to maintain data retention policy and prevent database bloat.
 */
class CleanupOldSnapshotsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Number of weeks to retain snapshots.
     */
    private const RETENTION_WEEKS = 52;

    /**
     * Chunk size for efficient deletion of large datasets.
     */
    private const CHUNK_SIZE = 100;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cutoffDate = now()->subWeeks(self::RETENTION_WEEKS);
        $deletedCount = 0;

        CapacitySnapshot::query()
            ->where('snapshot_date', '<', $cutoffDate)
            ->chunkById(self::CHUNK_SIZE, function ($snapshots) use (&$deletedCount) {
                foreach ($snapshots as $snapshot) {
                    $snapshot->delete();
                    $deletedCount++;
                }
            });

        Log::info('CleanupOldSnapshotsJob: Completed cleanup', [
            'cutoff_date' => $cutoffDate->toDateString(),
            'deleted_count' => $deletedCount,
            'retention_weeks' => self::RETENTION_WEEKS,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CleanupOldSnapshotsJob: Job failed permanently', [
            'error' => $exception->getMessage(),
        ]);
    }
}
