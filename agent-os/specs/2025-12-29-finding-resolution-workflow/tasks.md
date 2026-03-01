# Task Breakdown: Finding Resolution Workflow

## Overview
Total Tasks: 39 (across 5 task groups)

This feature formalizes the finding resolution process with enhanced UX including quick action buttons for status transitions, bulk operations for assignment and status changes, in-app and email notifications, due date tracking, and resolution timeline metrics.

## Task List

### Database Layer

#### Task Group 1: Database Schema and Models
**Dependencies:** None
**Complexity:** Medium

- [x] 1.0 Complete database layer for finding resolution workflow
  - [x] 1.1 Write 4-6 focused tests for database layer
    - Test FindingStatusTransition model creation and relationships
    - Test Finding model due_date handling (overdue, due_soon scopes)
    - Test statusTransitions relationship on Finding
    - Test time metrics calculations (time_to_first_response, total_resolution_time)
  - [x] 1.2 Create `finding_status_transitions` migration
    - Columns: id, finding_id (foreign key), from_status (enum), to_status (enum), user_id (foreign key), notes (nullable text), transitioned_at (timestamp)
    - Add indexes on finding_id, user_id, transitioned_at
    - Foreign keys: findings.id, users.id
  - [x] 1.3 Add `due_date` migration for findings table
    - Add nullable date column `due_date` after `resolved_at`
    - No default value (findings created before this are grandfathered)
  - [x] 1.4 Create FindingStatusTransition model
    - Fillable: finding_id, from_status, to_status, user_id, notes, transitioned_at
    - Casts: from_status and to_status to FindingStatus enum, transitioned_at to datetime
    - Relationships: belongsTo Finding, belongsTo User
  - [x] 1.5 Update Finding model
    - Add `due_date` to fillable array
    - Add cast for `due_date` as date
    - Add hasMany relationship to FindingStatusTransition
    - Add scopes: `scopeOverdue()`, `scopeDueSoon()`, `scopeNoDueDate()`
    - Add accessor methods: `isOverdue()`, `isDueSoon()` (within 3 days)
  - [x] 1.6 Create FindingStatusTransition factory for testing
  - [x] 1.7 Update FindingFactory to include due_date states
    - Add states: `overdue()`, `dueSoon()`, `noDueDate()`
  - [x] 1.8 Ensure database layer tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify migrations run successfully

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- Migrations run without errors
- FindingStatusTransition model correctly tracks status changes
- Finding model has working due date scopes and accessors
- Relationships are properly defined and functional

---

### Backend API Layer

#### Task Group 2: API Endpoints and Business Logic
**Dependencies:** Task Group 1
**Complexity:** High

