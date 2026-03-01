# Task Breakdown: Custom Report Builder

## Overview
Total Tasks: 47

This feature enables users to build customized reports by selecting a report type (Capacity, Assets, Connections, or Audit History) and configuring columns, filters, sorting, grouping, and calculated fields, with support for on-screen preview and multiple export formats (PDF, CSV/Excel, JSON).

## Task List

### Backend Foundation

#### Task Group 1: Configuration and Service Layer
**Dependencies:** None

- [x] 1.0 Complete report configuration and service layer
  - [x] 1.1 Write 4-6 focused tests for CustomReportBuilderService
    - Test report type configuration loading
    - Test field/column availability per report type
    - Test calculated field computation
    - Test filter application to queries
  - [x] 1.2 Create ReportType enum with available report types
    - Values: Capacity, Assets, Connections, AuditHistory
    - Add label() method for user-friendly display names
  - [x] 1.3 Create ReportFieldConfiguration value object
    - Fields: key, display_name, category, is_calculated, data_type
    - Implement fromArray() factory method
    - Pattern: Follow existing value object patterns in app/
  - [x] 1.4 Create CustomReportBuilderService
    - Method: getAvailableFieldsForType(ReportType): array
    - Method: getAvailableFiltersForType(ReportType): array
    - Method: getCalculatedFieldsForType(ReportType): array
    - Method: buildQuery(ReportType, array $fields, array $filters, array $sort, ?string $groupBy): Builder
    - Inject existing report services (CapacityReportService, AssetReportService, etc.)
  - [x] 1.5 Define field configurations per report type
    - Capacity: rack_name, datacenter_name, room_name, row_name, u_height, used_u_space, available_u_space, utilization_percent, power_capacity_watts, power_used_watts, devices_per_rack (calculated)
    - Assets: asset_tag, name, serial_number, manufacturer, model, device_type, lifecycle_status, datacenter_name, room_name, rack_name, start_u, warranty_end_date, days_until_warranty_expiration (calculated), age_in_years (calculated)
    - Connections: connection_id, source_device, source_port, destination_device, destination_port, cable_type, connection_status, port_utilization_percentage (calculated)
    - Audit History: audit_id, audit_date, audit_type, datacenter_name, room_name, finding_count, severity, resolution_date, days_to_resolution (calculated), finding_resolution_rate (calculated)
  - [x] 1.6 Ensure service layer tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify field configurations are correct per report type
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- ReportType enum has all four report types with labels
- CustomReportBuilderService returns correct fields/filters per report type
- Calculated fields are properly defined for each report type

---

#### Task Group 2: API Controller and Form Request
**Dependencies:** Task Group 1

- [x] 2.0 Complete API layer for custom reports
  - [x] 2.1 Write 4-6 focused tests for CustomReportBuilderController
    - Test index action returns available report types and initial configuration
    - Test configure action returns fields/filters for selected report type
    - Test preview action returns paginated data based on configuration
    - Test export actions (PDF, CSV, JSON) return correct response types
    - Test role-based access control (IT Manager, Administrator, Auditor only)
  - [x] 2.2 Create CustomReportBuilderRequest form request
    - Validate report_type: required, valid enum value
    - Validate columns: required, array, min:1, each must be valid for report type
    - Validate filters: optional, array, validate based on report type
    - Validate sort: optional, array, max 3 columns, each with column and direction
    - Validate group_by: optional, string, must be valid field for report type
    - Validate page: optional, integer, min:1
    - Implement custom validation messages
    - Pattern: Follow existing form requests in app/Http/Requests/
  - [x] 2.3 Create CustomReportBuilderController
    - Constructor: inject CustomReportBuilderService
    - Method: index() - Return Inertia page with report type options
    - Method: configure(Request) - Return available fields/filters for selected type (JSON)
    - Method: preview(CustomReportBuilderRequest) - Return paginated data (Inertia)
    - Method: exportPdf(CustomReportBuilderRequest) - Return PDF download
    - Method: exportCsv(CustomReportBuilderRequest) - Return CSV download
    - Method: exportJson(CustomReportBuilderRequest) - Return JSON response
    - Apply role middleware: IT Manager, Administrator, Auditor
    - Use existing getAccessibleDatacenters() pattern from CapacityReportController
  - [x] 2.4 Register routes in routes/web.php
    - GET /custom-reports -> index
    - GET /custom-reports/configure -> configure (API endpoint)
    - POST /custom-reports/preview -> preview
    - POST /custom-reports/export/pdf -> exportPdf
    - POST /custom-reports/export/csv -> exportCsv
    - POST /custom-reports/export/json -> exportJson
    - Apply role middleware to route group
  - [x] 2.5 Ensure API layer tests pass
    - Run ONLY the 4-6 tests written in 2.1
    - Verify routes are registered correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 2.1 pass
