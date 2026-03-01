# Task Breakdown: Audit Creation

## Overview
Total Tasks: 34

This feature enables IT Managers and Auditors to create new audits with configurable scope (datacenter, room, or individual racks/devices), audit type (connection or inventory), and team assignment for execution.

## Task List

### Database Layer

#### Task Group 1: Enums and Core Data Models
**Dependencies:** None

- [x] 1.0 Complete enums and core data models
  - [x] 1.1 Write 4-6 focused tests for Audit model functionality
    - Test audit creation with required fields
    - Test audit type enum casting
    - Test scope type enum casting
    - Test status defaults to pending on creation
    - Test relationship to datacenter/room/racks
    - Test relationship to assignees (users)
  - [x] 1.2 Create AuditType enum
    - Cases: Connection, Inventory
    - Include `label()` method for display
    - Follow pattern from `/Users/helderdene/rackaudit/app/Enums/RackStatus.php`
  - [x] 1.3 Create AuditScopeType enum
    - Cases: Datacenter, Room, Racks
    - Include `label()` method for display
  - [x] 1.4 Create AuditStatus enum
    - Cases: Pending, InProgress, Completed, Cancelled
    - Include `label()` method for display
  - [x] 1.5 Create Audit model with attributes and casts
    - Fields: name, description, due_date, type, scope_type, status
    - Relationships: datacenter, room, racks (belongsToMany), devices (belongsToMany), assignees (belongsToMany users), implementationFile
    - Use HasFactory, Loggable concerns following `/Users/helderdene/rackaudit/app/Models/ImplementationFile.php`
  - [x] 1.6 Ensure enum and model tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify enums work correctly with model casts

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- Enums have correct cases and label methods
- Audit model has proper casts and relationships defined
- Model follows existing codebase conventions

---

#### Task Group 2: Database Migrations and Pivot Tables
**Dependencies:** Task Group 1

- [x] 2.0 Complete database migrations
  - [x] 2.1 Write 3-5 focused tests for migration and relationships
    - Test audit can be created with all required fields
    - Test audit belongs to datacenter
    - Test audit can have multiple assignees via pivot
    - Test audit can have multiple racks via pivot
    - Test audit can have multiple devices via pivot (optional scope)
  - [x] 2.2 Create main audits table migration
    - id, name, description (nullable), due_date
    - type (enum: connection, inventory)
    - scope_type (enum: datacenter, room, racks)
    - status (enum: pending, in_progress, completed, cancelled) default 'pending'
    - datacenter_id (foreign key, required)
    - room_id (foreign key, nullable - for room scope)
    - implementation_file_id (foreign key, nullable - for connection audits)
    - created_by (foreign key to users)
    - timestamps, soft deletes
    - Add appropriate indexes
  - [x] 2.3 Create audit_user pivot table migration
    - audit_id (foreign key)
    - user_id (foreign key)
    - timestamps
    - Unique constraint on (audit_id, user_id)
  - [x] 2.4 Create audit_rack pivot table migration
    - audit_id (foreign key)
    - rack_id (foreign key)
    - timestamps
    - Unique constraint on (audit_id, rack_id)
  - [x] 2.5 Create audit_device pivot table migration
    - audit_id (foreign key)
    - device_id (foreign key)
    - timestamps
    - Unique constraint on (audit_id, device_id)
  - [x] 2.6 Set up Audit model relationships
    - datacenter(): BelongsTo
    - room(): BelongsTo (nullable)
    - implementationFile(): BelongsTo (nullable)
    - assignees(): BelongsToMany (users)
    - racks(): BelongsToMany
    - devices(): BelongsToMany
    - creator(): BelongsTo (user)
  - [x] 2.7 Create AuditFactory for testing
    - Include states: connectionType(), inventoryType()
    - Include states: datacenterScope(), roomScope(), racksScope()
    - Include state: withAssignees(int $count)
    - Follow pattern from `/Users/helderdene/rackaudit/database/factories/ImplementationFileFactory.php`
  - [x] 2.8 Ensure migration and relationship tests pass
    - Run ONLY the 3-5 tests written in 2.1
    - Verify migrations run successfully
    - Verify factory creates valid records

