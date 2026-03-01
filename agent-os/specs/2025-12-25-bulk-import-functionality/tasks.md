# Task Breakdown: Bulk Import Functionality

## Overview
Total Tasks: 56 (across 7 task groups)

This feature enables bulk import of datacenter infrastructure data (datacenters, rooms, rows, racks, devices, and ports) from CSV and XLSX files with validation, error reporting, and async processing for large imports.

## Task List

### Database Layer

#### Task Group 1: BulkImport Model and Migration
**Dependencies:** None

- [x] 1.0 Complete database layer for import tracking
  - [x] 1.1 Write 4-6 focused tests for BulkImport model functionality
    - Test BulkImport model creation with valid status values
    - Test status transitions (pending -> processing -> completed/failed)
    - Test relationship to User (who initiated the import)
    - Test error_report_path storage and retrieval
    - Test progress calculation (processed_rows / total_rows)
  - [x] 1.2 Create BulkImport model with validations
    - Fields: `user_id`, `entity_type`, `file_name`, `file_path`, `status`, `total_rows`, `processed_rows`, `success_count`, `failure_count`, `error_report_path`, `started_at`, `completed_at`
    - Status enum values: `pending`, `processing`, `completed`, `failed`
    - Entity type values: `datacenter`, `room`, `row`, `rack`, `device`, `port`, `mixed`
    - Relationship: belongsTo User
    - Use Loggable concern following existing models
  - [x] 1.3 Create migration for bulk_imports table
    - Add foreign key to users table (user_id)
    - Add indexes for: status, entity_type, created_at
    - Include timestamps
  - [x] 1.4 Create BulkImport factory for testing
    - Define states: pending, processing, completed, failed
    - Include realistic test data generation
  - [x] 1.5 Ensure database layer tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify migration runs successfully
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- BulkImport model properly tracks import status and progress
- Migration creates table with proper indexes and foreign keys
- Factory enables efficient test data creation

---

### Package Installation and Configuration

#### Task Group 2: Laravel Excel Setup
**Dependencies:** None (can run in parallel with Task Group 1)

- [x] 2.0 Install and configure Laravel Excel package
  - [x] 2.1 Install maatwebsite/excel package via Composer
    - Run: `composer require maatwebsite/excel`
  - [x] 2.2 Publish Laravel Excel configuration
    - Run: `php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config`
  - [x] 2.3 Configure Excel settings in config/excel.php
    - Set appropriate memory limit for large files
    - Configure temporary file handling
    - Set CSV delimiter and encoding defaults
  - [x] 2.4 Verify package installation
    - Confirm package is available in artisan commands
    - Test basic import/export functionality works

**Acceptance Criteria:**
- Laravel Excel package installed and configured
- Package commands available via artisan
- Configuration optimized for datacenter import use case

---

### Import Classes Layer

#### Task Group 3: Excel Import Classes and Validation
**Dependencies:** Task Groups 1, 2

