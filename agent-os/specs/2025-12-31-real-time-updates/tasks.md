# Task Breakdown: Real-Time Updates

## Overview
Total Tasks: 60 sub-tasks across 7 task groups

This feature implements Laravel Reverb and Laravel Echo to provide real-time notifications when infrastructure data changes, enabling users to stay synchronized without manual page refreshes while protecting active editing sessions from disruptive auto-updates.

## Task List

### Backend Infrastructure

#### Task Group 1: Laravel Reverb Installation and Configuration
**Dependencies:** None

- [x] 1.0 Complete Laravel Reverb backend setup
  - [x] 1.1 Write 4-6 focused tests for broadcasting infrastructure
    - Test that events implementing ShouldBroadcast are queued correctly
    - Test that broadcast channel authorization returns correct boolean
    - Test that broadcastWith() returns properly serialized payloads
    - Test that broadcastAs() returns expected event names
  - [x] 1.2 Install Laravel Reverb package
    - Run `composer require laravel/reverb`
    - Run `php artisan reverb:install` to publish configuration
    - Verify `config/reverb.php` is created
  - [x] 1.3 Configure environment variables for Reverb
    - Add `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` to `.env`
    - Add `REVERB_HOST=127.0.0.1`, `REVERB_PORT=8080` to `.env`
    - Add `REVERB_SCHEME=http` for local development
    - Add `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT` for frontend
  - [x] 1.4 Update broadcasting configuration
    - Set `BROADCAST_CONNECTION=reverb` in `.env`
    - Verify `config/broadcasting.php` has Reverb driver configured
    - Ensure queue connection is properly set for broadcasting
  - [x] 1.5 Create Supervisor configuration for Reverb server
    - Create `reverb-worker.conf` for Supervisor
    - Document deployment steps for running `php artisan reverb:start`
  - [x] 1.6 Ensure Reverb infrastructure tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify Reverb configuration is valid
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- Reverb server starts successfully with `php artisan reverb:start`
- Broadcasting driver is correctly configured
- Environment variables are properly set

---

#### Task Group 2: Datacenter-Scoped Channel Authorization
**Dependencies:** Task Group 1

- [x] 2.0 Complete channel authorization layer
  - [x] 2.1 Write 4-6 focused tests for channel authorization
    - Test that authorized users can access datacenter channel
    - Test that unauthorized users are denied access to datacenter channel
    - Test that users with multiple datacenters can access each appropriately
    - Test that non-existent datacenter ID returns false
  - [x] 2.2 Add datacenter channel authorization in `routes/channels.php`
    - Create `datacenter.{datacenterId}` private channel
    - Authorization callback checks `$user->datacenters->contains($datacenterId)`
    - Follow existing pattern from `audit.{auditId}` channel
  - [x] 2.3 Verify authorization integrates with existing permission system
    - Ensure Spatie Laravel-Permission is respected
    - Test with different user roles and datacenter assignments
  - [x] 2.4 Ensure channel authorization tests pass
    - Run ONLY the 4-6 tests written in 2.1
    - Verify channel authorization works correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 2.1 pass
- Datacenter channel authorization respects user permissions
- Unauthorized users cannot subscribe to datacenter channels

---

### Backend Events

#### Task Group 3: Extend Existing Events for Broadcasting
**Dependencies:** Task Group 2

