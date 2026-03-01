<?php

namespace App\Policies;

use App\Models\Row;
use App\Models\User;

/**
 * Policy for authorizing Row actions.
 *
 * - Administrators and IT Managers have full CRUD access to all rows
 * - Operators, Auditors, and Viewers have read-only access to rows within assigned datacenters
 * - Row access inherits from parent Room's Datacenter relationship
 */
class RowPolicy
{
    /**
     * Roles that have full access to all rows.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine whether the user can view any rows.
     * All authenticated users can view the row list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the row.
     * Admins/IT Managers can view all; others check parent Room's Datacenter access.
     */
    public function view(User $user, Row $row): bool
    {
        // Admins and IT Managers can view all rows
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }

        // Other users can only view rows in rooms within their assigned datacenters
        $room = $row->room;
        if (! $room) {
            return false;
        }

        return $user->datacenters()->where('datacenters.id', $room->datacenter_id)->exists();
    }

    /**
     * Determine whether the user can create rows.
     * Only Administrators and IT Managers can create rows.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can update the row.
     * Only Administrators and IT Managers can update rows.
     */
    public function update(User $user, Row $row): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can delete the row.
     * Only Administrators and IT Managers can delete rows.
     */
    public function delete(User $user, Row $row): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }
}
