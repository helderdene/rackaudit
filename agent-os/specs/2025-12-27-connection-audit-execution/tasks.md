# Task Breakdown: Connection Audit Execution

## Overview
Total Tasks: 43 sub-tasks across 6 task groups

This feature enables operators to execute connection audits by verifying documented connections against implementation specs, marking each as verified or discrepant with notes, and tracking progress across multiple operators working simultaneously.

## Task List

### Database Layer

#### Task Group 1: Verification Data Models and Migrations
**Dependencies:** None
**Complexity:** Medium

- [x] 1.0 Complete database layer for audit connection verifications
  - [x] 1.1 Write 4-6 focused tests for AuditConnectionVerification model
    - Test verification status transitions (pending -> verified/discrepant)
    - Test required notes validation for discrepant status
    - Test relationship to Audit model
    - Test relationship to User (verified_by)
    - Test lock expiration logic
  - [x] 1.2 Create migration for `audit_connection_verifications` table
    - Fields: id, audit_id, expected_connection_id (nullable), connection_id (nullable), discrepancy_type (enum), comparison_status (matched/missing/unexpected/mismatched/conflicting), verification_status (pending/verified/discrepant), notes (text, nullable), verified_by (user_id, nullable), verified_at (timestamp, nullable), locked_by (user_id, nullable), locked_at (timestamp, nullable)
    - Add foreign keys: audit_id -> audits, expected_connection_id -> expected_connections (nullable), connection_id -> connections (nullable), verified_by -> users (nullable), locked_by -> users (nullable)
    - Add indexes for: audit_id, verification_status, locked_by, discrepancy_type
    - Add composite unique index on (audit_id, expected_connection_id, connection_id) to prevent duplicates
  - [x] 1.3 Create `AuditConnectionVerification` model with relationships and casts
    - Relationships: belongsTo Audit, ExpectedConnection (nullable), Connection (nullable), User (verified_by), User (locked_by)
    - Casts: discrepancy_type -> DiscrepancyType enum, verification_status -> VerificationStatus enum (new), comparison_status -> DiscrepancyType enum, verified_at/locked_at -> datetime
    - Scopes: pending(), verified(), discrepant(), locked(), expired locks (5 min)
    - Methods: isLocked(), isLockedBy(User), lockFor(User), unlock(), markVerified(User, notes), markDiscrepant(User, DiscrepancyType, notes)
  - [x] 1.4 Create `VerificationStatus` enum
    - Cases: Pending, Verified, Discrepant
    - Add label() method
  - [x] 1.5 Add relationship to Audit model
    - Audit hasMany AuditConnectionVerification (verifications)
    - Add helper methods: totalVerifications(), completedVerifications(), pendingVerifications()
  - [x] 1.6 Ensure database layer tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify migration runs successfully

**Acceptance Criteria:**
- The 4-6 tests pass
- Migration creates table with correct indexes and foreign keys
- Model relationships work bidirectionally
- Lock expiration logic correctly identifies stale locks (> 5 minutes)
- VerificationStatus enum properly casts

---

#### Task Group 2: Finding Model Placeholder (Integration Point)
**Dependencies:** Task Group 1
**Complexity:** Low

- [x] 2.0 Create placeholder Finding model for discrepancy auto-creation
  - [x] 2.1 Write 2-3 focused tests for Finding model
    - Test Finding creation with required fields
    - Test relationship to AuditConnectionVerification
    - Test status defaults to 'open'
  - [x] 2.2 Create migration for `findings` table
    - Fields: id, audit_id, audit_connection_verification_id, discrepancy_type, title, description, status (open/resolved), resolved_by (nullable), resolved_at (nullable), timestamps
    - Add foreign keys and indexes
  - [x] 2.3 Create `Finding` model
    - Relationships: belongsTo Audit, AuditConnectionVerification, User (resolved_by)
    - Casts: discrepancy_type -> DiscrepancyType, status -> FindingStatus enum
  - [x] 2.4 Create `FindingStatus` enum
    - Cases: Open, Resolved
  - [x] 2.5 Add inverse relationship on AuditConnectionVerification
    - AuditConnectionVerification hasOne Finding
  - [x] 2.6 Ensure Finding tests pass

