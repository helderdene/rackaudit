# Task Breakdown: Port Management

## Overview
Total Tasks: 4 Task Groups with 31 Sub-tasks

This feature enables CRUD operations for ports on devices with type classification (Ethernet, Fiber, Power), labeling, status tracking, and visual position data to support future visual port mapping capabilities.

## Task List

### Database Layer

#### Task Group 1: Enums, Models, and Migrations
**Dependencies:** None

- [x] 1.0 Complete database layer for Port Management
  - [x] 1.1 Write 6 focused tests for Port model functionality
    - Test Port belongs to Device relationship
    - Test PortType and PortSubtype enum casts work correctly
    - Test PortStatus enum cast and default value
    - Test PortDirection enum cast with type-appropriate defaults
    - Test subtype validation matches parent type (Ethernet subtypes only with Ethernet type)
    - Test cascade delete when parent Device is deleted
  - [x] 1.2 Create PortType enum
    - Location: `app/Enums/PortType.php`
    - Cases: Ethernet, Fiber, Power
    - Include `label()` method for human-readable display
    - Follow pattern from `DeviceLifecycleStatus` enum
  - [x] 1.3 Create PortSubtype enum
    - Location: `app/Enums/PortSubtype.php`
    - Ethernet subtypes: Gbe1 (1GbE), Gbe10 (10GbE), Gbe25 (25GbE), Gbe40 (40GbE), Gbe100 (100GbE)
    - Fiber subtypes: Lc, Sc, Mpo
    - Power subtypes: C13, C14, C19, C20
    - Include `label()` method for human-readable display
    - Include static `forType(PortType $type): array` method returning valid subtypes for a parent type
  - [x] 1.4 Create PortStatus enum
    - Location: `app/Enums/PortStatus.php`
    - Cases: Available, Connected, Reserved, Disabled
    - Include `label()` method for human-readable display
    - Follow pattern from `DeviceLifecycleStatus` enum
  - [x] 1.5 Create PortDirection enum
    - Location: `app/Enums/PortDirection.php`
    - Cases: Uplink, Downlink, Bidirectional, Input, Output
    - Include `label()` method for human-readable display
    - Include static `forType(PortType $type): array` method returning valid directions for a port type
    - Include static `defaultForType(PortType $type): self` method returning default direction per type
  - [x] 1.6 Create PortVisualFace enum
    - Location: `app/Enums/PortVisualFace.php`
    - Cases: Front, Rear
    - Include `label()` method for human-readable display
  - [x] 1.7 Create Port model
    - Location: `app/Models/Port.php`
    - Use `HasFactory` and `Loggable` traits
    - Define `belongsTo` relationship to Device
    - Fillable fields: device_id, label, type, subtype, status, direction, position_slot, position_row, position_column, visual_x, visual_y, visual_face
    - Define `casts()` method with enum casts for type, subtype, status, direction, visual_face
    - Cast position fields as integer, visual coordinates as decimal/float
  - [x] 1.8 Create migration for ports table
    - Run: `php artisan make:migration create_ports_table --no-interaction`
    - Fields:
      - id (primary key)
      - device_id (foreign key, cascade delete)
      - label (string, required)
      - type (string, PortType enum)
      - subtype (string, PortSubtype enum)
      - status (string, PortStatus enum, default 'available')
      - direction (string, PortDirection enum)
      - position_slot (nullable integer)
      - position_row (nullable integer)
      - position_column (nullable integer)
      - visual_x (nullable decimal 5,2)
      - visual_y (nullable decimal 5,2)
      - visual_face (nullable string, PortVisualFace enum)
      - timestamps
    - Add indexes: device_id, type, status, (device_id, label) unique
  - [x] 1.9 Add ports() hasMany relationship to Device model
    - Add method: `public function ports(): HasMany`
    - Import HasMany from Illuminate\Database\Eloquent\Relations
  - [x] 1.10 Create PortFactory
    - Run: `php artisan make:factory PortFactory --no-interaction`
    - Generate realistic port data with consistent type/subtype combinations
    - Include states for each port type (ethernet, fiber, power)
    - Include states for each status (available, connected, reserved, disabled)
  - [x] 1.11 Create PortSeeder
    - Run: `php artisan make:seeder PortSeeder --no-interaction`
    - Create sample ports for existing devices
    - Include variety of types, subtypes, and statuses
  - [x] 1.12 Ensure database layer tests pass
    - Run ONLY the 6 tests written in 1.1
    - Verify migrations run successfully with `php artisan migrate:fresh`
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- All 6 tests from task 1.1 pass
- All enums have proper cases with label() methods
- PortSubtype.forType() returns correct subtypes for each parent type
- PortDirection.forType() returns correct directions for network vs power ports
- Port model has proper casts and relationships
- Migration creates table with all required fields and indexes
- Device model has ports() relationship
- Factory generates valid port data with consistent type/subtype combinations
- Cascade delete works correctly

