# Task Breakdown: Equipment Move Workflow

## Overview
Total Tasks: 37 sub-tasks across 5 task groups

This feature implements a guided multi-step wizard for moving devices between racks with connection documentation, approval workflow, and PDF work order generation. The implementation follows existing patterns from `ImplementationFile.php` (approval workflow), `Connection.php` (full state logging), and `AssetReportService.php` (PDF generation).

## Task List

### Database Layer

#### Task Group 1: EquipmentMove Model and Migration
**Dependencies:** None

- [x] 1.0 Complete database layer for EquipmentMove
  - [x] 1.1 Write 4-6 focused tests for EquipmentMove model functionality
    - Test status transitions (pending_approval -> approved -> executed)
    - Test connections_snapshot JSON casting and storage
    - Test relationships (device, sourceRack, destinationRack, requester, approver)
    - Test validation: device cannot have multiple pending moves
    - Test isPendingApproval(), isApproved(), isExecuted() helper methods
  - [x] 1.2 Create EquipmentMove model with Loggable trait
    - Apply `Loggable` trait following `Connection.php` pattern
    - Set `$logFullState = true` for complete state snapshots
    - Define fillable fields: device_id, source_rack_id, destination_rack_id, source_start_u, destination_start_u, source_rack_face, destination_rack_face, source_width_type, destination_width_type, status, connections_snapshot, requested_by, approved_by, operator_notes, approval_notes, requested_at, approved_at, executed_at
    - Cast connections_snapshot to array/JSON
    - Cast timestamps (requested_at, approved_at, executed_at) to datetime
    - Reference `ImplementationFile.php` for approval workflow pattern
  - [x] 1.3 Create migration for equipment_moves table
    - Add foreign keys: device_id, source_rack_id, destination_rack_id, requested_by, approved_by
    - Add indexes for: device_id, status, requested_by, approved_by
    - Use nullOnDelete for approved_by (matches ImplementationFile pattern)
    - Add status enum: pending_approval, approved, rejected, executed, cancelled
  - [x] 1.4 Set up model relationships and helper methods
    - belongsTo: device, sourceRack (Rack), destinationRack (Rack), requester (User), approver (User)
    - Implement isPendingApproval(), isApproved(), isRejected(), isExecuted(), isCancelled() helpers
    - Implement getEnrichedAttributesForLog() for activity logging (reference Connection.php)
    - Add scope for filtering by status, device, rack, date range
  - [x] 1.5 Create EquipmentMoveFactory for testing
    - Define default state with pending_approval status
    - Add states: approved(), rejected(), executed(), cancelled()
    - Include connections_snapshot sample data
  - [x] 1.6 Ensure database layer tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify migration runs successfully
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- EquipmentMove model properly tracks move requests with full state logging
- Status transitions work correctly
- JSON connections_snapshot stores and retrieves correctly
- All relationships load correctly

---

### Backend Services Layer

#### Task Group 2: Move Workflow Service and Business Logic
**Dependencies:** Task Group 1

- [x] 2.0 Complete move workflow service layer
  - [x] 2.1 Write 6-8 focused tests for move workflow business logic
    - Test move request creation with connections snapshot capture
    - Test validation: device not already in pending move
    - Test validation: destination position availability (collision detection)
    - Test approval flow: approve move, reject move
    - Test execution: connections auto-disconnect on approval
    - Test execution: device placement update after approval
    - Test cancel pending move
  - [x] 2.2 Create EquipmentMoveService with core methods
    - `createMoveRequest(Device $device, array $destinationData, User $requester): EquipmentMove`
    - `captureConnectionsSnapshot(Device $device): array` - capture all active connections before move
    - `validateDestinationPosition(int $rackId, int $startU, string $face, string $widthType, int $uSize): bool`
    - `checkDeviceHasPendingMove(Device $device): bool`
    - Reference `useRackElevation.ts` collision detection logic for backend validation
  - [x] 2.3 Implement approval workflow methods
    - `approveMove(EquipmentMove $move, User $approver, ?string $notes = null): bool`
    - `rejectMove(EquipmentMove $move, User $approver, string $notes): bool`
    - `cancelMove(EquipmentMove $move, User $user): bool`
    - Update status, timestamps, and approval notes accordingly
  - [x] 2.4 Implement move execution logic
    - `executeMove(EquipmentMove $move): bool` - called automatically after approval
    - Auto-disconnect all device connections with full state logging
    - Update device placement (rack_id, start_u, rack_face, width_type)
    - Update move status to 'executed' with executed_at timestamp
    - Reference `Connection.php` soft delete pattern for disconnections
  - [x] 2.5 Create EquipmentMovePolicy for authorization
    - `create`: authenticated users can initiate moves
    - `approve`: users with manager/approver role only
    - `cancel`: requester can cancel pending moves, managers can cancel any
    - `view`: participants and managers can view
  - [x] 2.6 Ensure service layer tests pass
    - Run ONLY the 6-8 tests written in 2.1
    - Verify all business logic works correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6-8 tests written in 2.1 pass
