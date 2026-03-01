# Task Breakdown: Dashboard Charts

## Overview
Total Tasks: 5 Task Groups with approximately 35 sub-tasks

This feature adds interactive Chart.js charts below the existing metric cards on the main Dashboard to visualize capacity trends, audit metrics, and activity patterns over time.

## Task List

### Database Layer

#### Task Group 1: Data Snapshot Infrastructure
**Dependencies:** None

- [x] 1.0 Complete database layer for dashboard snapshots
  - [x] 1.1 Write 4-6 focused tests for snapshot functionality
    - Test CapacitySnapshot model with device_count field
    - Test DashboardSnapshot model creation and relationships
    - Test CaptureCapacitySnapshotJob includes device_count
    - Test CaptureDashboardMetricsJob captures audit/activity metrics
    - Test daily scheduling works for snapshot jobs
  - [x] 1.2 Create migration to add device_count to capacity_snapshots
    - Add `device_count` integer column to existing table
    - Field should be nullable for backward compatibility with existing records
  - [x] 1.3 Create migration for dashboard_snapshots table
    - Fields: `id`, `datacenter_id`, `snapshot_date`
    - Fields: `open_findings_count`, `critical_findings_count`, `high_findings_count`
    - Fields: `medium_findings_count`, `low_findings_count`
    - Fields: `pending_audits_count`, `completed_audits_count`
    - Fields: `activity_count`, `activity_by_entity` (JSON)
    - Unique constraint on `datacenter_id` + `snapshot_date`
    - Add indexes for efficient querying
    - Foreign key to datacenters table with cascade delete
  - [x] 1.4 Create DashboardSnapshot model
    - Fillable fields matching migration columns
    - Casts for date, integers, and JSON
    - BelongsTo relationship with Datacenter
    - Reuse pattern from: `/Users/helderdene/rackaudit/app/Models/CapacitySnapshot.php`
  - [x] 1.5 Update CapacitySnapshot model
    - Add device_count to fillable array
    - Add device_count cast as integer
    - Reference: `/Users/helderdene/rackaudit/app/Models/CapacitySnapshot.php`
  - [x] 1.6 Create DashboardSnapshotFactory
    - Follow pattern from existing CapacitySnapshotFactory
    - Generate realistic test data for all metrics
    - Reference: `/Users/helderdene/rackaudit/database/factories/CapacitySnapshotFactory.php`
  - [x] 1.7 Ensure database layer tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify migrations run successfully
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- Migrations run without errors
- Models have correct casts and relationships
- Factories generate valid test data

---

#### Task Group 2: Scheduled Jobs and Data Capture
**Dependencies:** Task Group 1

- [x] 2.0 Complete scheduled job infrastructure
  - [x] 2.1 Write 4-6 focused tests for scheduled jobs
    - Test CaptureCapacitySnapshotJob captures device_count
    - Test CaptureDashboardMetricsJob captures all required metrics
    - Test jobs handle empty datacenters gracefully
    - Test job error handling and logging
  - [x] 2.2 Update CaptureCapacitySnapshotJob to include device_count
    - Calculate device count per datacenter during snapshot
    - Store in the new device_count field
    - Maintain backward compatibility with existing logic
    - Reference: `/Users/helderdene/rackaudit/app/Jobs/CaptureCapacitySnapshotJob.php`
  - [x] 2.3 Create CaptureDashboardMetricsJob
    - Capture daily audit/finding counts by severity
    - Capture activity counts by entity type
    - Use updateOrCreate pattern for idempotency
    - Reuse metric calculation patterns from DashboardController
    - Reference: `/Users/helderdene/rackaudit/app/Http/Controllers/DashboardController.php`
  - [x] 2.4 Update scheduler for daily execution
    - Change CaptureCapacitySnapshotJob from weekly to daily
    - Add CaptureDashboardMetricsJob to daily schedule
    - Schedule both jobs at same time (e.g., 00:00)
    - Update CleanupOldSnapshotsJob to run daily as well
    - Reference: `/Users/helderdene/rackaudit/routes/console.php`
  - [x] 2.5 Ensure scheduled job tests pass
    - Run ONLY the 4-6 tests written in 2.1
    - Verify jobs execute without errors
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 2.1 pass
- Jobs capture all required metrics accurately
- Scheduler runs jobs daily
- Jobs handle edge cases gracefully

