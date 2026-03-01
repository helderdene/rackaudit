# Specification: Asset Reports

## Goal
Provide comprehensive reporting on device inventory, warranty status, lifecycle distribution, and quantity-based asset valuation to help datacenter managers monitor and manage their equipment assets effectively.

## User Stories
- As an IT Manager, I want to view a complete inventory of all devices across my datacenters so that I can track and manage equipment assets
- As a Datacenter Administrator, I want to see which devices have expiring or expired warranties so that I can plan for renewals or replacements
- As an IT Manager, I want to understand the distribution of devices across lifecycle stages so that I can plan procurement and disposal activities

## Specific Requirements

**Device Inventory Report**
- Display all devices regardless of rack placement status (both racked and non-racked like "In Stock", "Ordered")
- Show key details: asset tag, name, serial number, manufacturer, model, device type
- Show location information: datacenter, room, rack, U position (null for non-racked devices)
- Include devices in all lifecycle statuses (Ordered, Received, In Stock, Deployed, Maintenance, Decommissioned, Disposed)
- Support pagination for large datasets using deferred props pattern from Inertia v2
- Allow sorting by any column (asset tag, name, manufacturer, lifecycle status, location)

**Warranty Status Report**
- Categorize devices into four warranty status groups:
  - Active warranty (warranty_end_date > today)
  - Expiring soon (warranty_end_date within 30 days from today)
  - Expired (warranty_end_date < today)
  - Unknown/Not tracked (warranty_end_date is null)
- Display summary counts for each category as metric cards
- Show device lists for each category with key details (asset tag, name, manufacturer, model, warranty_end_date)
- Highlight "Expiring soon" category prominently to encourage proactive management

**Lifecycle Distribution Report**
- Show distribution of devices across all 7 lifecycle statuses using DeviceLifecycleStatus enum
- Visualize using a pie chart (Chart.js already in tech stack)
- Display counts and percentages for each status
- Include legend with status labels and colors
- Apply consistent colors: Ordered (blue), Received (cyan), In Stock (teal), Deployed (green), Maintenance (amber), Decommissioned (orange), Disposed (gray)

**Asset Valuation (Quantity-Based)**
- Show inventory counts grouped by device type (using DeviceType model relationship)
- Show inventory counts grouped by manufacturer (device.manufacturer field)
- Display as summary tables with device type/manufacturer name and total count
- No dollar valuation or depreciation calculations (explicitly out of scope)

**Filtering System**
- Filter by datacenter (cascading filter - selecting datacenter loads rooms)
- Filter by room (cascading filter - child of datacenter)
- Filter by device type (dropdown populated from DeviceType model)
- Filter by lifecycle status (dropdown populated from DeviceLifecycleStatus enum)
- Filter by manufacturer (dropdown populated from distinct device.manufacturer values)
- Filter by warranty expiration date range (date picker for start/end range)
- All filters apply to all report sections simultaneously
- Follow CapacityFilters component pattern for cascading location filters

**Export Functionality**
- PDF export with formatted multi-section report (inventory summary, warranty breakdown, lifecycle chart, counts by type/manufacturer)
- CSV export with device inventory data for analysis
- Follow existing Capacity Reports export pattern using CapacityReportExport as reference
- Include filter scope description in exports
- Name files with timestamp: asset-report-{YYYYMMDDHHmmss}.pdf/csv

**Access Control**
- Administrators and IT Managers see all datacenters (ADMIN_ROLES constant pattern)
- Other roles see only assigned datacenters (via user.datacenters() relationship)
- Apply access control when loading filter options and query results
- Follow CapacityReportController pattern for role-based filtering

## Existing Code to Leverage

**CapacityReportController Pattern**
- Use ADMIN_ROLES constant for role-based access control
- Use getAccessibleDatacenters() pattern for datacenter filtering
- Use cascading filter validation (validateDatacenterId, validateRoomId)
- Use service injection pattern for calculations and report generation
- Apply same export endpoint pattern (exportPdf, exportCsv methods)

**CapacityFilters Component**
- Reuse cascading filter UI pattern for datacenter/room selection
- Extend with additional filter dropdowns for device type, lifecycle status, manufacturer
- Add date range picker for warranty expiration filtering
- Use same debounced filter application approach

**ExportButtons Component**
- Reuse exactly as-is for PDF/CSV export buttons
- Pass asset report export URLs instead of capacity report URLs

**CapacityReportService and CapacityCalculationService**
- Create AssetReportService following same structure for PDF generation
- Create AssetCalculationService for computing warranty counts, lifecycle distribution, and quantity summaries
- Use same Barryvdh\DomPDF\Facade\Pdf approach for PDF generation
- Store reports in reports/assets/ directory

**Device Model Relationships**
- Leverage device.deviceType() relationship for grouping by type
- Leverage device.rack().row().room().datacenter() chain for location hierarchy
- Use DeviceLifecycleStatus enum for consistent status handling
- Access warranty_end_date, manufacturer, serial_number fields directly

## Out of Scope
- Depreciation schedules and calculations
- Maintenance history tracking
- Custom asset fields or user-defined attributes
- Dollar-based asset valuation or purchase price tracking
- Historical lifecycle trends over time
- Batch warranty update functionality
- Email notifications for expiring warranties
- Asset barcode/QR code generation
- Integration with external asset management systems
- Historical snapshots or time-series warranty data
