# Specification: Dashboard Charts

## Goal
Add interactive charts below the existing metric cards on the main Dashboard (`/dashboard`) to visualize capacity trends, audit metrics, and activity patterns over time using Chart.js.

## User Stories
- As a datacenter manager, I want to see rack utilization and device count trends over time so that I can identify capacity planning needs before resources run out.
- As an IT manager, I want to view audit severity distribution and completion trends on the main dashboard so that I can monitor compliance status at a glance.

## Specific Requirements

**Capacity Trend Line Chart**
- Display rack utilization percentage over time as a line chart with filled area
- Support configurable time ranges: 7 days, 30 days, 90 days
- Show hover tooltips with exact utilization values and dates
- Include trend indicator showing direction (up/down/stable) and latest value
- Pull data from `capacity_snapshots` table via new API endpoint
- Handle empty state gracefully when no historical data exists

**Device Count Trend Line Chart**
- Display total device count over time as a second line chart
- Share the same time range filter as capacity trend chart
- Show hover tooltips with device counts and dates
- Calculate device count at each date from creation timestamps
- Use different color from capacity chart (green vs blue) for visual distinction

**Audit Severity Distribution Donut Chart**
- Display simplified version of severity distribution from Audits Dashboard
- Show critical, high, medium, low severity counts as donut segments
- Center text shows total open findings count
- Click-to-drill-down: clicking a segment navigates to Findings filtered by severity
- Reuse existing `SeverityDistributionChart.vue` component patterns

**Audit Completion Trend Line Chart**
- Show count of completed audits over the selected time period
- Display as line chart with data points for each day/week
- Include total completions count in header
- Reuse patterns from existing `AuditCompletionTrendChart.vue`

**Activity By Entity Type Chart**
- Bar or horizontal bar chart showing activity counts by entity type
- Entity types: Devices, Racks, Connections, Audits, Findings
- Data sourced from `activity_logs` table grouped by `subject_type`
- Hover tooltips show exact counts per entity type

**Time Period Filter Integration**
- Add time period dropdown filter: "Last 7 days", "Last 30 days", "Last 90 days"
- Filter applies to all charts simultaneously via query parameter
- Place filter next to existing datacenter filter in Dashboard header
- Charts respond to existing datacenter filter in addition to time period

**Dashboard Layout Updates**
- Add new section below existing metric cards grid
- Use 2-column grid layout for charts on desktop (lg breakpoint)
- Stack charts to single column on mobile and tablet
- Wrap each chart in Card component consistent with existing dashboard styling

**Data Snapshot Enhancement**
- Extend existing `CaptureCapacitySnapshotJob` to run daily instead of weekly
- Add device count field to `capacity_snapshots` table
- Add new `dashboard_snapshots` table for audit/activity metrics not in capacity snapshots
- Create `CaptureDashboardMetricsJob` to capture daily audit/finding/activity counts

## Existing Code to Leverage

**`HistoricalTrendChart.vue`**
- Provides complete line chart implementation with Chart.js integration
- Includes responsive sizing, hover tooltips, trend direction indicators
- Has date label formatting and empty state handling
- Use as primary template for capacity and device count trend charts

**`SeverityDistributionChart.vue`**
- Complete donut chart with click-to-navigate functionality
- Severity color mapping and percentage calculations
- Center text display and legend positioning
- Reuse directly or adapt for simplified dashboard version

**`AuditCompletionTrendChart.vue`**
- Line chart with time period label formatting
- Data sampling for large datasets to prevent overcrowding
- Total count header display pattern
- Use as template for audit completion trend on main dashboard

**`DashboardController.php`**
- Existing filter handling for datacenter_id
- Pattern for calculating metrics with datacenter access control
- Sparkline data generation approach for 7-day trends
- Extend with new `chartData()` method or separate controller action

**`CapacitySnapshot` model and `CaptureCapacitySnapshotJob`**
- Existing infrastructure for storing historical capacity data
- Job runs via scheduler, captures rack/power utilization
- Extend to include device_count and run daily
- Use as model for new dashboard_snapshots table

## Out of Scope
- Real-time chart updates or live data streaming
- Chart data export functionality (CSV, PDF, image)
- Custom chart configuration by users (colors, styles, chart types)
- Zoom and pan functionality on charts
- Power utilization trends (use existing Capacity Reports page)
- Date range picker with custom start/end dates (only preset ranges)
- Chart comparison mode (side-by-side different time periods)
- Drill-down from capacity charts to specific racks or devices
- Print-optimized chart styles
- Chart annotations or markers for events
