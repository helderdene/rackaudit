<?php

namespace App\Policies;

use App\Models\Datacenter;
use App\Models\User;

/**
 * Policy for authorizing Datacenter actions.
 *
 * - Administrators and IT Managers have full CRUD access to all datacenters
 * - Operators, Auditors, and Viewers have read-only access to assigned datacenters only
 */
class DatacenterPolicy
{
    /**
     * Roles that have full access to all datacenters.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine whether the user can view any datacenters.
     * All authenticated users can view the datacenter list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the datacenter.
     * Admins/IT Managers can view all; others can only view assigned datacenters.
     */
    public function view(User $user, Datacenter $datacenter): bool
    {
        // Admins and IT Managers can view all datacenters
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }

        // Other users can only view datacenters they are assigned to
        return $user->datacenters()->where('datacenters.id', $datacenter->id)->exists();
    }

    /**
     * Determine whether the user can create datacenters.
     * Only Administrators and IT Managers can create datacenters.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can update the datacenter.
     * Only Administrators and IT Managers can update datacenters.
     */
    public function update(User $user, Datacenter $datacenter): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can delete the datacenter.
     * Only Administrators and IT Managers can delete datacenters.
     */
    public function delete(User $user, Datacenter $datacenter): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }
}