- Move requests capture complete connection snapshots
- Destination validation prevents collisions
- Approval/rejection workflow updates all relevant fields
- Move execution properly disconnects connections and updates device placement

---

### API Layer

#### Task Group 3: Controllers and API Endpoints
**Dependencies:** Task Group 2

- [x] 3.0 Complete API layer for equipment moves
  - [x] 3.1 Write 6-8 focused tests for API endpoints
    - Test POST /equipment-moves (create move request)
    - Test GET /equipment-moves (index with filters)
    - Test GET /equipment-moves/{id} (show details)
    - Test POST /equipment-moves/{id}/approve
    - Test POST /equipment-moves/{id}/reject
    - Test POST /equipment-moves/{id}/cancel
    - Test authorization: only managers can approve
    - Test validation errors return proper format
  - [x] 3.2 Create EquipmentMoveController with CRUD actions
    - `index()`: list moves with pagination and filters (status, device_id, rack_id, date range)
    - `store()`: create new move request (validates destination availability)
    - `show()`: get move details with relationships
    - Follow existing controller patterns in the codebase
  - [x] 3.3 Create approval workflow endpoints
    - `approve(EquipmentMove $move)`: approve and execute move
    - `reject(EquipmentMove $move)`: reject with required notes
    - `cancel(EquipmentMove $move)`: cancel pending move
  - [x] 3.4 Create Form Request classes for validation
    - `StoreEquipmentMoveRequest`: device_id (required, not in pending move), destination_rack_id, destination_start_u, destination_rack_face, destination_width_type, operator_notes
    - `RejectEquipmentMoveRequest`: approval_notes (required)
    - `ApproveEquipmentMoveRequest`: approval_notes (optional)
  - [x] 3.5 Create EquipmentMoveResource for API responses
    - Include device details with location hierarchy
    - Include source and destination rack details
    - Include connections_snapshot formatted for display
    - Include requester and approver user info
    - Include all timestamps formatted
  - [x] 3.6 Register routes in routes/web.php (Inertia pages)
    - Resource routes for index, show
    - Custom routes for approve, reject, cancel actions
  - [x] 3.7 Ensure API layer tests pass
    - Run ONLY the 6-8 tests written in 3.1
    - Verify CRUD and workflow operations work
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6-8 tests written in 3.1 pass
- All endpoints return proper JSON responses with EquipmentMoveResource
- Authorization enforced via EquipmentMovePolicy
- Validation errors return consistent format
- Routes registered and accessible

---

### Frontend Components Layer

#### Task Group 4: Vue Components and Multi-Step Wizard
**Dependencies:** Task Group 3

