# Task Breakdown: Asset/Device Management

## Overview
Total Tasks: 46

This feature enables full lifecycle management of datacenter devices/assets with flexible hardware specifications, physical dimension tracking, warranty information, and auto-generated asset tags. Devices can be placed within racks via the existing elevation system.

## Task List

### Database Layer

#### Task Group 1: Device Types Data Model
**Dependencies:** None

- [x] 1.0 Complete device types database layer
  - [x] 1.1 Write 4 focused tests for DeviceType model functionality
    - Test DeviceType creation with required fields (name, default_u_size)
    - Test soft delete preserves historical references
    - Test DeviceType can be restored after soft delete
    - Test DeviceType name uniqueness validation
  - [x] 1.2 Create DeviceType model with soft deletes
    - Fields: name (required), description (nullable), default_u_size (integer, default 1)
    - Use `HasFactory` and `SoftDeletes` traits
    - Follow existing model patterns from `app/Models/Rack.php`
  - [x] 1.3 Create migration for `device_types` table
    - Add unique index on `name`
    - Add `deleted_at` for soft deletes
    - Include timestamps
  - [x] 1.4 Create DeviceType factory and seeder
    - Factory for testing with realistic device type names
    - Seeder with examples: Server, Switch, Router, Storage, PDU, UPS, Patch Panel, KVM, Console Server, Blade Chassis
  - [x] 1.5 Ensure device types tests pass
    - Run ONLY the 4 tests written in 1.1
    - Verify migrations run successfully

**Acceptance Criteria:**
- The 4 tests written in 1.1 pass
- DeviceType model supports soft deletes
- Seeder populates common device types

---

#### Task Group 2: Device/Asset Data Model
**Dependencies:** Task Group 1

- [x] 2.0 Complete device database layer
  - [x] 2.1 Write 6 focused tests for Device model functionality
    - Test Device creation with required fields
    - Test auto-generation of asset_tag on create
    - Test asset_tag format matches `ASSET-{YYYYMMDD}-{sequential}` pattern
    - Test Device relationship to DeviceType
    - Test Device relationship to Rack (nullable)
    - Test lifecycle status enum casting
  - [x] 2.2 Create DeviceLifecycleStatus enum
    - States: ordered, received, in_stock, deployed, maintenance, decommissioned, disposed
    - Include `label()` method returning human-readable labels
    - Follow existing `app/Enums/RackStatus.php` pattern
  - [x] 2.3 Create DeviceDepth enum
    - Values: standard, deep, shallow
    - Include `label()` method
    - Follow existing enum patterns
  - [x] 2.4 Create DeviceWidthType enum
    - Values: full, half_left, half_right
    - Include `label()` method
    - Map to existing TypeScript `DeviceWidth` type
  - [x] 2.5 Create DeviceRackFace enum
    - Values: front, rear
    - Include `label()` method
    - Map to existing TypeScript `RackFace` type
  - [x] 2.6 Create Device model
    - Required fields: name, device_type_id, asset_tag (auto-generated), lifecycle_status
    - Optional fields: serial_number, manufacturer, model, warranty_start_date, warranty_end_date, purchase_date, notes
    - Physical dimension fields: u_height (1-48), depth, width_type, rack_face, start_u (nullable)
    - JSON column: specs (flexible key-value pairs)
    - Foreign keys: device_type_id, rack_id (nullable)
    - Use `HasFactory` and `Loggable` traits
    - Implement asset tag auto-generation in model boot method
  - [x] 2.7 Create migration for `devices` table
    - All fields as specified in 2.6
    - Unique constraint on `asset_tag`
    - Foreign key to `device_types` with restrict on delete
    - Foreign key to `racks` with set null on delete
    - Index on `lifecycle_status` for filtering
    - Index on `rack_id` for rack device queries
  - [x] 2.8 Create Device factory
    - Realistic device names and specifications
    - States for each lifecycle status
    - States for placed vs unplaced devices
  - [x] 2.9 Set up model relationships
    - Device belongsTo DeviceType
    - Device belongsTo Rack (nullable)
    - DeviceType hasMany Devices
    - Rack hasMany Devices
  - [x] 2.10 Ensure device model tests pass
    - Run ONLY the 6 tests written in 2.1
    - Verify asset tag generation works correctly

**Acceptance Criteria:**
- The 6 tests written in 2.1 pass
- Asset tags are auto-generated with correct format
- Device can exist without rack placement (unplaced inventory)
- All enums have proper labels

---

### API Layer

#### Task Group 3: Device Types API
**Dependencies:** Task Group 1

