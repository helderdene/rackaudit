# Task Breakdown: User Management Module

## Overview
Total Tasks: 45
Estimated Task Groups: 5

This task breakdown implements a comprehensive CRUD interface for Administrators to manage users, assign roles, control datacenter access, and track user activity.

## Task List

### Database Layer

#### Task Group 1: User Model Extensions and Migrations
**Dependencies:** None

- [x] 1.0 Complete database layer for user management
  - [x] 1.1 Write 2-8 focused tests for User model extensions
    - Test status enum validation (active, inactive, suspended)
    - Test last_active_at timestamp updates
    - Test datacenter relationship (pivot table)
    - Test soft delete functionality
  - [x] 1.2 Create migration for user status and last_active_at fields
    - Add `status` enum column with values: active, inactive, suspended (default: active)
    - Add `last_active_at` nullable timestamp column
    - Add `deleted_at` column for soft deletes
    - Add index on `status` for filtering performance
  - [x] 1.3 Create `datacenter_user` pivot table migration
    - Create pivot table with `user_id` and `datacenter_id` foreign keys
    - Add timestamps to track when access was granted
    - Add indexes for both foreign keys
    - Note: Datacenter model may not exist yet - use unsigned big integer for datacenter_id
  - [x] 1.4 Extend User model with new attributes and relationships
    - Add `status` and `last_active_at` to $fillable array
    - Add `last_active_at` to casts() method as datetime
    - Add SoftDeletes trait
    - Create `datacenters()` belongsToMany relationship
    - Add `isActive()`, `isInactive()`, `isSuspended()` helper methods
  - [x] 1.5 Update UserFactory with status and last_active_at
    - Add status field with default 'active'
    - Add last_active_at with random past timestamp
    - Create states for inactive and suspended users
  - [x] 1.6 Ensure database layer tests pass
    - Run ONLY the 2-8 tests written in 1.1
    - Verify migrations run successfully with `php artisan migrate:fresh`
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-8 tests written in 1.1 pass
- Migrations run without errors
- User model has status, last_active_at, and datacenters relationship
- Soft deletes are properly configured
- Factory generates valid test data

---

### Backend Layer

#### Task Group 2: User Controller and Form Requests
**Dependencies:** Task Group 1

- [x] 2.0 Complete backend controller layer
  - [x] 2.1 Write 2-8 focused tests for UserController
    - Test user index with pagination, search, and filters
    - Test user creation with validation
    - Test user update (including self-demotion prevention)
    - Test user deletion (including self-deletion prevention)
    - Test bulk status change action
  - [x] 2.2 Create StoreUserRequest form request
    - Validate name (required, string, max:255)
    - Validate email (required, email, unique:users)
    - Validate password (required, min:8, confirmed)
    - Validate role (required, exists in available roles)
    - Validate status (required, in:active,inactive,suspended)
    - Validate datacenter_ids (optional, array of valid IDs)
  - [x] 2.3 Create UpdateUserRequest form request
    - Validate name (required, string, max:255)
    - Validate email (required, email, unique:users,id)
    - Validate password (optional, nullable, min:8, confirmed)
    - Validate role (required, exists in available roles)
    - Validate status (required, in:active,inactive,suspended)
    - Validate datacenter_ids (optional, array)
    - Add authorization check for self-demotion/deactivation prevention
  - [x] 2.4 Create BulkUserStatusRequest form request
    - Validate user_ids (required, array, min:1)
    - Validate status (required, in:active,inactive,suspended)
    - Validate that current user is not in the user_ids array
  - [x] 2.5 Create UserController with CRUD actions
    - `index()`: Paginated list with search (name, email) and filters (role, status)
    - `create()`: Return Inertia form page with available roles and datacenters
    - `store()`: Create user, sync role, sync datacenters, dispatch UserCreated event
    - `edit()`: Return Inertia form page with user data pre-populated
    - `update()`: Update user, sync role, sync datacenters, dispatch UserUpdated event
    - `destroy()`: Soft delete user with self-deletion prevention, dispatch UserDeleted event
    - `bulkStatus()`: Change status for multiple users, dispatch UserStatusChanged events
    - Follow pattern from RoleAssignmentController for JSON/Inertia dual response
  - [x] 2.6 Define routes for user management
    - GET `/users` - index
    - GET `/users/create` - create form
    - POST `/users` - store
    - GET `/users/{user}/edit` - edit form
    - PUT `/users/{user}` - update
    - DELETE `/users/{user}` - destroy
    - POST `/users/bulk-status` - bulk status change
    - Add `role:Administrator` middleware to all routes
    - Add redirect from `/users/roles` to `/users` for backward compatibility
  - [x] 2.7 Ensure backend layer tests pass
    - Run ONLY the 2-8 tests written in 2.1
    - Verify all CRUD operations work correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-8 tests written in 2.1 pass
