# Spec Requirements: User Management Module

## Initial Description

User Management Module - CRUD interface for administrators to manage users, assign roles, and control datacenter access.

## Requirements Discussion

### First Round Questions

**Q1:** I see that role assignment already exists at `/users/roles` (via `RoleAssignmentController` and `Roles.vue`). I assume this new module should expand on that foundation to provide full user CRUD (create, read, update, delete) while incorporating the existing role assignment functionality into a unified interface. Is that correct, or should role assignment remain as a separate page?
**Answer:** Correct - expand on the existing foundation to provide full user CRUD while incorporating existing role assignment functionality into a unified interface.

**Q2:** For user creation, I assume Administrators will manually create users with email/password (since the roadmap shows registration is disabled in Fortify). Should newly created users receive an email notification with temporary credentials, or will Administrators communicate credentials out-of-band?
**Answer:** Correct - Administrators will communicate credentials out-of-band (no email notification with temporary credentials).

**Q3:** The product mission mentions "Sam the Administrator" managing access across multiple datacenters. I assume "datacenter access" means assigning users to specific datacenters they can access (beyond role-based permissions). Is that correct, or does "datacenter access" simply mean the existing role permissions (e.g., Operators can only view datacenters they have permission for)?
**Answer:** Correct - "datacenter access" means assigning users to specific datacenters they can access (beyond role-based permissions).

**Q4:** For the user list view, I assume we need: search/filter capabilities (by name, email, role, status), pagination for large user bases, and bulk actions (e.g., deactivate multiple users). Should we also include a "last active" timestamp to help identify inactive accounts?
**Answer:** Correct - include search/filter capabilities, pagination, bulk actions, AND include a "last active" timestamp to help identify inactive accounts.

**Q5:** I assume users should have a status field (active/inactive/suspended) so Administrators can temporarily disable access without deleting accounts. Is that correct, or is deletion the only way to revoke access?
**Answer:** Correct - users should have a status field (active/inactive/suspended) for temporary access disabling.

**Q6:** The existing `Users/Roles.vue` uses a simple table layout. I assume the new User Management interface should follow a similar pattern with an index/list page, plus a separate create/edit form (either as a modal or dedicated page). Do you have a preference for modal-based editing vs. dedicated pages?
**Answer:** Correct - follow similar pattern with index/list page plus separate create/edit form (modal or dedicated page).

**Q7:** I assume audit logging of user management actions (user created, role changed, user deactivated) is required since the roadmap includes "Activity Logging Infrastructure" as a Phase 1 item. Should this be implemented as part of this module, or will it be added later when that infrastructure is built?
**Answer:** Correct - should be implemented as part of this module (when Activity Logging Infrastructure is built in Phase 1).

**Q8:** Is there anything that should explicitly be excluded from this module? For example: password reset for other users, email verification management, two-factor authentication management for other users, or integration with external identity providers (LDAP/SSO)?
**Answer:** Exclude the suggested features - password reset for other users, email verification management, two-factor authentication management for other users, and integration with external identity providers (LDAP/SSO).

### Existing Code to Reference

**Similar Features Identified:**
- Feature: Role Assignment - Path: `/Users/helderdene/rackaudit/app/Http/Controllers/RoleAssignmentController.php`
- Feature: Role Assignment UI - Path: `/Users/helderdene/rackaudit/resources/js/Pages/Users/Roles.vue`
- Feature: Profile Settings Form - Path: `/Users/helderdene/rackaudit/resources/js/Pages/settings/Profile.vue`
- Feature: Delete User Component - Path: `/Users/helderdene/rackaudit/resources/js/Components/DeleteUser.vue`
- Feature: User Model - Path: `/Users/helderdene/rackaudit/app/Models/User.php`
- Feature: Roles and Permissions Seeder - Path: `/Users/helderdene/rackaudit/database/seeders/RolesAndPermissionsSeeder.php`
- Components to potentially reuse: HeadingSmall, Button, Input, Label, InputError, AppLayout, dialog components, table patterns from Roles.vue
- Backend logic to reference: RoleAssignmentController for role syncing pattern, ProfileController for form request validation pattern

### Follow-up Questions

No follow-up questions were needed.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A

## Requirements Summary

### Functional Requirements

**User CRUD Operations:**
- Create new users with name, email, password fields (Administrator sets credentials manually)
- View user details including profile information, assigned role, datacenter access, status, and last active timestamp
- Update user information (name, email, password, role, datacenter access, status)
- Delete users with confirmation dialog
- Incorporate existing role assignment functionality into the unified interface

**User List/Index Features:**
- Paginated list of all users
- Search functionality (by name, email)
- Filter by role, status, datacenter access
- Display columns: name, email, role, status, last active, actions
- Bulk actions support (e.g., deactivate multiple users)

**User Status Management:**
- Status field with values: active, inactive, suspended
- Ability to temporarily disable access without deleting accounts
- Status changes should prevent login for inactive/suspended users

**Datacenter Access Control:**
- Assign users to specific datacenters they can access
- This is separate from role-based permissions (layered access control)
- Users can be assigned to multiple datacenters
- Relationship: User has many Datacenters (pivot table)

**Last Active Tracking:**
- Track and display last active timestamp for each user
- Update timestamp on user activity/login
- Help identify inactive accounts

**Audit Logging (when Activity Logging Infrastructure is built):**
- Log user creation events
- Log role assignment/change events
- Log status change events
- Log user deletion events
- Log datacenter access assignment changes

### Reusability Opportunities

- Extend existing `RoleAssignmentController` or create new `UserController` that incorporates its functionality
- Reuse table pattern from `Users/Roles.vue` for user list
- Reuse form components pattern from `settings/Profile.vue`
- Reuse `DeleteUser.vue` pattern for delete confirmation
- Use existing UI components: Button, Input, Label, InputError, dialog components
- Use existing Spatie Laravel-Permission integration for role management
- Existing roles: Administrator, IT Manager, Operator, Auditor, Viewer

### Scope Boundaries

**In Scope:**
- Full CRUD for user management
- Role assignment (integrated from existing functionality)
- Datacenter access assignment
- User status management (active/inactive/suspended)
- Last active timestamp tracking
- Search, filter, and pagination for user list
- Bulk actions (deactivate multiple users)
- Delete user with confirmation
- Audit logging hooks (implementation depends on Activity Logging Infrastructure)

**Out of Scope:**
- Password reset for other users (users manage their own via settings)
- Email verification management for other users
- Two-factor authentication management for other users
- Integration with external identity providers (LDAP/SSO)
- Email notifications for new user credentials (out-of-band communication)
- Self-registration (disabled in Fortify)

### Technical Considerations

- Uses Spatie Laravel-Permission for RBAC (already integrated)
- User model already has `HasRoles` trait from Spatie
- Need to add `status` field to users table (migration required)
- Need to add `last_active_at` field to users table (migration required)
- Need pivot table for user-datacenter relationship (datacenter model may not exist yet - Phase 2)
- Administrator-only access (middleware: `role:Administrator`)
- Follow existing patterns: Inertia.js + Vue 3 + Tailwind CSS 4
- Use Laravel Form Request classes for validation
- Use Wayfinder for TypeScript route generation
- Existing route prefix: `/users/roles` - new routes likely under `/users`
