<?php

namespace App\Enums;

/**
 * Row orientation for cooling aisle designation.
 *
 * Defines whether a row faces a hot aisle (exhaust) or cold aisle (intake)
 * to support proper airflow management in the datacenter.
 */
enum RowOrientation: string
{
    case HotAisle = 'hot_aisle';
    case ColdAisle = 'cold_aisle';

    /**
     * Get the human-readable label for the row orientation.
     */
    public function label(): string
    {
        return match ($this) {
            self::HotAisle => 'Hot Aisle',
            self::ColdAisle => 'Cold Aisle',
        };
    }
}
