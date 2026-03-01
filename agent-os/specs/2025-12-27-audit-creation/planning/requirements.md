# Spec Requirements: Audit Creation

## Initial Description
Audit Creation - Interface to create new audits with scope selection (datacenter, room, rack), audit type (connection, inventory), and configuration.

## Requirements Discussion

### First Round Questions

**Q1:** I assume an audit should be scoped at one of three levels: a single datacenter (all racks), a single room (all racks in that room), or individual racks (multi-select). Is that correct, or should we also support row-level scoping (all racks in a row)?
**Answer:** Correct - datacenter, room, or individual racks (multi-select). No row-level needed.

**Q2:** For audit types, the roadmap mentions "connection" and "inventory" audits. I'm assuming these are mutually exclusive for a single audit - meaning users create either a Connection Audit OR an Inventory Audit. Is that correct, or should users be able to create a combined audit that verifies both connections AND inventory in one session?
**Answer:** Correct - mutually exclusive. Users create either a Connection Audit OR an Inventory Audit, not combined.

**Q3:** For connection audits, I assume the audit should be linked to an approved implementation file to compare expected vs. actual connections. Should we require selecting a specific implementation file, or automatically use the latest approved version for the selected scope? What if no approved implementation files exist for the selected scope - should we block audit creation or allow it with a warning?
**Answer:** Automatically use the latest approved version for the selected scope. Block audit creation when no approved implementation file exists.

**Q4:** For inventory audits, what should be compared? I'm thinking: verify that documented devices exist physically (barcodes/serial numbers) and are in the correct rack/U position. Should we also compare device details like model, hostname, or other attributes?
**Answer:** Correct - verify documented devices exist physically (barcodes/serial numbers) and are in correct rack/U position.

**Q5:** I assume audit configuration should include basic metadata like name, description, and due date. Should there also be an assignee field to assign the audit to a specific operator/team member from the start, or is that handled separately after creation?
**Answer:** Correct - include name, description, due date, AND an assignee field to assign to a specific operator/team member from the start.

**Q6:** Should there be an option to schedule audits for future dates (the audit is created but not "started" until the scheduled date), or are audits always created in a "pending" or "in progress" state ready for immediate execution?
**Answer:** Audits always created in "pending" or "in progress" state ready for immediate execution. No future scheduling.

**Q7:** I'm assuming the audit creation interface should be accessible to users with IT Manager and Auditor roles based on RBAC. Should Operators also be able to create audits, or only execute them?
**Answer:** Correct - IT Manager and Auditor roles can create audits. Operators can only execute them.

**Q8:** Is there anything specific we should explicitly exclude from this feature? For example: audit templates, recurring/scheduled audit series, partial scope selection (specific devices within a rack)?
**Answer:** INCLUDE partial scope selection (specific devices within a rack) - this is IN scope for the feature.

### Existing Code to Reference

No similar existing features identified for reference. However, the following existing patterns should inform the implementation:
- CRUD interfaces for Datacenters, Rooms, Racks (`/Users/helderdene/rackaudit/resources/js/Pages/Datacenters/`, `/Users/helderdene/rackaudit/resources/js/Pages/Rooms/`, `/Users/helderdene/rackaudit/resources/js/Pages/Racks/`)
- Implementation Files management (`/Users/helderdene/rackaudit/resources/js/Pages/ImplementationFiles/`)
- Existing models: `ImplementationFile`, `ExpectedConnection`, `Connection`, `Datacenter`, `Room`, `Rack`, `Device`

### Follow-up Questions

**Follow-up 1:** For partial scope selection (specific devices within a rack), how should this work with scope hierarchy? Can a user select "Room A" as scope AND then add individual devices from "Room B"? Or should device-level selection only be available when selecting "Individual Racks" scope?
**Answer:** Device-level selection only available when selecting "Individual Racks" scope, allowing further filtering to specific devices within those racks.

**Follow-up 2:** For audit assignee, should this be a single user or support multiple assignees (e.g., a team working on a large audit together)?
**Answer:** Support multiple assignees (a team working on a large audit together).

**Follow-up 3:** When audit creation is blocked due to no approved implementation file (for connection audits), should we show a helpful link/action to navigate to Implementation Files to upload/approve one, or just display an error message?
**Answer:** Show a helpful link/action to navigate to Implementation Files to upload/approve one.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A

## Requirements Summary

### Functional Requirements
- Create new audits with two mutually exclusive types: Connection Audit or Inventory Audit
- Scope selection at three levels:
  - Datacenter level (all racks in the datacenter)
  - Room level (all racks in a specific room)
  - Individual racks (multi-select specific racks)
- When "Individual Racks" scope is selected, allow further filtering to specific devices within those racks (partial scope selection)
- Audit metadata fields: name, description, due date
- Multiple assignees support (team assignment)
- For Connection Audits:
  - Automatically link to the latest approved implementation file for the selected scope
  - Block audit creation if no approved implementation file exists
  - Show helpful link to Implementation Files page when blocked
- For Inventory Audits:
  - Compare documented devices against physical reality (barcodes/serial numbers, rack/U position)
- Audits created in "pending" state ready for immediate execution (no future scheduling)
- Role-based access: IT Manager and Auditor roles can create audits; Operators can only execute

### Reusability Opportunities
- Existing CRUD page patterns from Datacenters, Rooms, Racks pages
- Form validation patterns from existing Form Request classes
- Cascading dropdown patterns for hierarchical selection (datacenter > room > rack > device)
- Multi-select component patterns for rack and device selection
- User selection component for assignee field (similar patterns may exist in user management)

### Scope Boundaries
**In Scope:**
- Audit creation interface/form
- Scope selection UI (datacenter, room, individual racks)
- Partial scope selection (specific devices within selected racks)
- Audit type selection (connection vs. inventory)
- Metadata configuration (name, description, due date)
- Multiple assignee selection
- Implementation file validation for connection audits
- Error handling with helpful navigation when blocked
- Backend: Audit model, migration, controller, form request
- RBAC permissions for audit creation

**Out of Scope:**
- Audit execution workflow (covered by roadmap items #27, #28)
- Discrepancy detection engine (roadmap item #29)
- Finding management (roadmap item #30)
- Audit templates
- Recurring/scheduled audit series
- Future date scheduling
- Row-level scope selection
- Combined connection + inventory audits

### Technical Considerations
- Audit model needs relationships to: Datacenter, Room, Racks (polymorphic or pivot), Devices (for partial scope), Users (assignees), ImplementationFile (for connection audits)
- Scope type should be stored (enum: datacenter, room, racks) to determine how to interpret scope relationships
- Audit type stored as enum (connection, inventory)
- Status field with initial states (pending, in_progress, etc.)
- Cascade loading for scope selection: selecting datacenter loads rooms, selecting room loads racks, selecting racks loads devices
- Validation must check for approved implementation files when creating connection audits
- Permission middleware to restrict creation to IT Manager and Auditor roles
