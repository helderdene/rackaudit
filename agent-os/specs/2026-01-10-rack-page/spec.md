# Specification: Rack Page Enhancement

## Goal
Enhance the existing Rack Show page to provide comprehensive rack information, device listings, utilization metrics, and a mini elevation preview, matching the detail level of the Device Show page.

## User Stories
- As a datacenter technician, I want to view all devices installed in a rack from the rack page so that I can quickly assess rack contents without navigating to the elevation view.
- As an IT manager, I want to see utilization and power metrics on the rack page so that I can make capacity planning decisions at a glance.

## Specific Requirements

**Enhanced Rack Details Section**
- Expand the existing Rack Details card to include physical specifications
- Add manufacturer field to display rack manufacturer
- Add model field to display rack model number
- Add depth field to display rack depth dimensions
- Add installation_date field for when the rack was installed
- Add location_notes textarea field for additional location context
- Follow the grid layout pattern from Device Show page (sm:grid-cols-2 lg:grid-cols-4)

**Custom Specifications Key-Value Pairs**
- Add a new "Specifications" card section similar to Device Show page
- Display custom key-value pairs from a specs JSON field on the Rack model
- Use a table layout with Key and Value columns
- Show "No specifications recorded for this rack." when empty
- Follow the exact table styling from Device Show page specifications section

**Device List Table**
- Create a new "Installed Devices" card section
- Display all devices placed in the rack as a read-only table
- Table columns: Name (clickable link), Type, U Position, Status
- Sort devices by start_u position descending (highest U at top)
- Each device name links to the device detail page using DeviceController.show.url()
- Show status using Badge component with lifecycle status variant colors
- Display "No devices installed in this rack." when empty

**Utilization Metrics Display**
- Add a utilization summary section showing "X of Y U-spaces occupied"
- Display as a simple stat within the existing Rack Details card or as a new compact section
- Calculate usedU by counting U positions occupied by placed devices
- Show percentage utilization with color coding (green <70%, yellow 70-90%, red >90%)

**Power Metrics Display**
- Add power metrics section showing total power draw vs PDU capacity
- Aggregate power_draw_watts from all devices in the rack
- Aggregate total_capacity_kw from all assigned PDUs (convert to watts for comparison)
- Display as "X W of Y W capacity" with percentage
- Show warning color when utilization exceeds 80%

**Mini Elevation Preview Component**
- Create a new MiniElevationPreview.vue component
- Size: ~300px wide, proportional height based on rack U-height
- Static display only (no hover states, no drag-and-drop, no interactivity)
- Clickable container that navigates to full elevation view on click
- Show devices with colors based on device type (use existing DeviceBlock badge variants)
- Indicate device status using color-coded borders or backgrounds
- Display U numbers along the left side for reference
- Scale slot height to fit within reasonable viewport height

**Backend Controller Updates**
- Extend RackController show() method to include additional rack data
- Add devices relationship with eager loading for the device list
- Include device_type relationship for each device
- Calculate and return utilization stats (totalU, usedU, availableU, utilizationPercent)
- Calculate and return power metrics (totalPowerDraw, pduCapacity)
- Add new fields to rack response: manufacturer, model, depth, installation_date, location_notes, specs

**Rack Model Updates**
- Add new fillable fields: manufacturer, model, depth, installation_date, location_notes, specs
- Add migration to add these columns to the racks table
- Cast specs field to array type
- Cast installation_date to date type

## Visual Design
No visual mockups provided. Follow the established patterns from:
- Device Show page layout and card structure
- Elevation view component styling for the mini preview
- Existing table styling patterns used throughout the application

## Existing Code to Leverage

**Device Show Page (resources/js/pages/Devices/Show.vue)**
- Card layout structure with CardHeader, CardContent, CardTitle
- Grid layout for detail fields (sm:grid-cols-2 lg:grid-cols-4)
- Specifications table with key-value display pattern
- Badge component usage with status variant helper functions
- Date formatting helpers (formatDate, formatDateTime)
- Quick action button layout in header section

**Current Rack Show Page (resources/js/pages/Racks/Show.vue)**
- Base page structure to extend
- Breadcrumb navigation pattern for rack hierarchy
- PDU table implementation to reference for device table
- QR code and elevation view button patterns
- Status badge variant helper function (getStatusVariant)

**Elevation Components (resources/js/components/elevation/)**
- RackElevationView.vue: U-slot rendering logic and device positioning
- DeviceBlock.vue: Device type colors and badge variants
- UtilizationCard.vue: Utilization display pattern and color coding
- useRackElevation.ts: Utilization calculation logic

**RackController (app/Http/Controllers/RackController.php)**
- getDevicesForElevation() method for device data formatting
- formatDeviceForElevation() for device type mapping
- Existing show() method structure to extend

**TypeScript Types (resources/js/types/rooms.ts)**
- PlaceholderDevice interface for device data
- UtilizationStats interface for utilization metrics
- RackData interface to extend with new fields

## Out of Scope
- Device management actions (add/remove/reposition devices) - remains in elevation view only
- Service information or maintenance history tracking
- Embedded full elevation view on the rack page
- Interactive mini elevation preview (hover states, tooltips, click on individual devices)
- Connection visualization in the mini preview
- Real-time updates or live data refresh
- Bulk device operations from the rack page
- Device filtering or sorting controls in the device list
- Export functionality for rack device data
- Print-friendly rack page layout
