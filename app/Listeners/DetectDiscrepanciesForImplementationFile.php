<?php

namespace App\Listeners;

use App\Events\ImplementationFileApproved;
use App\Jobs\DetectDiscrepanciesJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Listener that triggers discrepancy detection when an implementation file is approved.
 *
 * Queues a detection job for all connections in the approved file since
 * the file now serves as an authoritative source for expected connections.
 */
class DetectDiscrepanciesForImplementationFile implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     */
    public string $queue = 'discrepancies';

    /**
     * Handle the ImplementationFileApproved event.
     *
     * Dispatches a detection job for all connections in the implementation file.
     */
    public function handle(ImplementationFileApproved $event): void
    {
        DetectDiscrepanciesJob::dispatch(
            implementationFileId: $event->implementationFile->id
        );
    }
}