- [x] 3.0 Complete import classes for all entity types
  - [x] 3.1 Write 6-8 focused tests for import validation logic
    - Test datacenter import with valid data
    - Test room import with valid parent datacenter reference
    - Test device import with rack placement validation
    - Test port import with type/subtype compatibility
    - Test validation failure generates proper error format
    - Test parent entity lookup by name path
    - Test enum validation (RoomType, RackStatus, etc.)
    - Test row-level error collection without stopping import
  - [x] 3.2 Create base AbstractEntityImport class
    - Define common import interface
    - Implement row validation framework
    - Implement error collection (row_number, field_name, error_message)
    - Implement parent entity lookup helper methods
    - Handle enum value parsing (label to value conversion)
  - [x] 3.3 Create DatacenterImport class
    - Location: `app/Imports/DatacenterImport.php`
    - Validate fields matching StoreDatacenterRequest rules
    - Required: name, address_line_1, city, state_province, postal_code, country, primary_contact_name, primary_contact_email, primary_contact_phone
    - Optional: address_line_2, company_name, secondary contact fields
  - [x] 3.4 Create RoomImport class
    - Location: `app/Imports/RoomImport.php`
    - Validate fields matching StoreRoomRequest rules
    - Parent lookup: datacenter_name
    - Required: name, type (RoomType enum)
    - Optional: description, square_footage
  - [x] 3.5 Create RowImport class
    - Location: `app/Imports/RowImport.php`
    - Validate fields matching StoreRowRequest rules
    - Parent lookup: datacenter_name > room_name
    - Required: name, position, orientation (RowOrientation enum), status (RowStatus enum)
  - [x] 3.6 Create RackImport class
    - Location: `app/Imports/RackImport.php`
    - Validate fields matching StoreRackRequest rules
    - Parent lookup: datacenter_name > room_name > row_name
    - Required: name, position, u_height (RackUHeight enum), status (RackStatus enum)
    - Optional: serial_number
  - [x] 3.7 Create DeviceImport class
    - Location: `app/Imports/DeviceImport.php`
    - Validate fields matching StoreDeviceRequest rules
    - Parent lookup: datacenter_name > room_name > row_name > rack_name (optional for unplaced)
    - DeviceType lookup by name
    - Required: name, device_type_name, lifecycle_status, u_height, depth, width_type, rack_face
    - Optional: serial_number, manufacturer, model, dates, rack placement (rack_name, start_u), specs, notes
    - Validate rack placement against rack capacity
    - Asset tag auto-generation handled by Device model boot
  - [x] 3.8 Create PortImport class
    - Location: `app/Imports/PortImport.php`
    - Validate fields matching StorePortRequest rules
    - Parent lookup: device_name (requires unique device identification or full path)
    - Required: label, type (PortType enum), subtype (PortSubtype enum)
    - Optional: status, direction, position fields, visual fields
    - Validate subtype compatibility with type using PortSubtype::forType()
    - Validate direction compatibility with type using PortDirection::forType()
  - [x] 3.9 Create CombinedImport class for mixed entity imports
    - Location: `app/Imports/CombinedImport.php`
    - Detect entity type per row based on filled columns
    - Delegate to appropriate entity import class
    - Maintain import order for proper parent creation
  - [x] 3.10 Ensure import class tests pass
    - Run ONLY the 6-8 tests written in 3.1
    - Verify validation logic works correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6-8 tests written in 3.1 pass
- All entity imports validate correctly against existing Form Request rules
- Parent entity lookup by name path works for all hierarchies
- Enum values validated and converted properly
- Error collection provides row_number, field_name, error_message format

---

### Template Generation Layer

#### Task Group 4: XLSX Template Generation with Enum Dropdowns
**Dependencies:** Task Group 2

- [x] 4.0 Complete template generation for all entity types
  - [x] 4.1 Write 4-6 focused tests for template generation
    - Test template contains correct column headers
    - Test template includes example data row
    - Test enum dropdowns are present for relevant columns
    - Test helper text/comments describe required vs optional fields
    - Test combined template includes all entity columns
  - [x] 4.2 Create base AbstractTemplateExport class
    - Location: `app/Exports/Templates/AbstractTemplateExport.php`
    - Define interface for template exports
    - Implement Excel data validation dropdown helper
    - Implement header row styling (bold, background color)
    - Implement cell comment helper for field descriptions
  - [x] 4.3 Create DatacenterTemplateExport class
    - Location: `app/Exports/Templates/DatacenterTemplateExport.php`
    - Headers: name, address_line_1, address_line_2, city, state_province, postal_code, country, company_name, primary_contact_name, primary_contact_email, primary_contact_phone, secondary_contact_name, secondary_contact_email, secondary_contact_phone
    - Include example row with sample data
    - Add comments indicating required vs optional fields
  - [x] 4.4 Create RoomTemplateExport class
    - Headers: datacenter_name, name, type, description, square_footage
    - Dropdown for type: RoomType enum values (using label() method)
    - Include example row
  - [x] 4.5 Create RowTemplateExport class
    - Headers: datacenter_name, room_name, name, position, orientation, status
    - Dropdowns: orientation (RowOrientation), status (RowStatus)
    - Include example row
  - [x] 4.6 Create RackTemplateExport class
    - Headers: datacenter_name, room_name, row_name, name, position, u_height, serial_number, status
    - Dropdowns: u_height (RackUHeight), status (RackStatus)
    - Include example row
  - [x] 4.7 Create DeviceTemplateExport class
    - Headers: datacenter_name, room_name, row_name, rack_name, name, device_type_name, lifecycle_status, serial_number, manufacturer, model, purchase_date, warranty_start_date, warranty_end_date, u_height, depth, width_type, rack_face, start_u, notes
    - Dropdowns: lifecycle_status (DeviceLifecycleStatus), depth (DeviceDepth), width_type (DeviceWidthType), rack_face (DeviceRackFace)
    - Add note: device_type_name must match existing DeviceType name
    - Include example row
  - [x] 4.8 Create PortTemplateExport class
    - Headers: device_name (or full path), label, type, subtype, status, direction, position_slot, position_row, position_column
    - Dropdowns: type (PortType), subtype (PortSubtype - note: valid subtypes depend on type), status (PortStatus), direction (PortDirection)
    - Add note about subtype/type compatibility
    - Include example row
  - [x] 4.9 Create CombinedTemplateExport class
    - Single template with all possible columns
    - Entity type detection column or instruction sheet
    - All relevant dropdowns included
    - Include example rows for each entity type
  - [x] 4.10 Create TemplateDownloadController
    - Location: `app/Http/Controllers/TemplateDownloadController.php`
    - Endpoints for each entity template download
    - Endpoint for combined template download
    - Use Wayfinder for route generation
  - [x] 4.11 Ensure template generation tests pass
    - Run ONLY the 4-6 tests written in 4.1
    - Verify templates generate correctly with dropdowns
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 4.1 pass
- Templates download as valid XLSX files
- Enum dropdowns work in Excel for all enum fields
- Example data and helper text guide users on field requirements
- Combined template supports all entity types in one file

