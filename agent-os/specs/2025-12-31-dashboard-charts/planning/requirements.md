# Spec Requirements: Dashboard Charts

## Initial Description
Interactive charts for capacity trends, audit metrics, and activity patterns using Chart.js

## Requirements Discussion

### First Round Questions

**Q1:** Where should the charts be placed on the Dashboard?
**Answer:** Add interactive charts below the current metric cards on the main Dashboard (`/dashboard`)

**Q2:** What types of capacity trend charts are needed?
**Answer:** Line chart showing rack utilization percentage over time with configurable time ranges (7 days, 30 days, 90 days). Include device count trends.

**Q3:** What audit metrics should be visualized?
**Answer:** Include simplified versions of audit charts on main Dashboard - severity distribution (donut chart) and completion trend (line chart).

**Q4:** What activity patterns should be shown?
**Answer:** Chart showing activity by entity type (devices, racks, connections, etc.)

**Q5:** What level of chart interactivity is required?
**Answer:** Hover tooltips showing exact values, click-to-drill-down functionality, responsive sizing for mobile/tablet. No zooming/panning needed.

**Q6:** How should time period filtering work?
**Answer:** Charts respond to existing datacenter filter. Add time period filter (Last 7 days, 30 days, 90 days).

**Q7:** Is historical data available or does it need to be created?
**Answer:** Create a data snapshot mechanism that captures daily metrics. New database table(s) for storing historical metric snapshots.

**Q8:** What should be excluded from this feature?
**Answer:** No real-time updates, no export functionality, no custom chart configuration.

### Existing Code to Reference

**Similar Features Identified:**
- Feature: SparklineChart - Path: `/resources/js/components/dashboard/SparklineChart.vue`
- Feature: HistoricalTrendChart - Path: `/resources/js/components/CapacityReports/HistoricalTrendChart.vue`
- Feature: SeverityDistributionChart - Path: `/resources/js/components/audits/SeverityDistributionChart.vue`
- Feature: AuditCompletionTrendChart - Path: `/resources/js/components/audits/AuditCompletionTrendChart.vue`
- Feature: Audits Dashboard - Path: `/resources/js/Pages/Audits/Dashboard.vue`

Components to potentially reuse: Existing Chart.js chart components with established patterns for line charts, donut charts, and sparklines.

Backend logic to reference: Audit dashboard data aggregation patterns.

### Follow-up Questions
None required - requirements were comprehensive.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A

## Requirements Summary

### Functional Requirements
- Display interactive charts below metric cards on main Dashboard
- Capacity trend line chart with rack utilization percentage over time
- Device count trend visualization
- Audit severity distribution donut chart (simplified version)
- Audit completion trend line chart (simplified version)
- Activity patterns chart by entity type (devices, racks, connections)
- Hover tooltips with exact values on all charts
- Click-to-drill-down functionality for detailed views
- Responsive chart sizing for mobile and tablet devices
- Time period filter (7 days, 30 days, 90 days)
- Integration with existing datacenter filter
- Daily metric snapshot mechanism for historical data

### Reusability Opportunities
- `SparklineChart.vue` - Base chart component patterns
- `HistoricalTrendChart.vue` - Time-series chart implementation
- `SeverityDistributionChart.vue` - Donut chart for severity data
- `AuditCompletionTrendChart.vue` - Trend line chart patterns
- `Audits/Dashboard.vue` - Dashboard layout and data fetching patterns

### Scope Boundaries
**In Scope:**
- Capacity trend charts (utilization percentage, device counts)
- Audit metric charts (severity distribution, completion trends)
- Activity patterns chart by entity type
- Time period filtering (7, 30, 90 days)
- Datacenter filter integration
- Hover tooltips and click-to-drill-down
- Responsive design for all screen sizes
- Database tables for historical metric snapshots
- Daily data snapshot mechanism

**Out of Scope:**
- Real-time chart updates
- Chart data export functionality
- Custom chart configuration by users
- Zoom and pan functionality

### Technical Considerations
- Use Chart.js library (consistent with existing chart components)
- New database migration(s) needed for metric snapshots table
- Scheduled task/command for daily metric capture
- API endpoints for chart data with time range parameters
- Deferred props for chart data to improve page load
- Integration with existing dashboard controller and page
