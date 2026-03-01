# Task Breakdown: Expected Connection Parsing

## Overview
Total Tasks: 4 Task Groups with 35 sub-tasks

This feature enables parsing of uploaded Excel/CSV implementation files to extract expected connections with port-to-port mapping. Parsed data populates an expected_connections table for comparison against actual documented connections during audits.

## Task List

### Database Layer

#### Task Group 1: Data Models and Migrations
**Dependencies:** None

- [x] 1.0 Complete database layer for expected connections
  - [x] 1.1 Write 4-6 focused tests for ExpectedConnection model functionality
    - Test model creation with required fields (implementation_file_id, source_device_id, source_port_id, dest_device_id, dest_port_id)
    - Test status field enum values (pending_review, confirmed, skipped)
    - Test belongsTo relationship to ImplementationFile
    - Test belongsTo relationships to Device and Port models
    - Test scope for filtering by status (confirmed only for comparison view)
    - Test archiving logic when new file version is uploaded
  - [x] 1.2 Create ExpectedConnectionStatus enum
    - Values: pending_review, confirmed, skipped
    - Add label() method for human-readable display
    - Follow pattern from `app/Enums/BulkImportStatus.php`
  - [x] 1.3 Create migration for expected_connections table
    - Columns: id, implementation_file_id, source_device_id, source_port_id, dest_device_id, dest_port_id, cable_type (nullable), cable_length (nullable), row_number, status, timestamps, soft_deletes
    - Foreign key: implementation_file_id references implementation_files(id)
    - Foreign keys: source_device_id, dest_device_id reference devices(id)
    - Foreign keys: source_port_id, dest_port_id reference ports(id)
    - Add index on implementation_file_id for efficient querying
    - Add index on status for filtering confirmed connections
  - [x] 1.4 Create ExpectedConnection model
    - Fields following Connection model pattern: source_device_id, source_port_id, dest_device_id, dest_port_id, cable_type, cable_length
    - Additional fields: implementation_file_id, row_number, status
    - Use Loggable trait for activity logging
    - Use SoftDeletes for archiving old versions
    - Cast cable_type to CableType enum
    - Cast cable_length to decimal:2
    - Cast status to ExpectedConnectionStatus enum
    - Implement getEnrichedAttributesForLog() following Connection model pattern
  - [x] 1.5 Set up model associations
    - ExpectedConnection belongsTo ImplementationFile
    - ExpectedConnection belongsTo Device (source_device)
    - ExpectedConnection belongsTo Device (dest_device)
    - ExpectedConnection belongsTo Port (source_port)
    - ExpectedConnection belongsTo Port (dest_port)
    - Add expectedConnections() hasMany relationship to ImplementationFile model
  - [x] 1.6 Create ExpectedConnection factory for testing
    - Follow pattern from ConnectionFactory
    - Include states for each status: pending_review, confirmed, skipped
    - Support creating with specific ImplementationFile
  - [x] 1.7 Ensure database layer tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify migration runs successfully
    - Verify model relationships work correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- Migration creates expected_connections table with correct structure
- ExpectedConnection model has all required relationships
- Factory can create test records in all statuses


### Backend Services Layer

#### Task Group 2: Template System and Parsing Engine
**Dependencies:** Task Group 1