---

### Queue Processing Layer

#### Task Group 5: Async Job Processing and Progress Tracking
**Dependencies:** Task Groups 1, 3

- [x] 5.0 Complete async processing for large imports
  - [x] 5.1 Write 4-6 focused tests for queue processing
    - Test ProcessBulkImportJob dispatches for 100+ row imports
    - Test sync processing for under 100 row imports
    - Test progress updates stored correctly during processing
    - Test error report CSV generation on failures
    - Test BulkImport status transitions through job lifecycle
    - Test partial import (valid rows succeed, invalid rows fail)
  - [x] 5.2 Create ProcessBulkImportJob
    - Location: `app/Jobs/ProcessBulkImportJob.php`
    - Implement ShouldQueue interface
    - Accept BulkImport model as parameter
    - Process rows in chunks (50-100 rows per chunk)
    - Update BulkImport progress after each chunk
    - Use database transactions per entity batch
    - Generate error report CSV on completion if failures exist
    - Update BulkImport status: processing -> completed/failed
    - Handle job failures gracefully
  - [x] 5.3 Create ImportErrorReportService
    - Location: `app/Services/ImportErrorReportService.php`
    - Generate CSV with columns: row_number, field_name, error_message
    - Store in storage/app/import-errors/ with unique filename
    - Set file path on BulkImport model
    - Implement 24-hour cleanup (scheduled command or on-demand)
  - [x] 5.4 Create BulkImportService
    - Location: `app/Services/BulkImportService.php`
    - Handle file upload and validation (extension, MIME type, size <= 10MB)
    - Count rows to determine sync vs async processing
    - Create BulkImport record with pending status
    - Dispatch ProcessBulkImportJob for async or process directly for sync
    - Detect entity type(s) from file columns
  - [x] 5.5 Create CleanupExpiredImportErrorReports command
    - Location: `app/Console/Commands/CleanupExpiredImportErrorReports.php`
    - Delete error reports older than 24 hours
    - Update BulkImport records to clear error_report_path
    - Schedule in routes/console.php to run daily
  - [x] 5.6 Ensure queue processing tests pass
    - Run ONLY the 4-6 tests written in 5.1
    - Verify job dispatching and progress tracking
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 5.1 pass
- Large imports (100+ rows) process asynchronously via queue
- Small imports process synchronously for immediate feedback
- Progress tracking enables UI polling for status
- Error reports generated and accessible for 24 hours
- Partial imports work (valid rows succeed, invalid rows fail)

---

### API Layer

