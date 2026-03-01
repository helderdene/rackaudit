<?php

namespace App\Enums;

/**
 * Report type classification for custom report builder.
 *
 * Defines the available report types that users can customize:
 * - Capacity: Rack space and power utilization reports
 * - Assets: Device inventory and lifecycle reports
 * - Connections: Physical connection and cable reports
 * - AuditHistory: Historical audit and findings reports
 */
enum ReportType: string
{
    case Capacity = 'capacity';
    case Assets = 'assets';
    case Connections = 'connections';
    case AuditHistory = 'audit_history';

    /**
     * Get the human-readable label for the report type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Capacity => 'Capacity Report',
            self::Assets => 'Asset Report',
            self::Connections => 'Connection Report',
            self::AuditHistory => 'Audit History Report',
        };
    }

    /**
     * Get a brief description for the report type.
     */
    public function description(): string
    {
        return match ($this) {
            self::Capacity => 'Analyze rack space utilization, power capacity, and port availability',
            self::Assets => 'View device inventory, warranty status, and lifecycle information',
            self::Connections => 'Examine physical connections, cable types, and port usage',
            self::AuditHistory => 'Review completed audits, findings, and resolution metrics',
        };
    }
}
