# Specification: Room/Zone Management

## Goal
Enable CRUD operations for Rooms, Rows, and PDUs within the datacenter hierarchy, establishing the foundation for rack placement and device management.

## User Stories
- As a datacenter administrator, I want to create and manage rooms within a datacenter so that I can organize physical spaces for rack placement.
- As an IT manager, I want to define rows with hot/cold aisle designation within rooms so that I can plan proper cooling and rack positioning.
- As an operator, I want to assign PDUs to rooms or rows so that I can track power distribution infrastructure.

## Specific Requirements

**Room Entity CRUD**
- Attributes: name (required, string), description (optional, text), square_footage (optional, decimal), type (enum: server_room, network_closet, cage_colocation, storage, electrical_room), datacenter_id (foreign key)
- Room belongs to Datacenter; access inherits from parent Datacenter's user access relationship
- Follow existing Gate authorization pattern from DatacenterPolicy for CRUD operations
- Use Loggable trait for activity logging on create, update, delete events

**Room Type Enum**
- Create RoomType enum with values: ServerRoom, NetworkCloset, CageColocation, Storage, ElectricalRoom
- Display human-readable labels in UI (e.g., "Server Room", "Network Closet", "Cage/Colocation Space")
- Use enum casting in model following Laravel conventions

**Room Index View**
- Display rooms filtered by datacenter context with columns: Name, Type, Row Count (placeholder "-"), Rack Count (placeholder "-")
- Include search functionality by room name
- Reuse table layout, pagination, and button patterns from Datacenters/Index.vue
- Show "Create Room" button only for users with Administrator or IT Manager roles

**Row Entity CRUD**
- Attributes: name (required, string), position (integer for ordering), orientation (enum: hot_aisle, cold_aisle), status (enum: active, inactive), room_id (foreign key)
- Row belongs to Room; position field determines display order within room
- Use Loggable trait for activity logging

**Row Orientation and Status Enums**
- Create RowOrientation enum with values: HotAisle, ColdAisle
- Create RowStatus enum with values: Active, Inactive
- Apply enum casting in model

**Row Management within Room**
- Display rows as a list section within Room Show page with columns: Name, Rack Count (placeholder "-"), Status
- Include inline create/edit capabilities or modal-based forms following existing patterns
- Support drag-and-drop reordering or position field editing for row sequencing

**PDU Entity CRUD**
- Attributes: name (required, string), model (optional, string), manufacturer (optional, string), total_capacity_kw (optional, decimal), voltage (optional, integer), phase (enum: single, three_phase), circuit_count (integer), status (enum: active, inactive, maintenance)
- PDU can belong to Room OR Row (polymorphic or nullable foreign keys: room_id, row_id)
- If row_id is set, PDU is row-level; otherwise room_id indicates room-level assignment
- Use Loggable trait for activity logging

**PDU Phase and Status Enums**
- Create PduPhase enum with values: Single, ThreePhase
- Create PduStatus enum with values: Active, Inactive, Maintenance
- Apply enum casting in model

**PDU List and Assignment View**
- Display PDUs within Room Show page with columns: Name, Model, Capacity, Circuit Usage (placeholder "N/A"), Status
- Show assignment level indicator (Room-level or Row: [Row Name])
- Support creating PDU assigned to room or to specific row via dropdown selection

**Navigation and Routing**
- Nest Room routes under Datacenter: /datacenters/{datacenter}/rooms
- Nest Row routes under Room: /datacenters/{datacenter}/rooms/{room}/rows
- PDU routes can be nested or standalone: /datacenters/{datacenter}/rooms/{room}/pdus
- Use Laravel resource routing with parent model binding

## Visual Design
No visual mockups provided - follow existing Datacenter module UI patterns for consistency.

## Existing Code to Leverage

**DatacenterController.php**
- Reuse controller structure with Gate authorization, request validation, and Inertia rendering pattern
- Follow paginated index with search, show with related data, store/update with Form Request validation
- Apply ADMIN_ROLES constant pattern for role-based create/edit/delete permissions

**Datacenter Model and Loggable Trait**
- Apply Loggable trait to Room, Row, and PDU models for automatic activity logging
- Follow fillable array pattern and Attribute accessor pattern for computed properties
- Use BelongsTo relationships for hierarchy (Room->Datacenter, Row->Room)

**DatacenterPolicy.php**
- Create RoomPolicy extending access checks to parent Datacenter
- ViewAny allows all authenticated users; view checks user-datacenter assignment for non-admin roles
- Create/update/delete restricted to Administrator and IT Manager roles

**StoreDatacenterRequest.php**
- Follow Form Request pattern with AUTHORIZED_ROLES constant and authorize() method
- Use array-based validation rules with custom error messages
- Create StoreRoomRequest, UpdateRoomRequest, StoreRowRequest, UpdateRowRequest, StorePduRequest, UpdatePduRequest

**Datacenters/Index.vue and Show.vue**
- Reuse page layout with AppLayout, HeadingSmall, breadcrumbs, and table structure
- Reuse Card components for detail sections on Show page
- Follow TypeScript interface patterns for props and data structures

## Out of Scope
- Floor plan visualization or room layout diagrams
- Capacity planning dashboards or power load calculations
- Real-time power monitoring or circuit-level tracking
- Detailed PDU circuit management (individual circuit assignment)
- Separate room-level access permissions (inherits from Datacenter)
- Rack entity creation (deferred to Rack Management spec)
- Device placement within racks (deferred to Device Placement spec)
- Electrical wiring or connectivity diagrams
- Environmental monitoring (temperature, humidity sensors)
- Automated hot/cold aisle optimization recommendations
