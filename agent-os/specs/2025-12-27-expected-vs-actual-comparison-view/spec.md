# Specification: Expected vs Actual Comparison View

## Goal
Provide a tabular comparison view that matches confirmed expected connections from approved implementation files against documented actual connections, enabling users to identify discrepancies and take corrective actions directly from the view.

## User Stories
- As a datacenter technician, I want to compare expected connections from an implementation file against actual connections so that I can identify missing or incorrect cabling.
- As a datacenter manager, I want to see an aggregated comparison across all approved implementation files for a datacenter so that I can assess overall compliance and prioritize remediation work.

## Specific Requirements

**Comparison Engine Service**
- Create a `ConnectionComparisonService` class in `app/Services/` to encapsulate all matching logic for reuse in Phase 4 audit features
- Match expected connections against actual connections using source_port_id + destination_port_id pairs
- Support bidirectional matching: treat (A->B) as equivalent to (B->A) when checking for matches
- Detect partial matches where source port matches but destination port differs from expectation
- Return structured results with discrepancy type, expected data, actual data, and match metadata

**Discrepancy Categories**
- **Matched**: Expected connection exists and an actual connection with identical source/dest ports exists
- **Expected but Missing**: Expected connection has no corresponding actual connection in the system
- **Actual but Unexpected**: Actual connection exists but is not specified in any approved implementation file for the datacenter
- **Mismatched (Partial)**: Source port matches an actual connection but destination port differs
- **Conflicting**: Multiple approved implementation files specify different expected destinations for the same source port; display warning indicator with both expectations shown

**Single File Comparison View**
- Accessible from the implementation file detail page (only for approved files with confirmed expected connections)
- Query only confirmed expected connections from the selected implementation file via `ExpectedConnection::confirmed()` scope
- Display comparison table filtered to that file's expected connections plus any unexpected actuals involving those ports

**Datacenter Aggregated Comparison View**
- Accessible from the datacenter show page via a new "Connection Audit" or "Compare Connections" button
- Aggregate confirmed expected connections from all approved implementation files for the datacenter
- Detect and flag conflicts when multiple files specify different expectations for the same source port
- Support pagination for large datasets; default 50 rows per page

**Tabular UI Layout**
- Table columns: Row #, Source Device, Source Port, Dest Device, Dest Port, Cable Type, Expected vs Actual Status, Actions
- Show expected values with actual values in parentheses when different (e.g., "Port-A1 (Actual: Port-B2)")
- Row highlighting based on discrepancy type: green for matched, yellow for mismatched/partial, red for missing, orange for unexpected, purple border for conflicts
- Use border-l-4 pattern from `ConnectionReviewTable.vue` for visual status indication

**Filtering Controls**
- Filter by discrepancy type via multi-select dropdown: Matched, Missing, Unexpected, Mismatched
- Filter by device via searchable dropdown populated from devices involved in comparisons
- Filter by rack via dropdown filtered to racks containing involved devices
- Persist filter state in URL query parameters for shareable links

**Create Connection Action**
- Enable "Create Connection" action on rows with "Expected but Missing" status
- Pre-populate the `CreateConnectionDialog` with source/dest ports from the expected connection
- Refresh comparison view after successful creation to reflect the newly matched status

**Delete Connection Action**
- Enable "Delete Connection" action on rows with "Actual but Unexpected" status
- Use `DeleteConnectionConfirmation` dialog pattern for confirmation
- Refresh comparison view after deletion to remove the unexpected row

**Acknowledge Discrepancy Action**
- Add "Acknowledge" action to defer resolution of any discrepancy
- Store acknowledgments in a new `discrepancy_acknowledgments` table with: expected_connection_id (nullable), connection_id (nullable), discrepancy_type, acknowledged_by, acknowledged_at, notes
- Display acknowledged discrepancies with muted styling and "Acknowledged" badge
- Allow filtering to show/hide acknowledged discrepancies

**Export Comparison Results**
- Export button to download comparison results as CSV
- Include columns: Source Device, Source Port, Dest Device, Dest Port, Expected Cable Type, Actual Cable Type, Discrepancy Type, Acknowledged, Notes
- Follow `AbstractDataExport` pattern from existing exports
- Respect current filter selections when exporting

## Visual Design
No visual mockups were provided. The UI should follow established patterns from `ConnectionReviewTable.vue` for table structure, row highlighting, status badges, and action buttons.

## Existing Code to Leverage

**`ConnectionReviewTable.vue` component**
- Reuse table structure with checkbox column pattern, row highlighting based on status, and status badge styling
- Adapt `getRowClasses()` function for discrepancy type-based coloring
- Reference tooltip patterns for showing match details

**`ExpectedConnection` model and scopes**
- Use `ExpectedConnection::confirmed()` scope to filter only finalized connections
- Leverage existing relationships: `sourceDevice`, `destDevice`, `sourcePort`, `destPort`, `implementationFile`

**`Connection` model**
- Use relationships `sourcePort` and `destinationPort` for fetching actual connection data
- Note the different column name: `destination_port_id` vs `dest_port_id` in ExpectedConnection

**`CreateConnectionDialog.vue` and `DeleteConnectionConfirmation.vue`**
- Reuse these dialogs directly for create/delete actions
- Pass appropriate props from comparison row data to pre-populate dialogs

**`AbstractDataExport` pattern**
- Follow the structure in `RackExport.php` for creating `ComparisonExport` class
- Implement `headings()`, `title()`, `query()`, and `transformRow()` methods

## Out of Scope
- Automated resolution suggestions (e.g., "Did you mean to connect to Port X?")
- Integration with Phase 4 audit workflow and scheduled audit runs
- Real-time updates when connections change (page refresh required)
- Comparison across multiple datacenters simultaneously
- Bulk actions for acknowledging or resolving multiple discrepancies at once
- Editing expected connections from the comparison view
- Historical comparison snapshots or diff between time periods
- Mobile-responsive design optimizations (desktop-first)
- Undo functionality for connection creation/deletion from this view
- Notification/email alerts for new discrepancies
