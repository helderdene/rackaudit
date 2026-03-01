<?php

namespace App\Policies;

use App\Models\ReportSchedule;
use App\Models\User;

/**
 * Policy for authorizing ReportSchedule actions.
 *
 * - Administrators and IT Managers have full access to all report schedules
 * - Operators and Auditors can view/create schedules for their accessible datacenters
 * - Operators and Auditors can update/delete only their own schedules
 * - Viewers can only view the list of schedules
 */
class ReportSchedulePolicy
{
    /**
     * Roles that have full administrative access to all report schedules.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine whether the user can view any report schedules.
     * Users with scheduled-reports.view permission can view the list.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('scheduled-reports.view');
    }

    /**
     * Determine whether the user can view the report schedule.
     * Admins/IT Managers can view all; others can only view their own schedules.
     */
    public function view(User $user, ReportSchedule $reportSchedule): bool
    {
        // Admins and IT Managers can view all report schedules
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }

        // Other users can only view their own schedules
        return $user->id === $reportSchedule->user_id;
    }

    /**
     * Determine whether the user can create report schedules.
     * Users with scheduled-reports.create permission can create schedules.
     * Datacenter access is enforced at the controller level during creation.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('scheduled-reports.create');
    }

    /**
     * Determine whether the user can update the report schedule.
     * Admins/IT Managers can update any schedule; others can only update their own.
     */
    public function update(User $user, ReportSchedule $reportSchedule): bool
    {
        // Admins and IT Managers can update any report schedule
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }

        // Other users can only update their own schedules
        return $user->id === $reportSchedule->user_id;
    }

    /**
     * Determine whether the user can delete the report schedule.
     * Admins/IT Managers can delete any schedule; others can only delete their own.
     */
    public function delete(User $user, ReportSchedule $reportSchedule): bool
    {
        // Admins and IT Managers can delete any report schedule
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }

        // Other users can only delete their own schedules
        return $user->id === $reportSchedule->user_id;
    }

    /**
     * Determine whether the user can restore the report schedule.
     * Only Admins and IT Managers can restore deleted schedules.
     */
    public function restore(User $user, ReportSchedule $reportSchedule): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Determine whether the user can permanently delete the report schedule.
     * Only Administrators can permanently delete schedules.
     */
    public function forceDelete(User $user, ReportSchedule $reportSchedule): bool
    {
        return $user->hasRole('Administrator');
    }
}
