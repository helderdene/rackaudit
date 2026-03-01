<?php

namespace App\Enums;

/**
 * PDU electrical phase configuration.
 *
 * Defines the electrical phase type of a Power Distribution Unit,
 * which affects power capacity and circuit configuration.
 */
enum PduPhase: string
{
    case Single = 'single';
    case ThreePhase = 'three_phase';

    /**
     * Get the human-readable label for the PDU phase.
     */
    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single Phase',
            self::ThreePhase => 'Three Phase',
        };
    }
}
