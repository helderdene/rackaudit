# Task Breakdown: Global Search Functionality

## Overview
Total Tasks: 38

This feature implements a global search accessible from the header navigation, allowing users to search across datacenters, racks, devices, ports, and connections with real-time results, hierarchical filtering, and RBAC-compliant result visibility.

## Task List

### Backend Layer

#### Task Group 1: Search Service Foundation
**Dependencies:** None

- [x] 1.0 Complete search service foundation
  - [x] 1.1 Write 4-6 focused tests for SearchService functionality
    - Test basic text search across single entity type
    - Test RBAC filtering for non-admin users
    - Test multi-entity unified search
    - Test empty search query handling
    - Test search term highlighting logic
  - [x] 1.2 Create SearchService class
    - Location: `app/Services/SearchService.php`
    - Methods: `search()`, `quickSearch()`, `searchByEntityType()`
    - Accept search query string and optional filters
    - Return structured results with entity type groupings
  - [x] 1.3 Implement entity-specific search methods
    - `searchDatacenters()`: name, city, country, company_name, primary_contact_name, secondary_contact_name
    - `searchRacks()`: name, serial_number
    - `searchDevices()`: name, asset_tag, serial_number, manufacturer, model
    - `searchPorts()`: label
    - `searchConnections()`: cable_color, path_notes, plus connected device/port names via relationships
  - [x] 1.4 Add RBAC permission filtering
    - Leverage DatacenterPolicy.php view() method logic
    - Use user->datacenters() relationship for non-admin users
    - Administrators and IT Managers see all results
    - Filter all entity results based on accessible datacenter IDs
  - [x] 1.5 Implement search result formatting
    - Build breadcrumb-style location context for each result
    - Format: "Entity-Name > Rack > Row > Room > Datacenter"
    - Include matched field information for highlighting
  - [x] 1.6 Ensure search service tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify RBAC filtering works correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- SearchService returns properly structured results
- RBAC filtering correctly limits results by user permissions
- Breadcrumb context is included for all results

---

#### Task Group 2: Search API Endpoints
**Dependencies:** Task Group 1

- [x] 2.0 Complete search API layer
  - [x] 2.1 Write 4-6 focused tests for search API endpoints
    - Test quick search endpoint returns limited results per entity type
    - Test full search endpoint with pagination
    - Test search with hierarchical filters (datacenter, room, row, rack)
    - Test search with entity-specific attribute filters
    - Test authentication required for search endpoints
  - [x] 2.2 Create SearchController
    - Location: `app/Http/Controllers/SearchController.php`
    - Actions: `quickSearch()`, `search()`
    - Follow existing controller patterns in codebase
  - [x] 2.3 Implement quick search endpoint
    - Route: `GET /api/search/quick` with query parameter `q`
    - Return maximum 3-5 results per entity type
    - Use for dropdown/typeahead results
    - Apply 300ms debounce expectation (frontend handles debounce)
  - [x] 2.4 Implement full search endpoint
    - Route: `GET /api/search` with query parameters
    - Parameters: `q` (query), `type` (entity type filter), `datacenter_id`, `room_id`, `row_id`, `rack_id`
    - Entity-specific filters: `lifecycle_status`, `port_type`, `port_status`, `rack_status`
    - Return paginated results (15-20 per page per entity type)
  - [x] 2.5 Create SearchRequest form request class
    - Location: `app/Http/Requests/SearchRequest.php`
    - Validate search query, filter parameters
    - Validate enum values for entity-specific filters (DeviceLifecycleStatus, PortType, PortStatus, RackStatus)
  - [x] 2.6 Add routes to routes/web.php or routes/api.php
    - Register search routes with appropriate middleware
    - Apply auth middleware for RBAC enforcement
  - [x] 2.7 Ensure search API tests pass
    - Run ONLY the 4-6 tests written in 2.1
    - Verify endpoints return correct response structure
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 2.1 pass
- Quick search returns limited results for dropdown
- Full search returns paginated results with all filter options
- Proper authorization enforced on all endpoints

---

#### Task Group 3: Search Results Page Controller
**Dependencies:** Task Group 2

- [x] 3.0 Complete search results page backend
  - [x] 3.1 Write 3-4 focused tests for search results page
    - Test Inertia page renders with search results
    - Test filter options are passed to frontend
    - Test query parameter persistence
  - [x] 3.2 Create search results Inertia controller action
    - Route: `GET /search` with query parameter `?q=searchterm`
    - Return Inertia::render('Search/Index', [...])
    - Pass search results, filter options, and current filters to frontend
  - [x] 3.3 Implement filter options loading
    - Load accessible datacenters for filter dropdown
    - Dynamically load rooms/rows/racks based on parent selection
    - Include enum values for entity-specific filters
  - [x] 3.4 Ensure search results page tests pass
    - Run ONLY the 3-4 tests written in 3.1
    - Verify Inertia page data structure is correct
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 3-4 tests written in 3.1 pass
- Search results page renders with correct data
- Filter options are properly loaded and passed to frontend

