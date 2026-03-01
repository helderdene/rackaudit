# Task Breakdown: Discrepancy Detection Engine

## Overview
Total Tasks: 61 sub-tasks across 7 task groups

This feature implements an automated detection engine that continuously monitors and identifies discrepancies between expected connections (from approved implementation files) and actual documented connections. It provides real-time alerts and a centralized dashboard for tracking connection drift.

## Task List

### Database Layer

#### Task Group 1: Discrepancy Model and Database Schema
**Dependencies:** None
**Complexity:** Medium

- [x] 1.0 Complete database layer for discrepancies
  - [x] 1.1 Write 4-6 focused tests for Discrepancy model functionality
    - Test discrepancy creation with required fields
    - Test status transitions (open -> acknowledged -> resolved)
    - Test relationships (datacenter, room, implementation file, connections)
    - Test configuration mismatch JSON storage and retrieval
    - Test scope queries (by datacenter, by room, by status)
  - [x] 1.2 Add ConfigurationMismatch case to DiscrepancyType enum
    - Add new enum case: `ConfigurationMismatch = 'configuration_mismatch'`
    - Add label method for new case
    - Location: `app/Enums/DiscrepancyType.php`
  - [x] 1.3 Create DiscrepancyStatus enum
    - Cases: Open, Acknowledged, Resolved, InAudit
    - Include label() method for display
    - Location: `app/Enums/DiscrepancyStatus.php`
  - [x] 1.4 Create discrepancies table migration
    - Fields: id, datacenter_id, room_id (nullable), implementation_file_id (nullable)
    - Fields: discrepancy_type (enum), status (enum)
    - Fields: source_port_id, dest_port_id, connection_id (nullable), expected_connection_id (nullable)
    - Fields: expected_config (JSON), actual_config (JSON), mismatch_details (JSON)
    - Fields: title, description (text, nullable)
    - Fields: detected_at, acknowledged_at (nullable), acknowledged_by (nullable)
    - Fields: resolved_at (nullable), resolved_by (nullable)
    - Fields: audit_id (nullable - for linking to importing audit)
    - Fields: finding_id (nullable - for linking to resulting finding)
    - Indexes: datacenter_id, status, discrepancy_type, detected_at
    - Unique constraint: source_port_id + dest_port_id + discrepancy_type (for upsert logic)
  - [x] 1.5 Create Discrepancy model
    - Fillable fields matching migration
    - Casts: discrepancy_type -> DiscrepancyType, status -> DiscrepancyStatus
    - Casts: expected_config/actual_config/mismatch_details -> array
    - Casts: detected_at/acknowledged_at/resolved_at -> datetime
    - Follow pattern from `app/Models/Finding.php`
    - Location: `app/Models/Discrepancy.php`
  - [x] 1.6 Set up Discrepancy model relationships
    - belongsTo: datacenter, room, implementationFile, connection, expectedConnection
    - belongsTo: acknowledgedBy (User), resolvedBy (User), audit, finding
    - Relationship to sourcePort and destPort
  - [x] 1.7 Add query scopes to Discrepancy model
    - Scopes: open(), acknowledged(), resolved(), inAudit()
    - Scopes: forDatacenter($id), forRoom($id), forType($type)
    - Scopes: detectedBetween($start, $end)
  - [x] 1.8 Create DiscrepancyFactory for testing
    - Define default state with open status
    - States: acknowledged(), resolved(), inAudit()
    - States: missing(), unexpected(), mismatched(), conflicting(), configurationMismatch()
    - Location: `database/factories/DiscrepancyFactory.php`
  - [x] 1.9 Ensure database layer tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify migration runs successfully
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- Discrepancy model correctly stores and retrieves all fields
- Status transitions work correctly with timestamps
- JSON fields properly serialize/deserialize configuration data
- Relationships function correctly
- Query scopes filter appropriately

---

### Backend Services Layer

#### Task Group 2: Discrepancy Detection Service
**Dependencies:** Task Group 1
**Complexity:** High

