<?php

namespace App\Enums;

/**
 * Device depth classification.
 *
 * Represents the physical depth of a device relative to standard
 * rack depth, affecting placement and cooling considerations.
 */
enum DeviceDepth: string
{
    case Standard = 'standard';
    case Deep = 'deep';
    case Shallow = 'shallow';

    /**
     * Get the human-readable label for the device depth.
     */
    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Deep => 'Deep',
            self::Shallow => 'Shallow',
        };
    }
}
