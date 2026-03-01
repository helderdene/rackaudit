# Spec Requirements: Connection Reports

## Initial Description
**Connection Reports** — Reports on connection inventory, cable types, and port utilization

## Requirements Discussion

### First Round Questions

**Q1:** I assume the report should follow the same cascading filter pattern as Capacity and Asset Reports (Datacenter > Room > Row/Rack). Is that correct, or should we use different filtering dimensions (e.g., filter by device type, port type)?
**Answer:** Correct - Follow the same cascading filter pattern (Datacenter > Room > Row/Rack)

**Q2:** I'm thinking we should include these key metrics for connection inventory: Total connections count, Connections by cable type (Cat5e, Cat6, Cat6a, Fiber SM, Fiber MM, Power cables), Connections by port type (Ethernet, Fiber, Power), Average cable length statistics. Is this the right set of metrics, or are there other connection properties you'd like to track?
**Answer:** Correct - Key metrics: Total connections, by cable type, by port type, average cable length

**Q3:** For port utilization, I assume we should show: Total ports vs. connected ports (utilization percentage), Port utilization broken down by port type (Ethernet/Fiber/Power), Port utilization by status (Available, Connected, Reserved, Disabled). Should we also show port utilization by device or by rack?
**Answer:** Correct - Port utilization: Total vs connected ports, by port type, by status

**Q4:** I assume we should include a paginated connections inventory table (similar to the device inventory table in Asset Reports) showing connection details like source device/port, destination device/port, cable type, cable length, and cable color. Is that correct?
**Answer:** Correct - Include paginated connections inventory table with source/dest device/port, cable type, length, color

**Q5:** Should we include any visualization/charts? For example: A pie/donut chart showing cable type distribution, A bar chart showing port utilization by type, A trend chart showing connection changes over time (if historical data is available)
**Answer:** Correct - Include visualizations (pie/donut for cable types, bar chart for port utilization)

**Q6:** I assume we should support PDF and CSV export functionality like the other reports. Is that correct, or do you need additional export formats?
**Answer:** Correct - Support PDF and CSV export like other reports

**Q7:** Should this report respect the same role-based access control as other reports (Administrator and IT Manager have full access, while Operators/Auditors only see assigned datacenters)?
**Answer:** Correct - Respect same role-based access control as other reports

**Q8:** Is there anything specific you want to exclude from this feature, or any future enhancements we should defer?
**Answer:** No exclusions mentioned

### Existing Code to Reference

**Similar Features Identified:**
- Feature: Capacity Reports - Path: `app/Http/Controllers/CapacityReportController.php`
- Feature: Asset Reports - Path: `app/Http/Controllers/AssetReportController.php`
- Components to potentially reuse:
  - `resources/js/Pages/AssetReports/Index.vue` - Page layout and structure
  - `resources/js/Pages/CapacityReports/Index.vue` - Page layout and structure
  - `resources/js/components/AssetReports/` - Filter components, inventory tables, charts
  - `resources/js/components/CapacityReports/ExportButtons.vue` - Export button component
- Backend logic to reference:
  - `app/Services/AssetCalculationService.php` - Calculation service pattern
  - `app/Services/AssetReportService.php` - PDF report generation service pattern
  - `app/Services/CapacityReportService.php` - Report service pattern
  - `app/Exports/AssetReportExport.php` - CSV export class pattern
  - `app/Exports/CapacityReportExport.php` - CSV export class pattern
- Models to reference:
  - `app/Models/Connection.php` - Connection model with cable_type, cable_length, cable_color, path_notes
  - `app/Models/Port.php` - Port model with type, subtype, status, direction
- Enums to reference:
  - `app/Enums/CableType.php` - Cat5e, Cat6, Cat6a, FiberSm, FiberMm, PowerC13, PowerC14, PowerC19, PowerC20
  - `app/Enums/PortType.php` - Ethernet, Fiber, Power
  - `app/Enums/PortStatus.php` - Available, Connected, Reserved, Disabled

### Follow-up Questions

No follow-up questions were needed.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
Not applicable - no visual files were provided.

## Requirements Summary

### Functional Requirements
- Display connection inventory metrics with cascading location filters (Datacenter > Room > Row/Rack)
- Show total connections count
- Show connections breakdown by cable type (Cat5e, Cat6, Cat6a, Fiber SM, Fiber MM, Power cables C13/C14/C19/C20)
- Show connections breakdown by port type (Ethernet, Fiber, Power)
- Show average cable length statistics
- Show port utilization metrics:
  - Total ports vs connected ports (utilization percentage)
  - Port utilization by port type (Ethernet/Fiber/Power)
  - Port utilization by status (Available, Connected, Reserved, Disabled)
- Display paginated connections inventory table with columns:
  - Source device name
  - Source port label
  - Destination device name
  - Destination port label
  - Cable type
  - Cable length
  - Cable color
- Include visualizations:
  - Pie/donut chart for cable type distribution
  - Bar chart for port utilization by type
- Support PDF export with current filters applied
- Support CSV export with current filters applied
- Enforce role-based access control:
  - Administrator and IT Manager: Full access to all datacenters
  - Operator, Auditor, Viewer: Access limited to assigned datacenters

### Reusability Opportunities
- Reuse cascading filter component pattern from AssetReports
- Reuse ExportButtons component from CapacityReports
- Follow AssetCalculationService pattern for ConnectionCalculationService
- Follow AssetReportService pattern for ConnectionReportService
- Follow AssetReportExport pattern for ConnectionReportExport
- Reuse Card, CardHeader, CardContent UI components
- Reuse Skeleton loading state pattern
- Reuse pagination handling pattern from DeviceInventoryTable

### Scope Boundaries
**In Scope:**
- Connection inventory metrics display
- Port utilization metrics display
- Cascading location filters (Datacenter > Room > Row/Rack)
- Paginated connections inventory table
- Cable type distribution chart (pie/donut)
- Port utilization chart (bar)
- PDF export functionality
- CSV export functionality
- Role-based access control enforcement

**Out of Scope:**
- Historical trend chart for connection changes over time (deferred - requires connection history snapshots)
- Port utilization by individual device
- Port utilization by individual rack
- Additional filter dimensions (device type, port type filters)

### Technical Considerations
- Follow existing controller patterns from CapacityReportController and AssetReportController
- Create ConnectionCalculationService for metrics calculation
- Create ConnectionReportService for PDF generation
- Create ConnectionReportExport for CSV export
- Use existing Connection and Port models with their relationships
- Leverage CableType, PortType, and PortStatus enums for grouping
- Use Laravel DomPDF for PDF generation (existing dependency)
- Use Laravel Excel for CSV export (existing dependency)
- Use Inertia.js for server-side rendering
- Use Vue 3 with Composition API for frontend components
- Use Tailwind CSS 4 for styling
- Use Chart.js for visualizations (existing dependency)