- [x] 2.0 Complete API layer for finding resolution workflow
  - [x] 2.1 Write 6-8 focused tests for API endpoints
    - Test quick status transition endpoint (POST /findings/{finding}/transition)
    - Test bulk assign endpoint (POST /findings/bulk-assign)
    - Test bulk status change endpoint (POST /findings/bulk-status)
    - Test due date filter in index endpoint (overdue, due_soon, no_due_date)
    - Test resolution notes minimum length validation (10 characters)
    - Test status transition creates FindingStatusTransition record
    - Test notification is dispatched on assignment change
    - Test notification is dispatched on status change
  - [x] 2.2 Update UpdateFindingRequest validation
    - Add `due_date` validation rule: nullable, date, after_or_equal:today
    - Add minimum length validation for resolution_notes (10 characters) when resolving
    - Grandfather existing resolution notes (only validate on new resolutions)
  - [x] 2.3 Create QuickTransitionRequest form request
    - Validate: target_status (required, enum), notes (optional, string)
    - Include authorization logic from UpdateFindingRequest
  - [x] 2.4 Create BulkFindingAssignRequest form request
    - Validate: finding_ids (required, array), assigned_to (required, exists:users,id)
    - Authorize: user must have access to all selected findings
  - [x] 2.5 Create BulkFindingStatusRequest form request
    - Validate: finding_ids (required, array), status (required, enum)
    - Authorize: user must have access to all selected findings
    - Validate workflow rules apply to all selected findings (or user is admin)
  - [x] 2.6 Add quick transition method to FindingController
    - Route: POST /findings/{finding}/transition
    - Accept target status and optional notes
    - Create FindingStatusTransition record
    - Dispatch appropriate notifications
    - Return updated finding data
  - [x] 2.7 Add bulk assign method to FindingController
    - Route: POST /findings/bulk-assign
    - Process in chunks of 100 for large selections
    - Create assignment notification for new assignee
    - Create reassignment notification for previous assignees
    - Return success count and any failures
  - [x] 2.8 Add bulk status change method to FindingController
    - Route: POST /findings/bulk-status
    - Process in chunks of 100 for large selections
    - Validate workflow rules for each finding (or bypass for admin)
    - Create FindingStatusTransition records
    - Dispatch status change notifications
    - Return success count and any failures
  - [x] 2.9 Update FindingController index method
    - Add due date filters: due_date_status (overdue, due_soon, no_due_date)
    - Include due_date in response data
    - Include due_date status indicators (is_overdue, is_due_soon)
  - [x] 2.10 Update FindingController show method
    - Include statusTransitions in response (timeline data)
    - Include time metrics: time_to_first_response, time_in_each_status, total_resolution_time
    - Include available quick actions based on current status
  - [x] 2.11 Update FindingController update method
    - Create FindingStatusTransition record on status change
    - Dispatch notifications on assignment change
    - Dispatch notifications on status change
  - [x] 2.12 Ensure API layer tests pass
    - Run ONLY the 6-8 tests written in 2.1
    - Verify all endpoints return correct responses

**Acceptance Criteria:**
- The 6-8 tests written in 2.1 pass
- Quick transition endpoint works with proper workflow validation
- Bulk operations process correctly with chunking for large datasets
- Status transitions are logged to finding_status_transitions table
- Due date filtering works in index view
- Time metrics are calculated and returned correctly

---

#### Task Group 3: Notification System
**Dependencies:** Task Group 1
**Complexity:** Medium

- [x] 3.0 Complete notification system for finding workflow
  - [x] 3.1 Write 4-6 focused tests for notifications
    - Test FindingAssignedNotification sends database and mail (when configured)
    - Test FindingStatusChangedNotification sends database and mail
    - Test FindingDueDateApproachingNotification triggers at 3 days before due
    - Test FindingOverdueNotification triggers when past due date
    - Test notification contains correct finding data and link
  - [x] 3.2 Create FindingAssignedNotification class
    - Channels: database, mail (when configured)
    - Include: finding title, audit name, datacenter, direct link
    - Implement toMail() and toArray() methods
    - Use ShouldQueue interface
  - [x] 3.3 Create FindingReassignedNotification class
    - Sent to previous assignee when reassigned away
    - Channels: database, mail (when configured)
    - Include: finding title, new assignee name, direct link
  - [x] 3.4 Create FindingStatusChangedNotification class
    - Sent to assignee when status changes on their findings
    - Channels: database, mail (when configured)
    - Include: finding title, old status, new status, direct link
  - [x] 3.5 Create FindingDueDateApproachingNotification class
    - Sent to assignee 3 days before due date
    - Channels: database, mail (when configured)
    - Include: finding title, due date, direct link
  - [x] 3.6 Create FindingOverdueNotification class
    - Sent to assignee when finding becomes overdue
    - Channels: database, mail (when configured)
    - Include: finding title, due date, days overdue, direct link
  - [x] 3.7 Update NotificationController
    - Add new notification types to getNotificationType() method
    - Add new message builders to buildMessage() method
    - Add link builders to buildLink() method for finding notifications
  - [x] 3.8 Create scheduled command for due date notifications
    - Artisan command: findings:send-due-date-notifications
    - Check for findings approaching due date (3 days) not already notified
    - Check for newly overdue findings not already notified
    - Schedule to run daily via Kernel
  - [x] 3.9 Ensure notification tests pass
    - Run ONLY the 4-6 tests written in 3.1
    - Verify notifications are dispatched correctly

