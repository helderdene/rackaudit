# Task Breakdown: Audit History Reports

## Overview
Total Tasks: 31 (across 4 task groups)

This feature provides historical views of completed audits with trend analysis for finding counts by severity and resolution time metrics. The implementation follows existing patterns from `CapacityReportController` and related components.

## Task List

### Backend Layer

#### Task Group 1: Controller, Routes, and Data Aggregation
**Dependencies:** None

- [x] 1.0 Complete backend controller and data aggregation layer
  - [x] 1.1 Write 6 focused tests for AuditHistoryReportController functionality
    - Test index returns correct Inertia page with metrics structure
    - Test time range filter applies correctly (30 days, 6 months, 12 months)
    - Test datacenter filter restricts data to accessible datacenters
    - Test audit type filter works for Connection/Inventory types
    - Test PDF export generates downloadable file
    - Test CSV export generates downloadable file
  - [x] 1.2 Create AuditHistoryReportRequest form request for validation
    - Validate time_range_preset: nullable, in:30_days,6_months,12_months
    - Validate start_date: nullable, date, before_or_equal:end_date
    - Validate end_date: nullable, date, after_or_equal:start_date
    - Validate datacenter_id: nullable, exists:datacenters,id
    - Validate audit_type: nullable, in:connection,inventory
    - Location: `app/Http/Requests/AuditHistoryReportRequest.php`
  - [x] 1.3 Create AuditHistoryReportController with index method
    - Copy `ADMIN_ROLES` and `getAccessibleDatacenters()` pattern from CapacityReportController
    - Implement `index()` returning Inertia::render with metrics, filter options, filters, trend data
    - Calculate date range from preset or custom dates (default: last 12 months)
    - Query completed audits: `Audit::where('status', AuditStatus::Completed)`
    - Filter by datacenter_id and audit_type when provided
    - Location: `app/Http/Controllers/AuditHistoryReportController.php`
  - [x] 1.4 Implement summary metrics calculation in controller
    - Total Audits Completed: count of audits in period with weekly/monthly sparkline
    - Total Findings: sum of findings with severity breakdown (Critical/High/Medium/Low counts)
    - Avg Resolution Time: mean `Finding::getTotalResolutionTime()` for resolved findings, formatted as hours/days
    - Avg Time to First Response: mean `Finding::getTimeToFirstResponse()` for findings with InProgress transition
    - Use `generateSparklineData()` pattern from DashboardController
  - [x] 1.5 Implement finding trend data aggregation
    - Group findings by time period (weekly for 30-day, monthly for longer views)
    - Aggregate counts by severity using `FindingSeverity::cases()`
    - Return structure: `[{period: string, critical: int, high: int, medium: int, low: int}]`
  - [x] 1.6 Implement resolution time trend data aggregation
    - Group resolved findings by time period
    - Calculate average resolution time per period
    - Calculate average time to first response per period
    - Return structure: `[{period: string, avg_resolution_time: float, avg_first_response: float}]`
  - [x] 1.7 Implement paginated audit history data for table
    - Query completed audits with eager loading: `->with(['datacenter', 'findings'])`
    - Calculate per-audit metrics: total findings, severity counts, avg resolution time
    - Apply server-side pagination (15 per page)
    - Support sorting by completion_date (default desc), total_findings, avg_resolution_time
  - [x] 1.8 Register routes in routes/web.php
    - GET `/reports/audit-history` -> `AuditHistoryReportController@index`
    - GET `/reports/audit-history/export/pdf` -> `AuditHistoryReportController@exportPdf`
    - GET `/reports/audit-history/export/csv` -> `AuditHistoryReportController@exportCsv`
    - Apply auth middleware
  - [x] 1.9 Ensure backend controller tests pass
    - Run ONLY the 6 tests written in 1.1
    - Verify controller returns expected data structure
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6 tests written in 1.1 pass
- Controller returns correct Inertia props structure
- Filters correctly restrict data to user's accessible datacenters
- Time range filters work for all preset options and custom dates
- Metrics calculations match Finding model methods

### Service and Export Layer

#### Task Group 2: PDF and CSV Export Services
**Dependencies:** Task Group 1

