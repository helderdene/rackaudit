<?php

namespace App\Listeners;

use App\Enums\DiscrepancyStatus;
use App\Events\FindingResolved;
use App\Models\Discrepancy;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Listener that auto-resolves discrepancies when their linked finding is resolved.
 *
 * When a Finding is resolved during an audit, if that finding has a linked
 * Discrepancy, this listener will automatically mark the discrepancy as resolved
 * with the same user and timestamp.
 */
class ResolveLinkedDiscrepancy implements ShouldQueue
{
    /**
     * Handle the FindingResolved event.
     */
    public function handle(FindingResolved $event): void
    {
        // Find the discrepancy linked to this finding
        $discrepancy = Discrepancy::where('finding_id', $event->finding->id)->first();

        if (! $discrepancy) {
            return;
        }

        // Only resolve if discrepancy is in InAudit status
        if ($discrepancy->status !== DiscrepancyStatus::InAudit) {
            return;
        }

        // Resolve the discrepancy
        $discrepancy->update([
            'status' => DiscrepancyStatus::Resolved,
            'resolved_at' => now(),
            'resolved_by' => $event->resolvedBy->id,
        ]);
    }
}
