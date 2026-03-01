# Specification: Connection Audit Execution

## Goal
Enable operators to execute connection audits by verifying documented connections against implementation specs, marking each as verified or discrepant with notes, and tracking progress across multiple operators working simultaneously.

## User Stories
- As an operator, I want to see a list of connections requiring verification so that I can systematically work through the audit
- As an auditor, I want to mark connections as verified or discrepant with notes so that discrepancies are documented for resolution

## Specific Requirements

**Entry Point and Audit Status Transitions**
- Add "Start Audit" button on Audit Show page when status is "Pending" (connection audit type only)
- Add "Continue Audit" button when status is "In Progress"
- Automatically transition audit status from "Pending" to "In Progress" when first verification is recorded
- Auto-complete audit when all connections are verified (transition to "Completed")

**Connection Verification List Pre-population**
- Use `ConnectionComparisonService` to generate the verification list based on audit scope
- Support scope filtering: datacenter-level uses `compareForDatacenter()`, implementation-file-level uses `compareForImplementationFile()`
- Include all discrepancy types from comparison: Matched, Missing, Unexpected, Mismatched, Conflicting
- Store verification items in a new `audit_connection_verifications` table for tracking individual verification status

**Verification Actions and Recording**
- Operators can mark each connection as "Verified" (confirming match status) or "Discrepant" (recording a discrepancy)
- When marking discrepant, require selecting a `DiscrepancyType` from the existing enum
- Notes field: optional for verified connections, required for discrepant connections
- Record the verifying operator and timestamp for each verification

**Multi-Operator Support and Connection Locking**
- Allow multiple operators to work on the same audit simultaneously
- Implement soft connection locking: when an operator starts verifying a connection, lock it for 5 minutes
- Show locked connections with the operator's name who has them locked
- Use Laravel Broadcasting (Echo) for real-time updates when connections are verified or locked

**Bulk Verification Operations**
- Support selecting multiple connections and marking them as verified in bulk
- Bulk verify only works for connections with "Matched" comparison status
- Bulk operations should skip locked connections and notify the operator

**Filtering and Display**
- Table view showing: source device/port, dest device/port, expected vs actual comparison result, verification status
- Filter by discrepancy type (Matched, Missing, Unexpected, Mismatched, Conflicting)
- Filter by verification status (Pending, Verified, Discrepant)
- Search by device name or port label
- Sort by row number, device name, or discrepancy type

**Progress Tracking**
- Display progress bar showing verified count vs total connections
- Show breakdown: X verified, Y discrepant, Z pending
- Display real-time updates when other operators complete verifications

**Pause/Resume Functionality**
- Audit can be left at any time and resumed later
- Verification progress is persisted per-connection in the database
- No explicit "pause" action needed - leaving the page naturally pauses work

**Finding Auto-Creation (Integration Point)**
- When a connection is marked as discrepant, automatically create a Finding record
- Link Finding to the audit and the connection verification
- Set Finding status to "Open" by default
- This is a placeholder integration - Finding model/table may not exist yet

## Visual Design

No visual mockups were provided. Follow existing UI patterns from `ExpectedConnections/Review.vue`:
- Statistics card at top showing progress summary with colored badges
- Progress bar showing completion percentage
- Table layout with selection checkboxes for bulk operations
- Row highlighting based on discrepancy type (green for matched, red for issues)
- Bulk action buttons above table for selected items
- Filter controls for narrowing the connection list

## Existing Code to Leverage

**`ConnectionComparisonService` (`app/Services/ConnectionComparisonService.php`)**
- Use `compareForImplementationFile()` for file-scoped audits
- Use `compareForDatacenter()` for datacenter-scoped audits
- Returns `ComparisonResultCollection` with filtering and statistics methods

**`DiscrepancyType` enum (`app/Enums/DiscrepancyType.php`)**
- Matched, Missing, Unexpected, Mismatched, Conflicting cases
- Use for classifying discrepancies when operators mark connections

**`DiscrepancyAcknowledgment` model (`app/Models/DiscrepancyAcknowledgment.php`)**
- Reference pattern for storing operator acknowledgments with notes
- Fields: expected_connection_id, connection_id, discrepancy_type, acknowledged_by, notes

**`ConnectionReviewTable.vue` (`resources/js/components/expected-connections/ConnectionReviewTable.vue`)**
- Reuse table structure with checkboxes, bulk actions, and row highlighting patterns
- Adapt statistics summary display and progress tracking UI

**`Review.vue` page (`resources/js/pages/ExpectedConnections/Review.vue`)**
- Follow page layout pattern with header, statistics card, table component
- Reuse loading/error states and finalization dialog pattern

## Out of Scope
- Inventory audit execution (this spec is connection audits only)
- Audit report generation and export
- Finding resolution workflow (only auto-creation of findings is included)
- Finding management CRUD beyond auto-creation
- Editing or deleting existing verifications once recorded
- Audit editing or cancellation workflows
- Email notifications for audit completion
- Historical comparison of multiple audit runs
- Connection comparison logic changes (use existing service as-is)
- Permission/role management (use existing audit assignee system)
