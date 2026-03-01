# Specification: Capacity Planning Reports

## Goal
Create a dedicated Capacity Planning Reports page that displays rack utilization, power consumption, and available capacity metrics across datacenters, with historical trend tracking, drill-down navigation, and export capabilities.

## User Stories
- As a datacenter manager, I want to view current capacity metrics filtered by datacenter, room, and row so that I can identify areas approaching capacity limits
- As an IT planner, I want to export capacity reports as PDF and CSV so that I can share data with stakeholders and perform analysis in spreadsheets

## Specific Requirements

**Capacity Planning Reports Page**
- Add dedicated page accessible from main navigation under "Capacity Reports"
- Follow existing page structure pattern from Dashboard.vue and Reports/Index.vue
- Include HeadingSmall component with title "Capacity Planning Reports" and description
- Implement responsive layout with mobile-first approach using Tailwind CSS 4
- Support dark mode using existing dark: prefixes

**Cascading Location Filters**
- Implement datacenter > room > row filter hierarchy similar to DiscrepancyFilters.vue pattern
- Room dropdown appears only when datacenter selected, row dropdown appears only when room selected
- Apply user access control using getAccessibleDatacenters() pattern from DashboardController
- Filters trigger debounced page reload using Inertia router.get() with preserveState

**Rack U-Space Utilization Metrics**
- Calculate utilization: (sum of device U-heights) / (sum of rack U-heights) * 100
- Reuse calculateRackUtilization() logic from DashboardController
- Display total U-space, used U-space, and available U-space per filtered scope
- Highlight racks at 80%+ utilization with warning color, 90%+ with critical color
- Show list of racks approaching capacity threshold with drill-down links

**Power Consumption Tracking**
- Add power_draw_watts nullable integer column to devices table via migration
- Add power_capacity_watts nullable integer column to racks table via migration
- Calculate power utilization: (sum of device power_draw_watts) / (rack power_capacity_watts) * 100
- Display power headroom (capacity minus consumption) per rack and aggregated by filter scope
- Handle null values gracefully (exclude from calculations, show "Not configured" in UI)

**Port Capacity Analysis**
- Group ports by PortType enum (Ethernet, Fiber, Power)
- Calculate per type: total ports count, connected ports count (has connection), available ports count
- Query ports through device > rack > row > room > datacenter relationship chain
- Display as table or grid with type labels from PortType::label() method

**Historical Capacity Snapshots**
- Create capacity_snapshots table: id, datacenter_id, snapshot_date, rack_utilization_percent, power_utilization_percent, total_u_space, used_u_space, total_power_capacity, total_power_consumption, port_stats (JSON), created_at
- Schedule weekly snapshot job using Laravel scheduler in routes/console.php
- Retain snapshots for 52 weeks (1 year), delete older records via cleanup job
- Display trend charts using SparklineChart component pattern from Dashboard

**On-Screen Report Viewing**
- Default view shows live calculated metrics without generating file
- Use MetricCard components for key summary statistics
- Display drill-down tables showing per-rack details with sorting
- Support clicking through from aggregated metrics to individual rack details

**PDF Export**
- Create CapacityReportService following AuditReportService pattern
- Use Barryvdh\DomPDF for PDF generation with A4 portrait layout
- Create blade template at resources/views/pdf/capacity-report.blade.php
- Include executive summary, utilization charts, and detailed rack tables
- Queue large report generation using job pattern from GenerateAuditReportJob

**CSV/Excel Export**
- Create CapacityReportExport extending AbstractDataExport
- Implement transformRow() to format capacity metrics per rack
- Apply current filter scope to exported data
- Include columns: datacenter, room, row, rack, u_capacity, u_used, u_available, power_capacity, power_used, power_available

## Existing Code to Leverage

**DashboardController.php**
- getAccessibleDatacenters() for user datacenter access control with ADMIN_ROLES constant
- calculateRackUtilization() for U-space calculation formula with device eager loading
- getRackUtilizationMetric() pattern for building metric response with trend and sparkline
- Datacenter filter validation and query building patterns

**Dashboard.vue and Components**
- MetricCard component for displaying capacity metrics with sparkline visualization
- MetricCardSkeleton for loading states during filter changes
- SparklineChart for historical trend visualization using Chart.js
- Responsive filter layout pattern with debounced updates

**DiscrepancyFilters.vue**
- Cascading datacenter > room filter pattern with watch() for resetting child filters
- Mobile collapsible filters with desktop sidebar layout
- hasActiveFilters computed property and clearFilters() function
- selectClass styling constant for consistent select appearance

**AuditReportService.php and AuditReportController.php**
- DomPDF integration pattern with Pdf::loadView() and setPaper()
- File storage pattern using Storage::disk('local')->put()
- Report download streaming with proper Content-Type headers
- formatFileSize() helper for displaying file sizes

**AbstractDataExport.php**
- Base class for Excel exports with filters support
- query() and transformRow() abstract method pattern
- FromCollection interface implementation for Maatwebsite\Excel

## Out of Scope
- Predictive forecasting or capacity projection algorithms
- External power monitoring system integration (PDU SNMP, etc.)
- Automated capacity alerts or email notifications
- Real-time power monitoring with live updates
- Machine learning-based capacity predictions
- Cooling capacity tracking
- Network bandwidth capacity analysis
- Custom report templates or report builder
- Scheduled report delivery via email
- Comparison between multiple time periods in a single view
