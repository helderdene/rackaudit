# Specification: Implementation File Approval Workflow

## Goal
Add a two-state approval workflow for implementation files to ensure only reviewed and approved files can serve as authoritative sources for datacenter audits, with separation of duties enforced between uploaders and approvers.

## User Stories
- As an IT Manager or Administrator, I want to approve implementation files uploaded by team members so that only verified documents are used in audits
- As an Operator, I want to receive notifications when my uploaded files are approved so that I know they are ready for use in audits
- As an Auditor, I want to see which implementation files are approved so that I select the correct authoritative source when creating audits

## Specific Requirements

**Approval Status Field**
- Add `approval_status` enum field to ImplementationFile model with values: "pending_approval" and "approved"
- Newly uploaded files automatically set to "pending_approval" status
- Add `approved_by` foreign key to users table (nullable, set when approved)
- Add `approved_at` timestamp field (nullable, set when approved)
- When new versions are uploaded, they inherit "pending_approval" status (approval does not carry over)

**Approval Permission Logic**
- Only users with "Administrator" or "IT Manager" roles can approve files
- Use existing ADMIN_ROLES constant in ImplementationFilePolicy for consistency
- Add new `approve` method to ImplementationFilePolicy
- Separation of duties: users cannot approve files they uploaded (check `uploaded_by` against current user)
- Users must have datacenter access to approve files within that datacenter

**Approve Controller Action**
- Add `approve` method to ImplementationFileController
- Validate user has approval permission via Gate::authorize
- Update `approval_status` to "approved", set `approved_by` and `approved_at`
- Return JSON response with updated ImplementationFileResource
- Log approval action using existing Loggable concern

**Email Notifications**
- Create ImplementationFileAwaitingApprovalNotification for approvers
- Create ImplementationFileApprovedNotification for uploaders
- Send to all users with IT Manager or Administrator roles who have datacenter access when file uploaded
- Send to uploader when their file is approved
- Include file name, datacenter name, uploader name, and link to view file

**In-App Notifications**
- Create notifications table migration if not exists (use Laravel's standard notifications table)
- Store notifications using Laravel's database notification driver
- Display notification count badge in navigation header
- Create notification dropdown/panel showing recent notifications
- Mark notifications as read when viewed

**Implementation Files List UI Updates**
- Add approval status badge next to file name in ImplementationFileList component
- Use "warning" variant badge with text "Pending Approval" for pending files
- Use "success" variant badge with text "Approved" for approved files
- Add filter dropdown/tabs to filter by: "All", "Pending Approval", "Approved"
- Show approver name and approval date for approved files in expanded view or tooltip

**Approve Button UI**
- Add "Approve" button in file list actions column for users with approve permission
- Button only visible for files in "pending_approval" status
- Button disabled for files the current user uploaded (show tooltip explaining separation of duties)
- Show confirmation dialog before approving
- Display success toast notification after approval

**Audit Creation Warning**
- When creating an audit, check if selected datacenter has any approved implementation files
- If no approved files exist, display warning alert: "This datacenter has no approved implementation files. Audits require approved implementation files as the authoritative source for expected connections."
- Allow audit creation to proceed (warning only, not blocking)

## Visual Design
No visual mockups provided.

## Existing Code to Leverage

**ImplementationFile Model (`app/Models/ImplementationFile.php`)**
- Already uses Loggable concern for activity logging - approval action will be automatically logged
- Has relationship to uploader via `uploaded_by` field - use for separation of duties check
- Has datacenter relationship - use for datacenter access checks
- Extend fillable array to include new approval fields

**ImplementationFilePolicy (`app/Policies/ImplementationFilePolicy.php`)**
- Already defines ADMIN_ROLES constant as ['Administrator', 'IT Manager'] - reuse for approval permission
- Has hasDatacenterAccess helper method - reuse for approve permission check
- Follow existing pattern of combining role check with datacenter access check

**ImplementationFileResource (`app/Http/Resources/ImplementationFileResource.php`)**
- Extend to include approval_status, approved_by user info, and approved_at timestamp
- Follow existing pattern for conditionally loading relationships with whenLoaded

**Badge Component (`resources/js/components/ui/badge`)**
- Has "success" variant (green) for approved status
- Has "warning" variant (yellow) for pending approval status
- Already used in ImplementationFileList for version badges - follow same pattern

**User Model (`app/Models/User.php`)**
- Already uses Notifiable trait - supports both email and database notifications
- Already uses HasRoles from Spatie Permission - supports role checks for approver identification

## Out of Scope
- Bulk approval of multiple files at once
- Approval delegation to specific users outside of role-based permissions
- Approval expiration dates or automatic expiry
- Approval comments or notes attached to approval action
- "Rejected" status - only "Pending Approval" and "Approved" states
- Detailed approval history/audit trail beyond approved_by and approved_at fields
- Workflow for revoking or undoing approvals
- Email notification preferences or opt-out settings
- Push notifications (mobile or desktop browser)
- Approval request/reminder emails for stale pending files