- [x] 3.0 Complete existing event broadcasting extensions
  - [x] 3.1 Write 6-8 focused tests for extended broadcast events
    - Test ConnectionChanged event broadcasts on correct datacenter channel
    - Test ConnectionChanged broadcastWith() returns minimal payload
    - Test ImplementationFileApproved event broadcasts correctly
    - Test FindingResolved event broadcasts correctly
    - Test that all events have correct broadcastAs() names
    - Test that deleted connection events include entity context
  - [x] 3.2 Extend `ConnectionChanged` event to implement `ShouldBroadcast`
    - Add `implements ShouldBroadcast` to class declaration
    - Add `broadcastOn()` returning `PrivateChannel('datacenter.'.$this->getDatacenterId())`
    - Add `broadcastWith()` with entity ID, action, user, timestamp
    - Add `broadcastAs()` returning `connection.changed`
    - Add helper method `getDatacenterId()` to retrieve datacenter from connection
  - [x] 3.3 Extend `ImplementationFileApproved` event to implement `ShouldBroadcast`
    - Add `implements ShouldBroadcast` to class declaration
    - Add `broadcastOn()`, `broadcastWith()`, `broadcastAs()` methods
    - Include file name and approver in broadcast payload
  - [x] 3.4 Extend `FindingResolved` event to implement `ShouldBroadcast`
    - Add `implements ShouldBroadcast` to class declaration
    - Add `broadcastOn()`, `broadcastWith()`, `broadcastAs()` methods
    - Include finding title and resolver in broadcast payload
  - [x] 3.5 Ensure extended event tests pass
    - Run ONLY the 6-8 tests written in 3.1
    - Verify events broadcast correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6-8 tests written in 3.1 pass
- All three existing events implement ShouldBroadcast
- Events broadcast to correct datacenter-scoped channels
- Payloads are minimal and properly serializable

---

#### Task Group 4: Create New Broadcast Events
**Dependencies:** Task Group 3

- [x] 4.0 Complete new broadcast event creation
  - [x] 4.1 Write 6-8 focused tests for new broadcast events
    - Test DeviceChanged event broadcasts on correct channel
    - Test RackChanged event broadcasts on correct channel
    - Test AuditStatusChanged event broadcasts on correct channel
    - Test FindingAssigned event broadcasts on correct channel
    - Test all events have consistent payload structure
    - Test events are dispatched from appropriate model observers/services
  - [x] 4.2 Create `DeviceChanged` event
    - Create `app/Events/DeviceChanged.php` implementing `ShouldBroadcast`
    - Follow pattern from `app/Events/AuditExecution/DeviceLocked.php`
    - Include device ID, rack ID, action (placed, moved, removed, status_changed)
    - Dispatch from device model observer or service class
  - [x] 4.3 Create `RackChanged` event
    - Create `app/Events/RackChanged.php` implementing `ShouldBroadcast`
    - Include rack ID, room ID, action (created, updated, deleted)
    - Dispatch from rack model observer or service class
  - [x] 4.4 Create `AuditStatusChanged` event
    - Create `app/Events/AuditStatusChanged.php` implementing `ShouldBroadcast`
    - Include audit ID, old status, new status, user who changed
    - Dispatch from audit status transition logic
  - [x] 4.5 Create `FindingAssigned` event
    - Create `app/Events/FindingAssigned.php` implementing `ShouldBroadcast`
    - Include finding ID, assignee info, assigner info
    - Dispatch from finding assignment logic
  - [x] 4.6 Set up event dispatching in model observers or services
    - Add event dispatching to relevant model observers
    - Ensure events fire on create, update, delete operations as appropriate
  - [x] 4.7 Ensure new event tests pass
    - Run ONLY the 6-8 tests written in 4.1
    - Verify all new events work correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6-8 tests written in 4.1 pass
- All four new events are created and implement ShouldBroadcast
- Events are dispatched from appropriate locations in codebase
- Payload structure is consistent across all broadcast events

---

### Frontend Infrastructure

#### Task Group 5: Laravel Echo Frontend Setup
**Dependencies:** Task Group 1

