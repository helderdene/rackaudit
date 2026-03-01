# Task Breakdown: Bulk Export Functionality

## Overview
Total Tasks: 41

This feature enables users to export datacenter infrastructure data (Datacenters, Rooms, Rows, Racks, Devices, Ports) to CSV/XLSX files for backup and external reporting, with hierarchical filtering and background processing for large datasets.

## Task List

### Database Layer

#### Task Group 1: BulkExport Model and Migration
**Dependencies:** None
**Complexity:** Medium

- [x] 1.0 Complete BulkExport model and migration
  - [x] 1.1 Write 4-6 focused tests for BulkExport model functionality
    - Test model creation with required fields
    - Test user relationship
    - Test progress percentage accessor (including zero division case)
    - Test status and entity_type enum casting
    - Test filters JSON casting
  - [x] 1.2 Create BulkExportStatus enum
    - Values: Pending, Processing, Completed, Failed
    - Add label() method mirroring BulkImportStatus pattern
    - Location: `app/Enums/BulkExportStatus.php`
  - [x] 1.3 Create BulkExport migration
    - Fields: id, user_id (FK), entity_type, status, file_name, file_path, total_rows, processed_rows, format (csv/xlsx), filters (JSON), started_at, completed_at, timestamps
    - Add indexes for: user_id, status, created_at
    - Foreign key: user_id references users(id) with cascade delete
    - Location: `database/migrations/xxxx_create_bulk_exports_table.php`
  - [x] 1.4 Create BulkExport model
    - Mirror BulkImport model structure
    - Fillable fields matching migration columns
    - Cast entity_type to BulkImportEntityType enum (reuse from imports)
    - Cast status to BulkExportStatus enum
    - Cast format to string, filters to array
    - Cast total_rows, processed_rows to integer
    - Cast started_at, completed_at to datetime
    - Add user() BelongsTo relationship
    - Add getProgressPercentageAttribute() accessor
    - Location: `app/Models/BulkExport.php`
  - [x] 1.5 Create BulkExport factory
    - Define states for each status (pending, processing, completed, failed)
    - Include realistic filter JSON examples
    - Location: `database/factories/BulkExportFactory.php`
  - [x] 1.6 Run migration and verify model tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify migration runs successfully

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- BulkExport model created with all required fields and casts
- Migration runs successfully
- User relationship works correctly
- Progress percentage calculation handles edge cases

---

### Service Layer

#### Task Group 2: BulkExportService and Job Processing
**Dependencies:** Task Group 1
**Complexity:** High

- [x] 2.0 Complete BulkExportService and background job
  - [x] 2.1 Write 5-7 focused tests for export service and job
    - Test small export processes synchronously (under 100 rows threshold)
    - Test large export dispatches ProcessBulkExportJob
    - Test hierarchical filter application (e.g., datacenter_id filters devices)
    - Test export file generation with correct format (CSV/XLSX)
    - Test job marks status correctly (processing, completed, failed)
    - Test chunk processing updates progress
  - [x] 2.2 Create BulkExportService
    - Constructor: no dependencies needed initially
    - Method: initiateExport(User $user, BulkImportEntityType $entityType, string $format, array $filters = []): BulkExport
    - Method: buildExportQuery(BulkImportEntityType $entityType, array $filters): Builder
    - Method: applyHierarchicalFilters(Builder $query, array $filters): Builder
    - Use async threshold of 100 rows (mirror BulkImportService)
    - Dispatch ProcessBulkExportJob for large exports
    - Process synchronously for small exports
    - Store files in `storage/app/exports/` with UUID prefix
    - Location: `app/Services/BulkExportService.php`
  - [x] 2.3 Create ProcessBulkExportJob
    - Implement ShouldQueue interface
    - Method: handle(): void
    - Method: markAsProcessing(): void
    - Method: markAsCompleted(): void
    - Method: markAsFailed(string $message): void
    - Process data in chunks of 1000 rows
    - Update processed_rows after each chunk
    - Use Laravel Excel for file generation
    - Handle both CSV and XLSX formats
    - Location: `app/Jobs/ProcessBulkExportJob.php`
  - [x] 2.4 Implement file cleanup for old exports
    - Create Artisan command: `php artisan exports:cleanup`
    - Delete exports older than 7 days
    - Remove both database records and files
    - Location: `app/Console/Commands/CleanupOldExports.php`
  - [x] 2.5 Ensure service layer tests pass
    - Run ONLY the 5-7 tests written in 2.1
    - Verify sync and async processing works
    - Verify hierarchical filters apply correctly