- [x] 3.0 Complete device types API layer
  - [x] 3.1 Write 4 focused tests for DeviceType API endpoints
    - Test index returns all device types (excluding soft-deleted)
    - Test store creates device type with valid data
    - Test update modifies device type
    - Test destroy soft-deletes device type
  - [x] 3.2 Create DeviceTypeController
    - Actions: index, store, update, destroy
    - Follow patterns from `RackController`
    - Return JSON responses for API consumption
  - [x] 3.3 Create StoreDeviceTypeRequest
    - Validation: name (required, unique), description (nullable), default_u_size (integer, 1-48)
    - Follow `StoreRackRequest` pattern
    - Include role-based authorization
  - [x] 3.4 Create UpdateDeviceTypeRequest
    - Same validation as store, with unique rule ignoring current record
    - Include role-based authorization
  - [x] 3.5 Create DeviceTypePolicy
    - Follow `RackPolicy` pattern
    - Only Administrators and IT Managers can manage device types
    - All authenticated users can view
  - [x] 3.6 Register routes for device types
    - Route group: `/device-types`
    - Standard resource routes (index, store, update, destroy)
  - [x] 3.7 Ensure device types API tests pass
    - Run ONLY the 4 tests written in 3.1

**Acceptance Criteria:**
- The 4 tests written in 3.1 pass
- CRUD operations work for device types
- Proper authorization enforced

---

#### Task Group 4: Device/Asset API
**Dependencies:** Task Groups 2, 3

- [x] 4.0 Complete device API layer
  - [x] 4.1 Write 6 focused tests for Device API endpoints
    - Test index returns devices with filtering by rack_id
    - Test store creates device with auto-generated asset_tag
    - Test show returns device with all relationships
    - Test update modifies device fields (but not asset_tag)
    - Test update can place/unplace device from rack
    - Test destroy deletes device
  - [x] 4.2 Create DeviceController
    - Actions: index, create, store, show, edit, update, destroy
    - Follow patterns from `RackController`
    - Support both rack-scoped and global device views
    - Return device data with device type and rack relationships
  - [x] 4.3 Create StoreDeviceRequest
    - Required: name, device_type_id, lifecycle_status
    - Optional: serial_number, manufacturer, model, warranty dates, notes, specs
    - Physical: u_height (1-48), depth, width_type, rack_face
    - Placement: rack_id, start_u (nullable)
    - Use `Rule::enum()` for enum validation
    - Follow `StoreRackRequest` authorization pattern
  - [x] 4.4 Create UpdateDeviceRequest
    - Same fields as store (asset_tag excluded - immutable)
    - Follow existing update request patterns
  - [x] 4.5 Create DevicePolicy
    - Follow `RackPolicy` pattern
    - Admins/IT Managers: full access
    - Others: read access through rack datacenter hierarchy
    - Handle unplaced devices (viewable by admins only)
  - [x] 4.6 Create DeviceResource (API Resource)
    - Transform device data for consistent JSON responses
    - Include device type, rack references
    - Calculate warranty status (active/expired/none)
  - [x] 4.7 Register routes for devices
    - Global route: `/devices` for inventory management
    - Nested route: `/datacenters/{}/rooms/{}/rows/{}/racks/{}/devices` for rack-scoped views
  - [x] 4.8 Ensure device API tests pass
    - Run ONLY the 6 tests written in 4.1

**Acceptance Criteria:**
- The 6 tests written in 4.1 pass
- Asset tags cannot be modified after creation
- Devices can be placed/unplaced from racks
- Proper authorization based on rack hierarchy

---

### Rack Integration Layer

#### Task Group 5: Elevation System Integration
**Dependencies:** Task Group 4

- [x] 5.0 Complete rack elevation integration
  - [x] 5.1 Write 4 focused tests for elevation integration
    - Test elevation endpoint returns real devices instead of placeholders
    - Test placing a device updates rack_id and start_u
    - Test removing device from rack clears placement fields
    - Test device collision detection (overlapping U positions)
  - [x] 5.2 Update RackController::elevation method
    - Replace `getPlaceholderDevices()` with real Device queries
    - Query devices by rack_id for placed devices
    - Query unplaced devices (rack_id is null) for sidebar
    - Return data matching existing TypeScript interfaces
  - [x] 5.3 Remove getPlaceholderDevices method
    - Delete placeholder method from RackController
    - Ensure all references use real device data
  - [x] 5.4 Create device placement API endpoint
    - Route: PATCH `/devices/{device}/place`
    - Accept rack_id, start_u, face, width_type
    - Validate no collision with existing devices
    - Follow existing controller patterns
  - [x] 5.5 Create device removal API endpoint
    - Route: PATCH `/devices/{device}/unplace`
    - Clear rack_id, start_u, rack_face fields
    - Return device to unplaced inventory
  - [x] 5.6 Ensure elevation integration tests pass
    - Run ONLY the 4 tests written in 5.1

