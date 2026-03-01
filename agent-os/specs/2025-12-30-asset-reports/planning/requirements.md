# Spec Requirements: Asset Reports

## Initial Description
Asset Reports - Reports on device inventory, warranty status, lifecycle distribution, and asset valuation. This is roadmap item #36, part of Phase 5: Reporting & Dashboard. It follows the completed Capacity Planning Reports (#35) and Main Dashboard (#34).

## Requirements Discussion

### First Round Questions

**Q1:** Device Inventory Report Scope - I assume the device inventory report should display all devices with key details like asset tag, name, serial number, manufacturer, model, device type, and current location (datacenter > room > rack > U position). Should this include devices that are NOT placed in racks (e.g., "In Stock", "Ordered" status devices), or only deployed/racked equipment?
**Answer:** Correct - include all devices (both racked and non-racked like "In Stock", "Ordered")

**Q2:** Warranty Status Categories - I'm thinking the warranty report should categorize devices into groups like "Active warranty", "Expiring soon (30/60/90 days)", and "Expired". Should we include devices with no warranty information as a separate "Unknown/Not tracked" category, and what timeframes would you prefer for the "expiring soon" alerts?
**Answer:** Correct - use 30 days for "expiring soon" threshold, and include "Unknown/Not tracked" category for devices without warranty info

**Q3:** Lifecycle Distribution Visualization - I assume lifecycle distribution should show counts/percentages for each status (Ordered, Received, In Stock, Deployed, Maintenance, Decommissioned, Disposed). Should this be displayed as a pie chart, bar chart, or both? Should we also include trends over time if historical data becomes available?
**Answer:** Pie chart

**Q4:** Asset Valuation Approach - The current Device model doesn't have a purchase price or current value field. For asset valuation, should we add a new purchase_price field, use a depreciation model, or simply count devices by type/category for a "quantity-based" inventory report instead of dollar valuation?
**Answer:** Simply show quantity-based inventory counts instead of dollar valuation (no purchase_price field, no depreciation)

**Q5:** Filtering Capabilities - I assume users should be able to filter reports by datacenter, room, device type, lifecycle status, and manufacturer (similar to Capacity Reports). Should we also support date range filtering for warranty expiration and purchase dates?
**Answer:** Correct - support filtering by datacenter, room, device type, lifecycle status, manufacturer, and date ranges

**Q6:** Export Formats - I assume the reports should support both PDF and CSV export (following the existing Capacity Reports pattern). Is that correct, or do you need additional formats like Excel with multiple worksheets?
**Answer:** Correct - PDF and CSV export (following Capacity Reports pattern)

**Q7:** User Access Control - I assume access should follow the existing pattern where Administrators and IT Managers see all datacenters, while other roles see only assigned datacenters. Is that correct?
**Answer:** Correct - follow existing pattern where Admins/IT Managers see all datacenters, others see only assigned

**Q8:** Anything Out of Scope - Are there any specific asset reporting features you explicitly do NOT want in this initial implementation?
**Answer:** Exclude depreciation schedules, maintenance history tracking, custom asset fields

### Existing Code to Reference

**Similar Features Identified:**
- Feature: Capacity Reports - Path: `/Users/helderdene/rackaudit/app/Http/Controllers/CapacityReportController.php`
- Feature: Capacity Reports Vue Page - Path: `/Users/helderdene/rackaudit/resources/js/Pages/CapacityReports/Index.vue`
- Components to potentially reuse: `/Users/helderdene/rackaudit/resources/js/components/CapacityReports/` (CapacityFilters, CapacityMetricCard, ExportButtons, HistoricalTrendChart, PortCapacityGrid, RackCapacityTable)
- Backend logic to reference: `/Users/helderdene/rackaudit/app/Services/CapacityCalculationService.php`, `/Users/helderdene/rackaudit/app/Services/CapacityReportService.php`
- Device Model: `/Users/helderdene/rackaudit/app/Models/Device.php`
- DeviceLifecycleStatus Enum: `/Users/helderdene/rackaudit/app/Enums/DeviceLifecycleStatus.php`
- DeviceType Model: `/Users/helderdene/rackaudit/app/Models/DeviceType.php`

### Follow-up Questions

No follow-up questions required - user provided comprehensive answers to all questions.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A - No visual assets to analyze.

## Requirements Summary

### Functional Requirements

**Device Inventory Report:**
- Display all devices regardless of rack placement status
- Show key details: asset tag, name, serial number, manufacturer, model, device type
- Show location information: datacenter, room, rack, U position (where applicable)
- Include devices in all lifecycle statuses (Ordered, Received, In Stock, Deployed, Maintenance, Decommissioned, Disposed)

**Warranty Status Report:**
- Categorize devices by warranty status:
  - Active warranty (warranty_end_date > today)
  - Expiring soon (warranty_end_date within 30 days)
  - Expired (warranty_end_date < today)
  - Unknown/Not tracked (warranty_end_date is null)
- Display counts and device lists for each category

**Lifecycle Distribution Report:**
- Show distribution of devices across all 7 lifecycle statuses
- Visualize using a pie chart
- Display counts and percentages for each status

**Asset Valuation (Quantity-Based):**
- Show inventory counts grouped by device type
- Show inventory counts grouped by manufacturer
- No dollar valuation or depreciation calculations

**Filtering:**
- Filter by datacenter (cascading filter support)
- Filter by room (cascading filter support)
- Filter by device type
- Filter by lifecycle status
- Filter by manufacturer
- Filter by date ranges (warranty expiration, purchase dates)

**Export:**
- PDF export with formatted report
- CSV export for data analysis
- Follow existing Capacity Reports export pattern

**Access Control:**
- Administrators and IT Managers: see all datacenters
- Other roles: see only assigned datacenters

### Reusability Opportunities
- Reuse CapacityFilters component pattern for cascading datacenter/room filters
- Reuse ExportButtons component for PDF/CSV download buttons
- Reuse CapacityMetricCard component pattern for summary metrics
- Follow CapacityReportController pattern for filter validation and data access control
- Create new AssetReportService and AssetCalculationService following existing service patterns

### Scope Boundaries

**In Scope:**
- Device inventory listing with all device details
- Warranty status categorization and reporting (30-day threshold for "expiring soon")
- Lifecycle distribution pie chart visualization
- Quantity-based asset counts by device type and manufacturer
- Filtering by datacenter, room, device type, lifecycle status, manufacturer, date ranges
- PDF and CSV export
- Role-based access control for datacenter visibility

**Out of Scope:**
- Depreciation schedules and calculations
- Maintenance history tracking
- Custom asset fields
- Dollar-based asset valuation
- Purchase price field on devices
- Historical lifecycle trends over time

### Technical Considerations
- Use Chart.js (already in tech stack) for pie chart visualization
- Follow existing Capacity Reports architecture for controller, service, and Vue component structure
- Leverage existing Device model relationships (deviceType, rack -> row -> room -> datacenter)
- Use DeviceLifecycleStatus enum for consistent status handling
- Implement cascading filters similar to CapacityReportController
- Use Laravel Excel for CSV export (already configured)
- Use Laravel DomPDF for PDF export (already configured)
