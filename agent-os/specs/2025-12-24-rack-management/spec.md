# Specification: Rack Management

## Goal
Enable full CRUD management of racks within the datacenter hierarchy (Datacenter > Room > Row > Rack), including a visual rack elevation diagram showing U-positions for future device placement.

## User Stories
- As an IT Manager, I want to create and manage racks within rows so that I can organize physical server equipment locations
- As an Operator, I want to view rack details and assigned PDUs so that I can understand power distribution to each rack
- As an Auditor, I want to see a visual elevation diagram of rack U-positions so that I can plan and document device placements

## Specific Requirements

**Rack Model and Database**
- Create `racks` table with: id, name, position (integer), u_height (integer), serial_number (nullable), status (enum), row_id (foreign key), timestamps
- Create `pdu_rack` pivot table for many-to-many PDU relationship: pdu_id, rack_id, timestamps
- Rack belongs to Row; Row has many Racks
- Use `RackStatus` enum with cases: Active, Inactive, Maintenance (follows existing pattern)
- Use `RackUHeight` enum with cases: 42, 45, 48 (standard rack heights)
- Include Loggable concern for activity tracking

**Rack CRUD Controller**
- Follow `RowController` pattern for nested resource controller under `datacenters.rooms.rows.racks`
- Methods: index, create, store, show, edit, update, destroy
- Calculate next position automatically in create method
- Pass PDU options for multi-select on create/edit (all PDUs from the same row or room)
- Handle PDU sync on store/update using many-to-many relationship
- On destroy, detach PDU relationships (do not delete PDUs)

**Rack List View (Index)**
- Table displaying: Position, Name, U-Height, PDU Count, Status, Actions
- Actions column: Edit button, Delete dialog
- Add Rack button for authorized users (Administrator, IT Manager)
- Back to Row button for navigation
- Follow `Rows/Index.vue` table layout pattern

**Rack Detail View (Show)**
- Card-based layout matching `Rows/Show.vue` pattern
- Rack Details card: Name, Position, U-Height, Serial Number, Status, Created date
- Assigned PDUs card: Table listing connected PDUs with name, model, capacity, status
- Link to Elevation View (separate page)
- Edit and Delete buttons for authorized users

**Rack Form Component**
- Shared form component for create/edit modes following `RowForm.vue` pattern
- Fields: name (required), position (required integer), u_height (required select), serial_number (optional), status (required select)
- Multi-select for PDU assignment (optional, supports multiple selections)
- Cancel returns to Row show page

**Rack Elevation View**
- Separate dedicated route: `datacenters.rooms.rows.racks.elevation`
- Display vertical representation of U-positions numbered 1 to N (bottom to top)
- Card-based UI aesthetic matching app design (not dark cabinet style)
- Each U slot displayed as a card/row element with U number label
- All slots show as empty/placeholder for future device placement
- Read-only view (no interaction with slots)
- Rack name and details shown in header

**Form Request Validation**
- Create `StoreRackRequest` and `UpdateRackRequest` following `StoreRowRequest` pattern
- Validation rules: name (required, string, max:255), position (required, integer, min:0), u_height (required, enum), serial_number (nullable, string, max:255), status (required, enum)
- PDU validation: array of existing PDU IDs, nullable
- Authorization: Administrator and IT Manager roles only

**Policy Authorization**
- Create `RackPolicy` following `RowPolicy` pattern
- viewAny: all authenticated users
- view: Admins/IT Managers always; others check parent Row's Room's Datacenter access
- create/update/delete: Administrator and IT Manager only

## Visual Design
No visual assets were provided. Follow existing app patterns:

**List/Index Views**
- Use existing table pattern with rounded border, muted header background
- Status displayed as Badge component with variant based on status value
- Actions in last column with Edit button and DeleteDialog component

**Detail/Show Views**
- Card component with CardHeader and CardContent
- Grid layout for details (sm:grid-cols-2 lg:grid-cols-4)
- Lucide icons in CardTitle (use Server icon for rack, Zap for PDUs)

**Elevation View**
- Full-width card container
- Vertical stack of U-slot cards numbered 1-N from bottom to top
- Each slot: small card with U number, empty state styling
- Subtle borders and muted background for empty slots

## Existing Code to Leverage

**RowController.php (app/Http/Controllers/RowController.php)**
- Nested resource controller pattern with datacenter, room, row parameters
- Gate::authorize calls for policy checks
- Enum options mapping for frontend dropdowns
- Index, show, create, edit methods return Inertia::render with consistent prop structure

**Row Model (app/Models/Row.php)**
- Loggable concern usage
- Enum casting pattern for status
- BelongsTo/HasMany relationship patterns
- Fillable array structure

**Rows/Show.vue (resources/js/Pages/Rows/Show.vue)**
- Breadcrumb construction with Wayfinder controller imports
- Card/CardHeader/CardContent layout pattern
- Table for related items (PDUs) with status Badge
- getStatusVariant function for badge styling

**RowForm.vue (resources/js/components/rows/RowForm.vue)**
- Form component with mode prop (create/edit)
- Computed formAction for dynamic action URL
- Wayfinder URL generation
- Select dropdown styling classes

**TypeScript Types (resources/js/types/rooms.ts)**
- Interface definitions for data structures
- Reference, Data, and Option interface patterns

## Out of Scope
- Drag-and-drop device placement in elevation view
- Power consumption calculations or monitoring
- Cable management visualization
- Device management (separate roadmap item)
- Device placement in racks (separate roadmap item)
- Manufacturer/model tracking for racks
- QR code generation (separate roadmap item)
- Interactive U-slot selection or assignment
- Power capacity aggregation from PDUs
- Rack search/filter functionality on index page
