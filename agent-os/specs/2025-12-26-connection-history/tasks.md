# Task Breakdown: Connection History

## Overview
Total Tasks: 34 sub-tasks across 5 task groups

This feature provides a user-friendly, connection-specific view of all changes with timeline display on connection detail pages and a standalone searchable history page with CSV/PDF export capabilities.

## Task List

### Backend Infrastructure Layer

#### Task Group 1: Loggable Trait Extension and Full State Snapshots
**Dependencies:** None

- [x] 1.0 Complete Loggable trait enhancements for connection history
  - [x] 1.1 Write 4-6 focused tests for enhanced Loggable trait functionality
    - Test full state snapshot capture on connection updates (all attributes, not just changed)
    - Test "restored" event logging when soft-deleted connection is recovered
    - Test resolved port labels and device names are included in snapshots
    - Test cable_type enum is stored with human-readable label
    - Skip exhaustive coverage of all edge cases
  - [x] 1.2 Extend Loggable trait with bootLoggableRestore() method
    - Listen to `restoring` and `restored` model events
    - Log the full connection state as new_values when restored
    - Set action to "restored" for restore events
    - Follow existing bootLoggable() pattern in `app/Models/Concerns/Loggable.php`
  - [x] 1.3 Modify Loggable::updated() to capture full state snapshots
    - Change from storing only changed fields to storing complete connection state
    - Include all fillable attributes in old_values and new_values
    - Maintain backward compatibility for other models using trait
    - Consider adding `$logFullState` property flag for Connection model
  - [x] 1.4 Add helper method for enriching connection snapshots
    - Resolve source_port_id and destination_port_id to port labels
    - Include device names for each port
    - Store cable_type enum value with human-readable label
    - Add method `getEnrichedAttributesForLog()` to Connection model
  - [x] 1.5 Ensure Loggable trait tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify full state snapshots work correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- Connection updates log full state (all attributes) in old_values and new_values
- Restore events are captured with action "restored"
- Snapshots include resolved port labels and device names
- Cable type enum values include human-readable labels

---

#### Task Group 2: Connection History Controller and API
**Dependencies:** Task Group 1

- [x] 2.0 Complete Connection History API layer
  - [x] 2.1 Write 5-7 focused tests for ConnectionHistoryController
    - Test index returns paginated connection activity logs
    - Test filtering by date range, user, action type
    - Test search across old_values and new_values JSON
    - Test role-based access (Auditor has full read access)
    - Test timeline endpoint for specific connection returns chronological entries
    - Skip exhaustive testing of all filter combinations
  - [x] 2.2 Create ConnectionHistoryController with index action
    - Inherit filtering patterns from ActivityLogController
    - Scope query to subject_type = Connection
    - Include causer relationship with user role
    - Apply role-based filtering (Auditor gets full access)
    - Return paginated results with 25 items per page
    - Follow pattern from `app/Http/Controllers/ActivityLogController.php`
  - [x] 2.3 Create timeline action for connection-specific history
    - Accept connection ID parameter
    - Return chronological entries for single connection
    - Support "Load more" pagination (limit 10 initially, then paginate)
    - Include related port status change events (linked via connection_id)
  - [x] 2.4 Create ConnectionHistoryIndexRequest for validation
    - Validate date range filters (start_date, end_date)
    - Validate action filter (created, updated, deleted, restored)
    - Validate user_id filter
    - Validate search parameter
    - Follow pattern from `app/Http/Requests/ActivityLogIndexRequest.php`
  - [x] 2.5 Extend ConnectionPolicy with viewHistory() method
    - Auditor role should have full history read access
    - All authenticated users can view history (matches viewAny behavior)
    - Add policy check in controller
  - [x] 2.6 Register routes for connection history
    - GET `/connections/history` for standalone history page
    - GET `/connections/{connection}/timeline` for connection-specific timeline
    - Apply auth middleware
    - Register in `routes/web.php`
  - [x] 2.7 Ensure Connection History API tests pass
    - Run ONLY the 5-7 tests written in 2.1
    - Verify filtering and pagination work
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 5-7 tests written in 2.1 pass
- Connection history endpoint returns filtered, paginated results
- Timeline endpoint returns chronological entries for a connection
- Role-based access control works (Auditors have full read access)
- Search across JSON columns functions correctly

