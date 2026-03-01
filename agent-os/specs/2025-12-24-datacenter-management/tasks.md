# Task Breakdown: Datacenter Management

## Overview
Total Tasks: 4 Task Groups (approximately 30 sub-tasks)

This feature implements full CRUD functionality for managing datacenters with structured location fields, contact information, and floor plan image upload capability, following established User Management patterns.

## Task List

### Database Layer

#### Task Group 1: Database Schema and Model Updates
**Dependencies:** None
**Complexity:** Medium

- [x] 1.0 Complete database layer for Datacenter entity
  - [x] 1.1 Write 4-6 focused tests for Datacenter model functionality
    - Test required field validation (name, address_line_1, city, state_province, postal_code, country, primary_contact_name, primary_contact_email, primary_contact_phone)
    - Test optional field handling (address_line_2, company_name, secondary contact fields)
    - Test `formattedAddress` accessor returns correct formatted string
    - Test existing `users()` relationship still works after model update
    - Test `floor_plan_path` field accepts and stores file path correctly
  - [x] 1.2 Create migration to add new columns to `datacenters` table
    - Location fields: `address_line_1` (required), `address_line_2` (nullable), `city` (required), `state_province` (required), `postal_code` (required), `country` (required)
    - Contact fields: `company_name` (nullable), `primary_contact_name` (required), `primary_contact_email` (required), `primary_contact_phone` (required)
    - Secondary contact fields: `secondary_contact_name` (nullable), `secondary_contact_email` (nullable), `secondary_contact_phone` (nullable)
    - Floor plan field: `floor_plan_path` (nullable string)
    - Add index on `name` column for search optimization
    - Reference: `/Users/helderdene/rackaudit/database/migrations/2025_12_23_193200_create_datacenters_table.php`
  - [x] 1.3 Update Datacenter model with new fillable fields and accessors
    - Add all new fields to `$fillable` array
    - Create `formattedAddress` accessor for display purposes (combines address fields into readable format)
    - Create `formattedLocation` accessor for summary display (city, country)
    - Keep existing `users()` relationship and `Loggable` trait
    - Reference: `/Users/helderdene/rackaudit/app/Models/Datacenter.php`
  - [x] 1.4 Update DatacenterFactory with realistic fake data
    - Generate faker data for all new fields
    - Create states for datacenters with/without floor plans
    - Create states for datacenters with/without secondary contacts
  - [x] 1.5 Create DatacenterSeeder for development data
    - Seed 5-10 sample datacenters with varied data
    - Include examples with and without optional fields
  - [x] 1.6 Ensure database layer tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify migration runs successfully with `php artisan migrate`
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- Migration adds all required columns to existing `datacenters` table
- Model correctly handles all new fields
- Accessors return properly formatted strings
- Existing User-Datacenter relationship remains functional

---

### Backend Layer

#### Task Group 2: Controller, Routes, and Form Requests
**Dependencies:** Task Group 1
**Complexity:** High

- [x] 2.0 Complete backend API and controller layer
  - [x] 2.1 Write 6-8 focused tests for DatacenterController
    - Test `index` returns paginated list with search filtering
    - Test `index` filters by user access (non-admin sees only assigned datacenters)
    - Test `create` returns form data with Inertia response
    - Test `store` creates datacenter with valid data
    - Test `store` validation rejects invalid data (missing required fields)
    - Test `show` returns datacenter details
    - Test `update` modifies existing datacenter
    - Test `destroy` removes datacenter (with proper authorization check)
  - [x] 2.2 Create `StoreDatacenterRequest` Form Request
    - Authorization: only Administrators and IT Managers can store
    - Validation rules for all required fields
    - Email format validation for contact emails
    - Phone format validation for contact phones
    - File validation for floor_plan: nullable, mimes:png,jpg,jpeg,pdf, max:10240 (10MB)
    - Custom error messages following StoreUserRequest pattern
    - Reference: `/Users/helderdene/rackaudit/app/Http/Requests/StoreUserRequest.php`
  - [x] 2.3 Create `UpdateDatacenterRequest` Form Request
    - Authorization: only Administrators and IT Managers can update
    - Similar validation rules as Store, with appropriate unique rule handling
    - Handle optional floor plan replacement
  - [x] 2.4 Create DatacenterController with full CRUD methods
    - `index()`: Paginated list (15 per page) with search by name/city/contact, filtered by user access
    - `create()`: Return Inertia form page
    - `store()`: Validate, handle file upload, create datacenter, dispatch event
    - `show()`: Return read-only detail view with all datacenter information
    - `edit()`: Return Inertia form page with existing data
    - `update()`: Validate, handle file replacement, update datacenter, dispatch event
    - `destroy()`: Delete datacenter with confirmation, dispatch event
    - Use `through()` transformer pattern for index response
    - Reference: `/Users/helderdene/rackaudit/app/Http/Controllers/UserController.php`
  - [x] 2.5 Implement floor plan file upload handling
    - Store files in `storage/app/public/floor-plans/` directory
    - Generate unique filename: `{datacenter_id}_{timestamp}.{extension}`
    - Handle file replacement (delete old file when uploading new)
    - Handle file removal (delete file when explicitly removed)
    - Create symbolic link if not exists: `php artisan storage:link`
  - [x] 2.6 Register datacenter routes in `routes/web.php`
    - Resource routes: `Route::resource('datacenters', DatacenterController::class)`
    - Apply auth middleware
    - Ensure Wayfinder generates TypeScript actions
  - [x] 2.7 Create DatacenterPolicy for authorization
    - `viewAny()`: All authenticated users
    - `view()`: Admins/IT Managers see all; others see only assigned datacenters
    - `create()`: Only Administrators and IT Managers
    - `update()`: Only Administrators and IT Managers
    - `delete()`: Only Administrators and IT Managers
    - Register policy in `AuthServiceProvider`
  - [x] 2.8 Ensure backend layer tests pass
    - Run ONLY the 6-8 tests written in 2.1
    - Verify all CRUD operations work correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6-8 tests written in 2.1 pass
