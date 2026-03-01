<?php

namespace App\Policies;

use App\Models\Rack;
use App\Models\User;

/**
 * Policy for authorizing Rack actions.
 *
 * - Administrators and IT Managers have full CRUD access to all racks
 * - Operators, Auditors, and Viewers have read-only access to racks within assigned datacenters
 * - Rack access inherits from parent Row's Room's Datacenter relationship
 */
class RackPolicy
{
    /**
     * Roles that have full access to all racks.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine whether the user can view any racks.
     * All authenticated users can view the rack list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the rack.
     * Admins/IT Managers can view all; others check parent Row's Room's Datacenter access.
     */
    public function view(User $user, Rack $rack): bool
    {
        // Admins and IT Managers can view all racks
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }

        // Other users can only view racks in rows within rooms within their assigned datacenters
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
     * Determine whether the user can create racks.
     * Only Administrators and IT Managers can create racks.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can update the rack.
     * Only Administrators and IT Managers can update racks.
     */
    public function update(User $user, Rack $rack): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can delete the rack.
     * Only Administrators and IT Managers can delete racks.
     */
    public function delete(User $user, Rack $rack): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can view the rack elevation diagram.
     * Same logic as view() - Admins/IT Managers always; others check datacenter access.
     */
    public function viewElevation(User $user, Rack $rack): bool
    {
        return $this->view($user, $rack);
    }
}