**Acceptance Criteria:**
- The 4 tests written in 5.1 pass
- Elevation view shows real device data
- Device placement updates persist correctly
- Collision detection prevents overlapping devices

---

### Frontend Components

#### Task Group 6: Device Types UI
**Dependencies:** Task Group 3

- [x] 6.0 Complete device types UI components
  - [x] 6.1 Write 3 focused tests for device types UI
    - Test device types index page renders list
    - Test create form submits with valid data
    - Test delete removes device type from list
  - [x] 6.2 Create DeviceTypes/Index.vue page
    - Table listing device types with name, description, default U size
    - Action buttons for edit, delete
    - Follow existing table patterns from Racks/Index.vue
  - [x] 6.3 Create DeviceTypes/Create.vue page
    - Form with name, description, default_u_size fields
    - Follow existing form patterns
  - [x] 6.4 Create DeviceTypes/Edit.vue page
    - Pre-populated form for editing device types
    - Follow existing edit page patterns
  - [x] 6.5 Add device types link to navigation
    - Add to settings or admin section as appropriate
  - [x] 6.6 Ensure device types UI tests pass
    - Run ONLY the 3 tests written in 6.1

**Acceptance Criteria:**
- The 3 tests written in 6.1 pass
- Device types can be managed through UI
- Follows existing UI patterns

---

#### Task Group 7: Device Management UI
**Dependencies:** Task Groups 4, 6

- [x] 7.0 Complete device management UI components
  - [x] 7.1 Write 4 focused tests for device management UI
    - Test devices index page renders device list
    - Test create form with device type selection
    - Test show page displays all device details
    - Test edit form updates device
  - [x] 7.2 Create TypeScript interfaces for Device
    - Extend/update `resources/js/types/rooms.ts`
    - Add DeviceData interface with all fields
    - Add DeviceTypeOption interface for dropdowns
    - Add lifecycle status option interface
  - [x] 7.3 Create Devices/Index.vue page
    - Table with device name, type, asset tag, lifecycle status, rack placement
    - Status badges using existing Badge component
    - Filtering by lifecycle status
    - Search by name, asset tag, serial number
    - Action buttons for view, edit, delete
  - [x] 7.4 Create Devices/Create.vue page
    - Form with all device fields
    - Device type dropdown selection
    - Lifecycle status dropdown
    - Physical dimension fields (u_height, depth, width_type)
    - Optional rack placement section
    - Flexible specs key-value editor component
    - Warranty date pickers
  - [x] 7.5 Create Devices/Show.vue page
    - Detail cards showing all device information
    - Device specifications section (key-value display)
    - Warranty status indicator (active/expired/none)
    - Rack placement information with link to elevation view
    - Activity log section if using Loggable trait
  - [x] 7.6 Create Devices/Edit.vue page
    - Pre-populated form matching create page
    - Asset tag displayed but not editable (immutable)
    - All other fields editable
  - [x] 7.7 Create KeyValueEditor.vue component
    - Reusable component for editing JSON specs
    - Add/remove key-value pairs
    - String values input
  - [x] 7.8 Add devices link to navigation
    - Add to main navigation or as appropriate
    - Consider adding to rack show page for quick access
  - [x] 7.9 Ensure device management UI tests pass
    - Run ONLY the 4 tests written in 7.1

**Acceptance Criteria:**
- The 4 tests written in 7.1 pass
- All device CRUD operations work through UI
- Specs can be edited as key-value pairs
- Warranty status displays correctly

---

#### Task Group 8: Elevation View Updates
**Dependencies:** Task Groups 5, 7

- [x] 8.0 Complete elevation view integration
  - [x] 8.1 Write 3 focused tests for elevation view
    - Test elevation page renders real devices
    - Test device drag-and-drop updates placement
    - Test unplaced devices appear in sidebar
  - [x] 8.2 Update Elevation.vue to use real Device data
    - Update props to accept DeviceData instead of PlaceholderDevice
    - Map device data to existing visual components
    - Maintain existing drag-and-drop functionality
  - [x] 8.3 Update useRackElevation composable
    - Update types to use DeviceData interface
    - Add API calls for device placement/removal
    - Handle placement validation responses
  - [x] 8.4 Add device details link from elevation
    - Click on placed device opens device show page
    - Or show quick info panel with link to full details
  - [x] 8.5 Ensure elevation view tests pass
    - Run ONLY the 3 tests written in 8.1

**Acceptance Criteria:**
- The 3 tests written in 8.1 pass
- Elevation view works with real device data
- Placement changes persist to database
- Navigation to device details works

---

### Testing

