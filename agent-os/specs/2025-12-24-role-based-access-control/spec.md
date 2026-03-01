# Specification: Role-Based Access Control

## Goal
Implement a comprehensive role-based access control system using Spatie Laravel-Permission with five predefined roles (Administrator, IT Manager, Operator, Auditor, Viewer) to control access to infrastructure management, audits, and system features.

## User Stories
- As an Administrator, I want to manage all system features including user roles so that I can control access across the organization
- As an IT Manager, I want to manage infrastructure (datacenters, racks, devices) and approve implementation files so that I can oversee technical operations
- As a Viewer, I want read-only access to infrastructure and reports so that I can monitor system status without making changes

## Specific Requirements

**Install and Configure Spatie Laravel-Permission**
- Install package via `composer require spatie/laravel-permission`
- Publish and run migrations for roles and permissions tables
- Add `HasRoles` trait to the User model
- Configure the permission cache in `config/permission.php`

**Define Five System-Wide Roles**
- Administrator: Full system access including user management, system configuration, and all features
- IT Manager: Manage infrastructure, approve implementation files, view reports, no user/system administration
- Operator: Document changes (devices, connections, ports), execute audits, no implementation approval or reports
- Auditor: Create/execute audits, manage findings, generate audit reports, read-only infrastructure access
- Viewer: Read-only access to infrastructure and reports only

**Implement Granular Resource-Based Permissions**
- Use naming convention: `{resource}.{action}` (e.g., `datacenters.view`, `datacenters.create`)
- Resources: datacenters, racks, devices, connections, ports, audits, findings, implementation-files, reports, users, settings
- Actions: view, create, update, delete, and resource-specific actions (e.g., `audits.execute`, `findings.resolve`, `implementation-files.approve`)
- Permissions must be checked both server-side (middleware/policies) and client-side (Vue conditional rendering)

**Create Permission Middleware and Gates**
- Register `role` and `permission` middleware aliases in `bootstrap/app.php`
- Create Laravel Gates for common permission checks
- Apply middleware to route groups based on required permissions
- Return 403 Forbidden with redirect and flash message for unauthorized access

**Build Role Assignment for Administrators**
- Only users with Administrator role can assign/modify roles
- Create controller and Inertia page for user role management
- Form Request validation to ensure valid role assignment
- Prevent Administrators from removing their own Administrator role

**Extend User Model and Authentication**
- Add `HasRoles` trait from Spatie package to User model
- Eager load roles and permissions when fetching authenticated user
- Include role name in user data shared via HandleInertiaRequests middleware
- Update TypeScript User type to include role information

**Create Database Seeders**
- Create `RolesAndPermissionsSeeder` with all roles and their permission sets
- Create initial Administrator user during seeding (configurable email/password via .env)
- Ensure seeder is idempotent (safe to run multiple times)
- Call seeder from DatabaseSeeder

**Implement UI Role Indicator**
- Display current user's role badge in UserInfo component below user name
- Use distinct colors/styling for each role (e.g., Administrator: red, IT Manager: blue)
- Show role in both sidebar NavUser and header user dropdown areas

**Conditional UI Rendering Based on Permissions**
- Create Vue composable `usePermissions()` to check user permissions client-side
- Conditionally render navigation menu items based on user permissions
- Hide action buttons (create, edit, delete) when user lacks permission
- Apply to mainNavItems array in AppHeader.vue based on role capabilities

**Handle Unauthorized Access Gracefully**
- Redirect unauthorized users to dashboard with flash error message
- Display user-friendly error message explaining access denial
- Log unauthorized access attempts for security monitoring
- Use AlertError component to display flash messages on redirected page

## Visual Design
No visual assets provided.

## Existing Code to Leverage

**User Model (`/Users/helderdene/rackaudit/app/Models/User.php`)**
- Extends Authenticatable and uses HasFactory, Notifiable, TwoFactorAuthenticatable traits
- Add `HasRoles` trait here alongside existing traits
- Existing fillable array and casts method should remain unchanged

**HandleInertiaRequests Middleware (`/Users/helderdene/rackaudit/app/Http/Middleware/HandleInertiaRequests.php`)**
- Already shares `auth.user` data with frontend
- Extend to include `auth.user.role` and `auth.permissions` for client-side checks
- Use this pattern for sharing permission data globally to Vue components

**UserInfo Component (`/Users/helderdene/rackaudit/resources/js/Components/UserInfo.vue`)**
- Displays user avatar and name in sidebar/header
- Add role badge display beneath the user name
- Follow existing Avatar/AvatarFallback pattern for styling

**TypeScript Types (`/Users/helderdene/rackaudit/resources/js/types/index.d.ts`)**
- User interface defines current user structure
- Extend to add `role?: string` and `permissions?: string[]` properties
- Update Auth interface if needed

**bootstrap/app.php (`/Users/helderdene/rackaudit/bootstrap/app.php`)**
- Contains middleware registration using Laravel 12 structure
- Add role/permission middleware aliases here using `$middleware->alias()`
- Follow existing pattern for middleware configuration

## Out of Scope
- Custom role creation by users (only predefined roles)
- Permission delegation (users cannot grant permissions to others)
- Audit logging of permission changes
- Datacenter-scoped role assignments (roles are system-wide only)
- Role hierarchy or inheritance (each role has explicitly defined permissions)
- API token-based permission scopes
- Temporary or time-limited role assignments
- Multi-tenancy or organization-based role separation
- Self-service role requests or approval workflows
- Permission caching invalidation UI (handled automatically by Spatie package)
