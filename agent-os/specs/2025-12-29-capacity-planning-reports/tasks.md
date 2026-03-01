# Task Breakdown: Capacity Planning Reports

## Overview
Total Tasks: 7 Task Groups (approximately 45 sub-tasks)

This feature adds a dedicated Capacity Planning Reports page with rack utilization metrics, power consumption tracking, port capacity analysis, historical snapshots, and export capabilities (PDF/CSV).

---

## Task List

### Database Layer

#### Task Group 1: Database Migrations and Model Updates
**Dependencies:** None
**Complexity:** Medium

- [x] 1.0 Complete database layer for capacity tracking
  - [x] 1.1 Write 4-6 focused tests for capacity-related model functionality
    - Test Device power_draw_watts field casting and nullable behavior
    - Test Rack power_capacity_watts field casting and nullable behavior
    - Test CapacitySnapshot model creation with JSON port_stats
    - Test CapacitySnapshot belongs to Datacenter relationship
    - Test snapshot date uniqueness per datacenter constraint
  - [x] 1.2 Create migration to add power_draw_watts to devices table
    - Field: `power_draw_watts` nullable unsigned integer
    - Add after existing columns (follow devices migration pattern)
    - Include rollback in down() method
  - [x] 1.3 Create migration to add power_capacity_watts to racks table
    - Field: `power_capacity_watts` nullable unsigned integer
    - Add after existing columns (follow racks migration pattern)
    - Include rollback in down() method
  - [x] 1.4 Update Device model with power_draw_watts attribute
    - Add to $fillable array
    - Add integer cast in casts() method
    - Follow existing model attribute patterns
  - [x] 1.5 Update Rack model with power_capacity_watts attribute
    - Add to $fillable array
    - Add integer cast in casts() method
    - Follow existing model attribute patterns
  - [x] 1.6 Create CapacitySnapshot model and migration
    - Create migration via `php artisan make:migration create_capacity_snapshots_table`
    - Fields: id, datacenter_id (foreign key), snapshot_date (date), rack_utilization_percent (decimal 5,2), power_utilization_percent (decimal 5,2 nullable), total_u_space (unsigned integer), used_u_space (unsigned integer), total_power_capacity (unsigned integer nullable), total_power_consumption (unsigned integer nullable), port_stats (JSON), timestamps
    - Add unique constraint on [datacenter_id, snapshot_date]
    - Add indexes on snapshot_date and datacenter_id
  - [x] 1.7 Create CapacitySnapshot model via `php artisan make:model CapacitySnapshot`
    - Add datacenter() belongsTo relationship
    - Add $fillable with all snapshot fields
    - Cast port_stats to array, snapshot_date to date
    - Cast numeric fields appropriately
  - [x] 1.8 Update Datacenter model with capacitySnapshots relationship
    - Add hasMany relationship to CapacitySnapshot
  - [x] 1.9 Run migrations and ensure database layer tests pass
    - Run `php artisan migrate`
    - Run ONLY the 4-6 tests written in 1.1
    - Verify all migrations complete successfully

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- Both power-related migrations run successfully
- capacity_snapshots table created with correct schema
- Device and Rack models include new power attributes
- CapacitySnapshot model correctly relates to Datacenter

---

### Backend Services Layer

#### Task Group 2: Capacity Calculation Service
**Dependencies:** Task Group 1
**Complexity:** High

