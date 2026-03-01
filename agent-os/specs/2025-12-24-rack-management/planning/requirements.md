# Spec Requirements: Rack Management

## Initial Description

Rack Management - CRUD for racks with location, U-height, power capacity, and visual rack elevation diagram component.

## Requirements Discussion

### First Round Questions

**Q1:** I assume racks belong to rows (following the Datacenter > Room > Row > Rack hierarchy shown in the roadmap). Is that correct, or should racks be directly assignable to rooms without requiring a row?
**Answer:** Confirmed - Racks belong to rows (Datacenter > Room > Row > Rack)

**Q2:** For rack identification, I'm thinking we need: name, position within row, U-height (standard options being 42U, 45U, 48U), and optionally a serial number or asset tag. Should we also track manufacturer/model information, or keep it minimal for now?
**Answer:** Confirmed - name, position within row, U-height (42U, 45U, 48U), optional serial number/asset tag. Keep minimal for now (no manufacturer/model).

**Q3:** For power capacity, I assume we track maximum power draw in kW or amps. Should racks reference PDUs that supply them, or just store a numeric capacity value?
**Answer:** Racks reference PDUs that supply them (relationship, not just numeric value)

**Q4:** For rack status, I'm assuming we follow the existing pattern with statuses like Active, Inactive, and Maintenance (similar to rows and PDUs). Is that correct, or do you need additional statuses like "Reserved" or "Decommissioning"?
**Answer:** Confirmed - Active, Inactive, Maintenance (following existing pattern)

**Q5:** The roadmap mentions a "visual rack elevation diagram component" as part of this feature. For the initial implementation, I assume this should be a read-only view showing U positions (1 through N from bottom to top) with placeholders for future device placement. Should this elevation view be on the rack detail page, or a separate dedicated view?
**Answer:** Read-only view showing U positions (1-N from bottom to top) with placeholders for future device placement. Separate dedicated view (not on rack detail page).

**Q6:** For the rack elevation diagram styling, should we follow datacenter industry conventions (dark cabinet background with numbered U slots), or match the existing app's card-based UI aesthetic?
**Answer:** Match existing app's card-based UI aesthetic

**Q7:** Is there anything that should explicitly be OUT of scope for this feature?
**Answer:** Drag-and-drop device placement, power consumption calculations, cable management visualization

### Existing Code to Reference

**Similar Features Identified:**
- Feature: Row Management - Path: `/Users/helderdene/rackaudit/app/Http/Controllers/RowController.php`
- Feature: Row Model - Path: `/Users/helderdene/rackaudit/app/Models/Row.php`
- Feature: Row Pages - Path: `/Users/helderdene/rackaudit/resources/js/Pages/Rows/`
- Feature: PDU Management (for many-to-many relationship pattern) - Path: `/Users/helderdene/rackaudit/app/Models/Pdu.php`
- Components to potentially reuse: Card, Badge, Button, Table patterns from existing pages
- Backend logic to reference: Nested resource controllers, Form Requests, Policies, Enums with label() methods

### Follow-up Questions

**Follow-up 1:** You mentioned racks should reference PDUs that supply them. Should a rack be able to connect to multiple PDUs (for redundant power, e.g., A-side and B-side feeds), or is a single PDU reference sufficient? Also, should this be a required field or optional when creating a rack?
**Answer:** Multiple PDUs (for redundant power, A-side/B-side feeds). Optional when creating a rack.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A - No visual files were provided for this specification.

## Requirements Summary

### Functional Requirements

**Rack CRUD Operations:**
- Create, read, update, and delete racks within the row context
- Racks belong to rows following hierarchy: Datacenter > Room > Row > Rack
- List view showing all racks in a row with search/filter capability
- Detail view showing rack information and associated PDUs

**Rack Data Model:**
- Name (required)
- Position within row (required, integer)
- U-height (required, options: 42U, 45U, 48U)
- Serial number / asset tag (optional)
- Status (required, enum: Active, Inactive, Maintenance)
- PDU relationships (optional, many-to-many for redundant power)

**Rack Elevation Diagram:**
- Separate dedicated view accessible from rack detail page
- Read-only visual representation of rack U positions
- Display U positions numbered 1 to N (bottom to top)
- Show placeholder slots for future device placement
- Match existing app's card-based UI aesthetic
- Responsive design following existing patterns

**PDU Relationship:**
- Many-to-many relationship between racks and PDUs
- Support for A-side/B-side redundant power configurations
- Optional assignment (racks can exist without PDU assignment)
- Display assigned PDUs on rack detail page

### Reusability Opportunities

- Row management patterns (nested resource controller, model, pages)
- Existing enum pattern with label() method for RackStatus
- Card, Badge, Button UI components from existing pages
- Table layout patterns from Rooms/Show.vue and Rows pages
- Form Request validation patterns from existing requests
- Policy authorization patterns from existing policies
- Loggable concern for activity tracking

### Scope Boundaries

**In Scope:**
- Rack CRUD operations (create, read, update, delete)
- Rack data model with name, position, U-height, serial number, status
- Many-to-many PDU relationship (optional, supports multiple PDUs)
- Rack list view within row context
- Rack detail view showing rack information and assigned PDUs
- Separate rack elevation diagram view (read-only)
- Visual U-position display (1 to N, bottom to top)
- Placeholder slots for future device placement
- RackStatus enum (Active, Inactive, Maintenance)
- Activity logging for rack changes
- Authorization/policies following existing patterns

**Out of Scope:**
- Drag-and-drop device placement in elevation view
- Power consumption calculations or monitoring
- Cable management visualization
- Device management (separate roadmap item #11)
- Device placement in racks (separate roadmap item #12)
- Manufacturer/model tracking for racks
- QR code generation (separate roadmap item #16)

### Technical Considerations

- Follow nested resource routing pattern: `datacenters.rooms.rows.racks`
- Create pivot table `pdu_rack` for many-to-many PDU relationship
- Use existing Loggable concern for activity tracking
- Create RackStatus enum following RowStatus pattern
- Create RackUHeight enum for U-height options (42, 45, 48)
- Form Request classes for validation (StoreRackRequest, UpdateRackRequest)
- Policy class for authorization (RackPolicy)
- Factory and seeder for testing
- Wayfinder integration for type-safe frontend routing
- Vue components following existing patterns (Card-based layout)
- Elevation view as separate route: `datacenters.rooms.rows.racks.elevation`
