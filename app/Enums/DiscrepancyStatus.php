<?php

namespace App\Enums;

/**
 * Status enum for discrepancy tracking.
 *
 * Tracks the lifecycle state of detected discrepancies:
 * - Open: Newly detected, requires attention
 * - Acknowledged: Reviewed but not yet resolved
 * - Resolved: Issue has been addressed and closed
 * - InAudit: Discrepancy has been imported into an audit for verification
 */
enum DiscrepancyStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
    case InAudit = 'in_audit';

    /**
     * Get the human-readable label for the discrepancy status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Acknowledged => 'Acknowledged',
            self::Resolved => 'Resolved',
            self::InAudit => 'In Audit',
        };
    }
}
