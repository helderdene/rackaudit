# Task Breakdown: Scheduled Report Generation

## Overview
Total Tasks: 54 (across 6 task groups)

This feature enables users to configure automated report generation on predefined schedules with email distribution via named distribution lists, integrating with the existing Custom Report Builder.

## Task List

### Database Layer

#### Task Group 1: Distribution List Data Models
**Dependencies:** None
**Complexity:** Medium

- [x] 1.0 Complete distribution list database layer
  - [x] 1.1 Write 4-6 focused tests for DistributionList and DistributionListMember models
    - Test DistributionList creation with name, description, user ownership
    - Test DistributionListMember email validation
    - Test relationship between DistributionList and members
    - Test cascade delete behavior (deleting list removes members)
    - Test unique email constraint within a distribution list
  - [x] 1.2 Create DistributionList model with validations
    - Fields: id, name, description, user_id, created_at, updated_at
    - Validations: name required, max 255 chars
    - Relationship: belongsTo User (owner)
    - Relationship: hasMany DistributionListMember
  - [x] 1.3 Create migration for distribution_lists table
    - Add foreign key: user_id references users(id) with cascade delete
    - Add unique index: (user_id, name) to prevent duplicate list names per user
  - [x] 1.4 Create DistributionListMember model with validations
    - Fields: id, distribution_list_id, email, sort_order, created_at, updated_at
    - Validations: email required, valid email format
    - Relationship: belongsTo DistributionList
  - [x] 1.5 Create migration for distribution_list_members table
    - Add foreign key: distribution_list_id references distribution_lists(id) with cascade delete
    - Add unique index: (distribution_list_id, email) to prevent duplicate emails in a list
    - Add index on sort_order for ordering
  - [x] 1.6 Create factories for DistributionList and DistributionListMember
    - DistributionListFactory with states: withMembers, empty
    - DistributionListMemberFactory
  - [x] 1.7 Ensure distribution list tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify migrations run successfully

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- Models pass validation tests
- Migrations run successfully
- Associations work correctly

---

#### Task Group 2: Report Schedule Data Models
**Dependencies:** Task Group 1
**Complexity:** High

- [x] 2.0 Complete report schedule database layer
  - [x] 2.1 Write 6-8 focused tests for ReportSchedule model
    - Test ReportSchedule creation with all frequency types (daily, weekly, monthly)
    - Test relationship to User (owner)
    - Test relationship to DistributionList
    - Test JSON storage of report configuration
    - Test schedule enable/disable functionality
    - Test next_run_at calculation for each frequency type
    - Test timezone handling
  - [x] 2.2 Create ScheduleFrequency enum
    - Cases: Daily, Weekly, Monthly
    - Methods: label(), description()
    - Location: app/Enums/ScheduleFrequency.php
  - [x] 2.3 Create ReportFormat enum
    - Cases: PDF, CSV
    - Methods: label(), mimeType(), extension()
    - Location: app/Enums/ReportFormat.php
  - [x] 2.4 Create ReportSchedule model with validations
    - Fields: id, name, user_id, distribution_list_id, report_type (enum), report_configuration (JSON), frequency (enum), day_of_week, day_of_month, time_of_day, timezone, format (enum), is_enabled, consecutive_failures, next_run_at, last_run_at, last_run_status, created_at, updated_at, deleted_at
    - Validations: name required, frequency required, time_of_day required, format required
    - Relationships: belongsTo User, belongsTo DistributionList
    - Casts: report_type => ReportType, frequency => ScheduleFrequency, format => ReportFormat, report_configuration => array
  - [x] 2.5 Create migration for report_schedules table
    - Add foreign key: user_id references users(id) with cascade delete
    - Add foreign key: distribution_list_id references distribution_lists(id) with set null on delete
    - Add index on is_enabled and next_run_at for scheduler queries
    - Add soft deletes
  - [x] 2.6 Create ReportScheduleExecution model for execution history
    - Fields: id, report_schedule_id, status (pending/success/failed), started_at, completed_at, error_message, file_size_bytes, recipients_count, created_at
    - Relationship: belongsTo ReportSchedule
  - [x] 2.7 Create migration for report_schedule_executions table
    - Add foreign key: report_schedule_id references report_schedules(id) with cascade delete
    - Add index on status and created_at for history queries
  - [x] 2.8 Create factories for ReportSchedule and ReportScheduleExecution
    - ReportScheduleFactory with states: daily, weekly, monthly, disabled, failed
    - ReportScheduleExecutionFactory with states: success, failed, pending
  - [x] 2.9 Add helper methods to ReportSchedule model
    - calculateNextRunAt(): Carbon - calculate next execution time based on frequency
    - markAsRun(bool $success, ?string $error): void - update last run status
    - incrementFailureCount(): void - increment consecutive failures
    - resetFailureCount(): void - reset consecutive failures on success
    - shouldDisable(): bool - check if failures exceed threshold (3)
  - [x] 2.10 Ensure report schedule tests pass
    - Run ONLY the 6-8 tests written in 2.1
    - Verify migrations run successfully

