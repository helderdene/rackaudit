<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

/**
 * Policy for authorizing Device actions.
 *
 * - Administrators and IT Managers have full CRUD access to all devices
 * - Operators, Auditors, and Viewers have read-only access to devices placed in racks
 *   within their assigned datacenters
 * - Unplaced devices (no rack_id) are only viewable by Administrators and IT Managers
 * - Device access inherits from parent Rack's Row's Room's Datacenter relationship
 */
class DevicePolicy
{
    /**
     * Roles that have full access to all devices.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine whether the user can view any devices.
     * All authenticated users can view the device list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the device.
     * Admins/IT Managers can view all devices (including unplaced).
     * Others can only view devices in racks within their assigned datacenters.
     */
    public function view(User $user, Device $device): bool
    {
        // Admins and IT Managers can view all devices
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }

        // Unplaced devices (no rack) are only viewable by admins
        $rack = $device->rack;
        if (! $rack) {
            return false;
        }

        // Check datacenter hierarchy: device -> rack -> row -> room -> datacenter
        $row = $rack->row;
        if (! $row) {
            return false;
        }

        $room = $row->room;
        if (! $room) {
            return false;
        }

        return $user->datacenters()->where('datacenters.id', $room->datacenter_id)->exists();
    }

    /**
     * Determine whether the user can create devices.
     * Only Administrators and IT Managers can create devices.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can update the device.
     * Only Administrators and IT Managers can update devices.
     */
    public function update(User $user, Device $device): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can delete the device.
     * Only Administrators and IT Managers can delete devices.
     */
    public function delete(User $user, Device $device): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can restore the device.
     * Only Administrators and IT Managers can restore devices.
     */
    public function restore(User $user, Device $device): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can permanently delete the device.
     * Only Administrators can permanently delete devices.
     */
    public function forceDelete(User $user, Device $device): bool
    {
        return $user->hasRole('Administrator');
    }
}