- All CRUD operations function correctly
- Form validation works on all endpoints
- Self-demotion and self-deletion are prevented
- Bulk status change works correctly
- Routes are protected by Administrator role middleware

---

#### Task Group 3: Authentication Middleware and Events
**Dependencies:** Task Group 2

- [x] 3.0 Complete authentication hooks and event system
  - [x] 3.1 Write 2-4 focused tests for auth middleware and events
    - Test that inactive users cannot login
    - Test that suspended users cannot login
    - Test that last_active_at is updated on login
    - Test that user management events are dispatched
  - [x] 3.2 Create authentication middleware for status check
    - Create `EnsureUserIsActive` middleware
    - Check user status after authentication
    - Redirect inactive/suspended users with appropriate message
    - Register middleware in bootstrap/app.php
  - [x] 3.3 Implement last_active_at update on login
    - Use Fortify `Login` event listener
    - Create `UpdateLastActiveTimestamp` listener
    - Update `last_active_at` to current timestamp on successful login
    - Register listener in EventServiceProvider or bootstrap
  - [x] 3.4 Create user management events
    - `UserCreated` event with actor, user data, timestamp
    - `UserUpdated` event with actor, user, old values, new values, timestamp
    - `UserDeleted` event with actor, user data, timestamp
    - `UserStatusChanged` event with actor, user, old status, new status, timestamp
    - `UserRoleChanged` event with actor, user, old role, new role, timestamp
    - Note: Actual logging deferred to Activity Logging Infrastructure
  - [x] 3.5 Ensure authentication tests pass
    - Run ONLY the 2-4 tests written in 3.1
    - Verify inactive/suspended users cannot login
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-4 tests written in 3.1 pass
- Inactive and suspended users are blocked from login
- Last active timestamp updates on successful login
- Events are dispatched (listeners can be added later)

---

### Frontend Layer

#### Task Group 4: User Management UI Components
**Dependencies:** Task Groups 2, 3

- [x] 4.0 Complete frontend user management interface
  - [x] 4.1 Write 2-8 focused tests for UI components
    - Test user list page renders with data
    - Test user creation form submission
    - Test user edit form with pre-populated values
    - Test delete confirmation dialog
    - Test bulk action selection and execution
  - [x] 4.2 Create Badge component for status display
    - Create `resources/js/components/ui/badge/Badge.vue`
    - Support variants: default, success, warning, destructive
    - Map status to variants: active=success, inactive=warning, suspended=destructive
    - Follow existing UI component patterns
  - [x] 4.3 Create UserForm component
    - Shared form component for create and edit
    - Fields: name, email, password (optional on edit), password_confirmation
    - Role select dropdown using available roles
    - Status select dropdown (active, inactive, suspended)
    - Datacenter multi-select for access assignment
    - Use Inertia Form component pattern from Profile.vue
    - Integrate Wayfinder for controller actions
    - Add client-side validation matching server rules
    - Show `recentlySuccessful` feedback on save
  - [x] 4.4 Create DeleteUserDialog component
    - Confirmation dialog for user deletion
    - Display username being deleted
    - Follow pattern from DeleteUser.vue
    - Use Dialog components from UI library
    - Prevent deletion of current user (hide button or disable)
  - [x] 4.5 Create BulkActionsBar component
    - Fixed bar that appears when users are selected
    - Show count of selected users
    - Status change dropdown (Activate, Deactivate, Suspend)
    - Confirmation dialog before execution
    - Exclude current user from bulk operations
  - [x] 4.6 Create Users/Index.vue page
    - Page header with title and "Create User" button
    - Search input for name/email filtering
    - Filter dropdowns for role and status
    - Paginated table with columns: checkbox, name, email, role, status, last active, actions
    - Checkbox column for bulk selection (with select all)
    - Status displayed with Badge component
    - Last active with relative timestamp (e.g., "2 hours ago", "Never")
    - Action buttons: Edit, Delete per row
    - Follow table pattern from Roles.vue
    - Use deferred props for initial load with skeleton loading
    - Implement server-side pagination using Inertia
  - [x] 4.7 Create Users/Create.vue page
    - Page header with breadcrumbs (Users > Create User)
    - Embed UserForm component in create mode
    - Redirect to user list on successful creation
    - Match layout pattern from settings/Profile.vue
  - [x] 4.8 Create Users/Edit.vue page
    - Page header with breadcrumbs (Users > Edit {username})
    - Embed UserForm component in edit mode with pre-populated values
    - Password field optional (placeholder text explaining this)
    - Show DeleteUserDialog trigger
    - Prevent self-demotion/deactivation with UI feedback
    - Match layout pattern from settings/Profile.vue
  - [x] 4.9 Run Wayfinder generation for new routes
    - Run `php artisan wayfinder:generate`
    - Verify TypeScript actions are generated for UserController
    - Import and use in Vue components
  - [x] 4.10 Ensure frontend tests pass
    - Run ONLY the 2-8 tests written in 4.1
    - Verify components render and function correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-8 tests written in 4.1 pass