- CustomReportBuilderRequest validates all configuration options
- Controller methods return correct response types
- Role-based access control is enforced
- Routes are properly registered with middleware

---

#### Task Group 3: Export Classes
**Dependencies:** Task Group 1, Task Group 2

- [x] 3.0 Complete export functionality
  - [x] 3.1 Write 3-5 focused tests for export classes
    - Test CustomReportExport generates CSV with dynamic columns
    - Test PDF template renders with dynamic data
    - Test JSON export returns correct structure
  - [x] 3.2 Create CustomReportExport extending AbstractDataExport
    - Constructor: accept ReportType, array $columns, array $filters, array $sort, ?string $groupBy
    - Override headings() to return dynamic column headers
    - Override query() to build filtered query via CustomReportBuilderService
    - Override transformRow() to extract only selected columns
    - Handle grouping when group_by is specified
    - Handle calculated fields in transformRow()
  - [x] 3.3 Create PDF view template for custom reports
    - File: resources/views/pdf/custom-report.blade.php
    - Accept dynamic column headers
    - Accept dynamic data rows
    - Include report metadata (generated date, filters applied, report type)
    - Include grouping headers with subtotals when grouped
    - Follow existing PDF template patterns in resources/views/pdf/
  - [x] 3.4 Update CustomReportBuilderService for PDF generation
    - Method: generatePdfReport(ReportType, array $columns, array $filters, array $sort, ?string $groupBy, User $user): string
    - Use DomPDF to render custom-report.blade.php
    - Save to storage and return file path
    - Pattern: Follow CapacityReportService.generatePdfReport()
  - [x] 3.5 Ensure export tests pass
    - Run ONLY the 3-5 tests written in 3.1
    - Verify CSV generates with correct columns
    - Verify PDF renders correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 3-5 tests written in 3.1 pass
- CSV export includes only selected columns with proper headers
- PDF export renders with dynamic data and grouping support
- JSON export returns structured data with metadata

---

### Frontend Components

#### Task Group 4: Report Type Selection and Configuration UI
**Dependencies:** Task Group 2

- [x] 4.0 Complete report type selection and column configuration
  - [x] 4.1 Write 3-5 focused tests for report configuration components
    - Test ReportTypeSelector emits correct selection event
    - Test ColumnSelector displays fields grouped by category
    - Test at least one column must be selected before preview
  - [x] 4.2 Create Pages/CustomReports/Builder.vue
    - Import and use AppLayout with breadcrumbs
    - Define TypeScript interfaces for props (report types, fields, filters, etc.)
    - Implement step-based UI flow: Select Type -> Configure -> Preview
    - Pattern: Follow existing CapacityReports/Index.vue structure
  - [x] 4.3 Create ReportTypeSelector component
    - File: resources/js/Components/CustomReports/ReportTypeSelector.vue
    - Display four report types as cards with icons and descriptions
    - Emit 'select' event with ReportType value
    - Highlight selected type
    - Show loading state while fetching configuration
    - Icons: BarChart3 (Capacity), Package (Assets), Cable (Connections), ClipboardList (AuditHistory)
  - [x] 4.4 Create ColumnSelector component
    - File: resources/js/Components/CustomReports/ColumnSelector.vue
    - Props: availableColumns (grouped by category), selectedColumns
    - Display checkboxes grouped by category
    - Add "Select All" / "Deselect All" per category
    - Show calculated fields with visual indicator
    - Emit 'update:selectedColumns' event
    - Validate minimum one column selected
  - [x] 4.5 Ensure report type selection tests pass
    - Run ONLY the 3-5 tests written in 4.1
    - Verify component interactions work correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 3-5 tests written in 4.1 pass
- Report type cards display correctly with icons
- Column selector shows fields grouped by category
- Select all/deselect all works per category
- At least one column validation works

---

#### Task Group 5: Filters and Sorting Configuration
**Dependencies:** Task Group 4

