# Task Breakdown: Expected vs Actual Comparison View

## Overview
Total Tasks: 45

This feature implements a comparison system that matches confirmed expected connections from approved implementation files against documented actual connections, enabling users to identify discrepancies and take corrective actions.

## Task List

### Database Layer

#### Task Group 1: Discrepancy Acknowledgment Data Model
**Dependencies:** None

- [x] 1.0 Complete discrepancy acknowledgments database layer
  - [x] 1.1 Write 4-6 focused tests for DiscrepancyAcknowledgment model functionality
    - Test acknowledgment creation with expected_connection_id
    - Test acknowledgment creation with connection_id
    - Test relationship to User (acknowledged_by)
    - Test discrepancy_type enum validation
    - Test nullable fields behavior (expected_connection_id/connection_id)
    - Test unique constraint on expected_connection_id + connection_id + discrepancy_type combination
  - [x] 1.2 Create DiscrepancyAcknowledgment model
    - Fields: id, expected_connection_id (nullable FK), connection_id (nullable FK), discrepancy_type (enum), acknowledged_by (FK to users), acknowledged_at, notes (text, nullable)
    - Relationships: belongsTo ExpectedConnection, belongsTo Connection, belongsTo User (as acknowledgedBy)
    - Add fillable and casts properties
    - Follow existing model patterns from `ExpectedConnection.php`
  - [x] 1.3 Create discrepancy_acknowledgments migration
    - Add foreign keys with cascade on delete for expected_connection_id, connection_id, acknowledged_by
    - Add unique composite index on (expected_connection_id, connection_id, discrepancy_type)
    - Add index on acknowledged_at for filtering
  - [x] 1.4 Create DiscrepancyType enum
    - Values: Matched, Missing, Unexpected, Mismatched, Conflicting
    - Add label() method for human-readable display
    - Place in `app/Enums/DiscrepancyType.php`
  - [x] 1.5 Create DiscrepancyAcknowledgmentFactory for testing
    - Define default state with faker data
    - Add states for different discrepancy types
    - Add state for acknowledged expected connection vs actual connection
  - [x] 1.6 Ensure database layer tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify migration runs successfully
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- Migration runs without errors
- Model relationships work correctly
- Enum provides correct labels

---

### Service Layer

#### Task Group 2: Connection Comparison Service
**Dependencies:** Task Group 1

- [x] 2.0 Complete ConnectionComparisonService
  - [x] 2.1 Write 6-8 focused tests for ConnectionComparisonService
    - Test exact match detection (source_port_id + destination_port_id match)
    - Test bidirectional matching (A->B matches B->A)
    - Test "Expected but Missing" detection
    - Test "Actual but Unexpected" detection
    - Test partial match detection (source matches, destination differs)
    - Test conflict detection (multiple files, same source, different destinations)
    - Test filtering by implementation file
    - Test aggregation across datacenter
  - [x] 2.2 Create ConnectionComparisonService class
    - Location: `app/Services/ConnectionComparisonService.php`
    - Method: `compareForImplementationFile(ImplementationFile $file): ComparisonResultCollection`
    - Method: `compareForDatacenter(Datacenter $datacenter): ComparisonResultCollection`
    - Method: `checkBidirectionalMatch(int $sourcePortId, int $destPortId): ?Connection`
    - Use query builder for efficient matching, avoid N+1 queries
  - [x] 2.3 Create ComparisonResult DTO class
    - Location: `app/DTOs/ComparisonResult.php`
    - Properties: discrepancy_type (DiscrepancyType), expected_connection (?ExpectedConnection), actual_connection (?Connection), source_port, dest_port, expected_dest_port, actual_dest_port, conflict_info (?array), acknowledgment (?DiscrepancyAcknowledgment)
    - Add static factory methods for each discrepancy type
  - [x] 2.4 Create ComparisonResultCollection class
    - Location: `app/DTOs/ComparisonResultCollection.php`
    - Implement iterable interface
    - Add methods: filterByDiscrepancyType(), filterByDevice(), filterByRack(), getStatistics()
    - Add pagination support with offset/limit
  - [x] 2.5 Implement conflict detection logic
    - Query all approved implementation files for datacenter
    - Group expected connections by source_port_id
    - Flag conflicts where same source has different dest_port_id across files
    - Include both conflicting expectations in result
  - [x] 2.6 Ensure service layer tests pass
    - Run ONLY the 6-8 tests written in 2.1
    - Verify all match scenarios work correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6-8 tests written in 2.1 pass
