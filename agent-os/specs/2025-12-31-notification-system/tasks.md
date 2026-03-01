# Task Breakdown: Notification System

## Overview
Total Tasks: 6 Task Groups, 36 Sub-tasks

This feature expands the in-app and email notification system to support audit assignments, finding updates, and approval requests with a dedicated notification preferences page allowing users to control per-category email delivery.

## Task List

### Database Layer

#### Task Group 1: Notification Preferences Data Model
**Dependencies:** None

- [x] 1.0 Complete notification preferences database layer
  - [x] 1.1 Write 4-6 focused tests for notification preferences functionality
    - Test User model `notification_preferences` attribute casting
    - Test `hasEmailEnabledFor()` method returns correct boolean for each category
    - Test default preferences are applied when column is null
    - Test `discrepancy_notifications` backward compatibility
  - [x] 1.2 Create migration to add `notification_preferences` JSON column to users table
    - Add `notification_preferences` JSON column (nullable)
    - Keep existing `discrepancy_notifications` field for backward compatibility
    - No data migration needed (out of scope per spec)
  - [x] 1.3 Update User model with notification preferences functionality
    - Add `notification_preferences` to `$casts` array as `array`
    - Create `hasEmailEnabledFor(string $category): bool` method
    - Define default preferences (all email categories enabled - opt-out model)
    - Categories: `audit_assignments`, `finding_updates`, `approval_requests`, `discrepancies`, `scheduled_reports`
  - [x] 1.4 Create `HasNotificationPreferences` trait (optional, or keep on User model)
    - Extract preference checking logic if User model becomes too large
    - Include constants for category names
  - [x] 1.5 Ensure database layer tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify migration runs successfully
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- Migration adds `notification_preferences` JSON column
- User model correctly checks preferences for any category
- Default behavior is email enabled for all categories

---

### Backend API Layer

#### Task Group 2: Notification Preferences API
**Dependencies:** Task Group 1

- [x] 2.0 Complete notification preferences API endpoints
  - [x] 2.1 Write 4-6 focused tests for preferences API
    - Test `GET settings/notifications` returns current preferences with Inertia
    - Test `PATCH settings/notifications` updates preferences successfully
    - Test validation rejects invalid category keys
    - Test unauthenticated users cannot access preferences
  - [x] 2.2 Create `NotificationPreferencesController` with `show()` and `update()` methods
    - `show()`: Return Inertia page with current user preferences
    - `update()`: Save preferences and redirect back with success message
    - Follow existing settings controller patterns
  - [x] 2.3 Create `UpdateNotificationPreferencesRequest` Form Request
    - Validate each category key exists and value is boolean
    - Categories: `audit_assignments`, `finding_updates`, `approval_requests`, `discrepancies`, `scheduled_reports`
    - Use array-based validation rules (follow existing convention)
  - [x] 2.4 Add routes to `routes/settings.php`
    - `GET settings/notifications` -> `NotificationPreferencesController@show`
    - `PATCH settings/notifications` -> `NotificationPreferencesController@update`
    - Apply appropriate middleware (auth, verified)
  - [x] 2.5 Ensure API tests pass
    - Run ONLY the 4-6 tests written in 2.1
    - Verify routes are registered correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 2.1 pass
- Routes respond correctly to GET and PATCH requests
- Validation properly rejects invalid input
- Preferences are persisted to the database

---

#### Task Group 3: Audit Assignment Notifications
**Dependencies:** Task Group 1 (COMPLETED)

- [x] 3.0 Complete audit assignment notification classes
  - [x] 3.1 Write 4-6 focused tests for audit notifications
    - Test `AuditAssignedNotification` is queued and sent to correct user
    - Test `AuditAssignedNotification` respects user email preferences
    - Test `AuditReassignedNotification` is triggered on assignment removal
    - Test notification data includes audit name, type, due date, datacenter, link
  - [x] 3.2 Create `AuditAssignedNotification` class
    - Implement `ShouldQueue` interface
    - Use constructor property promotion with `Audit $audit`
    - Implement `via()` method: always `database`, conditional `mail` based on user preference
    - Implement `toMail()` method with audit details
    - Implement `toArray()` method with structured data including explicit `type` field
    - Follow `FindingAssignedNotification` pattern
  - [x] 3.3 Create `AuditReassignedNotification` class
    - Similar structure to `AuditAssignedNotification`
    - Triggered when user is removed from audit assignees
    - Include different messaging for reassignment context
  - [x] 3.4 Add notification triggers to audit assignee sync logic
    - Dispatch `AuditAssignedNotification` when users are added to `audit.assignees()`
    - Dispatch `AuditReassignedNotification` when users are removed from assignees
    - Handle both create and update scenarios
  - [x] 3.5 Update `NotificationController.php` for new notification types
    - Add `audit_assigned` and `audit_reassigned` to `getNotificationType()` mapping
    - Update `buildMessage()` for new notification types
    - Update `buildLink()` to generate audit page URLs
  - [x] 3.6 Ensure notification tests pass
    - Run ONLY the 4-6 tests written in 3.1
    - Verify notifications are dispatched correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 3.1 pass
