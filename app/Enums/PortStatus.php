<?php

namespace App\Enums;

/**
 * Port status.
 *
 * Represents the current availability state of a port.
 */
enum PortStatus: string
{
    case Available = 'available';
    case Connected = 'connected';
    case Reserved = 'reserved';
    case Disabled = 'disabled';

    /**
     * Get the human-readable label for the port status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Connected => 'Connected',
            self::Reserved => 'Reserved',
            self::Disabled => 'Disabled',
        };
    }
}
