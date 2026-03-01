# Specification: Global Search Functionality

## Goal
Implement a global search feature accessible from the header navigation that allows users to search across datacenters, racks, devices, ports, and connections with real-time results, hierarchical filtering, and RBAC-compliant result visibility.

## User Stories
- As a datacenter operator, I want to quickly search for devices by name, serial number, or asset tag so that I can locate specific equipment without navigating through the hierarchy manually.
- As an IT manager, I want to find connections between specific devices or racks so that I can trace cable paths for troubleshooting or planning.

## Specific Requirements

**Global Search Input Component**
- Place search input in the AppHeader.vue component next to the existing Search icon button
- Trigger search dropdown on input focus or when typing begins
- Implement 300ms debounce using the existing `debounce` utility from `@/lib/utils`
- Display search icon (lucide-vue-next Search) within the input field
- Support keyboard shortcut (Cmd/Ctrl + K) to focus search input globally
- Clear search input when pressing Escape key

**Search Dropdown (Quick Results)**
- Show maximum 3-5 results per entity type in the dropdown for quick access
- Group results by entity type with clear section headers (Datacenters, Racks, Devices, Ports, Connections)
- Display breadcrumb-style location context for each result (e.g., "Device-01 > Rack-A1 > Room-Main > DC-NYC")
- Highlight matched search terms within results using `<mark>` or similar styling
- Include "View all results" link at dropdown bottom that navigates to full search results page
- Support keyboard navigation (arrow keys to navigate, Enter to select, Escape to close)

**Searchable Fields by Entity Type**
- Datacenters: name, city, country, company_name, primary_contact_name, secondary_contact_name
- Racks: name, serial_number
- Devices: name, asset_tag, serial_number, manufacturer, model
- Ports: label
- Connections: cable_color, path_notes, plus connected device names and port labels via relationships

**Full Search Results Page**
- Create dedicated search results page at route `/search` with query parameter `?q=searchterm`
- Display paginated results grouped by entity type with expandable sections
- Include result count per entity type in section headers
- Support entity type filter tabs/buttons to show only specific entity types
- Implement pagination using Laravel's built-in pagination (15-20 results per page per entity type)

**Hierarchical Location Filters**
- Implement cascading filter dropdowns: Datacenter > Room > Row > Rack
- Follow the pattern established in CapacityFilters.vue and DiscrepancyFilters.vue
- Reset child filters when parent filter changes (e.g., changing datacenter clears room/row/rack)
- Load filter options dynamically based on parent selection

**Entity-Specific Attribute Filters**
- Device filter: lifecycle_status (using DeviceLifecycleStatus enum values)
- Port filter: type (PortType enum), status (PortStatus enum)
- Rack filter: status (RackStatus enum)
- Display filters only when relevant entity type is selected or visible in results

**Connection-Specific Search**
- Allow searching for connections by source or destination device/rack name
- Support queries like "connections between Server-01 and Switch-A"
- Traverse Port > Device > Rack relationships for location context

**RBAC Integration**
- Filter search results based on user's datacenter access permissions
- Leverage existing DatacenterPolicy.php view() method logic for permission checking
- Administrators and IT Managers see all results; other roles see only assigned datacenter entities
- Use user->datacenters() relationship to determine accessible datacenters for non-admin users

## Visual Design
No visual mockups provided. Follow existing UI patterns from the codebase:
- Use existing UI components from `@/components/ui/` (Input, Button, Card, Dropdown)
- Match filter component styling from CapacityFilters.vue and DiscrepancyFilters.vue
- Use Tailwind classes consistent with AppHeader.vue styling
- Include loading states using Spinner component during search
- Display empty state messaging when no results found

## Existing Code to Leverage

**AppHeader.vue Component**
- Contains existing Search icon button that should be replaced/enhanced with search input
- Shows pattern for header component integration with navigation and user menu
- Uses lucide-vue-next for icons and existing UI components

**CapacityFilters.vue and DiscrepancyFilters.vue**
- Established pattern for hierarchical datacenter > room > row filtering
- Uses debounce for filter application (300ms)
- Shows mobile collapsible and desktop inline filter layouts
- Demonstrates cascading filter reset behavior on parent change

**Breadcrumbs.vue Component**
- Existing breadcrumb display component that can be adapted for result location context
- Uses BreadcrumbItem, BreadcrumbLink, BreadcrumbSeparator from UI components

**usePermissions Composable**
- Provides can(), hasRole(), hasAnyRole() methods for frontend permission checking
- Access permissions from Inertia shared data via page.props.auth

**DatacenterPolicy.php**
- Contains ADMIN_ROLES constant and view() method logic for RBAC
- Pattern for checking user datacenter access via user->datacenters() relationship

## Out of Scope
- Searching within Audits and audit-related entities
- Searching within Findings
- Searching within Implementation files
- Searching within Activity logs
- Full-text search within document contents or file attachments
- Saved searches or search history functionality
- Search analytics, tracking, or usage metrics
- Fuzzy/typo-tolerant search (use exact/partial matching only)
- Search result caching beyond standard Laravel query caching
- Export of search results to CSV/PDF
