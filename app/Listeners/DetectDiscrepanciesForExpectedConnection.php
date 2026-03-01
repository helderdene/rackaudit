<?php

namespace App\Listeners;

use App\Events\ExpectedConnectionConfirmed;
use App\Jobs\DetectDiscrepanciesJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Listener that triggers discrepancy detection when an expected connection is confirmed.
 *
 * Queues a detection job for the specific connection pair to check if it
 * matches an existing actual connection or represents a discrepancy.
 */
class DetectDiscrepanciesForExpectedConnection implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     *
     * @var string
     */
    public string $queue = 'discrepancies';

    /**
     * Handle the ExpectedConnectionConfirmed event.
     *
     * Dispatches a detection job for the specific expected connection pair.
     */
    public function handle(ExpectedConnectionConfirmed $event): void
    {
        DetectDiscrepanciesJob::dispatch(
            expectedConnectionId: $event->expectedConnection->id
        );
    }
}
