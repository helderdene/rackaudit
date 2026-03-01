# Specification: Asset/Device Management

## Goal
Enable full lifecycle management of datacenter devices/assets with flexible hardware specifications, physical dimension tracking, warranty information, and auto-generated asset tags, allowing devices to be placed within racks via the existing elevation system.

## User Stories
- As a datacenter operator, I want to track all physical devices with their specifications, serial numbers, and warranty information so that I can maintain accurate inventory and plan for replacements
- As an IT manager, I want to configure custom device types so that I can categorize equipment according to our organization's needs

## Specific Requirements

**Device Type Management**
- Create a separate `device_types` database table for user-configurable device types
- CRUD operations for device types (name, description, default_u_size)
- Examples to seed: Server, Switch, Router, Storage, PDU, UPS, Patch Panel, KVM, Console Server, Blade Chassis
- Device types can be soft-deleted to preserve historical references

**Device/Asset CRUD**
- Create `devices` table with relationships to `device_types` and `racks`
- Required fields: name, device_type_id, asset_tag (auto-generated), lifecycle_status
- Optional fields: serial_number, manufacturer, model, warranty_start_date, warranty_end_date, purchase_date, notes
- Devices can exist without rack placement (unplaced/in-stock inventory)
- Follow existing controller patterns from `RackController` for CRUD operations

**Hardware Specifications (Flexible JSON)**
- Store device-type-specific attributes in a JSON column `specs`
- Examples: CPU count, RAM size, storage capacity, port count, power draw
- No schema validation on JSON - fully flexible key-value pairs
- Display specs as editable key-value pairs in the UI

**Physical Dimensions**
- `u_height` integer field (1-48) for rack units occupied
- `depth` enum: standard, deep, shallow
- `width_type` enum: full, half-left, half-right (matches existing `DeviceWidth` TypeScript type)
- `rack_face` enum: front, rear (matches existing `RackFace` TypeScript type)
- `start_u` nullable integer for rack placement position

**Lifecycle Status Management**
- Create `DeviceLifecycleStatus` enum with fixed states
- States: ordered, received, in_stock, deployed, maintenance, decommissioned, disposed
- Include `label()` method returning human-readable labels following existing enum patterns

**Asset Tag Auto-Generation**
- Format: `ASSET-{YYYYMMDD}-{sequential_number}` (e.g., ASSET-20251225-00001)
- Sequential number resets daily, zero-padded to 5 digits
- Asset tag is immutable after creation
- Ensure uniqueness via database unique constraint

**Warranty Information**
- `purchase_date` nullable date field
- `warranty_start_date` nullable date field
- `warranty_end_date` nullable date field
- No proactive alerts (explicitly out of scope)
- Display warranty status (active/expired/none) in device views

**Rack Integration**
- Devices belong to a rack via `rack_id` foreign key (nullable for unplaced devices)
- Replace placeholder device data in `RackController::getPlaceholderDevices()` with real Device queries
- Update elevation components to work with real Device model data
- Devices inherit datacenter access permissions through Rack > Row > Room > Datacenter hierarchy

## Visual Design
No visual assets provided. Follow existing UI patterns from Racks, Rooms, and PDUs pages:
- Index page with table listing, status badges, and action buttons
- Create/Edit pages with form components matching existing patterns
- Show page with detail cards and related data sections
- Use existing Badge component for lifecycle status display

## Existing Code to Leverage

**RackController and Rack Model**
- Follow the same nested resource controller pattern for device routes
- Use existing `Loggable` trait for activity logging on device changes
- Replicate authorization patterns using Gate and Policy classes
- Reference `getPlaceholderDevices()` method which defines the expected device data structure

**TypeScript Types (resources/js/types/rooms.ts)**
- Extend `PlaceholderDevice` interface for real device data
- Use existing `DeviceWidth` and `RackFace` types for consistency
- Follow `RackData` pattern for device data interface definition

**Enum Patterns (app/Enums/)**
- Follow `RackStatus` enum structure with `label()` method
- Use string-backed enums with lowercase values
- Include PHPDoc blocks matching existing patterns

**Form Request Patterns (app/Http/Requests/)**
- Follow `StoreRackRequest` pattern for device validation
- Include role-based authorization in `authorize()` method
- Use `Rule::enum()` for enum field validation

**Policy Patterns (app/Policies/)**
- Follow `RackPolicy` pattern for device authorization
- Admins/IT Managers have full access; others inherit datacenter access through rack relationship

## Out of Scope
- Software/license tracking for devices
- Port management and network port configurations
- Network configuration storage
- Power monitoring and real-time metrics
- Customer/department ownership tracking
- Warranty expiration alerts and notifications
- Device relationship tracking (parent/child, dependencies)
- Bulk import/export of devices
- QR code or barcode generation for asset tags
- Device movement history logging (beyond standard activity logs)
