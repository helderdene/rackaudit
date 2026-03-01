# Task Breakdown: Rack Management

## Overview
Total Tasks: 42

This feature adds full CRUD management for racks within the datacenter hierarchy (Datacenter > Room > Row > Rack), including a visual rack elevation diagram showing U-positions for future device placement. Racks support many-to-many PDU relationships for redundant power configurations.

## Task List

### Database Layer

#### Task Group 1: Enums and Data Models
**Dependencies:** None

- [x] 1.0 Complete database layer for Rack model
  - [x] 1.1 Write 4-6 focused tests for Rack model functionality
    - Test Rack model creation with valid data
    - Test RackStatus enum casting and label() method
    - Test RackUHeight enum casting and label() method
    - Test Rack belongs to Row relationship
    - Test Rack many-to-many PDU relationship
    - Test Loggable concern integration
  - [x] 1.2 Create `RackStatus` enum
    - File: `app/Enums/RackStatus.php`
    - Cases: `Active`, `Inactive`, `Maintenance`
    - Include `label()` method following `RowStatus` pattern
    - Pattern reference: `app/Enums/RowStatus.php`
  - [x] 1.3 Create `RackUHeight` enum
    - File: `app/Enums/RackUHeight.php`
    - Cases: `U42 = 42`, `U45 = 45`, `U48 = 48`
    - Include `label()` method returning formatted string (e.g., "42U")
  - [x] 1.4 Create migration for `racks` table
    - File: `database/migrations/2025_12_24_132859_create_racks_table.php`
    - Columns: id, name (string), position (integer), u_height (integer), serial_number (nullable string), status (string), row_id (foreign key), timestamps
    - Add index on row_id for efficient queries
    - Foreign key constraint: row_id references rows(id) with onDelete cascade
  - [x] 1.5 Create migration for `pdu_rack` pivot table
    - File: `database/migrations/2025_12_24_132905_create_pdu_rack_table.php`
    - Columns: id, pdu_id (foreign key), rack_id (foreign key), timestamps
    - Add composite unique index on [pdu_id, rack_id]
    - Foreign key constraints with onDelete cascade
  - [x] 1.6 Create `Rack` model
    - File: `app/Models/Rack.php`
    - Use `HasFactory` and `Loggable` traits
    - Define fillable: name, position, u_height, serial_number, status, row_id
    - Define casts() method for status (RackStatus) and u_height (RackUHeight)
    - Define `row()` BelongsTo relationship
    - Define `pdus()` BelongsToMany relationship with timestamps
    - Pattern reference: `app/Models/Row.php`
  - [x] 1.7 Add `racks()` relationship to Row model
    - File: `app/Models/Row.php`
    - Add `racks(): HasMany` relationship method
  - [x] 1.8 Add `racks()` relationship to Pdu model
    - File: `app/Models/Pdu.php`
    - Add `racks(): BelongsToMany` relationship method with timestamps
  - [x] 1.9 Create `RackFactory`
    - File: `database/factories/RackFactory.php`
    - Define default state with name, position, u_height, status, row_id
    - Add state methods: `active()`, `inactive()`, `maintenance()`, `withUHeight(int)`, `atPosition(int)`
    - Pattern reference: `database/factories/RowFactory.php`
  - [x] 1.10 Run migrations and verify model relationships
    - Execute: `php artisan migrate`
    - Verify all relationships work correctly
  - [x] 1.11 Ensure database layer tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify migrations run successfully
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- RackStatus and RackUHeight enums work correctly with label() methods
- Rack model has correct relationships (belongsTo Row, belongsToMany PDUs)
- Row model has hasMany racks relationship
- Pdu model has belongsToMany racks relationship
- Migrations run without errors
- Factory generates valid test data

### Authorization Layer

#### Task Group 2: Policy and Form Requests
**Dependencies:** Task Group 1

