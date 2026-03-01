<?php

namespace App\Enums;

/**
 * Rack U-height (unit height) options.
 *
 * Represents standard rack unit heights used in datacenter equipment.
 * Each case value represents the number of rack units available.
 */
enum RackUHeight: int
{
    case U42 = 42;
    case U45 = 45;
    case U48 = 48;

    /**
     * Get the human-readable label for the rack U-height.
     */
    public function label(): string
    {
        return match ($this) {
            self::U42 => '42U',
            self::U45 => '45U',
            self::U48 => '48U',
        };
    }
}
