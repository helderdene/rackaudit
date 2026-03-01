# Specification: Bulk Export Functionality

## Goal
Enable users to export datacenter infrastructure data (Datacenters, Rooms, Rows, Racks, Devices, Ports) to CSV/XLSX files for backup and external reporting, with hierarchical filtering and background processing for large datasets.

## User Stories
- As an IT Manager, I want to export all devices from a specific datacenter so that I can generate reports for stakeholders or create backups before making changes.
- As an Administrator, I want to export filtered infrastructure data in a format compatible with the import templates so that I can modify and re-import the data.

## Specific Requirements

**BulkExport Model and Migration**
- Create BulkExport model mirroring BulkImport structure with fields: user_id, entity_type, status, file_name, file_path, total_rows, processed_rows, format (csv/xlsx), filters (JSON), started_at, completed_at
- Use BulkExportStatus enum with values: Pending, Processing, Completed, Failed
- Reuse BulkImportEntityType enum for entity_type field
- Store generated export files in `storage/app/exports/` directory with UUID prefix
- Implement file cleanup for exports older than 7 days

**BulkExportController**
- Follow BulkImportController pattern for authorization using ADMIN_ROLES constant
- Implement index, create, store, show, and download methods
- Use the same role-based access: Administrator and IT Manager roles only
- Return Inertia responses for web requests, JSON for API polling
- Handle file download via Storage::disk('local')->download()

**BulkExportService**
- Follow BulkImportService pattern for sync/async processing threshold (100 rows)
- Accept entity_type, format (csv/xlsx), and hierarchical filters as parameters
- Build filtered queries using eager loading to prevent N+1 problems
- Dispatch ProcessBulkExportJob for large exports, process synchronously for small ones

**ProcessBulkExportJob**
- Mirror ProcessBulkImportJob structure with markAsProcessing, markAsCompleted, markAsFailed methods
- Use Laravel Excel (Maatwebsite/Excel) for file generation
- Process data in chunks (1000 rows) to manage memory for large exports
- Update progress after each chunk for real-time status polling

**Entity Export Classes**
- Create export classes in `app/Exports/` directory (DatacenterExport, RoomExport, etc.)
- Reuse column definitions from corresponding template exports (AbstractTemplateExport pattern)
- Include entity data values instead of example data
- Apply same header styling as template exports for consistency

**Hierarchical Filtering**
- Support filtering by datacenter_id, room_id, row_id, rack_id as query parameters
- Apply cascading filters: filtering by datacenter includes all rooms/rows/racks/devices/ports within
- Use Eloquent relationship scopes for efficient querying
- Store applied filters in BulkExport.filters JSON column for reference

**Export Formats**
- Support CSV format for single entity type exports
- Support XLSX format with optional multi-sheet layout for combined exports
- Let users select format via radio buttons on create form
- Default to XLSX for combined exports (supports multiple sheets)

**Navigation and Quick Actions**
- Add "Exports" item to AppSidebar.vue after "Imports" with Download icon and same role restrictions
- Add "Export" button to entity index pages (Devices, Racks, Datacenters, etc.) in the header actions area
- Export button on index pages should pre-select the entity type and pass current filter context

**Vue Pages (BulkExport/)**
- Create Index.vue mirroring BulkImport/Index.vue structure with export history table
- Create Create.vue with entity type selector, format selector, and hierarchical filter dropdowns
- Create Show.vue with progress polling (5-second interval), status display, and download button
- Reuse existing components: HeadingSmall, Badge, Button, progress indicators

## Visual Design
No visual assets provided. Follow BulkImport page patterns exactly for consistent user experience.

## Existing Code to Leverage

**BulkImportController (`app/Http/Controllers/BulkImportController.php`)**
- Reuse ADMIN_ROLES authorization pattern and authorizeAccess/authorizeImportAccess methods
- Mirror route structure: index, create, store, show, and download endpoints
- Follow same JSON/Inertia response pattern for API and web requests

**BulkImportService (`app/Services/BulkImportService.php`)**
- Replicate async threshold logic (100 rows) for sync vs queued processing
- Follow same file storage pattern using 'local' disk with UUID prefixes
- Mirror job dispatching pattern for background processing

**AbstractTemplateExport (`app/Exports/Templates/AbstractTemplateExport.php`)**
- Extend for entity exports to reuse header styling and column definitions
- Leverage headings(), enumColumns(), and styles() methods
- Apply same header colors and formatting for exported files

**BulkImport Vue Pages (`resources/js/Pages/BulkImport/`)**
- Copy Index.vue table structure for export history display
- Reuse status badge variants and icons (CheckCircle, XCircle, Loader2, Clock)
- Follow same pagination component pattern

**ProcessBulkImportJob (`app/Jobs/ProcessBulkImportJob.php`)**
- Mirror job structure with handle(), markAsProcessing(), markAsCompleted() methods
- Follow same progress update pattern for real-time status polling
- Replicate error handling and logging approach

## Out of Scope
- API endpoints for programmatic export access (no public API for exports)
- Scheduled or automated recurring exports
- Export of cable connections or port-to-port relationships data
- Export of PDU entities (not part of main import/export hierarchy)
- Export access for Operator or Auditor roles
- Custom column selection or ordering (use fixed template format)
- Export file password protection or encryption
- Email notifications when export completes
- Export of historical/audit log data
- Preview of export data before generation
