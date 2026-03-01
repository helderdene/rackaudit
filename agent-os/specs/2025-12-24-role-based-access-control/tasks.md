# Task Breakdown: Role-Based Access Control

## Overview
Total Tasks: 35 (across 5 task groups)

This implementation covers installing Spatie Laravel-Permission, defining five predefined roles with granular permissions, implementing middleware and policies for authorization, building role assignment UI for Administrators, and adding conditional UI rendering based on permissions.

## Task List

### Package Installation & Configuration

#### Task Group 1: Spatie Laravel-Permission Setup
**Dependencies:** None

- [x] 1.0 Complete package installation and configuration
  - [x] 1.1 Write 4 focused tests for permission system foundation
    - Test that User model uses HasRoles trait
    - Test that roles can be created and assigned to users
    - Test that permissions can be assigned to roles
    - Test that user permission checks work correctly
  - [x] 1.2 Install Spatie Laravel-Permission package
    - Run `composer require spatie/laravel-permission`
    - Publish configuration: `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`
    - Run migrations: `php artisan migrate`
  - [x] 1.3 Add HasRoles trait to User model
    - Import `Spatie\Permission\Traits\HasRoles`
    - Add trait to User model alongside existing traits (HasFactory, Notifiable, TwoFactorAuthenticatable)
    - Verify trait is properly integrated
  - [x] 1.4 Configure permission cache in `config/permission.php`
    - Review default cache settings
    - Configure appropriate cache store if needed
    - Set cache expiration time
  - [x] 1.5 Register middleware aliases in `bootstrap/app.php`
    - Add `role` middleware alias pointing to `\Spatie\Permission\Middleware\RoleMiddleware::class`
    - Add `permission` middleware alias pointing to `\Spatie\Permission\Middleware\PermissionMiddleware::class`
    - Add `role_or_permission` middleware alias pointing to `\Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class`
  - [x] 1.6 Ensure package installation tests pass
    - Run ONLY the 4 tests written in 1.1
    - Verify User model trait integration works
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4 tests written in 1.1 pass
- Spatie Laravel-Permission package is installed and migrations run successfully
- User model has HasRoles trait and can be assigned roles/permissions
- Middleware aliases are registered and available

---

### Database Layer

#### Task Group 2: Roles, Permissions & Seeders
**Dependencies:** Task Group 1

- [x] 2.0 Complete database seeding for roles and permissions
  - [x] 2.1 Write 6 focused tests for roles and permissions seeding
    - Test that all five roles are created by seeder (Administrator, IT Manager, Operator, Auditor, Viewer)
    - Test that Administrator role has all permissions
    - Test that IT Manager has correct permission subset (infrastructure, implementation-files.approve, reports.view)
    - Test that Operator has correct permission subset (devices, connections, ports, audits.execute)
    - Test that Auditor has correct permission subset (audits, findings, reports, read-only infrastructure)
    - Test that Viewer has only view permissions
  - [x] 2.2 Create `RolesAndPermissionsSeeder`
    - Define all resource permissions using `{resource}.{action}` convention
    - Resources: datacenters, racks, devices, connections, ports, audits, findings, implementation-files, reports, users, settings
    - Actions per resource: view, create, update, delete (plus resource-specific actions)
    - Special permissions: `audits.execute`, `findings.resolve`, `implementation-files.approve`
  - [x] 2.3 Define Administrator role permissions
    - Assign all permissions (full system access)
    - Include user management: `users.view`, `users.create`, `users.update`, `users.delete`
    - Include settings management: `settings.view`, `settings.update`
  - [x] 2.4 Define IT Manager role permissions
    - Infrastructure management: datacenters, racks, devices (all actions)
    - Implementation files: view, create, update, delete, approve
    - Reports: view only
    - Exclude: users.*, settings.*
  - [x] 2.5 Define Operator role permissions
    - Devices: view, create, update, delete
    - Connections: view, create, update, delete
    - Ports: view, create, update, delete
    - Audits: view, execute
    - Datacenters, racks: view only
    - Exclude: implementation-files.approve, reports.*, users.*, settings.*
  - [x] 2.6 Define Auditor role permissions
    - Audits: view, create, update, delete, execute
    - Findings: view, create, update, delete, resolve
    - Reports: view, create
    - Infrastructure (datacenters, racks, devices, connections, ports): view only
    - Exclude: users.*, settings.*
  - [x] 2.7 Define Viewer role permissions
    - All resources: view only
    - Exclude: create, update, delete, execute, resolve, approve actions
    - Exclude: users.*, settings.*
  - [x] 2.8 Create initial Administrator user seeding
    - Read email/password from .env variables (ADMIN_EMAIL, ADMIN_PASSWORD)
    - Create user if not exists
    - Assign Administrator role to user
    - Make seeder idempotent (safe to run multiple times)
  - [x] 2.9 Update DatabaseSeeder to call RolesAndPermissionsSeeder
    - Add call to RolesAndPermissionsSeeder
    - Ensure proper ordering (permissions before users)
  - [x] 2.10 Ensure database seeding tests pass
    - Run ONLY the 6 tests written in 2.1
    - Run seeder and verify roles/permissions created
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6 tests written in 2.1 pass
- All five roles created with correct permission sets
- Permission naming follows `{resource}.{action}` convention
- Initial Administrator user created from .env credentials
- Seeder is idempotent and safe to run multiple times

