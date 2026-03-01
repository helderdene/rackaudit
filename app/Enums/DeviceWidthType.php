<?php

namespace App\Enums;

/**
 * Device width type for rack placement.
 *
 * Represents how much horizontal space a device occupies in a rack slot,
 * mapping to the TypeScript DeviceWidth type for frontend consistency.
 */
enum DeviceWidthType: string
{
    case Full = 'full';
    case HalfLeft = 'half_left';
    case HalfRight = 'half_right';

    /**
     * Get the human-readable label for the width type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Full => 'Full Width',
            self::HalfLeft => 'Half Left',
            self::HalfRight => 'Half Right',
        };
    }
}