- Bidirectional matching works correctly
- All five discrepancy types are detected accurately
- Conflict detection identifies overlapping file expectations
- Query performance is efficient (eager loading, no N+1)

---

### API Layer

#### Task Group 3: Comparison API Endpoints
**Dependencies:** Task Group 2

- [x] 3.0 Complete comparison API endpoints
  - [x] 3.1 Write 5-7 focused tests for API endpoints
    - Test implementation file comparison endpoint returns correct structure
    - Test datacenter comparison endpoint returns correct structure
    - Test filtering by discrepancy type works
    - Test filtering by device/rack works
    - Test pagination works correctly
    - Test authorization (only approved files with confirmed connections)
    - Test acknowledgment creation endpoint
  - [x] 3.2 Create ConnectionComparisonController
    - Location: `app/Http/Controllers/Api/ConnectionComparisonController.php`
    - Action: `compareForFile(ImplementationFile $file)` - returns comparison for single file
    - Action: `compareForDatacenter(Datacenter $datacenter)` - returns aggregated comparison
    - Inject ConnectionComparisonService via constructor
  - [x] 3.3 Create ComparisonRequest form request classes
    - `CompareConnectionsRequest` with validation for filter parameters
    - Validate discrepancy_type filter against DiscrepancyType enum values
    - Validate device_id and rack_id exist
    - Validate show_acknowledged boolean
  - [x] 3.4 Create ComparisonResultResource API resource
    - Transform ComparisonResult DTO to JSON structure
    - Include nested source_device, source_port, dest_device, dest_port data
    - Include expected vs actual values with differences highlighted
    - Include acknowledgment data if present
  - [x] 3.5 Create DiscrepancyAcknowledgmentController
    - Location: `app/Http/Controllers/Api/DiscrepancyAcknowledgmentController.php`
    - Action: `store(AcknowledgeDiscrepancyRequest $request)` - create acknowledgment
    - Action: `destroy(DiscrepancyAcknowledgment $acknowledgment)` - remove acknowledgment
  - [x] 3.6 Register API routes
    - GET `/api/implementation-files/{file}/comparison` - file comparison
    - GET `/api/datacenters/{datacenter}/connection-comparison` - datacenter comparison
    - POST `/api/discrepancy-acknowledgments` - create acknowledgment
    - DELETE `/api/discrepancy-acknowledgments/{acknowledgment}` - delete acknowledgment
  - [x] 3.7 Ensure API layer tests pass
    - Run ONLY the 5-7 tests written in 3.1
    - Verify response structures match expectations
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 5-7 tests written in 3.1 pass
- API endpoints return correct JSON structure
- Filtering and pagination work correctly
- Authorization prevents access to non-approved files
- Acknowledgment CRUD works correctly

---

#### Task Group 4: Comparison Export Functionality
**Dependencies:** Task Group 3

- [x] 4.0 Complete comparison export functionality
  - [x] 4.1 Write 3-4 focused tests for export functionality
    - Test CSV export generates correct headers
    - Test CSV export includes all comparison data fields
    - Test export respects current filter selections
    - Test export includes acknowledged status
  - [x] 4.2 Create ComparisonExport class
    - Location: `app/Exports/ComparisonExport.php`
    - Extend AbstractDataExport following RackExport pattern
    - Implement headings(): Source Device, Source Port, Dest Device, Dest Port, Expected Cable Type, Actual Cable Type, Discrepancy Type, Acknowledged, Notes
    - Implement transformRow() for ComparisonResult DTOs
  - [x] 4.3 Add export endpoint to ConnectionComparisonController
    - Action: `exportForFile(ImplementationFile $file)`
    - Action: `exportForDatacenter(Datacenter $datacenter)`
    - Accept filter parameters to match current view state
    - Return CSV file download response
  - [x] 4.4 Register export routes
    - GET `/api/implementation-files/{file}/comparison/export`
    - GET `/api/datacenters/{datacenter}/connection-comparison/export`
  - [x] 4.5 Ensure export tests pass
    - Run ONLY the 3-4 tests written in 4.1
    - Verify CSV structure is correct
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 3-4 tests written in 4.1 pass
- CSV export includes all required columns
- Export respects filter selections
- File downloads correctly with proper headers