---

### API Layer

#### Task Group 2: Form Requests, Controller, and Routes
**Dependencies:** Task Group 1

- [x] 2.0 Complete API layer for Port Management
  - [x] 2.1 Write 8 focused tests for Port API endpoints
    - Test index returns ports for a specific device
    - Test store creates port with valid data
    - Test store validates subtype matches parent type
    - Test update modifies port successfully
    - Test destroy removes port
    - Test bulk store creates multiple ports from template
    - Test unauthorized users cannot create/update/delete ports
    - Test ports are scoped to their parent device
  - [x] 2.2 Create StorePortRequest form request
    - Run: `php artisan make:request StorePortRequest --no-interaction`
    - Authorization: Check user has permission to update parent device
    - Validation rules:
      - label: required, string, max:255
      - type: required, Rule::enum(PortType::class)
      - subtype: required, Rule::enum(PortSubtype::class)
      - status: nullable, Rule::enum(PortStatus::class)
      - direction: nullable, Rule::enum(PortDirection::class)
      - position_slot: nullable, integer, min:0
      - position_row: nullable, integer, min:0
      - position_column: nullable, integer, min:0
      - visual_x: nullable, numeric, min:0, max:100
      - visual_y: nullable, numeric, min:0, max:100
      - visual_face: nullable, Rule::enum(PortVisualFace::class)
    - Add custom validation: subtype must be valid for the given type
    - Include custom error messages
  - [x] 2.3 Create UpdatePortRequest form request
    - Run: `php artisan make:request UpdatePortRequest --no-interaction`
    - Follow same pattern as StorePortRequest
    - Authorization: Check user has permission to update parent device
  - [x] 2.4 Create BulkStorePortRequest form request
    - Run: `php artisan make:request BulkStorePortRequest --no-interaction`
    - Validation rules:
      - prefix: required, string, max:50
      - start_number: required, integer, min:1
      - end_number: required, integer, min:1, gte:start_number
      - type: required, Rule::enum(PortType::class)
      - subtype: required, Rule::enum(PortSubtype::class)
      - direction: nullable, Rule::enum(PortDirection::class)
    - Add custom validation: subtype must be valid for the given type
    - Add validation: end_number - start_number <= 100 (prevent creating too many ports)
  - [x] 2.5 Create PortResource API resource
    - Run: `php artisan make:resource PortResource --no-interaction`
    - Include all port fields
    - Add label fields for enums (type_label, subtype_label, status_label, direction_label)
    - Include device_id for reference
  - [x] 2.6 Create PortController
    - Run: `php artisan make:controller PortController --no-interaction`
    - Implement nested resource controller under devices
    - Methods: index, store, show, update, destroy, bulk
    - Use Gate authorization following DeviceController pattern
    - Return JSON responses (ports only accessible via Device Show, no Inertia pages)
    - Include helper methods for enum options (getTypeOptions, getSubtypeOptions, getStatusOptions, getDirectionOptions)
  - [x] 2.7 Register port routes nested under devices
    - Location: `routes/web.php` or `routes/api.php`
    - Routes:
      - GET /devices/{device}/ports - index
      - POST /devices/{device}/ports - store
      - GET /devices/{device}/ports/{port} - show
      - PUT/PATCH /devices/{device}/ports/{port} - update
      - DELETE /devices/{device}/ports/{port} - destroy
      - POST /devices/{device}/ports/bulk - bulk store
    - Apply appropriate middleware (auth, verified)
  - [x] 2.8 Ensure API layer tests pass
    - Run ONLY the 8 tests written in 2.1
    - Verify all CRUD operations work correctly
    - Verify bulk creation works with transaction
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- All 8 tests from task 2.1 pass
- Form requests validate all fields correctly
- Custom validation ensures subtype matches parent type
- PortController follows DeviceController patterns
- All CRUD endpoints work correctly
- Bulk endpoint creates multiple ports in single transaction
- Proper authorization is enforced
- JSON responses use PortResource format

