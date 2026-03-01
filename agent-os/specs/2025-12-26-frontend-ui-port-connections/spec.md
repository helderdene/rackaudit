# Specification: Frontend UI for Port Connections

## Goal
Build the frontend Vue components to manage port connections (CRUD operations) from the existing PortsSection on the Device Show page, using a hierarchical drill-down for remote port selection and supporting cable property management.

## User Stories
- As an IT Manager, I want to create connections between ports so that I can track physical cable links between devices in my datacenter.
- As an infrastructure operator, I want to view and edit connection details so that I can maintain accurate cable documentation including type, length, and color.

## Specific Requirements

**Display connection info on port rows in PortsSection**
- Add a "Connected To" column to the ports table showing the remote device name and port label when connected
- Show "-" or empty state for ports without connections
- Display connection info as a clickable element that opens the connection detail dialog
- Only show connection column when at least one port has a connection to avoid empty columns

**Create Connection Dialog**
- Triggered by clicking "Connect" action button on an available port row
- Uses hierarchical drill-down selector: Datacenter > Room > Row > Rack > Device > Port
- Pre-selects source port from the current device (not editable)
- Only shows available ports in the destination selector (status = 'available')
- Filters destination ports to match source port type (ethernet-to-ethernet, fiber-to-fiber, power-to-power)
- Includes cable property fields: cable_type (required), cable_length (required), cable_color (optional), path_notes (optional)

**Smart cable type auto-suggestion**
- When source port type is ethernet, default cable_type options to Cat5e/Cat6/Cat6a
- When source port type is fiber, default cable_type options to Fiber SM/Fiber MM
- When source port type is power, default cable_type options to C13/C14/C19/C20
- Auto-select the first appropriate cable type when destination port is selected

**Connection Detail Dialog**
- Opens when clicking on connection info in the port row
- Shows source and destination port/device information (read-only)
- Displays all cable properties: type, length, color, path notes
- Shows logical path as simple text when patch panels are involved (using logical_path from API)
- Edit button to switch to edit mode (if canEdit is true)
- Delete button with confirmation (if canEdit is true)

**Edit Connection Dialog**
- Allows editing cable properties only (source/destination ports cannot be changed)
- Pre-fills existing cable_type, cable_length, cable_color, path_notes values
- Submits PATCH request to update connection
- Closes and refreshes port list on success

**Delete Connection functionality**
- Confirmation dialog before deletion
- Submits DELETE request to backend API
- Shows success message and refreshes port list
- Port status automatically updates to "available" via backend

**Permission-based UI visibility**
- Use existing canEdit prop pattern from PortsSection
- Hide Connect/Edit/Delete buttons when canEdit is false
- Show connection info as view-only for non-editors
- Read-only users can still view connection details but cannot modify

**Hierarchical port selector component**
- Reuse cascading filter pattern from BulkExport/Create.vue
- Extend to include Device level (after Rack) and Port level (after Device)
- Each level filters options based on parent selection
- Reset child selections when parent changes
- Load options via API calls or receive as props from parent page

## Visual Design
No visual mockups were provided. Follow existing dialog and form patterns established in:
- AddPortDialog.vue for dialog structure and form layout
- BulkExport/Create.vue for hierarchical drill-down selector pattern
- EditPortDialog.vue for edit mode form handling

## Existing Code to Leverage

**PortsSection.vue component**
- Primary integration point for connection UI
- Already receives canEdit prop for permission checking
- Table structure to extend with connection column
- Action buttons pattern to add Connect button

**AddPortDialog.vue and EditPortDialog.vue**
- Dialog/modal component structure with DialogHeader, DialogContent, DialogFooter
- Inertia Form component usage pattern with error handling
- Dependent dropdown filtering pattern (type filters subtype/direction)
- Success callback pattern to close dialog and refresh

**BulkExport/Create.vue hierarchical selector**
- Cascading filter pattern for Datacenter > Room > Row > Rack
- Uses FilterOption interface with parent ID references
- watch() hooks to reset child selections when parent changes
- computed() properties to filter options based on parent selection

**ConnectionController.php (backend API)**
- GET /connections for listing with device_id filter support
- POST /connections for creating with StoreConnectionRequest validation
- PATCH /connections/{id} for updating cable properties only
- DELETE /connections/{id} for soft deletion with port status reset
- Static getCableTypeOptions() method for cable type dropdown

**CableType.php enum with forPortType() method**
- Maps port types to valid cable types for smart suggestions
- Provides label() method for human-readable display

## Out of Scope
- Bulk connection operations (connect multiple ports at once)
- Dedicated Connections index page separate from Device Show
- Visual connection diagramming or cable path visualization
- Connection history or audit log display
- Drag-and-drop connection creation
- Connection import/export functionality
- Cross-device connection search
- Connection templates or presets
- Automatic port labeling based on connections
- Real-time connection status monitoring
