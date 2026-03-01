# Specification: Connection Management

## Goal
Enable CRUD operations for point-to-point connections between ports, tracking cable properties (type, length, color) and path notes, with validation for port compatibility and power directionality, plus support for patch panel port pairing to derive logical end-to-end paths.

## User Stories
- As an IT Manager, I want to create connections between device ports so that I can document the physical cabling infrastructure in my datacenter.
- As an IT Manager, I want to pair patch panel front and back ports so that connections through patch panels show the logical end-to-end path.

## Specific Requirements

**Connection Model and Database Schema**
- Create `connections` table with: `id`, `source_port_id`, `destination_port_id`, `cable_type`, `cable_length`, `cable_color`, `path_notes`, `timestamps`, `deleted_at` (soft deletes)
- Foreign keys to `ports` table with cascade on delete
- Unique constraint: each port can only have one active connection (check both source and destination)
- Indexes on `source_port_id` and `destination_port_id` for efficient lookups
- Use the `Loggable` concern for activity logging like existing models

**Cable Type Enum**
- Create `CableType` enum with values: `cat5e`, `cat6`, `cat6a`, `fiber_sm`, `fiber_mm`, `power_c13`, `power_c14`, `power_c19`, `power_c20`
- Include `label()` method returning human-readable names (Cat5e, Cat6, Cat6a, Fiber SM, Fiber MM, C13, C14, C19, C20)
- Include `forPortType()` method to return valid cable types for each `PortType`

**Patch Panel Port Pairing**
- Add nullable `paired_port_id` column to `ports` table via migration
- Self-referential relationship on `Port` model: `pairedPort()` belongs-to relationship
- Pairing is bidirectional: when setting A's paired_port_id to B, also set B's paired_port_id to A
- Validation: only ports on the same device can be paired
- API endpoint to pair/unpair ports: `POST /devices/{device}/ports/{port}/pair` and `DELETE /devices/{device}/ports/{port}/pair`

**Port Compatibility Validation**
- Ethernet ports can only connect to Ethernet ports
- Fiber ports can only connect to Fiber ports
- Power ports require directional validation: source must be `Output`, destination must be `Input`
- Network/Fiber connections are bidirectional (no directional restriction)
- Implement as custom validation rule or within `StoreConnectionRequest` form request

**Connection CRUD API Endpoints**
- `GET /connections` - List all connections with filtering (by device, rack, port type)
- `POST /connections` - Create a new connection
- `GET /connections/{connection}` - Get connection details with source/destination port info
- `PUT /connections/{connection}` - Update connection properties (cable type, length, color, notes)
- `DELETE /connections/{connection}` - Soft delete a connection
- Port status automatically updates to `Connected` when connection created, `Available` when deleted

**Logical Path Derivation**
- Add `getLogicalPath()` method to `Connection` model
- Traverses patch panel port pairs to find the true endpoints
- Returns array of ports in the path from source endpoint to destination endpoint
- Example: Server Port -> Patch Panel Front -> (paired to) Patch Panel Back -> Switch Port

**Connection Resource and Controller**
- Create `ConnectionResource` for consistent JSON output
- Include source port with device info, destination port with device info, cable properties
- Include `logical_path` array when patch panels are involved
- Controller follows pattern established in `PortController`

**Form Request Validation**
- `StoreConnectionRequest`: validate source_port_id, destination_port_id, cable_type, cable_length (positive decimal), cable_color (string), path_notes (nullable text)
- `UpdateConnectionRequest`: same fields but for updating existing connection
- Custom validation for port compatibility and one-connection-per-port constraint

## Visual Design
No visual assets provided - this is data management focused.

## Existing Code to Leverage

**Port Model (`app/Models/Port.php`)**
- Already has `device` relationship, `type`, `subtype`, `status`, `direction` fields
- Add `connection()` relationship (hasOne through source or destination)
- Add `pairedPort()` relationship for patch panel pairing
- Status enum already has `Connected` value for tracking connection state

**PortDirection Enum (`app/Enums/PortDirection.php`)**
- Has `Input`/`Output` for power, `Uplink`/`Downlink`/`Bidirectional` for network
- Has `forType()` method to get valid directions per port type
- Use for power connection directional validation

**PortType Enum (`app/Enums/PortType.php`)**
- Has `Ethernet`, `Fiber`, `Power` values
- Use for port compatibility matching (same type required for connection)

**PortController (`app/Http/Controllers/PortController.php`)**
- Follow same patterns: Gate authorization, form requests, JSON responses
- Use similar structure for option getters (getCableTypeOptions, etc.)
- Controller returns JSON only (no Inertia pages)

**Loggable Concern (`app/Models/Concerns/Loggable.php`)**
- Apply to Connection model for automatic activity logging
- Tracks created, updated, deleted events with old/new values

## Out of Scope
- Visual representation of connections on rack elevation view
- Cable manufacturer or part number tracking
- Automatic patch panel port pairing by position
- Multiple connections per port (daisy-chaining)
- Cable inventory management separate from connections
- Bulk connection creation via import
- Connection diagrams or network topology visualization
- Connection testing or verification workflows
- Historical connection analytics or reporting
- Integration with network monitoring systems
