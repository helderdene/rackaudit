# Specification: Implementation File Upload

## Goal
Enable users to upload and manage implementation specification documents (PDF, Excel, CSV, Word, text) at the datacenter level, serving as the authoritative source for expected connections that can be compared against actual documented connections during audits.

## User Stories
- As an IT Manager, I want to upload implementation specification documents to a datacenter so that I have a centralized reference for expected connections
- As an Auditor, I want to view and download implementation files so that I can compare expected configurations against actual infrastructure

## Specific Requirements

**ImplementationFile Model**
- Create model with fields: `datacenter_id`, `file_name`, `original_name`, `description`, `file_path`, `file_size`, `mime_type`, `uploaded_by` (user_id)
- Include `replaced_at` nullable timestamp and `replaced_by` user_id for tracking replacements
- BelongsTo relationships: Datacenter, User (uploaded_by), User (replaced_by)
- Use Laravel's `HasFactory` and the existing `Loggable` concern for activity logging
- Add accessors for formatted file size (KB/MB) and human-readable file type

**Database Migration**
- Create `implementation_files` table with foreign keys to `datacenters` and `users`
- Include indexes on `datacenter_id`, `uploaded_by`, and `created_at` for query performance
- Store `file_size` as unsigned big integer (bytes)
- Use `string` for `mime_type` to store full MIME type (e.g., `application/pdf`)

**File Storage Configuration**
- Use Laravel's private disk (`storage/app/private`) for secure file storage
- Store files in `implementation-files/{datacenter_id}/` directory structure
- Generate unique filenames using UUID or timestamp to prevent collisions
- Validate MIME types server-side in addition to extension validation

**ImplementationFileController**
- `index`: Return paginated list of files for a datacenter with metadata
- `store`: Handle file upload with validation, store file, create database record
- `show`: Return file details including download URL
- `download`: Stream file download with proper headers and authentication check
- `preview`: Return PDF files inline for browser preview
- `destroy`: Delete file from storage and database (soft-delete the record for history)
- Nest routes under datacenter: `/datacenters/{datacenter}/implementation-files`

**File Upload Validation**
- Maximum file size: 10MB (10240 KB)
- Allowed MIME types: `application/pdf`, `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`, `application/vnd.ms-excel`, `text/csv`, `application/vnd.openxmlformats-officedocument.wordprocessingml.document`, `text/plain`
- Allowed extensions: `.pdf`, `.xlsx`, `.xls`, `.csv`, `.docx`, `.txt`
- Create `StoreImplementationFileRequest` Form Request class with validation rules and custom messages

**File Replacement Logic**
- When uploading a file with the same `original_name`, update existing record instead of creating new
- Set `replaced_at` and `replaced_by` on the existing record before updating file path
- Delete old file from storage after successful replacement
- Track replacement history through the `replaced_at` and `replaced_by` fields

**Authorization and Policy**
- Create `ImplementationFilePolicy` following existing `DatacenterPolicy` pattern
- Admins and IT Managers: full access (upload, delete, view, download)
- Operators and Auditors: read-only access (view and download only)
- All users must have access to the parent datacenter to access its files

**PDF Preview Functionality**
- Serve PDF files with `Content-Disposition: inline` header for browser preview
- Use browser's native PDF viewer (no external library needed)
- For non-PDF files, provide download only with file type icon display

## Visual Design

No visual mockups provided. Follow existing UI patterns from:
- Datacenter Show page for layout structure and card organization
- BulkImport Create page for FileDropzone component usage
- BulkImport Index page for file list table with actions

**Implementation Files Card on Datacenter Show Page**
- Add new Card section below existing Rooms section
- Display file list in a table with columns: Name, Type, Size, Uploaded By, Date
- Include Upload button for Admin/IT Manager users
- Each row has View/Download/Delete action buttons based on permissions
- Show empty state when no files exist with upload CTA

**Upload Dialog/Modal**
- Reuse existing `FileDropzone` component from `@/components/imports/FileDropzone.vue`
- Configure accepted types for PDF, Excel, CSV, Word, text files
- Add description text input field (optional, max 500 characters)
- Show progress during upload and success/error states

**File List Display**
- Use icons to indicate file type (PDF, spreadsheet, document, text)
- Display formatted file size (KB/MB)
- Show uploader name and relative time (e.g., "2 hours ago")
- Highlight replaced files indicator if applicable

## Existing Code to Leverage

**FileDropzone Component (`resources/js/components/imports/FileDropzone.vue`)**
- Fully functional drag-and-drop file upload component
- Already handles file type and size validation on client side
- Emits `file-selected`, `file-removed`, and `validation-error` events
- Extend `acceptedTypes` prop to include new file types

**BulkImportController Pattern (`app/Http/Controllers/BulkImportController.php`)**
- Authorization pattern using `ADMIN_ROLES` constant for role checks
- File storage using `Storage::disk('local')` for private files
- File download using `Storage::disk('local')->download()` method
- Error handling and JSON/Inertia response handling

**DatacenterPolicy (`app/Policies/DatacenterPolicy.php`)**
- Role-based authorization pattern with `ADMIN_ROLES` constant
- Methods for `viewAny`, `view`, `create`, `update`, `delete`
- Datacenter access check for non-admin users via pivot relationship

**StoreBulkImportRequest (`app/Http/Requests/StoreBulkImportRequest.php`)**
- Form Request pattern with `authorize()` checking user roles
- File validation rules including `mimes` and `max` size
- Custom error messages for validation failures

**Datacenter Show Page (`resources/js/pages/Datacenters/Show.vue`)**
- Layout structure with Cards for different sections
- Permission-based button display using `canEdit`/`canDelete` props
- Breadcrumb and heading patterns for consistent navigation

## Out of Scope
- Full version control with version history viewer (Item #22)
- Approval workflow with draft/pending/approved states (Item #23)
- Parsing file contents to extract expected connections (Item #24)
- Expected vs Actual connection comparison view (Item #25)
- Preview capability for non-PDF files (Word, Excel rendering)
- Bulk file upload (multiple files at once)
- File search and filtering functionality
- File tagging or categorization system
- File sharing or external access links
- Virus/malware scanning of uploaded files
