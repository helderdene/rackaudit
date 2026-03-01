# Specification: Bulk Import Functionality

## Goal
Enable bulk import of datacenter infrastructure data (datacenters, rooms, rows, racks, devices, and ports) from CSV and XLSX files with validation, error reporting, and async processing for large imports.

## User Stories
- As a datacenter administrator, I want to import infrastructure data from spreadsheets so that I can quickly populate the system with existing assets
- As a technician, I want to download pre-formatted templates with dropdown validation so that I can prepare import files correctly without manual reference to documentation

## Specific Requirements

**File Format Support**
- Accept CSV (.csv) and XLSX (.xlsx) file formats only
- Use repeated parent columns format where each row contains full parent path (datacenter_name, room_name, row_name, rack_name, device_name)
- Maximum file size limit of 10MB to prevent server overload
- First row must contain column headers matching expected field names
- Validate file extension and MIME type before processing

**Hierarchical Data Structure**
- Support importing all entity types: Datacenter, Room, Row, Rack, Device, Port
- Each row must reference parents by name, not database IDs
- Parent lookup follows path: datacenter_name > room_name > row_name > rack_name > device_name
- Fail rows that reference non-existent parent entities (no auto-creation)
- Entities without parent columns (datacenters) are created as top-level entities

**Template Generation**
- Provide downloadable XLSX templates with column headers and example data
- Include Excel data validation dropdowns for all enum fields
- Enum dropdowns: RoomType, RowOrientation, RowStatus, RackStatus, RackUHeight, DeviceLifecycleStatus, DeviceDepth, DeviceWidthType, DeviceRackFace, PortType, PortSubtype, PortStatus, PortDirection
- Include helper text in template describing required vs optional fields
- DeviceType referenced by name (looked up in device_types table)

**Validation Rules**
- Validate all rows before committing any data to database
- Required fields per entity type match existing Form Request validation
- Validate enum values against defined PHP enum cases
- Validate parent entity existence by name lookup
- Validate device rack placement (start_u, u_height) against rack capacity
- Validate port subtype compatibility with port type

**Error Handling and Reporting**
- Generate downloadable CSV error report with columns: row_number, field_name, error_message
- Partial imports allowed: valid rows imported, invalid rows skipped
- Track and report count of successful imports vs failures
- Store error report temporarily for download (24-hour retention)

**Processing Flow**
- Synchronous processing for imports under 100 rows
- Async queue-based processing for imports of 100+ rows with progress tracking
- Use database transactions for atomic operations per entity batch
- Import Job should be dispatchable and trackable via Laravel queues
- Store import progress in cache or database for status polling

**Import Status Tracking**
- Create BulkImport model to track import jobs: status, total_rows, processed_rows, success_count, failure_count
- Status values: pending, processing, completed, failed
- Store error_report_path for downloadable error file
- Poll endpoint for async import progress updates

**UI/UX Considerations**
- Import page accessible from main navigation or settings area
- File upload dropzone with drag-and-drop support
- Template download buttons for each entity type or combined template
- Progress indicator for async imports with percentage complete
- Display summary upon completion showing success/failure counts
- Download error report button when failures exist

## Visual Design
No visual assets provided.

## Existing Code to Leverage

**Eloquent Models (Datacenter, Room, Row, Rack, Device, Port)**
- Follow existing fillable field definitions for required/optional fields
- Use existing relationships: Datacenter->rooms, Room->rows, Row->racks, Rack->devices, Device->ports
- Leverage existing enum casts for validation reference
- Asset tag auto-generation in Device model boot method should be reused

**Form Request Validation (StoreDatacenterRequest, StoreRoomRequest, etc.)**
- Mirror validation rules from existing Form Requests for each entity type
- Adapt rules for import context (e.g., parent lookup by name instead of ID)
- Use existing validation message patterns for consistency

**Enum Definitions (RoomType, RowOrientation, RackStatus, etc.)**
- Use enum cases for template dropdown generation
- Use enum label() methods for human-readable template values
- PortSubtype::forType() and PortDirection::forType() for conditional validation

**Queue Configuration**
- Database queue driver already configured in config/queue.php
- Use default connection for import job dispatching
- Follow existing Laravel job conventions for queue workers

**Vue Component Patterns (resources/js/Pages/Devices/Index.vue)**
- Follow existing page layout with AppLayout wrapper and breadcrumbs
- Use existing UI components: Button, Input, Badge from components/ui
- Follow existing table styling for import history display
- Use Inertia router patterns for navigation and state management

## Out of Scope
- Port connections between devices (cable/connection mapping)
- User assignments or permission imports
- Audit data or activity log imports
- Auto-creation of missing parent entities
- Other file formats (JSON, XML, etc.)
- PDU imports (separate feature consideration)
- Update/merge logic for existing entities (import creates new only)
- Real-time WebSocket progress updates (polling is sufficient)
- Import scheduling or recurring imports
- Data export functionality
