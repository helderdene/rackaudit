# Task Breakdown: Rack Elevation View

## Overview
Total Tasks: 37
Total Task Groups: 5

This spec implements an interactive rack elevation diagram with front/rear views, drag-and-drop device positioning, multi-U and half-width device support, placeholder device system, and U-space utilization visualization.

## Task List

### TypeScript Types & Interfaces

#### Task Group 1: Type Definitions
**Dependencies:** None

- [x] 1.0 Complete TypeScript type definitions for elevation system
  - [x] 1.1 Write 2-4 focused tests for type validation
    - Test PlaceholderDevice interface completeness
    - Test DevicePosition type constraints
    - Test RackElevationState type structure
  - [x] 1.2 Add PlaceholderDevice interface to `/Users/helderdene/rackaudit/resources/js/types/rooms.ts`
    - Fields: `id: string`, `name: string`, `type: string`, `u_size: number`, `width: 'full' | 'half-left' | 'half-right'`, `start_u?: number`, `face?: 'front' | 'rear'`
    - Follow existing interface naming patterns (e.g., RackData, RowData)
  - [x] 1.3 Add DevicePosition interface
    - Fields: `device_id: string`, `start_u: number`, `face: 'front' | 'rear'`, `width: 'full' | 'half-left' | 'half-right'`
  - [x] 1.4 Add RackElevationState interface
    - Fields: `placedDevices: PlaceholderDevice[]`, `unplacedDevices: PlaceholderDevice[]`, `draggedDevice: PlaceholderDevice | null`
  - [x] 1.5 Add UtilizationStats interface
    - Fields: `totalU: number`, `usedU: number`, `availableU: number`, `utilizationPercent: number`, `frontUsedU?: number`, `rearUsedU?: number`
  - [x] 1.6 Ensure type definitions are exported correctly
    - Verify imports work from Elevation.vue
    - Run TypeScript compilation check

**Acceptance Criteria:**
- All interfaces compile without TypeScript errors
- Interfaces follow existing naming conventions in rooms.ts
- Types are exported and importable from other files

### Backend Layer

#### Task Group 2: Controller & Sample Data
**Dependencies:** Task Group 1

- [x] 2.0 Complete backend data layer for elevation view
  - [x] 2.1 Write 2-4 focused tests for elevation controller
    - Test elevation endpoint returns correct data structure
    - Test placeholder devices data is included in response
    - Test authorization is enforced
  - [x] 2.2 Create sample placeholder devices data in RackController elevation method
    - Location: `/Users/helderdene/rackaudit/app/Http/Controllers/RackController.php`
    - Add `placeholderDevices` array to Inertia::render response
    - Include mix of device types: servers (1U, 2U, 4U), network switches (1U), storage arrays (2U, 4U)
    - Include half-width devices for demonstration
    - Add clear code comment noting this is temporary until Device model exists
  - [x] 2.3 Add sample placed devices with positions
    - Include 3-5 pre-placed devices for demonstration
    - Mix of front and rear face placements
    - Include at least one half-width pair in same U position
  - [x] 2.4 Add sample unplaced devices
    - Include 4-6 devices in unplaced list
    - Various U-sizes and width types
  - [x] 2.5 Ensure backend tests pass
    - Run ONLY the 2-4 tests written in 2.1
    - Verify elevation endpoint returns expected data structure

**Acceptance Criteria:**
- The 2-4 tests written in 2.1 pass
- Elevation endpoint returns placeholderDevices data
- Sample data includes variety of device types, sizes, and placements
- Code is clearly documented as placeholder system

### Frontend Core Components

#### Task Group 3: Elevation Vue Components
**Dependencies:** Task Group 2

- [x] 3.0 Complete core elevation Vue components
  - [x] 3.1 Write 2-6 focused tests for core components
    - Test USlot component renders with correct U-number
    - Test RackElevationView component shows correct number of slots
    - Test DeviceBlock component displays device information
    - Test empty slot vs occupied slot rendering
  - [x] 3.2 Create USlot component
    - Location: `/Users/helderdene/rackaudit/resources/js/components/elevation/USlot.vue`
    - Props: `uNumber: number`, `isOccupied: boolean`, `isDropTarget: boolean`, `isValidDrop: boolean`
    - Render U-number label on left side
    - Apply dashed border for empty, solid for occupied
    - Apply hover states for interactivity
    - Support half-width slot divisions (left/right)
  - [x] 3.3 Create DeviceBlock component
    - Location: `/Users/helderdene/rackaudit/resources/js/components/elevation/DeviceBlock.vue`
    - Props: `device: PlaceholderDevice`, `isPlaced: boolean`
    - Display device name, type, and U-size
    - Support multi-U height (span multiple rows visually)
    - Support half-width rendering (50% slot width)
    - Add click handler for navigation
    - Include Badge component for device type indicator
  - [x] 3.4 Create RackElevationView component
    - Location: `/Users/helderdene/rackaudit/resources/js/components/elevation/RackElevationView.vue`
    - Props: `face: 'front' | 'rear'`, `uHeight: number`, `devices: PlaceholderDevice[]`
    - Generate U-slots from uHeight (highest at top, U1 at bottom)
    - Position devices correctly based on start_u and u_size
    - Handle multi-U device spanning across slots
    - Handle half-width device positioning
  - [x] 3.5 Create UtilizationCard component
    - Location: `/Users/helderdene/rackaudit/resources/js/components/elevation/UtilizationCard.vue`
    - Props: `stats: UtilizationStats`
    - Display total/used/available U-space
    - Include progress bar for capacity percentage
    - Show separate front/rear counts if applicable
    - Use existing Card component pattern
  - [x] 3.6 Ensure core component tests pass
    - Run ONLY the 2-6 tests written in 3.1
    - Verify components render correctly

