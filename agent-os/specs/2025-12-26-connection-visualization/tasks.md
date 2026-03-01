# Task Breakdown: Connection Visualization

## Overview
Total Tasks: 38 sub-tasks across 5 task groups

This feature creates an interactive diagram showing device interconnections with drill-down capabilities, hierarchical filtering, and integration with existing device/rack views.

## Task List

### API Layer

#### Task Group 1: Connection Diagram API Endpoints
**Dependencies:** None

- [x] 1.0 Complete API layer for connection diagram data
  - [x] 1.1 Write 4-6 focused tests for diagram API endpoints
    - Test fetching connections with device relationships
    - Test hierarchical filtering (datacenter, room, row, rack)
    - Test filtering by connection type and status
    - Test device node aggregation response format
  - [x] 1.2 Extend ConnectionController with diagram-specific endpoint
    - Create `diagram` action returning connections optimized for visualization
    - Include source/destination device info with coordinates
    - Add eager loading for `sourcePort.device.rack`, `destinationPort.device.rack`
    - Follow existing `index` method pattern from `ConnectionController.php`
  - [x] 1.3 Create ConnectionDiagramResource for visualization data
    - Include device-level aggregation (device id, name, type, asset_tag)
    - Include connection edge data (verified status, cable type, cable color)
    - Include port counts per device for drill-down
    - Follow existing `ConnectionResource` pattern
  - [x] 1.4 Add hierarchical location filtering to diagram endpoint
    - Filter by datacenter_id, room_id, row_id, rack_id parameters
    - Implement cascading filter logic consistent with `HierarchicalPortSelector`
    - Add device_type filter parameter
    - Add verified/unverified status filter
  - [x] 1.5 Create endpoint for port-level drill-down data
    - Return ports for a specific device with their connections
    - Include port status, label, type, and connection info
    - Reuse existing Port and Connection relationships
  - [x] 1.6 Ensure API layer tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify endpoint responses match expected format
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- Diagram endpoint returns properly formatted visualization data
- All filter combinations work correctly
- Port drill-down endpoint returns complete port data

---

### Frontend - Core Diagram Components

#### Task Group 2: Connection Diagram Core Components
**Dependencies:** Task Group 1

- [x] 2.0 Complete core diagram visualization components
  - [x] 2.1 Write 4-6 focused tests for diagram components
    - Test device node rendering with correct labels
    - Test connection edge rendering (solid/dashed lines)
    - Test node click interaction triggers drill-down
    - Test zoom and pan controls functionality
  - [x] 2.2 Install and configure Vue Flow library
    - Add `@vue-flow/core` and `@vue-flow/controls` dependencies
    - Configure TypeScript types for Vue Flow
    - Create base diagram composable following `useRackElevation.ts` pattern
  - [x] 2.3 Create DeviceNode component for diagram nodes
    - Props: device id, name, type, asset_tag, port_count, connection_count
    - Display device name and type icon
    - Implement selection state with visual highlight
    - Support draggable positioning via Vue Flow
    - Use existing badge styling from `Devices/Show.vue`
  - [x] 2.4 Create ConnectionEdge component for diagram edges
    - Props: connection id, verified, cable_type, cable_color, has_discrepancy
    - Solid line for verified, dashed for unverified
    - Warning indicator (color/icon) for audit discrepancies
    - Display cable color visually on edge
    - Different styling by connection type (Ethernet, Fiber, Power)
  - [x] 2.5 Create useConnectionDiagram composable
    - State management for nodes, edges, and selection
    - Loading and error state handling (follow `useRackElevation.ts`)
    - Methods for fetching diagram data from API
    - Methods for handling node/edge interactions
    - Layout algorithm integration (force-directed or hierarchical)
  - [x] 2.6 Create ConnectionDiagramCanvas component
    - Integrate Vue Flow with DeviceNode and ConnectionEdge
    - Implement zoom and pan controls
    - Add minimap for large diagrams
    - Handle node drag-and-drop for manual positioning
    - Smooth animation for position updates
  - [x] 2.7 Ensure core diagram component tests pass
    - Run ONLY the 4-6 tests written in 2.1
    - Verify components render correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 2.1 pass