- [x] 5.0 Complete Laravel Echo frontend setup
  - [x] 5.1 Write 4-6 focused tests for Echo integration
    - Test Echo connects successfully with Reverb configuration
    - Test Echo subscribes to private channels correctly
    - Test Echo handles connection loss and reconnection
    - Test channel cleanup on component unmount
  - [x] 5.2 Install Laravel Echo and Pusher JS packages
    - Run `npm install laravel-echo pusher-js`
    - Verify packages are added to `package.json`
  - [x] 5.3 Create Echo bootstrap file
    - Create `resources/js/echo.ts` bootstrap file
    - Initialize Echo with Reverb configuration from Vite env variables
    - Configure for private channels with CSRF authentication
    - Export Echo instance for use in components
  - [x] 5.4 Import Echo bootstrap in app.ts
    - Import Echo initialization in `resources/js/app.ts`
    - Ensure Echo connects on page load
    - Verify TypeScript types are properly configured
  - [x] 5.5 Create `useRealtimeUpdates` composable
    - Create `resources/js/composables/useRealtimeUpdates.ts`
    - Accept datacenter ID parameter to subscribe to correct channel
    - Provide `onDataChange(entityType, callback)` method
    - Export reactive `hasUpdates` ref and `pendingUpdates` array
    - Handle channel subscription and cleanup on component unmount
    - Follow Echo pattern from existing audit execution code
  - [x] 5.6 Ensure Echo integration tests pass
    - Run ONLY the 4-6 tests written in 5.1
    - Verify Echo connects and subscribes correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 5.1 pass
- Echo connects to Reverb server successfully
- `useRealtimeUpdates` composable subscribes to datacenter channels
- Proper cleanup occurs on component unmount

---

### Frontend Components

#### Task Group 6: Real-Time Toast and UI Components
**Dependencies:** Task Group 5

- [x] 6.0 Complete real-time UI components
  - [x] 6.1 Write 4-6 focused tests for UI components
    - Test RealtimeToast renders correctly with event data
    - Test toast auto-dismisses after 10 seconds
    - Test refresh button triggers Inertia page reload
    - Test dismiss button closes toast
    - Test multiple toasts stack correctly
  - [x] 6.2 Create `RealtimeToast.vue` component
    - Create `resources/js/Components/notifications/RealtimeToast.vue`
    - Display entity type, action, and user who made change
    - Include "Refresh" button triggering `router.reload()`
    - Include "Dismiss" button to close toast
    - Auto-dismiss after 10 seconds if not interacted with
    - Use existing styling patterns from `NotificationBell.vue`
  - [x] 6.3 Create toast container for stacking multiple toasts
    - Create `resources/js/Components/notifications/RealtimeToastContainer.vue`
    - Stack multiple toasts when several changes occur quickly
    - Position toasts in bottom-right corner
    - Animate toast entry and exit
  - [x] 6.4 Create edit form conflict warning toast
    - Create variant of toast for edit form conflict scenario
    - Warning text: "This [entity] was modified by [user]. Save your changes or refresh."
    - More prominent styling to indicate potential data conflict
    - Do NOT auto-dismiss conflict warnings
  - [x] 6.5 Enhance `NotificationBell.vue` for real-time updates
    - Subscribe to datacenter channel in NotificationBell
    - Add separate counter for real-time update indicators
    - Distinguish between database notifications and real-time indicators
    - Clear real-time indicator when user refreshes or navigates
  - [x] 6.6 Ensure UI component tests pass
    - Run ONLY the 4-6 tests written in 6.1
    - Verify toast components work correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 6.1 pass
- RealtimeToast displays event information correctly
- Toasts auto-dismiss and can be manually dismissed
- NotificationBell shows real-time update indicators
- Edit form conflicts show prominent warnings

---

### Page Integration

#### Task Group 7: Integrate Real-Time Updates into Key Pages
**Dependencies:** Task Groups 4, 6