**Acceptance Criteria:**
- The 6-8 tests written in 2.1 pass
- Enums created with proper methods
- Models pass validation tests
- Migrations run successfully
- Helper methods calculate next run times correctly

---

### Permissions & Authorization

#### Task Group 3: Permissions Setup
**Dependencies:** Task Groups 1-2
**Complexity:** Low

- [x] 3.0 Complete permissions setup
  - [x] 3.1 Write 4 focused tests for permission enforcement
    - Test IT Manager/Administrator can create/manage all scheduled reports
    - Test Operator/Auditor can create schedules within accessible datacenters
    - Test distribution list CRUD follows same role-based access
    - Test unauthorized users cannot access schedule management
  - [x] 3.2 Update RolesAndPermissionsSeeder with new permissions
    - Add to resourcePermissions array:
      - 'scheduled-reports' => ['view', 'create', 'update', 'delete']
      - 'distribution-lists' => ['view', 'create', 'update', 'delete']
    - Update role assignments following existing hierarchy:
      - Administrator: all permissions
      - IT Manager: all scheduled-reports and distribution-lists permissions
      - Operator: view + create for accessible datacenters
      - Auditor: view + create for accessible datacenters
      - Viewer: view only
  - [x] 3.3 Create ReportSchedulePolicy
    - viewAny: user can view scheduled-reports
    - view: owner or admin/IT Manager
    - create: user can create scheduled-reports + datacenter access check
    - update: owner or admin/IT Manager
    - delete: owner or admin/IT Manager
    - Location: app/Policies/ReportSchedulePolicy.php
  - [x] 3.4 Create DistributionListPolicy
    - viewAny: user can view distribution-lists
    - view: owner or admin/IT Manager
    - create: user can create distribution-lists
    - update: owner or admin/IT Manager
    - delete: owner or admin/IT Manager
    - Location: app/Policies/DistributionListPolicy.php
  - [x] 3.5 Register policies in AuthServiceProvider
  - [x] 3.6 Ensure permissions tests pass
    - Run ONLY the 4 tests written in 3.1

**Acceptance Criteria:**
- The 4 tests written in 3.1 pass
- Permissions seeded correctly
- Policies enforce proper access control

---

### API Layer

#### Task Group 4: Distribution List API
**Dependencies:** Task Groups 1-3
**Complexity:** Medium

- [x] 4.0 Complete distribution list API layer
  - [x] 4.1 Write 6-8 focused tests for distribution list endpoints
    - Test index returns user's distribution lists with member counts
    - Test store creates list with members and validates emails
    - Test show returns list with all members
    - Test update modifies list name/description and members
    - Test destroy removes list (cascade deletes members)
    - Test validation errors for invalid emails
    - Test authorization (user can only manage own lists)
  - [x] 4.2 Create StoreDistributionListRequest form request
    - Rules: name required|max:255, description nullable|max:1000, members array, members.*.email required|email
    - Authorization: can create distribution-lists
    - Location: app/Http/Requests/StoreDistributionListRequest.php
  - [x] 4.3 Create UpdateDistributionListRequest form request
    - Rules: name sometimes|required|max:255, description nullable|max:1000, members array, members.*.email required|email
    - Authorization: can update distribution list
    - Location: app/Http/Requests/UpdateDistributionListRequest.php
  - [x] 4.4 Create DistributionListController
    - index(): return user's lists with member counts
    - store(): create list with members
    - show(): return list with members
    - update(): update list and sync members
    - destroy(): delete list
    - Location: app/Http/Controllers/DistributionListController.php
  - [x] 4.5 Create DistributionListResource for API responses
    - Fields: id, name, description, members_count, members (when loaded), created_at
    - Location: app/Http/Resources/DistributionListResource.php
  - [x] 4.6 Register routes in routes/web.php
    - Route::resource('distribution-lists', DistributionListController::class)
    - Apply auth middleware
  - [x] 4.7 Ensure distribution list API tests pass
    - Run ONLY the 6-8 tests written in 4.1