---

### API Layer

#### Task Group 3: Chart Data API Endpoints
**Dependencies:** Task Group 2

- [x] 3.0 Complete API layer for chart data
  - [x] 3.1 Write 4-6 focused tests for API endpoints
    - Test chartData endpoint returns all required chart datasets
    - Test time period filter (7, 30, 90 days) works correctly
    - Test datacenter filter integration
    - Test user access control for datacenter filtering
    - Test empty state response when no historical data exists
  - [x] 3.2 Add chartData method to DashboardController
    - Accept `time_period` parameter (7_days, 30_days, 90_days)
    - Accept `datacenter_id` parameter (existing filter)
    - Apply user access control using existing pattern
    - Return structured response with all chart datasets
    - Reference: `/Users/helderdene/rackaudit/app/Http/Controllers/DashboardController.php`
  - [x] 3.3 Implement capacity trend data aggregation
    - Query CapacitySnapshot for date range
    - Group by date, aggregate utilization percentages
    - Handle multi-datacenter aggregation when no filter applied
    - Return labels (dates) and data (percentages) arrays
  - [x] 3.4 Implement device count trend data aggregation
    - Query CapacitySnapshot for device_count by date
    - Aggregate counts across datacenters when needed
    - Return labels (dates) and data (counts) arrays
  - [x] 3.5 Implement audit severity distribution data
    - Query current open findings grouped by severity
    - Calculate total and per-severity counts
    - Match format expected by SeverityDistributionChart
    - Reference: `/Users/helderdene/rackaudit/resources/js/components/audits/SeverityDistributionChart.vue`
  - [x] 3.6 Implement audit completion trend data
    - Query DashboardSnapshot for completed_audits_count
    - Calculate trend data points for selected period
    - Match format expected by AuditCompletionTrendChart
    - Reference: `/Users/helderdene/rackaudit/resources/js/components/audits/AuditCompletionTrendChart.vue`
  - [x] 3.7 Implement activity by entity type data
    - Query DashboardSnapshot activity_by_entity JSON
    - Aggregate by entity type (Device, Rack, Connection, Audit, Finding)
    - Return labels (entity types) and data (counts) arrays
  - [x] 3.8 Add route for chartData endpoint
    - Add GET route to web.php or extend existing dashboard route
    - Ensure proper middleware (auth, verified)
    - Consider using deferred props for chart data
  - [x] 3.9 Ensure API layer tests pass
    - Run ONLY the 4-6 tests written in 3.1
    - Verify all endpoints return correct data structure
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 3.1 pass
- All chart data endpoints return correctly formatted data
- Time period and datacenter filters work correctly
- Access control respects user permissions

---

### Frontend Components

#### Task Group 4: Chart Components and Dashboard Integration
**Dependencies:** Task Group 3

