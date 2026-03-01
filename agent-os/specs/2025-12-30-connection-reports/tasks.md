# Task Breakdown: Connection Reports

## Overview
Total Tasks: 39

This feature provides comprehensive reporting on connection inventory, cable types, and port utilization across datacenters. The implementation follows existing patterns from AssetReports and CapacityReports.

## Task List

### Backend Layer

#### Task Group 1: Connection Calculation Service
**Dependencies:** None

- [x] 1.0 Complete connection calculation service
  - [x] 1.1 Write 6 focused tests for ConnectionCalculationService
    - Test buildFilteredConnectionQuery with datacenter filter
    - Test buildFilteredConnectionQuery with room filter
    - Test getConnectionMetrics returns correct structure
    - Test getCableTypeDistribution groups correctly
    - Test getPortUtilizationMetrics calculates percentages
    - Test getCableLengthStatistics handles null cable_length values
  - [x] 1.2 Create ConnectionCalculationService class
    - Location: `app/Services/ConnectionCalculationService.php`
    - Reuse pattern from: `app/Services/AssetCalculationService.php`
    - Inject service in constructor (no dependencies needed initially)
  - [x] 1.3 Implement buildFilteredConnectionQuery method
    - Accept datacenterId, roomId parameters
    - Query through Connection > sourcePort > device > rack > row > room > datacenter chain
    - Eager load sourcePort.device and destinationPort.device relationships
    - Return Eloquent Builder for chaining
  - [x] 1.4 Implement buildFilteredPortQuery method
    - Accept datacenterId, roomId parameters
    - Query through Port > device > rack > row > room > datacenter chain
    - Return Eloquent Builder for port utilization calculations
  - [x] 1.5 Implement getCableTypeDistribution method
    - Group connections by cable_type enum
    - Return count and percentage for each CableType
    - Group power cables (C13/C14/C19/C20) under "Power" in summary
  - [x] 1.6 Implement getPortTypeDistribution method
    - Count connections by source port type (Ethernet, Fiber, Power)
    - Use PortType enum for grouping
    - Return count and percentage per type
  - [x] 1.7 Implement getCableLengthStatistics method
    - Calculate mean, min, max for connections with non-null cable_length
    - Handle edge case when no connections have cable_length data
    - Return structured array with statistics
  - [x] 1.8 Implement getPortUtilizationMetrics method
    - Calculate total ports vs connected ports (status = Connected)
    - Break down by PortType (Ethernet, Fiber, Power)
    - Break down by PortStatus (Available, Connected, Reserved, Disabled)
    - Calculate utilization percentage per type
  - [x] 1.9 Implement getConnectionMetrics aggregation method
    - Combine all metrics into single structured response
    - Include: totalConnections, cableTypeDistribution, portTypeDistribution, cableLengthStats, portUtilization
    - Follow pattern from AssetCalculationService.getAssetMetrics()
  - [x] 1.10 Ensure service tests pass
    - Run ONLY the 6 tests written in 1.1
    - Verify all calculation methods return expected structures

**Acceptance Criteria:**
- The 6 tests written in 1.1 pass
- Service correctly filters connections by datacenter/room
- Cable type distribution calculates correct counts and percentages
- Port utilization metrics include breakdown by type and status
- Cable length statistics handle edge cases gracefully

---

#### Task Group 2: Connection Report Controller
**Dependencies:** Task Group 1