- [x] 2.0 Complete capacity calculation service
  - [x] 2.1 Write 5-7 focused tests for capacity calculation logic
    - Test U-space utilization calculation with mixed rack sizes
    - Test power utilization calculation with null values excluded
    - Test port capacity grouping by PortType enum
    - Test metrics aggregation by datacenter/room/row filters
    - Test handling of empty datasets (no racks, no devices)
    - Test warning/critical threshold classification (80%/90%)
  - [x] 2.2 Create CapacityCalculationService via `php artisan make:class Services/CapacityCalculationService`
    - Inject necessary dependencies in constructor
    - Follow AuditReportService class structure pattern
  - [x] 2.3 Implement calculateUSpaceUtilization() method
    - Accept Builder query for filtered racks
    - Reuse calculation logic from DashboardController::calculateRackUtilization()
    - Return array with total_u_space, used_u_space, available_u_space, utilization_percent
  - [x] 2.4 Implement calculatePowerUtilization() method
    - Accept Builder query for filtered racks
    - Sum device power_draw_watts through rack relationship
    - Sum rack power_capacity_watts for total capacity
    - Handle null values gracefully (exclude from calculations)
    - Return array with total_capacity, total_consumption, power_headroom, utilization_percent (or null)
  - [x] 2.5 Implement calculatePortCapacity() method
    - Query ports through device > rack relationship chain
    - Group by PortType enum (Ethernet, Fiber, Power)
    - Calculate per type: total_ports, connected_ports (has connection), available_ports
    - Use PortType::label() for display names
    - Return array keyed by port type value
  - [x] 2.6 Implement getRacksApproachingCapacity() method
    - Accept utilization threshold parameter (default 80)
    - Return collection of racks with utilization >= threshold
    - Include rack details: id, name, utilization_percent, available_u_space
    - Classify as warning (80-89%) or critical (90%+)
  - [x] 2.7 Implement getCapacityMetrics() method
    - Accept filter parameters: datacenter_id, room_id, row_id
    - Call all calculation methods with filtered query
    - Return aggregated metrics object for controller use
  - [x] 2.8 Ensure capacity calculation tests pass
    - Run ONLY the 5-7 tests written in 2.1
    - Verify all calculation methods work correctly

**Acceptance Criteria:**
- The 5-7 tests written in 2.1 pass
- All calculation methods handle null/empty data gracefully
- Utilization percentages calculated correctly
- Port capacity grouped by PortType enum
- Threshold-based rack classification works

---

#### Task Group 3: Snapshot Scheduler and Export Services
**Dependencies:** Task Groups 1, 2
**Complexity:** Medium-High

- [x] 3.0 Complete snapshot and export services
  - [x] 3.1 Write 4-6 focused tests for snapshot and export functionality
    - Test weekly snapshot job creates records for all datacenters
    - Test snapshot cleanup job removes records older than 52 weeks
    - Test PDF generation creates valid file with expected content
    - Test CSV export includes correct columns and filtered data
    - Test export respects datacenter access control
  - [x] 3.2 Create CaptureCapacitySnapshotJob via `php artisan make:job CaptureCapacitySnapshotJob`
    - Inject CapacityCalculationService
    - Query all datacenters and calculate metrics for each
    - Create CapacitySnapshot record with current metrics
    - Store port_stats as JSON
  - [x] 3.3 Create CleanupOldSnapshotsJob via `php artisan make:job CleanupOldSnapshotsJob`
    - Delete CapacitySnapshot records older than 52 weeks
    - Use chunk() for efficient deletion of large datasets
    - Log number of records deleted
  - [x] 3.4 Register scheduled jobs in routes/console.php
    - Schedule CaptureCapacitySnapshotJob weekly (e.g., Sunday midnight)
    - Schedule CleanupOldSnapshotsJob weekly after snapshot capture
    - Follow existing scheduler patterns in the file
  - [x] 3.5 Create CapacityReportService via `php artisan make:class Services/CapacityReportService`
    - Follow AuditReportService pattern for structure
    - Inject CapacityCalculationService
  - [x] 3.6 Implement generatePdfReport() method
    - Accept filter parameters and user
    - Use Barryvdh\DomPDF for PDF generation
    - Load blade template resources/views/pdf/capacity-report.blade.php
    - Set paper to A4 portrait
    - Store file using Storage::disk('local') pattern
    - Return stored file path
  - [x] 3.7 Create PDF blade template at resources/views/pdf/capacity-report.blade.php
    - Include executive summary section with key metrics
    - Add U-space utilization table with per-rack details
    - Add power consumption summary (when data available)
    - Add port capacity table grouped by type
    - Include generation timestamp and filter scope
    - Follow styling from existing pdf templates
  - [x] 3.8 Create CapacityReportExport extending AbstractDataExport
    - Create at app/Exports/CapacityReportExport.php
    - Define headings: datacenter, room, row, rack, u_capacity, u_used, u_available, power_capacity, power_used, power_available
    - Implement query() with eager loading and filters
    - Implement transformRow() to format rack capacity data
  - [x] 3.9 Ensure snapshot and export tests pass
    - Run ONLY the 4-6 tests written in 3.1
    - Verify jobs execute correctly
    - Verify exports generate valid files