- [x] 4.0 Complete frontend chart components
  - [x] 4.1 Write 4-6 focused tests for UI components
    - Test CapacityTrendChart renders with data
    - Test DeviceCountTrendChart renders with data
    - Test charts display empty state when no data
    - Test TimePeriodFilter changes update charts
    - Test charts are responsive at different breakpoints
  - [x] 4.2 Create TimePeriodFilter component
    - Dropdown with options: "Last 7 days", "Last 30 days", "Last 90 days"
    - Emit selected value on change
    - Match existing select styling in Dashboard
    - Reference: `/Users/helderdene/rackaudit/resources/js/Pages/Dashboard.vue` (datacenter filter styling)
  - [x] 4.3 Create CapacityTrendChart component
    - Reuse HistoricalTrendChart as base template
    - Display rack utilization percentage line chart with filled area
    - Blue color (rgb(59, 130, 246)) to match existing design
    - Include trend indicator and latest value display
    - Handle empty state gracefully
    - Reference: `/Users/helderdene/rackaudit/resources/js/components/CapacityReports/HistoricalTrendChart.vue`
  - [x] 4.4 Create DeviceCountTrendChart component
    - Adapt HistoricalTrendChart for device counts
    - Green color (rgb(34, 197, 94)) for visual distinction
    - Remove percentage formatting (use raw counts)
    - Display trend indicator with count units
    - Handle empty state gracefully
    - Reference: `/Users/helderdene/rackaudit/resources/js/components/CapacityReports/HistoricalTrendChart.vue`
  - [x] 4.5 Create DashboardSeverityChart component
    - Simplified version of SeverityDistributionChart for dashboard
    - Donut chart with severity color mapping
    - Center text shows total open findings
    - Click-to-navigate to Findings page filtered by severity
    - Handle empty state with helpful message
    - Reference: `/Users/helderdene/rackaudit/resources/js/components/audits/SeverityDistributionChart.vue`
  - [x] 4.6 Create DashboardCompletionChart component
    - Simplified version of AuditCompletionTrendChart
    - Line chart showing completed audits over time
    - Include total completions in header
    - Handle empty state gracefully
    - Reference: `/Users/helderdene/rackaudit/resources/js/components/audits/AuditCompletionTrendChart.vue`
  - [x] 4.7 Create ActivityByEntityChart component
    - Horizontal bar chart using Chart.js
    - Entity types: Devices, Racks, Connections, Audits, Findings
    - Consistent color scheme with other dashboard elements
    - Hover tooltips with exact counts
    - Handle empty state gracefully
  - [x] 4.8 Create ChartCardSkeleton component
    - Pulsing skeleton for chart loading states
    - Match Card component dimensions
    - Follow pattern from existing skeleton components
    - Reference: `/Users/helderdene/rackaudit/resources/js/components/dashboard/MetricCardSkeleton.vue`
  - [x] 4.9 Update Dashboard.vue with charts section
    - Add TimePeriodFilter next to datacenter filter
    - Add new section below metric cards grid
    - Use 2-column grid layout on desktop (lg breakpoint)
    - Stack to single column on mobile/tablet
    - Wrap each chart in Card component
    - Reference: `/Users/helderdene/rackaudit/resources/js/Pages/Dashboard.vue`
  - [x] 4.10 Implement deferred props for chart data
    - Use Inertia deferred props pattern for chart data
    - Show skeleton states while chart data loads
    - Improve initial page load performance
  - [x] 4.11 Add chart components to dashboard index
    - Export new components from dashboard components index
    - Reference: `/Users/helderdene/rackaudit/resources/js/components/dashboard/index.ts`
  - [x] 4.12 Ensure UI component tests pass
    - Run ONLY the 4-6 tests written in 4.1
    - Verify charts render correctly with test data
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 4.1 pass
- All charts render correctly with data
- Empty states display appropriate messages
- Responsive layout works at all breakpoints
- Time period filter updates all charts simultaneously

---

### Testing

#### Task Group 5: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-4

