# Task Breakdown: Inventory Audit Execution

## Overview
Total Tasks: 45 (across 5 task groups)

This feature enables operators to execute inventory audits by verifying physical devices against documented inventory records, with barcode/QR scanning support for device identification, and tracking verification progress across multiple operators working simultaneously.

## Task List

### Database Layer

#### Task Group 1: Data Models and Migrations
**Dependencies:** None
**Complexity:** Medium

- [x] 1.0 Complete database layer for inventory audit verification
  - [x] 1.1 Write 6 focused tests for AuditDeviceVerification model
    - Test model creation with required fields (audit_id, device_id, verification_status)
    - Test verification status transitions (pending -> verified, pending -> not_found, pending -> discrepant)
    - Test lock acquisition and expiration logic
    - Test relationship to audit, device, and verifiedBy user
    - Test scope queries (pending, verified, notFound, discrepant, locked, expiredLocks)
    - Test markVerified(), markNotFound(), and markDiscrepant() methods
  - [x] 1.2 Create migration for `audit_device_verifications` table
    - Fields: id, audit_id (foreign key), device_id (foreign key), rack_id (nullable foreign key for context)
    - Status fields: verification_status (enum: pending, verified, not_found, discrepant)
    - Verification details: notes (text nullable), verified_by (foreign key nullable), verified_at (timestamp nullable)
    - Locking fields: locked_by (foreign key nullable), locked_at (timestamp nullable)
    - Timestamps: created_at, updated_at
    - Add indexes for: audit_id, device_id, verification_status, locked_by
    - Add composite index for: (audit_id, verification_status) for progress queries
  - [x] 1.3 Create migration for `audit_rack_verifications` table (empty rack confirmations)
    - Fields: id, audit_id (foreign key), rack_id (foreign key)
    - Status: verified (boolean default false), notes (text nullable)
    - Verification details: verified_by (foreign key nullable), verified_at (timestamp nullable)
    - Timestamps: created_at, updated_at
    - Add unique constraint on (audit_id, rack_id)
  - [x] 1.4 Create `AuditDeviceVerification` model
    - Follow `AuditConnectionVerification` model structure
    - Fillable: audit_id, device_id, rack_id, verification_status, notes, verified_by, verified_at, locked_by, locked_at
    - Casts: verification_status (DeviceVerificationStatus enum), verified_at (datetime), locked_at (datetime)
    - Constant: LOCK_EXPIRATION_MINUTES = 5
    - Relationships: audit(), device(), rack(), verifiedBy(), lockedBy(), finding()
    - Scopes: pending(), verified(), notFound(), discrepant(), locked(), expiredLocks()
    - Methods: isLocked(), isLockedBy(User), lockFor(User), unlock(), markVerified(User, notes), markNotFound(User, notes), markDiscrepant(User, notes)
  - [x] 1.5 Create `AuditRackVerification` model
    - Fillable: audit_id, rack_id, verified, notes, verified_by, verified_at
    - Relationships: audit(), rack(), verifiedBy()
    - Methods: markVerified(User, notes)
  - [x] 1.6 Create `DeviceVerificationStatus` enum
    - Cases: Pending, Verified, NotFound, Discrepant
    - Method: label() returning human-readable labels
  - [x] 1.7 Update `Finding` model to support device verification
    - Add audit_device_verification_id to fillable array
    - Add deviceVerification() relationship (nullable)
    - Update migration to add nullable audit_device_verification_id foreign key
  - [x] 1.8 Update `Audit` model with device verification relationships
    - Add deviceVerifications() hasMany relationship
    - Add rackVerifications() hasMany relationship
    - Add methods: pendingDeviceVerifications(), totalDeviceVerifications()
  - [x] 1.9 Create model factories
    - AuditDeviceVerificationFactory with states: pending, verified, notFound, discrepant, locked
    - AuditRackVerificationFactory with states: pending, verified
  - [x] 1.10 Ensure database layer tests pass
    - Run ONLY the 6 tests written in 1.1
    - Verify migrations run successfully
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6 tests written in 1.1 pass
- Migrations run without errors
- Models have correct relationships and methods
- Locking mechanism works correctly with 5-minute expiration
- Device verification statuses transition correctly

---

### Backend Service Layer

#### Task Group 2: Audit Execution Service Extension
**Dependencies:** Task Group 1
**Complexity:** High

