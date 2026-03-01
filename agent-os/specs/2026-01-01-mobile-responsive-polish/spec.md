# Specification: Mobile Responsive Polish

## Goal
Optimize key interfaces for iPad-sized tablets (768px-1024px viewport) to support datacenter operators executing audits, viewing rack diagrams, and managing inventory on the floor.

## User Stories
- As a datacenter technician, I want to execute inventory audits on my tablet so that I can verify devices in real-time while walking the floor
- As an auditor, I want rack elevation views to be readable on my tablet so that I can verify device placements without switching devices

## Specific Requirements

**Sidebar Auto-Collapse for Tablet Viewports**
- Modify `SidebarProvider.vue` to detect tablet viewport (768px-1024px) using `useMediaQuery`
- Auto-collapse sidebar to icon-only state when viewport matches tablet breakpoint
- Preserve user's manual toggle preference within the session
- Maintain existing mobile drawer behavior for screens below 768px
- Ensure smooth transition animation when viewport crosses breakpoints

**Rack Elevation Vertical Stacking**
- Stack front and rear elevation views vertically on tablet viewports in `Elevation.vue`
- Use `md:flex-row` to `flex-col` responsive pattern on the elevation views container
- Maintain the existing side-by-side layout for desktop viewports (lg+)
- Keep collapsible unplaced devices sidebar pattern unchanged
- Adjust max-height constraints for vertical layout to prevent excessive scrolling

**Audit Execution Filter Layout**
- Convert multi-column filter controls in `Execute.vue` and `InventoryExecute.vue` to stacked layout
- Use `flex-col gap-3` pattern for filter groups on tablet, inline on desktop
- Ensure filter dropdowns expand to full width on tablet for easier touch interaction
- Maintain filter functionality and debounced search behavior

**Card-Based Views for Audit Data Tables**
- Create responsive card views as alternative to tables in `DeviceVerificationTable.vue`
- Show table on desktop (lg+), card layout on tablet (md) and below
- Follow existing card pattern from `Audits/Show.vue` Report History section
- Include all essential data: device name, asset tag, position, status, and action button
- Maintain checkbox selection functionality in card view

**Touch-Friendly Tap Targets**
- Increase button sizes from `size="sm"` to default size on tablet viewports
- Apply minimum 44x44px touch targets per Apple HIG guidelines
- Add `min-h-11 min-w-11` classes to action buttons in audit execution pages
- Ensure 8px minimum gaps between adjacent clickable elements
- Apply touch-friendly sizing to pagination controls

**QR Scanner Tablet Enhancements**
- Increase scanner overlay size from `size-48` to `size-64` on tablet viewports in `QrScannerModal.vue`
- Implement haptic feedback on successful scan using Navigator Vibration API
- Enlarge manual device ID input field for easier touch typing
- Increase dialog width using `sm:max-w-xl` for tablet screens

**Dashboard Responsive Optimization**
- Verify existing responsive grid (`grid-cols-1 sm:grid-cols-2 lg:grid-cols-4`) works well on tablets
- Ensure metric cards and charts maintain readability at tablet widths
- Adjust filter controls to stack appropriately on tablet landscape orientation

**Progress Card Touch Optimization**
- Increase badge sizes in progress stats sections for better readability
- Ensure progress bar has sufficient height for visual clarity on tablet

## Visual Design
No visual assets were provided.

## Existing Code to Leverage

**`Audits/Show.vue` Report History Card Pattern**
- Lines 487-508 implement a mobile card view pattern with `md:hidden` / `hidden md:block` toggle
- Card structure includes rounded border, padding, justified content layout
- Reuse this pattern for audit execution tables to provide card-based alternative views

**`Dashboard.vue` Responsive Grid**
- Uses `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4` for metric cards at line 241
- Filter controls use `flex-col gap-3 sm:flex-row` stacking pattern at line 219
- These patterns already work well and should be referenced for consistency

**`SidebarProvider.vue` Media Query Pattern**
- Uses `useMediaQuery("(max-width: 768px)")` for mobile detection at line 22
- Add tablet detection `useMediaQuery("(min-width: 768px) and (max-width: 1024px)")`
- Leverage existing `setOpen` function to auto-collapse for tablet

**`Elevation.vue` Responsive Layout**
- Uses `flex-col gap-4 lg:flex-row` pattern for main content at line 240
- Already has collapsible sidebar for mobile/tablet with `lg:hidden` / `hidden lg:flex` toggle
- Elevation views container uses `flex-col gap-4 md:flex-row` at line 277

**`QrScannerModal.vue` Scanner Implementation**
- Scanner overlay at line 287 uses `size-48` class
- BarcodeDetector API usage can be extended with Vibration API for haptic feedback
- Dialog content uses `max-w-md sm:max-w-lg` sizing pattern

## Out of Scope
- Phone-sized responsive design (viewports below 768px)
- Offline capabilities and service workers
- New features or functionality beyond layout optimization
- Pages not in priority list (settings, user management, reports pages)
- Swipe gestures for navigation or actions
- Landscape-specific optimizations beyond general tablet support
- Performance optimization for low-powered tablets
- Dark mode specific adjustments (existing dark mode patterns should continue to work)
- Accessibility improvements beyond touch target sizing
- Browser compatibility testing (assumes modern browsers with BarcodeDetector support)
