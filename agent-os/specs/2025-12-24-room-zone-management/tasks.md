# Task Breakdown: Room/Zone Management

## Overview
Total Tasks: 58 (across 6 task groups)

This feature implements CRUD operations for Rooms, Rows, and PDUs within the datacenter hierarchy. It establishes the foundation for rack placement and device management by creating three new entities that belong to the Datacenter > Room > Row hierarchy.

## Task List

---

### Database Layer

#### Task Group 1: Models, Migrations, and Enums
**Dependencies:** None

- [x] 1.0 Complete database layer for Room, Row, and PDU entities
  - [x] 1.1 Write 6 focused tests for Room, Row, and PDU model functionality
    - Test Room belongs to Datacenter relationship
    - Test Room type enum casting works correctly
    - Test Row belongs to Room relationship
    - Test Row position ordering within Room
    - Test PDU polymorphic assignment (room-level vs row-level)
    - Test enum values return correct labels
  - [x] 1.2 Create RoomType enum
    - Values: ServerRoom, NetworkCloset, CageColocation, Storage, ElectricalRoom
    - Add label() method returning human-readable strings
    - Follow existing enum patterns in the codebase
  - [x] 1.3 Create RowOrientation and RowStatus enums
    - RowOrientation values: HotAisle, ColdAisle
    - RowStatus values: Active, Inactive
    - Add label() methods for human-readable display
  - [x] 1.4 Create PduPhase and PduStatus enums
    - PduPhase values: Single, ThreePhase
    - PduStatus values: Active, Inactive, Maintenance
    - Add label() methods for human-readable display
  - [x] 1.5 Create Room model with Loggable trait
    - Fields: name (string), description (text, nullable), square_footage (decimal, nullable), type (RoomType enum), datacenter_id (foreign key)
    - BelongsTo relationship with Datacenter
    - HasMany relationship with Row (for future use)
    - HasMany relationship with Pdu (room-level PDUs)
    - Apply enum casting for type field
  - [x] 1.6 Create migration for rooms table
    - Add indexes for: datacenter_id, name
    - Foreign key: datacenter_id references datacenters(id) with cascade delete
  - [x] 1.7 Create Row model with Loggable trait
    - Fields: name (string), position (integer), orientation (RowOrientation enum), status (RowStatus enum), room_id (foreign key)
    - BelongsTo relationship with Room
    - HasMany relationship with Pdu (row-level PDUs)
    - Apply enum casting for orientation and status fields
  - [x] 1.8 Create migration for rows table
    - Add indexes for: room_id, position
    - Foreign key: room_id references rooms(id) with cascade delete
  - [x] 1.9 Create Pdu model with Loggable trait
    - Fields: name (string), model (string, nullable), manufacturer (string, nullable), total_capacity_kw (decimal, nullable), voltage (integer, nullable), phase (PduPhase enum), circuit_count (integer), status (PduStatus enum), room_id (foreign key, nullable), row_id (foreign key, nullable)
    - BelongsTo relationships with Room and Row
    - Validation: either room_id or row_id must be set, not both
    - Apply enum casting for phase and status fields
  - [x] 1.10 Create migration for pdus table
    - Add indexes for: room_id, row_id, name
    - Foreign keys: room_id references rooms(id), row_id references rows(id) with set null on delete
  - [x] 1.11 Create RoomFactory with states for each room type
  - [x] 1.12 Create RowFactory with states for orientation and status
  - [x] 1.13 Create PduFactory with states for phase and status
  - [x] 1.14 Ensure database layer tests pass
    - Run ONLY the 6 tests written in 1.1
    - Verify migrations run successfully
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6 tests written in 1.1 pass
- All three models have proper relationships defined
- Enums cast correctly and return proper labels
- Migrations create tables with correct columns, indexes, and foreign keys
- Factories generate valid model instances

---

### Authorization Layer

#### Task Group 2: Policies and Form Requests
**Dependencies:** Task Group 1

