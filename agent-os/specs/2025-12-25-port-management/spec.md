# Specification: Port Management

## Goal
Enable CRUD operations for ports on devices with type classification (Ethernet, Fiber, Power), labeling, status tracking, and visual position data to support future visual port mapping capabilities.

## User Stories
- As an IT Manager, I want to add and manage ports on devices so that I can accurately track the physical connectivity options of each asset
- As a Technician, I want to see all ports on a device with their status and labels so that I can quickly identify available connection points

## Specific Requirements

**Port Model with Device Relationship**
- Port belongs to Device (one-to-many relationship)
- Use `device_id` foreign key with cascade delete
- Include `Loggable` concern for activity logging
- Generate migration with proper indexes on `device_id`, `type`, and `status`

**Port Type Hierarchy with Enums**
- Create `PortType` enum with three categories: Ethernet, Fiber, Power
- Create `PortSubtype` enum with values grouped by parent type:
  - Ethernet: 1GbE, 10GbE, 25GbE, 40GbE, 100GbE
  - Fiber: LC, SC, MPO
  - Power: C13, C14, C19, C20
- Store both `type` (PortType) and `subtype` (PortSubtype) as separate enum-casted fields
- Add validation to ensure subtype matches parent type

**Port Status Tracking**
- Create `PortStatus` enum with values: Available, Connected, Reserved, Disabled
- Default new ports to Available status
- Include `label()` method for human-readable display
- Status should visually differentiate on the Device Show page using Badge component

**Dual Labeling System**
- `label` field: user-defined text label (e.g., "eth0", "port1", "PSU-A")
- `position_slot` field: optional slot/module number for modular devices (nullable integer)
- `position_row` and `position_column` fields: optional physical grid position for patch panels (nullable integers)
- Label is required, physical position fields are optional

**Direction Attribute for Connection Validation**
- Create `PortDirection` enum with values appropriate per type:
  - Network (Ethernet/Fiber): Uplink, Downlink, Bidirectional
  - Power: Input, Output
- Store as `direction` field with enum cast
- Default to Bidirectional for network ports, Input for power ports
- Will be used for connection validation in future spec #17

**Visual Port Mapping Position Data**
- Include `visual_x` and `visual_y` fields (nullable decimal/float)
- Store coordinates as percentage (0-100) for device face positioning
- Include `visual_face` field to indicate front/rear placement
- Designed for future visual port mapping feature (#20)

**Port Section on Device Show Page**
- Add new Card section below existing cards titled "Ports"
- Use Plug icon from lucide-vue-next
- Display ports in a responsive table with columns: Label, Type, Subtype, Direction, Status
- Show port count in CardTitle (e.g., "Ports (24)")
- Include empty state when no ports exist
- Add "Add Port" and "Bulk Add" buttons when user has edit permission

**Bulk Port Creation**
- Provide batch creation endpoint accepting template parameters
- Template pattern: prefix, start number, end number, type, subtype, direction
- Generate labels automatically (e.g., "eth1", "eth2", ... "eth24")
- Create all ports in single transaction for atomicity
- Return created ports collection in response

**Port Controller API Endpoints**
- Nest port routes under devices: `devices/{device}/ports`
- Standard CRUD: index, store, show, update, destroy
- Additional bulk endpoint: `devices/{device}/ports/bulk`
- Follow DeviceController patterns for JSON/Inertia responses
- Use Form Request classes for validation

## Visual Design
No visual mockups were provided. The implementation should follow existing Device Show page patterns using Card components with consistent spacing and typography.

## Existing Code to Leverage

**DeviceController (app/Http/Controllers/DeviceController.php)**
- Follow same controller structure with Gate authorization
- Reuse enum options helper pattern (getLifecycleStatusOptions)
- Apply same JSON/Inertia response pattern (wantsJson check)
- Use resource controller pattern with nested routes

**DeviceLifecycleStatus Enum (app/Enums/DeviceLifecycleStatus.php)**
- Follow same enum structure with string-backed cases
- Include `label()` method for human-readable display
- Use TitleCase for enum case names

**Device Model (app/Models/Device.php)**
- Add `ports()` hasMany relationship to Device model
- Follow same casts() method pattern for enum casting
- Use Loggable concern for activity logging

**Device Show Page (resources/js/Pages/Devices/Show.vue)**
- Reuse Card, CardHeader, CardTitle, CardContent components
- Follow existing grid layout and typography patterns
- Use Badge component for status display with variant mapping

**StoreDeviceRequest (app/Http/Requests/StoreDeviceRequest.php)**
- Follow same Form Request pattern with role-based authorization
- Use Rule::enum() for enum validation
- Include custom error messages

## Out of Scope
- Port-to-port connections (deferred to roadmap item #17)
- VLAN assignments for network ports
- Power consumption tracking for power ports
- Automatic port discovery from network devices
- Standalone global ports index page (ports only accessible via Device Show)
- Port templates/profiles at device type level
- Import/export of port configurations
- Port statistics or utilization metrics
- Cable management or cable types
- Port grouping or link aggregation
