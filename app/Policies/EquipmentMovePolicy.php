<?php

namespace App\Policies;

use App\Models\EquipmentMove;
use App\Models\User;

/**
 * Policy for authorizing EquipmentMove actions.
 *
 * - All authenticated users can create (initiate) move requests
 * - Administrators and IT Managers can approve/reject moves
 * - Requesters can cancel their own pending moves
 * - Administrators and IT Managers can cancel any pending move
 * - Participants (requester, approver) and managers can view moves
 * - Participants and managers can download work order PDFs
 */
class EquipmentMovePolicy
{
    /**
     * Roles that have manager/approver access.
     *
     * @var array<string>
     */
    private const MANAGER_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine whether the user can view any moves.
     * All authenticated users can view the move list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the move.
     * Participants (requester, approver) and managers can view.
     */
    public function view(User $user, EquipmentMove $equipmentMove): bool
    {
        // Managers can view any move
        if ($user->hasAnyRole(self::MANAGER_ROLES)) {
            return true;
        }

        // Requester can view their own move
        if ($equipmentMove->requested_by === $user->id) {
            return true;
        }

        // Approver can view moves they approved/rejected
        if ($equipmentMove->approved_by === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create move requests.
     * All authenticated users can initiate moves.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can approve the move.
     * Only users with manager/approver role can approve.
     */
    public function approve(User $user, EquipmentMove $equipmentMove): bool
    {
        // Must have manager role
        if (! $user->hasAnyRole(self::MANAGER_ROLES)) {
            return false;
        }

        // Move must be pending approval
        if (! $equipmentMove->isPendingApproval()) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can reject the move.
     * Only users with manager/approver role can reject.
     */
    public function reject(User $user, EquipmentMove $equipmentMove): bool
    {
        // Same rules as approve
        return $this->approve($user, $equipmentMove);
    }

    /**
     * Determine whether the user can cancel the move.
     * Requester can cancel their own pending moves.
     * Managers can cancel any pending move.
     */
    public function cancel(User $user, EquipmentMove $equipmentMove): bool
    {
        // Move must be pending approval to be cancelled
        if (! $equipmentMove->isPendingApproval()) {
            return false;
        }

        // Managers can cancel any pending move
        if ($user->hasAnyRole(self::MANAGER_ROLES)) {
            return true;
        }

        // Requester can cancel their own move
        if ($equipmentMove->requested_by === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can download the work order PDF.
     * Participants (requester, approver) and managers can download.
     */
    public function downloadWorkOrder(User $user, EquipmentMove $equipmentMove): bool
    {
        // Same rules as view - participants and managers
        return $this->view($user, $equipmentMove);
    }

    /**
     * Determine whether the user can update the move.
     * Not supported - moves follow a workflow and cannot be directly updated.
     */
    public function update(User $user, EquipmentMove $equipmentMove): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the move.
     * Not supported - moves should be cancelled, not deleted.
     */
    public function delete(User $user, EquipmentMove $equipmentMove): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the move.
     * Not supported.
     */
    public function restore(User $user, EquipmentMove $equipmentMove): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the move.
     * Not supported.
     */
    public function forceDelete(User $user, EquipmentMove $equipmentMove): bool
    {
        return false;
    }
}