- [x] 5.0 Complete filter and sorting configuration UI
  - [x] 5.1 Write 3-5 focused tests for filter and sorting components
    - Test cascading location filters reset child when parent changes
    - Test type-specific filters show/hide based on report type
    - Test sort configuration allows up to 3 columns
  - [x] 5.2 Create CustomReportFilters component
    - File: resources/js/Components/CustomReports/CustomReportFilters.vue
    - Props: reportType, datacenterOptions, roomOptions, rowOptions, filters
    - Implement cascading location filters (datacenter > room > row)
    - Pattern: Follow CapacityFilters.vue structure
    - Use watchers to reset child filters when parent changes
    - Emit 'update:filters' event
  - [x] 5.3 Create TypeSpecificFilters component
    - File: resources/js/Components/CustomReports/TypeSpecificFilters.vue
    - Props: reportType, filterOptions, filters
    - Conditionally render filters based on reportType:
      - Assets: device_type dropdown, lifecycle_status dropdown, manufacturer dropdown, warranty date range
      - Capacity: utilization_threshold percentage input
      - Connections: cable_type dropdown, connection_status dropdown
      - AuditHistory: date range, audit_type dropdown, finding_severity dropdown
    - All filters optional
    - Emit 'update:filters' event
  - [x] 5.4 Create SortConfiguration component
    - File: resources/js/Components/CustomReports/SortConfiguration.vue
    - Props: availableColumns (from selected columns), sortConfig
    - Allow adding up to 3 sort columns
    - Each sort item: column dropdown + direction toggle (asc/desc)
    - Add/remove sort column buttons
    - Emit 'update:sortConfig' event
    - Default: first selected column, descending
  - [x] 5.5 Create GroupBySelector component
    - File: resources/js/Components/CustomReports/GroupBySelector.vue
    - Props: availableColumns, groupBy
    - Single dropdown to select grouping field (or "No grouping")
    - Emit 'update:groupBy' event
    - Optional grouping
  - [x] 5.6 Ensure filter and sorting tests pass
    - Run ONLY the 3-5 tests written in 5.1
    - Verify cascading filters work correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 3-5 tests written in 5.1 pass
- Cascading location filters work correctly
- Type-specific filters show/hide based on selected report type
- Multi-column sorting (up to 3) with direction toggle works
- Group by selector allows optional single-field grouping

---

#### Task Group 6: Preview Table and Export Actions
**Dependencies:** Task Group 5

- [x] 6.0 Complete preview and export functionality
  - [x] 6.1 Write 3-5 focused tests for preview and export components
    - Test PreviewTable renders dynamic columns based on selection
    - Test pagination works with 25 rows per page
    - Test export buttons trigger correct download actions
  - [x] 6.2 Create PreviewTable component
    - File: resources/js/Components/CustomReports/PreviewTable.vue
    - Props: columns, data, pagination, loading, groupBy
    - Render table headers based on selected columns
    - Render data rows with proper formatting per data type
    - Show group headers with subtotals when grouped
    - Implement pagination controls (25 rows per page)
    - Show total record count
    - Pattern: Follow existing table patterns in the codebase
  - [x] 6.3 Create TableLoadingSkeleton component
    - File: resources/js/Components/CustomReports/TableLoadingSkeleton.vue
    - Show skeleton loading animation during data fetch
    - Dynamic column count based on selected columns
    - Pattern: Follow skeleton patterns in CapacityReports/Index.vue
  - [x] 6.4 Create CustomReportExportButtons component
    - File: resources/js/Components/CustomReports/ExportButtons.vue
    - Props: reportConfig, loading
    - Buttons for PDF, CSV, JSON export
    - Each button triggers POST request with current configuration
    - Show loading states per button
    - Pattern: Follow ExportButtons.vue pattern but with POST requests
    - Use Wayfinder actions for routes
  - [x] 6.5 Integrate all components in Builder.vue
    - Wire up ReportTypeSelector to fetch configuration on selection
    - Wire up ColumnSelector, filters, sorting, groupBy to local state
    - Add "Generate Preview" button to trigger preview action
    - Wire up PreviewTable with preview data
    - Wire up ExportButtons with current configuration
    - Handle loading states throughout the flow
  - [x] 6.6 Ensure preview and export tests pass
    - Run ONLY the 3-5 tests written in 6.1
    - Verify table renders correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 3-5 tests written in 6.1 pass
- Preview table renders with dynamic columns
- Pagination displays 25 rows per page with controls
- Group headers and subtotals appear when grouping is active
- Export buttons trigger correct download actions
- Loading skeletons appear during data operations

---

### Responsive Design and Polish

#### Task Group 7: Responsive Design and Accessibility
**Dependencies:** Task Group 6