---

### Frontend Layer

#### Task Group 3: Connection History UI Components
**Dependencies:** Task Group 2

- [x] 3.0 Complete Connection History frontend components
  - [x] 3.1 Write 4-6 focused tests for connection history UI
    - Test ConnectionTimeline component renders entries correctly
    - Test color-coded action badges (green/yellow/red for created/updated/deleted)
    - Test expandable before/after state display
    - Test relative timestamp with hover for exact datetime
    - Skip exhaustive testing of all component states
  - [x] 3.2 Create ConnectionTimeline component for detail pages
    - Vertical timeline UI pattern with action-specific color coding
    - Display user name with role in parentheses (e.g., "John Doe (IT Manager)")
    - Show IP address for each change entry
    - Format timestamps as relative (e.g., "2 hours ago") with exact datetime on hover
    - Handle null causer_id showing "System" for automated changes
    - Integrate expandable entries using ActivityDetailPanel component
    - Support "Load more" pagination for additional entries
    - Reuse: `resources/js/components/activity/ActivityDetailPanel.vue`
  - [x] 3.3 Create ConnectionHistory/Index.vue standalone page
    - Follow existing ActivityLogs/Index.vue patterns for consistency
    - Implement search functionality across old_values and new_values
    - Filter controls: date range, user, action type (created/updated/deleted/restored)
    - Paginated table with expandable rows showing ActivityDetailPanel
    - Add export buttons for CSV and PDF (prepare for Task Group 4)
    - Reuse: `resources/js/Pages/ActivityLogs/Index.vue` as template
  - [x] 3.4 Create ConnectionHistoryRow component for table rows
    - Display timestamp, user (with role), action badge, connection identifier
    - Show summary of changes
    - Expandable to show full ActivityDetailPanel
    - Click to toggle expansion
  - [x] 3.5 Add ConnectionTimeline to connection detail dialog/page
    - Integrate timeline component into existing connection detail view
    - Position timeline in logical location within dialog
    - Lazy load timeline data on dialog open
    - Reference: ConnectionDetailDialog.vue location in codebase
  - [x] 3.6 Apply styling and responsive design
    - Follow existing design system and Tailwind conventions
    - Mobile-friendly timeline display (320px - 768px)
    - Responsive table layout for history page
    - Dark mode support matching existing components
  - [x] 3.7 Ensure UI component tests pass
    - Run ONLY the 4-6 tests written in 3.1
    - Verify components render correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 3.1 pass
- ConnectionTimeline displays chronological entries with color-coded actions
- Standalone history page has working search and filters
- Expandable rows show before/after comparison
- User context displays name, role, IP, and relative timestamps

---

### Export Layer

#### Task Group 4: CSV and PDF Export Functionality
**Dependencies:** Task Groups 2, 3

- [x] 4.0 Complete export functionality for connection history
  - [x] 4.1 Write 4-6 focused tests for export functionality
    - Test CSV export generates file with correct columns
    - Test CSV export respects current filter criteria
    - Test PDF export generates formatted document with header
    - Test export job progress tracking works
    - Skip exhaustive testing of all export scenarios
  - [x] 4.2 Create ConnectionHistoryExportJob for async processing
    - Follow BulkExport model pattern for status tracking
    - Support both CSV and PDF format parameter
    - Apply filter criteria passed from controller
    - Track progress (processed_rows, total_rows)
    - Follow pattern from existing export infrastructure
  - [x] 4.3 Implement CSV export format
    - Include columns: timestamp, user, role, IP, action, connection_id, old_values_summary, new_values_summary
    - Filter exports based on current page filters
    - Use League CSV or Laravel Excel pattern
    - Store in exports directory with timestamped filename
  - [x] 4.4 Implement PDF export using Laravel DomPDF
    - Generate formatted PDF report
    - Include header: export date, filter criteria, generating user
    - Format before/after changes in readable table layout
    - Use existing DomPDF configuration
    - Create Blade template for PDF layout
  - [x] 4.5 Create export controller actions
    - POST `/connections/history/export` for initiating export
    - GET `/connections/history/export/{id}/download` for download
    - GET `/connections/history/export/{id}/status` for polling status
    - Return BulkExport record for status tracking
  - [x] 4.6 Add export UI to ConnectionHistory/Index.vue
    - Export dropdown with CSV and PDF options
    - Show export status/progress modal
    - Download link when export complete
    - Follow BulkExport/Index.vue patterns for status display
  - [x] 4.7 Ensure export tests pass
    - Run ONLY the 4-6 tests written in 4.1
    - Verify exports generate correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 4.1 pass