**Acceptance Criteria:**
- The 6-8 tests written in 4.1 pass
- All CRUD operations work correctly
- Proper authorization enforced
- Email validation works

---

#### Task Group 5: Report Schedule API
**Dependencies:** Task Groups 1-4
**Complexity:** High

- [x] 5.0 Complete report schedule API layer
  - [x] 5.1 Write 6-8 focused tests for report schedule endpoints
    - Test index returns user's schedules with status info
    - Test store creates schedule with all frequency types
    - Test store validates report configuration against CustomReportBuilderService
    - Test toggle enables/disables schedule
    - Test destroy removes schedule
    - Test execution history retrieval
    - Test re-enable after failure
  - [x] 5.2 Create StoreReportScheduleRequest form request
    - Rules: name required, distribution_list_id required|exists, report_type required|in:capacity,assets,connections,audit_history, report_configuration required|array (columns, filters, sort, group_by), frequency required|in:daily,weekly,monthly, day_of_week nullable|required_if:frequency,weekly|integer|between:0,6, day_of_month nullable|required_if:frequency,monthly|string (1-28 or 'last'), time_of_day required|date_format:H:i, timezone required|timezone, format required|in:pdf,csv
    - Custom validation: validate report_configuration structure
    - Authorization: can create scheduled-reports + datacenter access
    - Location: app/Http/Requests/StoreReportScheduleRequest.php
  - [x] 5.3 Create ToggleReportScheduleRequest form request
    - Rules: is_enabled required|boolean
    - Authorization: can update report schedule
    - Location: app/Http/Requests/ToggleReportScheduleRequest.php
  - [x] 5.4 Create ReportScheduleController
    - index(): return user's schedules with next_run_at, last_run_status
    - store(): create schedule, calculate next_run_at
    - show(): return schedule with execution history
    - toggle(): enable/disable schedule, reset failures if re-enabling
    - destroy(): soft delete schedule
    - history(): return paginated execution history
    - Location: app/Http/Controllers/ReportScheduleController.php
  - [x] 5.5 Create ReportScheduleResource for API responses
    - Fields: id, name, report_type, frequency, schedule_display (human readable), format, is_enabled, consecutive_failures, next_run_at, last_run_at, last_run_status, distribution_list (nested), created_at
    - Location: app/Http/Resources/ReportScheduleResource.php
  - [x] 5.6 Create ReportScheduleExecutionResource
    - Fields: id, status, started_at, completed_at, error_message, file_size_bytes, recipients_count, duration_seconds
    - Location: app/Http/Resources/ReportScheduleExecutionResource.php
  - [x] 5.7 Register routes in routes/web.php
    - Route::resource('report-schedules', ReportScheduleController::class)->except(['edit', 'update'])
    - Route::patch('report-schedules/{reportSchedule}/toggle', [ReportScheduleController::class, 'toggle'])
    - Route::get('report-schedules/{reportSchedule}/history', [ReportScheduleController::class, 'history'])
    - Apply auth middleware
  - [x] 5.8 Ensure report schedule API tests pass
    - Run ONLY the 6-8 tests written in 5.1

**Acceptance Criteria:**
- The 6-8 tests written in 5.1 pass
- Schedule creation validates configuration
- Toggle and history endpoints work
- Proper authorization enforced

---

### Backend Services & Jobs

#### Task Group 6: Report Generation Service & Job
**Dependencies:** Task Groups 1-5
**Complexity:** High

