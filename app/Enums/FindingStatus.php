<?php

namespace App\Enums;

/**
 * Finding status for audit discrepancy findings.
 *
 * Tracks the resolution state of findings created when
 * connections are marked as discrepant during an audit:
 * - Open: Finding requires attention and resolution
 * - InProgress: Actively being worked on
 * - PendingReview: Work completed, awaiting verification
 * - Deferred: Temporarily postponed for later resolution
 * - Resolved: Finding has been addressed and closed
 */
enum FindingStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case PendingReview = 'pending_review';
    case Deferred = 'deferred';
    case Resolved = 'resolved';

    /**
     * Get the human-readable label for the finding status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In Progress',
            self::PendingReview => 'Pending Review',
            self::Deferred => 'Deferred',
            self::Resolved => 'Resolved',
        };
    }

    /**
     * Get the badge color classes for the status.
     *
     * Returns Tailwind CSS classes for styling status badges.
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
            self::InProgress => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
            self::PendingReview => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
            self::Deferred => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
            self::Resolved => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        };
    }

    /**
     * Check if the current status can transition to the target status.
     *
     * Status workflow:
     * - Open -> InProgress, Deferred
     * - InProgress -> PendingReview, Deferred, Open
     * - PendingReview -> Resolved, InProgress, Open
     * - Deferred -> Open, InProgress
     * - Resolved -> Open (admin only, handled in controller)
     *
     * Note: Transition from Open directly to Resolved is not allowed
     * (must go through InProgress -> PendingReview -> Resolved).
     */
    public function canTransitionTo(self $targetStatus): bool
    {
        $allowedTransitions = match ($this) {
            self::Open => [self::InProgress, self::Deferred],
            self::InProgress => [self::PendingReview, self::Deferred, self::Open],
            self::PendingReview => [self::Resolved, self::InProgress, self::Open],
            self::Deferred => [self::Open, self::InProgress],
            self::Resolved => [self::Open], // Only admin can reopen
        };

        return in_array($targetStatus, $allowedTransitions, true);
    }
}