**Acceptance Criteria:**
- The 5-7 tests written in 2.1 pass
- Small exports process synchronously
- Large exports process via background job
- Hierarchical filtering works correctly
- Export files are generated in correct format
- Progress updates during chunk processing

---

### Export Classes

#### Task Group 3: Entity Export Classes
**Dependencies:** Task Group 2
**Complexity:** Medium

- [x] 3.0 Complete entity export classes
  - [x] 3.1 Write 4-6 focused tests for export classes
    - Test DatacenterExport generates correct columns
    - Test DeviceExport applies datacenter filter correctly
    - Test export headings match template export headings
    - Test XLSX styling is applied (header colors)
    - Test data values are correctly formatted
  - [x] 3.2 Create AbstractDataExport base class
    - Extend AbstractTemplateExport to reuse styling and column definitions
    - Accept collection or query builder as data source
    - Override collection() method to return actual data
    - Location: `app/Exports/AbstractDataExport.php`
  - [x] 3.3 Create DatacenterExport class
    - Extend AbstractDataExport
    - Accept optional filter for specific datacenter IDs
    - Reuse column definitions from DatacenterTemplateExport
    - Location: `app/Exports/DatacenterExport.php`
  - [x] 3.4 Create RoomExport class
    - Extend AbstractDataExport
    - Support filtering by datacenter_id
    - Reuse column definitions from RoomTemplateExport
    - Location: `app/Exports/RoomExport.php`
  - [x] 3.5 Create RowExport class
    - Extend AbstractDataExport
    - Support filtering by datacenter_id, room_id
    - Reuse column definitions from RowTemplateExport
    - Location: `app/Exports/RowExport.php`
  - [x] 3.6 Create RackExport class
    - Extend AbstractDataExport
    - Support filtering by datacenter_id, room_id, row_id
    - Reuse column definitions from RackTemplateExport
    - Location: `app/Exports/RackExport.php`
  - [x] 3.7 Create DeviceExport class
    - Extend AbstractDataExport
    - Support filtering by datacenter_id, room_id, row_id, rack_id
    - Reuse column definitions from DeviceTemplateExport
    - Use eager loading to prevent N+1 (rack.row.room.datacenter)
    - Location: `app/Exports/DeviceExport.php`
  - [x] 3.8 Create PortExport class
    - Extend AbstractDataExport
    - Support filtering by datacenter_id, room_id, row_id, rack_id
    - Reuse column definitions from PortTemplateExport
    - Use eager loading (device.rack.row.room.datacenter)
    - Location: `app/Exports/PortExport.php`
  - [x] 3.9 Create CombinedDataExport class for multi-sheet XLSX
    - Implement WithMultipleSheets from Laravel Excel
    - Include sheets for all entity types with consistent filtering
    - Location: `app/Exports/CombinedDataExport.php`
  - [x] 3.10 Ensure export class tests pass
    - Run ONLY the 4-6 tests written in 3.1
    - Verify column definitions match templates
    - Verify filtering works correctly

**Acceptance Criteria:**
- The 4-6 tests written in 3.1 pass
- All entity export classes created
- Column definitions match import templates exactly
- Hierarchical filtering works across all entities
- Eager loading prevents N+1 queries
- XLSX styling matches template exports

---

### API Layer

#### Task Group 4: BulkExportController and Routes
**Dependencies:** Task Groups 1, 2, 3
**Complexity:** Medium

- [x] 4.0 Complete BulkExportController and routing
  - [x] 4.1 Write 5-7 focused tests for controller endpoints
    - Test index returns paginated export history
    - Test create returns form with entity type and format options
    - Test store initiates export and redirects to show
    - Test show returns export status with progress
    - Test download returns file stream for completed exports
    - Test unauthorized users receive 403 response
    - Test only export owner (or Administrator) can access
  - [x] 4.2 Create StoreBulkExportRequest form request
    - Validate entity_type is valid BulkImportEntityType value
    - Validate format is 'csv' or 'xlsx'
    - Validate optional filter IDs (datacenter_id, room_id, row_id, rack_id)
    - Location: `app/Http/Requests/StoreBulkExportRequest.php`
  - [x] 4.3 Create BulkExportResource
    - Include all model fields
    - Include progress_percentage computed field
    - Include user relationship data
    - Include formatted dates
    - Include download_url when completed
    - Location: `app/Http/Resources/BulkExportResource.php`
  - [x] 4.4 Create BulkExportController
    - Reuse ADMIN_ROLES constant pattern from BulkImportController
    - Inject BulkExportService in constructor
    - Method: index(Request $request): InertiaResponse|JsonResponse
    - Method: create(Request $request): InertiaResponse
    - Method: store(StoreBulkExportRequest $request): RedirectResponse|JsonResponse
    - Method: show(Request $request, BulkExport $export): InertiaResponse|JsonResponse
    - Method: download(Request $request, BulkExport $export): StreamedResponse|JsonResponse
    - Private: authorizeAccess(), authorizeExportAccess()
    - Return hierarchical filter options (datacenters, rooms, rows, racks)
    - Location: `app/Http/Controllers/BulkExportController.php`
  - [x] 4.5 Register routes in web.php
    - Route::get('/exports', [BulkExportController::class, 'index'])->name('exports.index')
    - Route::get('/exports/create', [BulkExportController::class, 'create'])->name('exports.create')
    - Route::post('/exports', [BulkExportController::class, 'store'])->name('exports.store')
    - Route::get('/exports/{bulkExport}', [BulkExportController::class, 'show'])->name('exports.show')
    - Route::get('/exports/{bulkExport}/download', [BulkExportController::class, 'download'])->name('exports.download')
    - Apply auth middleware
  - [x] 4.6 Run php artisan wayfinder:generate
    - Regenerate TypeScript route helpers
  - [x] 4.7 Ensure controller tests pass
    - Run ONLY the 5-7 tests written in 4.1
    - Verify all endpoints work correctly
    - Verify authorization is enforced

