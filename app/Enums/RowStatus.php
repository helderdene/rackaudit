<?php

namespace App\Enums;

/**
 * Row operational status.
 *
 * Indicates whether a row is currently active and accepting rack placements
 * or inactive and not in use.
 */
enum RowStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * Get the human-readable label for the row status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }
}
