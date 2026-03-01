# Task Breakdown: Audit Report Generation

## Overview
Total Tasks: 4 Task Groups with 32 Sub-tasks

This feature enables users to generate comprehensive PDF reports summarizing audit scope, findings organized by severity, and connection comparison results. Reports are stored for historical access from both individual audit pages and a dedicated Reports navigation section.

## Task List

### Database Layer

#### Task Group 1: AuditReport Model and Migration
**Dependencies:** None

- [x] 1.0 Complete database layer for AuditReport
  - [x] 1.1 Write 2-8 focused tests for AuditReport model functionality
    - Test model creation with required fields (audit_id, user_id, file_path, generated_at, file_size_bytes)
    - Test audit relationship (belongsTo Audit)
    - Test generator relationship (belongsTo User)
    - Test soft delete functionality
    - Test file_path accessor/formatting if needed
  - [x] 1.2 Create AuditReport model with php artisan make:model
    - Fields: id, audit_id, user_id, file_path, generated_at, file_size_bytes, timestamps, soft_deletes
    - Relationships: audit() belongsTo Audit, generator() belongsTo User
    - Casts: generated_at as datetime, file_size_bytes as integer
    - Fillable: audit_id, user_id, file_path, generated_at, file_size_bytes
  - [x] 1.3 Create migration for audit_reports table
    - Add audit_id foreign key with cascade delete
    - Add user_id foreign key (nullable to handle user deletion)
    - Add file_path string column
    - Add generated_at datetime column
    - Add file_size_bytes unsigned big integer column
    - Add soft_deletes column
    - Add index on audit_id for efficient lookups
    - Add index on generated_at for sorting
  - [x] 1.4 Add reports() relationship to Audit model
    - HasMany relationship to AuditReport
    - Order by generated_at descending by default
  - [x] 1.5 Create AuditReportFactory for testing
    - Use existing AuditFactory and UserFactory for relationships
    - Generate realistic file paths and sizes
  - [x] 1.6 Ensure database layer tests pass
    - Run ONLY the 2-8 tests written in 1.1
    - Verify migration runs successfully
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-8 tests written in 1.1 pass
- AuditReport model created with proper relationships
- Migration runs successfully and creates correct table structure
- Factory generates valid test data

---

### Backend Service Layer

#### Task Group 2: PDF Generation Service and Job
**Dependencies:** Task Group 1

- [x] 2.0 Complete PDF generation backend
  - [x] 2.1 Write 2-8 focused tests for AuditReportService
    - Test report generation for connection audit with findings
    - Test report generation for inventory audit (skips connection comparison)
    - Test executive summary calculations (total findings, resolution rate, critical count)
    - Test findings grouping by severity (Critical, High, Medium, Low order)
    - Test PDF file storage and AuditReport record creation
    - Test that empty severity sections are omitted
  - [x] 2.2 Create AuditReportService class in app/Services
    - Follow pattern from QrCodePdfService for DomPDF integration
    - Method: generateReport(Audit $audit, User $generator): AuditReport
    - Calculate executive summary metrics:
      - Total findings count
      - Resolution rate: (resolved findings / total findings) * 100
      - Critical issues count
      - Date range (created_at to completed_at or now)
    - Group findings by severity using FindingSeverity enum ordering
    - Build connection comparison summary for connection-type audits:
      - Matched count (discrepancy_type = 'matched')
      - Missing count (discrepancy_type = 'missing')
      - Unexpected count (discrepancy_type = 'unexpected')
    - Load finding relationships: assignee, verification, deviceVerification
  - [x] 2.3 Create Blade view template for PDF report
    - Create resources/views/pdf/audit-report.blade.php
    - Executive Summary section with audit name, datacenter, room, type, date range, metrics
    - Findings by Severity sections with proper ordering and styling
    - Finding details: title, description, status, assignee, resolution notes, related device/connection
    - Connection Comparison Summary section (conditional for connection audits)
    - Use severity colors from FindingSeverity enum for consistent styling
    - Professional PDF styling suitable for stakeholder sharing
  - [x] 2.4 Create GenerateAuditReportJob for queued generation
    - Create app/Jobs/GenerateAuditReportJob.php
    - Accept audit_id and user_id parameters
    - Call AuditReportService to generate the report
    - Handle failures gracefully with logging
    - Implement ShouldQueue interface for background processing
  - [x] 2.5 Implement file storage for generated PDFs
    - Use Laravel filesystem (configurable for local or S3)
    - File naming: audit-report-{audit_id}-{timestamp}.pdf
    - Store in reports/audits/ subdirectory
    - Store file size in AuditReport record
  - [x] 2.6 Ensure backend service tests pass
    - Run ONLY the 2-8 tests written in 2.1
    - Verify PDF generates correctly with proper content
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-8 tests written in 2.1 pass
- AuditReportService generates valid PDF with all required sections
- PDF contains executive summary, findings by severity, connection comparison (when applicable)
- Files are stored correctly and AuditReport records are created
- Job can be queued for larger audits

