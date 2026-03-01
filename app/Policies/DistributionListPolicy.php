<?php

namespace App\Policies;

use App\Models\DistributionList;
use App\Models\User;

/**
 * Policy for authorizing DistributionList actions.
 *
 * - Administrators and IT Managers have full access to all distribution lists
 * - Operators and Auditors can create their own distribution lists
 * - Operators and Auditors can update/delete only their own distribution lists
 * - Viewers can only view the list of distribution lists
 */
class DistributionListPolicy
{
    /**
     * Roles that have full administrative access to all distribution lists.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine whether the user can view any distribution lists.
     * Users with distribution-lists.view permission can view the list.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('distribution-lists.view');
    }

    /**
     * Determine whether the user can view the distribution list.
     * Admins/IT Managers can view all; others can only view their own lists.
     */
    public function view(User $user, DistributionList $distributionList): bool
    {
        // Admins and IT Managers can view all distribution lists
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }

        // Other users can only view their own distribution lists
        return $user->id === $distributionList->user_id;
    }

    /**
     * Determine whether the user can create distribution lists.
     * Users with distribution-lists.create permission can create lists.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('distribution-lists.create');
    }

    /**
     * Determine whether the user can update the distribution list.
     * Admins/IT Managers can update any list; others can only update their own.
     */
    public function update(User $user, DistributionList $distributionList): bool
    {
        // Admins and IT Managers can update any distribution list
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }

        // Other users can only update their own distribution lists
        return $user->id === $distributionList->user_id;
    }

    /**
     * Determine whether the user can delete the distribution list.
     * Admins/IT Managers can delete any list; others can only delete their own.
     */
    public function delete(User $user, DistributionList $distributionList): bool
    {
        // Admins and IT Managers can delete any distribution list
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }

        // Other users can only delete their own distribution lists
        return $user->id === $distributionList->user_id;
    }

    /**
     * Determine whether the user can restore the distribution list.
     * Only Admins and IT Managers can restore deleted lists.
     */
    public function restore(User $user, DistributionList $distributionList): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can permanently delete the distribution list.
     * Only Administrators can permanently delete distribution lists.
     */
    public function forceDelete(User $user, DistributionList $distributionList): bool
    {
        return $user->hasRole('Administrator');
    }
}