- [x] 2.0 Complete authorization layer for Room, Row, and PDU
  - [x] 2.1 Write 6 focused tests for authorization
    - Test RoomPolicy viewAny allows all authenticated users
    - Test RoomPolicy view checks parent Datacenter access for non-admin users
    - Test RoomPolicy create/update/delete restricted to Administrator and IT Manager
    - Test RowPolicy inherits authorization from parent Room
    - Test PduPolicy inherits authorization from parent Room
    - Test Form Request authorization rejects unauthorized users
  - [x] 2.2 Create RoomPolicy following DatacenterPolicy pattern
    - viewAny: allow all authenticated users
    - view: admins see all, others check user-datacenter assignment
    - create/update/delete: restricted to ADMIN_ROLES (Administrator, IT Manager)
    - Access check leverages parent Datacenter's user relationship
  - [x] 2.3 Create RowPolicy extending authorization from Room
    - All actions inherit from parent Room's access control
    - Check user can view parent Room before allowing Row actions
  - [x] 2.4 Create PduPolicy extending authorization from Room
    - All actions inherit from parent Room's access control
    - Handle both room-level and row-level PDU authorization
  - [x] 2.5 Create StoreRoomRequest with validation rules
    - Rules: name (required, string, max:255), description (nullable, string), square_footage (nullable, numeric, min:0), type (required, enum:RoomType)
    - AUTHORIZED_ROLES constant for Administrator and IT Manager
    - Custom error messages following existing patterns
  - [x] 2.6 Create UpdateRoomRequest with validation rules
    - Same rules as StoreRoomRequest
    - Inherit authorization pattern
  - [x] 2.7 Create StoreRowRequest with validation rules
    - Rules: name (required, string, max:255), position (required, integer, min:0), orientation (required, enum:RowOrientation), status (required, enum:RowStatus)
    - AUTHORIZED_ROLES constant
    - Custom error messages
  - [x] 2.8 Create UpdateRowRequest with validation rules
  - [x] 2.9 Create StorePduRequest with validation rules
    - Rules: name (required, string, max:255), model (nullable, string, max:255), manufacturer (nullable, string, max:255), total_capacity_kw (nullable, numeric, min:0), voltage (nullable, integer, min:0), phase (required, enum:PduPhase), circuit_count (required, integer, min:1), status (required, enum:PduStatus), room_id (nullable, exists:rooms,id), row_id (nullable, exists:rows,id)
    - Custom validation: either room_id or row_id must be provided
    - AUTHORIZED_ROLES constant
  - [x] 2.10 Create UpdatePduRequest with validation rules
  - [x] 2.11 Ensure authorization layer tests pass
    - Run ONLY the 6 tests written in 2.1
    - Verify policy methods work correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6 tests written in 2.1 pass
- Policies correctly inherit access from parent Datacenter
- Form Requests validate all required fields
- PDU validation ensures proper room/row assignment
- Authorization rejects unauthorized users appropriately

---

### API Layer

#### Task Group 3: Controllers and Routes
**Dependencies:** Task Group 2

- [x] 3.0 Complete API layer for Room, Row, and PDU CRUD
  - [x] 3.1 Write 8 focused tests for controller functionality
    - Test RoomController index returns rooms filtered by datacenter
    - Test RoomController store creates room with valid data
    - Test RoomController show returns room with rows and PDUs
    - Test RowController index returns rows for a room
    - Test RowController store creates row with correct position
    - Test PduController store creates PDU with room-level assignment
    - Test PduController store creates PDU with row-level assignment
    - Test search functionality filters rooms by name
  - [x] 3.2 Create RoomController following DatacenterController pattern
    - index: paginated rooms filtered by datacenter context with search
    - create: render create form with RoomType enum options
    - store: validate and create room, redirect to datacenter show
    - show: room details with rows and PDUs lists
    - edit: render edit form with current values
    - update: validate and update room
    - destroy: delete room with cascade to rows and PDUs
    - Include canCreate, canEdit, canDelete permission flags
  - [x] 3.3 Create RowController for nested Row CRUD
    - index: rows for a specific room ordered by position
    - store: create row with auto-positioning or specified position
    - update: update row details and/or position
    - destroy: delete row, handle PDU reassignment if needed
    - Include orientation and status enum options
  - [x] 3.4 Create PduController for PDU CRUD
    - index: PDUs for a room (including row-level PDUs)
    - store: create PDU with room or row assignment
    - update: update PDU details or reassign to different row
    - destroy: delete PDU
    - Include assignment level indicator in responses
  - [x] 3.5 Register Room routes nested under Datacenter
    - Route: /datacenters/{datacenter}/rooms
    - Use resource routing with parent model binding
    - Register in routes/web.php following existing patterns
  - [x] 3.6 Register Row routes nested under Room
    - Route: /datacenters/{datacenter}/rooms/{room}/rows
    - Use resource routing with parent model binding
  - [x] 3.7 Register PDU routes nested under Room
    - Route: /datacenters/{datacenter}/rooms/{room}/pdus
    - Use resource routing with parent model binding
  - [x] 3.8 Run Wayfinder generation for new routes
    - Execute: php artisan wayfinder:generate
    - Verify TypeScript actions are created for all new controllers
  - [x] 3.9 Ensure API layer tests pass
    - Run ONLY the 8 tests written in 3.1
    - Verify all CRUD operations work
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 8 tests written in 3.1 pass
- All CRUD operations function correctly
- Routes are properly nested with parent model binding
- Wayfinder generates TypeScript functions for all endpoints
- Authorization is enforced on all actions

