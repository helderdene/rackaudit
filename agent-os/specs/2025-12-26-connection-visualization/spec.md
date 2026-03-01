# Specification: Connection Visualization

## Goal
Create an interactive diagram showing device interconnections that allows users to visualize and explore the network topology of their datacenter infrastructure, with the ability to drill down from device-level to port-level details.

## User Stories
- As a datacenter administrator, I want to see a visual diagram of all device connections so that I can quickly understand the network topology and identify connection patterns.
- As a network engineer, I want to filter connections by location, type, and status so that I can focus on specific areas of the infrastructure.

## Specific Requirements

**Dedicated Connection Diagram Page**
- Create a new page at `/connections/diagram` with full-screen visualization canvas
- Include a sidebar or toolbar for filters and controls
- Implement breadcrumb navigation consistent with existing pages (see `ConnectionHistory/Index.vue` pattern)
- Page should use `AppLayout` wrapper like other pages in the application

**Device-Level Node Visualization**
- Display devices as interactive nodes on the diagram canvas
- Show device name, type, and asset tag on node labels
- Use visual indicators (icons/colors) to differentiate device types
- Support selection state with visual highlight when a device is clicked
- Nodes should be draggable for manual positioning

**Connection Edge Visualization**
- Draw lines/edges between connected devices
- Solid lines for verified connections, dashed lines for unverified connections
- Visual warning indicator (color/icon) for connections with audit discrepancies
- Display cable color visually on the edge where available
- Different line styles or colors by connection type (Ethernet, Fiber, Power)

**Port-Level Drill-Down**
- Click on a device node to expand and show port-level connections
- Display individual port labels and their connections
- Show port status (available, connected, reserved, disabled) with appropriate styling
- Allow clicking a connection line to view connection details in a modal/panel

**Automatic Layout with Manual Adjustment**
- Implement force-directed or hierarchical layout algorithm for initial node positioning
- Enable drag-and-drop to manually reposition device nodes
- Node positions should update smoothly with animation
- Consider Vue Flow library for built-in layout and interaction features

**Hierarchical Filtering**
- Filter by datacenter, room, row, rack (cascading dropdowns pattern from `HierarchicalPortSelector`)
- Filter by connection type (Ethernet, Fiber, Power) using `PortType` enum values
- Filter by device type using existing device type options
- Filter by connection status (verified/unverified)
- Filters should update the diagram in real-time without page reload

**Interactive Features**
- Hover tooltips showing key information (device name, port count, connection count)
- Zoom and pan controls for navigating large diagrams
- Click node to show device details in modal (reuse `DeviceDetailModal` pattern)
- Click connection edge to show connection details (reuse `ConnectionDetailDialog`)
- Navigation links to device/connection edit pages (read-only diagram with edit links)

**Contextual Views Integration**
- Add "View Connections" button on Device Show page linking to filtered diagram
- Add "Connection Diagram" link on Rack Show page showing rack-scoped connections
- Integrate connection visualization into Rack Elevation View as a tab or toggle

**Rack Elevation Integration**
- Add a tab or toggle to Rack Elevation View to show connection overlay
- Draw connection lines between ports in the elevation view
- Maintain consistency with existing RackElevationView interaction patterns

## Visual Design
No visual mockups were provided. The design should follow existing application patterns:
- Use Card components for filter panels consistent with `ConnectionHistory/Index.vue`
- Follow badge styling patterns from `Devices/Show.vue` for status indicators
- Use existing color scheme from Tailwind CSS configuration
- Implement dark mode support using `dark:` variant classes

## Existing Code to Leverage

**`/Users/helderdene/rackaudit/resources/js/composables/useRackElevation.ts`**
- State management pattern for devices with ref and computed properties
- Drag-and-drop logic that can be adapted for node positioning
- Occupation map concept can be extended for connection tracking
- Loading and error state handling patterns

**`/Users/helderdene/rackaudit/resources/js/components/elevation/RackElevationView.vue`**
- Drag event handling patterns (dragstart, dragenter, dragover, drop, dragend)
- Device click and selection interaction model
- Computed properties for device mapping and filtering
- Emit patterns for parent component communication

**`/Users/helderdene/rackaudit/app/Http/Controllers/ConnectionController.php`**
- Existing API endpoints for connection CRUD operations
- Filtering by device_id, rack_id, and port_type already implemented
- ConnectionResource for consistent API response format
- Can extend index method to support additional visualization filters

**`/Users/helderdene/rackaudit/resources/js/types/connections.ts`**
- TypeScript interfaces for ConnectionData, ConnectionWithPorts, ConnectionPortInfo
- CableTypeValue and PortTypeValue type definitions
- HierarchicalFilterOptions interface for cascading filter dropdowns
- Cable type utility functions for filtering by port type

**`/Users/helderdene/rackaudit/resources/js/components/connections/`**
- `ConnectionDetailDialog.vue` - Reusable dialog for viewing connection details
- `HierarchicalPortSelector.vue` - Cascading filter dropdown pattern to replicate
- `ConnectionTimeline.vue` - Visual display patterns for connection data

## Out of Scope
- 3D visualization of connections or datacenter layout
- Historical connection playback with timeline animation showing connection changes over time
- Real-time live updates via WebSocket for automatic refresh when connections change
- Print or export functionality (PDF, image, SVG export of diagrams)
- Direct connection creation or editing from the diagram interface
- Mobile-specific optimizations beyond standard responsive design
- Connection path tracing through multiple patch panels with animated highlighting
- Performance metrics or bandwidth visualization on connections
- Integration with external network monitoring or management systems
- Custom node shape or icon upload functionality