---

### API & Authorization Layer

#### Task Group 3: Middleware, Gates, and Policies
**Dependencies:** Task Group 2

- [x] 3.0 Complete authorization layer
  - [x] 3.1 Write 6 focused tests for authorization
    - Test that Administrator can access all routes
    - Test that unauthorized role returns 403 Forbidden
    - Test that permission middleware blocks access correctly
    - Test that Viewer cannot perform create/update/delete actions
    - Test unauthorized access redirects to dashboard with flash message
    - Test that unauthorized access attempts are logged
  - [x] 3.2 Create Laravel Gates for common permission checks
    - Define gates in AuthServiceProvider or AppServiceProvider
    - Create gates for: manage-users, manage-settings, approve-implementation-files
    - Gates should check underlying Spatie permissions
  - [x] 3.3 Apply permission middleware to route groups
    - Protect user management routes: `permission:users.view|users.create|users.update|users.delete`
    - Protect settings routes: `permission:settings.view|settings.update`
    - Protect resource routes with appropriate permissions
    - Apply to routes in `routes/web.php` and `routes/api.php`
  - [x] 3.4 Create RoleAssignmentController for user role management
    - Only accessible by Administrator role
    - Methods: index (list users with roles), update (assign role to user)
    - Prevent Administrator from removing their own Administrator role
    - Use Form Request for validation
  - [x] 3.5 Create RoleAssignmentRequest Form Request
    - Validate user_id exists
    - Validate role is one of the five predefined roles
    - Add authorization check for Administrator role
  - [x] 3.6 Implement unauthorized access handling
    - Create custom exception handler for authorization failures
    - Redirect to dashboard with flash error message
    - Use AlertError component pattern for displaying message
    - Log unauthorized access attempts with user ID, route, and timestamp
  - [x] 3.7 Define API routes for role assignment
    - GET /users/roles - List all users with their roles
    - PUT /users/{user}/role - Update user's role
    - Apply Administrator-only middleware
  - [x] 3.8 Ensure authorization layer tests pass
    - Run ONLY the 6 tests written in 3.1
    - Verify middleware blocks unauthorized access
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6 tests written in 3.1 pass
- Gates defined and functional for common checks
- Route groups protected with appropriate permission middleware
- Role assignment API works for Administrators only
- Unauthorized access handled gracefully with redirect and flash message

---

### Frontend Layer

#### Task Group 4: UI Components and Permission-Based Rendering
**Dependencies:** Task Group 3

- [x] 4.0 Complete frontend integration
  - [x] 4.1 Write 6 focused tests for frontend permission features
    - Test that user role is shared via Inertia props
    - Test that permissions array is shared via Inertia props
    - Test that UserInfo component displays role badge
    - Test that navigation items are conditionally rendered based on permissions
    - Test that action buttons are hidden when user lacks permission
    - Test that role assignment page renders correctly for Administrators
  - [x] 4.2 Extend HandleInertiaRequests middleware
    - Add `auth.user.role` to shared data (primary role name)
    - Add `auth.permissions` array to shared data
    - Eager load roles and permissions when fetching authenticated user
    - Follow existing share pattern in middleware
  - [x] 4.3 Update TypeScript types
    - Extend User interface in `resources/js/types/index.d.ts`
    - Add `role?: string` property
    - Add `permissions?: string[]` property
    - Update Auth interface if needed
  - [x] 4.4 Create `usePermissions()` Vue composable
    - Access permissions from `usePage().props.auth`
    - Provide `can(permission: string): boolean` method
    - Provide `hasRole(role: string): boolean` method
    - Provide `hasAnyPermission(permissions: string[]): boolean` method
    - Export from `resources/js/composables/usePermissions.ts`
  - [x] 4.5 Update UserInfo component with role badge
    - Display role name below user's name
    - Apply distinct colors per role:
      - Administrator: red/rose badge
      - IT Manager: blue badge
      - Operator: amber/yellow badge
      - Auditor: purple badge
      - Viewer: gray badge
    - Follow existing Avatar/AvatarFallback styling patterns
    - Display in both sidebar NavUser and header user dropdown
  - [x] 4.6 Implement conditional navigation rendering
    - Update AppHeader.vue mainNavItems array
    - Hide menu items based on user permissions
    - Use `usePermissions()` composable for checks
    - Hide: Settings (requires settings.view), Users (requires users.view)
  - [x] 4.7 Create Role Assignment Inertia page
    - Create `resources/js/Pages/Users/Roles.vue`
    - Display table of users with current role
    - Role selection dropdown for each user
    - Submit button to update role
    - Use existing table and form component patterns
  - [x] 4.8 Conditionally render action buttons throughout app
    - Hide create buttons when user lacks `{resource}.create` permission
    - Hide edit buttons when user lacks `{resource}.update` permission
    - Hide delete buttons when user lacks `{resource}.delete` permission
    - Apply to existing resource pages (datacenters, racks, devices, etc.)
  - [x] 4.9 Ensure frontend tests pass
    - Run ONLY the 6 tests written in 4.1
    - Verify role badge displays correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6 tests written in 4.1 pass
