# Task Breakdown: Rack Page Enhancement

## Overview
Total Tasks: 32 (across 4 task groups)

This feature enhances the existing Rack Show page to provide comprehensive rack information, device listings, utilization metrics, power metrics, custom specifications, and a mini elevation preview component.

## Task List

### Database Layer

#### Task Group 1: Rack Model and Migration Updates
**Dependencies:** None

- [x] 1.0 Complete database layer for rack enhancements
  - [x] 1.1 Write 4-6 focused tests for Rack model functionality
    - Test specs JSON cast works correctly (storing/retrieving key-value pairs)
    - Test installation_date date cast works correctly
    - Test new fillable fields can be mass assigned
    - Test devices relationship returns correct devices with eager loading
  - [x] 1.2 Create migration to add new columns to racks table
    - Add `manufacturer` (string, nullable) - rack manufacturer name
    - Add `model` (string, nullable) - rack model number
    - Add `depth` (string, nullable) - rack depth dimensions
    - Add `installation_date` (date, nullable) - when rack was installed
    - Add `location_notes` (text, nullable) - additional location context
    - Add `specs` (json, nullable) - custom key-value specifications
    - File: `database/migrations/2026_01_09_165147_add_enhancement_fields_to_racks_table.php`
  - [x] 1.3 Update Rack model with new fillable fields and casts
    - Add fields to `$fillable`: manufacturer, model, depth, installation_date, location_notes, specs
    - Add casts in `casts()` method: `'specs' => 'array'`, `'installation_date' => 'date'`
    - File: `app/Models/Rack.php`
  - [x] 1.4 Update RackFactory with new fields for testing
    - Add faker definitions for manufacturer, model, depth, installation_date, location_notes, specs
    - File: `database/factories/RackFactory.php`
  - [x] 1.5 Ensure database layer tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify migration runs successfully with `php artisan migrate`
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- Migration adds all new columns correctly
- Rack model can store and retrieve specs as JSON array
- installation_date is properly cast to Carbon date
- Factory produces valid test data for new fields

**Files to Modify:**
- `app/Models/Rack.php`
- `database/factories/RackFactory.php`

**Files to Create:**
- `database/migrations/2026_01_09_165147_add_enhancement_fields_to_racks_table.php`
- `tests/Feature/RackEnhancementTest.php`

---

### Backend Layer

#### Task Group 2: Controller and Resource Updates
**Dependencies:** Task Group 1

- [x] 2.0 Complete backend layer for rack page data
  - [x] 2.1 Write 4-6 focused tests for RackController show() enhancements
    - Test show() returns new rack fields (manufacturer, model, depth, installation_date, location_notes, specs)
    - Test show() returns devices list with device_type eager loaded
    - Test show() returns utilization stats (totalU, usedU, availableU, utilizationPercent)
    - Test show() returns power metrics (totalPowerDraw, pduCapacity, powerUtilizationPercent)
    - Test devices are sorted by start_u descending
  - [x] 2.2 Update RackController show() method
    - Add new rack fields to response: manufacturer, model, depth, installation_date, location_notes, specs
    - Eager load devices relationship with deviceType
    - Format devices for device list table (id, name, type, start_u, lifecycle_status, lifecycle_status_label)
    - Sort devices by start_u descending (highest U at top)
    - File: `app/Http/Controllers/RackController.php`
  - [x] 2.3 Add utilization calculation to show() method
    - Calculate totalU from rack u_height enum value
    - Calculate usedU by summing u_height of all placed devices
    - Calculate availableU as totalU - usedU
    - Calculate utilizationPercent as (usedU / totalU) * 100
    - Return as `utilization` object in response
    - File: `app/Http/Controllers/RackController.php`
  - [x] 2.4 Add power metrics calculation to show() method
    - Aggregate power_draw_watts from all devices in rack
    - Aggregate total_capacity_kw from all assigned PDUs (convert to watts: kW * 1000)
    - Calculate power utilization percentage
    - Return as `powerMetrics` object with totalPowerDraw, pduCapacity, powerUtilizationPercent
    - File: `app/Http/Controllers/RackController.php`
  - [x] 2.5 Update Store and Update request classes for new fields
    - Add validation rules for manufacturer (nullable, string, max:255)
    - Add validation rules for model (nullable, string, max:255)
    - Add validation rules for depth (nullable, string, max:100)
    - Add validation rules for installation_date (nullable, date)
    - Add validation rules for location_notes (nullable, string, max:1000)
    - Add validation rules for specs (nullable, array)
    - Files: `app/Http/Requests/StoreRackRequest.php`, `app/Http/Requests/UpdateRackRequest.php`
  - [x] 2.6 Update store() and update() methods to handle new fields
    - Include new fields in create/update operations
    - File: `app/Http/Controllers/RackController.php`
  - [x] 2.7 Ensure backend layer tests pass
    - Run ONLY the 4-6 tests written in 2.1
    - Verify controller returns expected data structure
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 2.1 pass
- show() returns all new rack fields
- Devices list includes type name and lifecycle status
- Utilization stats are calculated correctly
- Power metrics aggregate device and PDU data correctly
- Validation accepts new fields in create/update