---

### Frontend Layer

#### Task Group 3: Device Show Port Section UI
**Dependencies:** Task Group 2

- [x] 3.0 Complete frontend UI for Port Management
  - [x] 3.1 Write 4 focused tests for Port UI components
    - Test port section displays on Device Show page
    - Test port table renders with correct columns
    - Test empty state displays when no ports exist
    - Test add port button only shows when user has edit permission
  - [x] 3.2 Create PortData TypeScript interface
    - Location: `resources/js/types/ports.ts` or extend existing types file
    - Define PortData interface with all port fields
    - Include enum type literals for type, subtype, status, direction, visual_face
  - [x] 3.3 Create PortStatusBadge component
    - Location: `resources/js/components/ports/PortStatusBadge.vue`
    - Accept status prop
    - Map status values to Badge variants:
      - available: default (green)
      - connected: secondary (blue)
      - reserved: outline (yellow)
      - disabled: destructive (red)
    - Display status label
  - [x] 3.4 Create PortsSection component for Device Show page
    - Location: `resources/js/components/devices/PortsSection.vue`
    - Props: ports (array), deviceId, canEdit
    - Use Card, CardHeader, CardTitle, CardContent from existing UI components
    - Use Plug icon from lucide-vue-next
    - Display port count in title (e.g., "Ports (24)")
    - Include responsive table with columns: Label, Type, Subtype, Direction, Status
    - Use PortStatusBadge for status column
    - Include empty state when no ports exist
    - Show "Add Port" and "Bulk Add" buttons when canEdit is true
  - [x] 3.5 Create AddPortDialog component
    - Location: `resources/js/components/ports/AddPortDialog.vue`
    - Use existing Dialog component pattern from project
    - Form fields: label, type (select), subtype (select - filtered by type), direction (select - filtered by type), status (select)
    - Subtype options should update when type changes
    - Direction options should update when type changes
    - Use Form component from Inertia for submission
    - Submit to POST /devices/{device}/ports
  - [x] 3.6 Create BulkAddPortDialog component
    - Location: `resources/js/components/ports/BulkAddPortDialog.vue`
    - Form fields: prefix, start_number, end_number, type (select), subtype (select), direction (select)
    - Show preview of labels that will be generated (e.g., "eth1, eth2, ... eth24")
    - Subtype options should update when type changes
    - Submit to POST /devices/{device}/ports/bulk
  - [x] 3.7 Create EditPortDialog component
    - Location: `resources/js/components/ports/EditPortDialog.vue`
    - Similar to AddPortDialog but pre-populated with existing port data
    - Submit to PUT /devices/{device}/ports/{port}
  - [x] 3.8 Create DeletePortDialog component
    - Location: `resources/js/components/ports/DeletePortDialog.vue`
    - Confirm deletion with port label displayed
    - Submit to DELETE /devices/{device}/ports/{port}
  - [x] 3.9 Integrate PortsSection into Device Show page
    - Update `resources/js/Pages/Devices/Show.vue`
    - Add ports to Props interface
    - Add canEditPorts to Props (derived from canEdit)
    - Import and render PortsSection component below existing cards
    - Pass ports, deviceId, and canEdit props
  - [x] 3.10 Update DeviceController show method to include ports
    - Eager load ports relationship: `$device->load(['deviceType', 'rack', 'ports'])`
    - Add ports to Inertia render data using PortResource collection
    - Include port enum options for form dropdowns
  - [x] 3.11 Ensure frontend tests pass
    - Run ONLY the 4 tests written in 3.1
    - Verify port section renders correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- All 4 tests from task 3.1 pass
