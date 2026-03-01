# Task Breakdown: Implementation File Approval Workflow

## Overview
Total Tasks: 36 (across 4 task groups)

This feature adds a two-state approval workflow for implementation files to ensure only reviewed and approved files can serve as authoritative sources for datacenter audits, with separation of duties enforced between uploaders and approvers.

## Task List

### Database Layer

#### Task Group 1: Approval Fields and Notifications Infrastructure
**Dependencies:** None

- [x] 1.0 Complete database layer for approval workflow
  - [x] 1.1 Write 4-6 focused tests for approval model functionality
    - Test approval_status field defaults to "pending_approval" on new records
    - Test approved_by and approved_at are nullable and set correctly
    - Test approver relationship returns User model
    - Test new version inherits "pending_approval" status (approval does not carry over)
    - Test casts for approval_status enum and approved_at datetime
  - [x] 1.2 Create migration to add approval fields to implementation_files table
    - Add `approval_status` enum field with values: "pending_approval", "approved" (default: "pending_approval")
    - Add `approved_by` foreign key to users table (nullable, nullOnDelete)
    - Add `approved_at` timestamp field (nullable)
    - Add index on `approval_status` for filtering performance
  - [x] 1.3 Update ImplementationFile model with approval fields
    - Add `approval_status`, `approved_by`, `approved_at` to fillable array
    - Add cast for `approval_status` as enum/string
    - Add cast for `approved_at` as datetime
    - Add `approver()` BelongsTo relationship to User model
    - Add `isPendingApproval()` and `isApproved()` helper methods
  - [x] 1.4 Create notifications table migration (if not exists)
    - Use Laravel's standard notifications table structure
    - Run `php artisan make:notifications-table` to generate migration
    - Ensure migration is idempotent (check if table exists)
  - [x] 1.5 Update ImplementationFileController store method
    - Set `approval_status` to "pending_approval" on new file creation
    - Ensure new versions also get "pending_approval" status (in restore method too)
  - [x] 1.6 Ensure database layer tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify migration runs successfully
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- Migration adds approval fields correctly
- New files automatically have "pending_approval" status
- Approver relationship works correctly
- Notifications table exists for in-app notifications

---

### API Layer

#### Task Group 2: Approval Policy, Controller Action, and Notifications
**Dependencies:** Task Group 1

- [x] 2.0 Complete API layer for approval workflow
  - [x] 2.1 Write 6-8 focused tests for approval API
    - Test approve action sets approval_status to "approved"
    - Test approve action sets approved_by and approved_at
    - Test only users with Administrator or IT Manager roles can approve
    - Test users cannot approve files they uploaded (separation of duties)
    - Test users must have datacenter access to approve files
    - Test approve action returns updated ImplementationFileResource
    - Test approval is logged via Loggable concern
    - Test 403 response for unauthorized approval attempts
  - [x] 2.2 Add `approve` method to ImplementationFilePolicy
    - Require user to have Administrator or IT Manager role (use existing ADMIN_ROLES constant)
    - Require user to have datacenter access (use existing hasDatacenterAccess helper)
    - Enforce separation of duties: user cannot approve files where `uploaded_by` matches current user
    - Return boolean authorization result
  - [x] 2.3 Add `approve` method to ImplementationFileController
    - Accept Datacenter and ImplementationFile route parameters
    - Use Gate::authorize('approve', $implementationFile) for permission check
    - Update file with `approval_status` = "approved", `approved_by` = current user, `approved_at` = now()
    - Return JSON response with updated ImplementationFileResource
    - Activity logging happens automatically via Loggable concern
  - [x] 2.4 Create ApproveImplementationFileRequest form request (optional validation)
    - Validate file is in "pending_approval" status
    - Return appropriate error message if file already approved
  - [x] 2.5 Register approve route in routes/web.php or routes/api.php
    - POST /datacenters/{datacenter}/implementation-files/{implementation_file}/approve
    - Apply appropriate middleware (auth, verified)
  - [x] 2.6 Update ImplementationFileResource with approval fields
    - Add `approval_status` field
    - Add `approved_at` timestamp field
    - Add `approver` field using whenLoaded pattern with id and name
    - Add `can_approve` boolean indicating if current user can approve this file
  - [x] 2.7 Create ImplementationFileAwaitingApprovalNotification
    - Implement via(user) method returning ['mail', 'database']
    - Create toMail() method with file name, datacenter name, uploader name, and link to view file
    - Create toDatabase()/toArray() method for in-app notification data
    - Use Queueable trait for async delivery
  - [x] 2.8 Create ImplementationFileApprovedNotification
    - Implement via(user) method returning ['mail', 'database']
    - Create toMail() method with file name, datacenter name, approver name, and link to view file
    - Create toDatabase()/toArray() method for in-app notification data
    - Use Queueable trait for async delivery
  - [x] 2.9 Dispatch notifications from controller actions
    - In store method: send ImplementationFileAwaitingApprovalNotification to all IT Managers/Administrators with datacenter access
    - In approve method: send ImplementationFileApprovedNotification to uploader
    - Query users with correct roles and datacenter access for awaiting approval notification
  - [x] 2.10 Ensure API layer tests pass
    - Run ONLY the 6-8 tests written in 2.1
    - Verify approve action works correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6-8 tests written in 2.1 pass