- [x] 2.0 Complete DiscrepancyDetectionService
  - [x] 2.1 Write 6-8 focused tests for DiscrepancyDetectionService
    - Test detection for datacenter scope creates correct discrepancies
    - Test detection for room scope filters appropriately
    - Test detection for implementation file scope
    - Test upsert logic (updates existing, creates new)
    - Test configuration mismatch detection (cable_type, cable_length)
    - Test port type mismatch detection
    - Test resolved discrepancies marked when connections match
    - Test incremental detection based on last run timestamp
  - [x] 2.2 Create DiscrepancyDetectionService class
    - Inject ConnectionComparisonService as dependency
    - Location: `app/Services/DiscrepancyDetectionService.php`
  - [x] 2.3 Implement detectForDatacenter() method
    - Call ConnectionComparisonService::compareForDatacenter()
    - Convert ComparisonResult items to Discrepancy records
    - Use upsert logic: update if same source_port + dest_port + type exists
    - Return collection of created/updated discrepancies
  - [x] 2.4 Implement detectForRoom() method
    - Filter expected connections to specific room
    - Follow similar pattern to datacenter detection
    - Scope actual connections to room's devices
  - [x] 2.5 Implement detectForImplementationFile() method
    - Call ConnectionComparisonService::compareForImplementationFile()
    - Convert results to Discrepancy records
    - Link discrepancies to the implementation file
  - [x] 2.6 Implement configuration mismatch detection
    - Compare cable_type between expected and actual connections
    - Compare cable_length between expected and actual
    - Create ConfigurationMismatch discrepancy when properties differ
    - Store differences in mismatch_details JSON field
  - [x] 2.7 Implement port type mismatch detection
    - Compare source port types (expected vs actual)
    - Compare destination port types
    - Add to mismatch_details when types differ
  - [x] 2.8 Implement upsertDiscrepancy() private method
    - Find existing discrepancy by source_port + dest_port + type
    - Update detected_at if found, create new if not
    - Handle status transitions appropriately
  - [x] 2.9 Implement markResolvedDiscrepancies() method
    - Find open discrepancies for matched connections
    - Set status to Resolved, resolved_at to now
    - Called after successful detection run
  - [x] 2.10 Implement incrementalDetection() method
    - Accept optional last_run_at timestamp parameter
    - Query only connections/expected connections modified since last run
    - Optimize for large datacenters
  - [x] 2.11 Ensure DiscrepancyDetectionService tests pass
    - Run ONLY the 6-8 tests written in 2.1
    - Verify all detection scenarios work correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6-8 tests written in 2.1 pass
- Service wraps ConnectionComparisonService correctly
- All discrepancy types detected and persisted
- Configuration mismatches properly identified and stored
- Upsert logic prevents duplicate discrepancies
- Resolved discrepancies marked appropriately

---

### Events and Jobs Layer

#### Task Group 3: Event-Driven and Scheduled Detection
**Dependencies:** Task Group 2
**Complexity:** Medium-High

- [x] 3.0 Complete event-driven and scheduled detection
  - [x] 3.1 Write 5-7 focused tests for events, listeners, and jobs
    - Test Connection created event triggers limited detection
    - Test Connection updated event triggers detection for affected ports
    - Test ImplementationFile approved event triggers full file detection
    - Test ExpectedConnection confirmed event triggers detection
    - Test DetectDiscrepanciesJob runs detection for correct scope
    - Test scheduled job configuration works correctly
  - [x] 3.2 Create/extend Connection model events
    - Dispatch event on created, updated, deleted
    - Include connection data and affected port IDs
    - Location: `app/Events/ConnectionChanged.php` (if not exists)
  - [x] 3.3 Create ImplementationFileApproved event
    - Include implementation file data
    - Dispatch when approval_status changes to 'approved'
    - Location: `app/Events/ImplementationFileApproved.php`
  - [x] 3.4 Create ExpectedConnectionConfirmed event
    - Dispatch when expected connection is confirmed
    - Include expected connection data
    - Location: `app/Events/ExpectedConnectionConfirmed.php`
  - [x] 3.5 Create DetectDiscrepanciesForConnection listener
    - Listen for ConnectionChanged event
    - Queue the detection job for affected connection only
    - Limit scope to prevent full datacenter rescan
    - Location: `app/Listeners/DetectDiscrepanciesForConnection.php`
  - [x] 3.6 Create DetectDiscrepanciesForImplementationFile listener
    - Listen for ImplementationFileApproved event
    - Queue detection for all connections in that file
    - Location: `app/Listeners/DetectDiscrepanciesForImplementationFile.php`
  - [x] 3.7 Create DetectDiscrepanciesForExpectedConnection listener
    - Listen for ExpectedConnectionConfirmed event
    - Queue detection for that specific connection pair
    - Location: `app/Listeners/DetectDiscrepanciesForExpectedConnection.php`
  - [x] 3.8 Create DetectDiscrepanciesJob queued job
    - Accepts scope parameters: datacenter_id, room_id, implementation_file_id
    - Calls DiscrepancyDetectionService with appropriate method
    - Implements ShouldQueue interface
    - Location: `app/Jobs/DetectDiscrepanciesJob.php`
  - [x] 3.9 Register event listeners in EventServiceProvider
    - Map events to their listeners
    - Ensure listeners are queued
  - [x] 3.10 Configure scheduled detection in routes/console.php
    - Schedule DetectDiscrepanciesJob nightly at 2:00 AM
    - Make schedule time configurable via config file
    - Add config: `config/discrepancies.php` with schedule settings
  - [x] 3.11 Ensure events and jobs tests pass
    - Run ONLY the 5-7 tests written in 3.1
    - Verify event dispatching works
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 5-7 tests written in 3.1 pass
- Events dispatch correctly on model changes
- Listeners queue appropriate detection jobs
- Detection scoped to affected connections only
- Scheduled job runs at configured time
- No blocking of main request cycle

