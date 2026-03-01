# Spec Requirements: Capacity Planning Reports

## Initial Description
Capacity Planning Reports - Reports showing rack utilization, power consumption, and available capacity across datacenters.

## Requirements Discussion

### First Round Questions

**Q1:** I assume this feature should display a "Capacity Planning Reports" page accessible from the main navigation, where users can view current capacity metrics and generate/export reports. Is that correct, or do you envision this as part of an existing page (like the Dashboard)?
**Answer:** Yes, a dedicated "Capacity Planning Reports" page with drill-down navigation.

**Q2:** For rack utilization metrics, I'm thinking we calculate: (sum of device U-heights) / (sum of rack U-heights) * 100 - the same approach used in the Dashboard. Should we add additional utilization metrics like "busiest racks" or "racks with most available space"?
**Answer:** Yes, show utilization metrics. Include available U-space per rack and highlight racks approaching capacity.

**Q3:** I assume we'll need to add power consumption tracking to devices. Currently Device has no power-related fields. Should we add a `power_draw_watts` field to the Device model and track total power vs. rack power capacity?
**Answer:** Yes, add `power_draw_watts` to devices and `power_capacity_watts` to racks for power capacity tracking.

**Q4:** For port capacity analysis, I assume we should group ports by type (Ethernet, Fiber, Power) and show: total ports, connected ports, and available ports. Should we also show port utilization by device type?
**Answer:** Yes, show port capacity by type with total, connected, and available counts.

**Q5:** I assume reports should be filterable by datacenter, and optionally by room and row, with the ability to drill down to individual rack details. Is that the correct navigation hierarchy?
**Answer:** Correct - filterable by datacenter, room, row with drill-down to rack level.

**Q6:** For the "available capacity" view, I'm thinking we show: available U-space, power headroom (capacity minus consumption), and perhaps empty rack positions. Should we also highlight "reserved" or "planned" rack spaces if that concept exists?
**Answer:** Yes, show available U-space, power headroom, and empty/reserved rack positions.

**Q7:** I assume users will want to export these reports as PDF for presentations and CSV/Excel for data analysis. Should on-screen viewing also be supported for quick reference without generating a file?
**Answer:** Yes, support on-screen viewing plus PDF and CSV/Excel export options.

**Q8:** Should we include historical capacity trends (e.g., utilization over the past 6-12 months), or focus only on current point-in-time capacity snapshots?
**Answer:** Include historical trends with weekly snapshots retained for 1 year.

**Q9:** For the visual presentation of utilization data, should we use Chart.js charts similar to the Dashboard sparklines, or would you prefer different visualization approaches (bar charts, gauge charts, heatmaps)?
**Answer:** Use Chart.js charts consistent with Dashboard style.

**Q10:** Is there anything that should explicitly NOT be part of this initial implementation?
**Answer:** Out of scope: predictive forecasting, external power monitoring integration, automated capacity alerts.

### Existing Code to Reference

**Similar Features Identified:**
- Feature: Dashboard - Path: `/Users/helderdene/rackaudit/app/Http/Controllers/DashboardController.php`
  - Rack utilization calculation logic already exists
  - Datacenter filtering pattern with user access control
  - MetricCard and SparklineChart components for data visualization
- Feature: Dashboard Vue Components - Path: `/Users/helderdene/rackaudit/resources/js/pages/Dashboard.vue`
  - MetricCard, SparklineChart, OpenFindingsCard patterns
  - Responsive filter dropdowns with debounced updates
- Feature: Discrepancy Filters - Path: `/Users/helderdene/rackaudit/resources/js/components/Discrepancies/DiscrepancyFilters.vue`
  - Cascading datacenter > room filter pattern
  - Mobile/desktop responsive filter layouts
- Feature: Audit Reports - Path: `/Users/helderdene/rackaudit/app/Http/Controllers/AuditReportController.php`
  - Report listing with pagination and filters
  - PDF download functionality
