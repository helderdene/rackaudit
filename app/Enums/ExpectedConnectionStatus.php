<?php

namespace App\Enums;

/**
 * Expected connection review status.
 *
 * Tracks the review state of parsed expected connections from
 * implementation files before they become authoritative for
 * audit comparisons.
 */
enum ExpectedConnectionStatus: string
{
    case PendingReview = 'pending_review';
    case Confirmed = 'confirmed';
    case Skipped = 'skipped';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PendingReview => 'Pending Review',
            self::Confirmed => 'Confirmed',
            self::Skipped => 'Skipped',
        };
    }
}