---

### Frontend Components

#### Task Group 4: Room UI Components and Pages
**Dependencies:** Task Group 3 (completed)

- [x] 4.0 Complete Room UI following Datacenter patterns
  - [x] 4.1 Write 4 focused tests for Room UI components
    - Test Rooms/Index.vue renders room list correctly
    - Test Rooms/Create.vue form submits valid data
    - Test Rooms/Show.vue displays room details with rows and PDUs sections
    - Test search input filters rooms by name
  - [x] 4.2 Create TypeScript interfaces for Room, Row, and PDU data
    - RoomData interface with all fields including type label
    - RowData interface with orientation and status labels
    - PduData interface with assignment level indicator
    - Pagination and filter interfaces following existing patterns
  - [x] 4.3 Create Rooms/Index.vue page
    - Reuse layout from Datacenters/Index.vue
    - Table columns: Name, Type, Row Count (placeholder "-"), Rack Count (placeholder "-")
    - Search by room name
    - "Create Room" button for users with canCreate permission
    - View/Edit/Delete actions following existing button patterns
    - Breadcrumbs: Datacenters > [Datacenter Name] > Rooms
  - [x] 4.4 Create Rooms/Create.vue page
    - Form fields: name, description, square_footage, type (dropdown with RoomType options)
    - Follow form patterns from Datacenters/Create.vue
    - Use Wayfinder for form action and cancel navigation
    - Breadcrumbs: Datacenters > [Datacenter Name] > Rooms > Create
  - [x] 4.5 Create Rooms/Edit.vue page
    - Pre-populate form with existing room data
    - Follow patterns from Datacenters/Edit.vue
    - Breadcrumbs: Datacenters > [Datacenter Name] > Rooms > [Room Name] > Edit
  - [x] 4.6 Create Rooms/Show.vue page
    - Room details card with all attributes
    - Rows section: table with Name, Rack Count (placeholder), Status, Actions
    - PDUs section: table with Name, Model, Capacity, Circuit Usage (placeholder), Status, Assignment Level
    - "Add Row" and "Add PDU" buttons for authorized users
    - Breadcrumbs: Datacenters > [Datacenter Name] > Rooms > [Room Name]
  - [x] 4.7 Create DeleteRoomDialog component
    - Follow DeleteDatacenterDialog pattern
    - Confirmation modal with room name
    - Handle delete action with proper redirect
  - [x] 4.8 Ensure Room UI tests pass
    - Run ONLY the 4 tests written in 4.1
    - Verify pages render and forms submit correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4 tests written in 4.1 pass
- Room pages follow existing Datacenter UI patterns
- Forms validate and submit correctly
- Breadcrumb navigation works correctly
- Table displays room data with proper formatting

---

#### Task Group 5: Row and PDU UI Components
**Dependencies:** Task Group 4 (completed)

- [x] 5.0 Complete Row and PDU UI components
  - [x] 5.1 Write 4 focused tests for Row and PDU UI
    - Test Rows/Create.vue form submits with orientation selection
    - Test PDU form allows selection between room-level and row-level assignment
    - Test inline row editing updates position correctly
    - Test PDU list displays assignment level indicator
  - [x] 5.2 Create Rows/Create.vue modal or page
    - Form fields: name, position, orientation (dropdown), status (dropdown)
    - Use inline form or modal within Room Show page
    - Handle position auto-increment if not specified
  - [x] 5.3 Create Rows/Edit.vue modal or page
    - Pre-populate with existing row data
    - Allow position reordering
    - Match create form layout
  - [x] 5.4 Create DeleteRowDialog component
    - Confirmation with row name
    - Warning about PDU reassignment if row has PDUs
  - [x] 5.5 Create Pdus/Create.vue modal or page
    - Form fields: name, model, manufacturer, total_capacity_kw, voltage, phase (dropdown), circuit_count, status (dropdown)
    - Assignment dropdown: "Room Level" or select specific Row
    - Populate row options from parent room's rows
  - [x] 5.6 Create Pdus/Edit.vue modal or page
    - Pre-populate with existing PDU data
    - Allow reassignment between room and row levels
    - Match create form layout
  - [x] 5.7 Create DeletePduDialog component
    - Confirmation with PDU name
    - Simple deletion without cascade concerns
  - [x] 5.8 Create Row list component with inline actions
    - Render within Room Show page
    - Support inline editing or link to edit modal
    - Display position ordering with visual indicator
  - [x] 5.9 Create PDU list component with assignment indicator
    - Render within Room Show page
    - Show "Room Level" or "Row: [Row Name]" for assignment
    - Display capacity and circuit info
  - [x] 5.10 Ensure Row and PDU UI tests pass
    - Run ONLY the 4 tests written in 5.1
    - Verify forms and lists render correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4 tests written in 5.1 pass
