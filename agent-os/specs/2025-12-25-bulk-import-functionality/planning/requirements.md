# Spec Requirements: Bulk Import Functionality

## Initial Description
CSV/Excel import for datacenters, racks, devices, and ports with validation and error reporting

## Requirements Discussion

### First Round Questions

**Q1:** File format support - I assume we want to support both CSV and XLSX (Excel) formats. Is that correct, or should we focus on just one format?
**Answer:** CSV and XLSX formats only.

**Q2:** Import structure - Should we use a combined format where hierarchical data (datacenter > room > rack > device) can be imported in one file with parent references, or separate files per entity type?
**Answer:** Combined format for hierarchical data in one file.

**Q3:** Validation error handling - I assume we should provide a downloadable error report showing row numbers and specific validation failures. Is that correct?
**Answer:** Yes, downloadable error report with row number, field name, and error message.

**Q4:** Partial imports - When validation errors occur, should we import valid rows and skip invalid ones (with error reporting), or reject the entire import if any row fails?
**Answer:** Partial imports - valid rows imported, invalid skipped with error reporting.

**Q5:** Entity references - When importing devices into racks, should users reference parent entities by name/path (e.g., "DC1 > Room A > Rack 01") or by database IDs?
**Answer:** Reference parents by name/path, not database IDs.

**Q6:** Template downloads - Should we provide downloadable import templates with headers, example data, and validation rules documented?
**Answer:** Yes, downloadable templates with headers, example data, and dropdown validation for enums in Excel.

**Q7:** Processing approach - For large imports (1000+ rows), should processing happen synchronously or via background jobs with progress tracking?
**Answer:** Async processing via queues for 100+ rows, sync for smaller imports.

**Q8:** Scope exclusions - Is there anything specifically out of scope, such as port connections between devices or user/permission assignments?
**Answer:** Out of scope: port connections, user assignments, audit data.

### Existing Code to Reference
No similar existing features identified for reference.

### Follow-up Questions

**Follow-up 1:** For the hierarchical structure in a combined file, which format do you prefer?
- Option A: Repeated Parent Columns (each row contains the full parent path: datacenter_name, room_name, rack_name, device_name)
- Option B: Section-Based (entities grouped in sections with headers like [Datacenters], [Rooms], [Racks], [Devices])

**Answer:** Option A - Repeated Parent Columns (each row contains the full parent path)

**Follow-up 2:** For Phase 1, which entities should be importable?
- All entities (Datacenters, Rooms, Racks, Devices, Ports)
- Core infrastructure only (Datacenters, Rooms, Racks)
- Devices and Ports only (assuming infrastructure exists)

**Answer:** All entities in Phase 1: Datacenters, Rooms, Racks, Devices (with placement in racks), and Ports

**Follow-up 3:** When a row references a parent that doesn't exist, should we:
- Option A: Fail that row (require manual creation first)
- Option B: Auto-create the parent with minimal data
- Option C: Allow configuration per import

**Answer:** Option A - Fail if any parent entity doesn't exist (require manual creation first)

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A

## Requirements Summary

### Functional Requirements
- Import datacenters, rooms, racks, devices, and ports from CSV or XLSX files
- Support combined format with full parent path in each row (repeated parent columns)
- Reference parent entities by name/path, not database IDs
- Validate all rows before importing; import valid rows and skip invalid ones
- Generate downloadable error reports with row number, field name, and error message
- Provide downloadable import templates with headers, example data, and dropdown validation for enums in Excel
- Process imports asynchronously via queues when row count exceeds 100; process synchronously for smaller imports
- Fail rows that reference non-existent parent entities (no auto-creation)

### Reusability Opportunities
No similar existing features identified for reference.

### Scope Boundaries

**In Scope:**
- CSV file import
- XLSX (Excel) file import
- Datacenter import
- Room import
- Rack import
- Device import (with rack placement)
- Port import
- Validation and error reporting
- Downloadable error reports
- Downloadable import templates with enum dropdowns
- Partial import (valid rows proceed, invalid rows skipped)
- Async processing for large imports (100+ rows)
- Sync processing for small imports (under 100 rows)

**Out of Scope:**
- Port connections between devices
- User assignments
- Audit data import
- Auto-creation of missing parent entities
- Other file formats (JSON, XML, etc.)

### Technical Considerations
- Queue system required for async processing of large imports
- Excel library needed for XLSX parsing and template generation with dropdown validation
- Parent entity lookup by name/path requires efficient querying strategy
- Progress tracking needed for async imports
- Error report generation (CSV format recommended for consistency)
- Template generation with example data and enum dropdown lists
- Threshold of 100 rows determines sync vs async processing