**Acceptance Criteria:**
- The 4-6 tests written in 3.1 pass
- All notification types send both database and email (when mail configured)
- Notifications contain correct finding information and links
- Due date notifications are sent at appropriate times
- Previous assignee is notified when finding is reassigned

---

### Frontend Layer

#### Task Group 4: UI Components and Pages
**Dependencies:** Task Groups 2, 3
**Complexity:** High

- [x] 4.0 Complete frontend UI for finding resolution workflow
  - [x] 4.1 Write 4-6 focused tests for UI components
    - Test quick action buttons render based on current status
    - Test bulk selection and bulk action toolbar appears
    - Test due date picker in edit form
    - Test workflow progress indicator displays correctly
    - Test resolution notes shows character count and minimum hint
  - [x] 4.2 Create WorkflowProgressIndicator.vue component
    - Display workflow steps: Open > In Progress > Pending Review > Resolved
    - Highlight current status
    - Show Deferred as a side branch
    - Use existing design system colors from FindingStatus enum
  - [x] 4.3 Create QuickActionButtons.vue component
    - Props: currentStatus, isAdmin, onTransition callback
    - "Start Working" button: visible when Open
    - "Submit for Review" button: visible when In Progress
    - "Approve & Close" button: visible when Pending Review (with notes modal)
    - "Defer" button: visible when Open or In Progress
    - "Reopen" button: visible when Deferred, or Resolved (admins only)
    - Use existing Button component variants for distinct styling
  - [x] 4.4 Create StatusTransitionTimeline.vue component
    - Display list of status transitions with timestamps
    - Show: from_status > to_status, user name, relative time, notes (if any)
    - Use existing Card component styling
  - [x] 4.5 Create BulkActionToolbar.vue component
    - Props: selectedCount, onAssign, onChangeStatus, onDefer, onClear
    - "Assign to..." dropdown with user selector
    - "Change Status to..." dropdown with status options
    - "Defer All" quick action button
    - "Clear Selection" button
    - Show selected count
  - [x] 4.6 Create DueDateIndicator.vue component
    - Props: dueDate, isOverdue, isDueSoon
    - Display due date with appropriate styling
    - Red styling when overdue
    - Amber/yellow styling when due soon (within 3 days)
    - Normal styling otherwise
  - [x] 4.7 Update Findings/Index.vue page
    - Add checkbox column as first column in table (desktop and mobile)
    - Add "Select All" checkbox in table header
    - Track selected finding IDs in reactive state
    - Add BulkActionToolbar component (appears when items selected)
    - Add due date column after Status column
    - Add DueDateIndicator to each row
    - Add due date filter dropdown (Overdue, Due Soon, No Due Date)
    - Implement bulk assign API call with loading state
    - Implement bulk status change API call with loading state
  - [x] 4.8 Update Findings/Show.vue page
    - Add WorkflowProgressIndicator below header badges
    - Add QuickActionButtons below WorkflowProgressIndicator
    - Add "Next Steps" guidance text based on current status
    - Add due date picker to edit form (using existing date input patterns)
    - Add character count to resolution notes textarea
    - Add minimum 10 characters hint when status is resolved
    - Add StatusTransitionTimeline section (new Card)
    - Display time metrics (time to first response, total resolution time)
  - [x] 4.9 Update TypeScript types for findings
    - Add due_date field to finding types
    - Add is_overdue, is_due_soon boolean fields
    - Add status_transitions array type
    - Add time_metrics type for timeline data
    - Add bulk operation request/response types
  - [x] 4.10 Ensure UI component tests pass
    - Run ONLY the 4-6 tests written in 4.1
    - Verify components render correctly

