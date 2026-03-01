# Task Breakdown: Frontend UI for Port Connections

## Overview
Total Tasks: 4 Task Groups with 28 Sub-Tasks

This feature adds CRUD operations for port connections to the existing PortsSection component on the Device Show page. The implementation includes a hierarchical port selector (Datacenter > Room > Row > Rack > Device > Port), connection detail/edit dialogs, smart cable type suggestions, and permission-based UI visibility.

## Task List

### TypeScript Types & Shared Utilities

#### Task Group 1: Connection Types and Interfaces
**Dependencies:** None

- [x] 1.0 Complete TypeScript types and shared utilities
  - [x] 1.1 Write 2-4 focused tests for connection type utilities
    - Test cable type options filtering by port type
    - Test connection data type validation
    - Test hierarchical filter option structure
  - [x] 1.2 Create connection TypeScript interfaces in `/resources/js/types/connections.ts`
    - `ConnectionData` interface with all connection fields (id, source_port_id, destination_port_id, cable_type, cable_length, cable_color, path_notes, logical_path, timestamps)
    - `ConnectionWithPorts` interface extending ConnectionData with source_port and destination_port relations
    - `CableTypeOption` interface with value, label, and port_types array
    - `HierarchicalFilterOption` interface extending existing FilterOption with device_id and rack_id
    - `AvailablePortOption` interface for port selector (id, label, device_name, device_id, type, status)
  - [x] 1.3 Create cable type utility functions
    - `getCableTypesForPortType(portType: PortTypeValue): CableTypeOption[]` function
    - Map ethernet ports to Cat5e/Cat6/Cat6a options
    - Map fiber ports to Fiber SM/Fiber MM options
    - Map power ports to C13/C14/C19/C20 options
    - Follow pattern from existing port type utilities
  - [x] 1.4 Extend PortData interface in `/resources/js/types/ports.ts`
    - Add optional `connection` property of type ConnectionWithPorts
    - Add optional `remote_device_name` and `remote_port_label` for display
  - [x] 1.5 Ensure type utilities tests pass
    - Run ONLY the tests written in 1.1
    - Verify type exports work correctly

**Acceptance Criteria:**
- All connection-related TypeScript interfaces are defined
- Cable type utility functions return correct options per port type
- PortData interface extended with connection fields
- Tests pass for type utilities

### Hierarchical Port Selector Component

#### Task Group 2: HierarchicalPortSelector Component
**Dependencies:** Task Group 1

- [x] 2.0 Complete hierarchical port selector component
  - [x] 2.1 Write 3-5 focused tests for HierarchicalPortSelector
    - Test cascading filter behavior (parent change resets children)
    - Test port filtering by type and availability
    - Test disabled state when parent not selected
    - Test selection callback with full port data
  - [x] 2.2 Create HierarchicalPortSelector.vue component
    - Location: `/resources/js/components/connections/HierarchicalPortSelector.vue`
    - Props: filterOptions, sourcePortType, excludeDeviceId, modelValue (selected port)
    - Emit: update:modelValue with selected port data
    - Follow cascading filter pattern from BulkExport/Create.vue
  - [x] 2.3 Implement datacenter/room/row/rack cascading dropdowns
    - Reuse FilterOption interface and computed filtering pattern
    - Add watch() hooks to reset child selections when parent changes
    - Use existing select styling from AddPortDialog.vue
  - [x] 2.4 Add device dropdown (new level after Rack)
    - Fetch devices for selected rack via API or props
    - Filter to only show devices with available ports of matching type
    - Exclude current device (using excludeDeviceId prop)
  - [x] 2.5 Add port dropdown (final level after Device)
    - Show only ports with status = 'available'
    - Filter by port type matching sourcePortType prop
    - Display port label and subtype in option
  - [x] 2.6 Implement loading states for API-fetched options
    - Show spinner while loading devices/ports
    - Disable dependent dropdowns during loading
  - [x] 2.7 Ensure HierarchicalPortSelector tests pass
    - Run ONLY the tests written in 2.1
    - Verify cascading behavior works correctly

**Acceptance Criteria:**
- Component correctly filters options at each hierarchy level
- Child selections reset when parent changes
- Only available ports of matching type are shown
- Current device is excluded from selection
- Loading states display appropriately

### Connection Dialog Components

#### Task Group 3: Connection CRUD Dialog Components
**Dependencies:** Task Groups 1, 2