- PortsSection displays on Device Show page
- Port table shows all columns with proper formatting
- Status badges use correct color variants
- Empty state shows when no ports exist
- Add/Bulk Add buttons only visible with edit permission
- AddPortDialog creates single port successfully
- BulkAddPortDialog creates multiple ports with generated labels
- EditPortDialog updates port successfully
- DeletePortDialog removes port successfully
- Subtype and direction selects filter based on selected type

---

### Testing

#### Task Group 4: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-3

- [x] 4.0 Review existing tests and fill critical gaps
  - [x] 4.1 Review tests from Task Groups 1-3
    - Review the 6 tests written for database layer (Task 1.1)
    - Review the 8 tests written for API layer (Task 2.1)
    - Review the 4 tests written for UI layer (Task 3.1)
    - Total existing tests: 18 tests
  - [x] 4.2 Analyze test coverage gaps for Port Management feature
    - Identify critical user workflows lacking coverage
    - Focus ONLY on gaps related to Port Management requirements
    - Prioritize end-to-end workflows over unit test gaps
    - Consider edge cases for type/subtype validation
  - [x] 4.3 Write up to 8 additional strategic tests to fill gaps
    - Integration test: Full flow of adding ports then viewing on Device Show
    - Test: Bulk creation with maximum range (100 ports)
    - Test: Position fields (slot, row, column) stored correctly
    - Test: Visual position fields (visual_x, visual_y, visual_face) stored correctly
    - Test: Port label uniqueness within same device
    - Test: Cascade delete removes all device ports when device deleted
    - Test: Validation error messages display correctly in UI
    - Test: Empty state displays and add buttons work from empty state
  - [x] 4.4 Run feature-specific tests only
    - Run ONLY tests related to Port Management feature
    - Expected total: approximately 26 tests
    - Verify all critical workflows pass
    - Do NOT run the entire application test suite

**Acceptance Criteria:**
- All 26 feature-specific tests pass
- Critical user workflows for Port Management are covered
- No more than 8 additional tests added
- Type/subtype validation edge cases are tested
- Bulk creation edge cases are tested
- Integration between frontend and backend verified

---

## Execution Order

Recommended implementation sequence:

1. **Database Layer (Task Group 1)** - Enums, Models, and Migrations
   - Create all enums first (PortType, PortSubtype, PortStatus, PortDirection, PortVisualFace)
   - Create Port model with relationships and casts
   - Create migration with proper indexes
   - Add ports() relationship to Device model
   - Create factory and seeder
   - Run database layer tests

2. **API Layer (Task Group 2)** - Form Requests, Controller, and Routes
   - Create form request classes for validation
   - Create PortResource for API responses
   - Create PortController with all CRUD methods
   - Register nested routes under devices
   - Run API layer tests

3. **Frontend Layer (Task Group 3)** - Device Show Port Section UI
   - Create TypeScript interfaces
   - Create PortStatusBadge component
   - Create PortsSection component
   - Create dialog components (Add, BulkAdd, Edit, Delete)
   - Integrate into Device Show page
   - Update controller to include ports data
   - Run frontend tests

4. **Test Review (Task Group 4)** - Gap Analysis and Final Testing
   - Review all existing tests
   - Identify and fill critical gaps
   - Run complete feature test suite
   - Verify all acceptance criteria met

---

## Technical Notes

### Enum Type/Subtype Validation
The subtype field must be validated against the parent type. Implement this as:
- A static method on PortSubtype: `forType(PortType $type): array`
- Custom validation rule in form requests using closure or custom Rule class

### Bulk Port Creation
The bulk endpoint should:
1. Accept template parameters (prefix, start_number, end_number, type, subtype, direction)
2. Generate labels: `{prefix}{number}` (e.g., eth1, eth2, eth3...)
3. Create all ports in a database transaction
4. Return the created ports collection

### Frontend Type Filtering
When type changes in Add/Edit dialogs:
1. Clear the current subtype selection
2. Fetch valid subtypes for new type
3. Update subtype select options
4. Similarly update direction options based on type

### Visual Position Fields
The visual_x and visual_y fields store percentage values (0-100) for future visual port mapping. These are optional and not exposed in the initial UI but should be stored and returned in API responses.