**Acceptance Criteria:**
- The 2-6 tests written in 3.1 pass
- Components render correctly with sample data
- Multi-U devices span visually across slots
- Half-width devices render at 50% width

### Frontend Drag-and-Drop

#### Task Group 4: Drag-and-Drop Implementation
**Dependencies:** Task Group 3

- [x] 4.0 Complete drag-and-drop functionality
  - [x] 4.1 Write 2-6 focused tests for drag-and-drop
    - Test device can be dragged from unplaced sidebar
    - Test valid drop target highlighting
    - Test invalid drop target indication (occupied slot)
    - Test collision detection prevents overlap
  - [x] 4.2 Install vue-draggable-plus package
    - Run: `npm install vue-draggable-plus`
    - Verify installation in package.json
  - [x] 4.3 Create UnplacedDevicesSidebar component
    - Location: `/Users/helderdene/rackaudit/resources/js/components/elevation/UnplacedDevicesSidebar.vue`
    - Props: `devices: PlaceholderDevice[]`
    - Display list of unplaced devices with name, type, U-size
    - Make devices draggable using vue-draggable-plus
    - Include search/filter input for large device lists
    - Use Card or Sheet component pattern from existing UI
  - [x] 4.4 Create useRackElevation composable
    - Location: `/Users/helderdene/rackaudit/resources/js/composables/useRackElevation.ts`
    - Manage elevation state (placed/unplaced devices)
    - Implement collision detection logic
    - Implement device placement validation
    - Calculate utilization statistics
    - Handle device move operations
    - Provide methods: `placeDevice()`, `moveDevice()`, `removeDevice()`, `canPlaceAt()`
  - [x] 4.5 Add drag-and-drop handlers to USlot component
    - Implement drop target detection
    - Add visual feedback for valid/invalid drops (green/red border)
    - Show ghost/preview of device at cursor position
    - Handle insufficient space indication
  - [x] 4.6 Add drag-and-drop handlers to RackElevationView
    - Wire up vue-draggable-plus sortable functionality
    - Connect to useRackElevation composable
    - Handle device placement from sidebar
    - Handle device repositioning within rack
  - [x] 4.7 Ensure drag-and-drop tests pass
    - Run ONLY the 2-6 tests written in 4.1
    - Verify drag-and-drop interactions work correctly

**Acceptance Criteria:**
- The 2-6 tests written in 4.1 pass
- Devices can be dragged from sidebar to rack slots
- Valid drop targets show green highlighting
- Invalid drop targets show red highlighting
- Collision detection prevents overlapping placements

### Frontend Page Integration

#### Task Group 5: Elevation Page Assembly
**Dependencies:** Task Groups 3, 4

- [x] 5.0 Complete Elevation.vue page integration
  - [x] 5.1 Write 2-6 focused tests for page integration
    - Test front and rear views render side-by-side
    - Test unplaced devices sidebar displays correctly
    - Test utilization stats update dynamically
    - Test click-to-navigate works for placed devices
  - [x] 5.2 Update Elevation.vue layout structure
    - Location: `/Users/helderdene/rackaudit/resources/js/pages/Racks/Elevation.vue`
    - Add three-column layout: sidebar | front view | rear view
    - Use flex or grid layout for responsive design
    - Import and use new components
  - [x] 5.3 Add UnplacedDevicesSidebar to layout
    - Position on left side of page
    - Connect to useRackElevation composable
    - Wire up device filtering
  - [x] 5.4 Add front and rear RackElevationView components
    - Wrap each in Card with "Front View" / "Rear View" headers
    - Position side-by-side with synchronized U-alignment
    - Connect to useRackElevation composable
  - [x] 5.5 Add UtilizationCard component
    - Position above or below elevation views
    - Wire up dynamic statistics from composable
    - Update stats when devices are placed/moved
  - [x] 5.6 Implement click-to-navigate functionality
    - Handle device click events
    - Use Inertia router.visit() for navigation
    - Navigate to placeholder page for placeholder devices
    - Add clear indication that device is clickable (cursor, hover state)
  - [x] 5.7 Add responsive design support
    - Mobile: Stack views vertically, hide sidebar or make collapsible
    - Tablet: Side-by-side views with collapsible sidebar
    - Desktop: Full three-column layout
    - Use Tailwind responsive prefixes (sm:, md:, lg:)
  - [x] 5.8 Ensure page integration tests pass
    - Run ONLY the 2-6 tests written in 5.1
    - Verify all components work together correctly

