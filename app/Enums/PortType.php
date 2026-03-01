<?php

namespace App\Enums;

/**
 * Port type classification.
 *
 * Represents the main category of a port: network (Ethernet/Fiber) or Power.
 */
enum PortType: string
{
    case Ethernet = 'ethernet';
    case Fiber = 'fiber';
    case Power = 'power';

    /**
     * Get the human-readable label for the port type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Ethernet => 'Ethernet',
            self::Fiber => 'Fiber',
            self::Power => 'Power',
        };
    }
}
