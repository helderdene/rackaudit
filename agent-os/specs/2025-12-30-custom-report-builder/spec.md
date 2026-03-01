# Specification: Custom Report Builder

## Goal
Enable users to build customized reports by selecting a report type and configuring columns, filters, sorting, grouping, and calculated fields, with support for on-screen preview and multiple export formats (PDF, CSV/Excel, JSON).

## User Stories
- As an IT Manager, I want to customize which columns appear in my reports so that I can focus on the specific data relevant to my analysis.
- As an Auditor, I want to filter and sort report data using multiple criteria so that I can quickly find the information I need for compliance reviews.

## Specific Requirements

**Report Type Selection**
- Users select ONE report type to customize: Capacity, Assets, Connections, or Audit History
- Selection determines available columns, filters, and calculated fields
- Use a prominent card-based selector similar to existing report navigation patterns
- Load dynamic configuration (available fields, filters) when report type is selected

**Column/Field Selection**
- Display available columns as checkboxes grouped by category
- Each field has a user-friendly display name (e.g., "Warranty End Date" not "warranty_end_date")
- Support selecting/deselecting all columns per category
- Minimum of one column must be selected to generate report
- Store column configuration in the request payload as an array of field keys

**Cascading Location Filters**
- Implement Datacenter > Room > Row cascading dropdowns following the pattern in `CapacityFilters.vue`
- Room dropdown populates when datacenter selected; Row dropdown populates when room selected
- Reset child filters when parent filter changes
- All location filters are optional

**Report-Type-Specific Filters**
- Assets: device type dropdown, lifecycle status dropdown, manufacturer dropdown, warranty date range (start/end)
- Capacity: utilization threshold percentage input
- Connections: cable type dropdown, connection status dropdown
- Audit History: date range (start/end), audit type dropdown, finding severity dropdown
- All type-specific filters are optional
- Dynamically show/hide filter fields based on selected report type

**Sorting Configuration**
- Allow sorting by any selected column
- Support ascending and descending direction toggle
- Support multi-column sorting (up to 3 columns) with priority order
- Default sort by first selected column, descending

**Grouping Configuration**
- Allow grouping by one field (e.g., by datacenter, by device type, by lifecycle status)
- Grouping is optional
- When grouped, display group headers with subtotals where applicable

**Calculated Fields**
- Provide predefined calculated fields per report type:
  - Capacity: devices per rack, utilization percentage, available U-space
  - Assets: days until warranty expiration, age in years
  - Connections: port utilization percentage
  - Audit History: days to resolution, finding resolution rate
- Calculated fields appear as selectable columns alongside standard fields

**Output Formats**
- PDF: Use existing DomPDF infrastructure, create new PDF template for custom reports
- CSV/Excel: Extend `AbstractDataExport` class pattern with dynamic column support
- JSON: Return structured JSON response from dedicated API endpoint
- On-screen preview: Paginated table rendered in browser before export

**On-Screen Preview**
- Display results in a paginated data table (25 rows per page)
- Show column headers based on selected fields
- Include pagination controls matching existing patterns in `Reports/Index.vue`
- Show loading skeleton during data fetch
- Display total record count

## Visual Design
No visual assets provided. Follow existing design patterns from `CapacityReports/Index.vue` and `Reports/Index.vue`:
- Card-based filter panel with collapsible mobile view
- Data table with sortable column headers
- Export buttons group in header area
- Skeleton loading states during data operations

## Existing Code to Leverage

**CapacityReportController.php**
- Pattern for cascading location filter validation (datacenter_id, room_id, row_id)
- `getAccessibleDatacenters()` method for role-based datacenter filtering
- Export methods (`exportPdf`, `exportCsv`) structure for generating downloads
- ADMIN_ROLES constant pattern for access control

**AssetReportController.php**
- Extended filter patterns including device_type_id, lifecycle_status, manufacturer, warranty date range
- `getDeviceTypeOptions()`, `getLifecycleStatusOptions()`, `getManufacturerOptions()` for filter dropdowns
- Paginated device inventory with eager loading pattern
- Date string validation helper method

**AbstractDataExport.php**
- Base class for CSV/Excel exports with filters support
- `query()` and `transformRow()` abstract methods to implement
- Collection-based export pattern for dynamic column selection

**CapacityFilters.vue**
- Cascading filter component with debounced Inertia router navigation
- Mobile collapsible and desktop inline layout patterns
- Watch handlers for resetting child filters when parent changes

**ExportButtons.vue**
- Reusable export button group component with loading states
- Pattern for triggering downloads via temporary link elements

## Out of Scope
- Combining multiple report types into a single unified report
- Saved report templates or configurations for reuse
- Sharing report configurations between users
- Scheduled/automated report generation
- Real-time dashboards or live-updating displays
- Cross-datacenter aggregation in a single report
- Access for Operator or Viewer roles
- Custom formula builder for user-defined calculated fields
- Drag-and-drop column reordering in the preview table
- Report versioning or history tracking