**Acceptance Criteria:**
- The 4-6 tests written in 3.1 pass
- Weekly snapshot job captures all datacenter metrics
- Cleanup job enforces 52-week retention
- PDF generates with executive summary and detailed tables
- CSV export includes all capacity columns

---

### API Layer

#### Task Group 4: Controller and Routes
**Dependencies:** Task Groups 2, 3
**Complexity:** Medium

- [x] 4.0 Complete API layer for capacity reports
  - [x] 4.1 Write 5-7 focused tests for controller endpoints
    - Test index page returns correct Inertia props
    - Test datacenter/room/row filter validation
    - Test user access control (admin vs assigned datacenters)
    - Test PDF export endpoint returns downloadable file
    - Test CSV export endpoint returns downloadable file
    - Test metrics endpoint returns correct JSON structure
  - [x] 4.2 Create CapacityReportController via `php artisan make:controller CapacityReportController`
    - Inject CapacityCalculationService and CapacityReportService
    - Define ADMIN_ROLES constant following DashboardController pattern
  - [x] 4.3 Implement index() method for main page
    - Reuse getAccessibleDatacenters() pattern from DashboardController
    - Get rooms for selected datacenter (cascading filter support)
    - Get rows for selected room (cascading filter support)
    - Validate filter IDs against accessible scope
    - Get capacity metrics via service
    - Get historical snapshots for sparkline data
    - Return Inertia::render('CapacityReports/Index', [...])
  - [x] 4.4 Implement exportPdf() method
    - Validate filter parameters
    - Call CapacityReportService::generatePdfReport()
    - Return file download response with proper headers
    - Set Content-Type: application/pdf
  - [x] 4.5 Implement exportCsv() method
    - Validate filter parameters
    - Instantiate CapacityReportExport with filters
    - Return Excel::download() response
    - Use filename: capacity-report-{timestamp}.csv
  - [x] 4.6 Register routes in routes/web.php
    - GET /capacity-reports (index) - CapacityReportController@index
    - GET /capacity-reports/export/pdf - CapacityReportController@exportPdf
    - GET /capacity-reports/export/csv - CapacityReportController@exportCsv
    - Apply auth middleware
    - Name routes: capacity-reports.index, capacity-reports.export.pdf, capacity-reports.export.csv
  - [x] 4.7 Add navigation menu item
    - Add "Capacity Reports" to main navigation
    - Use appropriate icon (e.g., ChartBar or similar)
    - Place under Reports section or as standalone item
    - Follow existing navigation structure pattern
  - [x] 4.8 Ensure API layer tests pass
    - Run ONLY the 5-7 tests written in 4.1
    - Verify all endpoints return expected responses

**Acceptance Criteria:**
- The 5-7 tests written in 4.1 pass
- Index page loads with correct filter options
- Cascading filters work correctly
- User access control enforced
- PDF and CSV exports download successfully
- Navigation includes new menu item

---

### Frontend Layer

#### Task Group 5: Vue Components and Page
**Dependencies:** Task Group 4
**Complexity:** High

