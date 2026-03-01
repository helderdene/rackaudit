<?php

namespace App\Policies;

use App\Models\Pdu;
use App\Models\User;

/**
 * Policy for authorizing PDU actions.
 *
 * - Administrators and IT Managers have full CRUD access to all PDUs
 * - Operators, Auditors, and Viewers have read-only access to PDUs within assigned datacenters
 * - PDU access inherits from parent Room's (or Row's Room's) Datacenter relationship
 */
class PduPolicy
{
    /**
     * Roles that have full access to all PDUs.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine whether the user can view any PDUs.
     * All authenticated users can view the PDU list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the PDU.
     * Admins/IT Managers can view all; others check parent Room's Datacenter access.
     */
    public function view(User $user, Pdu $pdu): bool
    {
        // Admins and IT Managers can view all PDUs
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }

        // Determine the datacenter ID based on assignment level
        $datacenterId = $this->getDatacenterId($pdu);
        if (! $datacenterId) {
            return false;
        }

        return $user->datacenters()->where('datacenters.id', $datacenterId)->exists();
    }

    /**
     * Determine whether the user can create PDUs.
     * Only Administrators and IT Managers can create PDUs.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can update the PDU.
     * Only Administrators and IT Managers can update PDUs.
     */
    public function update(User $user, Pdu $pdu): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can delete the PDU.
     * Only Administrators and IT Managers can delete PDUs.
     */
    public function delete(User $user, Pdu $pdu): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Get the datacenter ID for a PDU based on its assignment level.
     *
     * For room-level PDUs, use the room's datacenter.
     * For row-level PDUs, use the row's room's datacenter.
     */
    private function getDatacenterId(Pdu $pdu): ?int
    {
        // Room-level PDU
        if ($pdu->room_id !== null) {
            $room = $pdu->room;

            return $room?->datacenter_id;
        }

        // Row-level PDU
        if ($pdu->row_id !== null) {
            $row = $pdu->row;
            $room = $row?->room;

            return $room?->datacenter_id;
        }

        return null;
    }
}
