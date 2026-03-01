<?php

namespace App\Enums;

/**
 * Finding severity classification.
 *
 * Indicates the urgency and impact level of a finding:
 * - Critical: Requires immediate attention, significant impact
 * - High: Urgent issue that needs prompt resolution
 * - Medium: Moderate priority, should be addressed in normal workflow
 * - Low: Minor issue that can be addressed when convenient
 */
enum FindingSeverity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    /**
     * Get the human-readable label for the severity.
     */
    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Critical',
            self::High => 'High',
            self::Medium => 'Medium',
            self::Low => 'Low',
        };
    }

    /**
     * Get the badge color classes for the severity.
     *
     * Returns Tailwind CSS classes for styling severity badges.
     */
    public function color(): string
    {
        return match ($this) {
            self::Critical => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
            self::High => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
            self::Medium => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
            self::Low => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        };
    }
}