**Acceptance Criteria:**
- The 4-6 tests written in 4.1 pass
- Quick action buttons display contextually based on status
- Bulk selection and operations work on Index page
- Due date picker and indicators function correctly
- Workflow progress indicator shows current position
- Timeline displays status transition history
- Resolution notes show character count and validation hint

---

### Testing and Integration

#### Task Group 5: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-4
**Complexity:** Medium

- [x] 5.0 Review existing tests and fill critical gaps
  - [x] 5.1 Review tests from Task Groups 1-4
    - Review 6 database layer tests (Task 1.1)
    - Review 8 API endpoint tests (Task 2.1)
    - Review 7 notification tests (Task 3.1)
    - Review 6 UI component tests (Task 4.1)
    - Total existing tests: 27 tests
  - [x] 5.2 Analyze test coverage gaps for finding resolution workflow
    - Identified gaps: end-to-end workflow, bulk chunking, admin override, command testing, grandfathered notes, timeline metrics
    - Focus on integration points between components
    - Prioritized: full workflow from assignment to resolution, bulk operations edge cases
  - [x] 5.3 Write up to 8 additional strategic tests
    - Test complete workflow: Open > In Progress > Pending Review > Resolved
    - Test bulk assign with 100+ findings (chunking behavior)
    - Test admin override of workflow restrictions
    - Test due date notification scheduling command
    - Test grandfathered resolution notes (existing short notes still work)
    - Test timeline metrics accuracy (calculated times)
    - Test admin can reopen resolved findings (clears resolution data)
    - Test bulk status change creates individual transitions
  - [x] 5.4 Run feature-specific tests only
    - Ran all tests related to finding resolution workflow
    - Total tests: 35 tests (27 existing + 8 new)
    - All critical workflows pass
  - [x] 5.5 Document any known limitations or edge cases
    - See Known Limitations section below

**Acceptance Criteria:**
- All feature-specific tests pass (35 tests total)
- Complete workflow from assignment to resolution is covered
- Bulk operations are tested with edge cases
- 8 additional tests added (within budget)
- Critical integration points are verified

---

## Known Limitations and Edge Cases

### Performance Considerations for Bulk Operations
- Bulk operations are chunked at 100 items per batch to prevent request timeouts
- For very large selections (1000+ findings), operations may take several seconds
- Progress feedback is not provided during bulk processing (only final result)

### Notification Timing for Due Date Alerts
- The `findings:send-due-date-notifications` command should be scheduled to run daily
- Due date approaching notifications are sent when findings are within 3 days of due date
- Overdue notifications are sent when findings pass their due date
- Notifications are de-duplicated: only one notification per finding per day per type
- Resolved and Deferred findings are excluded from due date notifications

### Workflow Transition Restrictions
- The `FindingStatus::canTransitionTo()` method includes `Resolved -> Open` as an allowed transition
- The spec indicates "Reopen" should be admin-only for Resolved findings
- Current implementation allows any authorized user to reopen via the workflow (not enforced at transition level)
- Admin restriction is primarily enforced via UI (Reopen button visibility)

### Resolution Notes Grandfathering
- Existing findings with resolution notes shorter than 10 characters are grandfathered
- Validation only applies when providing NEW resolution notes during the resolve action
- Re-resolving a finding without changing notes will preserve the original short notes

### Test Files Summary
| Test File | Test Count | Coverage Area |
|-----------|------------|---------------|
| DatabaseLayerTest.php | 6 | Models, scopes, relationships, metrics |
| FindingApiTest.php | 8 | Endpoints, validation, notifications |
| FindingNotificationTest.php | 7 | All notification types, data content |
| FindingUIComponentsTest.php | 6 | Component rendering, data availability |
| FindingWorkflowIntegrationTest.php | 8 | End-to-end flows, edge cases |
| **Total** | **35** | |