---

### API Layer

#### Task Group 3: Controllers and Routes
**Dependencies:** Task Group 2

- [x] 3.0 Complete API and controller layer
  - [x] 3.1 Write 2-8 focused tests for AuditReportController
    - Test generate action only available when audit status is in_progress or completed
    - Test generate action returns error for pending/cancelled audits
    - Test generate action queues job for large audits
    - Test index action returns paginated reports with filters
    - Test download action returns PDF file
    - Test authentication required for all actions
  - [x] 3.2 Create AuditReportController with php artisan make:controller
    - generate(Audit $audit): Queue job or generate directly, return redirect to audit page
    - index(): List all reports with filters (datacenter, search, date range)
    - download(AuditReport $report): Return file download response
    - show(AuditReport $report): Return report detail view (optional, could redirect to download)
  - [x] 3.3 Add generate action to existing AuditController (alternative approach)
    - Add generateReport method to AuditController if preferred over separate controller
    - Keep report-related actions cohesive with audit context
  - [x] 3.4 Create routes for report functionality
    - POST /audits/{audit}/reports - generate new report
    - GET /reports - reports index page
    - GET /reports/{report}/download - download report file
    - Use route model binding for audit and report parameters
  - [x] 3.5 Create AuditReportResource for API responses
    - Include: id, audit_id, audit_name, generator_name, generated_at, file_size_bytes, download_url
    - Format generated_at for display
    - Format file_size_bytes as human-readable (KB, MB)
  - [x] 3.6 Add authorization checks
    - Ensure user can view the audit before generating/downloading reports
    - Use existing audit authorization patterns
  - [x] 3.7 Ensure API layer tests pass
    - Run ONLY the 2-8 tests written in 3.1
    - Verify routes respond correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-8 tests written in 3.1 pass
- Generate Report action validates audit status before proceeding
- Reports Index page loads with filtering and pagination
- Download action returns PDF file correctly
- Proper authorization enforced on all endpoints

---

### Frontend Layer

#### Task Group 4: Vue Components and Pages
**Dependencies:** Task Group 3

- [x] 4.0 Complete frontend implementation
  - [x] 4.1 Write 2-8 focused tests for frontend components
    - Test Generate Report button visibility based on audit status
    - Test Report History section displays on Audit Show page
    - Test Reports Index page renders with filters
    - Test download link functionality
  - [x] 4.2 Add "Generate Report" button to Audit Show page
    - Add to resources/js/Pages/Audits/Show.vue
    - Only visible when audit.status is 'in_progress' or 'completed'
    - Use Button component with FileText or Download icon
    - POST to generate endpoint on click
    - Show loading state during generation
    - Display success message or error feedback
  - [x] 4.3 Add "Report History" section to Audit Show page
    - Add Card component below existing cards
    - Display list of previously generated reports
    - Show: generated date (formatted), generator name, file size, download link
    - Order by generated_at descending (most recent first)
    - Handle empty state when no reports exist
    - Use existing table/list patterns from Index pages
  - [x] 4.4 Create Reports Index page
    - Create resources/js/Pages/Reports/Index.vue
    - Follow pattern from Audits/Index.vue for layout and styling
    - Filters: datacenter select, audit name search input, date range pickers
    - Sortable columns: generated date, audit name
    - Each row shows: audit name, datacenter, generated date, generator name, file size
    - Actions: Download button, Link to source audit
    - Implement pagination using existing patterns
    - Mobile card view and desktop table view
  - [x] 4.5 Add "Reports" item to sidebar navigation
    - Edit resources/js/components/AppSidebar.vue
    - Add after "Findings" item or in logical position
    - Use FileBarChart or FileText icon from lucide-vue-next
    - Link to /reports route
    - Add role restriction if needed (IT Manager, Auditor roles)
  - [x] 4.6 Generate Wayfinder actions for new controller
    - Run php artisan wayfinder:generate
    - Import AuditReportController actions in Vue components
    - Use Wayfinder for all route URLs
  - [x] 4.7 Apply responsive design
    - Mobile: Stack filters, use card layout for reports list
    - Tablet: Adjusted table layout
    - Desktop: Full table with all columns
    - Follow existing responsive patterns from Audits/Index.vue
  - [x] 4.8 Ensure frontend tests pass
    - Run ONLY the 2-8 tests written in 4.1
    - Verify components render correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-8 tests written in 4.1 pass