**Acceptance Criteria:**
- Finding model can be created when verification marked as discrepant
- Relationship between verification and finding works
- This is a placeholder - full Finding CRUD is out of scope

---

### Backend Services

#### Task Group 3: Audit Execution Service and Controllers
**Dependencies:** Task Groups 1-2
**Complexity:** High

- [x] 3.0 Complete backend service layer for audit execution
  - [x] 3.1 Write 6-8 focused tests for AuditExecutionService and controllers
    - Test pre-population of verifications from ConnectionComparisonService
    - Test verification status update (verify/discrepant)
    - Test connection locking and unlocking
    - Test auto-status transition (Pending -> InProgress on first verification)
    - Test auto-completion (all verified -> Completed)
    - Test bulk verification for matched connections only
    - Test Finding auto-creation on discrepant marking
  - [x] 3.2 Create `AuditExecutionService` class
    - Method: `prepareVerificationItems(Audit $audit): void` - generates AuditConnectionVerification records from ConnectionComparisonService
    - Method: `getVerificationItems(Audit $audit, array $filters = []): LengthAwarePaginator` - retrieves verification items with filtering/sorting
    - Method: `lockConnection(AuditConnectionVerification $verification, User $user): bool`
    - Method: `unlockConnection(AuditConnectionVerification $verification): void`
    - Method: `releaseExpiredLocks(Audit $audit): int` - releases locks older than 5 minutes
    - Method: `markVerified(AuditConnectionVerification $verification, User $user, ?string $notes = null): void`
    - Method: `markDiscrepant(AuditConnectionVerification $verification, User $user, DiscrepancyType $type, string $notes): void`
    - Method: `bulkVerify(array $verificationIds, User $user): array` - returns results with skipped locked items
    - Method: `getProgressStats(Audit $audit): array` - returns verified/discrepant/pending counts
    - Use ConnectionComparisonService based on audit scope_type (datacenter or implementation_file)
  - [x] 3.3 Create `AuditConnectionVerificationController` (API)
    - `index(Audit $audit)` - list verifications with filtering, sorting, search
    - `show(Audit $audit, AuditConnectionVerification $verification)` - single verification details
    - `verify(Request $request, Audit $audit, AuditConnectionVerification $verification)` - mark as verified
    - `discrepant(Request $request, Audit $audit, AuditConnectionVerification $verification)` - mark as discrepant
    - `lock(Audit $audit, AuditConnectionVerification $verification)` - acquire lock
    - `unlock(Audit $audit, AuditConnectionVerification $verification)` - release lock
    - `bulkVerify(Request $request, Audit $audit)` - bulk verify matched connections
  - [x] 3.4 Create Form Request classes for validation
    - `VerifyConnectionRequest` - notes (optional string)
    - `MarkDiscrepantRequest` - discrepancy_type (required, valid enum), notes (required string)
    - `BulkVerifyRequest` - verification_ids (required array of existing IDs)
  - [x] 3.5 Create `AuditConnectionVerificationResource` (API Resource)
    - Include: id, comparison_status, verification_status, source_device, source_port, dest_device, dest_port, expected_connection, actual_connection, notes, verified_by, verified_at, locked_by, locked_at, is_locked, row_number (from expected connection)
  - [x] 3.6 Update `AuditController` to add execution entry points
    - Modify `show()` to include can_start_audit and can_continue_audit flags
    - Add `startExecution(Audit $audit)` action - transitions status to InProgress, prepares verifications if needed, redirects to execution page
  - [x] 3.7 Register API routes for verification endpoints
    - Nested under /api/audits/{audit}/verifications
    - Routes: index, show, verify, discrepant, lock, unlock, bulk-verify
  - [x] 3.8 Ensure backend service tests pass

**Acceptance Criteria:**
- The 6-8 tests pass
- ConnectionComparisonService correctly used based on audit scope
- Verification items correctly pre-populated from comparison results
- Lock mechanism prevents concurrent verification of same connection
- Status transitions work automatically
- Finding auto-created when marking discrepant

---

### Real-Time Features

