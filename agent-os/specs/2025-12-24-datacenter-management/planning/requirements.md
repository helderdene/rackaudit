# Spec Requirements: Datacenter Management

## Initial Description
Datacenter Management - CRUD for datacenters with name, location, contact information, and floor plan visualization placeholder

## Requirements Discussion

### First Round Questions

**Q1:** I assume the location information should include structured fields (address line 1, address line 2, city, state/province, postal code, country) rather than a single free-text field. Is that correct, or would you prefer a simpler approach with just a single location text field?
**Answer:** Correct - use structured fields (address line 1, address line 2, city, state/province, postal code, country)

**Q2:** For contact information, I'm thinking we should capture: primary contact name, email, phone number, and optionally a secondary/emergency contact. Should we also include fields like company/organization name, or is this simpler contact structure sufficient?
**Answer:** Correct - include primary contact name, email, phone number, secondary/emergency contact, AND also include company/organization name

**Q3:** I assume users should be able to see which datacenters they have access to based on the existing User-Datacenter relationship. Should we also show datacenter-level statistics on the index/list page (like number of rooms, racks, total U capacity) as placeholders that will be populated when those features are built?
**Answer:** Correct - show placeholder statistics on list page

**Q4:** For the floor plan visualization placeholder, I'm assuming this should be a designated area on the datacenter detail/show page that displays a message like "Floor plan coming soon" or "Upload floor plan" with the actual visualization deferred to a future spec. Should this placeholder support uploading a static image (PNG/PDF) of the floor plan as an interim solution, or should it remain purely a placeholder with no upload functionality?
**Answer:** Correct - support uploading static image (PNG/PDF) as interim solution

**Q5:** I assume the CRUD interface should follow the same patterns as the existing User Management module (Index with data table, Create form, Edit form). Should we also include a dedicated "Show/View" page for viewing datacenter details in read-only mode, or is the Edit page sufficient for viewing details?
**Answer:** Include a dedicated "Show/View" page for read-only datacenter details

**Q6:** For access control, I assume Administrators and IT Managers can create/edit/delete datacenters, while Operators, Auditors, and Viewers can only view datacenters they have access to. Does this match your expectations, or should there be different permission levels?
**Answer:** Correct - Administrators and IT Managers can CRUD, others can only view assigned datacenters

**Q7:** Is there anything that should explicitly be excluded from this initial Datacenter Management feature?
**Answer:** Exclude room management, rack assignment, and capacity calculations from this initial feature

### Existing Code to Reference

**Similar Features Identified:**
- Feature: User Management - Path: `/Users/helderdene/rackaudit/resources/js/Pages/Users/`
- Feature: UserController - Path: `/Users/helderdene/rackaudit/app/Http/Controllers/UserController.php`
- Feature: Existing Datacenter Model (placeholder) - Path: `/Users/helderdene/rackaudit/app/Models/Datacenter.php`
- Components to potentially reuse: Data table, form components, modal dialogs from User Management module
- Backend logic to reference: UserController patterns for CRUD operations, Form Request validation

### Follow-up Questions
No follow-up questions required - all requirements are clearly defined.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A - No visual files to analyze.

## Requirements Summary

### Functional Requirements

**Datacenter Entity Fields:**
- Name (required, string)
- Location - Structured address fields:
  - Address Line 1 (required)
  - Address Line 2 (optional)
  - City (required)
  - State/Province (required)
  - Postal Code (required)
  - Country (required)
- Contact Information:
  - Company/Organization Name (optional)
  - Primary Contact Name (required)
  - Primary Contact Email (required)
  - Primary Contact Phone (required)
  - Secondary/Emergency Contact Name (optional)
  - Secondary/Emergency Contact Email (optional)
  - Secondary/Emergency Contact Phone (optional)
- Floor Plan Image (optional, PNG/PDF upload)

**CRUD Operations:**
- Create: Form to add new datacenter with all fields
- Read (Index): List view with data table showing datacenters user has access to
- Read (Show): Dedicated read-only detail page for viewing datacenter information
- Update: Form to edit existing datacenter details
- Delete: Ability to remove datacenter (with appropriate confirmation)

**List Page Features:**
- Data table with datacenter name, location summary, primary contact
- Placeholder statistics columns (rooms count, racks count, U capacity) showing "0" or "-" until related features are built
- Filter/search functionality
- Pagination

**Detail/Show Page Features:**
- All datacenter information in read-only format
- Floor plan display area (shows uploaded image if present, or upload prompt if not)
- Action buttons for Edit/Delete (for authorized users)

**Floor Plan Placeholder:**
- Upload functionality for static images (PNG, JPG, PDF)
- Display uploaded floor plan image on datacenter detail page
- Ability to replace/remove uploaded floor plan
- Note: Interactive floor plan visualization deferred to future feature

### Reusability Opportunities
- User Management module patterns (Index.vue, Create.vue, Edit.vue, Show.vue)
- UserController CRUD patterns
- Existing form components and data table components
- Activity logging via Loggable trait (already present in Datacenter model)
- Existing User-Datacenter relationship for access control

### Scope Boundaries

**In Scope:**
- Datacenter CRUD (Create, Read, Update, Delete)
- Structured location fields (address, city, state, postal, country)
- Contact information (company, primary contact, secondary contact)
- Floor plan static image upload (PNG/JPG/PDF)
- Index page with data table and placeholder statistics
- Dedicated Show/View page for read-only details
- Access control (Admins/IT Managers can CRUD, others view only)
- Activity logging for all datacenter changes

**Out of Scope:**
- Room management within datacenters
- Rack assignment to datacenters/rooms
- Capacity calculations (power, U-space, etc.)
- Interactive floor plan visualization/editing
- Floor plan with clickable room/rack placement
- Bulk import/export of datacenters
- Real-time collaboration features

### Technical Considerations
- Extend existing Datacenter model (currently has only `name` field)
- Create migration to add new fields to datacenters table
- Follow Laravel 12 conventions and project patterns
- Use Inertia.js v2 with Vue 3 for frontend
- Use Tailwind CSS 4 for styling
- Implement Form Request validation classes
- Use Laravel Storage for floor plan file uploads
- Leverage existing User-Datacenter pivot table for access control
- Follow UserController patterns for authorization checks
- Use Spatie Laravel-Permission for role-based access control