- [x] 2.0 Complete template system and file parsing engine
  - [x] 2.1 Write 6-8 focused tests for parsing functionality
    - Test Excel template generation with correct columns and instructions sheet
    - Test CSV template generation with header row
    - Test successful parsing of valid Excel file with all columns
    - Test successful parsing of valid CSV file
    - Test rejection of files missing required columns
    - Test row-level error capture for invalid data
    - Test fuzzy matching confidence score calculation
    - Test file size limit enforcement
  - [x] 2.2 Create ConnectionTemplateExport class for Excel template generation
    - Use Laravel Excel (maatwebsite/excel) package
    - Create main sheet with columns: Source Device, Source Port, Dest Device, Dest Port, Cable Type, Cable Length
    - Create Instructions sheet with column descriptions and sample data
    - Follow existing export patterns in the codebase
  - [x] 2.3 Create ConnectionTemplateController for template downloads
    - GET endpoint for Excel template download: /api/templates/connections/excel
    - GET endpoint for CSV template download: /api/templates/connections/csv
    - Use proper Content-Disposition headers for file downloads
    - Register routes in routes/api.php
  - [x] 2.4 Create ConnectionFileImport class for parsing uploaded files
    - Use Laravel Excel for reading .xlsx, .xls files
    - Use native PHP for reading CSV files
    - Validate required columns exist before parsing: Source Device, Source Port, Dest Device, Dest Port
    - Extract each row into structured array: source_device, source_port, dest_device, dest_port, cable_type, cable_length
    - Capture row number for each extracted connection
    - Capture parsing errors per row for user review
  - [x] 2.5 Create FuzzyMatchingService for device/port matching
    - Use Levenshtein distance algorithm for string similarity
    - Query Device model by name field for device matching
    - Query Port model by label field for port matching
    - Calculate match confidence scores (0-100)
    - Categorize matches: exact (100), suggested (threshold-99), unrecognized (below threshold)
    - Configure threshold as class constant (suggest 70-80%)
    - Return matched IDs and confidence scores for each parsed row
  - [x] 2.6 Create ParseConnectionsAction class to orchestrate parsing
    - Accept ImplementationFile model as input
    - Validate file is Excel/CSV type before parsing
    - Enforce file size limit (configurable, suggest 5MB for synchronous processing)
    - Call ConnectionFileImport to extract raw data
    - Call FuzzyMatchingService for each row to match devices/ports
    - Create ExpectedConnection records in pending_review status
    - Return parsing results with match statistics: total, matched, suggested, unrecognized
    - Handle version replacement: soft delete previous version's expected connections
  - [x] 2.7 Create ParseConnectionsController for API endpoint
    - POST endpoint: /api/implementation-files/{implementationFile}/parse-connections
    - Validate implementation file is approved before allowing parsing
    - Validate file type is Excel or CSV
    - Call ParseConnectionsAction
    - Return parsed connections with match statuses for review
    - Return parsing errors if any
    - Follow existing controller patterns for error handling
  - [x] 2.8 Ensure parsing engine tests pass
    - Run ONLY the 6-8 tests written in 2.1
    - Verify template generation works correctly
    - Verify parsing handles valid and invalid files
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6-8 tests written in 2.1 pass
- Excel and CSV templates can be downloaded
- Parser correctly extracts connection data from valid files
- Parser rejects files missing required columns with clear error messages
- Fuzzy matching correctly categorizes matches by confidence
- Expected connections are created with pending_review status


### API Layer

#### Task Group 3: Review and Confirmation API
**Dependencies:** Task Group 2

- [x] 3.0 Complete API layer for connection review and confirmation
  - [x] 3.1 Write 4-6 focused tests for review API endpoints
    - Test listing parsed connections for an implementation file
    - Test updating individual expected connection (device/port mapping correction)
    - Test bulk confirm action for matched connections
    - Test bulk skip action for unrecognized connections
    - Test device/port creation on the fly for unrecognized entries
    - Test authorization check - only file uploader or admin can review
  - [x] 3.2 Create ExpectedConnectionResource for API responses
    - Include all expected connection fields
    - Include related device names and port labels
    - Include match status (exact, suggested, unrecognized)
    - Include original parsed values for comparison
    - Include row number for reference
    - Follow existing API Resource patterns
  - [x] 3.3 Create ExpectedConnectionController for CRUD operations
    - GET /api/implementation-files/{implementationFile}/expected-connections - list all for review
    - PUT /api/expected-connections/{expectedConnection} - update individual mapping
    - Response includes match statistics summary
    - Follow existing controller patterns
  - [x] 3.4 Create BulkConfirmExpectedConnectionsAction
    - Accept array of expected connection IDs
    - Update status to confirmed for all matching IDs
    - Validate all connections belong to same implementation file
    - Return count of confirmed connections
  - [x] 3.5 Create BulkSkipExpectedConnectionsAction
    - Accept array of expected connection IDs
    - Update status to skipped for all matching IDs
    - Validate all connections belong to same implementation file
    - Return count of skipped connections
  - [x] 3.6 Create bulk action endpoints in ExpectedConnectionController
    - POST /api/expected-connections/bulk-confirm - confirm multiple connections
    - POST /api/expected-connections/bulk-skip - skip multiple connections
    - Validate request with array of IDs
    - Use Form Request classes for validation
  - [x] 3.7 Create CreateDevicePortOnFlyAction for unrecognized entries
    - Accept device name and port label
    - Create Device if not exists (minimal required fields)
    - Create Port on the device if not exists
    - Return created device/port IDs
    - Update the expected connection with new IDs
  - [x] 3.8 Ensure API layer tests pass
    - Run ONLY the 4-6 tests written in 3.1
    - Verify all CRUD operations work correctly
    - Verify bulk actions update statuses
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 3.1 pass
- Expected connections can be listed with match statuses
- Individual connections can be updated to correct mappings
- Bulk confirm/skip actions work correctly
- Devices/ports can be created on the fly for unrecognized entries