- [x] 7.0 Complete page integration
  - [x] 7.1 Write 6-8 focused integration tests
    - Test Connections page shows toast on connection change events
    - Test Devices page shows toast on device change events
    - Test Racks page shows toast on rack change events
    - Test Implementation Files page shows toast on approval events
    - Test Findings page shows toast on status/assignment events
    - Test edit forms show conflict warning when entity modified
    - Test refresh button reloads page data correctly
  - [x] 7.2 Integrate real-time listener into Connections pages
    - Add `useRealtimeUpdates` to Connections/Diagram.vue
    - Add `useRealtimeUpdates` to Connections/Show.vue
    - Listen for `connection.changed` events
    - Show toast on connection create/update/delete
  - [x] 7.3 Integrate real-time listener into Devices pages
    - Add `useRealtimeUpdates` to Devices/Index.vue
    - Add `useRealtimeUpdates` to Devices/Edit.vue
    - Listen for `device.changed` events
    - Show toast on device placement/status changes
  - [x] 7.4 Integrate real-time listener into Racks pages
    - Add `useRealtimeUpdates` to Racks/Index.vue
    - Add `useRealtimeUpdates` to Racks/Edit.vue
    - Listen for `rack.changed` events
    - Show toast on rack modifications
  - [x] 7.5 Integrate real-time listener into Implementation Files page
    - Add `useRealtimeUpdates` to ImplementationFiles/Comparison.vue
    - Listen for `implementation_file.approved` events
    - Show toast on approval status changes
  - [x] 7.6 Integrate real-time listener into Findings pages
    - Add `useRealtimeUpdates` to Findings/Index.vue
    - Add `useRealtimeUpdates` to Findings/Show.vue
    - Listen for `finding.resolved` and `finding.assigned` events
    - Show toast on status and assignment changes
  - [x] 7.7 Implement edit form protection
    - Track entity ID being edited in edit forms
    - Show conflict warning toast if entity modified by another user
    - Do NOT auto-update form fields or reset form state
    - Allow user to continue editing (optimistic concurrency)
  - [x] 7.8 Ensure page integration tests pass
    - Run ONLY the 6-8 tests written in 7.1
    - Verify all page integrations work correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6-8 tests written in 7.1 pass
- All key pages show toast notifications on relevant events
- Edit forms display conflict warnings without disrupting user
- Refresh buttons reload page data correctly
- No auto-reload of content occurs

---

### Testing

#### Task Group 8: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-7

- [x] 8.0 Review existing tests and fill critical gaps only
  - [x] 8.1 Review tests from Task Groups 1-7
    - Review the 4-6 tests written by Task Group 1 (Reverb infrastructure)
    - Review the 4-6 tests written by Task Group 2 (channel authorization)
    - Review the 6-8 tests written by Task Group 3 (extended events)
    - Review the 6-8 tests written by Task Group 4 (new events)
    - Review the 4-6 tests written by Task Group 5 (Echo setup)
    - Review the 4-6 tests written by Task Group 6 (UI components)
    - Review the 6-8 tests written by Task Group 7 (page integration)
    - Total existing tests: approximately 36-48 tests
  - [x] 8.2 Analyze test coverage gaps for THIS feature only
    - Identify critical user workflows that lack test coverage
    - Focus ONLY on gaps related to real-time updates feature
    - Do NOT assess entire application test coverage
    - Prioritize end-to-end workflows over unit test gaps
  - [x] 8.3 Write up to 10 additional strategic tests maximum
    - Add maximum of 10 new tests to fill identified critical gaps
    - Focus on integration points between backend events and frontend
    - Test WebSocket connection resilience (reconnection)
    - Test multiple simultaneous users receiving broadcasts
    - Test event ordering and race conditions
    - Do NOT write comprehensive coverage for all scenarios
  - [x] 8.4 Run feature-specific tests only
    - Run ONLY tests related to real-time updates feature
    - Expected total: approximately 46-58 tests maximum
    - Do NOT run the entire application test suite
    - Verify all critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 46-58 tests total)
- Critical user workflows for real-time updates are covered
- No more than 10 additional tests added when filling gaps
- Testing focused exclusively on real-time updates feature

---

## Execution Order

Recommended implementation sequence:

