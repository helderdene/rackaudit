# Specification: Notification System

## Goal
Expand and polish the in-app and email notification system to support audit assignments, finding updates, and approval requests with a dedicated notification preferences page allowing users to control per-category email delivery.

## User Stories
- As an Auditor, I want to be notified when I am assigned to an audit so that I can start preparing for the audit execution
- As a User, I want to manage my notification preferences so that I only receive emails for categories I care about while still seeing all in-app notifications

## Specific Requirements

**Audit Assignment Notifications**
- Create `AuditAssignedNotification` class triggered when user is added to `audit.assignees()` relationship
- Create `AuditReassignedNotification` class triggered when audit assignees are changed (removed from assignment)
- Both notifications should include audit name, type, due date, datacenter, and link to audit
- Follow existing notification pattern: implement `ShouldQueue`, use `database` and conditional `mail` channels
- Respect user's per-category email preferences when determining mail channel inclusion

**Notification Preferences Page**
- Create new `Notifications.vue` page in `resources/js/Pages/Settings/`
- Use `SettingsLayout` component as the parent layout (same as Profile.vue, Password.vue)
- Add "Notifications" item to the `sidebarNavItems` array in `resources/js/layouts/settings/Layout.vue`
- Group notification toggles by category: Audit Assignments, Finding Updates, Approval Requests, Discrepancies, Scheduled Reports
- Each category has independent in-app (always on, read-only) and email (toggleable) columns
- Display clear visual distinction that in-app notifications cannot be disabled

**Backend Notification Preferences**
- Create migration to add `notification_preferences` JSON column to users table (replaces singular `discrepancy_notifications` field)
- Store preferences as JSON object with category keys and boolean email values
- Create `NotificationPreferencesController` with `show()` and `update()` methods
- Create `UpdateNotificationPreferencesRequest` Form Request with validation rules
- Add route `GET/PATCH settings/notifications` to `routes/settings.php`

**Preference-Aware Notification Delivery**
- Modify existing notification classes to check user preferences before adding `mail` channel
- Create `HasNotificationPreferences` trait or helper method on User model to check category preferences
- Default preferences: email enabled for all categories (opt-out model, not opt-in)
- Migration should convert existing `discrepancy_notifications` data to new JSON format

**NotificationBell UI Improvements**
- Add new notification type icons for audit assignment notifications (use `ClipboardCheck` from lucide-vue-next)
- Extend `Notification` interface to include `audit_assigned` and `audit_reassigned` types
- Update `getNotificationIcon()` function with new notification type mappings
- Update icon color styling for audit notification types (use blue color scheme)
- Update `NotificationController.php` to handle new notification types in `getNotificationType()` and `buildMessage()` methods

**Real-Time Notification Delivery**
- Create `NotificationCreated` broadcast event that fires when new notification is stored
- Subscribe to user-specific private channel `user.{userId}` in NotificationBell component
- Update unread badge count immediately when broadcast received (increment counter)
- Add new notification to the top of the list if dropdown is open

**Email Notification Enhancements**
- All email notifications sent individually in real-time (no digest functionality)
- Email only sent when: (1) real mail driver configured, AND (2) user has email enabled for that category
- Update all existing notification classes to respect the new preference system

## Visual Design
No visual mockups provided. Follow existing Settings page patterns from `Profile.vue` and `Password.vue` for the Notifications preferences page.

## Existing Code to Leverage

**Settings Pages Pattern (`/resources/js/Pages/Settings/`)**
- Profile.vue demonstrates form layout with `<Form>` component, `recentlySuccessful` feedback, and `SettingsLayout` usage
- Use `HeadingSmall` component for section headers
- Follow consistent spacing with `space-y-6` for form sections

**Existing Notification Classes (`/app/Notifications/`)**
- `FindingAssignedNotification.php` is the primary template - uses constructor property promotion, `ShouldQueue`, conditional mail channel
- `via()` method pattern checks `config('mail.default')` to conditionally include mail channel
- `toArray()` method provides structured data for database storage with explicit `type` field

**NotificationController (`/app/Http/Controllers/NotificationController.php`)**
- `getNotificationType()` maps notification class names to frontend type strings
- `buildMessage()` provides fallback message generation
- `buildLink()` generates navigation URLs based on notification type and data

**NotificationBell Component (`/resources/js/components/notifications/NotificationBell.vue`)**
- Uses axios for API calls, not Inertia forms
- `useRealtimeUpdates` composable pattern for Echo subscription (datacenter-scoped, needs user-scoped equivalent)
- Polling fallback with 30-second interval for unread count

## Out of Scope
- SMS notifications
- Mobile push notifications (native apps)
- Slack, Teams, or webhook integrations
- Admin customization of notification email templates
- Email digest or summary functionality (daily/weekly rollups)
- Dedicated full-page notification history view with search/filtering
- Notification scheduling or delayed delivery
- Notification archiving or deletion
- Mark individual notifications as unread
- Converting existing `discrepancy_notifications` field (keep for backward compatibility until migration)
