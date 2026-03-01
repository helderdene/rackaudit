<?php

namespace App\Enums;

/**
 * Schedule frequency for automated report generation.
 *
 * Defines the available scheduling intervals for report generation:
 * - Daily: Report generated every day at specified time
 * - Weekly: Report generated on specified day of week at specified time
 * - Monthly: Report generated on specified day of month at specified time
 */
enum ScheduleFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    /**
     * Get the human-readable label for the frequency.
     */
    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
        };
    }

    /**
     * Get a brief description for the frequency.
     */
    public function description(): string
    {
        return match ($this) {
            self::Daily => 'Generate report every day at the specified time',
            self::Weekly => 'Generate report on the specified day of the week',
            self::Monthly => 'Generate report on the specified day of the month',
        };
    }
}