- [x] 2.0 Complete authorization layer
  - [x] 2.1 Write 4-6 focused tests for authorization
    - Test viewAny allows all authenticated users
    - Test view allows Admins/IT Managers always
    - Test view checks datacenter access for other roles
    - Test create/update/delete restricted to Admin/IT Manager
    - Test StoreRackRequest validation rules
    - Test UpdateRackRequest validation rules
  - [x] 2.2 Create `RackPolicy`
    - File: `app/Policies/RackPolicy.php`
    - Define ADMIN_ROLES constant: ['Administrator', 'IT Manager']
    - `viewAny()`: return true for all authenticated users
    - `view()`: Admins always true; others check parent Row->Room->Datacenter access
    - `create()`, `update()`, `delete()`: Admin/IT Manager only
    - `viewElevation()`: same logic as view() for elevation diagram access
    - Pattern reference: `app/Policies/RowPolicy.php`
  - [x] 2.3 Register RackPolicy in AuthServiceProvider
    - File: `app/Providers/AuthServiceProvider.php` (or check Laravel 12 auto-discovery)
    - Add policy mapping if not auto-discovered
    - NOTE: Laravel 12 auto-discovers policies based on naming conventions, no manual registration needed
  - [x] 2.4 Create `StoreRackRequest`
    - File: `app/Http/Requests/StoreRackRequest.php`
    - Authorization: Admin/IT Manager only
    - Rules: name (required, string, max:255), position (required, integer, min:0), u_height (required, enum:RackUHeight), serial_number (nullable, string, max:255), status (required, enum:RackStatus), pdu_ids (nullable, array), pdu_ids.* (exists:pdus,id)
    - Include custom error messages
    - Pattern reference: `app/Http/Requests/StoreRowRequest.php`
  - [x] 2.5 Create `UpdateRackRequest`
    - File: `app/Http/Requests/UpdateRackRequest.php`
    - Same rules as StoreRackRequest
    - Pattern reference: `app/Http/Requests/UpdateRowRequest.php`
  - [x] 2.6 Ensure authorization tests pass
    - Run ONLY the 4-6 tests written in 2.1
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 2.1 pass
- RackPolicy correctly authorizes all CRUD actions
- Elevation view access properly controlled
- Form requests validate all fields correctly
- PDU array validation works for multi-select

### API/Controller Layer

#### Task Group 3: RackController Implementation
**Dependencies:** Task Groups 1, 2

- [x] 3.0 Complete controller layer
  - [x] 3.1 Write 6-8 focused tests for RackController
    - Test index returns racks for a row ordered by position
    - Test create returns form with PDU options and next position
    - Test store creates rack and syncs PDUs
    - Test show returns rack details with assigned PDUs
    - Test edit returns form with current rack data and PDU options
    - Test update modifies rack and syncs PDUs
    - Test destroy detaches PDUs and deletes rack
    - Test elevation returns rack U-height data
  - [x] 3.2 Create/update `RackController`
    - File: `app/Http/Controllers/RackController.php`
    - Define ADMIN_ROLES constant
    - Pattern reference: `app/Http/Controllers/RowController.php`
  - [x] 3.3 Implement `index()` method
    - Gate::authorize viewAny
    - Return racks for row ordered by position
    - Map rack data including pdu_count
    - Pass canCreate based on user role
    - Pass statusOptions and uHeightOptions for filters
  - [x] 3.4 Implement `create()` method
    - Gate::authorize create
    - Calculate nextPosition from row's max position + 1
    - Query available PDUs from same room (room-level and row-level PDUs)
    - Return Inertia::render with form options
  - [x] 3.5 Implement `store()` method
    - Use StoreRackRequest for validation
    - Create Rack with validated data and row_id
    - Sync PDU relationships if pdu_ids provided
    - Redirect to Row show page with success message
  - [x] 3.6 Implement `show()` method
    - Gate::authorize view
    - Load rack with PDUs relationship
    - Map PDU data with status labels
    - Pass canEdit, canDelete flags
    - Return Inertia::render Racks/Show
  - [x] 3.7 Implement `edit()` method
    - Gate::authorize update
    - Query available PDUs (same as create)
    - Return current rack data with selected pdu_ids
    - Return Inertia::render Racks/Edit
  - [x] 3.8 Implement `update()` method
    - Use UpdateRackRequest for validation
    - Update rack attributes
    - Sync PDU relationships
    - Redirect to Row show page with success message
  - [x] 3.9 Implement `destroy()` method
    - Gate::authorize delete
    - Detach all PDU relationships (pivot records only)
    - Delete rack
    - Redirect to Row show page with success message
  - [x] 3.10 Implement `elevation()` method
    - Gate::authorize view (or viewElevation if separate)
    - Return Inertia::render Racks/Elevation with rack data and u_height
  - [x] 3.11 Register routes in web.php (if not already done)
    - File: `routes/web.php`
    - Add nested resource: `Route::resource('datacenters.rooms.rows.racks', RackController::class)`
    - Add elevation route: `Route::get('datacenters/{datacenter}/rooms/{room}/rows/{row}/racks/{rack}/elevation', [RackController::class, 'elevation'])->name('datacenters.rooms.rows.racks.elevation')`
  - [x] 3.12 Ensure controller tests pass
    - Run ONLY the 6-8 tests written in 3.1
    - Verify all controller actions work correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6-8 tests written in 3.1 pass
