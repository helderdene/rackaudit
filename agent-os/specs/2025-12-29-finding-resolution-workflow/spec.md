# Specification: Finding Resolution Workflow

## Goal
Formalize the finding resolution process with enhanced UX including quick action buttons for status transitions, bulk operations for assignment and status changes, in-app and email notifications, due date tracking, and resolution timeline metrics.

## User Stories
- As an operator, I want quick action buttons to transition findings through the workflow so that I can efficiently manage my assigned findings without navigating complex forms.
- As a manager, I want to bulk assign findings and change statuses for multiple items so that I can efficiently triage and delegate work across my team.
- As an assignee, I want to receive notifications when findings are assigned to me or approaching due dates so that I can prioritize my work appropriately.

## Specific Requirements

**Quick Action Buttons for Status Transitions**
- Display contextual action buttons on Finding Show page based on current status and allowed transitions
- "Start Working" button: transitions Open to In Progress
- "Submit for Review" button: transitions In Progress to Pending Review
- "Approve & Close" button: transitions Pending Review to Resolved (requires resolution notes)
- "Defer" button: available from Open and In Progress states
- "Reopen" button: available from Deferred and Resolved (admins only for Resolved)
- Buttons should be styled distinctly using existing Button component variants

**Workflow Guidance UI**
- Display current status prominently with a visual workflow progress indicator
- Show "Next Steps" guidance text below status indicating what actions are available
- Highlight the recommended next action (e.g., "Ready to submit? Click Submit for Review")
- Keep existing admin override capability to transition to any status via dropdown

**Due Date Field**
- Add `due_date` nullable date column to findings table
- Display due date picker in Finding edit form using existing date input patterns
- Show due date on Finding Show page and Index list view
- Visual indicator when finding is overdue (past due date) or approaching (within 3 days)
- Filter findings by "Overdue", "Due Soon", "No Due Date" in Index view

**Bulk Operations on Index Page**
- Add checkbox selection column to findings table
- "Select All" checkbox in header for current page
- Bulk action toolbar appears when items are selected
- "Assign to..." action: opens user selector dropdown, assigns all selected to chosen user
- "Change Status to..." action: opens status selector, applies to all selected (respects workflow rules)
- "Defer All" quick action for bulk deferral
- Chunked processing for large selections (100+ items) to prevent timeouts

**In-App Notifications**
- Notify assignee when a finding is assigned or reassigned to them
- Notify previous assignee when finding is reassigned away from them
- Notify assignee when status changes on their assigned findings
- Notify assignee 3 days before due date (approaching due date)
- Notify assignee when finding becomes overdue
- Use existing DatabaseNotification infrastructure and NotificationController patterns

**Email Notifications**
- Send email for same events as in-app notifications
- Use existing mail notification pattern with ShouldQueue interface
- Include finding title, audit name, datacenter, and direct link to finding
- Only send when mail driver is configured (not log/array)

**Resolution Notes Validation**
- Require minimum 10 characters for resolution notes when resolving
- Update UpdateFindingRequest validation to enforce minimum length
- Display character count and minimum requirement hint in textarea
- Existing resolution notes that don't meet minimum are grandfathered (only enforce on new resolutions)

**Status Transition Timeline Tracking**
- Create `finding_status_transitions` table to log all status changes
- Store: finding_id, from_status, to_status, user_id, transitioned_at timestamp, notes (optional)
- Calculate and display metrics: time to first response (Open to In Progress), time in each status, total resolution time
- Show timeline on Finding Show page as a simple list of transitions with timestamps
- Support future audit reporting and SLA visibility

## Visual Design
No visual mockups provided. UI should follow existing patterns from Findings/Index.vue and Findings/Show.vue pages.

**Index Page Enhancements**
- Add checkbox column as first column in table
- Add due date column after Status column
- Bulk action toolbar appears above table when items selected
- Overdue findings: red text or icon indicator on due date
- Due soon findings: yellow/amber indicator on due date

**Show Page Enhancements**
- Quick action buttons displayed prominently below header badges
- Workflow progress indicator showing: Open > In Progress > Pending Review > Resolved
- Due date field in edit form with date picker
- Timeline section showing status transition history
- Resolution notes textarea with character count

## Existing Code to Leverage

**FindingStatus Enum (app/Enums/FindingStatus.php)**
- Already has `canTransitionTo()` method defining valid workflow transitions
- Has `label()` and `color()` methods for display
- Use this logic for quick action button visibility and validation

**UpdateFindingRequest (app/Http/Requests/UpdateFindingRequest.php)**
- Already validates status transitions via `validateStatusTransition()` method
- Already validates resolution notes required for resolved status
- Extend to add minimum length validation for resolution notes

**NotificationController (app/Http/Controllers/NotificationController.php)**
- Existing infrastructure for fetching, formatting, and marking notifications read
- Has `formatNotification()` method pattern for consistent notification display
- Add new notification types to `getNotificationType()` and `buildMessage()` methods

**NewDiscrepancyNotification (app/Notifications/NewDiscrepancyNotification.php)**
- Pattern for creating notifications with both database and mail channels
- Shows mail driver check to only send when properly configured
- Use as template for new finding notification classes

**Findings Vue Pages (resources/js/Pages/Findings/)**
- Index.vue has filter patterns, table structure, and pagination
- Show.vue has form patterns, Card components, and edit capabilities
- Use existing Button, Card, Input components from ui directory

## Out of Scope
- Automated finding creation from audit results
- Integration with external ticketing systems (Jira, ServiceNow, etc.)
- SLA enforcement with auto-escalation (only tracking, no enforcement)
- Automated assignment rules based on category or severity
- Approval workflow beyond current status-based approach (no formal approve/reject actions)
- Required evidence attachments for resolution (keep optional)
- Notification preferences configuration UI (future consideration)
- Bulk edit of other fields (severity, category) - only assignment and status
- Finding comments or discussion threads
- Finding templates or duplication
