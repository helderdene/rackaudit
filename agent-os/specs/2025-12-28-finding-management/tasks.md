# Task Breakdown: Finding Management

## Overview
Total Tasks: 33 (across 5 task groups)

This feature builds a comprehensive CRUD interface for managing audit findings, enabling users to track, categorize, assign, and resolve discrepancies discovered during datacenter audits.

## Task List

### Database Layer

#### Task Group 1: Data Models and Migrations
**Dependencies:** None

- [x] 1.0 Complete database layer for finding management
  - [x] 1.1 Write 6 focused tests for Finding model enhancements and new models
    - Test FindingSeverity enum values and color() method
    - Test extended FindingStatus enum with new statuses and color() method
    - Test FindingCategory model basic CRUD operations
    - Test FindingEvidence model with file and text types
    - Test Finding model new relationships (assignee, category, evidence)
    - Test Finding model scopes (filterByStatus, filterBySeverity, filterByCategory, filterByAssignee)
  - [x] 1.2 Create FindingSeverity enum at `app/Enums/FindingSeverity.php`
    - Cases: Critical, High, Medium, Low
    - Add label() method returning human-readable strings
    - Add color() method returning badge color classes (Critical=red, High=orange, Medium=yellow, Low=blue)
  - [x] 1.3 Extend FindingStatus enum at `app/Enums/FindingStatus.php`
    - Add new cases: InProgress, PendingReview, Deferred
    - Update label() method with new status labels
    - Add color() method for badge styling
    - Add canTransitionTo() method for status workflow validation
  - [x] 1.4 Create migration to add severity and assignment columns to findings table
    - Add `severity` column with enum values, default to 'medium'
    - Add `assigned_to` nullable foreign key referencing users table
    - Add `finding_category_id` nullable foreign key (to be created next)
    - Add `resolution_notes` text column (nullable)
    - Add indexes on severity, assigned_to, finding_category_id, status
  - [x] 1.5 Create FindingCategory model and migration
    - Create `finding_categories` table with: id, name, description, is_default (boolean), timestamps
    - Add unique constraint on name
    - Create FindingCategory model at `app/Models/FindingCategory.php`
    - Define fillable attributes and basic methods
  - [x] 1.6 Create database seeder for default FindingCategory records
    - Seed from DiscrepancyType values: Missing, Unexpected, Mismatched, Conflicting, Configuration Mismatch
    - Set is_default=true for seeded categories
    - Create `database/seeders/FindingCategorySeeder.php`
  - [x] 1.7 Create FindingEvidence model and migration
    - Create `finding_evidence` table with: id, finding_id (FK), type (enum: file, text), content (text), file_path (nullable), original_filename (nullable), mime_type (nullable), timestamps
    - Create FindingEvidence model at `app/Models/FindingEvidence.php`
    - Add EvidenceType enum at `app/Enums/EvidenceType.php` with File and Text cases
  - [x] 1.8 Enhance Finding model with new relationships and scopes
    - Add assignee relationship (belongsTo User)
    - Add category relationship (belongsTo FindingCategory)
    - Add evidence relationship (hasMany FindingEvidence)
    - Add severity cast to FindingSeverity enum
    - Update fillable array with new columns
    - Add filter scopes: scopeFilterByStatus, scopeFilterBySeverity, scopeFilterByCategory, scopeFilterByAssignee
    - Add scopeSearchByTitleOrDescription for search functionality
  - [x] 1.9 Create FindingFactory and update existing factory if needed
    - Update `database/factories/FindingFactory.php` with severity, assigned_to, finding_category_id
    - Create `database/factories/FindingCategoryFactory.php`
    - Create `database/factories/FindingEvidenceFactory.php`
  - [x] 1.10 Ensure database layer tests pass
    - Run ONLY the 6 tests written in 1.1
    - Verify migrations run successfully with `php artisan migrate:fresh --seed`
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6 tests written in 1.1 pass
- All migrations run without errors
- FindingSeverity and extended FindingStatus enums work correctly
- FindingCategory and FindingEvidence models are created with proper relationships
- Finding model enhanced with new relationships and scopes
- Default categories are seeded successfully

---

### API Layer

#### Task Group 2: Finding Controller and API Endpoints
**Dependencies:** Task Group 1

