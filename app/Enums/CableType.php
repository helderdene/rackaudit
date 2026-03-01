<?php

namespace App\Enums;

/**
 * Cable type classification.
 *
 * Represents the type of cable used for physical connections between ports.
 * Grouped by port type: Ethernet cables, Fiber cables, and Power cables.
 */
enum CableType: string
{
    // Ethernet cables
    case Cat5e = 'cat5e';
    case Cat6 = 'cat6';
    case Cat6a = 'cat6a';

    // Fiber cables
    case FiberSm = 'fiber_sm';
    case FiberMm = 'fiber_mm';

    // Power cables
    case PowerC13 = 'power_c13';
    case PowerC14 = 'power_c14';
    case PowerC19 = 'power_c19';
    case PowerC20 = 'power_c20';

    /**
     * Get the human-readable label for the cable type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cat5e => 'Cat5e',
            self::Cat6 => 'Cat6',
            self::Cat6a => 'Cat6a',
            self::FiberSm => 'Fiber SM',
            self::FiberMm => 'Fiber MM',
            self::PowerC13 => 'C13',
            self::PowerC14 => 'C14',
            self::PowerC19 => 'C19',
            self::PowerC20 => 'C20',
        };
    }

    /**
     * Get valid cable types for a given port type.
     *
     * @return array<self>
     */
    public static function forPortType(PortType $type): array
    {
        return match ($type) {
            PortType::Ethernet => [
                self::Cat5e,
                self::Cat6,
                self::Cat6a,
            ],
            PortType::Fiber => [
                self::FiberSm,
                self::FiberMm,
            ],
            PortType::Power => [
                self::PowerC13,
                self::PowerC14,
                self::PowerC19,
                self::PowerC20,
            ],
        };
    }
}