**Acceptance Criteria:**
- The 3-5 tests written in 2.1 pass
- All migrations run without errors
- Foreign key constraints work correctly
- Pivot tables support many-to-many relationships
- Factory produces valid test data

---

### API Layer

#### Task Group 3: Form Request and Validation
**Dependencies:** Task Group 2

- [x] 3.0 Complete form request validation
  - [x] 3.1 Write 4-6 focused tests for validation rules
    - Test IT Manager can create audits
    - Test Auditor can create audits
    - Test Operator cannot create audits (403)
    - Test validation fails without required fields
    - Test connection audit blocked without approved implementation file
    - Test scope validation based on scope_type
  - [x] 3.2 Create StoreAuditRequest form request
    - Authorization: AUTHORIZED_ROLES = ['Administrator', 'IT Manager', 'Auditor']
    - Use hasAnyRole() pattern from `/Users/helderdene/rackaudit/app/Http/Requests/StoreDatacenterRequest.php`
    - Validation rules:
      - name: required, string, max:255
      - description: nullable, string, max:1000
      - due_date: required, date, after_or_equal:today
      - type: required, in:connection,inventory
      - scope_type: required, in:datacenter,room,racks
      - datacenter_id: required, exists:datacenters,id
      - room_id: required_if:scope_type,room, exists:rooms,id
      - rack_ids: required_if:scope_type,racks, array
      - rack_ids.*: exists:racks,id
      - device_ids: nullable, array (only when scope_type=racks)
      - device_ids.*: exists:devices,id
      - assignee_ids: required, array, min:1
      - assignee_ids.*: exists:users,id
  - [x] 3.3 Add custom validation for connection audits
    - Check datacenter has approved implementation file
    - Return error with link to Implementation Files page if not found
    - Use withValidator() method for custom validation
  - [x] 3.4 Add custom error messages
    - Follow pattern from StoreDatacenterRequest
    - Include user-friendly messages for all rules
  - [x] 3.5 Ensure form request tests pass
    - Run ONLY the 4-6 tests written in 3.1
    - Verify authorization works correctly
    - Verify validation rules work correctly

**Acceptance Criteria:**
- The 4-6 tests written in 3.1 pass
- Only authorized roles can submit requests
- All validation rules work correctly
- Connection audit validation checks for approved implementation file
- Error messages are user-friendly

---

#### Task Group 4: Controller and API Endpoints
**Dependencies:** Task Group 3

- [x] 4.0 Complete controller and API endpoints
  - [x] 4.1 Write 4-6 focused tests for controller actions
    - Test create page loads with required data (datacenters, users)
    - Test store creates audit and redirects
    - Test store auto-links latest approved implementation file for connection audits
    - Test index page shows user's audits
    - Test show page displays audit details
  - [x] 4.2 Create AuditController with resource methods
    - Use `php artisan make:controller AuditController --resource`
    - index(): Show paginated list of audits with filters
    - create(): Render audit creation form with required data
    - store(): Create audit and redirect to show/index
    - show(): Display audit details
    - Follow patterns from `/Users/helderdene/rackaudit/app/Http/Controllers/DatacenterController.php`
  - [x] 4.3 Implement create() method
    - Load datacenters (with user access filter for non-admins)
    - Load users who can be assigned (Operators, Auditors)
    - Return Inertia::render('Audits/Create', [...])
  - [x] 4.4 Implement store() method
    - Validate using StoreAuditRequest
    - Auto-find latest approved implementation file for connection audits
    - Create audit with relationships
    - Attach assignees, racks, devices as needed
    - Redirect to audit index/show with success message
  - [x] 4.5 Implement index() method
    - Filter audits by user access (assignees or admin)
    - Include search and filter by status/type
    - Paginate results
  - [x] 4.6 Add routes to web.php
    - Route::resource('audits', AuditController::class)->only(['index', 'create', 'store', 'show'])
    - Add middleware(['auth'])
  - [x] 4.7 Ensure controller tests pass
    - Run ONLY the 4-6 tests written in 4.1
    - Verify all actions work correctly

**Acceptance Criteria:**
- The 4-6 tests written in 4.1 pass
- All CRUD operations work correctly
- Latest approved implementation file auto-linked for connection audits
- Routes registered and accessible

---

#### Task Group 5: API Endpoints for Cascading Dropdowns
**Dependencies:** Task Group 4

