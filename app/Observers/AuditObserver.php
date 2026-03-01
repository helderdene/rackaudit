<?php

namespace App\Observers;

use App\Enums\AuditStatus;
use App\Events\AuditStatusChanged;
use App\Models\Audit;
use Illuminate\Support\Facades\Auth;

/**
 * Observer for Audit model events.
 *
 * Dispatches AuditStatusChanged broadcast events when an audit's
 * status transitions to enable real-time UI updates.
 */
class AuditObserver
{
    /**
     * Handle the Audit "updated" event.
     *
     * Only broadcasts when the status field has changed.
     */
    public function updated(Audit $audit): void
    {
        // Only dispatch if status changed
        if ($audit->isDirty('status')) {
            $user = Auth::user();

            if ($user) {
                $originalStatus = $audit->getOriginal('status');
                $oldStatus = $originalStatus instanceof AuditStatus
                    ? $originalStatus
                    : AuditStatus::from($originalStatus);
                $newStatus = $audit->status;

                AuditStatusChanged::dispatch($audit, $oldStatus, $newStatus, $user);
            }
        }
    }
}