- Device nodes display with correct information and styling
- Connection edges show correct line styles based on status
- Zoom, pan, and drag interactions work smoothly

---

### Frontend - Diagram Page and Filtering

#### Task Group 3: Connection Diagram Page with Filtering
**Dependencies:** Task Group 2

- [x] 3.0 Complete diagram page with filtering and interactions
  - [x] 3.1 Write 4-6 focused tests for diagram page functionality
    - Test page loads with diagram canvas
    - Test hierarchical filter dropdown interactions
    - Test filter changes update diagram in real-time
    - Test tooltip display on node/edge hover
  - [x] 3.2 Create Connections/Diagram.vue page component
    - Full-screen visualization canvas layout
    - Use `AppLayout` wrapper consistent with other pages
    - Implement breadcrumb navigation (follow `ConnectionHistory/Index.vue` pattern)
    - Sidebar or toolbar for filters and controls
  - [x] 3.3 Implement hierarchical filtering UI
    - Cascading dropdowns: datacenter > room > row > rack
    - Connection type filter (Ethernet, Fiber, Power) using `PortType` enum
    - Device type filter dropdown
    - Connection status filter (verified/unverified)
    - Follow `HierarchicalPortSelector.vue` pattern
  - [x] 3.4 Add real-time filter updates to diagram
    - Update diagram when filter selections change
    - Debounce filter changes to prevent excessive API calls
    - Show loading state during updates
    - Preserve node positions when filtering if possible
  - [x] 3.5 Implement hover tooltips
    - Device node tooltip: name, type, port count, connection count
    - Connection edge tooltip: cable type, color, length, status
    - Follow existing tooltip patterns in the application
  - [x] 3.6 Add modal integrations for detail views
    - Click device node to show `DeviceDetailModal` pattern
    - Click connection edge to show `ConnectionDetailDialog`
    - Include navigation links to edit pages (read-only diagram)
  - [x] 3.7 Add route for diagram page
    - Register `/connections/diagram` route in `web.php`
    - Create controller action to render Inertia page
    - Generate Wayfinder types for the new route
  - [x] 3.8 Ensure diagram page tests pass
    - Run ONLY the 4-6 tests written in 3.1
    - Verify page loads and interactions work
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 3.1 pass
- Diagram page loads with full-screen canvas
- Filters update diagram in real-time without page reload
- Tooltips and modals display correct information

---

### Frontend - Port Drill-Down and Integration

#### Task Group 4: Port-Level Drill-Down and View Integration
**Dependencies:** Task Group 3

- [x] 4.0 Complete port drill-down and existing view integration
  - [x] 4.1 Write 4-6 focused tests for drill-down and integration
    - Test device node expansion shows port-level connections
    - Test port status styling (available, connected, reserved, disabled)
    - Test "View Connections" button on Device Show page
    - Test connection diagram integration in Rack Elevation View
  - [x] 4.2 Implement port-level drill-down on device node click
    - Expand device node to show individual ports
    - Display port labels with status indicators
    - Draw port-to-port connection lines
    - Allow clicking connection line to view details
  - [x] 4.3 Add "View Connections" button to Device Show page
    - Add button to `Devices/Show.vue`
    - Link to `/connections/diagram?device_id={id}`
    - Filter diagram to show only connections for that device
  - [x] 4.4 Add "Connection Diagram" link to Rack Show page
    - Add link to rack detail page
    - Link to `/connections/diagram?rack_id={id}`
    - Filter diagram to show rack-scoped connections
  - [x] 4.5 Integrate connection visualization into Rack Elevation View
    - Add tab or toggle to `RackElevationView.vue`
    - Draw connection lines between ports in elevation view
    - Maintain consistency with existing interaction patterns
    - Follow existing emit patterns for parent communication
  - [x] 4.6 Ensure drill-down and integration tests pass
    - Run ONLY the 4-6 tests written in 4.1
    - Verify drill-down expands correctly
    - Verify integration links work
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 4.1 pass
- Port drill-down shows port-level details within diagram
- Integration links on Device/Rack pages work correctly
- Rack Elevation integration maintains existing patterns