- Approve action properly updates file status
- Separation of duties enforced (uploaders cannot approve own files)
- Role and datacenter access checks work correctly
- Notifications sent to appropriate users
- JSON response includes approval fields

---

### Frontend Components

#### Task Group 3: Approval UI and In-App Notifications
**Dependencies:** Task Group 2

- [x] 3.0 Complete UI components for approval workflow
  - [x] 3.1 Write 4-6 focused tests for UI approval functionality
    - Test approval status badge displays correctly for pending and approved files
    - Test Approve button only visible for authorized users on pending files
    - Test Approve button disabled for files user uploaded (with tooltip)
    - Test approval filter dropdown filters files correctly
    - Test notification badge shows unread count
    - Test approval confirmation dialog works
  - [x] 3.2 Update ImplementationFile TypeScript interface
    - Add `approval_status: 'pending_approval' | 'approved'` field
    - Add `approved_at: string | null` field
    - Add `approver: { id: number; name: string } | null` field
    - Add `can_approve: boolean` field
  - [x] 3.3 Add approval status badge to ImplementationFileList component
    - Display Badge with variant="warning" and text "Pending Approval" for pending files
    - Display Badge with variant="success" and text "Approved" for approved files
    - Position badge next to file name, after version badge
  - [x] 3.4 Add approval filter dropdown to ImplementationFileList
    - Create filter dropdown/tabs with options: "All", "Pending Approval", "Approved"
    - Default to "All" to show all files
    - Filter files client-side based on selection
    - Preserve filter state during session (optional: URL query parameter)
  - [x] 3.5 Add Approve button to file list actions
    - Display "Approve" button in actions column for users with can_approve permission
    - Only show for files in "pending_approval" status
    - Disable button and show tooltip "You cannot approve files you uploaded" when user is uploader
    - Use CheckCircle icon from lucide-vue-next
  - [x] 3.6 Create ApproveImplementationFileDialog component
    - Confirmation dialog with file name and warning about action
    - Show confirmation message: "Are you sure you want to approve this file?"
    - Include file details (name, uploader, date uploaded)
    - Cancel and Approve buttons
    - Handle loading state during API call
  - [x] 3.7 Implement approve action in ImplementationFileList
    - Call POST /datacenters/{datacenter}/implementation-files/{id}/approve
    - Show success toast notification after approval
    - Update file in list with new approval status (or reload list)
    - Handle error responses appropriately
  - [x] 3.8 Show approver info for approved files
    - In expanded view or tooltip, show "Approved by {approver name} on {date}"
    - Format date in user-friendly relative or absolute format
    - Only display for files with approval_status = "approved"
  - [x] 3.9 Create notification bell/dropdown in navigation header
    - Add notification bell icon to main navigation header
    - Display unread count badge when notifications exist
    - Create dropdown panel showing recent notifications
    - Each notification shows: message, relative time, read/unread status
  - [x] 3.10 Create NotificationController for fetching/marking notifications
    - GET /notifications - fetch user's notifications (paginated)
    - POST /notifications/{id}/read - mark notification as read
    - POST /notifications/mark-all-read - mark all as read
  - [x] 3.11 Implement notification dropdown functionality
    - Fetch notifications on dropdown open (or use polling/websocket)
    - Display notification list with type-specific formatting
    - Mark notifications as read when clicked
    - Link to relevant file/datacenter from notification
  - [x] 3.12 Ensure UI component tests pass
    - Run ONLY the 4-6 tests written in 3.1
    - Verify approval UI elements work correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 3.1 pass
- Approval status badges display correctly
- Approve button appears for authorized users only
- Separation of duties enforced in UI
- Filter dropdown works correctly
- Notification bell shows unread count
- Notifications link to relevant files

---

### Integration & Audit Warning

#### Task Group 4: Audit Creation Warning and Test Review
**Dependencies:** Task Groups 1-3

