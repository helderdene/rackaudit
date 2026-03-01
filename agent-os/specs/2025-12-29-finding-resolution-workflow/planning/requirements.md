# Spec Requirements: Finding Resolution Workflow

## Initial Description
Finding Resolution Workflow — Process for assigning findings, tracking resolution progress, and closing with resolution notes

## Requirements Discussion

### First Round Questions

**Q1:** I see the Finding model already has status tracking (Open, In Progress, Pending Review, Deferred, Resolved), assignment capabilities (assigned_to), and resolution notes. I assume the workflow enhancement should focus on formalizing the transition process with better UX (quick status actions, assignment notifications, due dates for resolution) rather than rebuilding the data model. Is that correct, or are there missing status states or data fields you need?
**Answer:** Correct - focus on formalizing the transition process with better UX (quick status actions, assignment notifications, due dates) rather than rebuilding the data model.

**Q2:** The current status transition rules enforce a workflow path (Open -> In Progress -> Pending Review -> Resolved). I assume we should keep this flow but add visible workflow guidance in the UI (e.g., showing "Next Steps" or quick action buttons like "Start Working", "Submit for Review", "Approve & Close"). Should we also allow admins to skip steps, or keep the current behavior where admins can transition to any status?
**Answer:** Correct - add visible workflow guidance with quick action buttons. Keep current admin behavior where admins can transition to any status.

**Q3:** For bulk operations, I assume operators and managers should be able to assign multiple findings to a user at once, or change status of multiple findings together (e.g., "Defer all low-severity findings"). Is this a priority for this workflow, or should we focus on single-finding workflows first?
**Answer:** Correct - include bulk operations for assigning multiple findings and changing status of multiple findings together.

**Q4:** When a finding is assigned or reassigned, I assume the assignee should receive an in-app notification (using your existing notification system). Should we also send email notifications for assignments, status changes, or when findings are approaching a due date?
**Answer:** Correct, also send email notifications for assignments, status changes, and approaching due dates.

**Q5:** I assume findings can be assigned to any active user. Should we restrict assignment to specific roles (e.g., only Operators and Auditors can be assigned findings), or keep the current behavior of allowing any active user?
**Answer:** Allow any active user to be assigned findings.

**Q6:** When marking a finding as "Resolved", the current system requires resolution notes. I assume we should add validation to ensure resolution notes meet a minimum length or quality standard (e.g., at least 10 characters). Should we also require evidence attachments before allowing resolution, or keep that optional?
**Answer:** Correct - add validation for resolution notes. Evidence attachments remain optional.

**Q7:** For the "Pending Review" status, I assume a manager or IT admin reviews the resolution before final closure. Should we add a formal approval workflow with explicit "Approve" or "Reject" actions, or is the current status-based approach sufficient?
**Answer:** Correct - current status-based approach is sufficient.

**Q8:** Should we track resolution timeline metrics such as time from Open to In Progress, time in each status, and total resolution time? This would support future audit reporting and SLA tracking.
**Answer:** Correct - track resolution timeline metrics for audit reporting and SLA tracking.

**Q9:** Is there anything you explicitly want to exclude from this workflow? For example: automated finding creation, integration with external ticketing systems, or SLA enforcement with auto-escalation?
**Answer:** Exclude automated finding creation, external ticketing integration, and SLA enforcement with auto-escalation.

### Existing Code to Reference

No similar existing features identified for reference by the user. However, relevant existing code includes:
- Finding model: `app/Models/Finding.php`
- Finding controller: `app/Http/Controllers/FindingController.php`
- FindingStatus enum with transitions: `app/Enums/FindingStatus.php`
- Finding Vue pages: `resources/js/Pages/Findings/Index.vue`, `resources/js/Pages/Findings/Show.vue`
- Notification controller: `app/Http/Controllers/NotificationController.php`

### Follow-up Questions

No follow-up questions needed - all requirements are clear.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A - No visual files to analyze.

## Requirements Summary

### Functional Requirements

**Workflow UX Enhancements:**
- Add visible workflow guidance showing current status and available next steps
- Implement quick action buttons for common transitions:
  - "Start Working" (Open -> In Progress)
  - "Submit for Review" (In Progress -> Pending Review)
  - "Approve & Close" (Pending Review -> Resolved)
  - "Defer" (available from Open, In Progress)
  - "Reopen" (available from Deferred, Resolved for admins)
- Maintain existing admin override capability to transition to any status

**Assignment System:**
- Allow assignment of findings to any active user
- Support reassignment with tracking of assignment history
- Add due date field for resolution tracking

**Bulk Operations:**
- Bulk assign multiple findings to a single user
- Bulk status change for multiple selected findings
- Bulk defer functionality for low-priority findings

**Notification System:**
- In-app notifications for:
  - New finding assignment
  - Finding reassignment
  - Status changes on assigned findings
  - Approaching due dates
- Email notifications for the same events
- Configurable notification preferences (future consideration)

**Resolution Validation:**
- Require resolution notes with minimum length validation (e.g., 10+ characters)
- Keep evidence attachments optional
- Track who resolved and when (already exists)

**Timeline Metrics Tracking:**
- Track timestamps for each status transition
- Calculate and store:
  - Time from Open to In Progress (response time)
  - Time in each status
  - Total resolution time (Open to Resolved)
- Support future audit reporting and SLA visibility

### Reusability Opportunities

- Existing FindingStatus enum with `canTransitionTo()` method
- Existing notification infrastructure in NotificationController
- Existing form validation patterns in UpdateFindingRequest
- Existing evidence upload component (EvidenceUpload.vue)
- Existing category select component (CategorySelect.vue)

### Scope Boundaries

**In Scope:**
- Quick action buttons for status transitions
- Workflow guidance UI showing next steps
- Bulk assignment operations
- Bulk status change operations
- Due date field for findings
- In-app notifications for assignments and status changes
- Email notifications for assignments, status changes, and approaching due dates
- Resolution notes validation (minimum length)
- Status transition timestamp tracking
- Resolution timeline metrics storage

**Out of Scope:**
- Automated finding creation
- Integration with external ticketing systems (Jira, ServiceNow, etc.)
- SLA enforcement with auto-escalation
- Automated assignment rules
- Approval workflow beyond current status-based approach
- Required evidence attachments for resolution

### Technical Considerations

- Database migration needed for:
  - `due_date` column on findings table
  - Status transition history table for timeline metrics
- Notification infrastructure leverages existing Laravel notification system
- Email notifications require queue configuration for performance
- Bulk operations should handle large selections efficiently (chunked processing)
- Frontend components should use existing UI patterns (Button, Card, etc.)
- Timeline metrics should be calculated on-demand or cached for performance
