<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

/**
 * Policy for authorizing Room actions.
 *
 * - Administrators and IT Managers have full CRUD access to all rooms
 * - Operators, Auditors, and Viewers have read-only access to rooms within assigned datacenters
 * - Room access inherits from parent Datacenter's user relationship
 */
class RoomPolicy
{
    /**
     * Roles that have full access to all rooms.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine whether the user can view any rooms.
     * All authenticated users can view the room list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the room.
     * Admins/IT Managers can view all; others can only view rooms within assigned datacenters.
     */
    public function view(User $user, Room $room): bool
    {
        // Admins and IT Managers can view all rooms
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }

        // Other users can only view rooms in datacenters they are assigned to
        return $user->datacenters()->where('datacenters.id', $room->datacenter_id)->exists();
    }

    /**
     * Determine whether the user can create rooms.
     * Only Administrators and IT Managers can create rooms.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can update the room.
     * Only Administrators and IT Managers can update rooms.
     */
    public function update(User $user, Room $room): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can delete the room.
     * Only Administrators and IT Managers can delete rooms.
     */
    public function delete(User $user, Room $room): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }
}