- Feature: Reports Index Page - Path: `/Users/helderdene/rackaudit/resources/js/pages/Reports/Index.vue`
  - Report listing table with sorting and filtering
  - Download button patterns
- Feature: PDF Generation Service - Path: `/Users/helderdene/rackaudit/app/Services/AuditReportService.php`
  - Barryvdh\DomPDF pattern for PDF generation
  - Report storage and file size tracking
- Feature: Excel Exports - Path: `/Users/helderdene/rackaudit/app/Exports/AbstractDataExport.php`
  - Maatwebsite\Excel export pattern with filters
  - transformRow() and query() abstraction

### Follow-up Questions

**Follow-up 1:** How often should historical capacity snapshots be captured, and how long should they be retained?
**Answer:** Weekly snapshots, retain for 1 year.

**Follow-up 2:** For the power capacity baseline, should we add a `power_capacity_watts` field to the Rack model, or should power capacity be tracked at a different level (room, datacenter)?
**Answer:** Add `power_capacity_watts` field to the Rack model alongside `power_draw_watts` on devices.

**Follow-up 3:** Are there existing features in your codebase with similar patterns we should reference?
**Answer:** Search the codebase to find relevant existing features, components, and patterns.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
Not applicable - no visual assets were provided for this spec.

## Requirements Summary

### Functional Requirements
- Dedicated "Capacity Planning Reports" page in main navigation
- Rack U-space utilization metrics with percentage and available U-space per rack
- Highlight racks approaching capacity thresholds
- Power consumption tracking with new `power_draw_watts` field on Device model
- Power capacity tracking with new `power_capacity_watts` field on Rack model
- Power headroom calculation (capacity minus consumption)
- Port capacity by type (Ethernet, Fiber, Power) showing total, connected, and available
- Cascading filters: datacenter > room > row with drill-down to rack level
- Empty and reserved rack position tracking
- On-screen report viewing for quick reference
- PDF export for presentations
- CSV/Excel export for data analysis
- Historical capacity trends with weekly snapshots
- 1 year retention for historical data
- Chart.js visualizations consistent with Dashboard style

### Reusability Opportunities
- `DashboardController::calculateRackUtilization()` - Existing rack utilization logic
- `DashboardController::getAccessibleDatacenters()` - User access control pattern
- `MetricCard` component - For displaying capacity metrics with trends
- `SparklineChart` component - For historical trend visualization
- `DiscrepancyFilters.vue` - Cascading datacenter > room filter pattern
- `AuditReportController` - Report listing, filtering, and download patterns
- `AuditReportService` - PDF generation with DomPDF
- `AbstractDataExport` - Excel/CSV export base class
- Reports/Index.vue - Table layout with sorting, filtering, pagination

### Scope Boundaries
**In Scope:**
- Capacity Planning Reports page with navigation
- Rack U-space utilization metrics
- Power consumption and capacity tracking (new fields)
- Port capacity by type
- Datacenter > room > row > rack drill-down
- Available capacity view (U-space, power headroom, empty positions)
- On-screen viewing
- PDF export
- CSV/Excel export
- Historical weekly snapshots with 1 year retention
- Chart.js visualizations

**Out of Scope:**
- Predictive forecasting / capacity planning projections
- External power monitoring system integration
- Automated capacity alerts or notifications
- Real-time power monitoring
- Machine learning-based capacity predictions

### Technical Considerations
- Add `power_draw_watts` column to devices table (migration required)
- Add `power_capacity_watts` column to racks table (migration required)
- Create new capacity_snapshots table for historical data storage
- Scheduled job for weekly snapshot capture
- Snapshot cleanup job for 1-year retention policy
- Use existing Barryvdh\DomPDF for PDF generation
- Use existing Maatwebsite\Excel for CSV/Excel export
- Follow existing controller patterns (DashboardController, AuditReportController)
- Implement user datacenter access control (admin vs assigned users)
- Vue 3 + Inertia.js for frontend with existing component library
- Tailwind CSS 4 styling consistent with application design
- Chart.js for data visualization (vue-chartjs already installed)