**Acceptance Criteria:**
- The 5-7 tests written in 4.1 pass
- All CRUD endpoints work correctly
- Role-based authorization enforced (Administrator, IT Manager only)
- JSON responses for API polling
- Inertia responses for web requests
- File download works for completed exports

---

### Frontend Layer

#### Task Group 5: Vue Pages and Components
**Dependencies:** Task Group 4
**Complexity:** High

- [x] 5.0 Complete Vue pages for bulk exports
  - [x] 5.1 Write 4-6 focused tests for Vue components
    - Test Index page renders export history table
    - Test Create page submits form with correct data
    - Test Show page polls for status updates
    - Test download button appears when export is completed
    - Test hierarchical filter dropdowns cascade correctly
  - [x] 5.2 Create BulkExport/Index.vue
    - Mirror BulkImport/Index.vue structure
    - Display export history table with columns: Entity Type, Format, Status, Progress, Created, Actions
    - Use Badge component for status display
    - Use pagination component
    - Add "New Export" button linking to create page
    - Location: `resources/js/Pages/BulkExport/Index.vue`
  - [x] 5.3 Create BulkExport/Create.vue
    - Entity type selector (radio buttons or dropdown)
    - Format selector (CSV/XLSX radio buttons)
    - Hierarchical filter dropdowns (Datacenter -> Room -> Row -> Rack)
    - Cascade filters: selecting datacenter loads its rooms, etc.
    - Submit button initiates export
    - Use existing form components and patterns
    - Location: `resources/js/Pages/BulkExport/Create.vue`
  - [x] 5.4 Create BulkExport/Show.vue
    - Mirror BulkImport/Show.vue structure
    - Display export status with icon (CheckCircle, XCircle, Loader2, Clock)
    - Show progress bar during processing
    - Poll for status every 5 seconds while processing
    - Show download button when completed
    - Display error message if failed
    - Show applied filters for reference
    - Location: `resources/js/Pages/BulkExport/Show.vue`
  - [x] 5.5 Add Exports item to AppSidebar.vue
    - Add after "Imports" menu item
    - Use Download icon (from lucide-vue-next)
    - Apply same role restrictions (Administrator, IT Manager)
    - Route to exports.index
  - [x] 5.6 Add Export quick-action buttons to entity index pages
    - Add to Devices/Index.vue, Racks/Index.vue, Datacenters/Index.vue, etc.
    - Button appears in header actions area
    - Pre-selects entity type when navigating to exports/create
    - Passes current filter context if any
  - [x] 5.7 Apply base styles matching BulkImport pages
    - Use consistent spacing, typography, colors
    - Follow existing Tailwind patterns
    - Support dark mode if other pages do
  - [x] 5.8 Run npm run build and ensure tests pass
    - Run ONLY the 4-6 tests written in 5.1
    - Verify all pages render correctly
    - Verify navigation works

**Acceptance Criteria:**
- The 4-6 tests written in 5.1 pass
- All Vue pages render correctly
- Export history displays with proper formatting
- Create form submits correctly with hierarchical filters
- Show page polls and updates status
- Download button works for completed exports
- Navigation item appears in sidebar
- Export buttons appear on entity index pages

---

### Testing

#### Task Group 6: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-5
**Complexity:** Medium