- [x] 5.0 Complete cascading dropdown API endpoints
  - [x] 5.1 Write 3-4 focused tests for dropdown APIs
    - Test rooms endpoint returns rooms for datacenter
    - Test racks endpoint returns racks for room
    - Test devices endpoint returns devices for racks
    - Test users endpoint returns assignable users
  - [x] 5.2 Create API routes for dropdown data
    - GET /api/audits/datacenters/{datacenter}/rooms
    - GET /api/audits/rooms/{room}/racks
    - GET /api/audits/racks/{rack}/devices (or bulk with rack_ids[])
    - GET /api/audits/assignable-users
  - [x] 5.3 Add methods to AuditController or create Api/AuditDataController
    - rooms(): Return rooms for datacenter with rack counts
    - racks(): Return racks for room (or rooms array)
    - devices(): Return devices for rack(s) with asset_tag, start_u
    - assignableUsers(): Return users who can execute audits
  - [x] 5.4 Ensure API endpoint tests pass
    - Run ONLY the 3-4 tests written in 5.1

**Acceptance Criteria:**
- The 3-4 tests written in 5.1 pass
- All dropdown APIs return correct filtered data
- Responses include necessary fields for UI display

---

### Frontend Components

#### Task Group 6: Audit Type Selection UI
**Dependencies:** Task Group 4

- [x] 6.0 Complete audit type selection component
  - [x] 6.1 Write 2-3 focused tests for type selection
    - Test type selector renders both options
    - Test selecting type updates form state
    - Test type descriptions display correctly
  - [x] 6.2 Create AuditTypeSelector.vue component
    - Radio button group for Connection/Inventory
    - Descriptive text for each option:
      - Connection: "Verify physical connections match the approved implementation file"
      - Inventory: "Verify documented devices exist physically and are in correct positions"
    - Emit selected type to parent
    - Use card-style radio buttons for visual clarity
  - [x] 6.3 Apply styling with Tailwind CSS
    - Match existing form component styles from `/Users/helderdene/rackaudit/resources/js/Components/datacenters/DatacenterForm.vue`
    - Dark mode support
  - [x] 6.4 Ensure type selection tests pass
    - Run ONLY the 2-3 tests written in 6.1

**Acceptance Criteria:**
- The 2-3 tests written in 6.1 pass
- Type selector is visually clear and intuitive
- Descriptions help users understand each audit type

---

#### Task Group 7: Scope Selection UI
**Dependencies:** Task Group 5, Task Group 6

- [x] 7.0 Complete scope selection components
  - [x] 7.1 Write 3-4 focused tests for scope selection
    - Test scope type tabs render correctly
    - Test datacenter dropdown loads and cascades to rooms
    - Test rack multi-select component works
    - Test device selection only appears for rack scope
  - [x] 7.2 Create ScopeTypeSelector.vue component
    - Tab/radio group for Datacenter/Room/Racks scope types
    - Clear labeling for each scope type
    - Emit selected scope type to parent
  - [x] 7.3 Create CascadingLocationSelect.vue component
    - Datacenter dropdown (always visible)
    - Room dropdown (visible for room/racks scope, cascades from datacenter)
    - Display rack count summary when datacenter/room selected
    - Fetch rooms via API when datacenter changes
  - [x] 7.4 Create RackMultiSelect.vue component
    - Searchable multi-select for racks
    - Filter by room when room selected
    - Display rack name and location
    - Use existing component patterns or build custom
  - [x] 7.5 Create DeviceMultiSelect.vue component
    - Only shown when scope_type = 'racks' and racks selected
    - Fetch devices for selected racks
    - Display device name, asset_tag, start_u position
    - Optional selection (empty = all devices in racks)
  - [x] 7.6 Ensure scope selection tests pass
    - Run ONLY the 3-4 tests written in 7.1

**Acceptance Criteria:**
- The 3-4 tests written in 7.1 pass
- Cascading dropdowns work smoothly
- Rack and device multi-select are intuitive
- Rack count summaries display correctly

---

#### Task Group 8: Audit Metadata Form
**Dependencies:** Task Group 7

