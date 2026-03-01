import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Composable for checking user permissions and roles.
 * Accesses permissions from Inertia shared data.
 */
export function usePermissions() {
    const page = usePage();

    const permissions = computed<string[]>(
        () => page.props.auth?.permissions ?? [],
    );

    const userRole = computed<string | undefined>(
        () => page.props.auth?.user?.role,
    );

    /**
     * Check if the user has a specific permission.
     */
    function can(permission: string): boolean {
        return permissions.value.includes(permission);
    }

    /**
     * Check if the user has a specific role.
     */
    function hasRole(role: string): boolean {
        return userRole.value === role;
    }

    /**
     * Check if the user has any of the specified roles.
     */
    function hasAnyRole(roles: string[]): boolean {
        return userRole.value !== undefined && roles.includes(userRole.value);
    }

    /**
     * Check if the user has any of the specified permissions.
     */
    function hasAnyPermission(permissionList: string[]): boolean {
        return permissionList.some((permission) =>
            permissions.value.includes(permission),
        );
    }

    /**
     * Check if the user has all of the specified permissions.
     */
    function hasAllPermissions(permissionList: string[]): boolean {
        return permissionList.every((permission) =>
            permissions.value.includes(permission),
        );
    }

    return {
        permissions,
        userRole,
        can,
        hasRole,
        hasAnyRole,
        hasAnyPermission,
        hasAllPermissions,
    };
}
