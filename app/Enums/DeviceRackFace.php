<?php

namespace App\Enums;

/**
 * Device rack face for placement.
 *
 * Represents which side of the rack a device is mounted on,
 * mapping to the TypeScript RackFace type for frontend consistency.
 */
enum DeviceRackFace: string
{
    case Front = 'front';
    case Rear = 'rear';

    /**
     * Get the human-readable label for the rack face.
     */
    public function label(): string
    {
        return match ($this) {
            self::Front => 'Front',
            self::Rear => 'Rear',
        };
    }
}