- All CRUD routes respond correctly
- Form Request validation works as specified
- File upload/replacement/removal functions correctly
- Authorization properly restricts access based on role

---

### Frontend Layer

#### Task Group 3: Vue Components and Pages
**Dependencies:** Task Group 2
**Complexity:** High

- [x] 3.0 Complete frontend UI components and pages
  - [x] 3.1 Write 4-6 focused tests for frontend components
    - Test Index page renders datacenter list with correct columns
    - Test Index page search filters results
    - Test Create form submits with valid data
    - Test Show page displays all datacenter information
    - Test Edit form loads existing data and submits changes
    - Test Delete confirmation dialog works
  - [x] 3.2 Create `Datacenters/Index.vue` page
    - Data table with columns: Name, Location (city, country), Primary Contact, Rooms (-), Racks (-), Actions
    - Search input for name/city/contact filtering
    - Pagination following Users/Index.vue pattern (15 per page)
    - "Create Datacenter" button linking to create page
    - Action buttons: View (Show), Edit, Delete
    - TypeScript interfaces for DatacenterData and Props
    - Reference: `/Users/helderdene/rackaudit/resources/js/Pages/Users/Index.vue`
  - [x] 3.3 Create `Datacenters/Show.vue` page (read-only detail view)
    - Sections: Basic Info, Location, Primary Contact, Secondary Contact (if present)
    - Floor plan display area: show uploaded image or upload prompt placeholder
    - Action buttons for Edit and Delete (visible to authorized users only)
    - Breadcrumb navigation: Datacenters > [Datacenter Name]
    - Responsive layout with clear visual hierarchy
  - [x] 3.4 Create `Datacenters/Create.vue` page
    - Include DatacenterForm component in create mode
    - Breadcrumb navigation: Datacenters > Create
    - Page heading with description
  - [x] 3.5 Create `Datacenters/Edit.vue` page
    - Include DatacenterForm component in edit mode
    - Pre-populate form with existing datacenter data
    - Breadcrumb navigation: Datacenters > Edit [Name]
    - Page heading with description
  - [x] 3.6 Create `components/datacenters/DatacenterForm.vue` component
    - Form fields organized in sections:
      - Basic Info: Name
      - Location: Address Line 1, Address Line 2, City, State/Province, Postal Code, Country
      - Primary Contact: Company Name, Name, Email, Phone
      - Secondary Contact: Name, Email, Phone (optional section)
      - Floor Plan: File upload input
    - Mode-based behavior (create vs edit)
    - Use Form component from Inertia with v-slot for errors/processing
    - Use existing UI components: Input, Label, InputError, Button
    - Reference: `/Users/helderdene/rackaudit/resources/js/components/users/UserForm.vue`
  - [x] 3.7 Create `components/datacenters/DeleteDatacenterDialog.vue` component
    - Confirmation dialog before deletion
    - Display datacenter name in confirmation message
    - Disable delete button during processing
    - Reference: `/Users/helderdene/rackaudit/resources/js/components/users/DeleteUserDialog.vue`
  - [x] 3.8 Create `components/datacenters/FloorPlanUpload.vue` component
    - File input accepting PNG, JPG, JPEG, PDF
    - Display current floor plan image if exists
    - Preview selected file before upload
    - Remove/replace functionality
    - Display file size validation error (max 10MB)
  - [x] 3.9 Add datacenter link to navigation
    - Add "Datacenters" link to main navigation menu
    - Position appropriately in navigation hierarchy
  - [x] 3.10 Apply styling and responsive design
    - Follow existing design system and Tailwind CSS patterns
    - Mobile: 320px - 768px (stacked form layout)
    - Tablet: 768px - 1024px (two-column layout where appropriate)
    - Desktop: 1024px+ (full layout with sidebar/sections)
    - Dark mode support matching existing components
  - [x] 3.11 Ensure frontend tests pass
    - Run ONLY the 4-6 tests written in 3.1
    - Verify all pages render and function correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 3.1 pass
