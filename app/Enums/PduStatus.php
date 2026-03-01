<?php

namespace App\Enums;

/**
 * PDU operational status.
 *
 * Indicates the current operational state of a Power Distribution Unit,
 * including active service, inactive, or under maintenance.
 */
enum PduStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Maintenance = 'maintenance';

    /**
     * Get the human-readable label for the PDU status.
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