---

### Frontend Layer

#### Task Group 4: Global Search Input Component
**Dependencies:** Task Group 2

- [x] 4.0 Complete global search input component
  - [x] 4.1 Write 4-6 focused tests for GlobalSearch component
    - Test search input renders in header
    - Test debounced search triggers API call
    - Test keyboard shortcut (Cmd/Ctrl + K) focuses input
    - Test Escape key clears input and closes dropdown
    - Test dropdown displays grouped results
  - [x] 4.2 Create GlobalSearch.vue component
    - Location: `resources/js/Components/GlobalSearch.vue`
    - Include search input with Search icon from lucide-vue-next
    - Use existing debounce utility from `@/lib/utils` (300ms)
    - Integrate with quick search API endpoint
  - [x] 4.3 Implement search dropdown
    - Display max 3-5 results per entity type
    - Group results with section headers (Datacenters, Racks, Devices, Ports, Connections)
    - Show breadcrumb-style location context for each result
    - Highlight matched search terms using `<mark>` styling
    - Include "View all results" link at dropdown bottom
  - [x] 4.4 Add keyboard navigation
    - Arrow keys to navigate results
    - Enter to select/navigate to result
    - Escape to close dropdown and clear input
    - Cmd/Ctrl + K global shortcut to focus search input
  - [x] 4.5 Integrate into AppHeader.vue
    - Replace or enhance existing Search icon button
    - Match existing header styling with Tailwind classes
    - Ensure responsive behavior for mobile/desktop
  - [x] 4.6 Add loading and empty states
    - Display Spinner component during search
    - Show empty state message when no results found
  - [x] 4.7 Ensure GlobalSearch component tests pass
    - Run ONLY the 4-6 tests written in 4.1
    - Verify component renders and interacts correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 4.1 pass
- Search input is accessible from header on all pages
- Dropdown shows grouped results with proper styling
- Keyboard navigation works correctly
- Loading and empty states display appropriately

---

#### Task Group 5: Search Results Page UI
**Dependencies:** Task Groups 3, 4

- [x] 5.0 Complete search results page UI
  - [x] 5.1 Write 4-6 focused tests for Search/Index page
    - Test page renders with search results
    - Test entity type filter tabs work correctly
    - Test hierarchical filter dropdowns cascade properly
    - Test pagination navigation
    - Test empty results state displays
  - [x] 5.2 Create Search/Index.vue page component
    - Location: `resources/js/Pages/Search/Index.vue`
    - Display paginated results grouped by entity type
    - Include expandable sections per entity type
    - Show result count in section headers
  - [x] 5.3 Implement entity type filter tabs/buttons
    - Allow filtering to show only specific entity types
    - Highlight active filter selection
    - Update results when filter changes
  - [x] 5.4 Implement hierarchical location filters
    - Create cascading filter dropdowns: Datacenter > Room > Row > Rack
    - Follow pattern from CapacityFilters.vue and DiscrepancyFilters.vue
    - Reset child filters when parent filter changes
    - Load filter options dynamically based on parent selection
  - [x] 5.5 Implement entity-specific attribute filters
    - Device filter: lifecycle_status dropdown (DeviceLifecycleStatus enum values)
    - Port filters: type (PortType enum), status (PortStatus enum)
    - Rack filter: status (RackStatus enum)
    - Show filters only when relevant entity type is visible
  - [x] 5.6 Implement search result cards
    - Display entity name with highlighted matched terms
    - Show breadcrumb-style location context
    - Include relevant metadata per entity type
    - Link to entity detail page on click
  - [x] 5.7 Add pagination component
    - Use Laravel's built-in pagination (15-20 results per page per entity type)
    - Maintain filter state during pagination
  - [x] 5.8 Implement responsive design
    - Mobile: Collapsible filter panel (follow CapacityFilters.vue pattern)
    - Desktop: Inline filter layout
    - Match existing UI patterns and Tailwind classes
  - [x] 5.9 Ensure search results page tests pass
    - Run ONLY the 4-6 tests written in 5.1
    - Verify page renders and filters work correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 5.1 pass
- Results page displays grouped, paginated results
- All filter types work correctly (hierarchical, entity-specific)
- Responsive layout matches existing patterns
- Empty state displays when no results found

---

### Integration Layer

#### Task Group 6: Connection-Specific Search
**Dependencies:** Task Groups 1, 2