- [x] 7.0 Complete responsive design and accessibility
  - [x] 7.1 Write 2-4 focused tests for responsive behavior
    - Test mobile collapsible filter panel works
    - Test table horizontal scroll on small screens
  - [x] 7.2 Implement mobile-responsive filter panel
    - Use Collapsible component for mobile view
    - Show filter badge when filters are active
    - Pattern: Follow CapacityFilters.vue mobile/desktop pattern
  - [x] 7.3 Implement responsive preview table
    - Horizontal scroll for tables on small screens
    - Consider sticky first column for better UX
    - Responsive pagination controls
  - [x] 7.4 Add accessibility improvements
    - Proper ARIA labels on interactive elements
    - Keyboard navigation for column selector
    - Focus management between steps
    - Screen reader announcements for loading states
  - [x] 7.5 Ensure responsive tests pass
    - Run ONLY the 2-4 tests written in 7.1
    - Verify mobile and desktop layouts work
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-4 tests written in 7.1 pass
- Mobile: Filter panel collapses with active badge indicator
- Mobile: Tables scroll horizontally
- Desktop: Full inline filter display
- Keyboard navigation works throughout the builder

---

### Testing

#### Task Group 8: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-7

- [x] 8.0 Review existing tests and fill critical gaps only
  - [x] 8.1 Review tests from Task Groups 1-7
    - Review the 4-6 tests written by Task Group 1 (service layer)
    - Review the 4-6 tests written by Task Group 2 (API layer)
    - Review the 3-5 tests written by Task Group 3 (exports)
    - Review the 3-5 tests written by Task Group 4 (type selection)
    - Review the 3-5 tests written by Task Group 5 (filters/sorting)
    - Review the 3-5 tests written by Task Group 6 (preview/export)
    - Review the 2-4 tests written by Task Group 7 (responsive)
    - Total existing tests: approximately 22-36 tests
  - [x] 8.2 Analyze test coverage gaps for this feature only
    - Identify critical user workflows that lack test coverage
    - Focus ONLY on gaps related to Custom Report Builder requirements
    - Do NOT assess entire application test coverage
    - Prioritize end-to-end workflows: select type -> configure -> preview -> export
  - [x] 8.3 Write up to 10 additional strategic tests maximum
    - Add maximum of 10 new tests to fill identified critical gaps
    - Priority tests to consider:
      - End-to-end: Full flow from type selection to CSV export
      - End-to-end: Full flow from type selection to PDF export
      - Integration: Calculated fields compute correctly in preview
      - Integration: Grouping with subtotals displays correctly
      - Edge case: Empty result set handling
      - Edge case: Single column selection validation
    - Do NOT write comprehensive coverage for all scenarios
  - [x] 8.4 Run feature-specific tests only
    - Run ONLY tests related to Custom Report Builder feature
    - Expected total: approximately 32-46 tests maximum
    - Do NOT run the entire application test suite
    - Verify critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 32-46 tests total)
- Critical user workflows for Custom Report Builder are covered
- No more than 10 additional tests added when filling in testing gaps
- Testing focused exclusively on Custom Report Builder requirements

---

## Execution Order

Recommended implementation sequence:
1. Backend Foundation - Configuration and Service Layer (Task Group 1)
2. Backend Foundation - API Controller and Form Request (Task Group 2)
3. Backend Foundation - Export Classes (Task Group 3)
4. Frontend Components - Report Type Selection and Configuration UI (Task Group 4)
5. Frontend Components - Filters and Sorting Configuration (Task Group 5)
6. Frontend Components - Preview Table and Export Actions (Task Group 6)
7. Responsive Design and Polish (Task Group 7)
8. Test Review and Gap Analysis (Task Group 8)

## Technical Notes

### Existing Patterns to Leverage
- **CapacityReportController.php**: Cascading location filter validation, getAccessibleDatacenters(), export methods
- **AssetReportController.php**: Extended filter patterns, getDeviceTypeOptions(), date validation
- **AbstractDataExport.php**: Base class for CSV/Excel exports with filters support
- **CapacityFilters.vue**: Cascading filter component with debounced navigation
- **ExportButtons.vue**: Reusable export button group with loading states

### Key Dependencies
- Inertia.js for page rendering and navigation
- Laravel Excel (Maatwebsite) for CSV/Excel exports
- DomPDF for PDF generation
- Wayfinder for TypeScript route generation

### Access Control
- Restricted to: IT Manager, Administrator, Auditor roles
- Non-admin users see only their assigned datacenters
- Follow existing ADMIN_ROLES constant pattern

### Performance Considerations
- Use eager loading to prevent N+1 queries
- Paginate preview results (25 per page)
- Consider query limits for large exports