- [x] 4.0 Complete frontend UI components
  - [x] 4.1 Write 4-6 focused tests for critical UI behaviors
    - Test wizard step navigation (next, back, progress indicator)
    - Test device search and selection in Step 1
    - Test connection acknowledgment checkbox blocks proceeding in Step 2
    - Test destination position picker shows collision warnings in Step 3
    - Test form submission creates move request
  - [x] 4.2 Create MoveWizard.vue dialog component (main wizard container)
    - Use dialog/sheet pattern from existing modals
    - Manage wizard state: current step, form data, validation
    - Show progress indicator (4 steps)
    - Handle step navigation with back button support
    - Props: isOpen, device (optional pre-selected), onClose, onComplete
  - [x] 4.3 Create Step 1: DeviceSelectionStep.vue
    - Device search input (by name, asset tag, serial number)
    - Current location display: datacenter > room > row > rack > U position
    - Device attributes display: U height, width type, rack face
    - Disable devices with pending moves (show indicator)
    - Support pre-selection when launched from device detail page
  - [x] 4.4 Create Step 2: ConnectionReviewStep.vue
    - List all active connections on selected device
    - Display: source port, destination port, cable type, cable length, cable color
    - Show connected device names and port labels
    - Acknowledgment checkbox: "I understand all connections will be disconnected"
    - Block "Next" button until acknowledged (when connections exist)
  - [x] 4.5 Create Step 3: DestinationSelectionStep.vue
    - Hierarchical select: datacenter > room > row > rack
    - Visual U position picker (adapt patterns from useRackElevation.ts)
    - Real-time collision detection with visual feedback
    - Support intra-rack moves (same rack, different U position)
    - Show destination rack utilization statistics
    - Rack face selector (front/rear)
    - Width type selector (full/half-left/half-right)
  - [x] 4.6 Create Step 4: ConfirmationStep.vue
    - Summary display: device info, source location, destination location
    - Operator notes textarea
    - Submit button with loading state
    - Success/error feedback
  - [x] 4.7 Create supporting composable: useDestinationPicker.ts
    - Fetch available racks with utilization data
    - Implement canPlaceAt() logic (port from useRackElevation.ts)
    - Get valid drop positions for destination rack
    - Manage destination selection state
  - [x] 4.8 Create Move History pages
    - `Pages/EquipmentMoves/Index.vue`: list with filters (status, device, rack, date)
    - Pagination support
    - Status badges using existing Badge component
    - Link to move details
    - Filter controls (select for status, search for device, date picker)
  - [x] 4.9 Create Move Details page
    - `Pages/EquipmentMoves/Show.vue`: full move details
    - Display device info, source/destination locations
    - Display connections that were/will be disconnected
    - Action buttons for approve/reject/cancel (based on status and permissions)
    - Timeline showing move lifecycle (requested, approved, executed timestamps)
  - [x] 4.10 Integrate wizard launch points
    - Add "Move Device" button to device detail page
    - Add "Move" action to rack elevation view device context menu
    - Add "Move History" link to device detail page
  - [x] 4.11 Apply responsive design and dark mode support
    - Mobile: 320px - 768px (wizard as full-screen modal)
    - Tablet: 768px - 1024px
    - Desktop: 1024px+ (wizard as centered dialog)
    - Follow existing dark mode patterns with `dark:` classes
  - [x] 4.12 Ensure UI component tests pass
    - Run ONLY the 4-6 tests written in 4.1
    - Verify critical user flows work
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 4.1 pass
- Wizard navigates through all 4 steps correctly
- Connection acknowledgment enforced when connections exist
- Destination picker shows real-time collision feedback
- Move history displays with proper filtering
- Responsive across all screen sizes
- Dark mode supported

---

### PDF Generation and Integration Layer

#### Task Group 5: PDF Work Order and Final Integration
**Dependencies:** Task Groups 1-4

- [x] 5.0 Complete PDF work order generation and integration
  - [x] 5.1 Write 4-6 focused tests for PDF and integration
    - Test PDF work order generates with correct device data
    - Test PDF includes all connections in snapshot
    - Test PDF download endpoint returns valid PDF
    - Test move history links from device detail page
    - Test full workflow: create -> approve -> execute (integration)
  - [x] 5.2 Create EquipmentMoveReportService for PDF generation
    - `generateWorkOrder(EquipmentMove $move, User $generator): string`
    - Follow `AssetReportService.php` pattern with Pdf::loadView()
    - Return file path for download
  - [x] 5.3 Create PDF Blade template: resources/views/pdf/move-work-order.blade.php
    - Device details: name, asset tag, serial number, manufacturer, model
    - Current location: datacenter, room, row, rack, U position, face
    - Destination location: datacenter, room, row, rack, U position, face
    - Connections table: port labels, cable type, cable length, cable color, connected device
    - Signature/timestamp fields for operator use
    - Move request metadata: requested by, requested at, status
    - Print-friendly styling
  - [x] 5.4 Add PDF download endpoint to EquipmentMoveController
    - `downloadWorkOrder(EquipmentMove $move)`: generate and stream PDF
    - Authorization: participants and managers can download
  - [x] 5.5 Add PDF download button to UI
    - Add to move details page (Show.vue)
    - Add to wizard confirmation step (after successful submission)
  - [x] 5.6 Run `vendor/bin/pint --dirty` for code formatting
  - [x] 5.7 Ensure PDF and integration tests pass
    - Run ONLY the 4-6 tests written in 5.1
    - Verify PDF generates correctly
    - Verify full workflow works end-to-end
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 5.1 pass
- PDF work order includes all required information
- PDF downloads correctly from both detail page and wizard
- Full move workflow operates correctly end-to-end