#### Task Group 4: Broadcasting and Real-Time Updates
**Dependencies:** Task Group 3
**Complexity:** Medium

- [x] 4.0 Implement real-time updates for multi-operator support
  - [x] 4.1 Write 3-4 focused tests for broadcasting
    - Test VerificationCompleted event broadcasts correctly
    - Test ConnectionLocked event broadcasts correctly
    - Test ConnectionUnlocked event broadcasts correctly
    - Test channel authorization for audit assignees
  - [x] 4.2 Create broadcast events
    - `VerificationCompleted` - broadcasts when a verification is marked verified/discrepant
    - `ConnectionLocked` - broadcasts when a connection is locked by an operator
    - `ConnectionUnlocked` - broadcasts when a connection is unlocked
    - All events implement ShouldBroadcast, broadcast to private channel `audit.{audit_id}`
  - [x] 4.3 Create broadcast channel authorization
    - Define private channel `audit.{audit_id}` in routes/channels.php
    - Authorize if user is an assignee of the audit or audit creator
  - [x] 4.4 Dispatch events from AuditExecutionService
    - Dispatch VerificationCompleted after markVerified/markDiscrepant/bulkVerify
    - Dispatch ConnectionLocked after lockConnection
    - Dispatch ConnectionUnlocked after unlockConnection or releaseExpiredLocks
  - [x] 4.5 Ensure broadcasting tests pass

**Acceptance Criteria:**
- Events broadcast to correct channels
- Only authorized users can subscribe to audit channels
- Events contain necessary data for frontend updates

---

### Frontend Components

#### Task Group 5: Audit Execution UI
**Dependencies:** Task Groups 3-4
**Complexity:** High

- [x] 5.0 Complete frontend components for audit execution
  - [x] 5.1 Write 4-6 focused tests for UI components
    - Test verification table renders with correct data
    - Test bulk verification action works for matched connections
    - Test marking individual connection as verified
    - Test marking connection as discrepant with required notes
    - Test real-time updates from Echo
  - [x] 5.2 Create `Execute.vue` page in `resources/js/Pages/Audits/`
    - Page layout following Review.vue pattern
    - Header with audit name, status badge, "Back to Audit" button
    - Statistics card showing progress (verified/discrepant/pending counts)
    - Progress bar with percentage complete
    - Filter controls (discrepancy type, verification status, device search)
    - Main verification table component
    - Real-time connection to Laravel Echo for audit channel
  - [x] 5.3 Create `ConnectionVerificationTable.vue` component
    - Reuse patterns from ConnectionReviewTable.vue
    - Columns: checkbox, row#, source device/port, dest device/port, comparison result, verification status, actions
    - Row highlighting based on discrepancy type (green=matched, yellow=mismatched, red=missing/unexpected, purple=conflicting)
    - Show lock indicator with operator name when locked by another user
    - Disable actions on locked rows
    - Selection checkboxes for bulk operations
  - [x] 5.4 Create `VerificationActionDialog.vue` component
    - Dialog for marking verification verified or discrepant
    - Radio buttons: Verified / Discrepant
    - When discrepant: show DiscrepancyType dropdown (required)
    - Notes textarea (required for discrepant, optional for verified)
    - Submit and Cancel buttons
    - Loading state during submission
  - [x] 5.5 Create `BulkVerifyButton.vue` component
    - Button to bulk verify selected matched connections
    - Shows count of selected items
    - Confirmation dialog before action
    - Handles skipped locked connections with notification
  - [x] 5.6 Implement real-time updates with Echo
    - Subscribe to `audit.{id}` private channel on mount
    - Handle VerificationCompleted - update row in table, refresh stats
    - Handle ConnectionLocked - show lock indicator on row
    - Handle ConnectionUnlocked - remove lock indicator
    - Unsubscribe on unmount
  - [x] 5.7 Add filter and sort controls
    - Filter by discrepancy type (Matched, Missing, Unexpected, Mismatched, Conflicting)
    - Filter by verification status (Pending, Verified, Discrepant)
    - Search by device name or port label
    - Sort by row number, device name, or discrepancy type
  - [x] 5.8 Update `Show.vue` to add execution buttons
    - Add "Start Audit" button when status is Pending and type is connection
    - Add "Continue Audit" button when status is InProgress
    - Buttons navigate to the Execute.vue page
  - [x] 5.9 Register frontend routes
    - Add route for /audits/{id}/execute in routes/web.php
    - Create controller method to render Execute.vue with Inertia
  - [x] 5.10 Ensure frontend tests pass