- [x] 3.0 Complete connection dialog components
  - [x] 3.1 Write 4-6 focused tests for connection dialogs
    - Test CreateConnectionDialog form submission
    - Test ConnectionDetailDialog displays connection info
    - Test EditConnectionDialog pre-fills existing values
    - Test delete confirmation workflow
    - Test permission-based button visibility
  - [x] 3.2 Create CreateConnectionDialog.vue component
    - Location: `/resources/js/components/connections/CreateConnectionDialog.vue`
    - Props: sourcePort (PortData), deviceId, filterOptions, cableTypeOptions, canEdit
    - Display source port info as read-only (device name, port label)
    - Include HierarchicalPortSelector for destination port selection
    - Cable property fields: cable_type (required select), cable_length (required input), cable_color (optional input), path_notes (optional textarea)
    - Auto-select first cable type when destination port selected
    - Submit POST to `/connections` endpoint
    - Follow Form component pattern from AddPortDialog.vue
  - [x] 3.3 Create ConnectionDetailDialog.vue component
    - Location: `/resources/js/components/connections/ConnectionDetailDialog.vue`
    - Props: connection (ConnectionWithPorts), canEdit
    - Display source and destination port/device info (read-only)
    - Show cable properties: type, length, color, path notes
    - Display logical_path text if present (for patch panel connections)
    - Edit button (visible when canEdit=true) opens EditConnectionDialog
    - Delete button (visible when canEdit=true) triggers confirmation
  - [x] 3.4 Create EditConnectionDialog.vue component
    - Location: `/resources/js/components/connections/EditConnectionDialog.vue`
    - Props: connection (ConnectionWithPorts), cableTypeOptions
    - Pre-fill existing cable_type, cable_length, cable_color, path_notes
    - Source/destination ports shown as read-only info (not editable)
    - Submit PUT to `/connections/{id}` endpoint
    - Follow edit dialog pattern from EditPortDialog.vue
  - [x] 3.5 Create DeleteConnectionConfirmation.vue component
    - Location: `/resources/js/components/connections/DeleteConnectionConfirmation.vue`
    - Props: connection, onConfirm callback
    - Confirmation dialog with connection details summary
    - Submit DELETE to `/connections/{id}` endpoint
    - Show success message and trigger refresh
    - Follow delete dialog pattern from DeletePortDialog.vue
  - [x] 3.6 Implement smart cable type auto-suggestion
    - Watch destination port selection in CreateConnectionDialog
    - When destination selected, auto-select first matching cable type
    - Update cable type options based on source port type
  - [x] 3.7 Apply consistent styling and dark mode support
    - Follow existing dialog styling patterns
    - Ensure all dialogs support dark mode (dark: classes)
    - Use existing design system variables
  - [x] 3.8 Ensure connection dialog tests pass
    - Run ONLY the tests written in 3.1
    - Verify form submissions work correctly
    - Verify permission-based visibility

**Acceptance Criteria:**
- CreateConnectionDialog allows creating connections with hierarchical port selection
- ConnectionDetailDialog displays all connection info with edit/delete actions
- EditConnectionDialog allows updating cable properties only
- Delete confirmation prevents accidental deletions
- Smart cable type suggestion works based on port type
- Permission-based UI correctly shows/hides actions

### PortsSection Integration

#### Task Group 4: PortsSection Integration and Final Testing
**Dependencies:** Task Groups 1, 2, 3

- [x] 4.0 Complete PortsSection integration and final testing
  - [x] 4.1 Write 3-5 focused integration tests
    - Test "Connected To" column displays for connected ports
    - Test "Connect" button appears for available ports when canEdit=true
    - Test clicking connection info opens detail dialog
    - Test connection column hidden when no connections exist
  - [x] 4.2 Update PortsSection.vue to add "Connected To" column
    - Conditionally render column when any port has a connection
    - Display remote device name + port label as clickable element
    - Show "-" for ports without connections
    - Add column header between Status and Actions
  - [x] 4.3 Add connection interaction handlers to PortsSection
    - Click handler on connection info opens ConnectionDetailDialog
    - Import and integrate ConnectionDetailDialog component
    - Pass connection data and canEdit prop
  - [x] 4.4 Add "Connect" action button for available ports
    - Show only when port.status === 'available' and canEdit=true
    - Button opens CreateConnectionDialog
    - Pass source port, deviceId, filterOptions props
  - [x] 4.5 Update Device Show page controller to pass connection filter options
    - Add filterOptions prop with datacenters, rooms, rows, racks hierarchy
    - Add cableTypeOptions prop from CableType enum
    - Include connection data in ports array via eager loading
  - [x] 4.6 Implement connection refresh after CRUD operations
    - Reload page or update ports data after create/edit/delete
    - Follow existing refresh pattern from port dialogs
  - [x] 4.7 Ensure integration tests pass
    - Run ONLY the tests written in 4.1
    - Verify PortsSection displays connections correctly
    - Verify CRUD operations work end-to-end