**Files to Modify:**
- `app/Http/Controllers/RackController.php`
- `app/Http/Requests/StoreRackRequest.php`
- `app/Http/Requests/UpdateRackRequest.php`

**Files to Create:**
- `tests/Feature/Http/Controllers/RackControllerShowTest.php`

---

### Frontend Layer

#### Task Group 3: Enhanced Rack Show Page and Components
**Dependencies:** Task Group 2

- [x] 3.0 Complete frontend components for enhanced rack page
  - [x] 3.1 Write 4-6 focused tests for frontend components (Feature tests)
    - Test Rack Show page displays new rack detail fields
    - Test specifications table renders key-value pairs correctly
    - Test device list table displays devices sorted by U position
    - Test utilization metrics are received correctly
    - Test power metrics are received correctly
    - Test empty rack is handled correctly
    - File: `tests/Feature/RackShowPageEnhancedTest.php`
  - [x] 3.2 Update TypeScript types for enhanced rack data
    - Extend RackData interface with: manufacturer, model, depth, installation_date, location_notes, specs
    - Add UtilizationStats interface (already exists, verify compatibility)
    - Add PowerMetrics interface: totalPowerDraw, pduCapacity, powerUtilizationPercent
    - Add RackDevice interface for device list: id, name, type, start_u, u_height, lifecycle_status, lifecycle_status_label
    - File: `resources/js/types/rooms.ts`
  - [x] 3.3 Update Rack Show page props and imports
    - Add new props: utilization, powerMetrics, devices
    - Import new components (to be created in subsequent tasks)
    - Update Props interface to match new controller response
    - File: `resources/js/pages/Racks/Show.vue`
  - [x] 3.4 Enhance Rack Details card with new fields
    - Add manufacturer field to grid
    - Add model field to grid
    - Add depth field to grid
    - Add installation_date field with date formatting
    - Add location_notes display (conditionally shown when present)
    - Follow existing grid layout pattern (sm:grid-cols-2 lg:grid-cols-4)
    - File: `resources/js/pages/Racks/Show.vue`
  - [x] 3.5 Add Specifications card section
    - Create new Card section below Rack Details
    - Display key-value table when specs has entries
    - Use FileText icon from lucide-vue-next (matching Device Show page)
    - Table columns: Key, Value
    - Show "No specifications recorded for this rack." when empty
    - Match exact table styling from Device Show page specifications section
    - File: `resources/js/pages/Racks/Show.vue`
  - [x] 3.6 Add Installed Devices card section
    - Create new Card section for device list
    - Use Server icon in CardTitle
    - Table columns: Name (clickable link), Type, U Position, Status
    - Device name links to device detail page using DeviceController.show.url()
    - Display U position as "U{start_u}" format
    - Show status using Badge component with lifecycle status variant (getLifecycleStatusVariant helper)
    - Show "No devices installed in this rack." when empty
    - File: `resources/js/pages/Racks/Show.vue`
  - [x] 3.7 Add Utilization metrics section
    - Display within or near Rack Details card
    - Show "X of Y U-spaces occupied" text
    - Display utilization percentage
    - Color code: green (<70%), yellow (70-90%), red (>90%)
    - Use progress bar or compact stat display
    - File: `resources/js/pages/Racks/Show.vue`
  - [x] 3.8 Add Power metrics section
    - Display near utilization metrics
    - Show "X W of Y W capacity" format
    - Display power utilization percentage
    - Show warning color (amber/orange) when >80%
    - Use Zap icon from lucide-vue-next
    - Handle case when no PDUs assigned (show "No PDUs assigned")
    - File: `resources/js/pages/Racks/Show.vue`
  - [x] 3.9 Create MiniElevationPreview.vue component
    - Create new component in elevation folder
    - Props: rack (RackData with u_height), devices (PlaceholderDevice[]), datacenter/room/row IDs for navigation
    - Size: ~300px width, proportional height based on rack U-height
    - Static display only (no hover states, no drag-and-drop)
    - Render U numbers along left side (like full elevation)
    - Render devices positioned at their start_u with correct height
    - Scale slot height to fit within reasonable viewport height (~400-500px max)
    - File: `resources/js/components/elevation/MiniElevationPreview.vue`
  - [x] 3.10 Add device type colors to MiniElevationPreview
    - Use device type to determine background color (match DeviceBlock.vue badge variants)
    - server: default (blue)
    - storage: secondary (gray)
    - switch: success (green)
    - ups/pdu: warning (amber)
    - other: outline (gray outline)
    - File: `resources/js/components/elevation/MiniElevationPreview.vue`
  - [x] 3.11 Make MiniElevationPreview clickable
    - Entire container is clickable
    - On click, navigate to full elevation view using RackController.elevation.url()
    - Add cursor-pointer styling
    - Add subtle hover effect (ring or shadow)
    - File: `resources/js/components/elevation/MiniElevationPreview.vue`
  - [x] 3.12 Integrate MiniElevationPreview into Rack Show page
    - Import and add MiniElevationPreview component
    - Position in new Card section or alongside other content
    - Pass required props (rack, devices, navigation IDs)
    - File: `resources/js/pages/Racks/Show.vue`
  - [x] 3.13 Update Rack Create/Edit forms for new fields (optional enhancement)
    - Add manufacturer input field
    - Add model input field
    - Add depth input field
    - Add installation_date date picker
    - Add location_notes textarea
    - Add specs key-value editor (if time permits, otherwise simple JSON textarea)
    - Files: `resources/js/pages/Racks/Create.vue`, `resources/js/pages/Racks/Edit.vue`
  - [x] 3.14 Ensure frontend component tests pass
    - Run ONLY the 4-6 tests written in 3.1
    - Verify components render correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 3.1 pass
