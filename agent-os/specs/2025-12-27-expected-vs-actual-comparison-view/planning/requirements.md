# Spec Requirements: Expected vs Actual Comparison View

## Initial Description
Side-by-side view comparing expected connections from implementation files against documented actual connections. This feature is item #25 in the product roadmap (Phase 3: Connections & Implementation Files), serving as the foundation for the Audit System features in Phase 4.

## Requirements Discussion

### First Round Questions

**Q1:** I assume the comparison view should be scoped to a single implementation file at a time, showing its confirmed expected connections against matching actual connections in the system. Is that correct, or should users be able to compare across multiple implementation files or the entire datacenter at once?
**Answer:** Correct - single implementation file at a time.

**Q2:** I'm thinking the comparison should identify three categories of discrepancies: (a) Expected but Missing - connections in the implementation file with no matching actual connection, (b) Actual but Unexpected - documented connections that don't appear in any implementation file, and (c) Mismatched - connections where both exist but differ (e.g., different cable type or endpoints). Should we also detect partial matches where the source port matches but destination differs?
**Answer:** Correct - all three categories (Expected but Missing, Actual but Unexpected, Mismatched), PLUS also detect partial matches where source port matches but destination differs.

**Q3:** For the comparison matching logic, I assume we should match on source port ID + destination port ID (either direction, since connections are bidirectional). Is that correct, or should we also factor in cable type/length for stricter matching?
**Answer:** Correct - match on source port ID + destination port ID (bidirectional).

**Q4:** I'm assuming the view should be accessible from the implementation file detail page (after the file is approved and connections are confirmed). Should there also be a global comparison view accessible from the datacenter level that aggregates all approved implementation files?
**Answer:** Correct - from implementation file detail page, AND ALSO from a global datacenter level that aggregates all approved implementation files.

**Q5:** For the UI layout, I'm thinking of a table-based view with rows representing each expected connection and columns showing the expected values, actual values (if found), and the discrepancy status. Would you prefer this tabular approach, or a true side-by-side layout with expected connections on the left and actual connections on the right?
**Answer:** Tabular approach (not side-by-side).

**Q6:** I assume users should be able to filter the comparison view by discrepancy type (matched, missing, extra, mismatched) and by device/rack to focus on specific areas. Are there other filter criteria you'd like to support?
**Answer:** Correct - by discrepancy type (matched, missing, extra, mismatched) and by device/rack.

**Q7:** For actions, I'm thinking users should be able to: (a) create an actual connection to resolve a "missing" discrepancy, (b) mark a discrepancy as "acknowledged" for later resolution, and (c) export the comparison results. Should users also be able to delete actual connections from this view to resolve "extra" discrepancies?
**Answer:** Correct - all actions (create connection, acknowledge, export, delete connection).

**Q8:** Is there anything that should explicitly be out of scope for this initial implementation (e.g., automated resolution suggestions, integration with the upcoming audit workflow)?
**Answer:** Exclude automated resolution suggestions.

### Existing Code to Reference

**Similar Features Identified:**
- Component: `ConnectionReviewTable.vue` - Path: `resources/js/Components/expected-connections/ConnectionReviewTable.vue`
  - Table with match status highlighting (exact/suggested/unrecognized)
  - Row selection with checkboxes
  - Bulk action buttons
  - Status badges and tooltips
- Component: `VersionCompareDialog.vue` - Path: `resources/js/Components/implementation-files/VersionCompareDialog.vue`
  - Side-by-side comparison pattern (for reference on comparison UI patterns)
- Model: `ExpectedConnection` - Path: `app/Models/ExpectedConnection.php`
  - Source/destination device/port relationships
  - Status scopes (confirmed, pending_review, skipped)
  - Implementation file relationship
- Model: `Connection` - Path: `app/Models/Connection.php`
  - Source/destination port relationships
  - Cable properties (type, length, color)
  - Logical path derivation through patch panels
- Components: `resources/js/Components/connections/` folder
  - `CreateConnectionDialog.vue` - For creating new connections
  - `DeleteConnectionConfirmation.vue` - For deleting connections
  - `ConnectionDetailDialog.vue` - For viewing connection details

### Follow-up Questions

**Follow-up 1:** For the global datacenter-level comparison view that aggregates all approved implementation files: If multiple implementation files have overlapping or conflicting expected connections (e.g., two files both specify a connection for the same source port but with different destinations), how should these be handled?
**Answer:** Option (a) - Show a warning/conflict indicator and display both expectations when multiple implementation files have overlapping or conflicting expected connections for the same source port.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A

## Requirements Summary

### Functional Requirements

**Comparison Engine:**
- Match expected connections against actual connections using source port ID + destination port ID
- Support bidirectional matching (A->B matches B->A)
- Detect partial matches where source port matches but destination differs

**Discrepancy Categories:**
- **Matched**: Expected connection exists and actual connection matches
- **Expected but Missing**: Expected connection exists but no matching actual connection
- **Actual but Unexpected**: Actual connection exists but not in any approved implementation file
- **Mismatched**: Both exist but differ in endpoints (partial match detected)
- **Conflicting**: Multiple implementation files specify different expectations for the same port (warning indicator)

**Access Points:**
- Implementation file detail page (single file comparison)
- Datacenter-level page (aggregated comparison across all approved implementation files)

**UI Layout:**
- Tabular view with rows per expected connection
- Columns showing: expected values, actual values (if found), discrepancy status
- Row highlighting based on discrepancy type

**Filtering:**
- Filter by discrepancy type (matched, missing, extra, mismatched)
- Filter by device
- Filter by rack

**Actions:**
- Create actual connection (to resolve "missing" discrepancy)
- Delete actual connection (to resolve "extra" discrepancy)
- Mark discrepancy as "acknowledged" for later resolution
- Export comparison results

**Conflict Handling:**
- Display warning/conflict indicator when multiple implementation files have overlapping expectations
- Show both conflicting expectations in the view

### Reusability Opportunities
- `ConnectionReviewTable.vue` - Table structure, row highlighting, status badges
- `CreateConnectionDialog.vue` - Dialog for creating new connections
- `DeleteConnectionConfirmation.vue` - Confirmation dialog for deletions
- `ExpectedConnection` model scopes for filtering confirmed connections
- Existing badge and tooltip components from UI library

### Scope Boundaries

**In Scope:**
- Comparison view for single implementation file
- Aggregated comparison view at datacenter level
- Four discrepancy categories plus conflict detection
- Filtering by discrepancy type, device, and rack
- Create connection action
- Delete connection action
- Acknowledge discrepancy action
- Export comparison results
- Conflict warning indicators for overlapping implementation files

**Out of Scope:**
- Automated resolution suggestions
- Integration with audit workflow (Phase 4 feature)
- Real-time updates when connections change
- Comparison across multiple datacenters

### Technical Considerations
- Comparison logic should be implemented as a service class for reuse in Phase 4 audit features
- Bidirectional matching requires checking both (source->dest) and (dest->source)
- Aggregated datacenter view may need pagination for large datasets
- Conflict detection requires cross-referencing all approved implementation files for the datacenter
- Export functionality should support CSV format at minimum
- "Acknowledged" status may require a new database field or separate tracking table