- [x] 2.0 Complete backend service layer for inventory audit execution
  - [x] 2.1 Write 8 focused tests for inventory audit service methods
    - Test prepareDeviceVerificationItems() creates records for all devices in audit scope
    - Test prepareDeviceVerificationItems() includes empty racks for confirmation
    - Test prepareDeviceVerificationItems() skips if verifications already exist
    - Test markDeviceVerified() updates status and records operator
    - Test markDeviceNotFound() creates Finding automatically
    - Test markDeviceDiscrepant() creates Finding with notes
    - Test bulkVerifyDevices() verifies multiple pending devices
    - Test getInventoryProgressStats() returns accurate counts
  - [x] 2.2 Extend `AuditExecutionService` with inventory audit methods
    - Add prepareDeviceVerificationItems(Audit) - queries devices based on scope, creates verification records
    - Add getDeviceVerificationItems(Audit, filters) - returns paginated list with eager loading
    - Add markDeviceVerified(AuditDeviceVerification, User, notes) - marks as verified
    - Add markDeviceNotFound(AuditDeviceVerification, User, notes) - marks as not found, creates Finding
    - Add markDeviceDiscrepant(AuditDeviceVerification, User, notes) - marks as discrepant, creates Finding
  - [x] 2.3 Implement device scope query logic
    - Datacenter scope: query all devices via racks in datacenter
    - Room scope: query devices via racks in room
    - Specific racks: query devices via audit_racks pivot
    - Specific devices: query devices via audit_devices pivot
    - Include rack context (rack_id) for each device verification record
  - [x] 2.4 Implement empty rack detection and verification
    - Query racks in scope that have no devices
    - Create AuditRackVerification records for empty racks
    - Add markEmptyRackVerified(AuditRackVerification, User, notes) method
  - [x] 2.5 Implement device locking methods
    - Add lockDevice(AuditDeviceVerification, User) - mirrors lockConnection pattern
    - Add unlockDevice(AuditDeviceVerification) - mirrors unlockConnection pattern
    - Add releaseExpiredDeviceLocks(Audit) - batch release expired locks
  - [x] 2.6 Implement bulk verification for devices
    - Add bulkVerifyDevices(array verificationIds, User) - bulk verify pending devices
    - Skip locked devices and track skipped IDs
    - Return results: { verified: [], skipped_locked: [] }
  - [x] 2.7 Implement inventory progress statistics
    - Add getInventoryProgressStats(Audit) method
    - Return: total, verified, not_found, discrepant, pending, empty_racks_total, empty_racks_verified, progress_percentage
    - Include both device verifications and empty rack verifications in totals
  - [x] 2.8 Implement auto-creation of Findings for inventory discrepancies
    - Create Finding when device marked NotFound or Discrepant
    - Set Finding status to Open
    - Link to audit_device_verification_id
    - Generate descriptive title: "Device not found: {device_name}" or "Device discrepancy: {device_name}"
  - [x] 2.9 Update audit status transitions for inventory audits
    - Transition Pending -> InProgress on first verification
    - Transition InProgress -> Completed when all devices + empty racks verified
    - Reuse updateAuditStatusAfterVerification() pattern
  - [x] 2.10 Ensure service layer tests pass
    - Run ONLY the 8 tests written in 2.1
    - Verify all service methods function correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 8 tests written in 2.1 pass
- Device verification items are correctly pre-populated based on audit scope
- Empty racks are included for verification
- Findings are auto-created for NotFound and Discrepant statuses
- Progress stats accurately reflect verification state
- Audit status transitions correctly

---

### API Layer

#### Task Group 3: API Controllers and Broadcasting
**Dependencies:** Task Groups 1-2
**Complexity:** Medium