### Frontend Layer

#### Task Group 4: Review Interface Components
**Dependencies:** Task Group 3

- [x] 4.0 Complete frontend review interface
  - [x] 4.1 Write 4-6 focused tests for frontend components
    - Test ConnectionReviewTable renders parsed connections correctly
    - Test visual distinction for match states (exact=green, suggested=yellow, unrecognized=red)
    - Test inline editing updates connection mapping
    - Test bulk confirm action submits correctly
    - Test bulk skip action submits correctly
    - Test device/port dropdown search functionality
  - [x] 4.2 Add template download button to UploadImplementationFileDialog
    - Add download link/button above FileDropzone component
    - Offer both Excel and CSV template options
    - Use Wayfinder for download endpoint URLs
    - Follow existing dialog component patterns
    - Reference: `/Users/helderdene/rackaudit/resources/js/components/implementation-files/UploadImplementationFileDialog.vue`
  - [x] 4.3 Create ParseConnectionsButton component
    - Show on implementation file detail after approval
    - Only visible for Excel/CSV files (hide for PDF/Word/text)
    - Call parse-connections API endpoint on click
    - Show loading state during synchronous parsing
    - Navigate to review interface on success
    - Display error message on failure
  - [x] 4.4 Create ConnectionReviewTable component
    - Display parsed connections in data table format
    - Columns: Row#, Source Device, Source Port, Dest Device, Dest Port, Cable Type, Cable Length, Status, Actions
    - Visual distinction for match states using Tailwind CSS:
      - Green background/border for exact matches
      - Yellow/amber for suggested matches with original value displayed
      - Red for unrecognized entries
    - Show match confidence percentage for suggested matches
    - Include row count summary header: total, matched, suggested, unrecognized
  - [x] 4.5 Create inline editing capability for ConnectionReviewTable rows
    - Device dropdown selector with search functionality
    - Port dropdown selector filtered by selected device
    - Cable type dropdown using CableType enum values
    - Cable length input with validation
    - Save button to submit individual row updates
    - Use Wayfinder for API endpoints
  - [x] 4.6 Create DeviceSearchSelect component
    - Searchable dropdown for device selection
    - Query devices API with search term
    - Show device name and asset tag in options
    - Emit selected device ID to parent
    - Reuse for both source and destination device selection
  - [x] 4.7 Create PortSearchSelect component
    - Searchable dropdown for port selection
    - Accept device_id prop to filter ports
    - Query ports API filtered by device
    - Show port label and type in options
    - Emit selected port ID to parent
  - [x] 4.8 Implement bulk action buttons in ConnectionReviewTable
    - "Confirm All Matched" button - confirms all exact match connections
    - "Reject All Unmatched" button - skips all unrecognized connections
    - Checkbox selection for individual bulk actions
    - "Confirm Selected" and "Skip Selected" for custom selection
    - Show confirmation dialog before bulk actions
    - Update table state after successful bulk action
  - [x] 4.9 Create CreateDevicePortDialog component for unrecognized entries
    - Modal dialog triggered from unrecognized row actions
    - Form fields: Device Name (required), Port Label (required)
    - Optional: Device Type selector if needed
    - Submit creates device/port and updates the expected connection
    - Close dialog and refresh table on success
  - [x] 4.10 Create ConnectionReviewPage Vue component
    - Integrate ConnectionReviewTable as main content
    - Include summary statistics card at top
    - "Finalize Review" button when all connections reviewed
    - Navigate back to implementation file on finalize
    - Register with Inertia in resources/js/Pages
  - [x] 4.11 Ensure frontend tests pass
    - Run ONLY the 4-6 tests written in 4.1
    - Verify components render and interact correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 4.1 pass
