# Specification: Activity Logging Infrastructure

## Goal
Build a comprehensive activity logging system that tracks all create, update, and delete operations across the application, providing administrators with full audit visibility and role-based access for other users to view relevant activity within their scope.

## User Stories
- As an Administrator, I want to view all activity logs across the system so that I can audit user actions and maintain security compliance
- As an IT Manager or Operator, I want to see activity logs relevant to my assigned datacenters so that I can monitor changes within my scope of responsibility

## Specific Requirements

**Single Polymorphic Activity Log Table**
- Create `activity_logs` table with polymorphic `subject_type` and `subject_id` columns
- Store action type as enum: `created`, `updated`, `deleted`
- Use nullable `causer_id` (foreign key to users) for the actor who performed the action
- Store `old_values` and `new_values` as JSON columns for change tracking
- Include `ip_address` (45 chars for IPv6) and `user_agent` (text) columns
- Add composite index on `(subject_type, subject_id)` and index on `causer_id` for query performance
- Add fulltext index on `old_values` and `new_values` JSON columns for search capability

**ActivityLog Eloquent Model**
- Create `ActivityLog` model in `app/Models/` with polymorphic `subject()` relationship
- Define `causer()` belongsTo relationship to User model
- Implement `scopeForSubject()`, `scopeByUser()`, `scopeByAction()`, `scopeInDateRange()` query scopes
- Cast `old_values` and `new_values` to arrays
- Add `$hidden` array to protect sensitive fields from serialization if needed

**Loggable Trait for Models**
- Create `Loggable` trait in `app/Models/Concerns/` to be applied to any model needing activity logging
- Define `$excludeFromActivityLog` property for sensitive fields (e.g., password)
- Trait hooks into model's `created`, `updated`, `deleted` events via `booted()` method
- Trait captures old/new values automatically, filtering out excluded fields
- Use `request()->user()`, `request()->ip()`, `request()->userAgent()` to capture context

**ActivityLogService**
- Create `ActivityLogService` class in `app/Services/` for direct logging use cases
- Accept subject model, action, old/new values, and optional causer override
- Filter sensitive fields before persisting (check `$excludeFromActivityLog` on subject model)
- Write synchronously (no queuing) to ensure consistency

**Activity Log API Controller**
- Create `ActivityLogController` in `app/Http/Controllers/` with `index` method
- Accept query parameters: `start_date`, `end_date`, `action`, `user_id`, `subject_type`, `search`
- Apply role-based filtering: Administrators see all; others see own activities + activities within their datacenter scope
- Use eager loading for `causer` and `subject` relationships
- Return paginated results (25 per page) via Inertia

**Form Request for Filtering**
- Create `ActivityLogIndexRequest` in `app/Http/Requests/`
- Validate date format, action enum values, user_id existence, subject_type from allowed list
- Sanitize search input

**Role-Based Access Control**
- Administrators: Full unrestricted access to all activity logs
- IT Manager/Operator: Access logs where `causer_id` is self OR subject belongs to a datacenter they have access to
- Auditor: Read-only access to logs within their datacenter scope (same as IT Manager)
- Viewer: Only see their own activity logs

**Data Retention Artisan Command**
- Create `activity:cleanup` command in `app/Console/Commands/`
- Accept `--days` option (default 365) for retention period
- Delete records older than retention period in chunks of 1000 to avoid memory issues
- Output count of deleted records
- Register in scheduler for daily execution

**Activity Log List Page**
- Create `ActivityLogs/Index.vue` in `resources/js/Pages/`
- Follow table pattern from `Users/Index.vue`: header with filters, table body, pagination
- Filter controls: date range picker (start/end), action type dropdown, user dropdown, entity type dropdown, search input
- Table columns: Timestamp, User, Action, Entity Type, Entity ID, Summary
- Debounced search input (300ms delay)
- Click on row to expand and show detailed old/new value diff

**UI Components**
- Create `ActionBadge.vue` component similar to `StatusBadge.vue` with variants for created/updated/deleted
- Create `ActivityDetailPanel.vue` for expanded row showing JSON diff of changes
- Use existing `HeadingSmall`, `Input`, `Button`, `Skeleton` components from UI library

**Route Registration**
- Add `GET /activity-logs` route in `routes/web.php` within auth middleware group
- Use route name `activity-logs.index`
- Apply `permission:view activity logs` middleware or role-based check in controller

## Visual Design
No visual mockups provided. Follow existing design patterns from `Users/Index.vue` for table layout, filters, and pagination styling.

## Existing Code to Leverage

**Event System (`app/Events/UserManagement/`)**
- Pattern shows events with `$user`, `$actor`, `$oldValues`, `$newValues`, `$timestamp` properties
- Activity logging can either create listeners for these events OR use the Loggable trait approach
- Consider creating `ActivityLogSubscriber` to listen to existing events and persist logs

**Users/Index.vue Table Pattern**
- Reuse table structure: header row with muted background, hover states on rows, pagination controls
- Copy filter implementation: search input, dropdown selects with styled borders
- Follow TypeScript interface pattern for props (PaginatedData, Filters)
- Use `debounce` utility from `@/lib/utils` for search

**StatusBadge.vue Component Pattern**
- Create ActionBadge following same structure: variant map, label map, computed properties
- Use Badge component with appropriate color variants (success for created, warning for updated, destructive for deleted)

**StoreUserRequest.php Validation Pattern**
- Follow same structure for ActivityLogIndexRequest: const arrays for valid values, Rule::in validation
- Include custom error messages for each rule

**UserController.php Controller Pattern**
- Follow index method structure: query building with filters, pagination with through(), Inertia render
- Handle both JSON and Inertia responses

## Out of Scope
- Real-time activity log streaming/websockets
- Export functionality (CSV, PDF)
- Email notifications for specific activities
- Activity log dashboard widgets/charts
- Reverting changes from activity log
- Custom retention periods per entity type
- Logging read/view actions
- External audit log integration (SIEM)
- Activity log archival to cold storage
- Bulk delete from activity log UI