- All pages (Index, Show, Create, Edit) render correctly
- Forms validate and submit properly
- Floor plan upload/display works
- Responsive design functions across screen sizes
- Navigation includes datacenter link

---

### Testing Layer

#### Task Group 4: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-3
**Complexity:** Medium

- [x] 4.0 Review existing tests and fill critical gaps
  - [x] 4.1 Review tests from Task Groups 1-3
    - Review the 6 model tests from Task 1.1 (DatacenterModelTest.php)
    - Review the 8 controller tests from Task 2.1 (DatacenterControllerTest.php)
    - Review the 6 frontend tests from Task 3.1 (DatacenterFrontendTest.php)
    - Total existing tests: 20 tests
  - [x] 4.2 Analyze test coverage gaps for Datacenter Management feature
    - Identified critical user workflows lacking coverage
    - Focused on integration points (authorization, file handling)
    - Prioritized end-to-end flows over edge cases
    - Did NOT assess entire application coverage
  - [x] 4.3 Write up to 10 additional strategic tests if needed
    - End-to-end test: Create datacenter with floor plan upload
    - End-to-end test: Edit datacenter and replace floor plan
    - Authorization test: Non-admin cannot access create page
    - Authorization test: Non-admin cannot access edit page
    - Validation test: File type rejection for floor plan
    - Validation test: File size rejection for floor plan (over 10MB)
    - Floor plan removal functionality test
    - IT Manager role full CRUD access test
    - Non-admin cannot view unassigned datacenter show page
    - Floor plan accepts all valid file types (PNG, JPG, JPEG, PDF)
    - Total: 10 new tests added in DatacenterStrategicTest.php
  - [x] 4.4 Run feature-specific tests only
    - Ran all Datacenter-related tests (from 1.1, 2.1, 3.1, and 4.3)
    - Total: 36 tests passed (406 assertions)
    - Did NOT run entire application test suite
    - All critical workflows verified

**Acceptance Criteria:**
- All feature-specific tests pass (36 tests total)
- Critical user workflows are covered
- Authorization scenarios are tested
- File upload functionality is tested
- 10 additional tests added (within limit)

---

## Execution Order

Recommended implementation sequence:

1. **Database Layer (Task Group 1)** - Foundation for all other work
   - Migration must run first
   - Model updates depend on migration
   - Factory/Seeder for development testing

2. **Backend Layer (Task Group 2)** - API and business logic
   - Depends on model being complete
   - Form Requests for validation
   - Controller for all CRUD operations
   - File upload handling
   - Policy for authorization

3. **Frontend Layer (Task Group 3)** - User interface
   - Depends on API endpoints being available
   - Pages for all CRUD operations
   - Form component for create/edit
   - File upload component
   - Navigation integration

4. **Testing Layer (Task Group 4)** - Quality assurance
   - Depends on all features being implemented
   - Review and gap analysis
   - Additional strategic tests
   - Final verification

---

## Reference Files

**Existing Patterns to Follow:**
- Controller: `/Users/helderdene/rackaudit/app/Http/Controllers/UserController.php`
- Form Request: `/Users/helderdene/rackaudit/app/Http/Requests/StoreUserRequest.php`
- Index Page: `/Users/helderdene/rackaudit/resources/js/Pages/Users/Index.vue`
- Form Component: `/Users/helderdene/rackaudit/resources/js/components/users/UserForm.vue`
- Delete Dialog: `/Users/helderdene/rackaudit/resources/js/components/users/DeleteUserDialog.vue`
- Model: `/Users/helderdene/rackaudit/app/Models/Datacenter.php`
- Migration: `/Users/helderdene/rackaudit/database/migrations/2025_12_23_193200_create_datacenters_table.php`

**Tech Stack:**
- Laravel 12 with PHP 8.4
- Vue 3 with TypeScript
- Inertia.js v2
- Tailwind CSS 4
- Pest for testing
- Laravel Wayfinder for route generation

---

## Notes

- Floor plan visualization is limited to static image display in this feature
- Room/rack statistics columns show placeholders ("-") until those features are implemented
- Activity logging leverages existing Loggable trait on Datacenter model
- User-Datacenter relationship already exists for access control filtering