1. **Task Group 1: Laravel Reverb Installation** (backend infrastructure)
2. **Task Group 2: Channel Authorization** (depends on 1)
3. **Task Group 5: Laravel Echo Frontend Setup** (can run parallel with 2, depends on 1)
4. **Task Group 3: Extend Existing Events** (depends on 2)
5. **Task Group 4: Create New Broadcast Events** (depends on 3)
6. **Task Group 6: Real-Time Toast UI Components** (depends on 5)
7. **Task Group 7: Page Integration** (depends on 4 and 6)
8. **Task Group 8: Test Review and Gap Analysis** (depends on all previous groups)

```
Task Group 1 (Reverb Install)
       |
       +--------+--------+
       |                 |
Task Group 2         Task Group 5
(Authorization)      (Echo Frontend)
       |                 |
Task Group 3         Task Group 6
(Extend Events)      (UI Components)
       |                 |
Task Group 4             |
(New Events)             |
       |                 |
       +--------+--------+
                |
         Task Group 7
       (Page Integration)
                |
         Task Group 8
        (Test Review)
```

## Key Files to Create/Modify

### New Files
- `resources/js/echo.ts` - Echo bootstrap configuration
- `resources/js/composables/useRealtimeUpdates.ts` - Real-time composable
- `resources/js/Components/notifications/RealtimeToast.vue` - Toast component
- `resources/js/Components/notifications/RealtimeToastContainer.vue` - Toast container
- `resources/js/types/realtime.ts` - TypeScript types for real-time updates
- `app/Events/DeviceChanged.php` - Device change broadcast event
- `app/Events/RackChanged.php` - Rack change broadcast event
- `app/Events/AuditStatusChanged.php` - Audit status broadcast event
- `app/Events/FindingAssigned.php` - Finding assignment broadcast event

### Files to Modify
- `config/broadcasting.php` - Add Reverb driver configuration
- `.env` / `.env.example` - Add Reverb environment variables
- `routes/channels.php` - Add datacenter channel authorization
- `app/Events/ConnectionChanged.php` - Add ShouldBroadcast interface
- `app/Events/ImplementationFileApproved.php` - Add ShouldBroadcast interface
- `app/Events/FindingResolved.php` - Add ShouldBroadcast interface
- `resources/js/app.ts` - Import Echo bootstrap
- `resources/js/Components/notifications/NotificationBell.vue` - Add real-time indicators
- `resources/js/Pages/Connections/Diagram.vue` - Add real-time listener
- `resources/js/Pages/Connections/Show.vue` - Add real-time listener
- `resources/js/Pages/Devices/Index.vue` - Add real-time listener
- `resources/js/Pages/Devices/Edit.vue` - Add edit form protection
- `resources/js/Pages/Racks/Index.vue` - Add real-time listener
- `resources/js/Pages/Racks/Edit.vue` - Add edit form protection
- `resources/js/Pages/ImplementationFiles/Comparison.vue` - Add real-time listener
- `resources/js/Pages/Findings/Index.vue` - Add real-time listener
- `resources/js/Pages/Findings/Show.vue` - Add real-time listener and edit form protection

## Existing Patterns to Follow

### Broadcast Event Pattern
Reference: `/Users/helderdene/rackaudit/app/Events/AuditExecution/DeviceLocked.php`
- Implements `ShouldBroadcast` interface
- Uses `broadcastOn()`, `broadcastWith()`, `broadcastAs()` methods
- Returns `PrivateChannel` with entity-scoped channel name

### Channel Authorization Pattern
Reference: `/Users/helderdene/rackaudit/routes/channels.php`
- `Broadcast::channel()` with user and ID parameters
- Authorization callback returns boolean
- Checks user relationship to authorize access

### Notification UI Pattern
Reference: `/Users/helderdene/rackaudit/resources/js/Components/notifications/NotificationBell.vue`
- Badge counter implementation with reactive refs
- Dropdown menu structure for notifications
- Icon selection based on notification type
- Loading and empty states
