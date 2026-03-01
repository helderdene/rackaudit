# Specification: Real-Time Updates

## Goal
Implement Laravel Reverb and Laravel Echo to provide real-time notifications when infrastructure data changes, enabling users to stay synchronized without manual page refreshes while protecting active editing sessions from disruptive auto-updates.

## User Stories
- As a datacenter operator, I want to receive real-time notifications when infrastructure data I'm viewing changes so that I can refresh and see the latest state without constantly reloading pages.
- As a team lead, I want to see toast notifications with badge counters when colleagues make changes so that I'm aware of updates and can choose when to refresh.

## Specific Requirements

**Install and Configure Laravel Reverb**
- Install `laravel/reverb` package via Composer
- Run `php artisan reverb:install` to publish configuration files
- Configure environment variables: `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`
- Update `config/broadcasting.php` to use Reverb as the default driver
- Configure queue connection for broadcasting events (use `ShouldBroadcast` interface)
- Add Reverb server to deployment/process manager configuration (Supervisor)

**Install and Configure Laravel Echo Frontend**
- Install `laravel-echo` and `pusher-js` npm packages
- Create `resources/js/echo.ts` bootstrap file to initialize Echo with Reverb configuration
- Import Echo bootstrap in `resources/js/app.ts`
- Configure Echo to use private channels with CSRF authentication
- Ensure Echo connects on page load and reconnects on connection loss

**Create Datacenter-Scoped Private Channels**
- Add channel authorization in `routes/channels.php` for `datacenter.{datacenterId}`
- Authorization callback checks if user has access to the datacenter via `$user->datacenters->contains($datacenterId)`
- All infrastructure broadcasts will be scoped to the datacenter channel
- Users only receive events for datacenters they have permission to access

**Extend Existing Events to Broadcast**
- Modify `ConnectionChanged` event to implement `ShouldBroadcast` interface
- Modify `ImplementationFileApproved` event to implement `ShouldBroadcast`
- Modify `FindingResolved` event to implement `ShouldBroadcast`
- Add `broadcastOn()` returning `PrivateChannel('datacenter.'.$this->getDatacenterId())`
- Add `broadcastWith()` to return minimal, serializable payload (entity ID, action, user, timestamp)
- Add `broadcastAs()` to return semantic event names (e.g., `connection.created`)

**Create New Broadcast Events for Additional Entities**
- Create `DeviceChanged` event for device placement/status changes (implements `ShouldBroadcast`)
- Create `RackChanged` event for rack modifications (implements `ShouldBroadcast`)
- Create `AuditStatusChanged` event for audit status transitions (implements `ShouldBroadcast`)
- Create `FindingAssigned` event for finding assignment changes (implements `ShouldBroadcast`)
- Follow the pattern established in `app/Events/AuditExecution/DeviceLocked.php`
- Dispatch events from model observers or service classes where changes occur

**Create Real-Time Toast Notification Component**
- Create `resources/js/components/notifications/RealtimeToast.vue` component
- Display toast when real-time event is received (entity type, action, user who made change)
- Include "Refresh" button that triggers Inertia page reload
- Include "Dismiss" button to close the toast
- Toast auto-dismisses after 10 seconds if not interacted with
- Stack multiple toasts if several changes occur in quick succession
- Use existing notification styling patterns from `NotificationBell.vue`

**Create Real-Time Composable for Vue**
- Create `resources/js/composables/useRealtimeUpdates.ts` composable
- Accept datacenter ID parameter to subscribe to correct channel
- Provide `onDataChange(entityType, callback)` method to register event handlers
- Handle channel subscription and cleanup on component unmount
- Export reactive `hasUpdates` ref and `pendingUpdates` array for UI binding
- Follow Echo usage pattern from `InventoryExecute.vue` for channel management

**Integrate Real-Time Updates into Key Pages**
- Add real-time listener to Connections index page (show toast on connection changes)
- Add real-time listener to Devices index page (show toast on device changes)
- Add real-time listener to Racks index page (show toast on rack changes)
- Add real-time listener to Implementation Files page (show toast on approval changes)
- Add real-time listener to Findings index page (show toast on status/assignment changes)
- Do NOT auto-refresh content; always show notification with manual refresh option

**Protect Edit Forms from Disruptive Updates**
- When user is on an edit form, store the entity ID being edited
- If real-time event indicates that entity was modified by another user, show warning toast
- Warning toast text: "This [entity] was modified by [user]. Save your changes or refresh."
- Do NOT auto-update form fields or reset form state
- Allow user to continue editing and submit (optimistic concurrency)

**Update Notification Badge Counter**
- Enhance `NotificationBell.vue` to subscribe to datacenter channel
- Increment badge counter when real-time events are received
- Distinguish between database notifications and real-time update indicators
- Clear real-time update indicator when user clicks refresh or navigates

## Existing Code to Leverage

**Broadcast Events in `app/Events/AuditExecution/`**
- `DeviceLocked.php`, `DeviceUnlocked.php`, `VerificationCompleted.php` already implement `ShouldBroadcast`
- Follow their structure: `broadcastOn()`, `broadcastWith()`, `broadcastAs()` methods
- Reuse the same patterns for payload structure and channel naming conventions

**Existing Events to Extend**
- `ConnectionChanged.php` - add `ShouldBroadcast` interface and broadcast methods
- `ImplementationFileApproved.php` - add `ShouldBroadcast` interface and broadcast methods
- `FindingResolved.php` - add `ShouldBroadcast` interface and broadcast methods
- These already have proper constructor signatures with entity relationships loaded

**Echo Setup in `InventoryExecute.vue`**
- Lines 478-529 show pattern for setting up Echo private channels
- Pattern for `setupEchoChannel()` and `cleanupEchoChannel()` functions
- Event listener registration with `.listen('.event.name', callback)` syntax
- Proper cleanup in `onUnmounted()` lifecycle hook

**`NotificationBell.vue` Component**
- Existing toast/notification UI patterns and styling
- Badge counter implementation with `unreadCount` ref
- Dropdown menu structure for notification display
- Icon selection based on notification type

**Channel Authorization in `routes/channels.php`**
- Existing `audit.{auditId}` channel authorization pattern
- Check user relationship to authorize channel access
- Return boolean from authorization callback

## Out of Scope
- Offline support / service workers for queuing events when disconnected
- Presence channels showing which users are viewing what pages
- Typing indicators or real-time cursor positions
- Collaborative editing features (simultaneous document editing)
- Auto-reload of content (user must click refresh to update)
- Real-time chat or messaging between users
- Push notifications to mobile devices or browser push API
- WebSocket fallback to long-polling for unsupported browsers
- Rate limiting of broadcast events (handled by queue system)
- Custom notification preferences per user for real-time events