---

### Testing

#### Task Group 6: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-5

- [x] 6.0 Review existing tests and fill critical gaps only
  - [x] 6.1 Review tests from Task Groups 1-5
    - Review the 4-6 tests written by database layer (Task 1.1)
    - Review the 6-8 tests written by service layer (Task 2.1)
    - Review the 6-8 tests written by API layer (Task 3.1)
    - Review the 4-6 tests written by frontend layer (Task 4.1)
    - Review the 4-6 tests written by integration layer (Task 5.1)
    - Total existing tests: approximately 24-34 tests
  - [x] 6.2 Analyze test coverage gaps for Equipment Move Workflow feature only
    - Identify critical user workflows that lack test coverage
    - Focus ONLY on gaps related to this spec's feature requirements
    - Do NOT assess entire application test coverage
    - Prioritize end-to-end workflows over unit test gaps
  - [x] 6.3 Write up to 10 additional strategic tests maximum
    - Add maximum of 10 new tests to fill identified critical gaps
    - Focus on: complete move lifecycle, permission edge cases, concurrent move prevention
    - Do NOT write comprehensive coverage for all scenarios
    - Skip edge cases, performance tests unless business-critical
  - [x] 6.4 Run feature-specific tests only
    - Run ONLY tests related to Equipment Move Workflow feature
    - Expected total: approximately 34-44 tests maximum
    - Do NOT run the entire application test suite
    - Verify all critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 34-44 tests total)
- Critical user workflows for Equipment Move Workflow are covered
- No more than 10 additional tests added when filling in testing gaps
- Testing focused exclusively on this spec's feature requirements

---

## Execution Order

Recommended implementation sequence:

1. **Database Layer** (Task Group 1) - Foundation for all other work
2. **Backend Services Layer** (Task Group 2) - Business logic before API exposure
3. **API Layer** (Task Group 3) - Backend complete before frontend
4. **Frontend Components Layer** (Task Group 4) - UI requires working API
5. **PDF Generation and Integration** (Task Group 5) - Enhancement after core features
6. **Test Review and Gap Analysis** (Task Group 6) - Final validation

## Key Files to Create

### Backend
- `app/Models/EquipmentMove.php`
- `database/migrations/xxxx_xx_xx_create_equipment_moves_table.php`
- `database/factories/EquipmentMoveFactory.php`
- `app/Services/EquipmentMoveService.php`
- `app/Services/EquipmentMoveReportService.php`
- `app/Http/Controllers/EquipmentMoveController.php`
- `app/Http/Resources/EquipmentMoveResource.php`
- `app/Http/Requests/StoreEquipmentMoveRequest.php`
- `app/Http/Requests/ApproveEquipmentMoveRequest.php`
- `app/Http/Requests/RejectEquipmentMoveRequest.php`
- `app/Policies/EquipmentMovePolicy.php`
- `resources/views/pdf/move-work-order.blade.php`

### Frontend
- `resources/js/Pages/EquipmentMoves/Index.vue`
- `resources/js/Pages/EquipmentMoves/Show.vue`
- `resources/js/Components/EquipmentMoves/MoveWizard.vue`
- `resources/js/Components/EquipmentMoves/DeviceSelectionStep.vue`
- `resources/js/Components/EquipmentMoves/ConnectionReviewStep.vue`
- `resources/js/Components/EquipmentMoves/DestinationSelectionStep.vue`
- `resources/js/Components/EquipmentMoves/ConfirmationStep.vue`
- `resources/js/composables/useDestinationPicker.ts`

### Tests
- `tests/Feature/EquipmentMoveTest.php`
- `tests/Feature/EquipmentMoveServiceTest.php`
- `tests/Feature/EquipmentMoveApiTest.php`
- `tests/Feature/EquipmentMoveWorkflowTest.php`

## Dependencies on Existing Code

- **ImplementationFile.php**: Approval workflow pattern (status enum, approved_by, approved_at)
- **Connection.php**: Full state logging pattern ($logFullState, getEnrichedAttributesForLog)
- **AssetReportService.php**: PDF generation pattern (Pdf::loadView, storeReport)
- **useRackElevation.ts**: Position validation (canPlaceAt, getOccupationMap, getValidDropPositions)
- **Loggable trait**: Activity logging for audit trail
- **Device model**: Relationships and placement fields
- **Rack model**: Location hierarchy relationships