- [x] 6.0 Complete report generation backend
  - [x] 6.1 Write 6-8 focused tests for report generation
    - Test ScheduledReportGenerationService generates PDF report
    - Test ScheduledReportGenerationService generates CSV report
    - Test GenerateScheduledReportJob dispatches and handles success
    - Test GenerateScheduledReportJob handles failure and retries
    - Test job disables schedule after 3 consecutive failures
    - Test notifications sent on failure
    - Test datacenter access permissions respected at generation time
  - [x] 6.2 Extend CustomReportBuilderService with CSV generation
    - Add generateCsvReport() method following generatePdfReport() pattern
    - Return file path to generated CSV
    - Use same buildQuery() and field configuration logic
    - Location: app/Services/CustomReportBuilderService.php
  - [x] 6.3 Create ScheduledReportGenerationService
    - generateReport(ReportSchedule $schedule): string - returns file path
    - Determines format (PDF/CSV) and calls appropriate method
    - Applies creator's datacenter access permissions
    - Calculates file size for execution record
    - Location: app/Services/ScheduledReportGenerationService.php
  - [x] 6.4 Create ScheduledReportEmailService
    - sendReport(ReportSchedule $schedule, string $filePath): void
    - Retrieves distribution list members
    - Builds email with report name, timestamp, filters summary
    - Attaches report file
    - Handles attachment size limit (configurable, default 25MB)
    - Location: app/Services/ScheduledReportEmailService.php
  - [x] 6.5 Create GenerateScheduledReportJob
    - Properties: $tries = 2 (initial + 1 retry), $backoff = 300 (5 min)
    - Constructor: ReportSchedule $schedule
    - handle(): Generate report, send email, record success
    - failed(): Increment failure count, send failure notification, disable if threshold exceeded
    - Follow pattern from GenerateAuditReportJob
    - Location: app/Jobs/GenerateScheduledReportJob.php
  - [x] 6.6 Create ScheduledReportMailable
    - Subject: "[Report Name] - Generated [Date]"
    - Body: Report name, generation timestamp, applied filters summary
    - Attachment: PDF or CSV file
    - Location: app/Mail/ScheduledReportMailable.php
  - [x] 6.7 Create ScheduledReportFailedNotification
    - Follow pattern from DiscrepancyThresholdNotification
    - Channels: database + mail
    - Include: schedule name, error message, failure count, action link
    - Location: app/Notifications/ScheduledReportFailedNotification.php
  - [x] 6.8 Create ScheduledReportDisabledNotification
    - Sent when schedule disabled after 3 consecutive failures
    - Include: schedule name, instruction to re-enable
    - Location: app/Notifications/ScheduledReportDisabledNotification.php
  - [x] 6.9 Add configuration file for scheduled reports
    - config/scheduled-reports.php
    - Settings: max_attachment_size_mb, retry_delay_seconds, max_consecutive_failures
  - [x] 6.10 Ensure report generation tests pass
    - Run ONLY the 6-8 tests written in 6.1

**Acceptance Criteria:**
- The 6-8 tests written in 6.1 pass
- PDF and CSV generation work correctly
- Email delivery works with attachments
- Failure handling and notifications work
- Permissions enforced at generation time

---

### Scheduler Integration

#### Task Group 7: Console Scheduler
**Dependencies:** Task Group 6
**Complexity:** Medium

- [x] 7.0 Complete scheduler integration
  - [x] 7.1 Write 4 focused tests for scheduler
    - Test ProcessScheduledReportsCommand finds due schedules
    - Test command dispatches jobs for due schedules
    - Test command respects timezone for each schedule
    - Test command skips disabled schedules
  - [x] 7.2 Create ProcessScheduledReportsCommand
    - Signature: reports:process-scheduled
    - Description: Process due scheduled reports and dispatch generation jobs
    - Logic: Query report_schedules where is_enabled=true AND next_run_at <= now()
    - For each: dispatch GenerateScheduledReportJob, update next_run_at
    - Location: app/Console/Commands/ProcessScheduledReportsCommand.php
  - [x] 7.3 Register command in routes/console.php
    - Schedule::command('reports:process-scheduled')->everyMinute()
    - Add name and description for visibility
  - [x] 7.4 Ensure scheduler tests pass
    - Run ONLY the 4 tests written in 7.1

**Acceptance Criteria:**
- The 4 tests written in 7.1 pass
- Command correctly identifies due schedules
- Jobs dispatched for each due schedule
- Timezone handling works correctly

---

### Frontend Components

#### Task Group 8: Distribution List UI
**Dependencies:** Task Groups 4-5
**Complexity:** Medium