---

## Execution Order

Recommended implementation sequence:

1. **Database Layer (Task Group 1)** - Foundation for all other work
   - Migration for finding_status_transitions table
   - Migration for due_date column
   - Models and relationships

2. **Backend API Layer (Task Group 2)** - Core business logic
   - Form requests and validation updates
   - Quick transition endpoint
   - Bulk operation endpoints
   - Index/Show updates for due dates and timeline

3. **Notification System (Task Group 3)** - Can be done in parallel with API layer
   - Notification classes
   - NotificationController updates
   - Scheduled command for due date alerts

4. **Frontend UI Layer (Task Group 4)** - Depends on API being ready
   - Components: WorkflowProgressIndicator, QuickActionButtons, etc.
   - Index page bulk selection and actions
   - Show page workflow UI and timeline

5. **Test Review and Gap Analysis (Task Group 5)** - Final verification
   - Review all tests from previous groups
   - Fill critical coverage gaps
   - End-to-end workflow verification

---

## Technical Notes

### Existing Code to Leverage
- `FindingStatus::canTransitionTo()` for workflow validation
- `NotificationController` patterns for formatting notifications
- `NewDiscrepancyNotification` as template for notification classes
- `UpdateFindingRequest` for validation patterns
- Existing Vue components: Button, Card, Input, Textarea, CategorySelect

### Key Files to Modify
- `/Users/helderdene/rackaudit/app/Models/Finding.php`
- `/Users/helderdene/rackaudit/app/Http/Controllers/FindingController.php`
- `/Users/helderdene/rackaudit/app/Http/Requests/UpdateFindingRequest.php`
- `/Users/helderdene/rackaudit/app/Http/Controllers/NotificationController.php`
- `/Users/helderdene/rackaudit/resources/js/Pages/Findings/Index.vue`
- `/Users/helderdene/rackaudit/resources/js/Pages/Findings/Show.vue`

### New Files to Create
- Migration: `create_finding_status_transitions_table`
- Migration: `add_due_date_to_findings_table`
- Model: `/Users/helderdene/rackaudit/app/Models/FindingStatusTransition.php`
- Request: `/Users/helderdene/rackaudit/app/Http/Requests/QuickTransitionRequest.php`
- Request: `/Users/helderdene/rackaudit/app/Http/Requests/BulkFindingAssignRequest.php`
- Request: `/Users/helderdene/rackaudit/app/Http/Requests/BulkFindingStatusRequest.php`
- Notification: `/Users/helderdene/rackaudit/app/Notifications/FindingAssignedNotification.php`
- Notification: `/Users/helderdene/rackaudit/app/Notifications/FindingReassignedNotification.php`
- Notification: `/Users/helderdene/rackaudit/app/Notifications/FindingStatusChangedNotification.php`
- Notification: `/Users/helderdene/rackaudit/app/Notifications/FindingDueDateApproachingNotification.php`
- Notification: `/Users/helderdene/rackaudit/app/Notifications/FindingOverdueNotification.php`
- Command: `/Users/helderdene/rackaudit/app/Console/Commands/SendFindingDueDateNotifications.php`
- Component: `/Users/helderdene/rackaudit/resources/js/Components/WorkflowProgressIndicator.vue`
- Component: `/Users/helderdene/rackaudit/resources/js/Components/QuickActionButtons.vue`
- Component: `/Users/helderdene/rackaudit/resources/js/Components/StatusTransitionTimeline.vue`
- Component: `/Users/helderdene/rackaudit/resources/js/Components/BulkActionToolbar.vue`
- Component: `/Users/helderdene/rackaudit/resources/js/Components/DueDateIndicator.vue`

### Performance Considerations
- Bulk operations use chunked processing (100 items per chunk) to prevent timeouts
- Status transitions query should be indexed on finding_id for timeline display
- Due date notification command should track which notifications have been sent
