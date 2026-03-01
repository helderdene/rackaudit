<?php

namespace App\Enums;

/**
 * Verification status for audit device verification items.
 *
 * Tracks the verification state of each device during an inventory audit:
 * - Pending: Not yet verified by an operator
 * - Verified: Device confirmed at documented location
 * - NotFound: Device not present at documented location
 * - Discrepant: Device found but with issues (wrong position, wrong asset tag, etc.)
 */
enum DeviceVerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case NotFound = 'not_found';
    case Discrepant = 'discrepant';

    /**
     * Get the human-readable label for the verification status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Verified => 'Verified',
            self::NotFound => 'Not Found',
            self::Discrepant => 'Discrepant',
        };
    }
}
