# Spec Requirements: Expected Connection Parsing

## Initial Description

**Feature Description:** Expected Connection Parsing — Parse uploaded implementation files to extract expected connections with port-to-port mapping

This feature enables parsing of uploaded implementation specification files (Excel/CSV) to extract expected connections with port-to-port mapping. The parsed data populates an expected_connections table that serves as the authoritative source for comparing against actual documented connections during audits.

---

## Requirements Discussion

### First Round Questions

**Q1:** I assume the system needs to parse structured data files (Excel/CSV) to extract expected connections, with PDF/Word documents being informational references that would require manual data entry rather than automated parsing. Is that correct, or should we attempt to extract connection data from all file types including PDFs?
**Answer:** Correct - parse structured data files (Excel/CSV) only. PDF/Word are informational references. User also requests: Create a template for the implementation file to have easier extraction.

**Q2:** For the expected connection data structure, I'm thinking each parsed connection should capture: source device identifier, source port identifier, destination device identifier, destination port identifier, and optionally cable type/length. Should we also capture additional attributes like priority, circuit ID, or cable color from the implementation files?
**Answer:** Correct - capture source device, source port, destination device, destination port, optionally cable type/length.

**Q3:** I assume implementation files will follow common datacenter documentation formats where connections are listed in rows/columns with headers like "Source Device", "Source Port", "Destination Device", "Destination Port". Should the system support multiple file format templates, or will you standardize on a single format that users must follow?
**Answer:** Correct - single standardized format that users must follow.

**Q4:** For device and port matching, I assume we'll need to match parsed identifiers (like "RACK-A1-SW01" or "Eth1/1") against existing devices and ports in the database. Should the parsing allow for fuzzy matching with user confirmation, or require exact matches only (failing unmatched entries)?
**Answer:** Correct - fuzzy matching with user confirmation.

**Q5:** When an implementation file is re-uploaded as a new version, should the previously parsed expected connections from the old version be automatically archived/replaced, or should users manually manage which version's expected connections are "active" for comparison?
**Answer:** Correct - previous expected connections from old version should be archived/replaced.

**Q6:** I assume the parsing will happen asynchronously (as a background job) since large files could take time, with status updates shown to the user. Is that correct, or should parsing be synchronous for immediate feedback on smaller files?
**Answer:** Correct - synchronous parsing for immediate feedback on smaller files.

**Q7:** Should there be a manual review/edit interface where users can see parsed connections before they're finalized? For example, correcting device name mismatches or fixing parsing errors before the expected connections become authoritative.
**Answer:** Correct - manual review/edit interface before finalizing.

**Q8:** Is there anything you explicitly do NOT want included in this feature?
**Answer:** Exclude AI-powered parsing of unstructured PDFs, exclude automatic connection creation (only parse to expected_connections table), exclude support for certain file types.

### Existing Code to Reference

**Similar Features Identified:**
- Feature: Bulk Import - Path: `app/Models/BulkImport.php`
- Components to potentially reuse: File upload patterns, status tracking (total_rows, processed_rows, success_count, failure_count), progress calculation
- Backend logic to reference: BulkImport model structure with entity_type enum, status enum, file storage patterns

### Follow-up Questions

**Follow-up 1:** For the standardized Excel/CSV template, should we include: a header row with exact column names, an instructions/example sheet in Excel files, and should the template be downloadable from the implementation file upload interface?
**Answer:** YES to all - Header row with exact column names (Source Device, Source Port, Dest Device, Dest Port, Cable Type, Cable Length), instructions/example sheet in Excel files showing sample data, template downloadable from the implementation file upload interface.

**Follow-up 2:** For the fuzzy matching with user confirmation, what should happen when a device name is close but not exact, or when a device/port is completely unrecognized?
**Answer:** YES to all - Show close matches as suggestions to confirm. Users can skip unrecognized, create device/port on the fly, or cancel and fix the file.