- [x] 8.0 Complete distribution list UI
  - [x] 8.1 Write 4-6 focused tests for distribution list components
    - Test distribution list index page renders correctly
    - Test create distribution list form validation
    - Test adding/removing members in form
    - Test edit distribution list loads existing data
    - Test delete confirmation works
  - [x] 8.2 Create DistributionLists/Index.vue page
    - List view with name, description, members count, actions
    - Create button linking to create form
    - Edit/Delete actions per row
    - Empty state when no lists exist
    - Location: resources/js/Pages/DistributionLists/Index.vue
  - [x] 8.3 Create DistributionLists/Create.vue page
    - Form fields: name, description
    - Dynamic email list with add/remove functionality
    - Email validation on input
    - Submit using Inertia Form component
    - Location: resources/js/Pages/DistributionLists/Create.vue
  - [x] 8.4 Create DistributionLists/Edit.vue page
    - Same form as create, pre-populated with existing data
    - Location: resources/js/Pages/DistributionLists/Edit.vue
  - [x] 8.5 Create DistributionListMemberInput.vue component
    - Input field with email validation
    - Add button to append to list
    - Remove button for each member
    - Drag-and-drop reordering (optional)
    - Location: resources/js/Components/DistributionLists/DistributionListMemberInput.vue
  - [x] 8.6 Add navigation link in AppSidebar.vue
    - Add "Distribution Lists" under Reports section
    - Icon: use appropriate icon from existing iconography
  - [x] 8.7 Ensure distribution list UI tests pass
    - Run ONLY the 4-6 tests written in 8.1

**Acceptance Criteria:**
- The 4-6 tests written in 8.1 pass
- List, create, edit pages render correctly
- Email validation works
- Navigation integrated

---

#### Task Group 9: Report Schedule UI
**Dependencies:** Task Groups 8
**Complexity:** High