- [x] 6.0 Complete connection-specific search functionality
  - [x] 6.1 Write 3-4 focused tests for connection search
    - Test searching connections by source device name
    - Test searching connections by destination device name
    - Test searching connections by port label
    - Test "connections between X and Y" query pattern
  - [x] 6.2 Implement connection relationship traversal
    - Traverse Port > Device > Rack relationships for location context
    - Include source and destination device/rack names in searchable fields
    - Support queries like "connections between Server-01 and Switch-A"
  - [x] 6.3 Add connection result formatting
    - Display source and destination endpoints
    - Show cable_color and path_notes
    - Include location context for both endpoints
  - [x] 6.4 Ensure connection search tests pass
    - Run ONLY the 3-4 tests written in 6.1
    - Verify connection search returns accurate results
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 3-4 tests written in 6.1 pass
- Connection search finds connections by device/rack names
- "Between X and Y" query pattern works correctly
- Connection results show complete endpoint information

---

### Testing Layer

#### Task Group 7: Test Review & Gap Analysis
**Dependencies:** Task Groups 1-6

- [x] 7.0 Review existing tests and fill critical gaps only
  - [x] 7.1 Review tests from Task Groups 1-6
    - Review the 4-6 tests from SearchService (Task 1.1)
    - Review the 4-6 tests from Search API (Task 2.1)
    - Review the 3-4 tests from Search Results Page backend (Task 3.1)
    - Review the 4-6 tests from GlobalSearch component (Task 4.1)
    - Review the 4-6 tests from Search/Index page (Task 5.1)
    - Review the 3-4 tests from Connection Search (Task 6.1)
    - Total existing tests: approximately 22-32 tests
  - [x] 7.2 Analyze test coverage gaps for search functionality only
    - Identify critical user workflows lacking test coverage
    - Focus ONLY on gaps related to search feature requirements
    - Prioritize end-to-end workflows over unit test gaps
    - Do NOT assess entire application test coverage
  - [x] 7.3 Write up to 10 additional strategic tests maximum
    - Add maximum of 10 new tests to fill identified critical gaps
    - Focus on integration points:
      - Full search workflow from input to results page
      - RBAC permission boundary testing
      - Filter combination edge cases
      - Keyboard navigation end-to-end
    - Do NOT write comprehensive coverage for all scenarios
  - [x] 7.4 Run feature-specific tests only
    - Run ONLY tests related to search functionality
    - Expected total: approximately 32-42 tests maximum
    - Do NOT run the entire application test suite
    - Verify critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 32-42 tests total)
- Critical user workflows for search feature are covered
- No more than 10 additional tests added when filling gaps
- Testing focused exclusively on search feature requirements

---

## Execution Order

Recommended implementation sequence:

1. **Backend Foundation** (Task Groups 1-3)
   - Task Group 1: Search Service Foundation - Core search logic and RBAC
   - Task Group 2: Search API Endpoints - API layer for frontend consumption
   - Task Group 3: Search Results Page Controller - Inertia page data

2. **Frontend Components** (Task Groups 4-5)
   - Task Group 4: Global Search Input Component - Header search with dropdown
   - Task Group 5: Search Results Page UI - Full results page with filters

3. **Specialized Features** (Task Group 6)
   - Task Group 6: Connection-Specific Search - Advanced connection search logic

4. **Quality Assurance** (Task Group 7)
   - Task Group 7: Test Review & Gap Analysis - Ensure comprehensive coverage

---

## Key Files to Create/Modify

### New Files
- `app/Services/SearchService.php`
- `app/Http/Controllers/SearchController.php`
- `app/Http/Requests/SearchRequest.php`
- `resources/js/Components/GlobalSearch.vue`
- `resources/js/Pages/Search/Index.vue`
- `tests/Feature/Search/SearchServiceTest.php`
- `tests/Feature/Search/SearchControllerTest.php`
- `tests/Feature/Search/SearchPageTest.php`

### Files to Modify
- `resources/js/Components/AppHeader.vue` - Integrate GlobalSearch component
- `routes/web.php` - Add search page route
- `routes/api.php` - Add search API routes (if using API routes)

---

## Reference Patterns

- **Hierarchical Filters:** Follow `CapacityFilters.vue` and `DiscrepancyFilters.vue` patterns
- **Breadcrumbs:** Adapt `Breadcrumbs.vue` component for result location context
- **Permissions:** Use `usePermissions` composable and `DatacenterPolicy.php` patterns
- **UI Components:** Leverage existing `@/components/ui/` components (Input, Button, Card, Dropdown)
- **Icons:** Use lucide-vue-next Search icon consistent with existing usage
