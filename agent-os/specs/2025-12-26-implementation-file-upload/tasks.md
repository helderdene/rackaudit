# Task Breakdown: Implementation File Upload

## Overview
Total Tasks: 4 Task Groups with 31 Sub-tasks

This feature enables users to upload and manage implementation specification documents (PDF, Excel, CSV, Word, text) at the datacenter level. Implementation files serve as the authoritative source for expected connections that can be compared against actual documented connections during audits.

## Task List

### Database Layer

#### Task Group 1: ImplementationFile Model and Migration
**Dependencies:** None

- [x] 1.0 Complete database layer for ImplementationFile
  - [x] 1.1 Write 2-8 focused tests for ImplementationFile model functionality
    - Test model creation with valid attributes
    - Test BelongsTo relationship with Datacenter
    - Test BelongsTo relationship with User (uploaded_by)
    - Test BelongsTo relationship with User (replaced_by)
    - Test formatted_file_size accessor (bytes to KB/MB)
    - Test file_type_label accessor for human-readable type
    - Test soft delete functionality
  - [x] 1.2 Create ImplementationFile model with artisan make:model
    - Fields: `datacenter_id`, `file_name`, `original_name`, `description`, `file_path`, `file_size`, `mime_type`, `uploaded_by`, `replaced_at`, `replaced_by`
    - Use `HasFactory` and `Loggable` traits
    - Use `SoftDeletes` trait for history preservation
    - Reuse pattern from: `app/Models/Datacenter.php`
  - [x] 1.3 Define fillable attributes on model
    - Include: `datacenter_id`, `file_name`, `original_name`, `description`, `file_path`, `file_size`, `mime_type`, `uploaded_by`, `replaced_at`, `replaced_by`
  - [x] 1.4 Set up model relationships
    - `datacenter()`: BelongsTo Datacenter
    - `uploader()`: BelongsTo User (uploaded_by foreign key)
    - `replacer()`: BelongsTo User (replaced_by foreign key, nullable)
  - [x] 1.5 Add model accessors
    - `formattedFileSize`: Convert bytes to KB/MB string (e.g., "2.5 MB")
    - `fileTypeLabel`: Human-readable file type from MIME type (e.g., "PDF Document", "Excel Spreadsheet")
  - [x] 1.6 Create migration for implementation_files table
    - `id` - primary key
    - `datacenter_id` - foreign key to datacenters, cascadeOnDelete
    - `file_name` - string (UUID-based stored filename)
    - `original_name` - string (user's original filename)
    - `description` - text, nullable (max 500 chars)
    - `file_path` - string (full storage path)
    - `file_size` - unsignedBigInteger (bytes)
    - `mime_type` - string (e.g., application/pdf)
    - `uploaded_by` - foreign key to users, nullOnDelete
    - `replaced_at` - timestamp, nullable
    - `replaced_by` - foreign key to users, nullable, nullOnDelete
    - `deleted_at` - timestamp, nullable (soft deletes)
    - `timestamps`
    - Add indexes on: `datacenter_id`, `uploaded_by`, `created_at`, `original_name`
  - [x] 1.7 Create model factory for ImplementationFile
    - Generate realistic test data for all fields
    - Include states for different file types (pdf, xlsx, docx, etc.)
    - Include state for replaced files
  - [x] 1.8 Add HasMany relationship to Datacenter model
    - Add `implementationFiles()` method returning HasMany
  - [x] 1.9 Ensure database layer tests pass
    - Run ONLY the tests written in 1.1
    - Verify migrations run successfully with `php artisan migrate:fresh`
    - Verify associations work correctly

**Acceptance Criteria:**
- The 2-8 tests written in 1.1 pass
- ImplementationFile model has all relationships properly defined
- Migration creates table with correct structure and indexes
- Factory generates valid test data
- Datacenter model has implementationFiles relationship

### API Layer

#### Task Group 2: ImplementationFileController and Authorization
**Dependencies:** Task Group 1

- [x] 2.0 Complete API layer for implementation files
  - [x] 2.1 Write 2-8 focused tests for API endpoints
    - Test index returns paginated files for a datacenter
    - Test store creates file record and stores file on disk
    - Test store replaces existing file with same original_name
    - Test download streams file with authentication
    - Test preview serves PDF inline
    - Test destroy soft-deletes record and removes file
    - Test authorization (Admin/IT Manager can upload/delete, Operator/Auditor read-only)
    - Test users must have datacenter access to access files
  - [x] 2.2 Create ImplementationFilePolicy
    - Follow pattern from: `app/Policies/DatacenterPolicy.php`
    - Use `ADMIN_ROLES` constant for Administrator and IT Manager
    - `viewAny`: User must have access to parent datacenter
    - `view`: User must have access to parent datacenter
    - `create`: Admin/IT Manager only + datacenter access
    - `update`: Admin/IT Manager only + datacenter access
    - `delete`: Admin/IT Manager only + datacenter access
    - `download`: User must have access to parent datacenter
  - [x] 2.3 Create StoreImplementationFileRequest form request
    - Follow pattern from: `app/Http/Requests/StoreBulkImportRequest.php`
    - Authorize: Admin/IT Manager roles only
    - Validation rules:
      - `file`: required, file, mimes:pdf,xlsx,xls,csv,docx,txt, max:10240
      - `description`: nullable, string, max:500
    - Custom error messages for file type and size validation
    - Server-side MIME type validation in addition to extension
  - [x] 2.4 Create ImplementationFileController with artisan
    - Inject Datacenter via route model binding (nested routes)
    - Follow pattern from: `app/Http/Controllers/BulkImportController.php`
  - [x] 2.5 Implement index action
    - Return paginated list of files for datacenter
    - Include relationships: uploader, replacer
    - Order by created_at descending
    - Support both JSON and Inertia responses
    - Authorize via ImplementationFilePolicy
  - [x] 2.6 Implement store action
    - Validate via StoreImplementationFileRequest
    - Generate UUID-based filename to prevent collisions
    - Store file in `implementation-files/{datacenter_id}/` on private disk
    - Check for existing file with same original_name (replacement logic)
    - If replacing: update replaced_at/replaced_by, delete old file
    - Create/update ImplementationFile record
    - Return success response with file data
  - [x] 2.7 Implement show action
    - Return file details including signed download URL
    - Authorize via ImplementationFilePolicy
  - [x] 2.8 Implement download action
    - Stream file with proper Content-Disposition: attachment header
    - Use original_name for download filename
    - Authorize via ImplementationFilePolicy
  - [x] 2.9 Implement preview action (PDF only)
    - Serve PDF files with Content-Disposition: inline header
    - Return 415 Unsupported Media Type for non-PDF files
    - Authorize via ImplementationFilePolicy
  - [x] 2.10 Implement destroy action
    - Soft-delete the ImplementationFile record
    - Delete file from storage
    - Authorize via ImplementationFilePolicy
  - [x] 2.11 Create ImplementationFileResource for API responses
    - Include: id, file_name, original_name, description, mime_type, formatted_file_size, file_type_label, uploader (name only), replaced_at, replaced_by, created_at, download_url, preview_url (if PDF)
  - [x] 2.12 Register routes nested under datacenter
    - Route group: `/datacenters/{datacenter}/implementation-files`
    - Routes: index, store, show, download, preview, destroy
    - Apply auth middleware
  - [x] 2.13 Ensure API layer tests pass
    - Run ONLY the tests written in 2.1
    - Verify all CRUD operations work correctly
    - Verify authorization is enforced properly

**Acceptance Criteria:**
- The 2-8 tests written in 2.1 pass
- All CRUD operations work with proper authorization
- File upload stores files securely on private disk
- File replacement logic updates existing records correctly
- PDF preview serves inline, other files force download
- Routes are properly nested under datacenter

### Frontend Layer

#### Task Group 3: UI Components and Pages
**Dependencies:** Task Group 2

- [x] 3.0 Complete UI components for implementation files
  - [x] 3.1 Write 2-8 focused tests for UI components
    - Test file list renders with correct columns
    - Test upload dialog opens and accepts files
    - Test file type icons display correctly
    - Test action buttons respect user permissions
    - Test empty state displays when no files
    - Test file download initiates correctly
    - Test PDF preview opens in new tab/modal
    - Test delete confirmation and action works
  - [x] 3.2 Create ImplementationFileList component
    - Location: `resources/js/components/implementation-files/ImplementationFileList.vue`
    - Display table with columns: Name, Type (icon), Size, Uploaded By, Date, Actions
    - Props: files array, canUpload boolean, canDelete boolean, datacenterId
    - Use file type icons (PDF, spreadsheet, document, text file)
    - Display formatted file size from API
    - Show relative time for upload date (e.g., "2 hours ago")
    - Action buttons: View/Download, Preview (PDF only), Delete
    - Reuse Table pattern from existing list pages
  - [x] 3.3 Create UploadImplementationFileDialog component
    - Location: `resources/js/components/implementation-files/UploadImplementationFileDialog.vue`
    - Use Dialog/Modal component from UI library
    - Integrate FileDropzone component with extended file types
    - Configure acceptedTypes: `.pdf`, `.xlsx`, `.xls`, `.csv`, `.docx`, `.txt`
    - Add description textarea input (optional, max 500 chars)
    - Show upload progress indicator
    - Handle success/error states
    - Close dialog on successful upload
    - Emit events: file-uploaded
  - [x] 3.4 Create ImplementationFileCard component for Datacenter Show page
    - Location: `resources/js/components/implementation-files/ImplementationFileCard.vue`
    - Use Card component matching existing datacenter page cards
    - Header with "Implementation Files" title and Upload button (if canUpload)
    - Include ImplementationFileList component
    - Show empty state with upload CTA when no files
    - Follow pattern from Rooms section in `resources/js/pages/Datacenters/Show.vue`
  - [x] 3.5 Create file type icon helper/component
    - Location: `resources/js/components/implementation-files/FileTypeIcon.vue`
    - Map MIME types to appropriate lucide-vue-next icons
    - PDF: FileText icon
    - Excel/CSV: FileSpreadsheet icon
    - Word: FileType icon
    - Text: FileText icon
    - Consistent sizing and color styling
  - [x] 3.6 Update Datacenter Show page to include Implementation Files card
    - Add ImplementationFileCard below Rooms section
    - Pass implementationFiles data from controller
    - Pass canUpload and canDelete permissions
  - [x] 3.7 Update DatacenterController show method
    - Eager load implementationFiles with uploader relationship
    - Pass canUpload and canDelete permissions based on policy
    - Use ImplementationFileResource for data transformation
  - [x] 3.8 Implement delete confirmation dialog
    - Use existing DeleteDialog pattern if available
    - Confirm file name before deletion
    - Show loading state during deletion
    - Refresh file list on successful deletion
  - [x] 3.9 Implement PDF preview functionality
    - Open PDF in new browser tab with inline Content-Disposition
    - Use preview endpoint URL from API response
  - [x] 3.10 Apply responsive design
    - Mobile: Stack actions vertically, truncate long filenames
    - Tablet/Desktop: Full table layout with all columns visible
    - Follow existing responsive patterns in the application
  - [x] 3.11 Ensure UI component tests pass
    - Run ONLY the tests written in 3.1
    - Verify components render correctly
    - Verify user interactions work as expected

**Acceptance Criteria:**
- The 2-8 tests written in 3.1 pass
- Implementation Files card displays on Datacenter Show page
- File upload dialog works with drag-and-drop
- File list displays all metadata correctly
- Actions respect user permissions (upload/delete for Admin/IT Manager)
- PDF preview opens in browser
- Non-PDF files download directly
- Responsive design works across screen sizes

### Testing Layer

#### Task Group 4: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-3

- [x] 4.0 Review existing tests and fill critical gaps only
  - [x] 4.1 Review tests from Task Groups 1-3
    - Review the 2-8 tests written by database layer (Task 1.1) in `tests/Feature/ImplementationFileModelTest.php`
    - Review the 2-8 tests written by API layer (Task 2.1) in `tests/Feature/ImplementationFile/ImplementationFileApiTest.php`
    - Review the 2-8 tests written by UI layer (Task 3.1) in `tests/Feature/ImplementationFile/ImplementationFileUITest.php`
    - Total existing tests: 24 tests
  - [x] 4.2 Analyze test coverage gaps for this feature only
    - Identified critical user workflows that lacked test coverage
    - Focused ONLY on gaps related to implementation file upload feature
    - Prioritized end-to-end workflows and edge cases
  - [x] 4.3 Write up to 10 additional strategic tests maximum
    - End-to-end: Admin uploads file and sees it in datacenter show page
    - Integration: Soft-deleted files not shown in list
    - Integration: File upload rejects invalid file types
    - Integration: IT Manager can delete files
    - Integration: Auditor can view file list and download files
    - Edge case: Download returns 404 for missing storage file
    - Edge case: Preview returns 404 for missing PDF storage file
    - Integration: Replaced files have replaced_by information
    - Integration: Show action returns file details with download URL
    - Authorization: Operator cannot upload implementation files
  - [x] 4.4 Run feature-specific tests only
    - Ran ONLY tests related to implementation file upload feature
    - Total tests: 34 tests (24 existing + 10 new)
    - All tests pass with 307 assertions

**Acceptance Criteria:**
- All feature-specific tests pass (34 tests total)
- Critical user workflows for file upload are covered
- 10 additional tests added to fill gaps
- Testing focused exclusively on implementation file upload feature

## Execution Order

Recommended implementation sequence:

1. **Database Layer (Task Group 1)**
   - Create ImplementationFile model, migration, and factory
   - Set up relationships with Datacenter and User models
   - Establish foundation for all subsequent work

2. **API Layer (Task Group 2)**
   - Create policy, form request, controller, and resource
   - Implement file storage and replacement logic
   - Register routes nested under datacenter

3. **Frontend Layer (Task Group 3)**
   - Build UI components for file list and upload dialog
   - Integrate into Datacenter Show page
   - Implement download and preview functionality

4. **Test Review & Gap Analysis (Task Group 4)**
   - Review all tests from previous groups
   - Fill critical coverage gaps
   - Verify complete feature functionality

## Technical Notes

### File Storage
- Use Laravel's private disk (`storage/app/private`)
- Store files in `implementation-files/{datacenter_id}/` directory
- Generate UUID-based filenames to prevent collisions
- Validate MIME types server-side for security

### Supported File Types
| Extension | MIME Type | Label |
|-----------|-----------|-------|
| .pdf | application/pdf | PDF Document |
| .xlsx | application/vnd.openxmlformats-officedocument.spreadsheetml.sheet | Excel Spreadsheet |
| .xls | application/vnd.ms-excel | Excel Spreadsheet |
| .csv | text/csv | CSV File |
| .docx | application/vnd.openxmlformats-officedocument.wordprocessingml.document | Word Document |
| .txt | text/plain | Text File |

### Authorization Matrix
| Role | View | Download | Upload | Delete |
|------|------|----------|--------|--------|
| Administrator | Yes | Yes | Yes | Yes |
| IT Manager | Yes | Yes | Yes | Yes |
| Operator | Yes* | Yes* | No | No |
| Auditor | Yes* | Yes* | No | No |

*Requires access to parent datacenter

### Existing Code References
- Model pattern: `app/Models/Datacenter.php`
- Policy pattern: `app/Policies/DatacenterPolicy.php`
- Controller pattern: `app/Http/Controllers/BulkImportController.php`
- Form request pattern: `app/Http/Requests/StoreBulkImportRequest.php`
- FileDropzone component: `resources/js/components/imports/FileDropzone.vue`
- Datacenter Show page: `resources/js/pages/Datacenters/Show.vue`
