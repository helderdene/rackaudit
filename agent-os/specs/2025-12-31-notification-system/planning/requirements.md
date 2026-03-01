# Spec Requirements: Notification System

## Initial Description

In-app and email notifications for audit assignments, finding updates, and approval requests. This is item #45 in the product roadmap (Phase 6: Polish & Optimization).

## Requirements Discussion

### First Round Questions

**Q1:** Given the existing notification infrastructure, I assume you want to expand/polish the system rather than build from scratch. Is the goal to (a) add new notification types for audit assignments specifically, (b) create a dedicated notification preferences/settings page, (c) improve the existing NotificationBell UI, or (d) all of the above?
**Answer:** (d) All of the above - add new notification types for audit assignments, create notification preferences page, AND improve NotificationBell UI

**Q2:** For audit assignments, I assume you want to notify users when they are assigned as an auditor/executor to an audit (using the `Audit.assignees()` relationship). Should this notification go to all assignees immediately when the audit is created, or when assignees are added/changed?
**Answer:** Correct - notify when assignees are added/changed

**Q3:** The User model has a `discrepancy_notifications` field. I assume you want a full "Notification Preferences" settings page where users can toggle on/off different notification categories (audit assignments, finding updates, approval requests, discrepancies, scheduled reports). Is that correct, or should it remain minimal?
**Answer:** Correct - yes, create a full preferences page with category toggles

**Q4:** Currently, emails only send when a real mail driver is configured. I assume you want to keep this behavior (in-app always, email optional based on config). Should users also have the ability to opt-out of email notifications per category while still receiving in-app notifications?
**Answer:** Correct - users can opt-out of email per category while keeping in-app

**Q5:** The current NotificationBell dropdown shows recent notifications. Do you want a dedicated full-page "All Notifications" view with search, filtering by type, and bulk actions, or is the dropdown sufficient?
**Answer:** The dropdown is sufficient - no dedicated full page needed

**Q6:** The app uses Laravel Echo with Pusher/Soketi for real-time updates. I assume new notifications should be pushed to the browser in real-time (updating the bell badge immediately). Is that correct?
**Answer:** Correct - push to browser in real-time

**Q7:** Should the system support daily or weekly email digests summarizing unread notifications, or should all notifications be sent individually in real-time only?
**Answer:** No digests - all notifications sent individually in real-time only

**Q8:** What should we explicitly exclude from this feature? For example: SMS notifications, push notifications (mobile), Slack/Teams integrations, or notification templates customization by admins.
**Answer:** Exclude SMS, push notifications (mobile), Slack/Teams integrations, and notification template customization by admins

### Existing Code to Reference

**Similar Features Identified:**
- Feature: Settings Pages - Path: `/Users/helderdene/rackaudit/resources/js/Pages/Settings/`
  - `Profile.vue` - user profile form with save confirmation
  - `Password.vue` - password change form
  - `Appearance.vue` - appearance settings
  - `TwoFactor.vue` - two-factor authentication settings
- Feature: Settings Layout - Path: `/Users/helderdene/rackaudit/resources/js/layouts/settings/Layout.vue`
- Feature: NotificationBell Component - Path: `/Users/helderdene/rackaudit/resources/js/components/notifications/NotificationBell.vue`
- Feature: NotificationController - Path: `/Users/helderdene/rackaudit/app/Http/Controllers/NotificationController.php`
- Feature: Existing Notifications - Path: `/Users/helderdene/rackaudit/app/Notifications/`
  - `FindingAssignedNotification.php`
  - `FindingReassignedNotification.php`
  - `FindingStatusChangedNotification.php`
  - `FindingDueDateApproachingNotification.php`
  - `FindingOverdueNotification.php`
  - `ImplementationFileAwaitingApprovalNotification.php`
  - `ImplementationFileApprovedNotification.php`
  - `NewDiscrepancyNotification.php`
  - `DiscrepancyThresholdNotification.php`
  - `ScheduledReportDisabledNotification.php`
  - `ScheduledReportFailedNotification.php`

### Follow-up Questions

No follow-up questions were needed.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A

## Requirements Summary

### Functional Requirements

**New Notification Types:**
- Audit assignment notification when a user is added as an assignee to an audit
- Audit reassignment notification when audit assignees are changed

**Notification Preferences Page:**
- Full settings page for notification preferences (similar to existing Settings pages)
- Category-based toggles for different notification types:
  - Audit assignments
  - Finding updates (assigned, reassigned, status changed, due date approaching, overdue)
  - Approval requests (implementation files awaiting approval, approved)
  - Discrepancies (new discrepancy, threshold alerts)
  - Scheduled reports (failed, disabled)
- Per-category email opt-out while maintaining in-app notifications
- In-app notifications always enabled (cannot be disabled)

**NotificationBell UI Improvements:**
- Enhance existing dropdown component
- Real-time badge updates via Laravel Echo
- Support for new audit assignment notification types
- Improved notification type icons and categorization

**Email Notifications:**
- Individual real-time email delivery (no digests)
- Respect user's per-category email preferences
- Only send when real mail driver is configured (existing behavior)

**Real-Time Delivery:**
- Push notifications to browser immediately via Laravel Echo/Pusher
- Update badge count in real-time

### Reusability Opportunities

- Use existing `SettingsLayout` component for notification preferences page
- Follow patterns from existing Settings pages (Profile.vue, Password.vue)
- Extend existing notification classes pattern for new audit assignment notifications
- Reuse NotificationController patterns for preference management
- User model already has `discrepancy_notifications` field - extend this pattern for other categories

### Scope Boundaries

**In Scope:**
- New `AuditAssignedNotification` class
- New `AuditReassignedNotification` class (if assignees change)
- Notification preferences Vue page in Settings
- User notification preferences database migration (extend beyond `discrepancy_notifications`)
- NotificationBell.vue improvements for new notification types
- Backend API endpoints for saving notification preferences
- Real-time notification delivery via Laravel Echo
- Email notifications with per-category opt-out

**Out of Scope:**
- SMS notifications
- Mobile push notifications
- Slack/Teams/webhook integrations
- Admin customization of notification templates
- Email digest/summary functionality
- Dedicated full-page notification history view
- Notification scheduling/delayed delivery

### Technical Considerations

- Existing tech stack: Laravel 12, Vue 3, Inertia.js v2, Tailwind CSS 4
- Real-time: Laravel Echo with Pusher/Soketi already configured
- Database: MySQL 8.x with existing `notifications` table (Laravel's default)
- User model uses Spatie Laravel-Permission for roles (Administrator, IT Manager, Operator, Auditor, Viewer)
- Existing `discrepancy_notifications` field on users table needs to be expanded to support all categories
- Queue system available for async notification delivery
- Wayfinder for TypeScript route generation
- Follow existing notification class patterns (implement `ShouldQueue`, use `database` and conditional `mail` channels)