- [x] 8.0 Complete audit metadata form
  - [x] 8.1 Write 2-3 focused tests for metadata form
    - Test form fields render with validation
    - Test date picker works for due_date
    - Test assignee multi-select loads users
  - [x] 8.2 Create AuditMetadataForm.vue component
    - Name field (required, Input component)
    - Description field (optional, Textarea component)
    - Due date field (required, date picker)
    - Follow HeadingSmall pattern for section grouping
  - [x] 8.3 Create AssigneeMultiSelect.vue component
    - Fetch assignable users from API
    - Multi-select with user name and email display
    - Required field (at least one assignee)
  - [x] 8.4 Ensure metadata form tests pass
    - Run ONLY the 2-3 tests written in 8.1

**Acceptance Criteria:**
- The 2-3 tests written in 8.1 pass
- All fields work correctly with validation
- Date picker prevents past dates
- Assignee selection is intuitive

---

#### Task Group 9: Implementation File Validation UI
**Dependencies:** Task Group 8

- [x] 9.0 Complete implementation file validation display
  - [x] 9.1 Write 2-3 focused tests for implementation file display
    - Test approved file displays name and version
    - Test error message shows when no approved file
    - Test link to Implementation Files page works
  - [x] 9.2 Create ImplementationFileStatus.vue component
    - Only shown for Connection audit type
    - Display linked implementation file name and version when found
    - Display error alert when no approved file exists
    - Include Link component to navigate to Implementation Files page
    - Use Alert component from `/Users/helderdene/rackaudit/resources/js/Components/ui/alert/`
  - [x] 9.3 Integrate with AuditForm to fetch implementation file status
    - On datacenter change for connection audits, check for approved files
    - Display loading state while checking
  - [x] 9.4 Ensure implementation file status tests pass
    - Run ONLY the 2-3 tests written in 9.1

**Acceptance Criteria:**
- The 2-3 tests written in 9.1 pass
- Users clearly see linked implementation file
- Error state blocks form submission with helpful message

---

#### Task Group 10: Main Audit Form Page
**Dependencies:** Task Groups 6-9

- [x] 10.0 Complete main audit creation page
  - [x] 10.1 Write 2-4 focused tests for full form flow
    - Test create page renders with all components
    - Test form submission creates audit successfully
    - Test validation errors display correctly
    - Test redirect after successful creation
  - [x] 10.2 Create AuditForm.vue component
    - Integrate all sub-components:
      - AuditTypeSelector
      - ScopeTypeSelector + CascadingLocationSelect
      - RackMultiSelect + DeviceMultiSelect (conditional)
      - AuditMetadataForm + AssigneeMultiSelect
      - ImplementationFileStatus (conditional for connection)
    - Use Form component from @inertiajs/vue3
    - Handle form state and validation errors
    - Follow section-based layout with HeadingSmall
  - [x] 10.3 Create Audits/Create.vue page
    - Use AppLayout with breadcrumbs
    - Render AuditForm component
    - Follow pattern from `/Users/helderdene/rackaudit/resources/js/Pages/Datacenters/Create.vue`
  - [x] 10.4 Create Audits/Index.vue page (basic)
    - Paginated list of audits
    - Show name, type, scope, status, due date
    - Link to create new audit
    - Filter by status
  - [x] 10.5 Create Audits/Show.vue page (basic)
    - Display audit details
    - Show assignees, scope summary
    - Link to edit/execution (out of scope but route ready)
  - [x] 10.6 Ensure full form tests pass
    - Run ONLY the 2-4 tests written in 10.1

**Acceptance Criteria:**
- The 2-4 tests written in 10.1 pass
- Full form flow works end-to-end
- All validation displays correctly
- Successful creation redirects appropriately

---

#### Task Group 11: Responsive Design and Polish
**Dependencies:** Task Group 10

- [x] 11.0 Complete responsive design and polish
  - [x] 11.1 Write 2 focused tests for responsive behavior
    - Test form renders correctly on mobile viewport
    - Test multi-select components work on touch devices
  - [x] 11.2 Apply responsive Tailwind classes
    - Mobile: Stack form sections vertically
    - Tablet: Two-column layout where appropriate
    - Desktop: Full form width with readable line lengths
    - Use sm:, md:, lg: breakpoints
  - [x] 11.3 Add loading and transition states
    - Loading spinners for API calls
    - Skeleton states for cascading dropdowns
    - Form submission processing state
  - [x] 11.4 Ensure responsive tests pass
    - Run ONLY the 2 tests written in 11.1

