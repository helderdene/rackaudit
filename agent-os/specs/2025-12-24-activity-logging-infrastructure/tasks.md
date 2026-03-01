# Task Breakdown: Activity Logging Infrastructure

## Overview
Total Tasks: 42

This task list implements a comprehensive activity logging system with polymorphic tracking, role-based access control, automatic model event logging via a trait, and a filterable UI for viewing logs.

## Task List

### Database Layer

#### Task Group 1: ActivityLog Model and Migration
**Dependencies:** None

- [x] 1.0 Complete database layer for activity logging
  - [x] 1.1 Write 4-6 focused tests for ActivityLog model functionality
    - Test ActivityLog model can be created with required fields
    - Test polymorphic `subject()` relationship resolves correctly
    - Test `causer()` relationship returns User model
    - Test query scopes: `scopeForSubject()`, `scopeByUser()`, `scopeByAction()`, `scopeInDateRange()`
    - Test `old_values` and `new_values` cast to arrays
  - [x] 1.2 Create migration for `activity_logs` table
    - Fields: `id`, `subject_type` (string), `subject_id` (unsigned big int), `causer_id` (nullable foreign key to users), `action` (enum: created, updated, deleted), `old_values` (json nullable), `new_values` (json nullable), `ip_address` (string 45 chars), `user_agent` (text nullable), `timestamps`
    - Add composite index on `(subject_type, subject_id)`
    - Add index on `causer_id`
    - Add fulltext index on `old_values` and `new_values` columns
  - [x] 1.3 Create ActivityLog Eloquent model in `app/Models/`
    - Define `$fillable` array with all fields
    - Define polymorphic `subject()` morphTo relationship
    - Define `causer()` belongsTo relationship to User model
    - Cast `old_values` and `new_values` to arrays using `casts()` method
    - Add `$hidden` array if needed for sensitive fields
  - [x] 1.4 Implement query scopes on ActivityLog model
    - `scopeForSubject($query, Model $subject)` - filter by polymorphic subject
    - `scopeByUser($query, int $userId)` - filter by causer_id
    - `scopeByAction($query, string $action)` - filter by action type
    - `scopeInDateRange($query, ?string $startDate, ?string $endDate)` - filter by created_at range
  - [x] 1.5 Create ActivityLog factory for testing
    - Define default values for all fields
    - Create states for each action type (created, updated, deleted)
  - [x] 1.6 Ensure database layer tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify migration runs successfully
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- Migration creates `activity_logs` table with correct schema and indexes
- ActivityLog model has working polymorphic and belongsTo relationships
- Query scopes filter correctly
- JSON columns cast to arrays

---

### Service Layer

#### Task Group 2: Loggable Trait and ActivityLogService
**Dependencies:** Task Group 1

- [x] 2.0 Complete service layer for activity logging
  - [x] 2.1 Write 4-6 focused tests for Loggable trait and ActivityLogService
    - Test Loggable trait logs `created` event with correct values
    - Test Loggable trait logs `updated` event with old/new values diff
    - Test Loggable trait logs `deleted` event
    - Test Loggable trait excludes fields from `$excludeFromActivityLog` property
    - Test ActivityLogService creates log entry with all required fields
    - Test ActivityLogService filters sensitive fields from subject model
  - [x] 2.2 Create `app/Models/Concerns/` directory if not exists
  - [x] 2.3 Create Loggable trait in `app/Models/Concerns/Loggable.php`
    - Define `$excludeFromActivityLog` property (array of field names to exclude, e.g., password)
    - Hook into model's `created`, `updated`, `deleted` events via `bootLoggable()` method
    - Capture old/new values automatically, filtering out excluded fields
    - Use `request()->user()`, `request()->ip()`, `request()->userAgent()` to capture context
    - Handle case when no authenticated user (system actions)
    - Write synchronously to ActivityLog model
  - [x] 2.4 Create `app/Services/` directory if not exists
  - [x] 2.5 Create ActivityLogService in `app/Services/ActivityLogService.php`
    - Method: `log(Model $subject, string $action, ?array $oldValues = null, ?array $newValues = null, ?User $causer = null): ActivityLog`
    - Filter sensitive fields before persisting (check `$excludeFromActivityLog` on subject model)
    - Capture IP address and user agent from request
    - Write synchronously (no queuing)
  - [x] 2.6 Ensure service layer tests pass
    - Run ONLY the 4-6 tests written in 2.1
    - Verify trait hooks fire on model events
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 2.1 pass
- Loggable trait automatically logs create/update/delete events
- Sensitive fields are excluded from log values
- ActivityLogService provides direct logging capability
- Request context (IP, user agent) captured correctly