- [x] 2.0 Complete API layer for finding management
  - [x] 2.1 Write 6 focused tests for FindingController
    - Test index action returns paginated findings with filters applied
    - Test show action returns finding with all relationships loaded
    - Test update action successfully updates finding properties
    - Test update action validates required resolution_notes when status changes to Resolved
    - Test authorization: only assigned user, admin, or IT Manager can update finding
    - Test status transition validation (cannot skip workflow steps without admin)
  - [x] 2.2 Create UpdateFindingRequest form request class
    - Create `app/Http/Requests/UpdateFindingRequest.php`
    - Validate status (must be valid FindingStatus value)
    - Validate severity (must be valid FindingSeverity value)
    - Validate assigned_to (must exist in users table, nullable)
    - Validate finding_category_id (must exist in finding_categories table, nullable)
    - Validate resolution_notes (required when status is Resolved)
    - Add authorization logic: user must be admin, IT Manager, or assigned user
  - [x] 2.3 Create FindingController with index, show, update actions
    - Create `app/Http/Controllers/FindingController.php`
    - Follow AuditController patterns for index method structure
    - index: paginated list with filters (status, severity, category, audit_id, assigned_to, search)
    - show: single finding with audit, assignee, category, evidence relationships
    - update: update finding properties with status workflow validation
    - Transform data using through() pattern from AuditController
  - [x] 2.4 Implement role-based access control in FindingController
    - Admin and IT Manager see all findings
    - Operators and Auditors see only findings from audits they are assigned to
    - Use existing ADMIN_ROLES pattern from AuditController
  - [x] 2.5 Create routes for finding management
    - Add routes to `routes/web.php` under authenticated group
    - GET /findings -> FindingController@index (findings.index)
    - GET /findings/{finding} -> FindingController@show (findings.show)
    - PUT /findings/{finding} -> FindingController@update (findings.update)
  - [x] 2.6 Generate Wayfinder actions for FindingController
    - Run `php artisan wayfinder:generate` to create TypeScript functions
    - Verify actions generated at `resources/js/actions/App/Http/Controllers/FindingController.ts`
  - [x] 2.7 Ensure API layer tests pass
    - Run ONLY the 6 tests written in 2.1
    - Verify routes are registered with `php artisan route:list --name=findings`
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6 tests written in 2.1 pass
- FindingController index returns paginated, filtered results
- FindingController show returns complete finding data
- FindingController update enforces authorization and validation
- Status workflow transitions are validated
- Routes are properly registered

---

#### Task Group 3: Evidence Management API
**Dependencies:** Task Group 2

- [x] 3.0 Complete evidence management API
  - [x] 3.1 Write 4 focused tests for evidence management
    - Test file upload creates FindingEvidence record with correct file_path
    - Test text note creation creates FindingEvidence record with content
    - Test evidence deletion removes record and file from storage
    - Test file validation rejects invalid file types and oversized files
  - [x] 3.2 Create StoreEvidenceRequest form request class
    - Create `app/Http/Requests/StoreEvidenceRequest.php`
    - Validate type (required, must be 'file' or 'text')
    - Validate file (required if type=file, max 10MB, allowed mimetypes: jpg, jpeg, png, gif, pdf, doc, docx)
    - Validate content (required if type=text, max 10000 characters)
    - Add authorization: user must be admin, IT Manager, or assigned to parent finding
  - [x] 3.3 Create FindingEvidenceController
    - Create `app/Http/Controllers/FindingEvidenceController.php`
    - store: handle both file uploads and text notes
    - destroy: remove evidence record and delete file from storage
    - Use Laravel Storage facade with configurable disk (local/s3)
    - Store files in `finding-evidence/{finding_id}/` directory
  - [x] 3.4 Add evidence management routes
    - POST /findings/{finding}/evidence -> FindingEvidenceController@store (findings.evidence.store)
    - DELETE /findings/{finding}/evidence/{evidence} -> FindingEvidenceController@destroy (findings.evidence.destroy)
  - [x] 3.5 Generate Wayfinder actions for FindingEvidenceController
    - Run `php artisan wayfinder:generate`
    - Verify actions generated for evidence endpoints
  - [x] 3.6 Ensure evidence API tests pass
    - Run ONLY the 4 tests written in 3.1
    - Verify file upload and deletion work correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4 tests written in 3.1 pass
- File uploads stored correctly with proper naming
- Text evidence stored with content
- File type and size validation enforced
- Evidence deletion removes files from storage

---

#### Task Group 4: Category Management API
**Dependencies:** Task Group 2