---

### Notification Layer

#### Task Group 4: Notification System
**Dependencies:** Task Group 3
**Complexity:** Medium

- [x] 4.0 Complete notification system
  - [x] 4.1 Write 4-6 focused tests for notifications
    - Test NewDiscrepancyNotification sends to IT Managers
    - Test DiscrepancyThresholdNotification sends when threshold exceeded
    - Test Operators only receive notifications if subscribed
    - Test notification channels (database and mail)
    - Test notification content includes correct discrepancy details
  - [x] 4.2 Create NewDiscrepancyNotification class
    - Implement Notifiable interface
    - Channels: database, mail
    - Include discrepancy type, source/dest details, datacenter
    - Location: `app/Notifications/NewDiscrepancyNotification.php`
  - [x] 4.3 Create DiscrepancyThresholdNotification class
    - Triggered when discrepancy count exceeds configurable threshold
    - Include count by type, datacenter summary
    - Channels: database, mail
    - Location: `app/Notifications/DiscrepancyThresholdNotification.php`
  - [x] 4.4 Add notification preferences to User model or settings
    - Add discrepancy_notifications preference field
    - Options: all, threshold_only, none
    - Operators default to 'none', IT Managers to 'all'
  - [x] 4.5 Create NotifyUsersOfDiscrepancies job
    - Query users by role and datacenter access
    - Apply notification preference filtering
    - Send appropriate notifications
    - Location: `app/Jobs/NotifyUsersOfDiscrepancies.php`
  - [x] 4.6 Add threshold configuration to config/discrepancies.php
    - discrepancy_threshold: default 10
    - notification_roles: ['it_manager', 'auditor']
  - [x] 4.7 Integrate notification dispatch into detection flow
    - Dispatch NotifyUsersOfDiscrepancies after detection completes
    - Pass newly created discrepancies
  - [x] 4.8 Ensure notification tests pass
    - Run ONLY the 4-6 tests written in 4.1
    - Verify notifications sent to correct users
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 4.1 pass
- IT Managers receive all new discrepancy notifications
- Auditors receive threshold-based notifications
- Operators only notified when subscribed
- Database and mail channels work correctly
- Notification content is accurate and useful

---

### API Layer

#### Task Group 5: API Endpoints
**Dependencies:** Task Groups 1-4
**Complexity:** Medium

