<?php

namespace App\Policies;

use App\Models\DeviceType;
use App\Models\User;

/**
 * Policy for authorizing DeviceType actions.
 *
 * - Administrators and IT Managers have full CRUD access to device types
 * - All authenticated users can view device types
 */
class DeviceTypePolicy
{
    /**
     * Roles that have full access to all device types.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine whether the user can view any device types.
     * All authenticated users can view the device type list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the device type.
     * All authenticated users can view device types.
     */
    public function view(User $user, DeviceType $deviceType): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create device types.
     * Only Administrators and IT Managers can create device types.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can update the device type.
     * Only Administrators and IT Managers can update device types.
     */
    public function update(User $user, DeviceType $deviceType): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can delete the device type.
     * Only Administrators and IT Managers can delete device types.
     */
    public function delete(User $user, DeviceType $deviceType): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can restore the device type.
     * Only Administrators and IT Managers can restore device types.
     */
    public function restore(User $user, DeviceType $deviceType): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can permanently delete the device type.
     * Only Administrators can permanently delete device types.
     */
    public function forceDelete(User $user, DeviceType $deviceType): bool
    {
        return $user->hasRole('Administrator');
    }
}