---

### API Layer

#### Task Group 3: Controller and Form Request
**Dependencies:** Task Groups 1, 2

- [x] 3.0 Complete API layer for activity log viewing
  - [x] 3.1 Write 4-6 focused tests for ActivityLogController
    - Test index returns paginated activity logs for Administrator
    - Test index applies role-based filtering for non-Administrator users
    - Test index filters by date range correctly
    - Test index filters by action type correctly
    - Test index filters by user_id correctly
    - Test index search works across old_values/new_values
  - [x] 3.2 Create ActivityLogIndexRequest in `app/Http/Requests/`
    - Validate `start_date` as nullable date format
    - Validate `end_date` as nullable date format, after or equal to start_date
    - Validate `action` as nullable, Rule::in(['created', 'updated', 'deleted'])
    - Validate `user_id` as nullable, exists in users table
    - Validate `subject_type` as nullable, from allowed list of model types
    - Validate `search` as nullable string, max 255 chars
    - Add custom error messages following StoreUserRequest pattern
  - [x] 3.3 Create ActivityLogController in `app/Http/Controllers/`
    - Implement `index(ActivityLogIndexRequest $request)` method
    - Build query with eager loading for `causer` and `subject` relationships
    - Apply filters: start_date, end_date, action, user_id, subject_type, search
    - Implement role-based filtering:
      - Administrator: see all logs
      - IT Manager/Operator/Auditor: see own logs + logs where subject belongs to their datacenters
      - Viewer: see only own activity logs (where causer_id = auth user)
    - Return paginated results (25 per page) via Inertia::render
    - Follow UserController pattern for JSON/Inertia response handling
  - [x] 3.4 Add route in `routes/web.php`
    - Route: `GET /activity-logs`
    - Name: `activity-logs.index`
    - Apply auth middleware
    - Controller: `ActivityLogController@index`
  - [x] 3.5 Ensure API layer tests pass
    - Run ONLY the 4-6 tests written in 3.1
    - Verify role-based filtering works correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 3.1 pass
- Form request validates all filter parameters
- Controller applies correct role-based filtering
- Pagination returns 25 items per page
- Route registered and accessible

---

### Artisan Command Layer

#### Task Group 4: Data Retention Command
**Dependencies:** Task Group 1

- [x] 4.0 Complete data retention command
  - [x] 4.1 Write 2-4 focused tests for activity:cleanup command
    - Test command deletes records older than specified days
    - Test command uses default 365 days when --days not provided
    - Test command outputs count of deleted records
    - Test command processes in chunks to avoid memory issues
  - [x] 4.2 Create CleanupActivityLogs command in `app/Console/Commands/`
    - Command signature: `activity:cleanup {--days=365 : Number of days to retain logs}`
    - Delete records where `created_at` < now minus retention days
    - Process deletions in chunks of 1000 records
    - Output count of deleted records
  - [x] 4.3 Register command in scheduler for daily execution
    - Add to `routes/console.php` or `bootstrap/app.php` schedule
    - Schedule daily at off-peak time (e.g., 2:00 AM)
  - [x] 4.4 Ensure command tests pass
    - Run ONLY the 2-4 tests written in 4.1
    - Verify chunked deletion works correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-4 tests written in 4.1 pass
