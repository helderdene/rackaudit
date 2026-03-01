# Specification: Connection History

## Goal
Provide a user-friendly, connection-specific view of all changes with timeline display on connection detail pages and a standalone searchable history page, enabling compliance auditing and change tracking with export capabilities.

## User Stories
- As an Auditor, I want to view a complete timeline of all changes to a specific connection so that I can verify compliance and trace when modifications occurred
- As an IT Manager, I want to search and filter connection changes across the entire system so that I can identify patterns and troubleshoot issues
- As an Administrator, I want to export connection history to CSV/PDF so that I can provide audit documentation for compliance reviews

## Specific Requirements

**Connection Detail Timeline Component**
- Display chronological timeline of changes on the connection detail dialog/page
- Show each history entry with user-friendly timestamp (e.g., "2 hours ago" with hover tooltip for exact datetime)
- Display the user's name, role, and IP address for each change
- Use vertical timeline UI pattern with action-specific color coding (green for created, yellow for updated, red for deleted)
- Expandable entries to show full before/after state comparison
- Limit initial display to recent entries with "Load more" pagination

**Standalone Connection History Page**
- Create dedicated page at `/connections/history` for system-wide connection history viewing
- Implement search functionality across old_values and new_values JSON columns
- Provide filter controls: date range, user, action type (created/updated/deleted/restored), and cable properties
- Display paginated table with expandable rows showing ActivityDetailPanel for before/after comparison
- Follow existing ActivityLogs/Index.vue page patterns for consistency

**Full State Snapshots for History Entries**
- Store complete connection state (all attributes) in old_values/new_values, not just changed fields
- Modify Loggable trait usage to capture full state on updates, not filtered changes
- Include resolved port labels and device names in snapshots for historical context
- Store cable_type enum values with human-readable labels for display

**Soft-Delete Restore Tracking**
- Extend Loggable trait to capture "restored" events when connections are recovered from soft-delete
- Log the full connection state as new_values when restored
- Update port status changes to "Connected" when connection is restored

**Related Port Status Change Tracking**
- When connection is created/deleted, log port status changes as linked events
- Store connection_id reference in port change activity logs for correlation
- Display port status changes in connection timeline as related events

**User Context Display**
- Show user's name with their role in parentheses (e.g., "John Doe (IT Manager)")
- Display IP address for each change entry
- Format timestamps using relative time (e.g., "5 minutes ago") with exact datetime on hover
- Handle null causer_id gracefully showing "System" for automated changes

**Export to CSV Functionality**
- Add export button on standalone history page for CSV download
- Include all visible columns: timestamp, user, action, connection ID, old/new values summary
- Filter exports based on current page filters (date range, user, action type)
- Use existing BulkExport infrastructure pattern for async export processing

**Export to PDF Functionality**
- Generate formatted PDF report of connection history
- Include page header with export date, filter criteria, and user who generated report
- Format before/after changes in readable table layout
- Use Laravel DomPDF for PDF generation following existing export patterns

## Visual Design
No visual mockups provided. Follow existing UI patterns from:
- ActivityLogs/Index.vue for table layout, filtering, and pagination
- ActivityDetailPanel.vue for before/after comparison display
- ConnectionDetailDialog.vue for timeline integration placement
- BulkExport/Index.vue for export status display patterns

## Existing Code to Leverage

**ActivityLog Model and Loggable Trait**
- ActivityLog already has polymorphic subject relationship and JSON old_values/new_values columns
- Loggable trait auto-logs create/update/delete events with IP and user agent
- Existing scopes: forSubject(), byUser(), byAction(), inDateRange() ready for reuse
- Extend trait to add bootLoggableRestore() for soft-delete restore event tracking

**ActivityLogController and Index.vue Page**
- Controller has role-based filtering logic that can be adapted for connection history
- Index.vue has complete filter UI with search, date range, action, user, and subject type dropdowns
- Reuse formatTimestamp(), formatSubjectType(), getSummary() utility functions
- Leverage expandable row pattern with ActivityDetailPanel for detail view

**ActivityDetailPanel Component**
- Complete before/after comparison UI with color-coded change indicators
- Handles created (new values only), deleted (old values only), and updated (both) states
- Row styling with green/red/yellow backgrounds for added/removed/modified fields
- Directly reusable for connection history detail expansion

**ConnectionPolicy and Permission System**
- Existing policy defines Administrator/IT Manager as admin roles with full access
- All authenticated users can view connections (viewAny, view return true)
- Extend policy with viewHistory() method for history page access control
- Auditor role should have full history read access for compliance

**BulkExport Model and Export Patterns**
- BulkExport tracks export status (pending/processing/completed/failed), progress, and file path
- User association and timestamp tracking already implemented
- Reuse entity_type enum pattern for "connection_history" export type
- Follow async job pattern with progress tracking for large history exports

## Out of Scope
- Real-time live updates/websocket notifications when connection changes occur
- Configurable retention periods - history is kept indefinitely per requirements
- Bulk history operations (e.g., bulk revert changes)
- Comparison view between two arbitrary points in time
- Email notifications for connection changes
- API endpoints for external system integration with history data
- History for other entity types (this spec is connection-specific only)
- Graphical visualizations or charts of change frequency
- Comments or annotations on history entries
- Audit trail signing or tamper-proofing mechanisms
