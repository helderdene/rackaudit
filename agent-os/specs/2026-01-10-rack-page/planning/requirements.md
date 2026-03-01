# Spec Requirements: Rack Page Enhancement

## Initial Description
Add a rack page similar to the existing device page. This is a Laravel + Inertia + Vue application.

The user wants to enhance the existing Rack Show page (`/resources/js/pages/Racks/Show.vue`) to have similar functionality and detail level as the Device Show page (`/resources/js/pages/Devices/Show.vue`).

Currently, the Rack Show page displays:
- Rack details (name, position, U-height, serial number, status, created date)
- Assigned PDUs table
- Quick actions (QR code, connection diagram, elevation view, edit, delete)

The goal is to bring similar richness and functionality to the Rack page.

## Requirements Discussion

### First Round Questions

**Q1:** Should the rack page display a list of all devices installed in the rack?
**Answer:** Yes, full table showing device details (name, type, U position, status) with click-through to each device.

**Q2:** Should utilization metrics be included (e.g., "X of Y U-spaces occupied")?
**Answer:** Yes, include "X of Y U-spaces occupied" and power metrics (total power draw vs PDU capacity).

**Q3:** What rack-specific information should be displayed beyond what currently exists?
**Answer:** Include physical specs (manufacturer, model, depth), installation date, location notes, custom specifications key-value pairs.

**Q4:** Should device management (add/remove/reposition) be possible from the rack page or remain in the elevation view?
**Answer:** Device management should remain in the elevation view only (NOT on rack page - read only list).

**Q5:** Should the elevation view remain as a separate page with a link, or be embedded/previewed on the rack page?
**Answer:** Keep as separate page with link, but ADD a mini elevation preview on the rack page.

**Q6:** Should service information (maintenance history, scheduled maintenance) be included?
**Answer:** Skip this section entirely.

**Q7:** Is there anything that should be explicitly excluded from the rack page?
**Answer:** None specified.

### Follow-up Questions

**Follow-up 1:** Should the mini elevation preview be interactive (hover states showing device info) or purely visual?
**Answer:** Static preview (no hover states or interactions)

**Follow-up 2:** Should clicking the mini elevation navigate to the full elevation view?
**Answer:** Yes, clickable - clicking navigates to the full elevation view

**Follow-up 3:** Should devices in the preview show colors/status indicators (e.g., green for active, red for offline)?
**Answer:** Yes - show device colors/status indicators

**Follow-up 4:** What approximate size should the mini preview be (e.g., thumbnail ~150px wide, or larger ~300px)?
**Answer:** ~300px wide (larger preview)

### Existing Code to Reference

**Similar Features Identified:**
- Feature: Device Show Page - Path: `/resources/js/pages/Devices/Show.vue`
- Feature: Current Rack Show Page - Path: `/resources/js/pages/Racks/Show.vue`
- Feature: Elevation View - Path: (to be identified by spec-writer)
- Components to potentially reuse: Device Show page layout, specifications key-value table component
- Backend logic to reference: Device controller patterns for comprehensive show pages

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A - No visual files found in the planning/visuals folder.

## Requirements Summary

### Functional Requirements
- Display comprehensive rack details including physical specs (manufacturer, model, depth)
- Show installation date and location notes
- Display custom specifications as key-value pairs
- Show full device list table with columns: name, type, U position, status
- Each device row should be clickable to navigate to device detail page
- Display utilization metrics: "X of Y U-spaces occupied"
- Display power metrics: total power draw vs PDU capacity
- Include mini elevation preview on the rack page
- Maintain link to full elevation view page
- Keep existing quick actions (QR code, connection diagram, elevation view, edit, delete)

### Mini Elevation Preview Requirements
- **Interactivity**: Static preview (no hover states or interactions)
- **Click behavior**: Clickable - clicking navigates to the full elevation view
- **Visual indicators**: Show device colors/status indicators (e.g., green for active, red for offline)
- **Size**: ~300px wide (larger preview)

### Reusability Opportunities
- Device Show page layout and structure
- Specifications key-value table component (if exists)
- Existing rack show page as base
- Device list table patterns from other pages

### Scope Boundaries

**In Scope:**
- Enhanced rack details section with physical specs
- Installation date and location notes display
- Custom specifications key-value pairs
- Device list table (read-only)
- Utilization metrics display
- Power metrics display
- Mini elevation preview (~300px wide, static, clickable, with status indicators)
- Link to full elevation view

**Out of Scope:**
- Device management (add/remove/reposition) - remains in elevation view only
- Service information / maintenance history
- Embedded full elevation view
- Interactive mini elevation preview (hover states, tooltips, etc.)

### Technical Considerations
- Laravel + Inertia + Vue application
- Must follow existing patterns from Device Show page
- Backend may need updates to provide additional rack data (manufacturer, model, depth, installation date, location notes, specifications)
- Power metrics calculation requires PDU data aggregation
- Mini elevation preview component may be extracted/simplified from existing elevation view component
- Status indicator colors should match existing application conventions
