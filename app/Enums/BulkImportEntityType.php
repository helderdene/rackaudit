<?php

namespace App\Enums;

/**
 * Bulk import entity type.
 *
 * Indicates the type of entities being imported in a bulk import job.
 * Mixed type is used when importing multiple entity types in a single file.
 * ConnectionHistory type is used for exporting connection history logs.
 */
enum BulkImportEntityType: string
{
    case Datacenter = 'datacenter';
    case Room = 'room';
    case Row = 'row';
    case Rack = 'rack';
    case Device = 'device';
    case Port = 'port';
    case Mixed = 'mixed';
    case ConnectionHistory = 'connection_history';

    /**
     * Get the human-readable label for the entity type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Datacenter => 'Datacenter',
            self::Room => 'Room',
            self::Row => 'Row',
            self::Rack => 'Rack',
            self::Device => 'Device',
            self::Port => 'Port',
            self::Mixed => 'Mixed',
            self::ConnectionHistory => 'Connection History',
        };
    }
}