- Command deletes old records in chunks
- Default retention period is 365 days
- Command is scheduled for daily execution

---

### Frontend Components

#### Task Group 5: UI Components
**Dependencies:** Task Group 3

- [x] 5.0 Complete reusable UI components
  - [x] 5.1 Write 2-4 focused tests for UI components
    - Test ActionBadge renders correct variant for each action type
    - Test ActivityDetailPanel displays old/new values diff correctly
  - [x] 5.2 Create ActionBadge.vue component in `resources/js/components/`
    - Follow StatusBadge.vue pattern
    - Props: `action: 'created' | 'updated' | 'deleted'`
    - Variant map: created -> success, updated -> warning, deleted -> destructive
    - Label map: created -> Created, updated -> Updated, deleted -> Deleted
  - [x] 5.3 Create ActivityDetailPanel.vue component in `resources/js/components/activity/`
    - Props: `oldValues: Record<string, any> | null`, `newValues: Record<string, any> | null`
    - Display JSON diff of changes in a readable format
    - Highlight added (green), removed (red), and changed (yellow) values
    - Handle null values gracefully (e.g., for created/deleted actions)
    - Use existing UI components (Card, Skeleton if needed)
  - [x] 5.4 Ensure UI component tests pass
    - Run ONLY the 2-4 tests written in 5.1
    - Verify components render correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-4 tests written in 5.1 pass
- ActionBadge displays correct colors and labels
- ActivityDetailPanel shows change diffs clearly

---

#### Task Group 6: Activity Log Index Page
**Dependencies:** Task Groups 3, 5

- [x] 6.0 Complete Activity Log Index page
  - [x] 6.1 Write 2-4 focused tests for ActivityLogs/Index.vue
    - Test page renders table with activity log data
    - Test filter controls update query parameters
    - Test row expansion shows ActivityDetailPanel
    - Test pagination navigates correctly
  - [x] 6.2 Create ActivityLogs/Index.vue in `resources/js/Pages/`
    - Follow Users/Index.vue table pattern
    - Define TypeScript interfaces: ActivityLogData, PaginatedActivityLogs, Filters
    - Layout: AppLayout with breadcrumbs
  - [x] 6.3 Implement filter controls
    - Date range picker: start_date, end_date inputs (type="date")
    - Action type dropdown: All, Created, Updated, Deleted
    - User dropdown: populated from available users (for Administrators)
    - Entity type dropdown: populated from available subject types
    - Search input with 300ms debounce
    - Follow existing filter pattern from Users/Index.vue
  - [x] 6.4 Implement data table
    - Columns: Timestamp, User, Action, Entity Type, Entity ID, Summary
    - Use ActionBadge for action column
    - Format timestamp using relative time or locale date
    - Summary column: truncated preview of changes
    - Hover states on rows (bg-muted/50)
    - Click row to expand/collapse detail panel
  - [x] 6.5 Implement row expansion
    - Track expanded row IDs in reactive state
    - Toggle expansion on row click
    - Render ActivityDetailPanel below expanded row
    - Smooth transition for expand/collapse
  - [x] 6.6 Implement pagination
    - Follow Users/Index.vue pagination pattern
    - Show "Showing X to Y of Z results" text
    - Pagination buttons with disabled states
  - [x] 6.7 Add responsive design
    - Mobile: Stack filters vertically, horizontal scroll for table
    - Tablet: Filters in 2-column grid
    - Desktop: Filters in single row
  - [x] 6.8 Ensure page tests pass
    - Run ONLY the 2-4 tests written in 6.1
    - Verify page renders and interactions work
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-4 tests written in 6.1 pass
- Page displays paginated activity logs
- All filters work correctly with debounced search
- Row expansion shows change details
- Responsive layout works across breakpoints

---

### Integration

#### Task Group 7: Apply Loggable Trait to Models
**Dependencies:** Task Groups 1, 2

