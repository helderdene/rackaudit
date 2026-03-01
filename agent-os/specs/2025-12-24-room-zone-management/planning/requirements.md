# Spec Requirements: Room/Zone Management

## Initial Description
CRUD for rooms within datacenters including layout, row/aisle organization, and PDU assignment.

This is item #8 on the product roadmap (Phase 2: Core Infrastructure Management), sized as "S" (small).

The feature builds on the existing Datacenter Management feature (#7) which is already complete, and will be a prerequisite for:
- Rack Management (#9)
- Rack Elevation View (#10)
- Device Placement (#12)

The hierarchy established is: Datacenter > Room > Row > Rack > Device > Port

## Requirements Discussion

### First Round Questions

**Q1:** I assume a Room will have attributes like Name/Label (required), Description (optional), Square Footage/Area (optional), and a reference to its parent Datacenter. Should we also include fields for Room Type (e.g., server room, network closet, cage/colocation space)?
**Answer:** Correct on all attributes. Yes, include Room Type with those options plus "storage" and "electrical room" as additional types.

**Q2:** For the Room list view, I'm thinking we show rooms grouped or filtered by Datacenter, with columns for Name, Type, Row Count, and Rack Count (placeholders until those entities exist). Does that match your expectations?
**Answer:** Correct. Show rooms filtered by datacenter with those columns. Row and rack counts will be placeholders initially.

**Q3:** I assume the CRUD operations should follow the same UI patterns as Datacenter Management - index page with table, create/edit forms, and delete confirmation modal. Is that correct?
**Answer:** Yes, follow the exact same patterns from Datacenter Management.

**Q4:** For "row/aisle organization," I assume we need a Row entity that belongs to a Room, with basic attributes like Name/Label and Position/Sequence for ordering. Should rows support any additional metadata like hot-aisle/cold-aisle designation?
**Answer:** Yes, Rows need Name/Label, Position/Sequence, and hot-aisle/cold-aisle designation. Keep it simple for now.

**Q5:** I'm thinking PDU (Power Distribution Unit) assignment means PDUs belong to a Room and have attributes like Name, Total Capacity (in kW or Amps), Circuit Count, and Status. Should PDUs track individual circuits, or just the overall PDU metadata for now?
**Answer:** PDUs should have Name, Total Capacity, Circuit Count, and Status. Just track overall PDU metadata for now - individual circuit tracking is a future enhancement.

**Q6:** Should there be any access control considerations? I assume Room access inherits from the parent Datacenter's user access relationship (users who can access a Datacenter can access its Rooms)?
**Answer:** Correct. Room access inherits from Datacenter access. No separate room-level permissions needed.

**Q7:** For activity logging, I assume we log all CRUD operations on Rooms, Rows, and PDUs following the existing activity logging patterns. Any additional events to track?
**Answer:** Yes, log all CRUD operations following existing patterns. No additional events needed for MVP.

**Q8:** Is there anything that should explicitly NOT be part of this feature? For example, should we exclude floor plan visualization, capacity planning dashboards, or power monitoring?
**Answer:** Exclude floor plan visualization (future feature), capacity planning, and power monitoring. This is just CRUD and basic relationships.

### Follow-up Questions

**Follow-up 1:** For the Row entity, I'm assuming: Name/Label (required), Position/Sequence for ordering, Direction/Orientation (hot-aisle/cold-aisle), and Status (active/inactive). Should rows have any other attributes?
**Answer:** Correct - Name/Label (required), Position/Sequence (ordering), Direction/Orientation (hot-aisle/cold-aisle), Status (active/inactive). No other attributes needed.

**Follow-up 2:** For the Row list view within a Room, should we show: Row name, rack count (placeholder), and status? Any other columns?
**Answer:** Correct - Display row name, rack count (placeholder), and status. No other columns needed.

**Follow-up 3:** For PDU attributes, I'm assuming: Name/Label (required), Model, Manufacturer, Total Capacity (kW/Amps), Voltage, Phase (single/three-phase), Circuit Count, and Status (active/inactive/maintenance). Does this cover the essential PDU metadata?
**Answer:** Correct - Name/Label (required), Model, Manufacturer, Total Capacity (kW/Amps), Voltage, Phase (single/three-phase), Circuit Count, Status (active/inactive/maintenance).

**Follow-up 4:** Should PDUs be assigned directly to a Room, or could they also be assigned to a specific Row within a room? (Some datacenters have PDUs serving entire rooms, others have row-level PDUs)
**Answer:** Support both assignment levels - PDUs can be assigned to a Room OR to a Row.

**Follow-up 5:** For the PDU list view, should we show: PDU name, model, capacity, circuit usage (e.g., "12/24 circuits" as placeholder), and status?
**Answer:** Correct - Display PDU name, model, capacity, circuit usage (placeholder), and status.

**Follow-up 6:** You mentioned just tracking overall PDU metadata. Should Circuit Count be a simple integer field, or should we track "used" vs "total" circuits even without detailed circuit management?
**Answer:** Just the overall circuit count as a simple integer field - detailed circuit management is a future enhancement.

### Existing Code to Reference

**Similar Features Identified:**
- Feature: Datacenter Management - Path: Follow existing Datacenter module patterns
- Components to potentially reuse: Tables, forms, modals, buttons from existing UI components
- Backend logic to reference: Datacenter CRUD controllers, models, and Form Requests

No specific file paths provided - implementation should follow Datacenter module patterns.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
Not applicable - no visuals were submitted.

## Requirements Summary

### Functional Requirements

**Room Entity:**
- Create, read, update, delete rooms within a datacenter
- Attributes: Name/Label (required), Description (optional), Square Footage/Area (optional), Room Type (server room, network closet, cage/colocation space, storage, electrical room), parent Datacenter reference
- List view: Rooms filtered by datacenter with columns for Name, Type, Row Count (placeholder), Rack Count (placeholder)

**Row Entity:**
- Create, read, update, delete rows within a room
- Attributes: Name/Label (required), Position/Sequence (for ordering), Direction/Orientation (hot-aisle/cold-aisle), Status (active/inactive)
- List view within Room: Row name, rack count (placeholder), status

**PDU Entity:**
- Create, read, update, delete PDUs
- Attributes: Name/Label (required), Model, Manufacturer, Total Capacity (kW/Amps), Voltage, Phase (single/three-phase), Circuit Count (simple integer), Status (active/inactive/maintenance)
- Assignment: PDUs can be assigned to a Room OR to a Row (flexible assignment level)
- List view: PDU name, model, capacity, circuit usage (placeholder), status

**Access Control:**
- Room access inherits from parent Datacenter's user access relationship
- No separate room-level permissions

**Activity Logging:**
- Log all CRUD operations on Rooms, Rows, and PDUs
- Follow existing activity logging patterns

### Reusability Opportunities
- Follow Datacenter module patterns for controllers, models, views
- Reuse existing UI components (tables, forms, modals, buttons)
- Reuse Form Request validation patterns from Datacenter
- Follow Vue/Inertia page patterns established in Datacenters module

### Scope Boundaries

**In Scope:**
- Room CRUD with all specified attributes
- Row CRUD with ordering and hot/cold aisle designation
- PDU CRUD with basic metadata
- PDU assignment to Room or Row level
- Access control inheritance from Datacenter
- Activity logging for all entities
- List views with placeholder counts for future entities

**Out of Scope:**
- Floor plan visualization (future feature)
- Capacity planning dashboards
- Power monitoring
- Detailed circuit management (future enhancement)
- Individual circuit tracking within PDUs
- Separate room-level access permissions

### Technical Considerations
- Builds on existing Datacenter Management feature
- Prerequisite for Rack Management, Rack Elevation View, Device Placement
- Hierarchy: Datacenter > Room > Row > Rack > Device > Port
- Row/Rack counts will be placeholder values until those entities are implemented
- PDU circuit usage will be placeholder until detailed circuit management is added
- Must integrate with existing user-datacenter access relationship
- Must use existing activity logging infrastructure