- [x] 5.0 Review existing tests and fill critical gaps only
  - [x] 5.1 Review tests from Task Groups 1-4
    - Review the 6 tests from database layer (Task 1.1)
    - Review the 6 tests from scheduled jobs (Task 2.1)
    - Review the 6 tests from API layer (Task 3.1)
    - Review the 6 tests from UI components (Task 4.1)
    - Total existing tests: 24 tests
  - [x] 5.2 Analyze test coverage gaps for THIS feature only
    - Identified critical workflows lacking test coverage:
      - Multi-datacenter aggregation without filters
      - Weighted average capacity calculation
      - Chronological ordering of trend data
      - IT Manager role access (equivalent to Administrator)
      - Invalid/default time period handling
      - Activity by entity aggregation across snapshots
      - Full flow: job capture -> API query integration
  - [x] 5.3 Write up to 10 additional strategic tests maximum
    - Added 8 integration tests in DashboardChartsIntegrationTest.php:
      1. chart data aggregates multiple datacenters when no filter applied
      2. capacity trend uses weighted average calculation by U-space
      3. chart data returns labels in chronological order
      4. IT Manager role has full datacenter access like Administrator
      5. invalid time period parameter defaults to 7 days
      6. default time period is 7 days when not specified
      7. full flow from job capture to API query returns consistent data
      8. activity by entity aggregates across multiple days in time period
  - [x] 5.4 Run feature-specific tests only
    - Ran all tests in tests/Feature/DashboardCharts/
    - Total: 32 tests passed (292 assertions)
    - All critical workflows verified

**Acceptance Criteria:**
- All feature-specific tests pass (32 tests total)
- Critical user workflows for dashboard charts are covered
- 8 additional tests added (within 10 test maximum)
- Testing focused exclusively on this spec's feature requirements

---

## Execution Order

Recommended implementation sequence:

1. **Database Layer (Task Group 1)** - Migrations, models, factories
2. **Scheduled Jobs (Task Group 2)** - Data capture infrastructure
3. **API Layer (Task Group 3)** - Chart data endpoints
4. **Frontend Components (Task Group 4)** - Charts and dashboard integration
5. **Test Review (Task Group 5)** - Gap analysis and coverage

---

## Existing Code to Leverage

| Component | Path | Usage |
|-----------|------|-------|
| HistoricalTrendChart | `/Users/helderdene/rackaudit/resources/js/components/CapacityReports/HistoricalTrendChart.vue` | Template for capacity and device count trend charts |
| SeverityDistributionChart | `/Users/helderdene/rackaudit/resources/js/components/audits/SeverityDistributionChart.vue` | Template for dashboard severity donut chart |
| AuditCompletionTrendChart | `/Users/helderdene/rackaudit/resources/js/components/audits/AuditCompletionTrendChart.vue` | Template for audit completion trend chart |
| DashboardController | `/Users/helderdene/rackaudit/app/Http/Controllers/DashboardController.php` | Extend with chartData method, reuse filter/access patterns |
| CapacitySnapshot | `/Users/helderdene/rackaudit/app/Models/CapacitySnapshot.php` | Model to extend with device_count |
| CaptureCapacitySnapshotJob | `/Users/helderdene/rackaudit/app/Jobs/CaptureCapacitySnapshotJob.php` | Update for daily execution and device_count |
| Dashboard.vue | `/Users/helderdene/rackaudit/resources/js/Pages/Dashboard.vue` | Extend with charts section and filter |
| MetricCardSkeleton | `/Users/helderdene/rackaudit/resources/js/components/dashboard/MetricCardSkeleton.vue` | Pattern for chart skeleton components |
| CapacitySnapshotFactory | `/Users/helderdene/rackaudit/database/factories/CapacitySnapshotFactory.php` | Pattern for DashboardSnapshotFactory |
| routes/console.php | `/Users/helderdene/rackaudit/routes/console.php` | Update scheduler configuration |

---

## Notes

- **Chart.js Integration**: All charts use Chart.js via vue-chartjs, which is already installed and configured in the project
- **Daily vs Weekly Snapshots**: CaptureCapacitySnapshotJob currently runs weekly; this feature changes it to daily
- **Deferred Props**: Use Inertia v2 deferred props for chart data to improve initial page load
- **Access Control**: Chart data must respect existing datacenter access control patterns
- **Color Scheme**: Maintain visual distinction between charts (blue for capacity, green for devices/completions, severity colors for findings)
