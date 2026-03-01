# Specification: Equipment Move Workflow

## Goal
Provide a guided multi-step wizard for moving devices between racks (or within the same rack) with full connection documentation, approval workflow, and PDF work order generation for datacenter floor operations.

## User Stories
- As an operator, I want to initiate a device move request with a step-by-step wizard so that I can properly document the move and get approval before execution.
- As a manager, I want to review and approve pending move requests so that moves are authorized before physical execution.
- As a technician, I want to print a PDF work order showing device details, current connections, and destination location so that I have all information needed on the datacenter floor.

## Specific Requirements

**Multi-Step Wizard Interface**
- Step 1: Select device to move (search/browse with current location display)
- Step 2: Review active connections on the device with acknowledgment checkbox
- Step 3: Select destination rack and U position with availability validation
- Step 4: Add operator notes and submit for approval
- Use dialog/sheet component for wizard overlay (follow existing UI patterns)
- Show progress indicator across wizard steps
- Allow navigation back to previous steps before final submission

**Device Selection (Step 1)**
- Search devices by name, asset tag, or serial number
- Display device's current location: datacenter > room > row > rack > U position
- Show device physical attributes: U height, width type, rack face
- Prevent selection of devices already in pending move requests
- Support launching wizard from device detail page or rack elevation view

**Connection Review (Step 2)**
- Display all active connections on the device being moved
- Show for each connection: source port, destination port, cable type, cable length, cable color
- Include connected device names and port labels for clarity
- Require operator to check acknowledgment: "I understand all connections will be disconnected"
- Block proceeding without acknowledgment when connections exist

**Destination Selection (Step 3)**
- Hierarchical selection: datacenter > room > row > rack
- Visual U position picker showing available slots (reuse patterns from `useRackElevation.ts`)
- Real-time collision detection for target position
- Support both inter-rack moves and intra-rack moves (same rack, different U position)
- Show destination rack utilization statistics

**Approval Workflow**
- New moves start with status "pending_approval"
- Manager/approver can approve or reject pending moves
- On approval: status changes to "approved", connections auto-disconnect, device placement updates
- On rejection: status changes to "rejected" with rejection notes, no changes made
- Only approved moves execute the actual device relocation
- Store: requested_by, approved_by, requested_at, approved_at, executed_at timestamps

**EquipmentMove Model**
- Create dedicated model to capture complete move events
- Fields: device_id, source_rack_id, destination_rack_id, source_start_u, destination_start_u
- Fields: source_rack_face, destination_rack_face, source_width_type, destination_width_type
- Fields: status (pending_approval, approved, rejected, executed, cancelled)
- Fields: connections_snapshot (JSON array of connection data before disconnection)
- Fields: requested_by, approved_by, operator_notes, approval_notes
- Fields: requested_at, approved_at, executed_at timestamps
- Use Loggable trait for activity logging

**PDF Work Order Generation**
- Generate printable checklist after move request is created
- Include device details: name, asset tag, serial number, manufacturer, model
- Include current location: datacenter, room, row, rack, U position
- Include destination location: datacenter, room, row, rack, U position
- Include connections table: port labels, cable type, cable length, cable color, connected device
- Include signature/timestamp fields for operator use
- Follow existing PDF generation pattern from `AssetReportService`

**Move Execution Logic**
- Only execute moves that have status "approved"
- Auto-disconnect all device connections with full state logging
- Update device placement (rack_id, start_u, rack_face, width_type)
- Update move status to "executed" with executed_at timestamp
- Log all changes via ActivityLog system

**Move History and Viewing**
- Provide move history index page with filtering (by device, by rack, by status, by date)
- Show move details: device info, source/destination, connections disconnected, timestamps
- Link from device detail page to its move history
- Support pagination for large history sets

## Visual Design

No visual mockups provided. Follow existing application patterns:
- Use dialog/sheet components for wizard overlay (similar to device modals)
- Use card-based layout for wizard steps
- Use existing form input components (Input, Select, Checkbox, Button)
- Use Badge component for status display
- Follow Tailwind CSS patterns and dark mode support from existing pages

## Existing Code to Leverage

**`app/Models/ImplementationFile.php` - Approval Workflow Pattern**
- Reference approval_status enum pattern: 'pending_approval', 'approved'
- Reference approved_by foreign key with nullOnDelete
- Reference approved_at timestamp field
- Implement isPendingApproval() and isApproved() helper methods

**`resources/js/composables/useRackElevation.ts` - Position Selection**
- Reuse canPlaceAt() logic for destination position validation
- Reference getOccupationMap() for collision detection
- Reference getValidDropPositions() for available slot calculation
- Adapt for destination rack selection context

**`app/Models/Connection.php` - Connection Handling**
- Reference logFullState = true pattern for full state snapshots
- Reference getEnrichedAttributesForLog() for enriched connection data
- Use SoftDeletes for connection history preservation
- Reference getLogicalPath() for connection path documentation

**`app/Services/AssetReportService.php` - PDF Generation**
- Follow Pdf::loadView() pattern with Barryvdh\DomPDF
- Reference storeReport() pattern for filesystem storage
- Reference buildFilterScope() pattern for report metadata
- Create new blade template at resources/views/pdf/move-work-order.blade.php

**`app/Models/Concerns/Loggable.php` - Activity Logging**
- Apply Loggable trait to EquipmentMove model
- Reference excludeFromActivityLog for sensitive fields
- Use existing ActivityLog infrastructure for audit trail

## Out of Scope
- Bulk moves (moving multiple devices simultaneously in one request)
- Scheduled moves (future-dated moves with automatic execution)
- Ticketing system integration (ServiceNow, Jira, etc.)
- Automatic reconnection at destination (connections are disconnected only)
- Move templates or presets (saved move configurations)
- Real-time notifications for approval workflow (future enhancement)
- Move cost tracking or billing integration
- Equipment reservation system
- Rollback/undo of executed moves
- Inter-datacenter network path validation
