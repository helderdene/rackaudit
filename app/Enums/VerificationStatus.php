<?php

namespace App\Enums;

/**
 * Verification status for audit connection verification items.
 *
 * Tracks the verification state of each connection during an audit:
 * - Pending: Not yet verified by an operator
 * - Verified: Confirmed as matching expected state
 * - Discrepant: Marked as having a discrepancy requiring attention
 */
enum VerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Discrepant = 'discrepant';

    /**
     * Get the human-readable label for the verification status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Verified => 'Verified',
            self::Discrepant => 'Discrepant',
        };
    }
}