- [x] 2.0 Complete export service layer
  - [x] 2.1 Write 4 focused tests for export functionality
    - Test PDF generation creates valid file with correct content
    - Test CSV export contains correct columns and data
    - Test exports respect current filter parameters
    - Test filter scope description is included in PDF
  - [x] 2.2 Create AuditHistoryReportService for PDF generation
    - Follow `CapacityReportService` pattern
    - Method: `generatePdfReport(array $filters, User $generator): string`
    - Include summary metrics, trend data for charts, top audits table
    - Build filter scope description (time range, datacenter, audit type)
    - Store in `reports/audit-history/` directory
    - Location: `app/Services/AuditHistoryReportService.php`
  - [x] 2.3 Create PDF Blade template for audit history report
    - Executive summary section with 4 metric cards
    - Finding trend chart placeholder (rendered as image from frontend)
    - Resolution time trend chart placeholder (rendered as image from frontend)
    - Top audits table (first 20 rows)
    - Footer with filter scope, generation timestamp, generator name
    - Location: `resources/views/pdf/audit-history-report.blade.php`
  - [x] 2.4 Create AuditHistoryReportExport extending AbstractDataExport
    - Headings: Audit Name, Type, Datacenter, Completion Date, Total Findings, Critical, High, Medium, Low, Avg Resolution Time (hours)
    - Title: "Audit History Report"
    - Query all completed audits matching filters (not paginated)
    - Transform row with severity counts and resolution time in hours
    - Location: `app/Exports/AuditHistoryReportExport.php`
  - [x] 2.5 Add exportPdf and exportCsv methods to controller
    - `exportPdf()`: validate filters, generate PDF via service, return download response
    - `exportCsv()`: validate filters, return Excel::download with timestamped filename
    - Follow same pattern as CapacityReportController export methods
  - [x] 2.6 Ensure export service tests pass
    - Run ONLY the 4 tests written in 2.1
    - Verify PDF generates and stores correctly
    - Verify CSV contains expected data
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4 tests written in 2.1 pass
- PDF generates with all required sections
- CSV export includes all audits matching filters
- File storage and download responses work correctly

### Frontend Components

#### Task Group 3: Vue Components and Page
**Dependencies:** Task Group 2

- [x] 3.0 Complete frontend UI components
  - [x] 3.1 Write 4 focused tests for frontend components
    - Test Index page renders with correct structure and sections
    - Test filters update URL parameters correctly
    - Test export buttons generate correct URLs with current filters
    - Test table pagination and sorting work
  - [x] 3.2 Create AuditHistoryFilters component
    - Time range preset select: "Last 30 days", "Last 6 months", "Last year"
    - Custom date range picker with start/end date inputs
    - Datacenter dropdown using accessible datacenters prop
    - Audit type dropdown: "All Types", "Connection", "Inventory"
    - Debounced filter application with Inertia router (preserveState, preserveScroll)
    - Store filters in URL query parameters
    - Location: `resources/js/components/AuditHistoryReports/AuditHistoryFilters.vue`
  - [x] 3.3 Create AuditHistoryMetricCard component
    - Adapt from CapacityMetricCard pattern
    - Display value with unit, sparkline, and optional trend indicator
    - Support severity breakdown badges slot for Total Findings card
    - Location: `resources/js/components/AuditHistoryReports/AuditHistoryMetricCard.vue`
  - [x] 3.4 Create FindingTrendChart component (stacked area)
    - Chart.js stacked area chart
    - 4 series with severity colors: Critical (red), High (orange), Medium (yellow), Low (blue)
    - X-axis: time periods, Y-axis: finding count
    - Interactive legend and tooltips showing breakdown per period
    - Location: `resources/js/components/AuditHistoryReports/FindingTrendChart.vue`
  - [x] 3.5 Create ResolutionTimeTrendChart component (line chart)
    - Chart.js line chart with two lines
    - Line 1: Average Resolution Time (blue)
    - Line 2: Average Time to First Response (green)
    - X-axis: time periods, Y-axis: time in hours/days (auto-scale)
    - Location: `resources/js/components/AuditHistoryReports/ResolutionTimeTrendChart.vue`
  - [x] 3.6 Create AuditHistoryTable component
    - Server-side paginated table (15 rows per page)
    - Columns: Audit Name (link), Type (badge), Datacenter, Completion Date, Total Findings, Critical/High/Medium/Low counts, Avg Resolution Time
    - Sortable by completion date, total findings, resolution time
    - Use Inertia Link for audit name linking to audit detail
    - Location: `resources/js/components/AuditHistoryReports/AuditHistoryTable.vue`
  - [x] 3.7 Create AuditHistoryReports/Index.vue page
    - Follow CapacityReports/Index.vue layout pattern
    - Header: HeadingSmall with title "Audit History Reports" and description
    - Export buttons using existing ExportButtons component
    - Filters section with AuditHistoryFilters
    - Metrics grid (4 cards): Total Audits, Total Findings, Avg Resolution Time, Avg First Response
    - Trend charts section: FindingTrendChart and ResolutionTimeTrendChart side by side
    - Detailed table section: AuditHistoryTable with pagination
    - Skeleton loading states during filter changes
    - Empty state when no data available
    - Location: `resources/js/pages/AuditHistoryReports/Index.vue`
  - [x] 3.8 Create component barrel export file
    - Export all components from `resources/js/components/AuditHistoryReports/index.ts`
    - Pattern: `export { default as AuditHistoryFilters } from './AuditHistoryFilters.vue'`
  - [x] 3.9 Ensure frontend component tests pass
    - Run ONLY the 4 tests written in 3.1
    - Verify page renders correctly
    - Verify filter interactions work
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4 tests written in 3.1 pass
- Page follows CapacityReports/Index.vue layout pattern exactly
- Charts render with correct colors and interactivity
- Table supports pagination and sorting
- Filters persist in URL for shareable links
- Responsive design works across breakpoints

