<?php

namespace App\Enums;

/**
 * Port subtype classification.
 *
 * Represents the specific subtype of a port within its parent type category.
 * Subtypes are grouped by parent type:
 * - Ethernet: 1GbE, 10GbE, 25GbE, 40GbE, 100GbE
 * - Fiber: LC, SC, MPO
 * - Power: C13, C14, C19, C20
 */
enum PortSubtype: string
{
    // Ethernet subtypes
    case Gbe1 = 'gbe1';
    case Gbe10 = 'gbe10';
    case Gbe25 = 'gbe25';
    case Gbe40 = 'gbe40';
    case Gbe100 = 'gbe100';

    // Fiber subtypes
    case Lc = 'lc';
    case Sc = 'sc';
    case Mpo = 'mpo';

    // Power subtypes
    case C13 = 'c13';
    case C14 = 'c14';
    case C19 = 'c19';
    case C20 = 'c20';

    /**
     * Get the human-readable label for the port subtype.
     */
    public function label(): string
    {
        return match ($this) {
            // Ethernet
            self::Gbe1 => '1GbE',
            self::Gbe10 => '10GbE',
            self::Gbe25 => '25GbE',
            self::Gbe40 => '40GbE',
            self::Gbe100 => '100GbE',
            // Fiber
            self::Lc => 'LC',
            self::Sc => 'SC',
            self::Mpo => 'MPO',
            // Power
            self::C13 => 'C13',
            self::C14 => 'C14',
            self::C19 => 'C19',
            self::C20 => 'C20',
        };
    }

    /**
     * Get valid subtypes for a given parent port type.
     *
     * @return array<self>
     */
    public static function forType(PortType $type): array
    {
        return match ($type) {
            PortType::Ethernet => [
                self::Gbe1,
                self::Gbe10,
                self::Gbe25,
                self::Gbe40,
                self::Gbe100,
            ],
            PortType::Fiber => [
                self::Lc,
                self::Sc,
                self::Mpo,
            ],
            PortType::Power => [
                self::C13,
                self::C14,
                self::C19,
                self::C20,
            ],
        };
    }
}
