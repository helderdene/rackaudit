# Specification: Scheduled Report Generation

## Goal
Enable users to configure automated report generation on predefined schedules with email distribution via named distribution lists, integrating with the existing Custom Report Builder.

## User Stories
- As an IT Manager, I want to schedule weekly capacity reports to be sent to my team automatically so that everyone stays informed without manual effort.
- As an Auditor, I want to create distribution lists of recipients so that I can easily reuse the same groups across multiple scheduled reports.

## Specific Requirements

**Schedule Configuration**
- Support three frequency types: daily, weekly, and monthly
- Daily: select specific time of day
- Weekly: select day of week (Monday-Sunday) and time of day
- Monthly: select day of month (1-28 or "last day") and time of day
- All schedules are timezone-aware, defaulting to the user's timezone or system timezone
- Allow users to create multiple schedules for the same report configuration

**Report Type Integration**
- Integrate with existing Custom Report Builder (CustomReportBuilderService)
- Support all 4 report types: Capacity, Assets, Connections, Audit History
- Store complete report configuration (report_type, columns, filters, sort, group_by)
- Report configuration is captured at schedule creation time but uses current data at generation time

**Distribution List Management**
- Create new standalone feature for managing named distribution lists
- Support CRUD operations: create, read, update, delete distribution lists
- Each list has a name (e.g., "Finance Team", "Weekly Audit Recipients") and description
- Manage email addresses within each list (add, remove, reorder)
- Distribution lists are reusable across multiple report schedules
- Validate email addresses on entry

**Email Delivery**
- Deliver reports as email attachments only (no download links)
- Support PDF and CSV attachment formats (user selects format in schedule)
- Email includes report name, generation timestamp, and applied filters summary
- Handle large attachments gracefully (configurable size limit, notify on failure)

**Permission Model**
- IT Managers and Administrators can create/manage all scheduled reports
- Operators and Auditors can create schedules for reports within their accessible datacenters
- Scheduled reports respect the creator's datacenter access permissions at generation time
- Distribution list management follows same role-based access

**Failure Handling**
- Notify schedule owner via email and in-app notification on generation or delivery failure
- Automatically retry once after initial failure (configurable delay)
- Disable schedule after 3 consecutive failures with notification to owner
- Log all generation attempts with status for troubleshooting
- Provide manual re-enable option in UI after schedule is disabled

**Schedule Management UI**
- List view showing all user's schedules with status, next run time, last run result
- Enable/disable toggle for each schedule
- Edit and delete actions
- View execution history for each schedule

## Existing Code to Leverage

**CustomReportBuilderService (app/Services/CustomReportBuilderService.php)**
- Reuse buildQuery() method for generating report data based on stored configuration
- Reuse generatePdfReport() method for PDF generation
- Reuse getAvailableFieldsForType() and getAvailableFiltersForType() for configuration display
- Extend to support CSV generation for attachments

**Notification Pattern (app/Notifications/DiscrepancyThresholdNotification.php)**
- Follow existing pattern for queued email notifications
- Use database + mail channels similar to existing notifications
- Implement toMail() and toArray() methods for dual delivery

**Job Queue Pattern (app/Jobs/GenerateAuditReportJob.php)**
- Follow existing pattern for queued background jobs with retry logic
- Use tries and backoff properties for failure handling
- Implement failed() method for permanent failure handling

**Console Scheduling Pattern (routes/console.php)**
- Follow existing patterns for scheduled tasks using Schedule facade
- Use timezone-aware scheduling similar to discrepancy detection
- Register dynamic schedules via custom scheduler command

**Role-Based Access (database/seeders/RolesAndPermissionsSeeder.php)**
- Add new permissions: scheduled-reports.view, scheduled-reports.create, scheduled-reports.update, scheduled-reports.delete
- Add new permissions: distribution-lists.view, distribution-lists.create, distribution-lists.update, distribution-lists.delete
- Assign permissions following existing role hierarchy

## Out of Scope
- Cron expression-based scheduling (only predefined frequencies)
- Download links for reports (attachments only)
- Real-time/on-demand report generation from scheduler (existing Custom Report Builder handles this)
- SMS or other notification channels beyond email
- Report storage/archive system (generated reports are attached and not stored)
- Ad-hoc email addresses on schedules (must use distribution lists)
- Bulk import of distribution list members
- Scheduling reports from non-Custom Report Builder sources
- Editing report configuration after schedule creation (must delete and recreate)
- Public/shared distribution lists across users
