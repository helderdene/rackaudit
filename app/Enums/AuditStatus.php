<?php

namespace App\Enums;

/**
 * Audit status classification.
 *
 * Tracks the lifecycle state of an audit:
 * - Pending: Audit created and ready for execution
 * - InProgress: Audit execution has started
 * - Completed: Audit has been finished
 * - Cancelled: Audit was cancelled before completion
 */
enum AuditStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Get the human-readable label for the audit status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }
}
