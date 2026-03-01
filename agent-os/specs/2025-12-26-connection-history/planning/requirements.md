# Spec Requirements: Connection History

## Initial Description
Connection History - Track all changes to connections with timestamps, users, and before/after states

## Requirements Discussion

### First Round Questions

**Q1:** I notice the existing `ActivityLog` system already captures create, update, and delete events for connections via the `Loggable` trait. I assume you want a dedicated Connection History feature that provides a more user-friendly, connection-specific view of these changes rather than relying on the generic activity log. Is that correct, or do you want an entirely separate tracking mechanism?
**Answer:** Correct - They want a dedicated Connection History feature with a user-friendly view (leveraging existing ActivityLog)

**Q2:** For the history view, I'm thinking a timeline display on the connection detail page showing each change with a clear before/after comparison (e.g., "Cable length changed from 2.5m to 3.0m"). Should this also include a standalone history page where users can search/filter connection changes across the entire system?
**Answer:** Correct - Timeline on connection detail page + standalone history page with search/filter

**Q3:** I assume you want to track changes to all connection attributes: cable_type, cable_length, cable_color, path_notes, and the source/destination ports. Should we also track related events like when a connection is restored from soft-delete, or when port statuses change as a result of connection changes?
**Answer:** Correct - Track all connection attributes + related events like soft-delete restores and port status changes

**Q4:** For the user display, I'm assuming we should show the user's name who made the change along with their role (e.g., "John Doe (Operator)"). Should we also display the IP address and timestamp in a user-friendly format (e.g., "2 hours ago" with hover to show exact time)?
**Answer:** Correct - Show user's name, role, IP address, and user-friendly timestamps

**Q5:** Regarding retention, I assume connection history should be kept indefinitely since this is critical for audit purposes. Is that correct, or should there be configurable retention periods?
**Answer:** Correct - Keep history indefinitely for audit purposes

**Q6:** For the before/after state display, should we show the full connection state at each point in time, or only the specific fields that changed? For example, if only the cable color changed, do we show just "cable_color: blue -> red" or the complete connection snapshot?
**Answer:** Show the full connection state at each point in time (complete snapshot, not just changed fields)

**Q7:** Should users with specific roles (e.g., Auditors, IT Managers) be able to export connection history to CSV/PDF for compliance documentation and audit reports?
**Answer:** Correct - Export to CSV/PDF for compliance/audit

**Q8:** Is there anything specific that should NOT be included in this feature, or any functionality you want to explicitly defer to a future phase?
**Answer:** No answer provided - nothing explicitly deferred

### Existing Code to Reference

No similar existing features identified for reference.

**Note:** The existing `ActivityLog` model and `Loggable` trait already capture connection changes. Key files to leverage:
- `app/Models/ActivityLog.php` - Polymorphic activity log model with old_values/new_values JSON columns
- `app/Models/Concerns/Loggable.php` - Trait that auto-logs create, update, delete events
- `app/Models/Connection.php` - Connection model already uses Loggable trait
- `app/Http/Controllers/ConnectionController.php` - Existing connection CRUD controller

### Follow-up Questions

No follow-up questions were necessary.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A

## Requirements Summary

### Functional Requirements
- **Connection Detail Timeline:** Display a chronological timeline of all changes to a specific connection on its detail page
- **Standalone History Page:** Provide a dedicated page to search and filter connection changes across the entire system
- **Complete State Snapshots:** Show the full connection state at each point in time, not just changed fields
- **User Context Display:** Show user's name, role, IP address, and user-friendly timestamps (e.g., "2 hours ago" with hover for exact time)
- **Tracked Attributes:** Track all connection attributes (cable_type, cable_length, cable_color, path_notes, source_port_id, destination_port_id)
- **Related Events:** Track soft-delete restores and associated port status changes as part of connection history
- **Export Functionality:** Allow export to CSV and PDF for compliance documentation and audit reports
- **Indefinite Retention:** Keep all connection history indefinitely for audit purposes

### Reusability Opportunities
- Leverage existing `ActivityLog` model and infrastructure
- Extend `Loggable` trait if needed to capture additional events (restores, related port changes)
- Reuse existing export patterns from bulk export functionality (`app/Models/BulkExport.php`)
- Follow existing controller patterns from `ConnectionController`

### Scope Boundaries
**In Scope:**
- Timeline view on connection detail page
- Standalone connection history page with search/filter
- Full state snapshots for each history entry
- User, role, IP, and timestamp display
- Tracking of all connection attributes
- Tracking of soft-delete restores
- Tracking of related port status changes
- CSV export of history data
- PDF export of history data
- Indefinite history retention

**Out of Scope:**
- No features were explicitly deferred
- Configurable retention periods (keeping indefinitely per requirements)

### Technical Considerations
- Existing `ActivityLog` already stores `old_values` and `new_values` as JSON - can be leveraged for full snapshots
- Connection model already uses `Loggable` trait - may need extension for restore events
- Need to consider how to link port status changes to parent connection changes
- PDF generation can use existing Laravel DomPDF infrastructure
- CSV export can use existing Laravel Excel infrastructure
- Search/filter on standalone page should support filtering by date range, user, action type, and connection attributes
