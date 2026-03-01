# Specification: Datacenter Management

## Goal
Implement a full CRUD interface for managing datacenters with structured location fields, contact information, and floor plan image upload capability, following the established User Management patterns.

## User Stories
- As an Administrator or IT Manager, I want to create and manage datacenters so that I can organize physical infrastructure locations
- As a user with datacenter access, I want to view datacenter details including location, contacts, and floor plans so that I can reference this information when needed

## Specific Requirements

**Datacenter Entity Fields**
- Name (required, string, max 255 characters)
- Location fields: address_line_1 (required), address_line_2 (optional), city (required), state_province (required), postal_code (required), country (required)
- Contact fields: company_name (optional), primary_contact_name (required), primary_contact_email (required), primary_contact_phone (required)
- Secondary contact fields: secondary_contact_name, secondary_contact_email, secondary_contact_phone (all optional)
- Floor plan image field: floor_plan_path (nullable string, stores file path)

**Database Migration**
- Create migration to add new columns to existing `datacenters` table
- Use appropriate column types: string for text fields, nullable for optional fields
- Do not modify the existing `id`, `name`, `timestamps` columns
- Add index on `name` column for search optimization

**DatacenterController CRUD**
- Follow `UserController` patterns for index, create, store, show, edit, update, destroy methods
- Index: paginated list with search/filter, return Inertia response with datacenter data
- Create/Edit: return Inertia response with form data
- Store/Update: use Form Request validation classes, handle file uploads
- Show: dedicated read-only view with all datacenter details
- Destroy: soft delete or hard delete with confirmation

**Index Page with Data Table**
- Display columns: Name, Location (city, country summary), Primary Contact, Rooms (placeholder "-"), Racks (placeholder "-"), Actions
- Search by name, city, or contact name
- Pagination following Users/Index.vue pattern (15 per page)
- Link to Show, Edit pages; Delete action with confirmation dialog

**Show Page (Read-only Detail View)**
- Display all datacenter information in organized sections: Basic Info, Location, Primary Contact, Secondary Contact
- Floor plan display area: show uploaded image or upload prompt placeholder
- Action buttons for Edit and Delete (visible only to authorized users)
- Breadcrumb navigation back to Index

**Floor Plan Upload**
- Accept PNG, JPG, JPEG, PDF file types
- Max file size: 10MB
- Store files using Laravel Storage (public disk, `floor-plans` directory)
- Display uploaded image on Show page with option to replace or remove
- Generate unique filename using datacenter ID and timestamp

**Access Control / Permissions**
- Administrators and IT Managers: full CRUD access to all datacenters
- Operators, Auditors, Viewers: read-only access to assigned datacenters only
- Use existing User-Datacenter pivot table relationship for access filtering
- Implement authorization in controller methods and Form Request `authorize()` methods

**Form Validation**
- Create `StoreDatacenterRequest` and `UpdateDatacenterRequest` Form Request classes
- Follow `StoreUserRequest` patterns for validation rules and custom messages
- Validate required fields, email format, phone format, file type/size

**Activity Logging**
- Leverage existing `Loggable` trait already present on Datacenter model
- Log create, update, delete actions with actor information

## Existing Code to Leverage

**UserController.php (`/Users/helderdene/rackaudit/app/Http/Controllers/UserController.php`)**
- Follow CRUD method structure: index with search/filters, create, store, edit, update, destroy
- Use same Inertia::render patterns and pagination approach (through() transformer)
- Reference authorization patterns for role-based access control
- Use same redirect with success message pattern

**Users/Index.vue (`/Users/helderdene/rackaudit/resources/js/Pages/Users/Index.vue`)**
- Reuse data table structure with search input and filter dropdowns
- Follow pagination component pattern with page navigation
- Use same TypeScript interface patterns for Props and data structures
- Import and use existing UI components: Button, Input, Checkbox, Skeleton

**UserForm.vue (`/Users/helderdene/rackaudit/resources/js/components/users/UserForm.vue`)**
- Follow Form component usage with v-slot for errors and processing state
- Reuse form field layout with Label, Input, InputError components
- Reference pattern for mode-based form (create vs edit)
- Use same hidden field approach for method override (PUT)

**StoreUserRequest.php (`/Users/helderdene/rackaudit/app/Http/Requests/StoreUserRequest.php`)**
- Follow validation rules structure with const arrays for valid values
- Reference authorize() method pattern for role-based authorization
- Use same custom error messages pattern

**Datacenter Model (`/Users/helderdene/rackaudit/app/Models/Datacenter.php`)**
- Extend existing model with new fillable fields
- Keep existing users() relationship and Loggable trait
- Add formatted address accessor for display purposes

## Out of Scope
- Room management within datacenters (separate future feature)
- Rack assignment to datacenters or rooms (separate future feature)
- Capacity calculations (power consumption, U-space utilization)
- Interactive floor plan visualization with clickable elements
- Floor plan with room/rack placement and drag-drop functionality
- Bulk import/export of datacenters via CSV/Excel
- Real-time collaboration features for concurrent editing
- Datacenter dashboard with live monitoring data
- Integration with external monitoring systems
- Automatic geocoding of addresses
