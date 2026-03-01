# Specification: Rack Elevation View

## Goal

Build an interactive visual rack elevation diagram showing device placement with front/rear views, support for multi-U and half-width devices, drag-and-drop positioning, and U-space utilization display. Initial implementation uses placeholder/mock devices until the Device model is ready.

## User Stories

- As a datacenter operator, I want to see both front and rear views of a rack so that I can understand device placement and port configurations at a glance
- As an IT manager, I want to drag devices into specific U positions so that I can plan rack layouts efficiently before physical installation

## Specific Requirements

**Front and Rear Rack Views Side-by-Side**
- Display both front and rear elevation views simultaneously in a side-by-side layout
- Each view shows U-slots numbered from 1 at the bottom to N (rack height) at the top
- Views should be visually synchronized so the same U-position aligns horizontally
- Use existing `rack.u_height` value (42, 45, or 48) to generate the correct number of slots
- Views should be contained in separate Card components with "Front View" and "Rear View" headers

**U-Slot Visual Rendering**
- Each U-slot rendered as a horizontal row with consistent height (approximately 24-32px)
- U-number labels displayed on the left side of each slot
- Empty slots show dashed border with muted background (existing pattern in Elevation.vue)
- Occupied slots show solid border with device information
- Hover states provide visual feedback on interactive slots

**Multi-U Device Support**
- Devices can span multiple rack units (1U, 2U, 4U, etc.)
- Multi-U devices visually merge across their occupied slots
- Device height stored as `u_size` property (number of rack units)
- Starting position stored as `start_u` property (lowest U-number occupied)
- Collision detection prevents overlapping device placement

**Half-Width Device Support**
- Slots support left-half and right-half device placement
- Two half-width devices can occupy the same U-position side-by-side
- Device width stored as `width` property: "full", "half-left", or "half-right"
- Half-width devices rendered at 50% slot width on their respective side
- Multi-U half-width devices supported (spanning multiple Us on one side)

**Unplaced Devices Sidebar**
- Left sidebar panel lists devices not yet placed in the rack
- Each device shows name, type, and U-size requirement
- Devices are draggable from sidebar to rack slots
- Sidebar uses existing Sheet or Card component patterns
- Filter/search functionality for finding devices in large lists

**Drag-and-Drop Implementation (VueDraggable Plus Recommended)**
- Use `vue-draggable-plus` library (Vue 3 compatible, actively maintained fork of SortableJS)
- Enables dragging from sidebar (unplaced) to rack slots (placement)
- Enables dragging placed devices to different positions within same rack
- Provides smooth animations and touch device support for tablet usage
- Alternative: `@vueuse/integrations` with native HTML5 drag-and-drop if simpler approach preferred

**Visual Feedback for Valid/Invalid Drop Targets**
- Valid drop zones highlighted with success color (green border/background)
- Invalid drop zones (occupied, insufficient space) shown with error color (red border)
- Real-time validation as device is dragged over potential positions
- Ghost/preview of device placement shown at cursor position
- Clear visual indication when device cannot fit due to U-size constraints

**U-Space Utilization Display**
- Summary statistics card showing total/used/available U-space
- Progress bar or visual indicator of rack capacity percentage
- Separate counts for front and rear if tracking differs
- Updates dynamically as devices are placed/moved

**Placeholder Device System**
- Mock device data structure matching future Device model interface
- Placeholder devices created via in-memory state (not persisted to database)
- TypeScript interface: `PlaceholderDevice { id, name, type, u_size, width, start_u?, face? }`
- Sample devices seeded for demonstration and testing purposes
- Clear documentation that this will integrate with real Device model later

**Click-to-Navigate Device Interaction**
- Clicking on a placed device navigates to device detail page
- Use Inertia `router.visit()` for navigation
- Placeholder devices navigate to a stub/placeholder page
- No inline popovers or modals for device details

## Visual Design

No visual mockups were provided. The implementation should follow the existing Elevation.vue patterns and extend them with the specified functionality.

## Existing Code to Leverage

**`/Users/helderdene/rackaudit/resources/js/pages/Racks/Elevation.vue`**
- Basic elevation page structure with breadcrumbs and layout already implemented
- U-slot generation logic using computed property from `rack.u_height`
- Status badge variants and styling patterns
- Extend this page rather than creating new page

**`/Users/helderdene/rackaudit/app/Http/Controllers/RackController.php` elevation method**
- Existing controller action returns rack data with hierarchy context
- Extend to include placeholder devices data when Device model is not ready
- Follows established Inertia::render pattern with nested route parameters

**`/Users/helderdene/rackaudit/resources/js/types/rooms.ts`**
- TypeScript interface patterns for rack-related data structures
- Add new interfaces for PlaceholderDevice, DevicePosition, RackElevationState
- Follow existing naming conventions (RackData, RackReference patterns)

**`/Users/helderdene/rackaudit/resources/js/components/ui/card/` and `/badge/`**
- Reuse Card, CardHeader, CardContent, CardTitle components for elevation views
- Reuse Badge component for device type/status indicators
- Maintain consistent styling with rest of application

**`/Users/helderdene/rackaudit/resources/js/components/ui/sheet/`**
- Consider Sheet component for unplaced devices sidebar (slide-out panel)
- Alternative: persistent sidebar using flex layout

## Out of Scope

- Printing/PDF export of rack diagrams
- Rack comparison view (comparing multiple racks side-by-side)
- Historical snapshots showing rack state at previous points in time
- Power consumption display or power-related data
- Inline device preview/popover on click (use navigation instead)
- Actual Device model database integration (separate spec - uses placeholder system)
- Device creation/editing forms within elevation view
- Rack-to-rack device transfer functionality
- Undo/redo for device placement operations
- Keyboard shortcuts for device placement