**Acceptance Criteria:**
- The 2 tests written in 11.1 pass
- Form works well on all screen sizes
- Loading states provide good feedback

---

### Testing

#### Task Group 12: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-11

- [x] 12.0 Review existing tests and fill critical gaps only
  - [x] 12.1 Review tests from Task Groups 1-11
    - Review the 4-6 tests from database layer (1.1, 2.1)
    - Review the 4-6 tests from API layer (3.1, 4.1, 5.1)
    - Review the ~12 tests from frontend components (6.1, 7.1, 8.1, 9.1, 10.1, 11.1)
    - Total existing tests: approximately 20-28 tests
  - [x] 12.2 Analyze test coverage gaps for audit creation feature only
    - Identify critical user workflows that lack test coverage
    - Focus ONLY on gaps related to this spec's feature requirements
    - Do NOT assess entire application test coverage
    - Prioritize end-to-end workflows over unit test gaps
  - [x] 12.3 Write up to 6 additional strategic tests maximum
    - Add maximum of 6 new tests to fill identified critical gaps
    - Focus on integration points and edge cases:
      - Test creating connection audit with no approved file returns proper error
      - Test creating audit with datacenter scope includes all racks
      - Test creating audit with room scope calculates rack count
      - Test partial scope (racks with specific devices) works correctly
      - Test audit creator is properly recorded
      - Test multiple assignees are all attached
  - [x] 12.4 Run feature-specific tests only
    - Run ONLY tests related to audit creation feature
    - Expected total: approximately 26-34 tests maximum
    - Do NOT run the entire application test suite
    - Verify critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 26-34 tests total)
- Critical user workflows for audit creation are covered
- No more than 6 additional tests added when filling in testing gaps
- Testing focused exclusively on audit creation feature requirements

---

## Execution Order

Recommended implementation sequence:

1. **Database Layer (Task Groups 1-2)**
   - Start with enums and models (Group 1)
   - Then migrations and factories (Group 2)

2. **API Layer (Task Groups 3-5)**
   - Form request validation (Group 3)
   - Controller and routes (Group 4)
   - Cascading dropdown APIs (Group 5)

3. **Frontend Components (Task Groups 6-11)**
   - Audit type selection (Group 6)
   - Scope selection components (Group 7)
   - Metadata form (Group 8)
   - Implementation file validation (Group 9)
   - Main form page integration (Group 10)
   - Responsive design and polish (Group 11)

4. **Test Review (Task Group 12)**
   - Review all tests from previous groups
   - Fill critical gaps only

---

## Notes

### Key Files to Reference
- Form pattern: `/Users/helderdene/rackaudit/resources/js/Components/datacenters/DatacenterForm.vue`
- Request pattern: `/Users/helderdene/rackaudit/app/Http/Requests/StoreAuditRequest.php`
- Controller pattern: `/Users/helderdene/rackaudit/app/Http/Controllers/DatacenterController.php`
- Model pattern: `/Users/helderdene/rackaudit/app/Models/ImplementationFile.php`
- Enum pattern: `/Users/helderdene/rackaudit/app/Enums/RackStatus.php`
- Factory pattern: `/Users/helderdene/rackaudit/database/factories/ImplementationFileFactory.php`
- Test pattern: `/Users/helderdene/rackaudit/tests/Feature/ImplementationFile/ImplementationFileApiTest.php`

### Existing Infrastructure to Leverage
- Datacenter model with `hasApprovedImplementationFiles()` method
- ImplementationFile model with `isApproved()` method
- Existing hierarchical relationships: Datacenter -> Room -> Row -> Rack -> Device
- UI components in `/Users/helderdene/rackaudit/resources/js/Components/ui/`
- HeadingSmall, InputError, Button, Input, Label components

### Out of Scope (Do Not Implement)
- Audit execution workflow and mobile interface
- Discrepancy detection engine and finding management
- Audit templates or recurring/scheduled audit series
- Future date scheduling (audits are immediately available)
- Row-level scope selection
- Combined connection + inventory audits
- Audit editing, cloning, deletion, or archiving
- Audit notifications or reminders
- Audit reporting or export