- [x] 3.0 Complete API layer for inventory audit execution
  - [x] 3.1 Write 6 focused tests for API endpoints
    - Test GET /api/audits/{audit}/device-verifications returns paginated list with filters
    - Test GET /api/audits/{audit}/device-verifications/stats returns progress statistics
    - Test POST /api/audits/{audit}/device-verifications/{verification}/verify marks device verified
    - Test POST /api/audits/{audit}/device-verifications/{verification}/not-found marks device not found
    - Test POST /api/audits/{audit}/device-verifications/{verification}/discrepant marks device discrepant
    - Test POST /api/audits/{audit}/device-verifications/bulk-verify bulk verifies devices
  - [x] 3.2 Create `AuditDeviceVerificationController` API controller
    - index(Audit) - list device verifications with filtering (room, rack, status, search)
    - stats(Audit) - return progress statistics
    - verify(Audit, AuditDeviceVerification, Request) - mark as verified
    - notFound(Audit, AuditDeviceVerification, Request) - mark as not found
    - discrepant(Audit, AuditDeviceVerification, Request) - mark as discrepant
    - bulkVerify(Audit, Request) - bulk verify multiple devices
    - lock(Audit, AuditDeviceVerification) - acquire lock
    - unlock(Audit, AuditDeviceVerification) - release lock
  - [x] 3.3 Create Form Request classes
    - VerifyDeviceRequest - validates notes (optional string)
    - DeviceNotFoundRequest - validates notes (required string)
    - DeviceDiscrepantRequest - validates notes (required string)
    - BulkVerifyDevicesRequest - validates verification_ids (required array of integers)
  - [x] 3.4 Create `AuditDeviceVerificationResource` API Resource
    - Include: id, device (id, name, asset_tag, serial_number), rack (id, name), room (id, name)
    - Include: verification_status, verification_status_label, notes
    - Include: verified_by (id, name), verified_at, locked_by (id, name), locked_at, is_locked
    - Follow AuditConnectionVerificationResource pattern
  - [x] 3.5 Create broadcasting events for real-time updates
    - DeviceLocked event (mirrors ConnectionLocked)
    - DeviceUnlocked event (mirrors ConnectionUnlocked)
    - DeviceVerificationCompleted event (mirrors VerificationCompleted)
    - Place in App\Events\AuditExecution\ namespace
    - Use same channel naming: `audit.{audit_id}`
  - [x] 3.6 Register API routes
    - Add routes to routes/api.php under audits/{audit}/device-verifications prefix
    - Apply appropriate middleware (auth:sanctum)
    - Name routes following existing pattern (api.audits.device-verifications.*)
  - [x] 3.7 Update AuditController for inventory audit execution entry
    - Ensure startExecution() works for inventory audit type
    - Ensure execute() action prepares device verifications on first access
    - Add can_start_audit and can_continue_audit props for inventory audits
  - [x] 3.8 Ensure API layer tests pass
    - Run ONLY the 6 tests written in 3.1
    - Verify all endpoints return correct responses
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6 tests written in 3.1 pass
- All API endpoints work correctly with proper authorization
- Broadcasting events fire for real-time updates
- API Resources return correctly formatted data
- Form validation prevents invalid input

---

### Frontend Components

#### Task Group 4: UI Components and Pages
**Dependencies:** Task Groups 1-3
**Complexity:** High

- [x] 4.0 Complete frontend UI for inventory audit execution
  - [x] 4.1 Write 6 focused tests for UI components
    - Test InventoryExecute.vue renders device list and progress stats
    - Test DeviceVerificationTable.vue displays devices grouped by rack
    - Test device filtering works (by room, rack, verification status, search)
    - Test bulk selection and bulk verify action
    - Test QrScannerModal.vue camera access and QR parsing
    - Test real-time updates via Echo channel subscription
  - [x] 4.2 Create `InventoryExecute.vue` page
    - Route: /audits/{audit}/inventory-execute
    - Layout: stats card at top, progress bar, filter controls, device table
    - Props: audit, progress_stats, rooms (for filtering), verification_statuses
    - Reuse patterns from Execute.vue (connection audit execution)
    - Add Echo channel subscription for real-time updates
  - [x] 4.3 Create `DeviceVerificationTable.vue` component
    - Display devices grouped by rack with collapsible sections
    - Columns: Device Name, Asset Tag, Rack/U Position, Status, Actions
    - Row highlighting based on verification status (green=verified, red=not found, yellow=discrepant)
    - Selection checkboxes for bulk actions
    - Show locked status with operator name
    - Rack headers with device count and per-rack progress
  - [x] 4.4 Create `EmptyRackVerificationSection.vue` component
    - Display empty racks requiring "nothing found here" confirmation
    - Simple verify button for each empty rack
    - Show within room/row context
  - [x] 4.5 Create `DeviceVerificationActionDialog.vue` component
    - Modal for verification actions (Verified, Not Found, Discrepant)
    - Notes input field (required for Not Found and Discrepant)
    - Display device details: name, asset tag, serial number, rack, U position
    - Loading state during API call
    - Error display on failure
  - [x] 4.6 Create `QrScannerModal.vue` component
    - Camera access via getUserMedia browser API
    - QR code detection and parsing
    - Extract device ID from URL format `/devices/{id}`
    - Emit scanned device ID to parent for navigation/scroll
    - Fallback message when camera unavailable
    - Close button and permission request handling
  - [x] 4.7 Create `DeviceSearchInput.vue` component
    - Search by device name, asset tag, or serial number
    - Debounced input for API efficiency
    - Emit search term to parent
  - [x] 4.8 Create `BulkVerifyDevicesButton.vue` component
    - Show count of selected devices eligible for bulk verify
    - Confirm dialog before bulk action
    - Display results: X verified, Y skipped (locked)
    - Follow BulkVerifyButton.vue pattern
  - [x] 4.9 Add filter controls to InventoryExecute.vue
    - Room filter dropdown (for datacenter-level audits)
    - Rack filter dropdown (filtered by selected room)
    - Verification status filter (Pending, Verified, Not Found, Discrepant)
    - Clear filters button
  - [x] 4.10 Implement real-time updates
    - Subscribe to `audit.{audit_id}` private channel
    - Handle `.device.verified` event - update device in list
    - Handle `.device.locked` event - show lock indicator
    - Handle `.device.unlocked` event - remove lock indicator
    - Update progress stats on verification events
  - [x] 4.11 Implement QR scanner integration
    - "Scan QR" button in toolbar
    - On scan, scroll to/highlight the scanned device in the list
    - If device not in current filter, clear filters and scroll to device
    - Toast notification showing scanned device name
  - [x] 4.12 Update Show.vue for inventory audit entry point
    - Add "Start Audit" button for inventory audits (status = Pending)
    - Add "Continue Audit" button for inventory audits (status = InProgress)
    - Link to InventoryExecute.vue route
    - Display inventory-specific progress stats
  - [x] 4.13 Add responsive design
    - Mobile-first layout for tablet use in datacenter
    - Collapsible rack groups for smaller screens
    - Touch-friendly tap targets for verification buttons
    - Fullscreen QR scanner modal on mobile
  - [x] 4.14 Ensure UI component tests pass
    - Run ONLY the 6 tests written in 4.1
    - Verify components render and interact correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6 tests written in 4.1 pass