#### Task Group 6: API Endpoints and Controllers
**Dependencies:** Task Groups 3, 4, 5

- [x] 6.0 Complete API layer for bulk imports
  - [x] 6.1 Write 4-6 focused tests for API endpoints
    - Test file upload endpoint accepts CSV and XLSX
    - Test file validation rejects invalid formats and oversized files
    - Test import status polling endpoint returns correct progress
    - Test error report download endpoint
    - Test template download endpoints for each entity type
    - Test authorization (only Admin/IT Manager can import)
  - [x] 6.2 Create BulkImportController
    - Location: `app/Http/Controllers/BulkImportController.php`
    - `index()`: List user's import history with pagination
    - `create()`: Show the import form page (Inertia render)
    - `store()`: Handle file upload and initiate import
    - `show()`: Get import status and progress for polling
    - `downloadErrors()`: Download error report CSV
    - Follow authorization pattern from existing controllers (Admin, IT Manager)
  - [x] 6.3 Create StoreBulkImportRequest
    - Location: `app/Http/Requests/StoreBulkImportRequest.php`
    - Validate file: required, mimes:csv,xlsx, max:10240 (10MB)
    - Validate entity_type: optional, in:datacenter,room,row,rack,device,port,mixed
    - Follow pattern from existing Form Requests
    - Include custom error messages
  - [x] 6.4 Add routes for bulk import endpoints
    - Location: `routes/web.php`
    - GET /imports - index
    - GET /imports/create - create
    - POST /imports - store
    - GET /imports/{bulkImport} - show (for polling)
    - GET /imports/{bulkImport}/errors - downloadErrors
    - Apply auth and role middleware
  - [x] 6.5 Create BulkImportResource for API responses
    - Location: `app/Http/Resources/BulkImportResource.php`
    - Include: id, entity_type, file_name, status, total_rows, processed_rows, success_count, failure_count, progress_percentage, has_errors, created_at, completed_at
  - [x] 6.6 Ensure API layer tests pass
    - Run ONLY the 4-6 tests written in 6.1
    - Verify all endpoints work correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 6.1 pass
- File upload validates format, MIME type, and size
- Import status polling returns real-time progress
- Error report downloads work when failures exist
- Template downloads provide valid XLSX files
- Authorization enforced consistently

---

### Frontend Components Layer

#### Task Group 7: Vue Components for Import UI
**Dependencies:** Task Group 6

- [x] 7.0 Complete frontend UI for bulk imports
  - [x] 7.1 Write 4-6 focused tests for UI components
    - Test Import page renders with file upload dropzone
    - Test template download buttons are present and functional
    - Test file upload triggers import and shows progress
    - Test error summary displays on import completion
    - Test error report download button appears when errors exist
    - Test import history table displays past imports
  - [x] 7.2 Create BulkImport/Index.vue page
    - Location: `resources/js/Pages/BulkImport/Index.vue`
    - Follow layout pattern from Devices/Index.vue
    - Use AppLayout wrapper with breadcrumbs
    - Display import history table with status badges
    - Include "New Import" button linking to create page
    - Show import status with progress percentage
    - Include download error report action when available
  - [x] 7.3 Create BulkImport/Create.vue page
    - Location: `resources/js/Pages/BulkImport/Create.vue`
    - File upload dropzone with drag-and-drop support
    - Accept only .csv and .xlsx files
    - Display file size limit (10MB max)
    - Entity type selector (optional, auto-detect if not specified)
    - Template download buttons for each entity type
    - Combined template download button
    - Submit button to initiate import
  - [x] 7.4 Create ImportProgress.vue component
    - Location: `resources/js/components/imports/ImportProgress.vue`
    - Display progress bar with percentage
    - Show processed rows / total rows
    - Poll for status updates using Inertia polling or manual fetch
    - Display completion status (success/partial/failed)
    - Show success_count and failure_count on completion
  - [x] 7.5 Create ImportErrorSummary.vue component
    - Location: `resources/js/components/imports/ImportErrorSummary.vue`
    - Display count of failed rows
    - Download error report button
  - [x] 7.6 Create FileDropzone.vue component
    - Location: `resources/js/components/imports/FileDropzone.vue`
    - Drag-and-drop file upload area
    - Click to browse files
    - File type and size validation before upload
    - Display selected file name and size
    - Clear/remove selected file option
  - [x] 7.7 Add navigation link for Imports
    - Add to sidebar navigation
    - Follow existing navigation patterns
  - [x] 7.8 Ensure UI component tests pass
    - Run ONLY the 4-6 tests written in 7.1
    - Verify components render and interact correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 7.1 pass