- [x] 2.0 Complete connection report controller
  - [x] 2.1 Write 6 focused tests for ConnectionReportController
    - Test index returns correct Inertia response with metrics
    - Test index applies datacenter filter correctly
    - Test index respects role-based access control (admin vs operator)
    - Test exportPdf returns PDF file download
    - Test exportCsv returns CSV file download
    - Test pagination works correctly with page parameter
  - [x] 2.2 Create ConnectionReportController class
    - Location: `app/Http/Controllers/ConnectionReportController.php`
    - Reuse pattern from: `app/Http/Controllers/AssetReportController.php`
    - Inject ConnectionCalculationService and ConnectionReportService
  - [x] 2.3 Implement role-based access control
    - Define ADMIN_ROLES constant: Administrator, IT Manager
    - Implement getAccessibleDatacenters method following AssetReportController pattern
    - Apply datacenter access filtering based on user role
  - [x] 2.4 Implement cascading filter validation
    - Implement validateDatacenterId method
    - Implement validateRoomId method (clear when datacenter changes)
    - Store filter state in URL query parameters for shareable links
  - [x] 2.5 Implement index action
    - Render 'ConnectionReports/Index' Inertia page
    - Pass metrics from ConnectionCalculationService
    - Pass filter options (datacenters, rooms)
    - Pass current filter state
    - Include paginated connections inventory (25 per page)
  - [x] 2.6 Implement getConnectionInventory method
    - Paginate connections (25 per page, matching AssetReports)
    - Transform connections for frontend with source/destination device/port info
    - Eager load sourcePort.device and destinationPort.device
  - [x] 2.7 Register routes
    - GET /connection-reports (index)
    - GET /connection-reports/export/pdf (exportPdf)
    - GET /connection-reports/export/csv (exportCsv)
    - Apply auth middleware
  - [x] 2.8 Ensure controller tests pass
    - Run ONLY the 6 tests written in 2.1
    - Verify Inertia responses are correct
    - Verify role-based filtering works

**Acceptance Criteria:**
- The 6 tests written in 2.1 pass
- Controller returns properly structured Inertia response
- Role-based access control enforced correctly
- Cascading filters work (room clears when datacenter changes)
- Export endpoints return correct file types

---

#### Task Group 3: PDF Report Service
**Dependencies:** Task Group 1

- [x] 3.0 Complete PDF report service
  - [x] 3.1 Write 4 focused tests for ConnectionReportService
    - Test generatePdfReport creates valid PDF file
    - Test buildFilterScope returns correct description
    - Test getConnectionInventory returns formatted connection data
    - Test storeReport saves to correct path
  - [x] 3.2 Create ConnectionReportService class
    - Location: `app/Services/ConnectionReportService.php`
    - Reuse pattern from: `app/Services/AssetReportService.php`
    - Inject ConnectionCalculationService
  - [x] 3.3 Implement buildFilterScope method
    - Generate human-readable filter description
    - Include datacenter and room names when filtered
    - Return "All Connections" when no filters applied
  - [x] 3.4 Implement getConnectionInventory method
    - Get all connections matching filters (no pagination for PDF)
    - Transform to array with source device, source port, destination device, destination port, cable type, cable length, cable color
  - [x] 3.5 Implement generatePdfReport method
    - Load 'pdf.connection-report' Blade view
    - Pass metrics, connections, filterScope, generatedBy, generatedAt
    - Set paper size A4 portrait
    - Return stored file path
  - [x] 3.6 Implement storeReport method
    - Save PDF to `storage/app/reports/connections/` directory
    - Use timestamp in filename: `connection-report-{YmdHis}.pdf`
  - [x] 3.7 Create PDF Blade template
    - Location: `resources/views/pdf/connection-report.blade.php`
    - Include header with title, filter scope, generated by/at
    - Include connection metrics summary section
    - Include connections inventory table
    - Follow styling from `resources/views/pdf/asset-report.blade.php`
  - [x] 3.8 Ensure PDF service tests pass
    - Run ONLY the 4 tests written in 3.1
    - Verify PDF generates and stores correctly

**Acceptance Criteria:**
- The 4 tests written in 3.1 pass
- PDF generates with correct metrics and data
- Filter scope description is human-readable
- PDF stored in correct directory with timestamp filename

---

#### Task Group 4: CSV Export Class
**Dependencies:** Task Group 1

