# Spec Requirements: Connection Management

## Initial Description
Connection Management - CRUD for point-to-point connections between ports including cable type, length, color, and path notes

## Requirements Discussion

### First Round Questions

**Q1:** I assume connections will be created between two ports (source and destination), where each port can only have one active connection at a time. Is that correct, or should ports support multiple connections (e.g., for daisy-chaining scenarios)?
**Answer:** Confirmed - each port can only have one active connection at a time.

**Q2:** I'm thinking cable properties should include: type (Cat5e, Cat6, Cat6a, fiber SM/MM, power C13/C14/C19/C20), length (in meters or feet), and color. Should we also track manufacturer/part number, or is that out of scope?
**Answer:** Confirmed - track type, length, and color. Manufacturer/part number is out of scope for initial implementation.

**Q3:** For the connection path notes, I assume this is free-form text to describe the physical routing (e.g., "Through overhead cable tray, down row 5"). Is that correct?
**Answer:** Confirmed - free-form text for physical routing descriptions.

**Q4:** Should connections be directional for certain port types? For example, power connections typically flow from PDU outlet to device power inlet, while network connections are often bidirectional.
**Answer:** See follow-up question 2 below for detailed answer.

**Q5:** How should we handle patch panel connections? Should we support "pass-through" connections where the patch panel is an intermediate point between two endpoints?
**Answer:** See follow-up question 1 below for detailed answer.

**Q6:** I assume we should validate that connected ports are compatible (e.g., Ethernet to Ethernet, Fiber to Fiber, Power Output to Power Input). Is that correct, or should we allow any port-to-port connection?
**Answer:** Confirmed - validate port compatibility (matching types for network, proper direction for power).

**Q7:** Should we include a visual representation of the connection on the rack view, or is this purely data management for now?
**Answer:** Data management for now, visual representation is a future enhancement.

**Q8:** Is there anything you explicitly do NOT want included in this feature, or any future enhancements we should plan the data model around but not implement yet?
**Answer:** Visual representation is out of scope. Focus on CRUD operations and data integrity.

### Existing Code to Reference

**Similar Features Identified:**
- Model: `App\Models\Port` - Path: `app/Models/Port.php` - Port entity with device relationship, types, subtypes, status, and direction
- Enum: `App\Enums\PortType` - Path: `app/Enums/PortType.php` - Ethernet, Fiber, Power types
- Enum: `App\Enums\PortDirection` - Path: `app/Enums/PortDirection.php` - Uplink, Downlink, Bidirectional for network; Input, Output for power
- Enum: `App\Enums\PortStatus` - Path: `app/Enums/PortStatus.php` - Port status tracking
- Model: `App\Models\Device` - Path: `app/Models/Device.php` - Device entity that ports belong to
- Model: `App\Models\Pdu` - Path: `app/Models/Pdu.php` - PDU entity for power connections

### Follow-up Questions

**Follow-up 1:** How should patch panel pass-through connections work?
- Option A: Two separate connection records (Device A -> Patch Panel front port) and (Patch Panel back port -> Device B), with the patch panel port pairs linked together
- Option B: Single connection record that references the patch panel as an intermediate point

**Answer:** Option A - Two separate connection records (Device A to Patch Panel front port) and (Patch Panel back port to Device B), with the patch panel port pairs linked together so the system can show the "logical" end-to-end path.

**Follow-up 2:** Which port types require directionality in connections?
**Answer:** Confirmed:
- Power connections: Always directional (PDU outlet = source, device power port = destination)
- Network connections: Bidirectional (no directionality needed)
- Fiber connections: Bidirectional

**Follow-up 3:** How should patch panel port pairing work?
- Option A: Auto-pair by position (front port 1 pairs with back port 1)
- Option B: Manual pairing configured when setting up the patch panel

**Answer:** Option B - Manual pairing configured when setting up the patch panel.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A

## Requirements Summary

### Functional Requirements
- CRUD operations for connections between two ports
- Connection properties: source port, destination port, cable type, cable length, cable color, path notes
- Cable types to support: Cat5e, Cat6, Cat6a, Fiber SM (single-mode), Fiber MM (multi-mode), Power C13, C14, C19, C20
- One active connection per port constraint
- Port compatibility validation (Ethernet-to-Ethernet, Fiber-to-Fiber, Power Output-to-Power Input)
- Directional enforcement for power connections (source must be Output direction, destination must be Input direction)
- Bidirectional treatment for network/fiber connections
- Patch panel port pairing: manual configuration to link front and back ports
- Patch panel pass-through: two separate connections with linked port pairs to derive logical end-to-end path
- Ability to query/display the logical end-to-end path through patch panels

### Reusability Opportunities
- Existing `Port` model with device relationship
- Existing `PortType` enum (Ethernet, Fiber, Power)
- Existing `PortDirection` enum (Uplink, Downlink, Bidirectional, Input, Output)
- Existing `PortStatus` enum for tracking connection status
- Follow established patterns in existing models for Loggable concern and factories

### Scope Boundaries
**In Scope:**
- Connection model with CRUD operations
- Cable properties (type, length, color)
- Path notes (free-form text)
- Port compatibility validation
- Power connection directionality enforcement
- Patch panel port pairing (manual configuration)
- Logical end-to-end path derivation through patch panels
- API endpoints for connection management
- Unit and feature tests

**Out of Scope:**
- Visual representation on rack view (future enhancement)
- Cable manufacturer/part number tracking
- Automatic patch panel port pairing by position
- Multi-connection per port (daisy-chaining)
- Cable inventory management

### Technical Considerations
- New `Connection` model linking two ports with cable metadata
- New `PatchPanelPortPair` or similar mechanism to link front/back patch panel ports (could be a self-referential relationship on Port or separate linking table)
- Validation rules for port type compatibility
- Validation rules for power connection directionality
- Consider soft deletes for connection history tracking
- Index on source_port_id and destination_port_id for efficient lookups
- Unique constraint to prevent multiple connections on same port
- Query method to traverse patch panel pairs and show logical path