---

### Testing and Polish

#### Task Group 5: Test Review, Gap Analysis, and Polish
**Dependencies:** Task Groups 1-4

- [x] 5.0 Review existing tests and fill critical gaps only
  - [x] 5.1 Review tests from Task Groups 1-4
    - Review the 4-6 tests written by Task Group 1 (API layer)
    - Review the 4-6 tests written by Task Group 2 (core components)
    - Review the 4-6 tests written by Task Group 3 (diagram page)
    - Review the 4-6 tests written by Task Group 4 (drill-down/integration)
    - Total existing tests: approximately 16-24 tests
  - [x] 5.2 Analyze test coverage gaps for connection visualization feature only
    - Identify critical user workflows that lack test coverage
    - Focus ONLY on gaps related to this spec's feature requirements
    - Do NOT assess entire application test coverage
    - Prioritize end-to-end workflows over unit test gaps
  - [x] 5.3 Write up to 8 additional strategic tests maximum
    - Add maximum of 8 new tests to fill identified critical gaps
    - Focus on integration points and end-to-end workflows
    - Consider browser tests for visual interaction testing
    - Do NOT write comprehensive coverage for all scenarios
  - [x] 5.4 Implement dark mode support
    - Add `dark:` variant classes to all diagram components
    - Test color contrast for accessibility
    - Ensure cable colors remain visible in dark mode
  - [x] 5.5 Add responsive design adjustments
    - Ensure diagram controls work on tablet viewports (768px - 1024px)
    - Adjust filter sidebar for smaller screens
    - Maintain zoom/pan usability across viewport sizes
  - [x] 5.6 Run feature-specific tests only
    - Run ONLY tests related to connection visualization feature
    - Expected total: approximately 24-32 tests maximum
    - Do NOT run the entire application test suite
    - Verify critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 24-32 tests total)
- Critical user workflows for connection visualization are covered
- No more than 8 additional tests added when filling in testing gaps
- Dark mode and responsive design implemented
- Testing focused exclusively on this spec's feature requirements

---

## Execution Order

Recommended implementation sequence:

1. **API Layer (Task Group 1)** - Build backend endpoints first to provide data for frontend
2. **Core Diagram Components (Task Group 2)** - Create reusable Vue Flow-based components
3. **Diagram Page with Filtering (Task Group 3)** - Assemble page with filtering and interactions
4. **Port Drill-Down and Integration (Task Group 4)** - Add drill-down and integrate with existing views
5. **Test Review and Polish (Task Group 5)** - Fill test gaps and add dark mode/responsive support

---

## Technical Notes

### Library Choice
- Vue Flow is recommended for better Vue 3 integration, built-in features (nodes, edges, controls, minimap), and easier maintainability
- Install: `npm install @vue-flow/core @vue-flow/controls @vue-flow/minimap`

### Existing Code to Reference
- `resources/js/composables/useRackElevation.ts` - State management pattern
- `resources/js/components/elevation/RackElevationView.vue` - Drag event handling, selection
- `app/Http/Controllers/ConnectionController.php` - Existing filtering patterns
- `resources/js/types/connections.ts` - TypeScript interfaces
- `resources/js/components/connections/ConnectionDetailDialog.vue` - Reusable dialog
- `resources/js/components/connections/HierarchicalPortSelector.vue` - Cascading filter pattern
- `resources/js/Pages/ConnectionHistory/Index.vue` - Page layout and breadcrumb pattern

### Visual Design Guidelines
- Use Card components for filter panels consistent with `ConnectionHistory/Index.vue`
- Follow badge styling patterns from `Devices/Show.vue` for status indicators
- Use existing color scheme from Tailwind CSS configuration
- Implement dark mode support using `dark:` variant classes