- [x] 9.0 Complete report schedule UI
  - [x] 9.1 Write 6-8 focused tests for schedule components
    - Test schedule index page shows schedules with status
    - Test create schedule form captures all configuration
    - Test frequency selection shows appropriate day/time fields
    - Test enable/disable toggle works
    - Test execution history displays correctly
    - Test schedule creation integrates with Custom Report Builder
  - [x] 9.2 Create ReportSchedules/Index.vue page
    - List view with: name, report type, frequency display, format, status (enabled/disabled), next run, last run status
    - Enable/disable toggle per row
    - View history, delete actions
    - Create button linking to create form
    - Color-coded status indicators (success/failed/disabled)
    - Location: resources/js/Pages/ReportSchedules/Index.vue
  - [x] 9.3 Create ReportSchedules/Create.vue page
    - Step 1: Configure report (embed/reuse Custom Report Builder configuration)
    - Step 2: Configure schedule (frequency, day, time, timezone)
    - Step 3: Select distribution list and format (PDF/CSV)
    - Form validation for all steps
    - Preview of schedule summary before submit
    - Location: resources/js/Pages/ReportSchedules/Create.vue
  - [x] 9.4 Create ReportSchedules/Show.vue page
    - Display schedule configuration summary
    - Show report configuration (columns, filters)
    - Display distribution list with recipients
    - Enable/disable toggle
    - Execution history table with pagination
    - Re-enable button if disabled due to failures
    - Location: resources/js/Pages/ReportSchedules/Show.vue
  - [x] 9.5 Create ScheduleFrequencySelector.vue component
    - Radio/select for Daily/Weekly/Monthly
    - Day of week selector (shown for Weekly)
    - Day of month selector (shown for Monthly, 1-28 + "Last day" option)
    - Time picker (24h format)
    - Timezone selector (default to user's timezone)
    - Location: resources/js/Components/ReportSchedules/ScheduleFrequencySelector.vue
  - [x] 9.6 Create ScheduleStatusBadge.vue component
    - States: active (green), disabled (gray), failed (red/orange)
    - Shows consecutive failure count if > 0
    - Location: resources/js/Components/ReportSchedules/ScheduleStatusBadge.vue
  - [x] 9.7 Create ExecutionHistoryTable.vue component
    - Columns: date, status, duration, recipients, file size, error (if failed)
    - Pagination
    - Status icons/badges
    - Location: resources/js/Components/ReportSchedules/ExecutionHistoryTable.vue
  - [x] 9.8 Add navigation link in AppSidebar.vue
    - Add "Scheduled Reports" under Reports section
    - Position after Distribution Lists
  - [x] 9.9 Ensure report schedule UI tests pass
    - Run ONLY the 6-8 tests written in 9.1

**Acceptance Criteria:**
- The 6-8 tests written in 9.1 pass
- All pages render correctly
- Multi-step create form works
- Execution history displays properly
- Status indicators accurate

---

### Testing

#### Task Group 10: Test Review & Integration Testing
**Dependencies:** Task Groups 1-9
**Complexity:** Medium

- [x] 10.0 Review existing tests and fill critical gaps
  - [x] 10.1 Review tests from all previous task groups
    - Review tests from Task Groups 1-2 (database layer): approximately 10-14 tests
    - Review tests from Task Group 3 (permissions): 4 tests
    - Review tests from Task Groups 4-5 (API layer): 12-16 tests
    - Review tests from Task Groups 6-7 (backend services): 10-12 tests
    - Review tests from Task Groups 8-9 (UI components): 10-14 tests
    - Total existing tests: approximately 46-60 tests
  - [x] 10.2 Analyze test coverage gaps for this feature only
    - Identify critical end-to-end workflows lacking coverage
    - Focus on integration points between components
    - Check permission enforcement across full workflow
  - [x] 10.3 Write up to 8 additional integration tests
    - Test full workflow: create distribution list -> create schedule -> job runs -> email sent
    - Test schedule failure flow: generation fails -> retry -> disable after 3 failures -> notification
    - Test permission edge cases: Operator creating schedule for inaccessible datacenter
    - Test timezone edge cases: schedule crosses midnight in different timezone
    - Test large attachment handling: report exceeds size limit
    - Test re-enable flow: disabled schedule re-enabled -> failures reset -> next run calculated
  - [x] 10.4 Run feature-specific tests only
    - Run all tests from directories: tests/Feature/DistributionLists/, tests/Feature/ReportSchedules/
    - Verify total tests: approximately 54-68 tests
    - All tests should pass

**Acceptance Criteria:**
- All feature-specific tests pass
- Critical end-to-end workflows covered
- No more than 8 additional tests added
- Integration between components verified

---

## Execution Order

Recommended implementation sequence:

```
Phase 1: Database Foundation
  1. Task Group 1: Distribution List Data Models
  2. Task Group 2: Report Schedule Data Models
  3. Task Group 3: Permissions Setup

Phase 2: Backend API
  4. Task Group 4: Distribution List API
  5. Task Group 5: Report Schedule API

Phase 3: Backend Services
  6. Task Group 6: Report Generation Service & Job
  7. Task Group 7: Console Scheduler

Phase 4: Frontend
  8. Task Group 8: Distribution List UI
  9. Task Group 9: Report Schedule UI

Phase 5: Verification
  10. Task Group 10: Test Review & Integration Testing
```

## Technical Notes

### Existing Code to Leverage
- **CustomReportBuilderService**: Reuse buildQuery(), generatePdfReport(), extend with generateCsvReport()
- **DiscrepancyThresholdNotification**: Pattern for queued notifications with database + mail channels
- **GenerateAuditReportJob**: Pattern for background jobs with retry logic
- **routes/console.php**: Pattern for scheduled tasks registration

### New Files to Create
- **Enums**: ScheduleFrequency, ReportFormat
- **Models**: DistributionList, DistributionListMember, ReportSchedule, ReportScheduleExecution
- **Policies**: ReportSchedulePolicy, DistributionListPolicy
- **Controllers**: DistributionListController, ReportScheduleController
- **Form Requests**: StoreDistributionListRequest, UpdateDistributionListRequest, StoreReportScheduleRequest, ToggleReportScheduleRequest
- **Resources**: DistributionListResource, ReportScheduleResource, ReportScheduleExecutionResource
- **Services**: ScheduledReportGenerationService, ScheduledReportEmailService
- **Jobs**: GenerateScheduledReportJob
- **Mailables**: ScheduledReportMailable
- **Notifications**: ScheduledReportFailedNotification, ScheduledReportDisabledNotification
- **Commands**: ProcessScheduledReportsCommand
- **Vue Pages**: DistributionLists/Index, Create, Edit; ReportSchedules/Index, Create, Show
- **Vue Components**: DistributionListMemberInput, ScheduleFrequencySelector, ScheduleStatusBadge, ExecutionHistoryTable

### Configuration
- Add config/scheduled-reports.php with:
  - max_attachment_size_mb: 25
  - retry_delay_seconds: 300
  - max_consecutive_failures: 3

### Database Changes
- 4 new tables: distribution_lists, distribution_list_members, report_schedules, report_schedule_executions
- Update RolesAndPermissionsSeeder with new permissions
