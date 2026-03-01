# Activity Logging Infrastructure - Requirements

## Confirmed Decisions

### Data Architecture
- **Single polymorphic table**: Use one `activity_logs` table with polymorphic relationships to support any model (users, datacenters, racks, devices, connections, etc.)

### Performance & Processing
- **Synchronous logging**: Write logs immediately (not queued) for guaranteed consistency

### Data Captured Per Log Entry
- Action type (created, updated, deleted)
- Actor user ID (who performed the action)
- Affected model type/ID (polymorphic)
- Old/new values for updates (as JSON)
- IP address
- User agent
- Timestamp

### What NOT to Log
- Read/view actions (reduces log volume)
- Sensitive field values (e.g., passwords) - log the event but exclude actual values

### Access Control
- Role-based filtering for activity log viewer:
  - Administrators: Full access to all logs
  - Other roles: See activity relevant to their scope (own activities, within their datacenters)

### Data Retention
- Archive/delete logs older than 1 year
- Implement automatic cleanup policy

### UI Features
- Filtering by: date range, action type, user, entity type
- Full-text search within change details
- Pagination

## Existing Code to Leverage
- Event system in `app/Events/UserManagement/` - add listeners to persist logs
- Users/Index.vue table pattern - reuse for activity log list view
- RBAC roles via Spatie Laravel-Permission

## Technical Notes
- MySQL 8 database
- Laravel 12, Vue 3, Inertia.js v2, Tailwind CSS v4