---

### Frontend Components

#### Task Group 5: Comparison Table Component
**Dependencies:** Task Groups 3, 4

- [x] 5.0 Complete comparison table UI component
  - [x] 5.1 Write 4-6 focused tests for ComparisonTable component
    - Test table renders all discrepancy types with correct styling
    - Test row highlighting based on discrepancy type
    - Test expected vs actual value display format
    - Test conflict indicator display
    - Test acknowledged rows show muted styling
    - Test action buttons appear for correct discrepancy types
  - [x] 5.2 Create ComparisonTable.vue component
    - Location: `resources/js/Components/comparison/ComparisonTable.vue`
    - Props: comparisons (array), statistics (object), isLoading (boolean)
    - Emit events: create-connection, delete-connection, acknowledge, refresh
    - Reuse table structure from ConnectionReviewTable.vue
    - Implement getRowClasses() for discrepancy-based coloring:
      - Green (border-l-green-500): Matched
      - Red (border-l-red-500): Missing
      - Orange (border-l-orange-500): Unexpected
      - Yellow (border-l-amber-500): Mismatched
      - Purple border: Conflicting
  - [x] 5.3 Create ComparisonRow.vue sub-component
    - Display expected values with actual values in parentheses when different
    - Example: "Port-A1 (Actual: Port-B2)"
    - Show conflict warning icon and tooltip for conflicting rows
    - Display "Acknowledged" badge for acknowledged discrepancies
  - [x] 5.4 Create ComparisonStatistics.vue component
    - Show counts for each discrepancy type with colored badges
    - Follow pattern from ConnectionReviewTable statistics section
    - Display: Total, Matched, Missing, Unexpected, Mismatched, Conflicting
  - [x] 5.5 Implement action button rendering logic
    - "Create Connection" button for "Missing" status only
    - "Delete Connection" button for "Unexpected" status only
    - "Acknowledge" button for all non-matched statuses
    - "View Details" button for matched connections
  - [x] 5.6 Ensure component tests pass
    - Run ONLY the 4-6 tests written in 5.1
    - Verify rendering and styling work correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 5.1 pass
- Table displays all discrepancy types correctly
- Row highlighting matches specification
- Action buttons appear appropriately
- Conflict indicators display correctly

---

#### Task Group 6: Comparison Filter Controls
**Dependencies:** Task Group 5

- [x] 6.0 Complete comparison filter controls
  - [x] 6.1 Write 3-4 focused tests for filter controls
    - Test discrepancy type multi-select works
    - Test device filter dropdown populates correctly
    - Test rack filter dropdown populates correctly
    - Test filter state persists in URL query parameters
  - [x] 6.2 Create ComparisonFilters.vue component
    - Location: `resources/js/Components/comparison/ComparisonFilters.vue`
    - Multi-select dropdown for discrepancy types
    - Searchable dropdown for device filter
    - Dropdown for rack filter
    - Checkbox for show/hide acknowledged
    - Export button with current filters applied
  - [x] 6.3 Implement URL query parameter persistence
    - Sync filter state with URL using router.push with query params
    - Parse URL params on component mount to restore filters
    - Use composable: `useComparisonFilters()`
  - [x] 6.4 Connect filters to parent page
    - Emit filter changes to parent for API request
    - Debounce filter changes to avoid excessive API calls
    - Show loading state during filter application
  - [x] 6.5 Ensure filter tests pass
    - Run ONLY the 3-4 tests written in 6.1
    - Verify filter interactions work correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 3-4 tests written in 6.1 pass
- All filter types work correctly
- URL reflects current filter state
- Shareable links preserve filter selections

---

#### Task Group 7: Acknowledge Discrepancy Dialog
**Dependencies:** Task Group 5

