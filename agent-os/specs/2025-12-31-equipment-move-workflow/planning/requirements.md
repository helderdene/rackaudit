# Spec Requirements: Equipment Move Workflow

## Initial Description
Equipment Move Workflow - Guided process for moving devices between racks with connection documentation and history.

From product roadmap item #44 (Phase 6: Polish & Optimization). This feature builds upon the existing infrastructure management capabilities including device placement, connection management, and activity logging.

## Requirements Discussion

### First Round Questions

**Q1:** I assume the move workflow should be a multi-step wizard (e.g., Step 1: Select device, Step 2: Disconnect cables, Step 3: Select destination rack/position, Step 4: Reconnect cables, Step 5: Confirm). Is that correct, or should it be a simpler single-page flow with expandable sections?
**Answer:** Confirmed - use a multi-step wizard approach.

**Q2:** For connection handling during moves, I'm thinking the workflow should document existing connections before the move, allow the operator to mark connections as "to be disconnected" vs "to be re-established at destination", and prompt for reconnection after placement. Should connections be automatically disconnected when a device is moved, or should they remain in a "pending reconnection" state requiring manual verification?
**Answer:** Connections should be automatically disconnected when a device is moved.

**Q3:** I assume the workflow should support moving devices between different racks (potentially in different rooms or datacenters). Should we also support moving devices within the same rack (changing U position), or is that already handled adequately by the existing rack elevation drag-and-drop?
**Answer:** Support both inter-rack moves (between different racks/rooms/datacenters) AND intra-rack moves (changing U position within the same rack).

**Q4:** For move history, should we create a dedicated "Equipment Move" record/model that captures the complete move event (source rack, destination rack, connections before/after, operator notes, timestamps), or should we rely on the existing ActivityLog system to track changes to the device's rack_id?
**Answer:** Create a dedicated "Equipment Move" record/model to capture complete move events.

**Q5:** I assume moves require some form of approval for production environments (e.g., operator initiates, manager approves). Should we implement an approval workflow for moves, or is this a direct operation for authorized operators?
**Answer:** Yes, implement approval workflow (operator initiates, manager approves).

**Q6:** For the "connection documentation" aspect, should the workflow generate a printable move checklist/work order (PDF) that operators can take to the datacenter floor, listing the device, its current connections, destination location, and cables to reconnect?
**Answer:** Yes, generate printable move checklist/work order (PDF) for datacenter floor use.

**Q7:** What should happen if a device has active connections when a move is initiated? Should the workflow block until connections are manually disconnected, auto-disconnect them with logging, or warn but allow proceeding?
**Answer:** Block the move until connections are manually disconnected first.

**Q8:** Is there anything specific that should be explicitly OUT of scope for this feature (e.g., bulk moves of multiple devices, scheduled moves, integration with ticketing systems)?
**Answer:** Bulk moves, scheduled moves, and ticketing system integration are explicitly excluded from scope.

### Existing Code to Reference

No similar existing features identified for reference by the user. However, based on codebase analysis, the following existing code should be referenced:

- **Device placement logic:** `resources/js/composables/useRackElevation.ts` - handles device placement within racks
- **Rack elevation UI:** `resources/js/Pages/Racks/Elevation.vue` - visual rack management interface
- **Connection model:** `app/Models/Connection.php` - connection management with full state logging
- **Activity logging:** `app/Models/ActivityLog.php` and `app/Models/Concerns/Loggable.php` - change tracking
- **Connection history:** `resources/js/Pages/ConnectionHistory/Index.vue` - history viewing patterns
- **Implementation file approval:** `app/Models/ImplementationFile.php` - example of approval workflow pattern
- **PDF generation:** Existing Laravel DomPDF integration for audit reports

### Follow-up Questions

**Follow-up 1:** I notice a conflict between your answers - you said connections should be automatically disconnected when a device is moved (Answer #2), but also that moves should be blocked until connections are manually disconnected first (Answer #7). Could you clarify the intended behavior?

**Answer:** Option C - Hybrid approach:
- The wizard shows active connections to the operator
- Requires operator acknowledgment/confirmation that connections will be disconnected
- Auto-disconnects connections when the move is approved/executed

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A - No mockups or wireframes available. UI design should follow existing application patterns from rack elevation views and other wizard-style interfaces if they exist.

## Requirements Summary

### Functional Requirements

**Move Workflow (Multi-Step Wizard):**
- Step-by-step guided process for moving devices
- Support for both inter-rack moves (different racks/rooms/datacenters) and intra-rack moves (same rack, different U position)
- Device selection with current location display
- Destination rack and U position selection with availability validation

**Connection Handling (Hybrid Approach):**
- Display all active connections on the device being moved
- Require operator acknowledgment that connections will be disconnected
- Automatically disconnect all connections when the move is approved/executed
- Log all disconnected connections with before/after state

**Approval Workflow:**
- Operator initiates move request
- Manager/approver reviews and approves or rejects
- Move executes only after approval
- Notifications for pending approvals (ties into future notification system)

**Move History (Dedicated Model):**
- Create EquipmentMove model to capture complete move events
- Store: source rack, destination rack, source U position, destination U position
- Store: connections before move (snapshot), operator who initiated, approver who approved
- Store: timestamps (requested, approved, executed), operator notes, approval notes
- Queryable history for auditing and reporting

**PDF Work Order Generation:**
- Generate printable move checklist/work order
- Include: device details, current location, destination location
- Include: list of all connections to be disconnected
- Include: cable details (type, length, color, endpoints)
- Include: space for operator signatures/timestamps

### Reusability Opportunities

- **useRackElevation composable:** Extend or reference for destination rack/position selection
- **Connection model logging:** Leverage existing `$logFullState = true` for connection change tracking
- **ActivityLog infrastructure:** Use for move-related activity tracking
- **PDF generation patterns:** Follow existing audit report PDF generation approach
- **Approval workflow patterns:** Reference ImplementationFile approval workflow if applicable

### Scope Boundaries

**In Scope:**
- Single device moves (one device at a time)
- Inter-rack moves (between different racks, rooms, or datacenters)
- Intra-rack moves (within same rack, different U position)
- Multi-step wizard interface
- Connection documentation and automatic disconnection
- Approval workflow (operator initiates, manager approves)
- Move history tracking with dedicated model
- PDF work order/checklist generation
- Move history viewing and filtering

**Out of Scope:**
- Bulk moves (moving multiple devices simultaneously)
- Scheduled moves (future-dated moves)
- Ticketing system integration
- Automatic reconnection at destination (connections are disconnected, not re-established)
- Move templates or presets

### Technical Considerations

- **Database:** New EquipmentMove model with migrations, relationships to Device, Rack, User (initiator, approver)
- **Backend:** New controller for move workflow, form requests for validation, policy for authorization
- **Frontend:** Multi-step wizard Vue component(s), integration with existing rack elevation components
- **PDF:** Laravel DomPDF for work order generation
- **Permissions:** New permissions for move initiation vs move approval (RBAC integration)
- **Real-time:** Consider Laravel Echo for approval notifications (ties into existing real-time infrastructure)
- **Activity Logging:** Extend logging to capture move events with full context