- Generate Report button appears only for in_progress/completed audits
- Report History section displays correctly on Audit Show page
- Reports Index page functions with filtering, sorting, and pagination
- Reports navigation item appears in sidebar
- All views are responsive across device sizes

---

### Testing

#### Task Group 5: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-4

- [x] 5.0 Review existing tests and fill critical gaps only
  - [x] 5.1 Review tests from Task Groups 1-4
    - Review the 2-8 tests written by database layer (Task 1.1)
    - Review the 2-8 tests written by backend service layer (Task 2.1)
    - Review the 2-8 tests written by API layer (Task 3.1)
    - Review the 2-8 tests written by frontend layer (Task 4.1)
    - Total existing tests: approximately 8-32 tests
  - [x] 5.2 Analyze test coverage gaps for THIS feature only
    - Identify critical user workflows that lack test coverage:
      - Full workflow: user generates report from audit page, downloads it
      - Edge case: generating report for audit with zero findings
      - Edge case: generating report for inventory audit (no connection comparison)
    - Focus ONLY on gaps related to this spec's feature requirements
    - Do NOT assess entire application test coverage
  - [x] 5.3 Write up to 10 additional strategic tests maximum
    - Add maximum of 10 new tests to fill identified critical gaps
    - Focus on integration points and end-to-end workflows:
      - Integration test: POST to generate, verify AuditReport created, file exists
      - Integration test: Reports Index filtering and pagination
      - Edge case: Audit with findings across all severity levels
      - Edge case: Report download for deleted file (error handling)
    - Do NOT write comprehensive coverage for all scenarios
    - Skip edge cases not critical to core functionality
  - [x] 5.4 Run feature-specific tests only
    - Run ONLY tests related to this spec's feature
    - Expected total: approximately 18-42 tests maximum
    - Do NOT run the entire application test suite
    - Verify critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 18-42 tests total)
- Critical user workflows for this feature are covered
- No more than 10 additional tests added when filling in testing gaps
- Testing focused exclusively on this spec's feature requirements

---

## Execution Order

Recommended implementation sequence:

1. **Database Layer (Task Group 1)** - Create AuditReport model and migration first as foundation
2. **Backend Service Layer (Task Group 2)** - Build PDF generation service and job
3. **API Layer (Task Group 3)** - Create controllers and routes
4. **Frontend Layer (Task Group 4)** - Build Vue components and pages
5. **Test Review & Gap Analysis (Task Group 5)** - Final testing verification

## Key Technical Notes

### Existing Code to Leverage
- **QrCodePdfService** (`/Users/helderdene/rackaudit/app/Services/QrCodePdfService.php`): Pattern for DomPDF integration, HTML generation, file download responses
- **Finding Model** (`/Users/helderdene/rackaudit/app/Models/Finding.php`): Relationships and scopes for filtering findings
- **Audit Model** (`/Users/helderdene/rackaudit/app/Models/Audit.php`): Relationships for datacenter, room, findings, verifications
- **FindingSeverity Enum** (`/Users/helderdene/rackaudit/app/Enums/FindingSeverity.php`): Severity ordering and color methods
- **FindingStatus Enum** (`/Users/helderdene/rackaudit/app/Enums/FindingStatus.php`): Status labels for display
- **DiscrepancyType Enum** (`/Users/helderdene/rackaudit/app/Enums/DiscrepancyType.php`): Values for connection comparison (matched, missing, unexpected)
- **Audits/Show.vue** (`/Users/helderdene/rackaudit/resources/js/Pages/Audits/Show.vue`): Page to add Generate Report button and Report History section
- **Audits/Index.vue** (`/Users/helderdene/rackaudit/resources/js/Pages/Audits/Index.vue`): Pattern for Reports Index page
- **AppSidebar.vue** (`/Users/helderdene/rackaudit/resources/js/components/AppSidebar.vue`): Add Reports navigation item

### File Naming Convention
- PDF files: `audit-report-{audit_id}-{timestamp}.pdf`
- Storage path: `reports/audits/`
- Example: `reports/audits/audit-report-42-20251229153045.pdf`

### Audit Status Constraint
- Generate Report button visible only when audit.status is `in_progress` or `completed`
- Use AuditStatus enum for comparison

### Connection Comparison Logic
- Only display for connection-type audits (`audit.type === 'connection'`)
- Skip for inventory audits
- Query AuditConnectionVerification with discrepancy_type counts