- [x] 6.0 Review existing tests and fill critical gaps only
  - [x] 6.1 Review tests from Task Groups 1-5
    - Review the 4-6 tests from database layer (Task 1.1)
    - Review the 5-7 tests from service layer (Task 2.1)
    - Review the 4-6 tests from export classes (Task 3.1)
    - Review the 5-7 tests from controller (Task 4.1)
    - Review the 4-6 tests from Vue components (Task 5.1)
    - Total existing tests: approximately 22-32 tests
  - [x] 6.2 Analyze test coverage gaps for bulk export feature only
    - Identify critical end-to-end workflows lacking coverage
    - Check integration between service, job, and export classes
    - Verify hierarchical filter edge cases are covered
    - Focus ONLY on gaps related to this spec's requirements
    - Do NOT assess entire application test coverage
  - [x] 6.3 Write up to 10 additional strategic tests maximum
    - End-to-end test: create export -> process -> download
    - Integration test: hierarchical filters applied correctly across entities
    - Edge case: export with empty result set
    - Edge case: export with maximum filter depth (all filters applied)
    - Concurrent export handling
    - File cleanup command removes old files correctly
    - Do NOT write comprehensive coverage for all scenarios
  - [x] 6.4 Run feature-specific tests only
    - Run ONLY tests related to bulk export feature
    - Expected total: approximately 32-42 tests maximum
    - Verify critical workflows pass
    - Do NOT run the entire application test suite

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 32-42 tests total)
- Critical end-to-end workflows covered
- No more than 10 additional tests added
- Testing focused exclusively on bulk export feature
- Hierarchical filtering edge cases verified

---

## Execution Order

Recommended implementation sequence:

1. **Database Layer (Task Group 1)** - Foundation: model, migration, enum
2. **Service Layer (Task Group 2)** - Core logic: export service, background job, cleanup
3. **Export Classes (Task Group 3)** - Data transformation: entity-specific export classes
4. **API Layer (Task Group 4)** - Endpoints: controller, routes, form request
5. **Frontend Layer (Task Group 5)** - UI: Vue pages, navigation, quick actions
6. **Test Review (Task Group 6)** - Quality: gap analysis, integration tests

---

## Files to Create

| File Path | Task |
|-----------|------|
| `app/Enums/BulkExportStatus.php` | 1.2 |
| `database/migrations/xxxx_create_bulk_exports_table.php` | 1.3 |
| `app/Models/BulkExport.php` | 1.4 |
| `database/factories/BulkExportFactory.php` | 1.5 |
| `app/Services/BulkExportService.php` | 2.2 |
| `app/Jobs/ProcessBulkExportJob.php` | 2.3 |
| `app/Console/Commands/CleanupOldExports.php` | 2.4 |
| `app/Exports/AbstractDataExport.php` | 3.2 |
| `app/Exports/DatacenterExport.php` | 3.3 |
| `app/Exports/RoomExport.php` | 3.4 |
| `app/Exports/RowExport.php` | 3.5 |
| `app/Exports/RackExport.php` | 3.6 |
| `app/Exports/DeviceExport.php` | 3.7 |
| `app/Exports/PortExport.php` | 3.8 |
| `app/Exports/CombinedDataExport.php` | 3.9 |
| `app/Http/Requests/StoreBulkExportRequest.php` | 4.2 |
| `app/Http/Resources/BulkExportResource.php` | 4.3 |
| `app/Http/Controllers/BulkExportController.php` | 4.4 |
| `resources/js/Pages/BulkExport/Index.vue` | 5.2 |
| `resources/js/Pages/BulkExport/Create.vue` | 5.3 |
| `resources/js/Pages/BulkExport/Show.vue` | 5.4 |

## Files to Modify

| File Path | Task |
|-----------|------|
| `routes/web.php` | 4.5 |
| `resources/js/Components/AppSidebar.vue` | 5.5 |
| `resources/js/Pages/Devices/Index.vue` | 5.6 |
| `resources/js/Pages/Racks/Index.vue` | 5.6 |
| `resources/js/Pages/Datacenters/Index.vue` | 5.6 |
| `resources/js/Pages/Rooms/Index.vue` | 5.6 |
| `resources/js/Pages/Rows/Index.vue` | 5.6 |
| `resources/js/Pages/Ports/Index.vue` | 5.6 |

## Key Patterns to Follow

- **BulkImportController**: Authorization, role checks, JSON/Inertia responses
- **BulkImportService**: Async threshold (100 rows), job dispatching, file storage
- **BulkImport Model**: Field structure, casts, relationships, progress accessor
- **AbstractTemplateExport**: Column definitions, styling, header formatting
- **ProcessBulkImportJob**: Job structure, status methods, progress updates
- **BulkImport Vue Pages**: Table structure, status badges, pagination, polling