- Import page accessible from navigation
- File upload works with drag-and-drop
- Templates downloadable for all entity types
- Progress indicator shows real-time updates for async imports
- Error summary and download available when failures occur
- Import history shows past imports with status

---

### Testing

#### Task Group 8: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-7

- [x] 8.0 Review existing tests and fill critical gaps only
  - [x] 8.1 Review tests from Task Groups 1-7
    - Review the 4-6 tests from database-layer (Task 1.1)
    - Review the 6-8 tests from import-classes (Task 3.1)
    - Review the 4-6 tests from template-generation (Task 4.1)
    - Review the 4-6 tests from queue-processing (Task 5.1)
    - Review the 4-6 tests from api-layer (Task 6.1)
    - Review the 4-6 tests from ui-components (Task 7.1)
    - Total existing tests: approximately 28-38 tests
  - [x] 8.2 Analyze test coverage gaps for bulk import feature only
    - Identify critical user workflows that lack test coverage
    - Focus ONLY on gaps related to this spec's feature requirements
    - Do NOT assess entire application test coverage
    - Prioritize end-to-end import workflows
  - [x] 8.3 Write up to 10 additional strategic tests maximum
    - End-to-end test: Upload file -> async process -> poll status -> download errors
    - End-to-end test: Upload small file -> sync process -> verify created entities
    - Integration test: Combined import with multiple entity types in correct order
    - Test: Duplicate entity handling (skip if exists by name)
    - Test: Rack placement conflict detection
    - Test: DeviceType lookup by name
    - Do NOT write comprehensive coverage for all edge cases
  - [x] 8.4 Run feature-specific tests only
    - Run ONLY tests related to bulk import feature (tests from groups 1-7 and 8.3)
    - Expected total: approximately 38-48 tests maximum
    - Do NOT run the entire application test suite
    - Verify all critical import workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 38-48 tests total)
- Critical import workflows for all entity types are covered
- No more than 10 additional tests added when filling gaps
- Testing focused exclusively on bulk import feature requirements

---

## Execution Order

Recommended implementation sequence:

1. **Task Group 1: Database Layer** - Create BulkImport model and migration
2. **Task Group 2: Package Installation** - Install Laravel Excel (can run in parallel with Group 1)
3. **Task Group 3: Import Classes** - Create entity import classes with validation
4. **Task Group 4: Template Generation** - Create XLSX templates with enum dropdowns
5. **Task Group 5: Queue Processing** - Implement async job processing
6. **Task Group 6: API Layer** - Create controllers and endpoints
7. **Task Group 7: Frontend Components** - Build Vue UI components
8. **Task Group 8: Test Review** - Fill critical test coverage gaps

## Key Files Created

**Models:**
- `/Users/helderdene/rackaudit/app/Models/BulkImport.php`

**Migrations:**
- `/Users/helderdene/rackaudit/database/migrations/YYYY_MM_DD_HHMMSS_create_bulk_imports_table.php`

**Import Classes:**
- `/Users/helderdene/rackaudit/app/Imports/AbstractEntityImport.php`
- `/Users/helderdene/rackaudit/app/Imports/DatacenterImport.php`
- `/Users/helderdene/rackaudit/app/Imports/RoomImport.php`
- `/Users/helderdene/rackaudit/app/Imports/RowImport.php`
- `/Users/helderdene/rackaudit/app/Imports/RackImport.php`
- `/Users/helderdene/rackaudit/app/Imports/DeviceImport.php`
- `/Users/helderdene/rackaudit/app/Imports/PortImport.php`
- `/Users/helderdene/rackaudit/app/Imports/CombinedImport.php`