**Acceptance Criteria:**
- Execution page displays verification items from API
- Bulk verify only allows matched connections
- Real-time updates show other operators' actions
- Locked connections display lock indicator
- Notes required for discrepant marking
- Progress accurately reflects completion state

---

### Testing & Integration

#### Task Group 6: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-5
**Complexity:** Medium

- [x] 6.0 Review existing tests and fill critical gaps
  - [x] 6.1 Review tests from Task Groups 1-5
    - Review database layer tests (Task 1.1) - 9 tests
    - Review Finding model tests (Task 2.1) - 4 tests
    - Review backend service tests (Task 3.1) - 7 tests
    - Review broadcasting tests (Task 4.1) - 6 tests
    - Review frontend tests (Task 5.1) - 6 tests
    - Total existing: 33 tests (exceeded initial estimate of ~24)
  - [x] 6.2 Analyze test coverage gaps for this feature
    - Identify end-to-end workflow gaps
    - Check integration between ConnectionComparisonService and AuditExecutionService
    - Verify status transition edge cases covered
    - Check multi-operator concurrency scenarios
  - [x] 6.3 Write up to 8 additional strategic tests
    - End-to-end: Complete audit from Pending through all verifications to Completed status
    - Integration: Verify ConnectionComparisonService output correctly maps to verifications (datacenter scope)
    - Integration: Verify ConnectionComparisonService output correctly maps to verifications (implementation file scope)
    - Concurrency: Two operators cannot verify same connection simultaneously
    - Edge case: Partial bulk verify when some connections locked
    - Edge case: Expired lock release functionality
    - Edge case: Verification items not duplicated on repeat prepareVerificationItems call
    - Integration: Finding auto-created with correct discrepancy type
  - [x] 6.4 Run feature-specific tests only
    - Run all tests related to this feature
    - Final total: 41 tests (33 existing + 8 new)
    - All critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (41 tests total)
- End-to-end workflow from start to completion tested
- Multi-operator scenarios covered
- Integration with existing services verified
- 8 additional tests added (at limit)

---

## Execution Order

Recommended implementation sequence:

1. **Task Group 1: Verification Data Models** - Foundation for storing verification data
2. **Task Group 2: Finding Model Placeholder** - Required for discrepancy auto-creation
3. **Task Group 3: Backend Services** - Core business logic and API endpoints
4. **Task Group 4: Broadcasting** - Real-time multi-operator support
5. **Task Group 5: Frontend UI** - User interface for audit execution
6. **Task Group 6: Testing** - Final test review and gap filling

## Key Integration Points

### Existing Code to Leverage
- **ConnectionComparisonService** (`app/Services/ConnectionComparisonService.php`)
  - `compareForImplementationFile()` for file-scoped audits
  - `compareForDatacenter()` for datacenter-scoped audits
- **DiscrepancyType enum** (`app/Enums/DiscrepancyType.php`)
  - Matched, Missing, Unexpected, Mismatched, Conflicting
- **DiscrepancyAcknowledgment model** - Pattern reference for verification records
- **ConnectionReviewTable.vue** - UI patterns for table, bulk actions, row highlighting
- **Review.vue** - Page layout patterns for statistics card and progress tracking

### Status Transitions
- Audit: `Pending` -> `InProgress` (on first verification) -> `Completed` (all verified)
- Verification: `Pending` -> `Verified` or `Discrepant`

### Locking Mechanism
- Soft lock with 5-minute expiration
- Lock stored in `audit_connection_verifications` table (locked_by, locked_at)
- Expired locks automatically released by scheduled task or on-demand

## Notes

- Finding Management CRUD is out of scope - only auto-creation on discrepant marking
- Only connection audits are in scope - inventory audit execution is separate
- No editing/deleting verifications once recorded
- Audit editing/cancellation workflows are out of scope
