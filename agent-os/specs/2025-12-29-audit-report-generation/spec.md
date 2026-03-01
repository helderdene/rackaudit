# Specification: Audit Report Generation

## Goal
Enable users to generate comprehensive PDF reports summarizing audit scope, findings organized by severity, and connection comparison results, with report storage for historical access from both individual audit pages and a dedicated Reports section.

## User Stories
- As an IT Manager, I want to generate a PDF report from an audit so that I can share documented findings with stakeholders and maintain compliance records.
- As an Auditor, I want to access previously generated reports so that I can review historical audit documentation without regenerating them.

## Specific Requirements

**Generate Report Action**
- Add "Generate Report" button to the Audit Show page (`resources/js/Pages/Audits/Show.vue`)
- Button only visible when audit status is `in_progress` or `completed`
- Generation should be queued for larger audits to prevent timeout issues
- Return report download URL upon completion or redirect to report detail view

**Executive Summary Section**
- Display audit name, datacenter, room (if applicable), and audit type
- Total findings count across all severity levels
- Resolution rate calculated as: (resolved findings / total findings) * 100
- Critical issues count prominently displayed
- Audit date range (created_at to completion or current date)

**Findings by Severity Section**
- Group findings by severity in order: Critical, High, Medium, Low
- Use `FindingSeverity` enum ordering (Critical, High, Medium, Low)
- Each severity section shows count as header (e.g., "Critical (3)")
- Empty severity sections should be omitted from the report

**Finding Detail Display**
- Title and description fields from Finding model
- Status using `FindingStatus` enum label (Open, In Progress, Pending Review, Deferred, Resolved)
- Assignee name from `assigned_to` relationship
- Resolution notes when status is Resolved
- Related connection/device details via `verification` and `deviceVerification` relationships

**Connection Comparison Summary**
- Total matched connections count (where discrepancy_type = 'matched')
- Missing connections count (where discrepancy_type = 'missing')
- Unexpected connections count (where discrepancy_type = 'unexpected')
- Leverage existing `AuditConnectionVerification` model data
- Only display for connection-type audits (skip for inventory audits)

**Report Storage Model**
- Create `AuditReport` model with: audit_id, user_id (generator), file_path, generated_at, file_size_bytes
- Store PDF files using Laravel's filesystem (local or S3 based on config)
- File naming convention: `audit-report-{audit_id}-{timestamp}.pdf`
- Soft deletes for report records to maintain history

**Report History on Audit Page**
- Add "Report History" section to Audit Show page below existing cards
- Display list of previously generated reports with: generated date, generator name, download link
- Most recent reports first (descending by generated_at)
- Allow regeneration even when previous reports exist

**Reports Navigation Section**
- Add "Reports" item to main sidebar navigation
- Create Reports Index page listing all generated reports
- Filter by: datacenter, audit name search, date range
- Sortable by: generated date, audit name
- Each row links to both the report download and the source audit

## Visual Design

No visual assets provided. Follow existing UI patterns:
- Use Card components consistent with Audit Show page design
- Badge component for severity indicators matching existing FindingSeverity colors
- Button component with Download icon for report download actions
- Table layout for report history similar to existing Index pages

## Existing Code to Leverage

**QrCodePdfService (`/Users/helderdene/rackaudit/app/Services/QrCodePdfService.php`)**
- Demonstrates DomPDF integration with `Barryvdh\DomPDF\Facade\Pdf`
- Pattern for HTML-to-PDF generation with custom styling
- File download response handling via `$pdf->download($filename)`
- Service class structure for PDF generation logic

**Finding Model (`/Users/helderdene/rackaudit/app/Models/Finding.php`)**
- Relationships: `audit()`, `verification()`, `deviceVerification()`, `assignee()`, `category()`
- Status and severity casting to enum types
- Scopes for filtering: `scopeFilterByStatus()`, `scopeFilterBySeverity()`
- Resolution tracking via `resolved_at`, `resolved_by`, `resolution_notes`

**Audit Model (`/Users/helderdene/rackaudit/app/Models/Audit.php`)**
- Relationships: `findings()`, `verifications()`, `deviceVerifications()`, `datacenter()`, `room()`
- Type casting for `AuditType`, `AuditStatus`, `AuditScopeType` enums
- Progress methods: `totalVerifications()`, `completedVerifications()`

**AuditConnectionVerification Model (`/Users/helderdene/rackaudit/app/Models/AuditConnectionVerification.php`)**
- Stores verification results including `discrepancy_type` for connection comparison
- Relationships: `expectedConnection()`, `connection()`, `finding()`
- Scopes: `pending()`, `verified()`, `discrepant()`

**FindingSeverity Enum (`/Users/helderdene/rackaudit/app/Enums/FindingSeverity.php`)**
- Cases: Critical, High, Medium, Low with `label()` and `color()` methods
- Use for ordering findings and applying consistent styling in PDF

## Out of Scope
- Recommendations section in the report (explicitly excluded per requirements)
- Timeline/history of finding creation and resolution dates
- Report generation from dashboard (only from individual audit detail pages)
- Multiple report format options (Excel, Word) - PDF only for initial version
- Email delivery of generated reports
- Scheduled/automatic report generation
- Report templates or customization options
- Comparison between multiple audit reports
- Bulk report generation for multiple audits
- Report access permissions beyond standard user authentication