- [x] 4.0 Complete CSV export class
  - [x] 4.1 Write 4 focused tests for ConnectionReportExport
    - Test headings returns correct column names
    - Test query applies datacenter filter
    - Test query applies room filter
    - Test transformRow formats connection data correctly
  - [x] 4.2 Create ConnectionReportExport class
    - Location: `app/Exports/ConnectionReportExport.php`
    - Extend AbstractDataExport
    - Reuse pattern from: `app/Exports/AssetReportExport.php`
  - [x] 4.3 Implement headings method
    - Return columns: Source Device, Source Port, Destination Device, Destination Port, Cable Type, Cable Length, Cable Color
  - [x] 4.4 Implement query method
    - Build Connection query with eager loading
    - Apply datacenter filter through port > device > rack > row > room chain
    - Apply room filter similarly
    - Order by source device name, then source port label
  - [x] 4.5 Implement transformRow method
    - Extract source device name and port label
    - Extract destination device name and port label
    - Get cable type label from CableType enum
    - Format cable length with unit (m or ft based on config)
    - Include cable color
  - [x] 4.6 Implement title method
    - Return "Connection Report"
  - [x] 4.7 Ensure export tests pass
    - Run ONLY the 4 tests written in 4.1
    - Verify CSV exports with correct columns and data

**Acceptance Criteria:**
- The 4 tests written in 4.1 pass
- CSV exports with correct column headers
- Connection data is properly formatted
- Filters are applied correctly

---

### Frontend Layer

#### Task Group 5: Connection Reports Page Structure
**Dependencies:** Task Groups 1, 2

- [x] 5.0 Complete page structure and filters
  - [x] 5.1 Write 4 focused tests for ConnectionReports page
    - Test page renders with metrics data
    - Test filter dropdowns populate correctly
    - Test filter changes trigger Inertia request
    - Test empty state displays when no connections
  - [x] 5.2 Create ConnectionReports/Index.vue page
    - Location: `resources/js/Pages/ConnectionReports/Index.vue`
    - Reuse structure from: `resources/js/Pages/AssetReports/Index.vue`
    - Define TypeScript interfaces for props (Metrics, Filters, FilterOptions)
  - [x] 5.3 Implement page layout
    - Include Head component with title
    - Use AppLayout with breadcrumbs
    - Add header with HeadingSmall and ExportButtons
    - Create main content area with flex layout
  - [x] 5.4 Create ConnectionFilters component
    - Location: `resources/js/components/ConnectionReports/ConnectionFilters.vue`
    - Reuse pattern from AssetFilters component
    - Include Datacenter and Room cascading selects
    - Emit 'filtering' event when filters change
    - Update URL query parameters
  - [x] 5.5 Implement filter state management
    - Track isFiltering ref for loading states
    - Build export URLs with current filters
    - Handle room reset when datacenter changes
    - Preserve filter state in URL for shareable links
  - [x] 5.6 Implement skeleton loading state
    - Show skeleton cards during filter changes
    - Match skeleton structure to actual content layout
    - Follow pattern from AssetReports/Index.vue
  - [x] 5.7 Implement empty state
    - Show when no connections exist
    - Use appropriate icon (Cable or Link2 from lucide-vue-next)
    - Display helpful message
  - [x] 5.8 Ensure page tests pass
    - Run ONLY the 4 tests written in 5.1
    - Verify page renders and filters work

**Acceptance Criteria:**
- The 4 tests written in 5.1 pass
- Page renders with proper layout and structure
- Cascading filters work correctly
- Loading and empty states display appropriately

---

#### Task Group 6: Connection Metrics Components
**Dependencies:** Task Group 5

- [x] 6.0 Complete connection metrics components
  - [x] 6.1 Write 4 focused tests for metrics components
    - Test ConnectionMetricsCards renders total connections
    - Test CableTypeDistributionChart renders pie chart
    - Test PortUtilizationChart renders bar chart
    - Test components handle empty data gracefully
  - [x] 6.2 Create ConnectionMetricsCards component
    - Location: `resources/js/components/ConnectionReports/ConnectionMetricsCards.vue`
    - Display total connections count
    - Display connections by port type (Ethernet, Fiber, Power)
    - Display cable length statistics (mean, min, max)
    - Use Card, CardHeader, CardContent from ui components
  - [x] 6.3 Create CableTypeDistributionChart component
    - Location: `resources/js/components/ConnectionReports/CableTypeDistributionChart.vue`
    - Implement pie/donut chart using Chart.js
    - Display count and percentage for each cable type
    - Use consistent color coding for cable types
    - Handle empty state when no connections exist
  - [x] 6.4 Create PortUtilizationChart component
    - Location: `resources/js/components/ConnectionReports/PortUtilizationChart.vue`
    - Implement horizontal bar chart using Chart.js
    - Display total ports vs connected ports per type
    - Show utilization percentage per port type
    - Color code by port type (Ethernet, Fiber, Power)
  - [x] 6.5 Create PortStatusBreakdown component
    - Location: `resources/js/components/ConnectionReports/PortStatusBreakdown.vue`
    - Show port counts by status (Available, Connected, Reserved, Disabled)
    - Use small bar or pill visualization per status
    - Include percentage of total
  - [x] 6.6 Create component barrel export
    - Location: `resources/js/components/ConnectionReports/index.ts`
    - Export all components for clean imports
  - [x] 6.7 Ensure metrics component tests pass
    - Run ONLY the 4 tests written in 6.1
    - Verify charts render with data

