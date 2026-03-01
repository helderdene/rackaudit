<?php

namespace App\Policies;

use App\Models\Connection;
use App\Models\User;

/**
 * Policy for authorizing Connection actions.
 *
 * - Administrators and IT Managers have full CRUD access to all connections
 * - Operators, Auditors, and Viewers have read-only access to connections
 */
class ConnectionPolicy
{
    /**
     * Roles that have full access to all connections.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine whether the user can view any connections.
     * All authenticated users can view the connection list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the connection.
     * All authenticated users can view connections.
     */
    public function view(User $user, Connection $connection): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view connection history.
     * All authenticated users can view connection history.
     * Role-based filtering is handled in the controller.
     */
    public function viewHistory(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create connections.
     * Only Administrators and IT Managers can create connections.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can update the connection.
     * Only Administrators and IT Managers can update connections.
     */
    public function update(User $user, Connection $connection): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can delete the connection.
     * Only Administrators and IT Managers can delete connections.
     */
    public function delete(User $user, Connection $connection): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can restore the connection.
     * Only Administrators and IT Managers can restore connections.
     */
    public function restore(User $user, Connection $connection): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can permanently delete the connection.
     * Only Administrators can permanently delete connections.
     */
    public function forceDelete(User $user, Connection $connection): bool
    {
        return $user->hasRole('Administrator');
    }
}
