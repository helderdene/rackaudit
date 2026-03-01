<?php

namespace App\Listeners;

use App\Events\ConnectionChanged;
use App\Jobs\DetectDiscrepanciesJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Listener that triggers discrepancy detection when a connection changes.
 *
 * Queues a detection job scoped to only the affected connection to prevent
 * full datacenter rescans. This ensures real-time detection without blocking
 * the main request cycle.
 */
class DetectDiscrepanciesForConnection implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     */
    public string $queue = 'discrepancies';

    /**
     * Handle the ConnectionChanged event.
     *
     * Dispatches a detection job scoped to the specific connection that changed.
     */
    public function handle(ConnectionChanged $event): void
    {
        DetectDiscrepanciesJob::dispatch(
            connectionId: $event->connection->id
        );
    }
}
