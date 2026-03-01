# Specification: Finding Management

## Goal
Build a comprehensive CRUD interface for managing audit findings, enabling users to track, categorize, assign, and resolve discrepancies discovered during datacenter audits.

## User Stories
- As an IT Manager, I want to view and filter all findings across audits so that I can prioritize resolution efforts based on severity and status
- As an Operator, I want to update finding status and add resolution notes so that I can document the steps taken to resolve issues

## Specific Requirements

**Findings List Page**
- Create `/findings` route with paginated list of all findings
- Include search by title/description and filters for status, severity, category, audit, and assignee
- Display severity with color-coded badges (Critical=red, High=orange, Medium=yellow, Low=blue)
- Show audit name as a link to the parent audit
- Mobile card view and desktop table view following Audits/Index.vue pattern

**Finding Detail/Edit Page**
- Display all finding properties including title, description, severity, category, status
- Show associated audit information with link to audit
- Display evidence attachments and text notes
- Show assignee information and resolution notes
- Include edit functionality for authorized users (status, severity, category, assignee, resolution notes)

**Severity System**
- Add `severity` column to findings table with enum: Critical, High, Medium, Low
- Create FindingSeverity enum at `app/Enums/FindingSeverity.php`
- Default new findings to Medium severity
- Add severity filter and sorting to findings list

**Category System**
- Create `finding_categories` table with: id, name, description, is_default (boolean), timestamps
- Seed default categories from DiscrepancyType values: Missing, Unexpected, Mismatched, Conflicting, Configuration Mismatch
- Add `finding_category_id` nullable foreign key to findings table
- Users can create custom categories (stored with is_default=false)
- Category management inline on finding edit (select existing or create new)

**Evidence Management**
- Create `finding_evidence` table with: id, finding_id, type (enum: file, text), content (text for notes), file_path (for uploads), original_filename, mime_type, timestamps
- Support file uploads via Laravel Storage (local/S3)
- Accept common file types: images (jpg, png, gif), PDFs, documents (doc, docx)
- Max file size: 10MB per file
- Evidence add/remove functionality on finding detail page

**Status Workflow**
- Extend FindingStatus enum with: Open, InProgress, PendingReview, Deferred, Resolved
- Add status transition validation (e.g., cannot go from Resolved back to Open without admin)
- Track `resolved_at` timestamp when status changes to Resolved
- Require resolution notes when marking as Resolved

**Assignment System**
- Add `assigned_to` nullable foreign key to findings table referencing users
- Assigned user can update status and add resolution notes
- Show assignee avatar/name on findings list and detail pages
- Filter findings by assignee on list page

**Navigation Integration**
- Add "Findings" item to AppSidebar.vue navigation after "Audits"
- Use Search/AlertCircle icon from lucide-vue-next
- Route to `/findings` (FindingController.index)

## Visual Design
No visual assets provided - follow existing UI patterns from Audits/Index.vue and Datacenters/Show.vue for consistent styling.

## Existing Code to Leverage

**Finding Model (`app/Models/Finding.php`)**
- Already has audit, verification, deviceVerification, and resolvedBy relationships
- Needs enhancement: add assignee, category, evidence relationships
- Needs scopes for filtering by status, severity, category, assignee

**FindingStatus Enum (`app/Enums/FindingStatus.php`)**
- Currently has Open and Resolved cases
- Extend with InProgress, PendingReview, Deferred cases
- Add color() method for badge styling

**DiscrepancyType Enum (`app/Enums/DiscrepancyType.php`)**
- Use values as seeds for default FindingCategory records
- Reference for category label formatting

**Audits/Index.vue**
- Reuse list page structure: filters, search, mobile cards, desktop table, pagination
- Copy badge styling patterns for status and severity
- Follow same debounced search and filter watching pattern

**AuditController.php**
- Follow controller structure for index method with filters, pagination, through() transformation
- Reference for role-based access control patterns
- Pattern for preparing select options from enums

## Out of Scope
- Email notifications for finding status changes or assignments
- Bulk operations (bulk status update, bulk assignment, bulk delete)
- Export to PDF functionality
- Manual finding creation outside of audit context (findings are auto-generated during audits)
- Finding templates or recurring findings
- Finding comments/discussion threads
- Finding history/changelog beyond status transitions
- Dashboard widgets or analytics for findings
- SLA tracking or due dates for findings
- Finding severity auto-assignment based on discrepancy type