**Acceptance Criteria:**
- PortsSection displays "Connected To" column when connections exist
- Available ports show "Connect" button for editors
- Clicking connection info opens detail dialog
- CRUD operations refresh the port list correctly
- Non-editors see connection info but no edit/delete actions

### Final Testing

#### Task Group 5: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-4 (completed)

- [x] 5.0 Review existing tests and fill critical gaps only
  - [x] 5.1 Review tests from Task Groups 1-4
    - Review tests from type utilities (Task 1.1) - 8 tests in resources/js/types/__tests__/connections.test.ts
    - Review tests from HierarchicalPortSelector (Task 2.1) - 5 tests in tests/Feature/ConnectionManagement/HierarchicalPortSelectorTest.php
    - Review tests from connection dialogs (Task 3.1) - 6 tests in tests/Feature/ConnectionManagement/ConnectionDialogTest.php
    - Review tests from PortsSection integration (Task 4.1) - 5 tests in tests/Feature/ConnectionManagement/PortsSectionConnectionTest.php
    - Total existing tests: approximately 24 tests
  - [x] 5.2 Analyze test coverage gaps for THIS feature only
    - Identify critical user workflows lacking test coverage
    - Focus ONLY on port connections feature requirements
    - Prioritize end-to-end workflows over unit test gaps
  - [x] 5.3 Write up to 10 additional strategic tests maximum
    - Focus on integration points between components
    - Test full create-view-edit-delete workflow
    - Test hierarchical selector with actual API responses
    - Test permission scenarios (canEdit true/false)
    - Skip edge cases unless business-critical
  - [x] 5.4 Run feature-specific tests only
    - Run ONLY tests related to port connections feature
    - Expected total: approximately 22-30 tests maximum
    - Verify critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass
- Critical user workflows for port connections are covered
- No more than 10 additional tests added
- Testing focused exclusively on this feature

## Execution Order

Recommended implementation sequence:

1. **Task Group 1: TypeScript Types** - Foundation for all components
2. **Task Group 2: HierarchicalPortSelector** - Core reusable component for port selection
3. **Task Group 3: Connection Dialogs** - CRUD UI components
4. **Task Group 4: PortsSection Integration** - Wire everything together
5. **Task Group 5: Test Review** - Fill coverage gaps and validate

## Files to Create/Modify

### New Files
- `/resources/js/types/connections.ts` - Connection TypeScript interfaces
- `/resources/js/components/connections/HierarchicalPortSelector.vue` - Cascading port selector
- `/resources/js/components/connections/CreateConnectionDialog.vue` - Create connection form
- `/resources/js/components/connections/ConnectionDetailDialog.vue` - View connection details
- `/resources/js/components/connections/EditConnectionDialog.vue` - Edit connection form
- `/resources/js/components/connections/DeleteConnectionConfirmation.vue` - Delete confirmation

### Modified Files
- `/resources/js/types/ports.ts` - Extend PortData with connection fields
- `/resources/js/Components/Devices/PortsSection.vue` - Add connection column and actions
- `/app/Http/Controllers/DeviceController.php` - Pass filterOptions and cableTypeOptions to show view

## Key Patterns to Follow

### Existing Patterns to Reuse
- **Dialog structure**: Use Dialog/DialogContent/DialogHeader/DialogFooter from AddPortDialog.vue
- **Form handling**: Use Inertia Form component with @success callback pattern
- **Cascading filters**: Use computed() + watch() pattern from BulkExport/Create.vue
- **Dependent dropdowns**: Use filtering pattern from AddPortDialog.vue (type filters subtype)
- **Permission checking**: Use canEdit prop pattern from PortsSection.vue
- **Table styling**: Follow existing table structure in PortsSection.vue

### API Endpoints (Backend)
- `GET /connections?device_id={id}` - List connections for a device
- `POST /connections` - Create new connection
- `PUT /connections/{id}` - Update connection cable properties
- `DELETE /connections/{id}` - Delete connection
