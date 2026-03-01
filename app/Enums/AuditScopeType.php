<?php

namespace App\Enums;

/**
 * Audit scope type classification.
 *
 * Defines the scope level of an audit:
 * - Datacenter: Audit covers all racks within a datacenter
 * - Room: Audit covers all racks within a specific room
 * - Racks: Audit covers individually selected racks and/or devices
 */
enum AuditScopeType: string
{
    case Datacenter = 'datacenter';
    case Room = 'room';
    case Racks = 'racks';

    /**
     * Get the human-readable label for the scope type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Datacenter => 'Datacenter',
            self::Room => 'Room',
            self::Racks => 'Racks',
        };
    }
}