- [x] 5.0 Complete frontend UI components
  - [x] 5.1 Write 4-6 focused tests for UI components
    - Test CapacityFilters cascading behavior (room appears when datacenter selected)
    - Test CapacityMetricCard displays correct values and thresholds
    - Test RackCapacityTable sorting functionality
    - Test export button triggers correct download
    - Test loading skeleton displays during filter changes
  - [x] 5.2 Create CapacityFilters.vue component
    - Location: resources/js/components/CapacityReports/CapacityFilters.vue
    - Follow DiscrepancyFilters.vue pattern for cascading filters
    - Props: filters, datacenters, rooms, rows
    - Implement datacenter > room > row hierarchy
    - Reset child filters when parent changes (watch pattern)
    - Use debounced router.get() for filter updates
    - Support mobile collapsible and desktop sidebar layouts
  - [x] 5.3 Create CapacityMetricCard.vue component
    - Location: resources/js/components/CapacityReports/CapacityMetricCard.vue
    - Extend/adapt MetricCard pattern from dashboard
    - Props: title, value, unit, total, available, threshold, trend, sparklineData
    - Display progress bar for utilization percentage
    - Apply warning color (amber) for 80-89% utilization
    - Apply critical color (red) for 90%+ utilization
    - Include sparkline chart for historical trend
  - [x] 5.4 Create RackCapacityTable.vue component
    - Location: resources/js/components/CapacityReports/RackCapacityTable.vue
    - Props: racks (array of rack capacity data)
    - Display columns: Rack Name, Location, U-Space Used/Total, Power Used/Capacity, Status
    - Support sorting by clicking column headers
    - Apply row highlighting based on utilization threshold
    - Include drill-down links to individual rack details
    - Show "Not configured" for null power values
  - [x] 5.5 Create PortCapacityGrid.vue component
    - Location: resources/js/components/CapacityReports/PortCapacityGrid.vue
    - Props: portStats (object keyed by port type)
    - Display card/grid for each port type (Ethernet, Fiber, Power)
    - Show total, connected, and available counts per type
    - Use PortType labels from backend
  - [x] 5.6 Create ExportButtons.vue component
    - Location: resources/js/components/CapacityReports/ExportButtons.vue
    - Props: pdfUrl, csvUrl, loading
    - Display PDF and CSV export buttons
    - Show loading state during export
    - Use appropriate icons (FileText, FileSpreadsheet)
  - [x] 5.7 Create CapacityReports/Index.vue page
    - Location: resources/js/pages/CapacityReports/Index.vue
    - Follow Dashboard.vue structure pattern
    - Include HeadingSmall with title "Capacity Planning Reports"
    - Integrate CapacityFilters component
    - Display metrics grid using CapacityMetricCard components
    - Include U-space utilization metric
    - Include power utilization metric (when data available)
    - Include port capacity grid
    - Display RackCapacityTable for detailed rack list
    - Include ExportButtons component
    - Add skeleton loading states for filter changes
    - Support dark mode using dark: prefixes
    - Implement responsive layout (mobile-first)
  - [x] 5.8 Generate Wayfinder actions
    - Run `php artisan wayfinder:generate`
    - Verify CapacityReportController actions available in @/actions
  - [x] 5.9 Ensure UI component tests pass
    - Run ONLY the 4-6 tests written in 5.1
    - Verify components render correctly
    - Verify filter interactions work

**Acceptance Criteria:**
- The 4-6 tests written in 5.1 pass
- Cascading filters work correctly
- Metric cards display with thresholds and sparklines
- Rack table supports sorting and drill-down
- Port capacity displays by type
- Export buttons trigger downloads
- Dark mode fully supported
- Responsive on all breakpoints

---

#### Task Group 6: Historical Trends and Sparklines
**Dependencies:** Task Groups 4, 5
**Complexity:** Medium

- [x] 6.0 Complete historical trend visualization
  - [x] 6.1 Write 3-4 focused tests for trend functionality
    - Test sparkline data populated from capacity snapshots
    - Test trend calculation between current and previous week
    - Test empty snapshot handling (no historical data)
    - Test multiple weeks of trend data display
  - [x] 6.2 Update CapacityReportController to include historical data
    - Query CapacitySnapshot for selected datacenter (last 7-12 weeks)
    - Format snapshot data for sparkline consumption
    - Calculate week-over-week trend percentage
    - Include in Inertia props
  - [x] 6.3 Create HistoricalTrendChart.vue component
    - Location: resources/js/components/CapacityReports/HistoricalTrendChart.vue
    - Use Chart.js (vue-chartjs) consistent with SparklineChart
    - Props: data (array of weekly values), labels, title
    - Display line chart with data points
    - Support both U-space and power utilization data
    - Include tooltip on hover
  - [x] 6.4 Integrate historical trends into Index page
    - Add trend section below summary metrics
    - Display HistoricalTrendChart for rack utilization over time
    - Display power utilization trend (when data available)
    - Show "No historical data available" when no snapshots exist
  - [x] 6.5 Ensure trend tests pass
    - Run ONLY the 3-4 tests written in 6.1
    - Verify trend data displays correctly

**Acceptance Criteria:**
- The 3-4 tests written in 6.1 pass
- Sparkline shows historical capacity data
- Week-over-week trend calculated correctly
- Empty state handled gracefully
- Charts render with consistent styling

---

### Testing and Integration

#### Task Group 7: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-6
**Complexity:** Medium

