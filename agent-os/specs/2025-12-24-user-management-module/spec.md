# Specification: User Management Module

## Goal

Build a comprehensive CRUD interface for Administrators to manage users, assign roles, control datacenter access, and track user activity within a unified user management module.

## User Stories

- As an Administrator, I want to create new user accounts with credentials so that I can onboard team members to the system
- As an Administrator, I want to view, filter, and search all users with their roles and status so that I can efficiently manage the user base
- As an Administrator, I want to assign datacenter access and roles to users so that I can control what resources each user can access

## Specific Requirements

**User List Page with Search and Filtering**
- Create paginated user index at `/users` with columns: name, email, role, status, last active, actions
- Implement server-side search by name and email using query parameters
- Add filter dropdowns for role (Administrator, IT Manager, Operator, Auditor, Viewer) and status (active, inactive, suspended)
- Display relative timestamps for last active (e.g., "2 hours ago", "Never")
- Include action buttons for edit and delete operations per row
- Use existing table pattern from `Roles.vue` with consistent styling

**User Creation Form**
- Create user form accessible from the user list page header
- Fields: name (required), email (required, unique), password (required, min 8 chars), role (select), status (select, default: active)
- Add datacenter access multi-select for assigning accessible datacenters
- Use Inertia Form component pattern from `Profile.vue` with Wayfinder integration
- Validate uniqueness of email on both client and server side

**User Edit Form**
- Reuse creation form component for editing with pre-populated values
- Password field optional on edit (only update if provided)
- Prevent Administrators from demoting themselves or changing their own status to inactive/suspended
- Use optimistic UI updates with proper error handling

**User Status Management**
- Add `status` enum field to users table with values: active, inactive, suspended
- Modify authentication middleware to prevent login for inactive/suspended users
- Display status with color-coded badges (green: active, yellow: inactive, red: suspended)
- Status changes take effect immediately without requiring password reset

**Datacenter Access Assignment**
- Create `datacenter_user` pivot table for many-to-many relationship
- Build multi-select component for datacenter assignment in user forms
- Store datacenter IDs user has explicit access to (beyond role permissions)
- Note: Datacenter model may not exist yet - create placeholder relationship

**Last Active Tracking**
- Add `last_active_at` timestamp field to users table
- Update timestamp on successful login via Fortify authentication hook
- Display in user list with human-readable relative format
- Use this to identify dormant accounts for security reviews

**Bulk Actions Support**
- Add checkbox selection column to user list table
- Implement bulk status change action (activate, deactivate, suspend)
- Show confirmation dialog before executing bulk actions
- Prevent bulk actions that would affect the current Administrator

**Delete User with Confirmation**
- Create modal dialog confirming user deletion with username display
- Prevent deletion of the currently authenticated Administrator
- Use soft deletes to preserve audit trail
- Follow pattern from `DeleteUser.vue` component

**Role Assignment Integration**
- Incorporate existing role assignment into the edit form as a select field
- Deprecate standalone `/users/roles` page (redirect to new user management)
- Reuse `syncRoles()` pattern from `RoleAssignmentController`
- Available roles: Administrator, IT Manager, Operator, Auditor, Viewer

**Audit Logging Hooks**
- Prepare event dispatching for: UserCreated, UserUpdated, UserDeleted, UserStatusChanged, UserRoleChanged
- Implementation of actual logging deferred to Activity Logging Infrastructure (Phase 1)
- Events should include: actor (who made change), target user, old/new values, timestamp

## Existing Code to Leverage

**RoleAssignmentController.php**
- Reuse `syncRoles()` method pattern for role assignment
- Follow JSON response structure for API consistency
- Adapt self-role-removal prevention logic for the new controller
- Reference for middleware usage: `role:Administrator`

**Users/Roles.vue**
- Reuse table structure with `border`, `bg-muted/50`, and `hover:bg-muted/50` patterns
- Follow select dropdown styling for role and status selects
- Adapt the reactive `selectedRoles` pattern for form state management
- Use same breadcrumb structure with Users parent

**settings/Profile.vue**
- Reuse Form component pattern with Wayfinder controller imports
- Follow `grid gap-2` spacing for form field groups
- Use same Input, Label, InputError component composition
- Apply `recentlySuccessful` feedback pattern for save confirmations

**DeleteUser.vue**
- Reuse Dialog component structure for delete confirmations
- Follow destructive action styling with red border/background
- Apply same DialogHeader, DialogContent, DialogFooter composition
- Adapt password confirmation pattern for simple confirmation

**UI Component Library**
- Button, Input, Label, InputError for form elements
- Dialog components for modals (delete, bulk actions)
- Skeleton component for loading states
- Badge component for status display (may need to create)

## Out of Scope

- Password reset functionality for other users (users manage their own via settings)
- Email verification management for other users
- Two-factor authentication management for other users
- Integration with external identity providers (LDAP/SSO)
- Email notifications for new user credentials (out-of-band communication)
- Self-registration (disabled in Fortify configuration)
- Activity logging implementation (only prepare hooks; actual logging built in Phase 1)
- Full Datacenter model implementation (create minimal relationship only)
- User profile photo/avatar management
- Session management for other users (force logout, view active sessions)