- [x] 4.0 Complete category management API
  - [x] 4.1 Write 3 focused tests for category management
    - Test store action creates new category with is_default=false
    - Test store validation rejects duplicate category names
    - Test index action returns all categories (defaults first, then custom sorted alphabetically)
  - [x] 4.2 Create FindingCategoryController
    - Create `app/Http/Controllers/FindingCategoryController.php`
    - index: return all categories for select dropdown
    - store: create new custom category (is_default=false)
    - Follow existing controller patterns
  - [x] 4.3 Create StoreFindingCategoryRequest form request class
    - Create `app/Http/Requests/StoreFindingCategoryRequest.php`
    - Validate name (required, unique, max 255)
    - Validate description (nullable, max 1000)
    - Authorization: any authenticated user can create categories
  - [x] 4.4 Add category management routes
    - GET /finding-categories -> FindingCategoryController@index (finding-categories.index)
    - POST /finding-categories -> FindingCategoryController@store (finding-categories.store)
  - [x] 4.5 Generate Wayfinder actions for FindingCategoryController
    - Run `php artisan wayfinder:generate`
    - Verify actions generated for category endpoints
  - [x] 4.6 Ensure category API tests pass
    - Run ONLY the 3 tests written in 4.1
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 3 tests written in 4.1 pass
- Categories can be created with validation
- Duplicate names are rejected
- Categories returned with defaults first

---

### Frontend Components

#### Task Group 5: Frontend UI Implementation
**Dependencies:** Task Groups 2, 3, 4

- [x] 5.0 Complete frontend UI for finding management
  - [x] 5.1 Write 5 focused tests for UI components
    - Test Findings/Index.vue renders paginated list correctly
    - Test Findings/Index.vue filters update URL and reload data
    - Test Findings/Show.vue displays all finding details
    - Test Findings/Show.vue edit form submits correctly
    - Test evidence upload component handles file selection and upload
  - [x] 5.2 Add "Findings" navigation item to AppSidebar.vue
    - Add navigation item after "Audits"
    - Use AlertCircle icon from lucide-vue-next
    - Route to `/findings` using FindingController.index
    - Match existing navigation item styling
  - [x] 5.3 Create TypeScript interfaces for Finding data
    - Create types in `resources/js/types/finding.ts` or appropriate location
    - Define FindingData interface with all properties
    - Define FindingEvidence interface
    - Define FindingCategory interface
    - Define filter and pagination interfaces following Audits/Index.vue pattern
  - [x] 5.4 Create Findings/Index.vue list page
    - Follow Audits/Index.vue structure and patterns
    - Include search by title/description (debounced)
    - Add filters: status, severity, category, audit, assignee
    - Display severity with color-coded badges (use FindingSeverity colors)
    - Display status with color-coded badges (use FindingStatus colors)
    - Show audit name as link to parent audit
    - Mobile card view with key information
    - Desktop table view with all columns
    - Pagination component following existing pattern
    - Clear filters button when filters are active
  - [x] 5.5 Create severity and status badge helper functions
    - Create getSeverityBadgeClass() function matching spec colors
    - Create getStatusBadgeClass() function for extended statuses
    - Follow getStatusBadgeClass pattern from Audits/Index.vue
    - Support dark mode variants
  - [x] 5.6 Create Findings/Show.vue detail/edit page
    - Display all finding properties (title, description, severity, status, category)
    - Show associated audit information with link to audit
    - Show assignee avatar/name or "Unassigned"
    - Show resolution notes if present
    - Display evidence section with file downloads and text notes
    - Include edit functionality with form validation
    - Status dropdown with allowed transitions based on current status
    - Severity dropdown
    - Category dropdown with "Create new" option
    - Assignee dropdown
    - Resolution notes textarea (required when status = Resolved)
    - Save button with loading state
  - [x] 5.7 Create EvidenceUpload component
    - Create `resources/js/Components/EvidenceUpload.vue`
    - File drop zone with click-to-browse
    - Show accepted file types and max size
    - File preview before upload
    - Upload progress indicator
    - Text note input alternative
    - List existing evidence with download/delete actions
    - Confirmation dialog for delete
  - [x] 5.8 Create CategorySelect component with inline creation
    - Create `resources/js/Components/CategorySelect.vue` or use existing select patterns
    - Dropdown with existing categories
    - "Create new category" option at bottom
    - Inline form for new category name
    - Submit creates category and selects it
    - Error handling for duplicate names
  - [x] 5.9 Apply responsive design to all pages
    - Mobile: stack filters vertically, use card view for list
    - Tablet: responsive breakpoints for filter layout
    - Desktop: table view with horizontal filters
    - Match Audits/Index.vue responsive patterns exactly
  - [x] 5.10 Implement loading and empty states
    - Loading skeleton while fetching data (following existing patterns)
    - Empty state with helpful message when no findings
    - Empty state for no evidence on a finding
    - Deferred props handling if applicable
  - [x] 5.11 Ensure UI component tests pass
    - Run ONLY the 5 tests written in 5.1
    - Verify pages render without JavaScript errors
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 5 tests written in 5.1 pass
- Navigation shows "Findings" link
- List page displays filtered, paginated findings
- Detail page shows all finding information
- Edit form validates and submits correctly
- Evidence upload/download/delete works
- Category creation inline works
- Responsive design matches existing patterns
- Dark mode supported

