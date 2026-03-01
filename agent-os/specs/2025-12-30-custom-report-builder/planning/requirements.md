# Spec Requirements: Custom Report Builder

## Initial Description
Custom Report Builder - Interface for users to configure report parameters, filters, and output format

## Requirements Discussion

### First Round Questions

**Q1:** I assume the Custom Report Builder should allow users to combine data from multiple existing report types (capacity, assets, connections, audit history) into a single custom report. Is that correct, or should it focus on customizing parameters within a single report type?
**Answer:** Customizing parameters within a single report type (NOT combining multiple report types)

**Q2:** I'm thinking the report configuration should include: data source selection, column/field selection, filter criteria, sorting options, and grouping. Should we also include calculated fields (e.g., "devices per rack" or "utilization percentage")?
**Answer:** Confirmed - include data source selection, column/field selection, filter criteria, sorting options, grouping, and calculated fields

**Q3:** For the output format, I assume we should support PDF and CSV/Excel export (matching existing reports). Should we also include an on-screen preview mode, or perhaps additional formats like JSON for API consumption?
**Answer:** Confirmed - PDF, CSV/Excel, on-screen preview, and JSON for API consumption

**Q4:** I'm assuming users should be able to save report configurations as "templates" for reuse and sharing with other users. Is that correct? Should these templates be private to the user, shared within a role, or shareable across the organization?
**Answer:** NO - they don't want saved templates functionality

**Q5:** For the filter interface, the existing reports use cascading location filters (datacenter > room > row). I assume the Custom Report Builder should use a similar approach but also allow filtering by additional criteria like device type, lifecycle status, date ranges, etc. Should all filters be optional, or are some required (like location scope)?
**Answer:** Confirmed - cascading location filters plus additional criteria (device type, lifecycle status, date ranges), all optional

**Q6:** I assume this feature should be available to users with the IT Manager, Administrator, or Auditor roles (similar to existing reports). Should Operators and Viewers have any access, perhaps read-only access to saved templates?
**Answer:** Confirmed - IT Manager, Administrator, Auditor roles with full access

**Q7:** Is there anything that should explicitly be OUT of scope for this feature? For example: scheduling reports (that's roadmap item #40), real-time dashboards, or cross-datacenter aggregation?
**Answer:** Exclude scheduling reports, real-time dashboards, and cross-datacenter aggregation

### Existing Code to Reference

**Similar Features Identified:**
- Existing report controllers: `app/Http/Controllers/CapacityReportController.php`, `AssetReportController.php`, `ConnectionReportController.php`, `AuditHistoryReportController.php`
- Report service classes: `app/Services/CapacityReportService.php`, `AssetReportService.php`, `ConnectionReportService.php`, `AuditHistoryReportService.php`
- Export classes: `app/Exports/CapacityReportExport.php`, `AssetReportExport.php`, `ConnectionReportExport.php`, `AuditHistoryReportExport.php`
- Calculation services: `app/Services/CapacityCalculationService.php`, `AssetCalculationService.php`
- Frontend report pages: `resources/js/Pages/Reports/Index.vue`, `resources/js/Pages/CapacityReports/Index.vue`
- Cascading filter patterns used in existing report controllers

### Follow-up Questions

No follow-up questions needed - requirements are clear and comprehensive.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A - No visuals to analyze

## Requirements Summary

### Functional Requirements

**Report Type Selection:**
- User selects ONE report type to customize from available options:
  - Capacity Reports (rack utilization, power consumption, port capacity)
  - Asset Reports (device inventory, warranty status, lifecycle distribution)
  - Connection Reports (connection inventory, cable types, port utilization)
  - Audit History Reports (completed audits, finding trends, resolution times)

**Column/Field Selection:**
- User can select which columns/fields to include in the report
- Each report type has its own set of available fields based on underlying data model
- Fields should have user-friendly display names

**Filter Criteria:**
- Cascading location filters: Datacenter > Room > Row (all optional)
- Additional filters vary by report type:
  - Assets: device type, lifecycle status, manufacturer, warranty date range
  - Capacity: utilization thresholds
  - Connections: cable type, connection status
  - Audit History: date range, audit type, finding severity
- All filters are optional

**Sorting Options:**
- User can specify sort column(s) and direction (ascending/descending)
- Support for multi-column sorting

**Grouping:**
- User can group results by selected fields
- Grouping affects how data is organized in the output

**Calculated Fields:**
- Support for derived/calculated fields such as:
  - Devices per rack
  - Utilization percentage
  - Days until warranty expiration
  - Finding resolution rate

**Output Formats:**
- PDF export (using existing DomPDF infrastructure)
- CSV/Excel export (using existing Laravel Excel infrastructure)
- On-screen preview (rendered in the browser)
- JSON export (for API consumption)

**On-Screen Preview:**
- Display report results in a paginated table view
- Allow users to review before exporting
- Support for large datasets with pagination

### Reusability Opportunities
- Existing report service classes for data retrieval logic
- Existing export classes as templates for CSV/Excel generation
- Existing PDF templates in `resources/views/pdf/`
- Cascading filter pattern from CapacityReportController
- UI components from existing Reports/Index.vue (table, filters, pagination)
- Role-based access control patterns from existing report controllers

### Scope Boundaries

**In Scope:**
- Single report type customization interface
- Column/field selection per report type
- Cascading location filters (datacenter > room > row)
- Additional type-specific filters (device type, lifecycle status, dates, etc.)
- Sorting by one or more columns
- Grouping by selected fields
- Calculated/derived fields
- PDF export
- CSV/Excel export
- JSON export
- On-screen preview with pagination
- Role-based access (IT Manager, Administrator, Auditor)

**Out of Scope:**
- Combining multiple report types into a single report
- Saved report templates/configurations
- Sharing templates between users
- Scheduled report generation (roadmap item #40)
- Real-time dashboards
- Cross-datacenter aggregation (reports are scoped to accessible datacenters per user role)
- Operator and Viewer role access

### Technical Considerations

**Backend:**
- New controller: CustomReportBuilderController
- New service: CustomReportBuilderService (orchestrates report type services)
- Leverage existing report services for data retrieval
- New form request for validation of report configuration
- JSON export endpoint for API consumption

**Frontend:**
- New Vue page: Pages/CustomReports/Builder.vue
- Report type selector component
- Dynamic column selector based on selected report type
- Filter panel with cascading dropdowns
- Sort configuration UI
- Group by selector
- Output format selector
- Preview table component with pagination
- Export action buttons

**Data Flow:**
1. User selects report type
2. System loads available columns, filters, and calculated fields for that type
3. User configures columns, filters, sorting, grouping
4. User can preview results on-screen
5. User exports in desired format (PDF, CSV, JSON)

**Performance:**
- Use eager loading to prevent N+1 queries
- Paginate on-screen preview for large datasets
- Consider query limits for very large exports
- Use queued jobs for large PDF/Excel exports if needed

**Access Control:**
- Restrict access to IT Manager, Administrator, and Auditor roles
- Filter available datacenters based on user's assigned datacenters (non-admin users)
- Follow existing patterns from report controllers