- All CRUD operations work correctly
- PDU sync works on create/update
- Proper authorization enforced on all actions
- Elevation route returns correct data
- Redirects and flash messages work correctly

### Frontend Layer

#### Task Group 4: TypeScript Types and Shared Components
**Dependencies:** Task Group 3

- [x] 4.0 Complete TypeScript types and shared components
  - [x] 4.1 Add Rack types to `resources/js/types/rooms.ts`
    - Add `RackData` interface: id, name, position, u_height, u_height_label, serial_number, status, status_label, pdu_count, created_at, updated_at
    - Add `RackReference` interface: id, name
    - Add `RowReference` interface if not exists: id, name
  - [x] 4.2 Create `RackForm.vue` component
    - File: `resources/js/components/racks/RackForm.vue`
    - Props: mode ('create'|'edit'), datacenter, room, row, rack?, nextPosition?, uHeightOptions, statusOptions, pduOptions, selectedPduIds?
    - Fields: name (required), position (required integer), u_height (required select), serial_number (optional), status (required select), pdu_ids (multi-select)
    - Use Wayfinder for form action URL generation
    - Include cancel button navigating to Row show page
    - Pattern reference: `resources/js/components/rows/RowForm.vue`
  - [x] 4.3 Create `DeleteRackDialog.vue` component
    - File: `resources/js/components/racks/DeleteRackDialog.vue`
    - Props: datacenterId, roomId, rowId, rackId, rackName, hasPdus
    - Show warning if rack has PDU assignments (will be detached, not deleted)
    - Use Wayfinder for delete action URL
    - Pattern reference: `resources/js/components/rows/DeleteRowDialog.vue`

**Acceptance Criteria:**
- TypeScript types correctly define Rack data structures
- RackForm handles both create and edit modes
- Multi-select for PDUs works correctly
- DeleteRackDialog shows appropriate warnings

#### Task Group 5: Rack Pages (Index, Show, Create, Edit)
**Dependencies:** Task Groups 3, 4

- [x] 5.0 Complete Rack page components
  - [x] 5.1 Write 4-6 focused tests for Rack UI components
    - Test Index page renders rack list correctly
    - Test Show page displays rack details and PDUs
    - Test Create page form submission
    - Test Edit page loads existing rack data
    - Test navigation breadcrumbs are correct
    - Test elevation link appears on Show page
  - [x] 5.2 Create `Racks/Index.vue` page
    - File: `resources/js/Pages/Racks/Index.vue`
    - Breadcrumbs: Datacenters > [datacenter] > Rooms > [room] > Rows > [row] > Racks
    - Table columns: Position, Name, U-Height, PDU Count, Status, Actions
    - Status displayed as Badge with variant based on status
    - Actions: Edit button, DeleteRackDialog
    - Add Rack button for authorized users
    - Back to Row button
    - Pattern reference: `resources/js/Pages/Rows/Index.vue`
  - [x] 5.3 Create `Racks/Show.vue` page
    - File: `resources/js/Pages/Racks/Show.vue`
    - Breadcrumbs: Include Racks index and current rack
    - Rack Details Card: Name, Position, U-Height, Serial Number, Status, Created date
    - Use Server icon (lucide-vue-next) in Rack Details card title
    - Assigned PDUs Card: Table with PDU name, model, capacity, status
    - Use Zap icon in PDUs card title
    - View Elevation button linking to elevation route
    - Edit and Delete buttons for authorized users
    - Back to Row button
    - Pattern reference: `resources/js/Pages/Rows/Show.vue`
  - [x] 5.4 Create `Racks/Create.vue` page
    - File: `resources/js/Pages/Racks/Create.vue`
    - Breadcrumbs: Include "Create Rack" as final item
    - Use RackForm component in create mode
    - Pass nextPosition, uHeightOptions, statusOptions, pduOptions
    - Pattern reference: `resources/js/Pages/Rows/Create.vue`
  - [x] 5.5 Create `Racks/Edit.vue` page
    - File: `resources/js/Pages/Racks/Edit.vue`
    - Breadcrumbs: Include rack name and "Edit" as final item
    - Use RackForm component in edit mode
    - Pass current rack data including selected pdu_ids
    - Pattern reference: `resources/js/Pages/Rows/Edit.vue`
  - [x] 5.6 Run Wayfinder generation
    - Execute: `php artisan wayfinder:generate`
    - Verify RackController actions are generated in `resources/js/actions/`
  - [x] 5.7 Ensure UI component tests pass
    - Run ONLY the 4-6 tests written in 5.1
    - Verify critical component behaviors work
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 5.1 pass
- All pages render correctly with proper styling
- Breadcrumb navigation works throughout hierarchy
- Forms validate and submit correctly
- Status badges display correct variants
- Wayfinder actions work for navigation