- [x] 5.0 Complete API layer for discrepancies
  - [x] 5.1 Write 5-7 focused tests for API endpoints
    - Test GET /api/discrepancies returns paginated list with filters
    - Test GET /api/discrepancies/{id} returns single discrepancy
    - Test PATCH /api/discrepancies/{id}/acknowledge updates status
    - Test PATCH /api/discrepancies/{id}/resolve updates status
    - Test POST /api/discrepancies/detect triggers on-demand detection
    - Test authorization checks for each endpoint
  - [x] 5.2 Create DiscrepancyController
    - Actions: index, show, acknowledge, resolve, detect
    - Follow pattern from existing controllers
    - Location: `app/Http/Controllers/Api/DiscrepancyController.php`
  - [x] 5.3 Implement index action with filtering
    - Filter by: discrepancy_type, datacenter_id, room_id, status, date_range
    - Sorting: type, datacenter, detected_at
    - Pagination with configurable per_page
    - Eager load relationships for performance
  - [x] 5.4 Implement show action
    - Return full discrepancy details
    - Include related connection and expected connection data
    - Include configuration mismatch details
  - [x] 5.5 Implement acknowledge action
    - Update status to Acknowledged
    - Set acknowledged_at and acknowledged_by
    - Validate user has permission
  - [x] 5.6 Implement resolve action
    - Update status to Resolved
    - Set resolved_at and resolved_by
    - Validate user has permission
  - [x] 5.7 Implement detect action for on-demand detection
    - Accept scope parameters: datacenter_id, room_id, implementation_file_id
    - Dispatch DetectDiscrepanciesJob
    - Return job status or progress indicator
  - [x] 5.8 Create DiscrepancyResource for API responses
    - Transform discrepancy data for frontend
    - Include nested port, device, datacenter information
    - Location: `app/Http/Resources/DiscrepancyResource.php`
  - [x] 5.9 Create DiscrepancySummaryResource for dashboard stats
    - Aggregate counts by type, by datacenter
    - Location: `app/Http/Resources/DiscrepancySummaryResource.php`
  - [x] 5.10 Register API routes in routes/api.php
    - Route::apiResource('discrepancies', DiscrepancyController::class)
    - Additional routes for acknowledge, resolve, detect actions
  - [x] 5.11 Ensure API tests pass
    - Run ONLY the 5-7 tests written in 5.1
    - Verify all endpoints respond correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 5-7 tests written in 5.1 pass
- All CRUD-like operations work correctly
- Filtering and sorting work as expected
- On-demand detection triggers successfully
- Proper authorization enforced
- Consistent response format using resources

---

### Frontend Layer

#### Task Group 6: Discrepancy Dashboard and UI Components
**Dependencies:** Task Group 5
**Complexity:** High

- [x] 6.0 Complete frontend components and pages
  - [x] 6.1 Write 4-6 focused tests for UI components
    - Test discrepancy index page renders with data
    - Test filter controls update query parameters
    - Test "Run Detection Now" button triggers detection
    - Test datacenter summary widget displays counts
    - Test discrepancy detail modal shows correct information
  - [x] 6.2 Create Discrepancy index page
    - Route: `/discrepancies`
    - Display filterable, sortable table of discrepancies
    - Columns: type (badge), source device/port, dest device/port, datacenter, detected_at, status
    - Include empty state when no discrepancies
    - Location: `resources/js/Pages/Discrepancies/Index.vue`
  - [x] 6.3 Create DiscrepancyFilters component
    - Filter controls: discrepancy_type, datacenter, room, status, date range
    - Persist filters in URL query parameters
    - Clear filters button
    - Location: `resources/js/Components/Discrepancies/DiscrepancyFilters.vue`
  - [x] 6.4 Create DiscrepancyTable component
    - Sortable columns with visual indicators
    - Row click opens detail view
    - Status badges with appropriate colors
    - Pagination controls
    - Location: `resources/js/Components/Discrepancies/DiscrepancyTable.vue`
  - [x] 6.5 Create DiscrepancySummaryStats component
    - Display at top of index page
    - Show counts by type (missing, unexpected, mismatched, conflicting, config_mismatch)
    - Show counts by datacenter
    - Clickable to filter by that type/datacenter
    - Location: `resources/js/Components/Discrepancies/DiscrepancySummaryStats.vue`
  - [x] 6.6 Create DiscrepancyDetailModal component
    - Show full discrepancy details
    - Display expected vs actual comparison
    - Show configuration mismatch details if applicable
    - Action buttons: Acknowledge, Resolve
    - Location: `resources/js/Components/Discrepancies/DiscrepancyDetailModal.vue`
  - [x] 6.7 Create RunDetectionButton component
    - "Run Detection Now" button
    - Scope selector dropdown (datacenter, room, or all)
    - Loading/progress indicator during detection
    - Success/error feedback
    - Location: `resources/js/Components/Discrepancies/RunDetectionButton.vue`
  - [x] 6.8 Create DiscrepancyController for Inertia pages
    - index(): Return Inertia page with discrepancies, filters, summary
    - Location: `app/Http/Controllers/DiscrepancyController.php`
  - [x] 6.9 Create DatacenterDiscrepancyWidget component
    - Summary widget for datacenter detail page
    - Show counts by discrepancy type
    - Visual indicator (badge) when discrepancies exist
    - Link to filtered discrepancy dashboard
    - Location: `resources/js/Components/Datacenters/DiscrepancyWidget.vue`
  - [x] 6.10 Integrate widget into Datacenter detail page
    - Add DiscrepancyWidget to existing datacenter detail page
    - Position appropriately in layout
    - Fetch discrepancy counts via deferred prop or API
  - [x] 6.11 Register routes for Discrepancy pages
    - Route::get('/discrepancies', [DiscrepancyController::class, 'index'])
    - Add to navigation menu
  - [x] 6.12 Apply responsive design
    - Mobile: Stacked layout for table, collapsible filters
    - Tablet/Desktop: Full table with sidebar filters
    - Tailwind responsive prefixes (sm:, md:, lg:)
  - [x] 6.13 Ensure frontend tests pass
    - Run ONLY the 4-6 tests written in 6.1
    - Verify components render correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 6.1 pass