- Audit assignment notifications are sent when assignees change
- Email channel respects user preferences
- Notification data is properly structured for frontend consumption

---

### Frontend Layer

#### Task Group 4: Notification Preferences UI
**Dependencies:** Task Group 2

- [x] 4.0 Complete notification preferences page
  - [x] 4.1 Write 3-5 focused tests for preferences UI
    - Test preferences page renders with correct form fields
    - Test form submission updates preferences successfully
    - Test in-app column shows as read-only/always enabled
  - [x] 4.2 Create `Notifications.vue` page in `resources/js/Pages/Settings/`
    - Use `SettingsLayout` component as parent (same as Profile.vue, Password.vue)
    - Use `HeadingSmall` component for section headers
    - Follow consistent spacing with `space-y-6` for form sections
    - Use Inertia `<Form>` component with `recentlySuccessful` feedback pattern
  - [x] 4.3 Implement notification category toggles
    - Group toggles by category:
      - Audit Assignments
      - Finding Updates
      - Approval Requests
      - Discrepancies
      - Scheduled Reports
    - Two columns: In-App (always on, read-only) and Email (toggleable)
    - Clear visual distinction that in-app cannot be disabled (use disabled checkbox or info text)
  - [x] 4.4 Add form submission handling
    - Use Wayfinder-generated route for PATCH request
    - Display success message using `recentlySuccessful` pattern
    - Handle validation errors
  - [x] 4.5 Add "Notifications" to settings sidebar navigation
    - Update `sidebarNavItems` array in `resources/js/layouts/settings/Layout.vue`
    - Use appropriate icon from lucide-vue-next
    - Place in logical position within existing nav items
  - [x] 4.6 Run Wayfinder generation
    - Execute `php artisan wayfinder:generate` to create TypeScript route functions
  - [x] 4.7 Ensure UI tests pass
    - Run ONLY the 3-5 tests written in 4.1
    - Verify page renders correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 3-5 tests written in 4.1 pass
- Notifications page accessible from Settings sidebar
- Category toggles display correctly with in-app always enabled
- Form saves preferences successfully with user feedback

---

#### Task Group 5: NotificationBell Enhancements
**Dependencies:** Task Groups 3, 4

- [x] 5.0 Complete NotificationBell UI improvements and real-time delivery
  - [x] 5.1 Write 4-6 focused tests for NotificationBell enhancements
    - Test new notification type icons render correctly for audit notifications
    - Test real-time broadcast event is dispatched on notification creation
    - Test NotificationBell subscribes to user-specific channel
    - Test unread badge increments on broadcast received
  - [x] 5.2 Create `NotificationCreated` broadcast event
    - Fire when new notification is stored in database
    - Broadcast on private channel `user.{userId}`
    - Include notification data in payload
    - Implement `ShouldBroadcastNow` for immediate delivery
  - [x] 5.3 Update notification classes to dispatch broadcast event
    - Add event dispatch in `toArray()` or use Laravel's `BroadcastNotificationCreated`
    - Ensure event fires after database storage
  - [x] 5.4 Extend `Notification` TypeScript interface
    - Add `audit_assigned` and `audit_reassigned` to notification types
    - Update type definitions in relevant files
  - [x] 5.5 Update `getNotificationIcon()` function in NotificationBell.vue
    - Add `ClipboardCheck` icon from lucide-vue-next for audit notifications
    - Add blue color scheme styling for audit notification types
    - Map `audit_assigned` and `audit_reassigned` to appropriate icons
  - [x] 5.6 Implement real-time subscription in NotificationBell.vue
    - Subscribe to user-specific private channel `user.{userId}`
    - Create composable or use existing `useRealtimeUpdates` pattern
    - On broadcast received: increment unread badge count
    - If dropdown open: prepend new notification to list
  - [x] 5.7 Ensure NotificationBell tests pass
    - Run ONLY the 4-6 tests written in 5.1
    - Verify icons render correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 5.1 pass
- Audit notification icons display with correct styling
- Real-time updates increment badge immediately
- New notifications appear at top of list when dropdown is open

---

### Testing & Integration

