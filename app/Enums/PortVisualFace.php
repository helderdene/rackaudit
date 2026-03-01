<?php

namespace App\Enums;

/**
 * Port visual face.
 *
 * Represents which face of the device the port is located on
 * for visual port mapping capabilities.
 */
enum PortVisualFace: string
{
    case Front = 'front';
    case Rear = 'rear';

    /**
     * Get the human-readable label for the visual face.
     */
    public function label(): string
    {
        return match ($this) {
            self::Front => 'Front',
            self::Rear => 'Rear',
        };
    }
}