- CSV export includes all required columns and respects filters
- PDF export is formatted with header and readable table layout
- Export status tracking works via BulkExport model
- Download links work after export completion

---

### Testing Layer

#### Task Group 5: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-4

- [x] 5.0 Review existing tests and fill critical gaps only
  - [x] 5.1 Review tests from Task Groups 1-4
    - Review the 4-6 tests from Task 1.1 (Loggable trait enhancements)
    - Review the 5-7 tests from Task 2.1 (ConnectionHistoryController)
    - Review the 4-6 tests from Task 3.1 (UI components)
    - Review the 4-6 tests from Task 4.1 (Export functionality)
    - Total existing tests: approximately 17-25 tests
  - [x] 5.2 Analyze test coverage gaps for Connection History feature
    - Identify critical user workflows lacking coverage
    - Focus ONLY on gaps related to connection history requirements
    - Prioritize end-to-end workflows: view timeline -> expand details -> export
    - Do NOT assess entire application test coverage
  - [x] 5.3 Write up to 8 additional strategic tests if needed
    - Add integration test for full history workflow (create connection -> update -> view history)
    - Add test for related port status changes appearing in timeline
    - Add test for "restored" action appearing after soft-delete recovery
    - Add browser test for history page filter interaction (if Pest v4 browser testing used)
    - Focus on integration points between components
    - Do NOT write comprehensive coverage for all scenarios
  - [x] 5.4 Run feature-specific tests only
    - Run ONLY tests related to Connection History feature
    - Expected total: approximately 25-33 tests maximum
    - Verify all critical workflows pass
    - Do NOT run the entire application test suite

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 25-33 tests total)
- Critical user workflows for connection history are covered
- No more than 8 additional tests added when filling gaps
- Testing focused exclusively on connection history requirements

---

## Execution Order

Recommended implementation sequence:

1. **Task Group 1: Loggable Trait Extension** - Extend backend infrastructure to capture full state snapshots and restore events
2. **Task Group 2: Connection History API** - Build controller, routes, and policy for serving history data
3. **Task Group 3: Connection History UI** - Create timeline component and standalone history page
4. **Task Group 4: Export Functionality** - Add CSV and PDF export capabilities
5. **Task Group 5: Test Review** - Review coverage and fill critical gaps

## Key Files Reference

### Existing Files to Leverage
- `app/Models/ActivityLog.php` - Activity log model with scopes
- `app/Models/Concerns/Loggable.php` - Trait to extend for restore events and full snapshots
- `app/Models/Connection.php` - Connection model already using Loggable trait
- `app/Http/Controllers/ActivityLogController.php` - Pattern for filtering and role-based access
- `app/Policies/ConnectionPolicy.php` - Policy to extend with viewHistory()
- `app/Models/BulkExport.php` - Export status tracking model
- `resources/js/Pages/ActivityLogs/Index.vue` - Template for history page
- `resources/js/components/activity/ActivityDetailPanel.vue` - Before/after comparison component

### New Files to Create
- `app/Http/Controllers/ConnectionHistoryController.php`
- `app/Http/Requests/ConnectionHistoryIndexRequest.php`
- `app/Jobs/ConnectionHistoryExportJob.php`
- `resources/js/Pages/ConnectionHistory/Index.vue`
- `resources/js/components/connections/ConnectionTimeline.vue`
- `resources/js/components/connections/ConnectionHistoryRow.vue`
- `resources/views/exports/connection-history-pdf.blade.php`
- `tests/Feature/ConnectionHistoryTest.php`
