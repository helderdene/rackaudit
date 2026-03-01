<?php

namespace App\Observers;

use App\Events\FindingAssigned;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Observer for Finding model events.
 *
 * Dispatches FindingAssigned broadcast events when a finding's
 * assignee changes to enable real-time UI updates.
 */
class FindingObserver
{
    /**
     * Handle the Finding "updated" event.
     *
     * Only broadcasts when the assigned_to field has changed.
     */
    public function updated(Finding $finding): void
    {
        // Only dispatch if assignee changed
        if ($finding->isDirty('assigned_to')) {
            $newAssigneeId = $finding->assigned_to;

            // Only broadcast if there's a new assignee
            if ($newAssigneeId !== null) {
                $assignee = User::find($newAssigneeId);
                $assigner = Auth::user();

                if ($assignee && $assigner) {
                    FindingAssigned::dispatch($finding, $assignee, $assigner);
                }
            }
        }
    }
}
