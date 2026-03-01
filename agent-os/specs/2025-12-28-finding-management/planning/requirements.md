# Spec Requirements: Finding Management

## Initial Description
**Finding Management** — CRUD for audit findings with severity, category, description, evidence, and status tracking

## Requirements Discussion

### First Round Questions

**Q1:** I see the Finding model already exists with basic fields (title, description, discrepancy_type, status). I assume we're building a full CRUD interface for findings that are currently auto-generated during audits. Is that correct, or should findings also be manually creatable independent of audits?
**Answer:** Correct - building full CRUD interface for findings that are auto-generated during audits

**Q2:** For severity levels, I'm thinking of adding Critical, High, Medium, Low tiers. Should we use these standard severity levels, or do you have a specific classification in mind (e.g., numeric 1-5 scale, or custom labels)?
**Answer:** Correct - use Critical, High, Medium, Low tiers

**Q3:** For categories, I assume these should match the existing DiscrepancyType values (Missing, Unexpected, Mismatched, Conflicting, Configuration Mismatch) plus potentially new categories. Should categories be predefined options, or should users be able to create custom categories?
**Answer:** Users should be able to create custom categories WITH predefined options (so both predefined defaults + custom user-created categories)

**Q4:** For evidence, I'm thinking findings could support file attachments (photos of cable issues, screenshots) and text notes. Should evidence support both file uploads and text descriptions, or just one type?
**Answer:** Correct - support both file uploads AND text descriptions

**Q5:** For status tracking, the current model has Open and Resolved. Should we add intermediate statuses like "In Progress", "Pending Review", "Deferred", or is a simple Open/Resolved workflow sufficient?
**Answer:** Correct - add intermediate statuses (In Progress, Pending Review, Deferred) beyond Open/Resolved

**Q6:** Regarding assignments, should findings be assignable to specific users for resolution (similar to how audits have assignees), with the assigned user able to update status and add resolution notes?
**Answer:** Correct - findings should be assignable to users for resolution with ability to update status and add resolution notes

**Q7:** For the UI location, should findings have their own dedicated section in the navigation, or should they only be viewable within the context of a specific audit (on the Audit Show/Execute pages)?
**Answer:** Own dedicated section in the navigation (not just within audit context)

**Q8:** Is there anything that should explicitly be OUT of scope for this feature (e.g., email notifications, bulk operations, export to PDF)?
**Answer:** Exclude email notifications, bulk operations, and export to PDF

### Existing Code to Reference
No similar existing features identified for reference.

### Follow-up Questions
None required - user provided comprehensive answers to all questions.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A

## Requirements Summary

### Functional Requirements

**Core CRUD Operations:**
- List all findings across the system with filtering and sorting capabilities
- View individual finding details with all associated information
- Edit finding properties (severity, category, description, status, assignee)
- Delete findings (with appropriate confirmation)
- Findings are auto-generated during audits (no manual creation outside audit context)

**Severity Tracking:**
- Four-tier severity classification: Critical, High, Medium, Low
- Visual indicators for severity levels in list and detail views
- Ability to filter/sort findings by severity

**Category Management:**
- Predefined default categories based on existing DiscrepancyType values:
  - Missing
  - Unexpected
  - Mismatched
  - Conflicting
  - Configuration Mismatch
- User-creatable custom categories (new FindingCategory model/table)
- Category assignment when editing findings

**Evidence Management:**
- File attachments support (photos, screenshots, documents)
- Text-based evidence notes/descriptions
- Multiple evidence items per finding
- Ability to add/remove evidence on existing findings

**Status Workflow:**
- Extended status options:
  - Open (default for new findings)
  - In Progress
  - Pending Review
  - Deferred
  - Resolved
- Status change tracking with timestamps
- Resolution notes when closing findings

**Assignment System:**
- Assign findings to specific users for resolution
- Assigned user can update status and add resolution notes
- Track who resolved the finding and when

**Navigation & UI:**
- Dedicated "Findings" section in main navigation
- Findings list page with:
  - Filtering by status, severity, category, audit, assignee
  - Sorting options
  - Search functionality
- Finding detail page showing:
  - All finding properties
  - Associated audit information
  - Evidence attachments and notes
  - Status history
  - Resolution information

### Reusability Opportunities
- Existing Finding model at `app/Models/Finding.php` (needs enhancement)
- Existing FindingStatus enum at `app/Enums/FindingStatus.php` (needs additional statuses)
- Existing DiscrepancyType enum for default categories
- Audit pages for UI patterns reference (`resources/js/Pages/Audits/`)
- Standard CRUD patterns from other modules (Datacenter, Room, Rack management)

### Scope Boundaries

**In Scope:**
- Full CRUD interface for findings
- Severity levels (Critical, High, Medium, Low)
- Custom and predefined categories
- File upload and text evidence support
- Extended status workflow (Open, In Progress, Pending Review, Deferred, Resolved)
- User assignment for resolution
- Dedicated navigation section and pages
- Filtering, sorting, and search on findings list

**Out of Scope:**
- Email notifications for finding status changes
- Bulk operations (bulk status update, bulk assignment, bulk delete)
- Export to PDF functionality
- Manual finding creation outside of audit context
- Finding templates or recurring findings

### Technical Considerations

**Database Changes Required:**
- Add `severity` column to findings table
- Add `assigned_to` foreign key to findings table
- Add `resolution_notes` text column to findings table
- Create `finding_categories` table for custom categories
- Add `finding_category_id` foreign key to findings table
- Create `finding_evidence` table for attachments and notes
- Update FindingStatus enum with additional statuses

**File Storage:**
- Evidence file uploads via Laravel Storage (S3 compatible)
- Support for common file types (images, PDFs, documents)

**Existing Model Enhancement:**
- Enhance existing Finding model with new relationships and attributes
- Add relationships: assignee, category, evidence items
- Add scopes for filtering by status, severity, category

**API/Controller Structure:**
- FindingController for web routes (Inertia pages)
- Potentially API endpoints for AJAX operations (status updates, evidence management)

**Frontend Components:**
- Finding list page with data table
- Finding detail/edit page
- Evidence upload component
- Category management (inline or separate page)
- Status change workflow component
