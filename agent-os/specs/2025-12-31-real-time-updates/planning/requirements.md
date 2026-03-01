# Spec Requirements: Real-Time Updates

## Initial Description

Implement Laravel Echo for real-time updates when infrastructure changes occur in the application.

## Requirements Discussion

### First Round Questions

**Q1:** I assume the primary goal is to notify users when infrastructure data they're viewing changes (e.g., someone else edits a connection they're looking at). Is that correct, or should real-time updates also include notification-style alerts (toast messages, badge counters, etc.)?
**Answer:** Correct - Real-time updates should include notification-style alerts (toast messages, badge counters, etc.)

**Q2:** I'm thinking we should broadcast updates for the following entity types where collaborative editing is most likely to cause conflicts: Connections (created/updated/deleted), Devices (placement changes, status changes), Racks (modifications), Implementation Files (approval status changes), Audit status changes, Findings (status updates, assignments). Should we prioritize all of these, or focus on a subset? Are there other entities that need real-time updates?
**Answer:** Correct - All suggested entity types should be included (Connections, Devices, Racks, Implementation Files, Audit status, Findings)

**Q3:** For the WebSocket provider, I assume we should use Reverb (Laravel's first-party WebSocket server) which is the recommended approach for Laravel 12. Is that correct, or do you prefer Pusher (hosted, paid) or Soketi (self-hosted, Pusher-compatible)?
**Answer:** Correct - Use Reverb (Laravel's first-party WebSocket server)

**Q4:** I assume updates should be scoped to the datacenter level (e.g., users viewing Datacenter A only receive updates for Datacenter A). Is that the right scope, or should it be more granular (e.g., room-level, rack-level) or broader (all datacenters a user has access to)?
**Answer:** Correct - Scope updates to datacenter level

**Q5:** For the user experience when data changes, I'm thinking: Show a subtle "Data has been updated" notification with a refresh option, NOT auto-reload content that the user might be actively editing. Is this the right approach, or should we auto-update certain views (like dashboards, lists) while protecting edit forms?
**Answer:** Correct - Show subtle notification with refresh option, don't auto-reload content being edited

**Q6:** I assume we should respect the existing permission system, meaning users only receive broadcasts for resources they have permission to view. Is that correct?
**Answer:** Correct - Respect existing permission system

**Q7:** Is there anything that should explicitly be OUT of scope for this feature? For example: offline support, presence channels (showing who's viewing what), or typing indicators?
**Answer:** Exclude the suggested out-of-scope features (offline support, presence channels, typing indicators)

### Existing Code to Reference

No similar existing features identified for reference.

Note: The codebase already has event classes in `app/Events/` that will need to be modified to implement `ShouldBroadcast`:
- `ConnectionChanged.php`
- `ExpectedConnectionConfirmed.php`
- `FindingResolved.php`
- `ImplementationFileApproved.php`

These existing events currently trigger internal application logic but are not set up for broadcasting.

### Follow-up Questions

No follow-up questions required - all requirements are clear.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
Not applicable.

## Requirements Summary

### Functional Requirements

- Install and configure Laravel Reverb as the WebSocket server
- Install and configure Laravel Echo on the frontend
- Implement broadcasting for infrastructure entity changes:
  - Connections (created, updated, deleted)
  - Devices (placement changes, status changes)
  - Racks (modifications)
  - Implementation Files (approval status changes)
  - Audits (status changes)
  - Findings (status updates, assignments)
- Scope broadcasts to datacenter-level private channels
- Display toast notifications when data changes occur
- Provide refresh option in notifications (not auto-reload)
- Protect edit forms from disruptive auto-updates
- Enforce permission checks on broadcast authorization

### Reusability Opportunities

- Existing event classes in `app/Events/` can be extended to implement `ShouldBroadcast`
- Existing activity logging infrastructure may inform what actions trigger broadcasts
- Existing permission system (Spatie Laravel-Permission) will be used for channel authorization

### Scope Boundaries

**In Scope:**
- Laravel Reverb installation and configuration
- Laravel Echo frontend setup
- Broadcasting events for all major entity types
- Datacenter-scoped private channels
- Toast notification UI component
- "Data updated" notifications with refresh option
- Permission-based channel authorization
- Badge counters for notification indicators

**Out of Scope:**
- Offline support / service workers
- Presence channels (showing who's viewing what)
- Typing indicators
- Real-time cursor positions
- Collaborative editing features
- Auto-reload of content (user-initiated refresh only)

### Technical Considerations

- WebSocket Provider: Laravel Reverb (first-party, self-hosted)
- Frontend: Laravel Echo with Vue 3 integration
- Channel Strategy: Private channels scoped to datacenter (e.g., `datacenter.{id}`)
- Authorization: Channel authorization via Laravel's broadcast authorization gates
- Events: Modify existing events to implement `ShouldBroadcast` interface
- Queue: Broadcasting events should be queued for performance
- Configuration: `config/broadcasting.php` needs to be created/configured
- Environment: New environment variables for Reverb configuration
- Package Installation Required:
  - Backend: `laravel/reverb`
  - Frontend: `laravel-echo`, `pusher-js` (Echo uses Pusher protocol)