- Row and PDU forms work within Room Show page context
- Assignment level selection functions correctly for PDUs
- Lists display appropriate data and action buttons
- Modals/forms follow existing UI patterns

---

### Testing

#### Task Group 6: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-5

- [x] 6.0 Review existing tests and fill critical gaps only
  - [x] 6.1 Review tests from Task Groups 1-5
    - Review the 6 tests from database layer (Task 1.1)
    - Review the 6 tests from authorization layer (Task 2.1)
    - Review the 8 tests from API layer (Task 3.1)
    - Review the 4 tests from Room UI (Task 4.1)
    - Review the 4 tests from Row/PDU UI (Task 5.1)
    - Total existing tests: 28 tests
  - [x] 6.2 Analyze test coverage gaps for Room/Zone Management feature
    - Identify critical user workflows lacking coverage
    - Focus ONLY on gaps related to this spec's requirements
    - Prioritize end-to-end workflows: create room with rows, assign PDUs
    - Do NOT assess entire application test coverage
  - [x] 6.3 Write up to 8 additional strategic tests maximum
    - E2E: Create room, add rows, add PDUs workflow
    - E2E: Room deletion cascades to rows and orphans PDUs (nullOnDelete behavior)
    - Integration: Row deletion reassigns PDUs to room level
    - Integration: PDU reassignment from room to row level
    - Activity logging captures Room, Row, PDU CRUD events
    - Edge case: Position reordering maintains integrity
    - Edge case: Room update functionality
    - Authorization: Non-admin user access to assigned datacenter's rooms
  - [x] 6.4 Run feature-specific tests only
    - Run ONLY tests related to Room/Zone Management feature
    - Total: 36 tests (28 existing + 8 new)
    - Do NOT run the entire application test suite
    - Verify critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (36 tests total)
- Critical user workflows for Room/Zone Management are covered
- 8 additional tests added in IntegrationAndEdgeCasesTest.php
- Testing focused exclusively on this spec's feature requirements

---

## Execution Order

Recommended implementation sequence:

1. **Database Layer (Task Group 1)** - Foundation: models, migrations, enums
2. **Authorization Layer (Task Group 2)** - Security: policies and form requests
3. **API Layer (Task Group 3)** - Backend: controllers and routes
4. **Room UI (Task Group 4)** - Frontend: Room pages and components
5. **Row/PDU UI (Task Group 5)** - Frontend: Row and PDU forms and lists
6. **Test Review (Task Group 6)** - Quality: gap analysis and final verification

---

## Technical Notes

### Route Structure
```
/datacenters/{datacenter}/rooms                     # Room index
/datacenters/{datacenter}/rooms/create              # Room create form
/datacenters/{datacenter}/rooms/{room}              # Room show
/datacenters/{datacenter}/rooms/{room}/edit         # Room edit form
/datacenters/{datacenter}/rooms/{room}/rows         # Row index (inline in Room show)
/datacenters/{datacenter}/rooms/{room}/rows/create  # Row create (modal)
/datacenters/{datacenter}/rooms/{room}/rows/{row}   # Row operations
/datacenters/{datacenter}/rooms/{room}/pdus         # PDU index (inline in Room show)
/datacenters/{datacenter}/rooms/{room}/pdus/create  # PDU create (modal)
/datacenters/{datacenter}/rooms/{room}/pdus/{pdu}   # PDU operations
```

### Enum Values Summary
| Enum | Values |
|------|--------|
| RoomType | ServerRoom, NetworkCloset, CageColocation, Storage, ElectricalRoom |
| RowOrientation | HotAisle, ColdAisle |
| RowStatus | Active, Inactive |
| PduPhase | Single, ThreePhase |
| PduStatus | Active, Inactive, Maintenance |

### Key Patterns to Reuse
- DatacenterController for controller structure and authorization
- DatacenterPolicy for policy inheritance pattern
- StoreDatacenterRequest for form request structure
- Datacenters/Index.vue for table and pagination patterns
- Datacenters/Show.vue for detail view layout
- DeleteDatacenterDialog for confirmation modals
- Loggable trait for activity logging

### PDU Assignment Logic
- If `row_id` is set: PDU is row-level (assigned to specific row)
- If only `room_id` is set: PDU is room-level (serves entire room)
- Validation ensures exactly one of room_id or row_id is populated
