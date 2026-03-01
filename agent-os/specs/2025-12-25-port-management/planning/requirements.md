# Spec Requirements: Port Management

## Initial Description
Port Management - CRUD for ports on devices with type classification (Ethernet, fiber, power), labeling, and status tracking

## Requirements Discussion

### First Round Questions

**Q1:** I assume ports will have types such as Ethernet (with speed subtypes like 1GbE, 10GbE, 25GbE, 40GbE, 100GbE), Fiber (with connector types like LC, SC, MPO), and Power (with types like C13, C14, C19, C20). Is that the right categorization, or should we use a different hierarchy (e.g., separate type and subtype fields vs. a flat list)?
**Answer:** Correct. Use the hierarchy: Ethernet (1GbE, 10GbE, 25GbE, 40GbE, 100GbE), Fiber (LC, SC, MPO), Power (C13, C14, C19, C20)

**Q2:** For labeling, I'm thinking each port will have a user-defined label (like "eth0", "port1", "PSU-A") plus an optional physical position indicator (e.g., row/column for patch panels, slot number for modular devices). Should we support both, or is a simple text label sufficient?
**Answer:** Support both: user-defined label (like "eth0", "port1", "PSU-A") AND optional physical position indicator (row/column for patch panels, slot number for modular devices)

**Q3:** For status tracking, I assume we need states like: Available, Connected, Reserved, Disabled/Faulty. Are these the right statuses, or do you need additional states for your auditing workflows?
**Answer:** Correct. Use states: Available, Connected, Reserved, Disabled/Faulty

**Q4:** I assume ports should be managed primarily from the Device Show page (as a related section/tab), rather than having a standalone Ports index page listing all ports across all devices. Is that correct, or do you also need a global ports view for searching/filtering across the entire datacenter?
**Answer:** Correct. Ports managed primarily from Device Show page as a related section/tab (no standalone global ports index)

**Q5:** For bulk port creation, I'm thinking we should support templates like "Add 24 Ethernet ports labeled eth1-eth24" or "Add 2 power ports labeled PSU-A, PSU-B". Is this batch creation pattern important, or is individual port creation acceptable?
**Answer:** Correct. Support batch creation templates like "Add 24 Ethernet ports labeled eth1-eth24"

**Q6:** Should ports have a "direction" attribute (e.g., uplink vs. downlink, input vs. output for power)? This could be useful for connection validation later.
**Answer:** Correct. Include direction (uplink/downlink, input/output for power) for connection validation later

**Q7:** For the visual port mapping mentioned in the roadmap (#20), should we design the port data model now to support a visual representation (e.g., port position coordinates on a device face diagram), or should that be deferred to the visual port mapping spec?
**Answer:** Design the port data model NOW to support visual representation (port position coordinates on device face diagram)

**Q8:** Is there anything you explicitly want to exclude from this port management feature? For example: port-to-port connections (deferred to #17), VLAN assignments, power consumption tracking, or automatic discovery?
**Answer:** Exclude: port-to-port connections (deferred to #17), VLAN assignments, power consumption tracking, automatic discovery

### Existing Code to Reference

**Similar Features Identified:**
- Feature: Device Show page - Path: `/Users/helderdene/rackaudit/resources/js/Pages/Devices/Show.vue`
- Components to potentially reuse: Cards (CardHeader, CardTitle, CardContent), KeyValueEditor, Badge components
- Backend logic to reference: DeviceController patterns, Device model with enums

### Follow-up Questions
No follow-up questions were needed.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A - No visual files were found in the visuals folder.

## Requirements Summary

### Functional Requirements
- CRUD operations for ports belonging to devices
- Port type classification with hierarchy:
  - Ethernet: 1GbE, 10GbE, 25GbE, 40GbE, 100GbE
  - Fiber: LC, SC, MPO
  - Power: C13, C14, C19, C20
- Dual labeling system:
  - User-defined label (e.g., "eth0", "port1", "PSU-A")
  - Optional physical position indicator (row/column, slot number)
- Status tracking: Available, Connected, Reserved, Disabled/Faulty
- Direction attribute: uplink/downlink for network, input/output for power
- Bulk port creation with templates (e.g., "Add 24 Ethernet ports labeled eth1-eth24")
- Port data model designed for future visual port mapping (position coordinates on device face)

### Reusability Opportunities
- Device Show page layout and Card components
- KeyValueEditor component for port attributes
- Badge component for status display
- Enum pattern from DeviceLifecycleStatus, DeviceDepth, etc.
- Resource controller pattern from DeviceController
- Form Request validation pattern

### Scope Boundaries
**In Scope:**
- Port model with device relationship
- Port type enum with subtypes (Ethernet/Fiber/Power categories)
- Port status enum (Available, Connected, Reserved, Disabled)
- Port direction attribute (uplink/downlink, input/output)
- Label and physical position fields
- Position coordinates for future visual mapping
- CRUD operations via Device Show page
- Bulk port creation interface
- Port listing section on Device Show page

**Out of Scope:**
- Port-to-port connections (deferred to roadmap item #17)
- VLAN assignments
- Power consumption tracking
- Automatic port discovery
- Standalone global ports index page

### Technical Considerations
- Ports belong to Devices (one-to-many relationship)
- Port type should use enum with type and subtype structure
- Port status enum following existing patterns (DeviceLifecycleStatus)
- Include position_x, position_y fields for visual port mapping (#20)
- API endpoints for port CRUD nested under devices or as standalone with device_id
- Bulk creation endpoint for batch port generation
- Follow existing controller patterns (DeviceController) for JSON/Inertia responses
- Activity logging for port changes using Loggable concern