**Acceptance Criteria:**
- The 4 tests written in 6.1 pass
- Metrics cards display all connection statistics
- Charts render correctly with Chart.js
- Components handle edge cases (no data, null values)

---

#### Task Group 7: Connections Inventory Table
**Dependencies:** Task Group 5

- [x] 7.0 Complete connections inventory table
  - [x] 7.1 Write 4 focused tests for ConnectionsInventoryTable
    - Test table renders with connection data
    - Test pagination controls work
    - Test columns display correct data
    - Test empty state displays appropriately
  - [x] 7.2 Create ConnectionsInventoryTable component
    - Location: `resources/js/components/ConnectionReports/ConnectionsInventoryTable.vue`
    - Reuse pattern from DeviceInventoryTable
    - Accept connections array and pagination props
  - [x] 7.3 Implement table columns
    - Source Device (device name)
    - Source Port (port label)
    - Destination Device (device name)
    - Destination Port (port label)
    - Cable Type (enum label)
    - Cable Length (formatted with unit)
    - Cable Color (with color swatch if applicable)
  - [x] 7.4 Implement pagination
    - Show current page and total pages
    - Previous/Next navigation buttons
    - Emit page-change event
    - Follow pattern from DeviceInventoryTable
  - [x] 7.5 Apply consistent table styling
    - Use Table, TableHeader, TableBody, TableRow, TableCell from ui
    - Match styling from AssetReports DeviceInventoryTable
    - Add hover states for rows
  - [x] 7.6 Ensure table tests pass
    - Run ONLY the 4 tests written in 7.1
    - Verify table renders and pagination works

**Acceptance Criteria:**
- The 4 tests written in 7.1 pass
- Table displays all connection columns correctly
- Pagination navigates between pages
- Styling consistent with other report tables

---

### Integration Layer

#### Task Group 8: Integration and Navigation
**Dependencies:** Task Groups 2, 5, 6, 7

- [x] 8.0 Complete integration and navigation
  - [x] 8.1 Write 4 focused tests for integration
    - Test navigation link appears in sidebar/menu
    - Test route is accessible to authorized users
    - Test route is not accessible to unauthorized users
    - Test end-to-end flow: load page, apply filter, export
  - [x] 8.2 Add navigation link
    - Add Connection Reports link to appropriate navigation menu
    - Use consistent icon (Cable or Link2)
    - Position appropriately with other report links
  - [x] 8.3 Generate Wayfinder types
    - Run `php artisan wayfinder:generate`
    - Verify TypeScript functions generated for ConnectionReportController
    - Update imports in Vue components if needed
  - [x] 8.4 Ensure integration tests pass
    - Run ONLY the 4 tests written in 8.1
    - Verify full user flow works

**Acceptance Criteria:**
- The 4 tests written in 8.1 pass
- Navigation link visible and functional
- Wayfinder generates correct TypeScript types
- Full user workflow tested end-to-end

---

### Testing Layer

#### Task Group 9: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-8

