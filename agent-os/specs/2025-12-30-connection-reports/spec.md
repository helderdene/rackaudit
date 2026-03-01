# Specification: Connection Reports

## Goal
Provide comprehensive reporting on connection inventory, cable types, and port utilization across datacenters, enabling infrastructure teams to monitor connectivity status and plan cable management effectively.

## User Stories
- As an IT Manager, I want to view connection inventory metrics filtered by location so that I can assess cable infrastructure across my datacenters.
- As an Administrator, I want to export connection reports as PDF or CSV so that I can share infrastructure data with stakeholders and auditors.

## Specific Requirements

**Cascading Location Filters**
- Implement Datacenter > Room > Row/Rack cascading filter pattern matching CapacityReports and AssetReports
- Validate filter values against user's accessible datacenters based on role
- Reset child filters when parent filter changes (e.g., clear room when datacenter changes)
- Store current filter state in URL query parameters for shareable links

**Connection Inventory Metrics**
- Display total connections count matching current filter scope
- Show connections breakdown by cable type using CableType enum (Cat5e, Cat6, Cat6a, Fiber SM, Fiber MM, C13, C14, C19, C20)
- Show connections breakdown by port type using PortType enum (Ethernet, Fiber, Power)
- Calculate and display average cable length statistics (mean, min, max) for connections with cable_length data
- Group power cable types (C13/C14/C19/C20) under "Power" category in summary views

**Port Utilization Metrics**
- Calculate total ports vs connected ports to derive utilization percentage
- Break down port utilization by port type (Ethernet, Fiber, Power)
- Break down port utilization by status using PortStatus enum (Available, Connected, Reserved, Disabled)
- Query ports through Device > Rack > Row > Room > Datacenter relationship chain for location filtering

**Paginated Connections Inventory Table**
- Display 25 connections per page following AssetReports pattern
- Include columns: Source Device, Source Port, Destination Device, Destination Port, Cable Type, Cable Length, Cable Color
- Eager load sourcePort.device and destinationPort.device relationships to prevent N+1 queries
- Support page navigation via URL query parameter

**Cable Type Distribution Chart**
- Implement pie/donut chart showing cable type distribution using Chart.js
- Display count and percentage for each cable type
- Use consistent color coding for cable types across the application
- Handle empty state gracefully when no connections exist

**Port Utilization Bar Chart**
- Implement horizontal bar chart showing port utilization by type using Chart.js
- Display total ports, connected ports, and utilization percentage per port type
- Use color coding to distinguish port types (Ethernet, Fiber, Power)

**PDF Export**
- Generate PDF report using Laravel DomPDF following AssetReportService pattern
- Include filter scope description, connection metrics summary, and connection inventory table
- Create PDF Blade template at `resources/views/pdf/connection-report.blade.php`
- Store generated PDFs in `storage/app/reports/connections/` directory

**CSV Export**
- Implement CSV export using Laravel Excel following AssetReportExport pattern
- Include columns matching inventory table: Source Device, Source Port, Destination Device, Destination Port, Cable Type, Cable Length, Cable Color
- Apply current filters to exported data

**Role-Based Access Control**
- Administrator and IT Manager roles have full access to all datacenters
- Operator, Auditor, Viewer roles only see assigned datacenters via user.datacenters relationship
- Apply access control at controller level consistent with existing report controllers

## Existing Code to Leverage

**AssetReportController.php**
- Reuse cascading filter validation pattern (validateDatacenterId, validateRoomId methods)
- Reuse getAccessibleDatacenters method for role-based datacenter filtering
- Reuse pagination handling pattern for inventory table
- Follow same controller structure with index, exportPdf, exportCsv methods

**AssetCalculationService.php**
- Follow service class pattern for ConnectionCalculationService
- Reuse buildFilteredQuery approach for Connection and Port queries
- Follow metrics aggregation pattern returning structured array

**AssetReportService.php**
- Follow PDF generation pattern for ConnectionReportService
- Reuse buildFilterScope method pattern for filter description
- Reuse storeReport pattern for PDF storage

**AssetReportExport.php and AbstractDataExport.php**
- Extend AbstractDataExport for ConnectionReportExport
- Follow headings, query, transformRow method pattern
- Apply filters in query method consistent with existing exports

**resources/js/Pages/AssetReports/Index.vue**
- Reuse page structure with header, filters, skeleton loading, content sections
- Reuse ExportButtons component from CapacityReports
- Follow same filter state management and URL parameter handling
- Reuse empty state pattern with appropriate icon

## Out of Scope
- Historical trend chart for connection changes over time (requires connection history snapshots not currently tracked)
- Port utilization breakdown by individual device
- Port utilization breakdown by individual rack
- Additional filter dimensions beyond location (no device type or port type filters)
- Connection path tracing through patch panels (logical path visualization)
- Cable management recommendations or alerts
- Connection health monitoring or status checks
- Real-time connection status updates
- Bulk connection operations from report view
- Connection comparison between time periods