- Template download buttons work in upload dialog
- Parse button appears for approved Excel/CSV files only
- Review table displays connections with correct visual distinction
- Inline editing updates connection mappings
- Bulk actions work correctly
- Device/port creation on the fly works for unrecognized entries


### Integration and Testing

#### Task Group 5: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-4

- [x] 5.0 Review existing tests and fill critical gaps only
  - [x] 5.1 Review tests from Task Groups 1-4
    - Review the 4-6 tests written for database layer (Task 1.1)
    - Review the 6-8 tests written for parsing engine (Task 2.1)
    - Review the 4-6 tests written for API layer (Task 3.1)
    - Review the 4-6 tests written for frontend (Task 4.1)
    - Total existing tests: approximately 18-26 tests
  - [x] 5.2 Analyze test coverage gaps for expected connection parsing feature
    - Identify critical end-to-end workflows that lack coverage
    - Focus ONLY on gaps related to this spec's feature requirements
    - Prioritize: upload -> parse -> review -> confirm workflow
    - Check version replacement logic (archive old expected connections)
    - Verify confirmed connections are available for comparison view
  - [x] 5.3 Write up to 8 additional strategic tests to fill critical gaps
    - End-to-end test: upload file, parse, review, confirm all
    - End-to-end test: upload new version replaces old expected connections
    - Integration test: fuzzy matching with mixed match types
    - Integration test: create device/port on the fly
    - Browser test: complete review workflow in UI (if Pest Browser available)
    - Do NOT write exhaustive coverage for all edge cases
  - [x] 5.4 Run feature-specific tests only
    - Run ONLY tests related to expected connection parsing feature
    - Include tests from 1.1, 2.1, 3.1, 4.1, and 5.3
    - Expected total: approximately 26-34 tests
    - Do NOT run the entire application test suite
    - Verify all critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 26-34 tests total)
- Critical user workflows for expected connection parsing are covered
- No more than 8 additional tests added when filling in testing gaps
- End-to-end parse -> review -> confirm workflow is tested
- Version replacement logic is verified


## Execution Order

Recommended implementation sequence:

1. **Database Layer (Task Group 1)** - Foundation for storing expected connections
   - Create enum, migration, model, relationships, factory
   - No dependencies on other groups

2. **Backend Services (Task Group 2)** - Template and parsing engine
   - Depends on Task Group 1 for ExpectedConnection model
   - Creates the core parsing functionality

3. **API Layer (Task Group 3)** - Review and confirmation endpoints
   - Depends on Task Groups 1-2 for models and parsing
   - Exposes review/edit/confirm functionality via API

4. **Frontend Layer (Task Group 4)** - User interface components
   - Depends on Task Group 3 for API endpoints
   - Creates the review interface for users

5. **Test Review and Gap Analysis (Task Group 5)** - Final validation
   - Depends on all previous groups
   - Ensures comprehensive test coverage for the feature


## Technical Notes

### Key Files to Reference
- Model pattern: `/Users/helderdene/rackaudit/app/Models/Connection.php`
- Enum pattern: `/Users/helderdene/rackaudit/app/Enums/BulkImportStatus.php`
- Upload dialog: `/Users/helderdene/rackaudit/resources/js/components/implementation-files/UploadImplementationFileDialog.vue`
- Loggable trait: `/Users/helderdene/rackaudit/app/Models/Concerns/Loggable.php`

### Package Dependencies
- Laravel Excel (maatwebsite/excel) - already installed for parsing/export

### Configuration Considerations
- File size limit for synchronous parsing: suggest 5MB
- Fuzzy matching threshold: suggest 75% confidence
- Template columns: Source Device, Source Port, Dest Device, Dest Port, Cable Type, Cable Length