**Acceptance Criteria:**
- The 2-6 tests written in 5.1 pass
- Front and rear views display side-by-side
- Sidebar shows unplaced devices
- Utilization stats update dynamically
- Click-to-navigate works for placed devices
- Layout is responsive across device sizes

### Testing

#### Task Group 6: Test Review & Gap Analysis
**Dependencies:** Task Groups 1-5

- [x] 6.0 Review existing tests and fill critical gaps only
  - [x] 6.1 Review tests from Task Groups 1-5
    - Review the 2-4 tests from Task Group 1 (types)
    - Review the 2-4 tests from Task Group 2 (backend)
    - Review the 2-6 tests from Task Group 3 (core components)
    - Review the 2-6 tests from Task Group 4 (drag-and-drop)
    - Review the 2-6 tests from Task Group 5 (page integration)
    - Total existing tests: approximately 10-26 tests
  - [x] 6.2 Analyze test coverage gaps for THIS feature only
    - Identify critical user workflows that lack test coverage
    - Focus ONLY on gaps related to rack elevation feature requirements
    - Do NOT assess entire application test coverage
    - Prioritize end-to-end workflows over unit test gaps
  - [x] 6.3 Write up to 10 additional strategic tests maximum
    - Add maximum of 10 new tests to fill identified critical gaps
    - Focus on integration points and end-to-end workflows
    - Consider testing:
      - Full device placement workflow (drag from sidebar, drop on slot)
      - Device repositioning workflow
      - Half-width device pairing
      - Multi-U device collision detection
      - Utilization calculation accuracy
    - Do NOT write comprehensive coverage for all scenarios
    - Skip edge cases unless business-critical
  - [x] 6.4 Run feature-specific tests only
    - Run ONLY tests related to rack elevation feature
    - Expected total: approximately 20-36 tests maximum
    - Do NOT run the entire application test suite
    - Verify critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 20-36 tests total)
- Critical user workflows for rack elevation are covered
- No more than 10 additional tests added when filling in testing gaps
- Testing focused exclusively on rack elevation feature requirements

## Execution Order

Recommended implementation sequence:

1. **Task Group 1: Type Definitions** - Establish TypeScript interfaces first as foundation for all other work
2. **Task Group 2: Controller & Sample Data** - Provide backend data for frontend development
3. **Task Group 3: Elevation Vue Components** - Build core visual components
4. **Task Group 4: Drag-and-Drop Implementation** - Add interactivity to components
5. **Task Group 5: Elevation Page Assembly** - Integrate all components into the page
6. **Task Group 6: Test Review & Gap Analysis** - Ensure comprehensive test coverage

## Key Files

### New Files to Create
- `/Users/helderdene/rackaudit/resources/js/components/elevation/USlot.vue`
- `/Users/helderdene/rackaudit/resources/js/components/elevation/DeviceBlock.vue`
- `/Users/helderdene/rackaudit/resources/js/components/elevation/RackElevationView.vue`
- `/Users/helderdene/rackaudit/resources/js/components/elevation/UtilizationCard.vue`
- `/Users/helderdene/rackaudit/resources/js/components/elevation/UnplacedDevicesSidebar.vue`
- `/Users/helderdene/rackaudit/resources/js/composables/useRackElevation.ts`

### Existing Files to Modify
- `/Users/helderdene/rackaudit/resources/js/types/rooms.ts` - Add new interfaces
- `/Users/helderdene/rackaudit/app/Http/Controllers/RackController.php` - Extend elevation method
- `/Users/helderdene/rackaudit/resources/js/pages/Racks/Elevation.vue` - Update with new components

### Existing Components to Reuse
- `/Users/helderdene/rackaudit/resources/js/components/ui/card/` - Card, CardHeader, CardContent, CardTitle
- `/Users/helderdene/rackaudit/resources/js/components/ui/badge/` - Badge for device type indicators
- `/Users/helderdene/rackaudit/resources/js/components/ui/sheet/` - Optional for sidebar
- `/Users/helderdene/rackaudit/resources/js/components/ui/input/` - For search/filter
- `/Users/helderdene/rackaudit/resources/js/components/ui/skeleton/` - For loading states

## Technical Notes

### vue-draggable-plus Usage
- Vue 3 compatible fork of SortableJS
- Install: `npm install vue-draggable-plus`
- Supports drag from list to list (sidebar to rack)
- Supports drag within same list (repositioning)
- Provides smooth animations and touch support

### Collision Detection Logic
The composable should implement:
```typescript
function canPlaceAt(device: PlaceholderDevice, startU: number, face: 'front' | 'rear'): boolean {
  // Check if all required U-slots are available
  // Consider device u_size (multi-U)
  // Consider device width (full vs half)
  // Check for existing devices in target slots
}
```

### Multi-U Device Rendering
- Device height = u_size * slot_height
- Device positioned at start_u
- Visually spans from start_u to (start_u + u_size - 1)

### Half-Width Device Pairing
- Two half-width devices can share same U-position
- One with width: 'half-left', other with width: 'half-right'
- Rendered at 50% container width on respective side