#### Task Group 6: Rack Elevation View
**Dependencies:** Task Groups 3, 5

- [x] 6.0 Complete Elevation view
  - [x] 6.1 Write 2-3 focused tests for Elevation view
    - Test elevation page renders correct number of U-slots
    - Test U-slots are numbered 1 to N from bottom to top
    - Test rack details displayed in header
  - [x] 6.2 Create `Racks/Elevation.vue` page
    - File: `resources/js/Pages/Racks/Elevation.vue`
    - Breadcrumbs: Include rack name and "Elevation" as final item
    - Header section: Rack name, U-Height, Status badge
    - Card container with vertical stack of U-slot cards
    - U-slots numbered 1 to rack.u_height from bottom to top
    - Each slot: small card/row element with U number label (e.g., "U1", "U2")
    - Empty state styling: subtle border, muted background
    - Read-only (no click handlers or interactions)
    - Responsive design matching app aesthetic
    - Back to Rack button
  - [x] 6.3 Style elevation U-slots
    - Use Tailwind classes matching app design system
    - Each slot: border, rounded corners, padding
    - U number label aligned left
    - Empty state indicator (e.g., "Empty" text or dashed border)
    - Consistent spacing between slots
    - Mobile-friendly vertical layout
  - [x] 6.4 Ensure elevation tests pass
    - Run ONLY the 2-3 tests written in 6.1
    - Verify visual rendering matches requirements
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-3 tests written in 6.1 pass
- Elevation view renders correct number of U-slots
- U-slots numbered correctly (1 at bottom, N at top)
- Visual design matches app aesthetic
- Responsive on mobile devices

### Integration Layer

#### Task Group 7: Navigation and Cross-References
**Dependencies:** Task Groups 5, 6

- [x] 7.0 Complete navigation integration
  - [x] 7.1 Update `Rows/Show.vue` to show racks
    - Add Racks section card similar to PDUs section
    - Table: Position, Name, U-Height, Status, Actions
    - Use Server icon in card title
    - Link rack names to rack show page
    - Add "Add Rack" button for authorized users
    - Add "View All Racks" link to Racks index
  - [x] 7.2 Update RowController show() method
    - Add racks query to show method
    - Map rack data with status labels and pdu_count
    - Pass canCreateRack flag
  - [x] 7.3 Verify complete navigation flow
    - Test: Datacenter > Room > Row > Racks > Rack > Elevation
    - Test: All breadcrumb links work correctly
    - Test: Back buttons navigate to correct parent pages

**Acceptance Criteria:**
- Rows/Show.vue displays racks in the row
- Navigation flows correctly through entire hierarchy
- All breadcrumbs and back buttons work
- Add Rack accessible from Row show page

### Testing

#### Task Group 8: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-7