- [x] 9.0 Review existing tests and fill critical gaps
  - [x] 9.1 Review tests from Task Groups 1-8
    - Review the 6 tests from ConnectionCalculationService (Task 1.1)
    - Review the 6 tests from ConnectionReportController (Task 2.1)
    - Review the 4 tests from ConnectionReportService (Task 3.1)
    - Review the 4 tests from ConnectionReportExport (Task 4.1)
    - Review the 4 tests from page components (Task 5.1)
    - Review the 4 tests from metrics components (Task 6.1)
    - Review the 4 tests from inventory table (Task 7.1)
    - Review the 4 tests from integration (Task 8.1)
    - Total existing tests: approximately 36 tests
  - [x] 9.2 Analyze test coverage gaps for Connection Reports feature
    - Identify critical user workflows lacking coverage
    - Focus ONLY on gaps related to Connection Reports requirements
    - Do NOT assess entire application test coverage
    - Prioritize end-to-end workflows over unit test gaps
  - [x] 9.3 Write up to 10 additional strategic tests if needed
    - Fill identified critical gaps only
    - Focus on integration points between services
    - Test edge cases: empty datacenter, mixed cable types, null values
    - Do NOT write comprehensive coverage for all scenarios
  - [x] 9.4 Run feature-specific tests
    - Run ONLY tests related to Connection Reports feature
    - Expected total: approximately 36-46 tests maximum
    - Do NOT run the entire application test suite
    - Verify all critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 36-46 tests total)
- Critical user workflows for Connection Reports are covered
- No more than 10 additional tests added to fill gaps
- Testing focused exclusively on Connection Reports feature

---

## Execution Order

Recommended implementation sequence:

1. **Backend Services** (Task Groups 1, 3, 4 - can be parallelized)
   - Start with ConnectionCalculationService (Task Group 1) as it's the foundation
   - ConnectionReportService (Task Group 3) and ConnectionReportExport (Task Group 4) can be done in parallel after Group 1

2. **Backend Controller** (Task Group 2)
   - Depends on services from Group 1
   - Implements routes and integrates services

3. **Frontend Components** (Task Groups 5, 6, 7 - can be parallelized)
   - Task Group 5 (Page Structure) should be first
   - Task Groups 6 and 7 can be done in parallel after Group 5

4. **Integration** (Task Group 8)
   - Final integration and navigation setup
   - Depends on all previous groups

5. **Test Review** (Task Group 9)
   - Review all tests and fill gaps
   - Must be done last

---

## Files to Create

### Backend Files
- `app/Services/ConnectionCalculationService.php`
- `app/Services/ConnectionReportService.php`
- `app/Http/Controllers/ConnectionReportController.php`
- `app/Exports/ConnectionReportExport.php`
- `resources/views/pdf/connection-report.blade.php`

### Frontend Files
- `resources/js/Pages/ConnectionReports/Index.vue`
- `resources/js/components/ConnectionReports/ConnectionFilters.vue`
- `resources/js/components/ConnectionReports/ConnectionMetricsCards.vue`
- `resources/js/components/ConnectionReports/CableTypeDistributionChart.vue`
- `resources/js/components/ConnectionReports/PortUtilizationChart.vue`
- `resources/js/components/ConnectionReports/PortStatusBreakdown.vue`
- `resources/js/components/ConnectionReports/ConnectionsInventoryTable.vue`
- `resources/js/components/ConnectionReports/index.ts`

### Test Files
- `tests/Feature/Services/ConnectionCalculationServiceTest.php`
- `tests/Feature/Http/Controllers/ConnectionReportControllerTest.php`
- `tests/Feature/Services/ConnectionReportServiceTest.php`
- `tests/Feature/Exports/ConnectionReportExportTest.php`
- `tests/Feature/Pages/ConnectionReportsTest.php`
- `tests/Feature/ConnectionReports/ConnectionReportGapAnalysisTest.php`

---

## Existing Patterns to Reference

| Pattern | Source File | Usage |
|---------|-------------|-------|
| Calculation Service | `app/Services/AssetCalculationService.php` | Query building, metrics aggregation |
| Report Service | `app/Services/AssetReportService.php` | PDF generation, filter scope |
| Report Controller | `app/Http/Controllers/AssetReportController.php` | Cascading filters, pagination, exports |
| Data Export | `app/Exports/AssetReportExport.php` | CSV export with filters |
| Report Page | `resources/js/Pages/AssetReports/Index.vue` | Page structure, TypeScript types |
| Export Buttons | `resources/js/components/CapacityReports/ExportButtons.vue` | Reusable export UI |
