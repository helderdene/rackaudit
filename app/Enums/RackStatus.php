<?php

namespace App\Enums;

/**
 * Rack operational status.
 *
 * Indicates the current operational state of a rack within a row,
 * including whether it is active, inactive, or under maintenance.
 */
enum RackStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Maintenance = 'maintenance';

    /**
     * Get the human-readable label for the rack status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Maintenance => 'Maintenance',
        };
    }
}