**Follow-up 3:** For the manual review/edit interface before finalizing, should users be able to edit individual parsed rows, have bulk actions, and should expected connections be immediately available for comparison after finalization?
**Answer:** YES to all - Users can edit individual parsed rows, bulk actions like "confirm all matched" or "reject all unmatched", after finalization expected connections immediately available for "Expected vs Actual Comparison View" (roadmap item #25).

---

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A - No visual files were added to the visuals folder.

---

## Requirements Summary

### Functional Requirements

**Template System:**
- Create a standardized Excel/CSV template for implementation files
- Template includes header row with exact column names: Source Device, Source Port, Dest Device, Dest Port, Cable Type, Cable Length
- Excel template includes an instructions/example sheet showing sample data
- Template downloadable from the implementation file upload interface

**File Parsing:**
- Parse Excel (.xlsx, .xls) and CSV files only
- Synchronous parsing for immediate user feedback
- Extract connection data: source device, source port, destination device, destination port, cable type (optional), cable length (optional)
- Only parse files following the standardized template format

**Device/Port Matching:**
- Fuzzy matching of parsed device/port identifiers against existing database records
- Show close matches as suggestions for user confirmation
- Handle unrecognized devices/ports with options: skip, create on the fly, or cancel and fix file

**Review Interface:**
- Manual review/edit interface before finalizing parsed connections
- Users can edit individual parsed rows to correct device/port mappings
- Bulk actions: "confirm all matched", "reject all unmatched"
- Clear visual distinction between matched, suggested, and unrecognized entries

**Expected Connections Storage:**
- Store parsed connections in expected_connections table (not actual connections)
- Link expected connections to the source implementation file
- When a new file version is uploaded, archive/replace previous version's expected connections
- Finalized expected connections immediately available for "Expected vs Actual Comparison View"

**Integration:**
- Integrate with existing ImplementationFile model and approval workflow
- Only approved implementation files should have their parsed connections used for audits

### Reusability Opportunities

- **BulkImport model pattern** (`app/Models/BulkImport.php`): Use similar structure for tracking parsing status, progress, and results
- **File upload components**: Reference existing implementation file upload dialog (`resources/js/components/implementation-files/UploadImplementationFileDialog.vue`)
- **Status enums**: Similar pattern to BulkImportStatus enum for parsing status tracking
- **Loggable trait**: Use existing activity logging pattern for audit trail

### Scope Boundaries

**In Scope:**
- Excel/CSV template creation and download functionality
- Parsing engine for standardized Excel/CSV files
- Fuzzy matching algorithm with suggestion system
- Review/edit interface for parsed connections
- Bulk confirmation and rejection actions
- Expected connections storage and versioning
- Integration with implementation file version control
- Skip, create-on-the-fly, or cancel options for unrecognized entries

**Out of Scope:**
- AI-powered parsing of unstructured documents (PDFs, Word)
- Automatic creation of actual Connection records (only expected_connections)
- Support for non-Excel/CSV file types for parsing
- Asynchronous/background job processing (synchronous only for this phase)
- Multiple template formats (single standardized format only)

### Technical Considerations

- **Database**: New `expected_connections` table linked to `implementation_files` table
- **File Storage**: Templates stored in application assets or generated dynamically
- **Parsing Library**: Use Laravel Excel (maatwebsite/excel) already in tech stack
- **Fuzzy Matching**: Consider using Levenshtein distance or similar algorithm for device/port name matching
- **Frontend**: Vue 3 components for review interface with Inertia.js integration
- **Validation**: Validate template format on upload before parsing
- **Error Handling**: Capture and display parsing errors per row for user review
- **Performance**: Synchronous parsing suitable for smaller files; consider file size limits

### Related Roadmap Items

- Item #21: Implementation File Upload (completed) - prerequisite
- Item #22: Implementation File Version Control (completed) - prerequisite
- Item #23: Implementation File Approval Workflow (completed) - prerequisite
- Item #24: Expected Connection Parsing (this feature)
- Item #25: Expected vs Actual Comparison View (next feature) - will consume expected connections