- Device verification list displays correctly grouped by rack
- Filters work correctly (room, rack, status, search)
- QR scanning navigates to correct device
- Bulk verification works for pending devices
- Real-time updates reflect other operators' actions
- Responsive design works on tablets

---

### Testing

#### Task Group 5: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-4
**Complexity:** Low

- [x] 5.0 Review existing tests and fill critical gaps
  - [x] 5.1 Review tests from Task Groups 1-4
    - Review 6 tests from database-engineer (Task 1.1)
    - Review 8 tests from backend-service (Task 2.1)
    - Review 6 tests from api-engineer (Task 3.1)
    - Review 6 tests from ui-designer (Task 4.1)
    - Total existing tests: approximately 26 tests
  - [x] 5.2 Analyze test coverage gaps for THIS feature only
    - Identify critical user workflows lacking test coverage
    - Focus ONLY on gaps related to inventory audit execution
    - Prioritize end-to-end workflows over unit test gaps
  - [x] 5.3 Write up to 8 additional strategic tests to fill gaps
    - Integration test: Full workflow from start audit -> verify all devices -> audit completion
    - Integration test: QR scan -> device lookup -> verification flow
    - Test: Multi-operator concurrent verification with locking
    - Test: Progress stats accuracy with mixed verification states
    - Test: Finding auto-creation with correct audit/device linkage
    - Test: Empty rack verification included in progress calculation
    - Test: Audit status transitions (Pending -> InProgress -> Completed)
    - Test: Filter combinations (room + status + search)
  - [x] 5.4 Run feature-specific tests only
    - Run ONLY tests related to inventory audit execution
    - Expected total: approximately 34 tests maximum
    - Do NOT run the entire application test suite
    - Verify all critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 34 tests total)
- Critical user workflows for inventory audit execution are covered
- No more than 8 additional tests added to fill gaps
- Testing focused exclusively on this feature's requirements

---

## Execution Order

Recommended implementation sequence:

1. **Database Layer (Task Group 1)** - Foundation with models, migrations, and enums
2. **Backend Service Layer (Task Group 2)** - Business logic for verification workflow
3. **API Layer (Task Group 3)** - Controllers, resources, and broadcasting events
4. **Frontend Components (Task Group 4)** - UI pages and components with real-time updates
5. **Test Review & Gap Analysis (Task Group 5)** - Final verification and integration testing

## Technical Notes

### Existing Code to Reuse
- `AuditConnectionVerification` model structure for `AuditDeviceVerification`
- `AuditExecutionService` patterns for locking, verification, progress stats
- `Execute.vue` page layout and Echo subscription patterns
- Broadcasting event structure from `app/Events/AuditExecution/`
- `QrCodePdfService` URL format for QR parsing

### Key Differences from Connection Audit
- Verification items are devices, not connections
- Three status outcomes: Verified, Not Found, Discrepant (vs Verified, Discrepant)
- QR scanning navigates to device (no auto-verify)
- Empty racks need explicit "nothing found" confirmation
- Grouping by rack instead of comparison status

### Broadcasting Channel Structure
- Private channel: `audit.{audit_id}`
- Events: `.device.locked`, `.device.unlocked`, `.device.verified`
- Payload follows existing verification event patterns