- Dashboard displays discrepancies with all filters working
- Sorting works correctly on all columns
- On-demand detection triggers and shows progress
- Datacenter widget shows accurate counts
- Responsive design works across breakpoints
- Matches existing application design patterns

---

### Integration Layer

#### Task Group 7: Audit Workflow Integration
**Dependencies:** Task Groups 5-6
**Complexity:** Medium

- [x] 7.0 Complete audit workflow integration
  - [x] 7.1 Write 4-6 focused tests for audit integration
    - Test importing discrepancies as verification items
    - Test checkbox selection for import
    - Test "in_audit" status prevents duplicate imports
    - Test discrepancy links to finding when verification confirms issue
    - Test auto-resolve when audit finding is resolved
  - [x] 7.2 Add import discrepancies UI to audit creation flow
    - Show available discrepancies when creating audit
    - Checkbox selection for which to import
    - Filter to datacenter/room scope of audit
    - Location: Enhance existing audit creation page
  - [x] 7.3 Create ImportDiscrepanciesRequest form request
    - Validate selected discrepancy IDs
    - Validate discrepancies are in correct scope
    - Validate discrepancies are not already in_audit
    - Location: `app/Http/Requests/ImportDiscrepanciesRequest.php`
  - [x] 7.4 Extend AuditExecutionService for discrepancy import
    - Add importDiscrepanciesAsVerificationItems() method
    - Create AuditConnectionVerification from discrepancy data
    - Mark discrepancy status as InAudit
    - Link discrepancy to audit
  - [x] 7.5 Add discrepancy linking to finding creation
    - When verification confirms discrepancy, link finding to discrepancy
    - Update discrepancy.finding_id when Finding created
  - [x] 7.6 Add auto-resolve listener for finding resolution
    - Listen for Finding resolved event
    - If finding has linked discrepancy, resolve discrepancy
    - Set resolved_by to same user who resolved finding
    - Location: `app/Listeners/ResolveLinkedDiscrepancy.php`
  - [x] 7.7 Update Discrepancy API to support bulk status check
    - Endpoint to check multiple discrepancies' audit status
    - Used by frontend to show import availability
  - [x] 7.8 Ensure integration tests pass
    - Run ONLY the 4-6 tests written in 7.1
    - Verify end-to-end workflow works
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 7.1 pass
- Discrepancies can be imported as verification items
- InAudit status prevents duplicate imports
- Discrepancies link to resulting findings
- Auto-resolve works when findings are resolved
- UI provides clear selection and feedback

---

### Testing Layer

#### Task Group 8: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-7
**Complexity:** Low-Medium