- Rack Show page displays all new fields
- Specifications table matches Device Show page styling
- Device list shows all devices with correct sorting and links
- Utilization and power metrics display with appropriate color coding
- Mini elevation preview renders at ~300px width with correct device positioning
- Clicking mini elevation navigates to full elevation view

**Files to Modify:**
- `resources/js/types/rooms.ts`
- `resources/js/pages/Racks/Show.vue`
- `resources/js/components/racks/RackForm.vue`

**Files to Create:**
- `resources/js/components/elevation/MiniElevationPreview.vue`
- `tests/Feature/RackShowPageEnhancedTest.php`

---

### Testing

#### Task Group 4: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-3

- [x] 4.0 Review existing tests and fill critical gaps only
  - [x] 4.1 Review tests from Task Groups 1-3
    - Review tests in tests/Feature/RackEnhancementTest.php (database layer - 6 tests)
    - Review tests in tests/Feature/Http/Controllers/RackControllerShowTest.php (backend layer - 6 tests)
    - Review tests in tests/Feature/RackShowPageEnhancedTest.php (frontend layer - 6 tests)
  - [x] 4.2 Analyze test coverage gaps for this feature only
    - Identify critical user workflows that lack test coverage
    - Focus ONLY on gaps related to rack page enhancement
  - [x] 4.3 Write up to 10 additional strategic tests maximum
    - Focus on integration points and edge cases:
      - Utilization calculation edge cases (empty rack, full rack)
      - Power metrics edge cases (no PDUs, no devices with power data)
      - Store/update with new fields
  - [x] 4.4 Run feature-specific tests only
    - Run tests related to this spec's feature
    - Verify all pass
  - [x] 4.5 Run code quality checks
    - Run `vendor/bin/pint --dirty` to fix PHP code style
    - Run `npm run build` to verify frontend compiles

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 22-28 tests total)
- Critical user workflows for rack page enhancement are covered
- No more than 10 additional tests added when filling in testing gaps
- Testing focused exclusively on this spec's feature requirements
- PHP code passes Pint style checks
- Frontend builds without errors

**Files to Create/Modify:**
- Test files created in Task Groups 1-3
- Additional integration test files as needed

---

## Execution Order

Recommended implementation sequence:

1. **Database Layer (Task Group 1)** - Must complete first
   - Creates migration and updates Rack model
   - No dependencies on other groups
   - Estimated effort: Small

2. **Backend Layer (Task Group 2)** - Depends on Task Group 1
   - Updates controller, requests, and API response
   - Requires database changes to be in place
   - Estimated effort: Medium

3. **Frontend Layer (Task Group 3)** - Depends on Task Group 2
   - Updates Rack Show page and creates MiniElevationPreview component
   - Requires backend API to return enhanced data
   - Estimated effort: Large (most complex group)

4. **Test Review & Gap Analysis (Task Group 4)** - Depends on Task Groups 1-3
   - Reviews all tests and fills critical gaps
   - Final verification of complete feature
   - Estimated effort: Small

---

## Reference Files

### Existing Code to Leverage

**Device Show Page Pattern:**
- `resources/js/pages/Devices/Show.vue` - Card layout, specifications table, grid layout for details

**Current Rack Show Page:**
- `resources/js/pages/Racks/Show.vue` - Base page to extend, PDU table pattern

**Elevation Components:**
- `resources/js/components/elevation/DeviceBlock.vue` - Device type colors and badge variants
- `resources/js/components/elevation/RackElevationView.vue` - U-slot rendering reference

**Backend Patterns:**
- `app/Http/Controllers/RackController.php` - getDevicesForElevation() method for device formatting
- `app/Http/Controllers/DeviceController.php` - show() method pattern for comprehensive data

**TypeScript Types:**
- `resources/js/types/rooms.ts` - RackData, PlaceholderDevice, UtilizationStats interfaces