#### Task Group 9: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-8

- [x] 9.0 Review existing tests and fill critical gaps only
  - [x] 9.1 Review tests from Task Groups 1-8
    - Review the 4 tests from Task 1.1 (DeviceType model)
    - Review the 6 tests from Task 2.1 (Device model)
    - Review the 4 tests from Task 3.1 (DeviceType API)
    - Review the 6 tests from Task 4.1 (Device API)
    - Review the 4 tests from Task 5.1 (Elevation integration)
    - Review the 3 tests from Task 6.1 (DeviceType UI)
    - Review the 4 tests from Task 7.1 (Device UI)
    - Review the 3 tests from Task 8.1 (Elevation view)
    - Total existing tests: 34 tests
  - [x] 9.2 Analyze test coverage gaps for this feature only
    - Identify critical user workflows lacking test coverage
    - Focus ONLY on gaps related to device management requirements
    - Prioritize end-to-end workflows over unit test gaps
    - Check warranty status calculation logic
    - Check asset tag uniqueness across concurrent requests
  - [x] 9.3 Write up to 8 additional strategic tests maximum
    - Add tests for critical integration points if needed
    - Focus on end-to-end device lifecycle workflow
    - Test device placement conflicts in elevation
    - Test warranty status display logic
    - Do NOT write comprehensive coverage for all scenarios
  - [x] 9.4 Run feature-specific tests only
    - Run ONLY tests related to device management feature
    - Expected total: approximately 42 tests maximum
    - Verify critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (42 tests total)
- Critical user workflows for device management are covered
- No more than 8 additional tests added when filling gaps
- Testing focused exclusively on device management requirements

---

## Execution Order

Recommended implementation sequence:

1. **Task Group 1: Device Types Data Model** - Foundation for device categorization
2. **Task Group 2: Device/Asset Data Model** - Core device model with all fields
3. **Task Group 3: Device Types API** - API for managing device types
4. **Task Group 4: Device/Asset API** - Main device CRUD API
5. **Task Group 5: Elevation System Integration** - Connect devices to rack elevation
6. **Task Group 6: Device Types UI** - Admin interface for device types
7. **Task Group 7: Device Management UI** - Main device management interface
8. **Task Group 8: Elevation View Updates** - Real device data in elevation view
9. **Task Group 9: Test Review and Gap Analysis** - Final test coverage review

## Key Files Reference

### Existing Files to Follow as Patterns
- `/Users/helderdene/rackaudit/app/Models/Rack.php` - Model pattern
- `/Users/helderdene/rackaudit/app/Http/Controllers/RackController.php` - Controller pattern
- `/Users/helderdene/rackaudit/app/Enums/RackStatus.php` - Enum pattern
- `/Users/helderdene/rackaudit/app/Policies/RackPolicy.php` - Policy pattern
- `/Users/helderdene/rackaudit/app/Http/Requests/StoreRackRequest.php` - Form request pattern
- `/Users/helderdene/rackaudit/resources/js/types/rooms.ts` - TypeScript interfaces
- `/Users/helderdene/rackaudit/resources/js/Pages/Racks/` - Vue page patterns

### New Files to Create
- `app/Models/DeviceType.php`
- `app/Models/Device.php`
- `app/Enums/DeviceLifecycleStatus.php`
- `app/Enums/DeviceDepth.php`
- `app/Enums/DeviceWidthType.php`
- `app/Enums/DeviceRackFace.php`
- `app/Http/Controllers/DeviceTypeController.php`
- `app/Http/Controllers/DeviceController.php`
- `app/Policies/DeviceTypePolicy.php`
- `app/Policies/DevicePolicy.php`
- `app/Http/Requests/StoreDeviceTypeRequest.php`
- `app/Http/Requests/UpdateDeviceTypeRequest.php`
- `app/Http/Requests/StoreDeviceRequest.php`
- `app/Http/Requests/UpdateDeviceRequest.php`
- `app/Http/Resources/DeviceResource.php`
- `database/migrations/*_create_device_types_table.php`
- `database/migrations/*_create_devices_table.php`
- `database/factories/DeviceTypeFactory.php`
- `database/factories/DeviceFactory.php`
- `database/seeders/DeviceTypeSeeder.php`
- `resources/js/Pages/DeviceTypes/Index.vue`
- `resources/js/Pages/DeviceTypes/Create.vue`
- `resources/js/Pages/DeviceTypes/Edit.vue`
- `resources/js/Pages/Devices/Index.vue`
- `resources/js/Pages/Devices/Create.vue`
- `resources/js/Pages/Devices/Show.vue`
- `resources/js/Pages/Devices/Edit.vue`
- `resources/js/Components/KeyValueEditor.vue`
