<?php

namespace App\Enums;

/**
 * Port direction.
 *
 * Represents the direction of data/power flow for connection validation.
 * Network ports use Uplink/Downlink/Bidirectional.
 * Power ports use Input/Output.
 */
enum PortDirection: string
{
    // Network directions
    case Uplink = 'uplink';
    case Downlink = 'downlink';
    case Bidirectional = 'bidirectional';

    // Power directions
    case Input = 'input';
    case Output = 'output';

    /**
     * Get the human-readable label for the port direction.
     */
    public function label(): string
    {
        return match ($this) {
            self::Uplink => 'Uplink',
            self::Downlink => 'Downlink',
            self::Bidirectional => 'Bidirectional',
            self::Input => 'Input',
            self::Output => 'Output',
        };
    }

    /**
     * Get valid directions for a given port type.
     *
     * @return array<self>
     */
    public static function forType(PortType $type): array
    {
        return match ($type) {
            PortType::Ethernet, PortType::Fiber => [
                self::Uplink,
                self::Downlink,
                self::Bidirectional,
            ],
            PortType::Power => [
                self::Input,
                self::Output,
            ],
        };
    }

    /**
     * Get the default direction for a given port type.
     */
    public static function defaultForType(PortType $type): self
    {
        return match ($type) {
            PortType::Ethernet, PortType::Fiber => self::Bidirectional,
            PortType::Power => self::Input,
        };
    }
}
