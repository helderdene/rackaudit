<?php

namespace App\Observers;

use App\Events\RackChanged;
use App\Models\Rack;
use Illuminate\Support\Facades\Auth;

/**
 * Observer for Rack model events.
 *
 * Dispatches RackChanged broadcast events when racks are created,
 * updated, or deleted to enable real-time UI updates.
 */
class RackObserver
{
    /**
     * Handle the Rack "created" event.
     */
    public function created(Rack $rack): void
    {
        $user = Auth::user();
        RackChanged::dispatch($rack, 'created', $user);
    }

    /**
     * Handle the Rack "updated" event.
     */
    public function updated(Rack $rack): void
    {
        $user = Auth::user();
        RackChanged::dispatch($rack, 'updated', $user);
    }

    /**
     * Handle the Rack "deleted" event.
     */
    public function deleted(Rack $rack): void
    {
        $user = Auth::user();
        RackChanged::dispatch($rack, 'deleted', $user);
    }
}
