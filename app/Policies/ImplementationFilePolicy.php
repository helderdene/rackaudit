<?php

namespace App\Policies;

use App\Models\Datacenter;
use App\Models\ImplementationFile;
use App\Models\User;

/**
 * Policy for authorizing ImplementationFile actions.
 *
 * - Administrators and IT Managers have full CRUD access (upload, delete, view, download, restore)
 * - Operators and Auditors have read-only access (view, download, and view versions only)
 * - All users must have access to the parent datacenter to access its files
 * - Approval requires Admin/IT Manager role, datacenter access, and separation of duties
 */
class ImplementationFilePolicy
{
    /**
     * Roles that have full access to implementation files.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine whether the user can view any implementation files.
     * User must have access to the parent datacenter.
     */
    public function viewAny(User $user, Datacenter $datacenter): bool
    {
        return $this->hasDatacenterAccess($user, $datacenter);
    }

    /**
     * Determine whether the user can view the implementation file.
     * User must have access to the parent datacenter.
     */
    public function view(User $user, ImplementationFile $implementationFile): bool
    {
        return $this->hasDatacenterAccess($user, $implementationFile->datacenter);
    }

    /**
     * Determine whether the user can view versions of the implementation file.
     * User must have access to the parent datacenter.
     * Mirrors the view() permission logic.
     */
    public function viewVersions(User $user, ImplementationFile $implementationFile): bool
    {
        return $this->hasDatacenterAccess($user, $implementationFile->datacenter);
    }

    /**
     * Determine whether the user can create implementation files.
     * Only Administrators and IT Managers with datacenter access can create.
     */
    public function create(User $user, Datacenter $datacenter): bool
    {
        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            return false;
        }

        return $this->hasDatacenterAccess($user, $datacenter);
    }

    /**
     * Determine whether the user can update the implementation file.
     * Only Administrators and IT Managers with datacenter access can update.
     */
    public function update(User $user, ImplementationFile $implementationFile): bool
    {
        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            return false;
        }

        return $this->hasDatacenterAccess($user, $implementationFile->datacenter);
    }

    /**
     * Determine whether the user can delete the implementation file.
     * Only Administrators and IT Managers with datacenter access can delete.
     */
    public function delete(User $user, ImplementationFile $implementationFile): bool
    {
        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            return false;
        }

        return $this->hasDatacenterAccess($user, $implementationFile->datacenter);
    }

    /**
     * Determine whether the user can download the implementation file.
     * User must have access to the parent datacenter.
     */
    public function download(User $user, ImplementationFile $implementationFile): bool
    {
        return $this->hasDatacenterAccess($user, $implementationFile->datacenter);
    }

    /**
     * Determine whether the user can restore a previous version of the implementation file.
     * Only Administrators and IT Managers with datacenter access can restore.
     * Mirrors the create() permission logic.
     */
    public function restore(User $user, ImplementationFile $implementationFile): bool
    {
        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            return false;
        }

        return $this->hasDatacenterAccess($user, $implementationFile->datacenter);
    }

    /**
     * Determine whether the user can approve the implementation file.
     * Requires:
     * - Administrator or IT Manager role
     * - Datacenter access
     * - Separation of duties: user cannot approve files they uploaded
     */
    public function approve(User $user, ImplementationFile $implementationFile): bool
    {
        // Must have Administrator or IT Manager role
        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            return false;
        }

        // Must have datacenter access
        if (! $this->hasDatacenterAccess($user, $implementationFile->datacenter)) {
            return false;
        }

        // Separation of duties: cannot approve files you uploaded
        if ($implementationFile->uploaded_by === $user->id) {
            return false;
        }

        return true;
    }

    /**
     * Check if the user has access to the given datacenter.
     * Admins/IT Managers have access to all datacenters; others must be assigned.
     */
    private function hasDatacenterAccess(User $user, Datacenter $datacenter): bool
    {
        // Admins and IT Managers have access to all datacenters
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }

        // Other users must be assigned to the datacenter
        return $user->datacenters()->where('datacenters.id', $datacenter->id)->exists();
    }
}