- [x] 8.0 Review existing tests and fill critical gaps only
  - [x] 8.1 Review tests from Task Groups 1-7
    - Review the 4-6 tests written in Task 1.1 (database layer) - 6 tests in DiscrepancyModelTest.php
    - Review the 6-8 tests written in Task 2.1 (detection service) - 8 tests in DiscrepancyDetectionServiceTest.php
    - Review the 5-7 tests written in Task 3.1 (events/jobs) - 7 tests in DiscrepancyEventsJobsTest.php
    - Review the 4-6 tests written in Task 4.1 (notifications) - 6 tests in DiscrepancyNotificationsTest.php
    - Review the 5-7 tests written in Task 5.1 (API) - 7 tests in DiscrepancyApiTest.php
    - Review the 4-6 tests written in Task 6.1 (frontend) - 6 tests in DiscrepancyUITest.php
    - Review the 4-6 tests written in Task 7.1 (audit integration) - 6 tests in DiscrepancyAuditIntegrationTest.php
    - Total existing tests: 46 tests
  - [x] 8.2 Analyze test coverage gaps for THIS feature only
    - Identified critical user workflows lacking coverage:
      - Full detection flow from connection change to dashboard update
      - Full flow from detection to notification delivery
      - Complete audit lifecycle: import, verify, resolve
      - Scheduled job creates discrepancies and dispatches notifications
      - Real-time detection on connection events
    - Focus ONLY on discrepancy detection feature requirements
    - Do NOT assess entire application test coverage
    - Prioritize end-to-end workflows over unit test gaps
  - [x] 8.3 Write up to 10 additional strategic tests maximum
    - Created DiscrepancyEndToEndTest.php with 10 tests:
      1. End-to-end: Full detection flow from connection change to dashboard update
      2. End-to-end: Full flow from detection to notification delivery
      3. End-to-end: Import to audit, verify, resolve discrepancy (audit lifecycle)
      4. Integration: Scheduled job runs and creates discrepancies
      5. Integration: Real-time detection on connection events
      6. E2E: Discrepancy status lifecycle via API
      7. E2E: Dashboard displays correct summary statistics
      8. E2E: Filter discrepancies by datacenter on dashboard
      9. E2E: Resolved discrepancies when connection matches
      10. E2E: InAudit status prevents duplicate import
    - Focus on integration points between services
    - Skip edge cases and exhaustive scenario testing
  - [x] 8.4 Run feature-specific tests only
    - Run ONLY tests related to discrepancy detection feature
    - Total: 56 tests across all discrepancy test files (46 original + 10 new)
    - All 74 discrepancy-related tests pass (includes related tests from other features)
    - Do NOT run the entire application test suite
    - Verify all critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (56 tests total in discrepancy test files)
- Critical user workflows for discrepancy detection are covered
- No more than 10 additional tests added when filling gaps (exactly 10 added)
- Testing focused exclusively on this spec's feature requirements

---

## Execution Order

Recommended implementation sequence:

1. **Database Layer (Task Group 1)** - Foundation models and migrations
2. **Backend Services Layer (Task Group 2)** - Core detection logic
3. **Events and Jobs Layer (Task Group 3)** - Event-driven architecture
4. **Notification Layer (Task Group 4)** - User alerting system
5. **API Layer (Task Group 5)** - REST endpoints
6. **Frontend Layer (Task Group 6)** - Dashboard and UI
7. **Integration Layer (Task Group 7)** - Audit workflow connection
8. **Testing Layer (Task Group 8)** - Final gap analysis

## Key Files Reference

### Existing Code to Leverage
- `app/Services/ConnectionComparisonService.php` - Core comparison logic (wrap, don't duplicate)
- `app/DTOs/ComparisonResult.php` - Result structure patterns
- `app/Enums/DiscrepancyType.php` - Existing enum to extend
- `app/Models/Finding.php` - Model pattern to follow
- `app/Services/AuditExecutionService.php` - Verification import pattern

### New Files to Create
- `app/Models/Discrepancy.php`
- `app/Enums/DiscrepancyStatus.php`
- `app/Services/DiscrepancyDetectionService.php`
- `app/Jobs/DetectDiscrepanciesJob.php`
- `app/Notifications/NewDiscrepancyNotification.php`
- `app/Notifications/DiscrepancyThresholdNotification.php`
- `app/Http/Controllers/Api/DiscrepancyController.php`
- `app/Http/Controllers/DiscrepancyController.php`
- `resources/js/Pages/Discrepancies/Index.vue`
- `resources/js/Components/Discrepancies/*.vue`
- `resources/js/Components/Datacenters/DiscrepancyWidget.vue`
- `config/discrepancies.php`
- `database/migrations/*_create_discrepancies_table.php`
- Various event, listener, and test files

## Configuration Requirements

### config/discrepancies.php
```php
return [
    'schedule' => [
        'enabled' => true,
        'time' => '02:00',
        'timezone' => config('app.timezone'),
    ],
    'notifications' => [
        'threshold' => 10,
        'roles' => ['it_manager', 'auditor'],
    ],
];
```