#### Task Group 6: Test Review & Gap Analysis
**Dependencies:** Task Groups 1-5

- [x] 6.0 Review existing tests and fill critical gaps only
  - [x] 6.1 Review tests from Task Groups 1-5
    - Review the 4-6 tests written for database layer (Task 1.1)
    - Review the 4-6 tests written for preferences API (Task 2.1)
    - Review the 4-6 tests written for audit notifications (Task 3.1)
    - Review the 3-5 tests written for preferences UI (Task 4.1)
    - Review the 4-6 tests written for NotificationBell (Task 5.1)
    - Total existing tests: approximately 19-29 tests
  - [x] 6.2 Analyze test coverage gaps for notification system only
    - Identify critical user workflows that lack test coverage
    - Focus ONLY on gaps related to this spec's feature requirements
    - Prioritize end-to-end workflows:
      - User changes notification preference -> notification respects new setting
      - Audit assignee added -> notification sent -> bell updates in real-time
    - Do NOT assess entire application test coverage
  - [x] 6.3 Write up to 10 additional strategic tests maximum
    - Add maximum of 10 new tests to fill identified critical gaps
    - Focus on integration points and end-to-end workflows
    - Consider browser tests for real-time notification flow (if appropriate)
    - Do NOT write comprehensive coverage for all scenarios
    - Skip edge cases and performance tests unless business-critical
  - [x] 6.4 Update existing notification classes to respect new preference system
    - Modify `via()` method in each existing notification class:
      - `FindingAssignedNotification`
      - `FindingReassignedNotification`
      - `FindingStatusChangedNotification`
      - `FindingDueDateApproachingNotification`
      - `FindingOverdueNotification`
      - `ImplementationFileAwaitingApprovalNotification`
      - `ImplementationFileApprovedNotification`
      - `NewDiscrepancyNotification`
      - `DiscrepancyThresholdNotification`
      - `ScheduledReportDisabledNotification`
      - `ScheduledReportFailedNotification`
    - Check user's `hasEmailEnabledFor()` for appropriate category
    - Map each notification to correct category
  - [x] 6.5 Run feature-specific tests only
    - Run ONLY tests related to this spec's notification system feature
    - Expected total: approximately 29-39 tests maximum
    - Do NOT run the entire application test suite
    - Verify critical workflows pass
  - [x] 6.6 Run Laravel Pint for code formatting
    - Execute `vendor/bin/pint --dirty` to format changed files

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 29-39 tests total)
- Critical user workflows for notification system are covered
- No more than 10 additional tests added when filling gaps
- All existing notification classes respect new preference system
- Code passes Pint formatting checks

---

## Execution Order

Recommended implementation sequence:

1. **Database Layer** (Task Group 1)
   - Foundation for all other work
   - No dependencies

2. **Notification Preferences API** (Task Group 2)
   - Requires database layer
   - Backend endpoints for frontend to consume

3. **Audit Assignment Notifications** (Task Group 3)
   - Requires database layer for preference checking
   - Can be done in parallel with Task Group 2

4. **Notification Preferences UI** (Task Group 4)
   - Requires API endpoints from Task Group 2
   - Frontend page for user interaction

5. **NotificationBell Enhancements** (Task Group 5)
   - Requires notification classes from Task Group 3
   - Requires preference system understanding from Task Groups 1-4

6. **Test Review & Gap Analysis** (Task Group 6)
   - Must come last as it reviews all prior work
   - Updates existing notification classes
   - Final validation of complete feature

## Parallel Execution Opportunities

The following task groups can be worked on in parallel:
- Task Groups 2 and 3 (both only depend on Task Group 1)

## Key Files to Create

| File | Task Group |
|------|------------|
| `database/migrations/xxxx_add_notification_preferences_to_users_table.php` | 1 |
| `app/Http/Controllers/Settings/NotificationPreferencesController.php` | 2 |
| `app/Http/Requests/Settings/UpdateNotificationPreferencesRequest.php` | 2 |
| `app/Notifications/AuditAssignedNotification.php` | 3 |
| `app/Notifications/AuditReassignedNotification.php` | 3 |
| `app/Events/NotificationCreated.php` | 5 |
| `resources/js/Pages/Settings/Notifications.vue` | 4 |

## Key Files to Modify

| File | Task Group |
|------|------------|
| `app/Models/User.php` | 1 |
| `routes/settings.php` | 2 |
| `app/Http/Controllers/NotificationController.php` | 3 |
| `resources/js/layouts/settings/Layout.vue` | 4 |
| `resources/js/components/notifications/NotificationBell.vue` | 5 |
| All existing notification classes in `app/Notifications/` | 6 |