### Testing

#### Task Group 4: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-3

- [x] 4.0 Review existing tests and fill critical gaps only
  - [x] 4.1 Review tests from Task Groups 1-3
    - Review the 6 tests written by backend engineer (Task 1.1)
    - Review the 4 tests written by export engineer (Task 2.1)
    - Review the 4 tests written by frontend engineer (Task 3.1)
    - Total existing tests: approximately 14 tests
  - [x] 4.2 Analyze test coverage gaps for THIS feature only
    - Identify critical user workflows that lack test coverage
    - Focus ONLY on gaps related to Audit History Reports feature
    - Do NOT assess entire application test coverage
    - Prioritize end-to-end workflows over unit test gaps
  - [x] 4.3 Write up to 8 additional strategic tests maximum
    - Test user without datacenter access sees only assigned datacenters
    - Test custom date range filtering works correctly
    - Test metric calculations with edge cases (no findings, no resolved findings)
    - Test trend data aggregation for different time periods
    - Test PDF includes correct filter scope description
    - Add browser tests if Pest v4 browser testing is set up
    - Do NOT write comprehensive coverage for all scenarios
    - Skip edge cases, performance tests unless business-critical
  - [x] 4.4 Run feature-specific tests only
    - Run ONLY tests related to Audit History Reports feature
    - Expected total: approximately 20-22 tests maximum
    - Do NOT run the entire application test suite
    - Verify critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 20-22 tests total)
- Critical user workflows for this feature are covered
- No more than 8 additional tests added when filling in testing gaps
- Testing focused exclusively on Audit History Reports feature requirements

## Execution Order

Recommended implementation sequence:

1. **Backend Layer (Task Group 1)** - Controller, routes, data aggregation
   - Sets up the foundation for all data retrieval and metrics
   - Enables frontend development to proceed with real data structure

2. **Service and Export Layer (Task Group 2)** - PDF/CSV exports
   - Builds on controller patterns
   - Completes all backend functionality

3. **Frontend Components (Task Group 3)** - Vue components and page
   - Leverages finalized backend API structure
   - Creates user-facing interface

4. **Test Review and Gap Analysis (Task Group 4)** - Final verification
   - Ensures feature completeness
   - Validates all integrations work correctly

## Key Implementation Notes

### Existing Patterns to Follow
- `CapacityReportController.php`: User access filtering, filter validation, Inertia props structure
- `CapacityReports/Index.vue`: Page layout, filter handling, skeleton states, metric cards
- `CapacityReportService.php`: PDF generation pattern with Blade template
- `CapacityReportExport.php`: CSV export extending AbstractDataExport
- `Finding::getTimeToFirstResponse()` and `Finding::getTotalResolutionTime()`: Resolution metrics

### Data Sources
- Completed audits: `Audit::where('status', AuditStatus::Completed)`
- Findings with severity: `Finding` model with `FindingSeverity` enum
- Resolution times: `Finding::getTotalResolutionTime()` and `Finding::getTimeToFirstResponse()`
- User datacenter access: Same pattern as CapacityReportController

### Chart Colors (from spec)
- Critical: red (rgb(239, 68, 68))
- High: orange (rgb(249, 115, 22))
- Medium: yellow (rgb(234, 179, 8))
- Low: blue (rgb(59, 130, 246))
- Resolution Time line: blue
- First Response line: green (rgb(34, 197, 94))
