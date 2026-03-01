# Spec Requirements: Implementation File Approval Workflow

## Initial Description

Implementation File Approval Workflow — Approval process for implementation files before they become authoritative for audits

## Requirements Discussion

### First Round Questions

**Q1:** I assume the approval workflow will follow a simple two-state model where files are either "Pending Approval" or "Approved" (and only approved files can be used as authoritative sources for audits). Is that correct, or do you need additional states like "Rejected", "Needs Revision", or "Superseded"?
**Answer:** Correct - simple two-state model (Pending Approval / Approved)

**Q2:** I'm thinking the approval authority should be role-based, where IT Managers and Administrators can approve files, while Operators can only upload and request approval. Should we follow this hierarchy, or do you need a more flexible approver assignment (e.g., specific users designated per datacenter)?
**Answer:** Correct - role-based (IT Managers and Administrators can approve, Operators can only upload)

**Q3:** I assume newly uploaded files should automatically enter "Pending Approval" status and the uploader cannot approve their own files (separation of duties). Is this correct, or should self-approval be allowed for certain roles like Administrators?
**Answer:** Correct - uploaders cannot approve their own files (separation of duties)

**Q4:** When a new version of an already-approved file is uploaded, I'm thinking the new version should require its own separate approval (the previous version's approval doesn't carry over). Should we also automatically revoke/supersede the previous version's approval status, or keep both versions independently approved?
**Answer:** Correct - new versions require separate approval

**Q5:** I assume approvers should receive an email notification when a file is uploaded and awaiting their approval, and uploaders should be notified when their file is approved or rejected. Is email notification sufficient, or do you also need in-app notifications?
**Answer:** Email AND in-app notifications (both needed)

**Q6:** I'm thinking the implementation files list should show the approval status prominently (badge/icon) and include filters to view "Pending Approval", "Approved", or "All" files. Should the list default to showing only approved files, or show all files with status indicators?
**Answer:** Correct - show approval status prominently with filters

**Q7:** When creating or running an audit, I assume the system should only allow selection of "Approved" implementation files as the authoritative source for expected connections. Should we also display a warning if the datacenter has no approved implementation files, or block audit creation entirely?
**Answer:** Correct - display a warning if the datacenter has no approved implementation files

**Q8:** Is there anything specific you want to explicitly exclude from this feature (e.g., bulk approval, approval delegation, approval expiration dates, approval comments/notes)?
**Answer:** Exclude all suggested features (bulk approval, approval delegation, approval expiration dates, approval comments/notes)

### Existing Code to Reference

No similar existing features identified for reference.

### Follow-up Questions

No follow-up questions needed - requirements are clear and complete.

## Visual Assets

### Files Provided:

No visual assets provided.

### Visual Insights:

N/A

## Requirements Summary

### Functional Requirements

- Two-state approval workflow: "Pending Approval" and "Approved"
- Only approved implementation files can be used as authoritative sources for audits
- Role-based approval authority: IT Managers and Administrators can approve files
- Operators can upload files but cannot approve them
- Separation of duties: uploaders cannot approve their own files (regardless of role)
- Newly uploaded files automatically enter "Pending Approval" status
- New versions of files require separate approval (approval does not carry over from previous versions)
- Email notifications for:
  - Approvers when a file is uploaded and awaiting approval
  - Uploaders when their file is approved
- In-app notifications for:
  - Approvers when a file is uploaded and awaiting approval
  - Uploaders when their file is approved
- Implementation files list displays approval status prominently (badge/icon)
- Filter options to view "Pending Approval", "Approved", or "All" files
- Warning displayed when creating an audit if datacenter has no approved implementation files

### Reusability Opportunities

- Existing ImplementationFile model at `app/Models/ImplementationFile.php`
- Existing ImplementationFileController at `app/Http/Controllers/ImplementationFileController.php`
- Existing ImplementationFilePolicy at `app/Policies/ImplementationFilePolicy.php`
- Existing ImplementationFileResource at `app/Http/Resources/ImplementationFileResource.php`
- Existing role-based access control via Spatie Laravel-Permission
- Existing activity logging infrastructure (Loggable concern already on ImplementationFile model)

### Scope Boundaries

**In Scope:**
- Add approval status field to ImplementationFile model
- Add approved_by and approved_at fields to track who approved and when
- Create approve action in ImplementationFileController
- Update ImplementationFilePolicy with approve permission
- Role-based permission checks for approval (IT Managers, Administrators)
- Separation of duties check (uploader cannot approve own file)
- Email notification system for approval workflow
- In-app notification system for approval workflow
- UI updates to show approval status badges/icons
- Filter controls for approval status in file list
- Warning message when creating audits without approved files

**Out of Scope:**
- Bulk approval of multiple files at once
- Approval delegation to specific users
- Approval expiration dates
- Approval comments or notes
- Rejected state (only Pending Approval and Approved)
- Approval history/audit trail beyond the approved_by and approved_at fields

### Technical Considerations

- Integration with existing Spatie Laravel-Permission for role checks
- Email notifications via Laravel's built-in mail system
- In-app notifications via Laravel's notification system (database driver)
- Migration to add approval-related fields to implementation_files table
- Update existing Vue components for implementation files list
- Activity logging for approval actions (already using Loggable concern)