- [x] 7.0 Review existing tests and fill critical gaps only
  - [x] 7.1 Review tests from Task Groups 1-6
    - Review 4-6 tests from database layer (Task 1.1)
    - Review 5-7 tests from capacity calculation (Task 2.1)
    - Review 4-6 tests from snapshot/export services (Task 3.1)
    - Review 5-7 tests from API layer (Task 4.1)
    - Review 4-6 tests from UI components (Task 5.1)
    - Review 3-4 tests from historical trends (Task 6.1)
    - Total existing tests: approximately 25-36 tests
  - [x] 7.2 Analyze test coverage gaps for Capacity Planning Reports feature
    - Identify critical user workflows lacking coverage
    - Focus ONLY on gaps related to this feature
    - Prioritize end-to-end workflows over unit gaps
    - Check: full filter-to-export workflow tested?
    - Check: snapshot capture and display workflow tested?
    - Check: user access control across all endpoints tested?
  - [x] 7.3 Write up to 10 additional strategic tests maximum
    - Add integration test for complete page load with all components
    - Add test for PDF generation with real data
    - Add test for CSV export content validation
    - Add test for cascading filter data consistency
    - Add end-to-end test for filter > view > export workflow
    - Focus on critical paths, skip edge cases
  - [x] 7.4 Run feature-specific tests only
    - Run ONLY tests related to Capacity Planning Reports feature
    - Expected total: approximately 35-46 tests maximum
    - Do NOT run entire application test suite
    - Verify all critical workflows pass
  - [x] 7.5 Fix any failing tests
    - Address test failures related to this feature only
    - Do not refactor unrelated code
  - [x] 7.6 Verify feature works end-to-end
    - Manually test complete user workflow
    - Verify navigation to Capacity Reports page
    - Test filter cascading behavior
    - Verify metric cards display correctly
    - Test PDF export downloads
    - Test CSV export downloads
    - Verify dark mode styling
    - Test responsive layout on mobile

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 35-46 tests total)
- Critical user workflows for this feature are covered
- No more than 10 additional tests added to fill gaps
- Testing focused exclusively on Capacity Planning Reports feature
- End-to-end workflow verified manually

---

## Execution Order

Recommended implementation sequence:

1. **Database Layer (Task Group 1)** - Foundation migrations and models
2. **Capacity Calculation Service (Task Group 2)** - Core business logic
3. **Snapshot and Export Services (Task Group 3)** - Background jobs and file generation
4. **API Layer (Task Group 4)** - Controller, routes, and navigation
5. **Frontend Components (Task Group 5)** - Vue components and main page
6. **Historical Trends (Task Group 6)** - Trend visualization features
7. **Test Review (Task Group 7)** - Gap analysis and integration testing

---

## Technical Notes

### Existing Patterns to Reuse
- `DashboardController::getAccessibleDatacenters()` - User access control
- `DashboardController::calculateRackUtilization()` - U-space calculation
- `DiscrepancyFilters.vue` - Cascading filter pattern
- `MetricCard` / `SparklineChart` - Metric visualization
- `AuditReportService` - PDF generation with DomPDF
- `AbstractDataExport` - CSV/Excel export base class

### Key Files to Reference
- `/Users/helderdene/rackaudit/app/Http/Controllers/DashboardController.php`
- `/Users/helderdene/rackaudit/resources/js/pages/Dashboard.vue`
- `/Users/helderdene/rackaudit/resources/js/components/Discrepancies/DiscrepancyFilters.vue`
- `/Users/helderdene/rackaudit/app/Services/AuditReportService.php`
- `/Users/helderdene/rackaudit/app/Exports/AbstractDataExport.php`
- `/Users/helderdene/rackaudit/app/Models/Rack.php`
- `/Users/helderdene/rackaudit/app/Models/Device.php`

### Dependencies
- Barryvdh\DomPDF - PDF generation
- Maatwebsite\Excel - CSV/Excel export
- Chart.js (vue-chartjs) - Data visualization
- Tailwind CSS 4 - Styling

### Out of Scope (Do Not Implement)
- Predictive forecasting or capacity projection algorithms
- External power monitoring system integration (PDU SNMP, etc.)
- Automated capacity alerts or email notifications
- Real-time power monitoring with live updates
- Machine learning-based capacity predictions
- Cooling capacity tracking
- Network bandwidth capacity analysis
- Custom report templates or report builder
- Scheduled report delivery via email
