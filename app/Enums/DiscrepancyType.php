<?php

namespace App\Enums;

/**
 * Discrepancy type for connection comparison results.
 *
 * Represents the status of a connection when comparing expected
 * connections from implementation files against actual documented
 * connections in the system.
 */
enum DiscrepancyType: string
{
    case Matched = 'matched';
    case Missing = 'missing';
    case Unexpected = 'unexpected';
    case Mismatched = 'mismatched';
    case Conflicting = 'conflicting';
    case ConfigurationMismatch = 'configuration_mismatch';

    /**
     * Get the human-readable label for the discrepancy type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Matched => 'Matched',
            self::Missing => 'Missing',
            self::Unexpected => 'Unexpected',
            self::Mismatched => 'Mismatched',
            self::Conflicting => 'Conflicting',
            self::ConfigurationMismatch => 'Configuration Mismatch',
        };
    }
}