**Template Exports:**
- `/Users/helderdene/rackaudit/app/Exports/Templates/AbstractTemplateExport.php`
- `/Users/helderdene/rackaudit/app/Exports/Templates/DatacenterTemplateExport.php`
- `/Users/helderdene/rackaudit/app/Exports/Templates/RoomTemplateExport.php`
- `/Users/helderdene/rackaudit/app/Exports/Templates/RowTemplateExport.php`
- `/Users/helderdene/rackaudit/app/Exports/Templates/RackTemplateExport.php`
- `/Users/helderdene/rackaudit/app/Exports/Templates/DeviceTemplateExport.php`
- `/Users/helderdene/rackaudit/app/Exports/Templates/PortTemplateExport.php`
- `/Users/helderdene/rackaudit/app/Exports/Templates/CombinedTemplateExport.php`

**Jobs:**
- `/Users/helderdene/rackaudit/app/Jobs/ProcessBulkImportJob.php`

**Services:**
- `/Users/helderdene/rackaudit/app/Services/BulkImportService.php`
- `/Users/helderdene/rackaudit/app/Services/ImportErrorReportService.php`

**Commands:**
- `/Users/helderdene/rackaudit/app/Console/Commands/CleanupExpiredImportErrorReports.php`

**Controllers:**
- `/Users/helderdene/rackaudit/app/Http/Controllers/BulkImportController.php`
- `/Users/helderdene/rackaudit/app/Http/Controllers/TemplateDownloadController.php`

**Requests:**
- `/Users/helderdene/rackaudit/app/Http/Requests/StoreBulkImportRequest.php`

**Resources:**
- `/Users/helderdene/rackaudit/app/Http/Resources/BulkImportResource.php`

**Vue Pages:**
- `/Users/helderdene/rackaudit/resources/js/Pages/BulkImport/Index.vue`
- `/Users/helderdene/rackaudit/resources/js/Pages/BulkImport/Create.vue`
- `/Users/helderdene/rackaudit/resources/js/Pages/BulkImport/Show.vue`

**Vue Components:**
- `/Users/helderdene/rackaudit/resources/js/components/imports/ImportProgress.vue`
- `/Users/helderdene/rackaudit/resources/js/components/imports/ImportErrorSummary.vue`
- `/Users/helderdene/rackaudit/resources/js/components/imports/FileDropzone.vue`

**Test Files:**
- `/Users/helderdene/rackaudit/tests/Feature/BulkImport/BulkImportModelTest.php`
- `/Users/helderdene/rackaudit/tests/Feature/BulkImport/BulkImportApiTest.php`
- `/Users/helderdene/rackaudit/tests/Feature/BulkImport/BulkImportFrontendTest.php`
- `/Users/helderdene/rackaudit/tests/Feature/BulkImport/Integration/BulkImportEndToEndTest.php`
- `/Users/helderdene/rackaudit/tests/Feature/LaravelExcel/LaravelExcelPackageTest.php`
- `/Users/helderdene/rackaudit/tests/Feature/Import/EntityImportValidationTest.php`
- `/Users/helderdene/rackaudit/tests/Feature/TemplateExport/TemplateGenerationTest.php`
- `/Users/helderdene/rackaudit/tests/Feature/QueueProcessing/BulkImportQueueTest.php`

## Technical Notes

### Enums to Reference
All enum classes are located in `/Users/helderdene/rackaudit/app/Enums/`:
- RoomType, RowOrientation, RowStatus
- RackStatus, RackUHeight
- DeviceLifecycleStatus, DeviceDepth, DeviceWidthType, DeviceRackFace
- PortType, PortSubtype, PortStatus, PortDirection, PortVisualFace

### Existing Patterns to Follow
- Form Request validation: See `StoreDeviceRequest.php`, `StorePortRequest.php` for enum validation patterns
- Model structure: See `Device.php` for casts, relationships, and boot method patterns
- Vue page layout: See `Devices/Index.vue` for table layout, filtering, and pagination
- Controller authorization: Use `hasAnyRole(['Administrator', 'IT Manager'])` pattern
- UI components: Use existing components from `/resources/js/components/ui/`

### Queue Configuration
- Database queue driver is configured in `config/queue.php`
- Use default connection for ProcessBulkImportJob
- Jobs folder does not exist yet - create at `/Users/helderdene/rackaudit/app/Jobs/`