- [x] 7.0 Complete acknowledge discrepancy dialog
  - [x] 7.1 Write 2-3 focused tests for acknowledgment dialog
    - Test dialog opens with correct discrepancy info
    - Test form submission creates acknowledgment
    - Test dialog closes and triggers refresh on success
  - [x] 7.2 Create AcknowledgeDiscrepancyDialog.vue component
    - Location: `resources/js/Components/comparison/AcknowledgeDiscrepancyDialog.vue`
    - Props: discrepancy (ComparisonResult type)
    - Form fields: notes (optional textarea)
    - Display discrepancy summary before confirmation
    - Use Dialog pattern from existing DeleteConnectionConfirmation.vue
  - [x] 7.3 Connect dialog to ComparisonTable
    - Open dialog when "Acknowledge" button clicked
    - Pass discrepancy data as prop
    - Refresh table on successful acknowledgment
  - [x] 7.4 Ensure dialog tests pass
    - Run ONLY the 2-3 tests written in 7.1
    - Verify form submission works
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-3 tests written in 7.1 pass
- Dialog displays discrepancy information
- Notes can be added
- Acknowledgment is created on submit

---

### Page Integration

#### Task Group 8: Implementation File Comparison Page
**Dependencies:** Task Groups 5, 6, 7 (completed)

- [x] 8.0 Complete implementation file comparison page
  - [x] 8.1 Write 3-4 focused tests for file comparison page
    - Test page loads for approved file with confirmed connections
    - Test page shows error for non-approved file
    - Test comparison data displays correctly
    - Test create/delete actions work from this page
  - [x] 8.2 Create FileComparisonPage.vue Inertia page
    - Location: `resources/js/Pages/ImplementationFiles/Comparison.vue`
    - Accept props: implementationFile, initialComparisons, filterOptions
    - Compose: ComparisonFilters, ComparisonTable, ComparisonStatistics
    - Include CreateConnectionDialog and DeleteConnectionConfirmation
  - [x] 8.3 Create FileComparisonController or add action to existing controller
    - Action: `comparison(ImplementationFile $file)`
    - Verify file is approved with confirmed expected connections
    - Return Inertia page with comparison data
    - Include filter options for devices/racks involved
  - [x] 8.4 Add "Compare Connections" button to implementation file detail page
    - Show button only for approved files with confirmed connections
    - Link to `/implementation-files/{file}/comparison`
  - [x] 8.5 Register route for file comparison page
    - GET `/implementation-files/{file}/comparison`
    - Apply appropriate middleware and authorization
  - [x] 8.6 Ensure page integration tests pass
    - Run ONLY the 3-4 tests written in 8.1
    - Verify page loads and actions work
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 3-4 tests written in 8.1 pass
- Page loads with correct comparison data
- Create/delete connection actions work
- Filters and export work correctly

---

#### Task Group 9: Datacenter Comparison Page
**Dependencies:** Task Groups 5, 6, 7

- [x] 9.0 Complete datacenter comparison page
  - [x] 9.1 Write 3-4 focused tests for datacenter comparison page
    - Test page aggregates from all approved implementation files
    - Test conflict detection displays correctly
    - Test pagination works for large datasets
    - Test filtering works across aggregated data
  - [x] 9.2 Create DatacenterComparisonPage.vue Inertia page
    - Location: `resources/js/Pages/Datacenters/ConnectionComparison.vue`
    - Accept props: datacenter, initialComparisons, filterOptions, paginationMeta
    - Compose: ComparisonFilters, ComparisonTable, ComparisonStatistics
    - Add pagination controls with 50 rows per page default
  - [x] 9.3 Create controller action for datacenter comparison
    - Add to DatacenterController or create dedicated controller
    - Action: `connectionComparison(Datacenter $datacenter)`
    - Aggregate confirmed connections from all approved files
    - Handle pagination with default 50 rows per page
  - [x] 9.4 Add "Connection Audit" button to datacenter show page
    - Prominent placement near other action buttons
    - Link to `/datacenters/{datacenter}/connection-comparison`
  - [x] 9.5 Register route for datacenter comparison page
    - GET `/datacenters/{datacenter}/connection-comparison`
    - Apply appropriate middleware and authorization
  - [x] 9.6 Ensure page integration tests pass
    - Run ONLY the 3-4 tests written in 9.1
    - Verify aggregation and pagination work
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 3-4 tests written in 9.1 pass
- Page aggregates data from all approved files
- Conflicts are detected and displayed
- Pagination works correctly

---

### Testing

#### Task Group 10: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-9