- [x] 4.0 Complete integration and audit warning functionality
  - [x] 4.1 Write 2-4 focused tests for audit creation warning
    - Test warning displays when datacenter has no approved implementation files
    - Test warning does not display when datacenter has approved files
    - Test audit creation can proceed despite warning (non-blocking)
    - Test warning message content is correct
  - [x] 4.2 Add hasApprovedImplementationFiles method to Datacenter model
    - Query implementation_files where approval_status = "approved"
    - Return boolean indicating if any approved files exist
    - Consider adding as a scope for reusability
  - [x] 4.3 Update audit creation page/component with warning
    - Check if selected datacenter has approved implementation files
    - Display warning alert when no approved files exist
    - Warning text: "This datacenter has no approved implementation files. Audits require approved implementation files as the authoritative source for expected connections."
    - Use warning variant Alert component
    - Warning is informational only, does not block audit creation
  - [x] 4.4 Pass approval status to frontend for audit creation
    - Include hasApprovedImplementationFiles in datacenter data passed to audit create page
    - Or fetch via separate API endpoint on datacenter selection
  - [x] 4.5 Review tests from Task Groups 1-3
    - Review the 4-6 tests written by database layer (Task 1.1)
    - Review the 6-8 tests written by API layer (Task 2.1)
    - Review the 4-6 tests written by UI layer (Task 3.1)
    - Total existing tests: approximately 14-20 tests
  - [x] 4.6 Analyze test coverage gaps for THIS feature only
    - Identify critical user workflows that lack test coverage
    - Focus on end-to-end approval workflow
    - Check notification delivery and display
    - Verify filter functionality across different scenarios
  - [x] 4.7 Write up to 6 additional strategic tests to fill critical gaps
    - End-to-end: file upload triggers awaiting approval notification
    - End-to-end: file approval triggers approved notification to uploader
    - Edge case: multiple approvers receive notification for same file
    - Edge case: version upload resets approval status
    - Integration: filter shows correct files for each status
    - Integration: notification count updates on new notification
  - [x] 4.8 Run feature-specific tests only
    - Run ONLY tests related to this spec's feature (tests from 1.1, 2.1, 3.1, 4.1, and 4.7)
    - Expected total: approximately 20-30 tests maximum
    - Do NOT run the entire application test suite
    - Verify all critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 20-30 tests total)
- Audit creation warning displays appropriately
- Warning does not block audit creation
- Critical user workflows for approval are covered
- No more than 6 additional tests added when filling gaps
- Testing focused exclusively on approval workflow feature

---

## Execution Order

Recommended implementation sequence:

1. **Database Layer (Task Group 1)** - Foundation for all other work
   - Migration for approval fields
   - Model updates
   - Notifications table setup

2. **API Layer (Task Group 2)** - Backend logic and notifications
   - Policy and authorization
   - Controller approve action
   - Email and in-app notifications
   - API resource updates

3. **Frontend Components (Task Group 3)** - User interface
   - Approval status badges
   - Approve button and dialog
   - Filter dropdown
   - Notification bell and dropdown

4. **Integration & Audit Warning (Task Group 4)** - Final integration
   - Audit creation warning
   - Test review and gap analysis
   - End-to-end verification

---

## Files to Create/Modify

### New Files
- `database/migrations/YYYY_MM_DD_HHMMSS_add_approval_fields_to_implementation_files_table.php`
- `database/migrations/YYYY_MM_DD_HHMMSS_create_notifications_table.php` (if not exists)
- `app/Notifications/ImplementationFileAwaitingApprovalNotification.php`
- `app/Notifications/ImplementationFileApprovedNotification.php`
- `app/Http/Controllers/NotificationController.php`
- `app/Http/Requests/ApproveImplementationFileRequest.php` (optional)
- `resources/js/components/implementation-files/ApproveImplementationFileDialog.vue`
- `resources/js/components/notifications/NotificationBell.vue`
- `resources/js/components/notifications/NotificationDropdown.vue`
- `tests/Feature/ImplementationFileApprovalTest.php`
- `tests/Feature/ImplementationFileNotificationTest.php`

### Modified Files
- `app/Models/ImplementationFile.php` - Add approval fields, relationships, and helper methods
- `app/Policies/ImplementationFilePolicy.php` - Add approve() method
- `app/Http/Controllers/ImplementationFileController.php` - Add approve action, update store for notifications
- `app/Http/Resources/ImplementationFileResource.php` - Add approval fields
- `routes/web.php` or `routes/api.php` - Add approve route, notification routes
- `resources/js/components/implementation-files/ImplementationFileList.vue` - Add badges, filter, approve button
- `resources/js/layouts/AppLayout.vue` (or main layout) - Add notification bell to header

---

## Technical Notes

### Existing Code to Leverage
- **ADMIN_ROLES constant** in `ImplementationFilePolicy` - reuse for approval authorization
- **hasDatacenterAccess helper** in `ImplementationFilePolicy` - reuse for datacenter access check
- **Loggable concern** on `ImplementationFile` - approval actions will be automatically logged
- **Badge component** with success/warning variants - use for approval status display
- **Notifiable trait** on User model - supports email and database notifications
- **HasRoles trait** from Spatie Permission - supports role checks for approver identification

### Key Constraints
- Separation of duties: `uploaded_by` must not equal current user for approval
- Only "pending_approval" files can be approved (no re-approval of already approved files)
- New versions always start with "pending_approval" status
- Approvers must have datacenter access AND Administrator/IT Manager role
- Warning on audit creation is non-blocking (informational only)