- [x] 8.0 Review existing tests and fill critical gaps only
  - [x] 8.1 Review tests from Task Groups 1-7
    - Review the 6 tests from database layer (Task 1.1)
    - Review the 6 tests from authorization (Task 2.1)
    - Review the 8 tests from controller (Task 3.1)
    - Review the 9 tests from UI (Task 5.1)
    - Review the 5 tests from elevation (Task 6.1)
    - Total existing tests: 34 tests
  - [x] 8.2 Analyze test coverage gaps for Rack Management only
    - Identified critical user workflows that lacked test coverage
    - Focused ONLY on gaps related to this spec's feature requirements
    - Prioritized end-to-end workflows and edge cases
  - [x] 8.3 Write up to 10 additional strategic tests maximum
    - Added 10 integration tests in `RackIntegrationTest.php`
    - Focus on integration points between layers
    - Test complete user workflows (create rack without PDUs, clear PDUs)
    - Test edge cases: rack without PDUs, null serial number, authorization boundaries
    - Test cascade behavior: row deletion cascades to racks
  - [x] 8.4 Run feature-specific tests only
    - Ran ONLY tests related to Rack Management feature
    - Total tests: 44 (34 existing + 10 new integration tests)
    - All 44 tests passing with 599 assertions
    - Critical workflows verified

**Acceptance Criteria:**
- All feature-specific tests pass
- Critical user workflows for Rack Management are covered
- No more than 10 additional tests added when filling in gaps
- Testing focused exclusively on Rack Management requirements

## Execution Order

Recommended implementation sequence:

1. **Database Layer (Task Group 1)** - Foundation for all other work
   - Creates enums, migrations, models, relationships, and factory
   - No dependencies on other groups

2. **Authorization Layer (Task Group 2)** - Security before features
   - Depends on: Task Group 1 (Rack model must exist)
   - Creates policy and form request validation

3. **Controller Layer (Task Group 3)** - Backend logic
   - Depends on: Task Groups 1 and 2
   - Implements all CRUD operations and elevation endpoint

4. **TypeScript Types and Shared Components (Task Group 4)** - Frontend foundation
   - Depends on: Task Group 3 (controller defines data structure)
   - Creates types and reusable form/dialog components

5. **Rack Pages (Task Group 5)** - Main UI
   - Depends on: Task Groups 3 and 4
   - Creates Index, Show, Create, Edit pages

6. **Elevation View (Task Group 6)** - Specialized view
   - Depends on: Task Groups 3 and 5
   - Creates dedicated elevation diagram page

7. **Navigation Integration (Task Group 7)** - Cross-references
   - Depends on: Task Groups 5 and 6
   - Updates Row show page to display racks
   - Verifies complete navigation flow

8. **Test Review and Gap Analysis (Task Group 8)** - Quality assurance
   - Depends on: All previous task groups
   - Reviews and fills critical test gaps only

## File Reference Summary

### New Files to Create
- `app/Enums/RackStatus.php`
- `app/Enums/RackUHeight.php`
- `database/migrations/xxxx_create_racks_table.php`
- `database/migrations/xxxx_create_pdu_rack_table.php`
- `app/Models/Rack.php`
- `database/factories/RackFactory.php`
- `app/Policies/RackPolicy.php`
- `app/Http/Requests/StoreRackRequest.php`
- `app/Http/Requests/UpdateRackRequest.php`
- `app/Http/Controllers/RackController.php`
- `resources/js/components/racks/RackForm.vue`
- `resources/js/components/racks/DeleteRackDialog.vue`
- `resources/js/Pages/Racks/Index.vue`
- `resources/js/Pages/Racks/Show.vue`
- `resources/js/Pages/Racks/Create.vue`
- `resources/js/Pages/Racks/Edit.vue`
- `resources/js/Pages/Racks/Elevation.vue`

### Existing Files to Modify
- `app/Models/Row.php` - Add racks() relationship
- `app/Models/Pdu.php` - Add racks() relationship
- `routes/web.php` - Add rack routes
- `resources/js/types/rooms.ts` - Add Rack type interfaces
- `resources/js/Pages/Rows/Show.vue` - Add racks section

### Pattern Reference Files
- `app/Http/Controllers/RowController.php`
- `app/Models/Row.php`
- `app/Enums/RowStatus.php`
- `app/Policies/RowPolicy.php`
- `app/Http/Requests/StoreRowRequest.php`
- `database/factories/RowFactory.php`
- `resources/js/Pages/Rows/Index.vue`
- `resources/js/Pages/Rows/Show.vue`
- `resources/js/components/rows/RowForm.vue`
- `resources/js/components/rows/DeleteRowDialog.vue`
