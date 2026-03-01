# Specification: Expected Connection Parsing

## Goal
Parse uploaded Excel/CSV implementation files to extract expected connections with port-to-port mapping, storing them in an expected_connections table for comparison against actual documented connections during audits.

## User Stories
- As a datacenter administrator, I want to upload a standardized implementation file so that expected connections are automatically parsed and stored for audit comparison.
- As an auditor, I want to review and confirm parsed connections before they become authoritative so that I can correct any device/port matching errors.

## Specific Requirements

**Template System**
- Create downloadable Excel template with columns: Source Device, Source Port, Dest Device, Dest Port, Cable Type, Cable Length
- Excel template includes a separate "Instructions" sheet with sample data and column descriptions
- CSV template available as alternative format with header row
- Template download button integrated into the implementation file upload interface
- Template files stored as application assets or generated dynamically via Laravel Excel

**File Parsing Engine**
- Parse Excel (.xlsx, .xls) and CSV files using Laravel Excel (maatwebsite/excel) package
- Synchronous parsing for immediate user feedback (no background jobs)
- Validate template format before parsing - reject files missing required columns
- Extract each row into: source_device, source_port, dest_device, dest_port, cable_type (optional), cable_length (optional)
- Capture row-level parsing errors for user review
- Enforce file size limits for synchronous processing performance

**Fuzzy Device/Port Matching**
- Use Levenshtein distance algorithm to match parsed device/port names against existing database records
- Calculate match confidence scores and categorize as: exact match, suggested match (close), or unrecognized
- Query Device model by name field and Port model by label field for matching
- Show close matches (confidence above threshold) as suggestions with original parsed value displayed
- Provide options for unrecognized entries: skip row, create device/port on the fly, or cancel and fix file

**Review Interface**
- Vue 3 component displaying parsed connections in a data table format
- Visual distinction between match states: green for exact matches, yellow for suggested matches, red for unrecognized
- Inline editing capability for individual rows to correct device/port mappings
- Dropdown selectors showing matched/suggested devices and ports with search functionality
- Bulk actions: "Confirm All Matched" to accept all exact matches, "Reject All Unmatched" to skip all unrecognized rows
- Show row count summary: total rows, matched, suggested, unrecognized

**Expected Connections Storage**
- New expected_connections table with columns: implementation_file_id, source_device_id, source_port_id, dest_device_id, dest_port_id, cable_type, cable_length, row_number, status
- Link each expected connection to the source ImplementationFile via foreign key
- Status field tracks: pending_review, confirmed, skipped
- When new file version uploaded, archive previous version's expected connections (soft delete or status change)
- Only finalized (confirmed) expected connections available for "Expected vs Actual Comparison View"

**Integration with Existing Workflow**
- Only allow parsing for Excel/CSV implementation files (skip PDF/Word/text)
- After file upload approval, show "Parse Connections" action button
- Parsing creates draft expected_connections in pending_review status
- User must finalize review before expected connections become active
- Respect existing ImplementationFile version control - new version parsing replaces old version's expected connections

## Existing Code to Leverage

**BulkImport Model Pattern (`/Users/helderdene/rackaudit/app/Models/BulkImport.php`)**
- Reuse status enum pattern (BulkImportStatus) for parsing status tracking
- Replicate progress tracking fields: total_rows, processed_rows, success_count, failure_count
- Follow same Loggable trait pattern for activity logging
- Use similar fillable attributes structure for the new ExpectedConnection model

**UploadImplementationFileDialog Component (`/Users/helderdene/rackaudit/resources/js/components/implementation-files/UploadImplementationFileDialog.vue`)**
- Extend dialog pattern to add template download link before upload dropzone
- Reuse FileDropzone component with modified accepted types (.xlsx, .xls, .csv only for parsing)
- Follow same Inertia router pattern for form submission
- Replicate success/error message display patterns

**Connection Model (`/Users/helderdene/rackaudit/app/Models/Connection.php`)**
- Match expected_connections table structure to connections table (source_port_id, destination_port_id, cable_type, cable_length)
- Reuse CableType enum for cable_type field validation
- Follow same relationship patterns (belongsTo Port/Device)
- Use similar getEnrichedAttributesForLog pattern for activity logging with device/port names

**Device and Port Models (`/Users/helderdene/rackaudit/app/Models/Device.php`, `/Users/helderdene/rackaudit/app/Models/Port.php`)**
- Query Device.name for fuzzy device matching
- Query Port.label for fuzzy port matching
- Use Device.ports() relationship when creating ports on the fly
- Follow existing factory patterns for testing

**FileDropzone Component (`/Users/helderdene/rackaudit/resources/js/components/imports/FileDropzone.vue`)**
- Reuse for template upload with restricted acceptedTypes parameter
- Follow same validation error handling patterns
- Leverage existing file size and type validation logic

## Out of Scope
- AI-powered parsing of unstructured PDFs or Word documents
- Automatic creation of actual Connection records (only expected_connections table)
- Support for non-Excel/CSV file types for automated parsing
- Asynchronous/background job processing for large files
- Multiple template format variations (single standardized format only)
- Cable color field in expected connections (not in template)
- Path notes field in expected connections
- Batch upload of multiple implementation files simultaneously
- Automatic device/port creation without user confirmation
- Export of expected connections to Excel/CSV