- User role and permissions shared via Inertia props
- TypeScript types updated with role/permissions
- usePermissions() composable works correctly
- Role badge displays with correct styling per role
- Navigation and action buttons conditionally rendered

---

### Testing

#### Task Group 5: Test Review & Gap Analysis
**Dependencies:** Task Groups 1-4

- [x] 5.0 Review existing tests and fill critical gaps only
  - [x] 5.1 Review tests from Task Groups 1-4
    - Review the 4 tests written by package setup (Task 1.1)
    - Review the 6 tests written by database layer (Task 2.1)
    - Review the 6 tests written by authorization layer (Task 3.1)
    - Review the 6 tests written by frontend layer (Task 4.1)
    - Total existing tests: 22 tests
  - [x] 5.2 Analyze test coverage gaps for RBAC feature only
    - Identify critical user workflows that lack test coverage
    - Focus ONLY on gaps related to role-based access control
    - Prioritize end-to-end workflows: role assignment flow, permission denial flow
    - Do NOT assess entire application test coverage
  - [x] 5.3 Write up to 10 additional strategic tests maximum
    - Add integration tests for complete role assignment workflow
    - Add test for Administrator self-role-removal prevention
    - Add test for permission cache invalidation on role change
    - Add test for flash message display on unauthorized access
    - Add browser test for role badge visibility in UI
    - Focus on integration points between backend and frontend
    - Do NOT write comprehensive coverage for all permission combinations
  - [x] 5.4 Run feature-specific tests only
    - Run ONLY tests related to RBAC feature (tests from 1.1, 2.1, 3.1, 4.1, and 5.3)
    - Expected total: approximately 32 tests maximum
    - Do NOT run the entire application test suite
    - Verify critical workflows pass
  - [x] 5.5 Run full test suite with user approval
    - Ask user for permission to run full test suite
    - Run `php artisan test` to verify no regressions
    - Fix any failing tests from existing codebase

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 32 tests total)
- Critical user workflows for RBAC are covered
- No more than 10 additional tests added when filling in testing gaps
- Testing focused exclusively on RBAC feature requirements
- Full test suite passes without regressions

---

## Execution Order

Recommended implementation sequence:

1. **Package Installation & Configuration (Task Group 1)**
   - Foundation for all RBAC functionality
   - Must be completed first

2. **Database Layer (Task Group 2)**
   - Depends on package installation
   - Creates roles and permissions structure

3. **API & Authorization Layer (Task Group 3)**
   - Depends on roles/permissions existing in database
   - Implements server-side access control

4. **Frontend Layer (Task Group 4)**
   - Depends on authorization layer for data sharing
   - Implements client-side permission checks and UI

5. **Test Review & Gap Analysis (Task Group 5)**
   - Depends on all other groups
   - Final verification and quality assurance

---

## Permission Matrix Reference

| Resource | Administrator | IT Manager | Operator | Auditor | Viewer |
|----------|--------------|------------|----------|---------|--------|
| datacenters | CRUD | CRUD | R | R | R |
| racks | CRUD | CRUD | R | R | R |
| devices | CRUD | CRUD | CRUD | R | R |
| connections | CRUD | CRUD | CRUD | R | R |
| ports | CRUD | CRUD | CRUD | R | R |
| audits | CRUD + execute | R | R + execute | CRUD + execute | R |
| findings | CRUD + resolve | R | R | CRUD + resolve | R |
| implementation-files | CRUD + approve | CRUD + approve | R | R | R |
| reports | CRUD | R | - | R + create | R |
| users | CRUD | - | - | - | - |
| settings | RU | - | - | - | - |

*Legend: C=Create, R=Read, U=Update, D=Delete*