- [x] 7.0 Integrate activity logging with existing models
  - [x] 7.1 Write 2-4 focused integration tests
    - Test User model logs created event when new user created
    - Test User model logs updated event with correct old/new values
    - Test User model excludes password from logged values
    - Test Datacenter model logs events (if applicable)
  - [x] 7.2 Apply Loggable trait to User model
    - Add `use Loggable;` to User model
    - Define `$excludeFromActivityLog = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes']`
  - [x] 7.3 Apply Loggable trait to Datacenter model
    - Add `use Loggable;` to Datacenter model
    - Define any fields to exclude if needed
  - [x] 7.4 Ensure integration tests pass
    - Run ONLY the 2-4 tests written in 7.1
    - Verify activity logs created for model events
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-4 tests written in 7.1 pass
- User and Datacenter models automatically log events
- Sensitive fields excluded from logs

---

### Testing

#### Task Group 8: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-7

- [x] 8.0 Review existing tests and fill critical gaps only
  - [x] 8.1 Review tests from Task Groups 1-7
    - Review 4-6 tests from Task Group 1 (database layer)
    - Review 4-6 tests from Task Group 2 (service layer)
    - Review 4-6 tests from Task Group 3 (API layer)
    - Review 2-4 tests from Task Group 4 (command)
    - Review 2-4 tests from Task Group 5 (UI components)
    - Review 2-4 tests from Task Group 6 (Index page)
    - Review 2-4 tests from Task Group 7 (integration)
    - Total existing tests: approximately 20-34 tests
  - [x] 8.2 Analyze test coverage gaps for activity logging feature only
    - Identify critical user workflows that lack coverage
    - Focus ONLY on gaps related to this feature
    - Do NOT assess entire application test coverage
    - Prioritize end-to-end workflows over unit test gaps
  - [x] 8.3 Write up to 10 additional strategic tests maximum
    - End-to-end test: User creates record -> activity log visible in UI
    - End-to-end test: User updates record -> changes shown in detail panel
    - Authorization test: Viewer cannot see other users' logs
    - Authorization test: IT Manager sees logs within datacenter scope
    - Edge case: Activity log with null causer (system action)
    - Edge case: Large old/new values JSON handling
    - Do NOT write exhaustive tests for all scenarios
  - [x] 8.4 Run feature-specific tests only
    - Run ONLY tests related to activity logging feature
    - Expected total: approximately 30-44 tests maximum
    - Do NOT run the entire application test suite
    - Verify critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 30-44 tests total)
- Critical user workflows for activity logging are covered
- No more than 10 additional tests added in gap filling
- Testing focused exclusively on activity logging feature

---

## Execution Order

Recommended implementation sequence:

1. **Task Group 1: Database Layer** - Foundation for all other work
2. **Task Group 2: Service Layer** - Depends on database, enables automatic logging
3. **Task Group 4: Data Retention Command** - Depends only on database, can run in parallel with Group 3
4. **Task Group 3: API Layer** - Depends on database and service for querying
5. **Task Group 5: UI Components** - Depends on API for data types
6. **Task Group 6: Activity Log Index Page** - Depends on API and UI components
7. **Task Group 7: Model Integration** - Depends on service layer
8. **Task Group 8: Test Review** - Final validation after all implementation

```
Group 1 (Database)
    |
    +---> Group 2 (Service) ---> Group 7 (Integration)
    |         |
    |         v
    |     Group 3 (API) ---> Group 5 (UI) ---> Group 6 (Page)
    |
    +---> Group 4 (Command)

All groups ---> Group 8 (Test Review)
```

---

## Technical Notes

- **Database**: MySQL 8 with fulltext index support on JSON columns
- **Backend**: Laravel 12 with Eloquent ORM
- **Frontend**: Vue 3 with Inertia.js v2, Tailwind CSS v4
- **Testing**: Pest v4 for all tests
- **Existing patterns**: Follow `UserController`, `StoreUserRequest`, `Users/Index.vue`, `StatusBadge.vue` patterns
- **Role-based access**: Uses Spatie Laravel-Permission package already in use