- User list displays with search, filter, and pagination
- Create and edit forms work correctly
- Delete confirmation shows and works
- Bulk actions function correctly
- All components follow existing design patterns
- Responsive design works on mobile, tablet, desktop

---

### Testing & Integration

#### Task Group 5: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-4

- [x] 5.0 Review existing tests and fill critical gaps only
  - [x] 5.1 Review tests from Task Groups 1-4
    - Review the 2-8 tests written by database layer (Task 1.1)
    - Review the 2-8 tests written by backend layer (Task 2.1)
    - Review the 2-4 tests written by auth middleware (Task 3.1)
    - Review the 2-8 tests written by frontend layer (Task 4.1)
    - Total existing tests: approximately 8-28 tests
  - [x] 5.2 Analyze test coverage gaps for user management feature
    - Identify critical user workflows that lack test coverage
    - Focus ONLY on gaps related to this spec's feature requirements
    - Do NOT assess entire application test coverage
    - Prioritize end-to-end workflows over unit test gaps
  - [x] 5.3 Write up to 10 additional strategic tests maximum
    - Add maximum of 10 new tests to fill identified critical gaps
    - Focus on integration points and end-to-end workflows
    - Suggested areas to cover if gaps exist:
      - Full user creation flow (form -> controller -> database -> redirect)
      - Role change with event dispatch verification
      - Bulk operations with mixed valid/invalid users
      - Search and filter combination testing
      - Edge cases: empty datacenter list, last admin protection
    - Do NOT write comprehensive coverage for all scenarios
    - Skip edge cases not critical to core functionality
  - [x] 5.4 Run feature-specific tests only
    - Run ONLY tests related to user management feature
    - Expected total: approximately 18-38 tests maximum
    - Do NOT run the entire application test suite unless requested
    - Verify critical workflows pass
  - [x] 5.5 Update UserSeeder for development testing
    - Add variety of users with different statuses
    - Include users with various roles
    - Add users with datacenter assignments (if datacenters exist)
    - Create at least 25 users for pagination testing

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 18-38 tests total)
- Critical user workflows for this feature are covered
- No more than 10 additional tests added when filling in testing gaps
- Testing focused exclusively on user management feature requirements
- Development seeder provides realistic test data

---

## Execution Order

Recommended implementation sequence:

1. **Database Layer (Task Group 1)** - Foundation for all other work
   - Migrations must run before any model changes can be used
   - User model extensions enable all subsequent features

2. **Backend Layer (Task Group 2)** - API and controller logic
   - Depends on database layer being complete
   - Provides endpoints for frontend to consume

3. **Authentication Middleware and Events (Task Group 3)** - Security and tracking
   - Can be partially parallelized with Task Group 2
   - Status middleware depends on status field from Task Group 1

4. **Frontend Layer (Task Group 4)** - User interface
   - Depends on backend routes being available
   - Can start component work while backend is in progress

5. **Test Review & Gap Analysis (Task Group 5)** - Quality assurance
   - Must be last to review all implemented functionality
   - Ensures comprehensive coverage of critical paths

## Key Implementation Notes

### Existing Code to Leverage
- `RoleAssignmentController.php`: Pattern for role syncing and self-role-removal prevention
- `Users/Roles.vue`: Table structure with border, bg-muted/50, hover patterns
- `settings/Profile.vue`: Form component pattern with Wayfinder imports
- `DeleteUser.vue`: Dialog structure for delete confirmations
- UI Components: Button, Input, Label, InputError, Dialog, Skeleton

### Technical Considerations
- Uses Spatie Laravel-Permission for RBAC (already integrated)
- User model already has `HasRoles` trait from Spatie
- Available roles: Administrator, IT Manager, Operator, Auditor, Viewer
- Administrator-only access via `role:Administrator` middleware
- Datacenter model may not exist yet - create minimal relationship only
- Events prepared for Activity Logging Infrastructure (Phase 1)

### Deprecation Note
- Standalone `/users/roles` page should redirect to new `/users` management interface
- Role assignment is now integrated into user edit form
