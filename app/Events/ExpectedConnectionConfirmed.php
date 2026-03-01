<?php

namespace App\Events;

use App\Models\ExpectedConnection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when an expected connection is confirmed.
 *
 * Triggers discrepancy detection for that specific connection pair to
 * check if it matches an existing actual connection or represents a new
 * expected state that needs to be audited.
 */
class ExpectedConnectionConfirmed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  ExpectedConnection  $expectedConnection  The expected connection that was confirmed
     */
    public function __construct(
        public ExpectedConnection $expectedConnection
    ) {}
}
