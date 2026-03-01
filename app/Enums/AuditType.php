<?php

namespace App\Enums;

/**
 * Audit type classification.
 *
 * Defines the type of audit being performed:
 * - Connection: Verifies physical connections match approved implementation files
 * - Inventory: Verifies documented devices exist physically and are in correct positions
 */
enum AuditType: string
{
    case Connection = 'connection';
    case Inventory = 'inventory';

    /**
     * Get the human-readable label for the audit type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Connection => 'Connection',
            self::Inventory => 'Inventory',
        };
    }
}