---

### Testing

#### Task Group 6: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-5

- [x] 6.0 Review existing tests and fill critical gaps only
  - [x] 6.1 Review tests from Task Groups 1-5
    - Review the 6 tests written by database-engineer (Task 1.1)
    - Review the 6 tests written by api-engineer (Task 2.1)
    - Review the 4 tests written by evidence-api-engineer (Task 3.1)
    - Review the 3 tests written by category-api-engineer (Task 4.1)
    - Review the 5 tests written by ui-designer (Task 5.1)
    - Total existing tests: 24 tests
  - [x] 6.2 Analyze test coverage gaps for Finding Management feature only
    - Identify critical user workflows that lack test coverage
    - Focus ONLY on gaps related to this spec's feature requirements
    - Do NOT assess entire application test coverage
    - Prioritize end-to-end workflows over unit test gaps
  - [x] 6.3 Write up to 8 additional strategic tests maximum
    - Add maximum of 8 new tests to fill identified critical gaps
    - Suggested gap areas (prioritize based on analysis):
      - End-to-end: Complete finding workflow from open to resolved
      - Integration: Finding creation during audit execution (verify existing behavior)
      - Status transition: Validate all workflow paths
      - Permission boundary: Non-assigned user cannot update finding
      - File storage: Evidence file is accessible after upload
      - Search: Full-text search returns expected results
    - Do NOT write comprehensive coverage for all scenarios
    - Skip edge cases, performance tests, and accessibility tests unless business-critical
  - [x] 6.4 Run feature-specific tests only
    - Run ONLY tests related to Finding Management feature
    - Expected total: approximately 24-32 tests maximum
    - Command: `php artisan test --filter=Finding`
    - Do NOT run the entire application test suite
    - Verify critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 24-32 tests total)
- Critical user workflows for Finding Management are covered
- No more than 8 additional tests added when filling gaps
- Testing focused exclusively on Finding Management feature requirements

---

## Execution Order

Recommended implementation sequence:

1. **Database Layer (Task Group 1)** - Foundation for all other work
   - Create enums, migrations, models, and seeders
   - Establish relationships and scopes

2. **Finding Controller API (Task Group 2)** - Core CRUD operations
   - Create main controller with index, show, update
   - Implement authorization and validation

3. **Evidence Management API (Task Group 3)** - File handling
   - Can be developed in parallel with Task Group 4
   - Requires Task Group 2 for finding routes

4. **Category Management API (Task Group 4)** - Category CRUD
   - Can be developed in parallel with Task Group 3
   - Simpler than evidence management

5. **Frontend UI (Task Group 5)** - User interface
   - Depends on all API endpoints being ready
   - Can start navigation and types after Task Group 2

6. **Test Review (Task Group 6)** - Quality assurance
   - Final step after all implementation complete
   - Fill critical test gaps only

---

## Notes

### Code Patterns to Follow
- **Controller structure**: Follow `AuditController.php` patterns for index/show/update methods
- **Page structure**: Follow `Audits/Index.vue` for list pages with filters
- **Badge styling**: Follow existing badge class patterns with dark mode support
- **Form validation**: Use Form Request classes, not inline validation
- **Data transformation**: Use through() method on paginated results

### Key Files to Reference
- `app/Http/Controllers/AuditController.php` - Controller patterns
- `resources/js/Pages/Audits/Index.vue` - List page patterns
- `resources/js/Pages/Datacenters/Show.vue` - Detail page patterns
- `app/Enums/FindingStatus.php` - Enum structure to extend
- `app/Enums/DiscrepancyType.php` - Category labels reference

### Out of Scope Reminders
- No email notifications
- No bulk operations
- No export to PDF
- No manual finding creation (findings come from audits only)
- No finding templates or recurring findings
- No comment threads
- No history/changelog beyond status
- No dashboard widgets
- No SLA/due dates