- [x] 10.0 Review existing tests and fill critical gaps only
  - [x] 10.1 Review tests from Task Groups 1-9
    - Review the 4-6 tests from database layer (Task 1.1)
    - Review the 6-8 tests from service layer (Task 2.1)
    - Review the 5-7 tests from API layer (Task 3.1)
    - Review the 3-4 tests from export functionality (Task 4.1)
    - Review the 4-6 tests from comparison table (Task 5.1)
    - Review the 3-4 tests from filter controls (Task 6.1)
    - Review the 2-3 tests from acknowledgment dialog (Task 7.1)
    - Review the 3-4 tests from file comparison page (Task 8.1)
    - Review the 3-4 tests from datacenter comparison page (Task 9.1)
    - Total existing tests: approximately 33-46 tests
  - [x] 10.2 Analyze test coverage gaps for THIS feature only
    - Identify critical end-to-end workflows lacking coverage
    - Focus ONLY on gaps related to comparison view feature
    - Prioritize integration tests over additional unit tests
    - Do NOT assess entire application test coverage
  - [x] 10.3 Write up to 10 additional strategic tests maximum
    - Focus on end-to-end workflow: view comparison -> create connection -> verify status update
    - Focus on end-to-end workflow: view comparison -> delete connection -> verify status update
    - Focus on end-to-end workflow: acknowledge discrepancy -> verify display change
    - Test edge case: empty comparison (no expected or actual connections)
    - Test edge case: all connections matched
    - Test filter combinations work correctly together
    - Do NOT write comprehensive coverage for all edge cases
  - [x] 10.4 Run feature-specific tests only
    - Run ONLY tests related to this spec's feature
    - Expected total: approximately 43-56 tests maximum
    - Do NOT run the entire application test suite unless requested
    - Verify all critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass
- Critical end-to-end workflows are covered
- No more than 10 additional tests added
- Testing focused exclusively on comparison view feature

---

## Execution Order

Recommended implementation sequence for efficient development:

1. **Database Layer** (Task Group 1) - No dependencies, creates foundation
2. **Service Layer** (Task Group 2) - Requires database layer
3. **API Layer** (Task Groups 3, 4) - Requires service layer, can run in parallel
4. **Frontend Components** (Task Groups 5, 6, 7) - Requires API, can develop in parallel with mock data
5. **Page Integration** (Task Groups 8, 9) - Requires frontend components, can run in parallel
6. **Test Review** (Task Group 10) - Final step after all implementation complete

### Parallel Development Opportunities

- Task Groups 3 and 4 (API endpoints and Export) can be developed in parallel after Task Group 2
- Task Groups 5, 6, and 7 (UI components) can be developed in parallel once API is available
- Task Groups 8 and 9 (page integration) can be developed in parallel

### Key Files to Create

| File | Location | Purpose |
|------|----------|---------|
| DiscrepancyAcknowledgment.php | app/Models/ | Acknowledgment model |
| DiscrepancyType.php | app/Enums/ | Discrepancy type enum |
| create_discrepancy_acknowledgments_table.php | database/migrations/ | Migration |
| ConnectionComparisonService.php | app/Services/ | Core comparison logic |
| ComparisonResult.php | app/DTOs/ | Result data object |
| ComparisonResultCollection.php | app/DTOs/ | Collection with filtering |
| ConnectionComparisonController.php | app/Http/Controllers/Api/ | API controller |
| DiscrepancyAcknowledgmentController.php | app/Http/Controllers/Api/ | Acknowledgment CRUD |
| ComparisonExport.php | app/Exports/ | CSV export |
| ComparisonTable.vue | resources/js/Components/comparison/ | Main table component |
| ComparisonFilters.vue | resources/js/Components/comparison/ | Filter controls |
| AcknowledgeDiscrepancyDialog.vue | resources/js/Components/comparison/ | Acknowledgment dialog |
| Comparison.vue | resources/js/Pages/ImplementationFiles/ | File comparison page |
| ConnectionComparison.vue | resources/js/Pages/Datacenters/ | Datacenter comparison page |

### Existing Files to Modify

| File | Modification |
|------|--------------|
| routes/api.php | Add comparison and acknowledgment routes |
| routes/web.php | Add Inertia page routes |
| Implementation file detail page | Add "Compare Connections" button |
| Datacenter show page | Add "Connection Audit" button |
