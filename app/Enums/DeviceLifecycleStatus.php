<?php

namespace App\Enums;

/**
 * Device lifecycle status.
 *
 * Represents the current stage in a device's lifecycle, from initial
 * ordering through deployment and eventual disposal.
 */
enum DeviceLifecycleStatus: string
{
    case Ordered = 'ordered';
    case Received = 'received';
    case InStock = 'in_stock';
    case Deployed = 'deployed';
    case Maintenance = 'maintenance';
    case Decommissioned = 'decommissioned';
    case Disposed = 'disposed';

    /**
     * Get the human-readable label for the lifecycle status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Ordered => 'Ordered',
            self::Received => 'Received',
            self::InStock => 'In Stock',
            self::Deployed => 'Deployed',
            self::Maintenance => 'Maintenance',
            self::Decommissioned => 'Decommissioned',
            self::Disposed => 'Disposed',
        };
    }
}
