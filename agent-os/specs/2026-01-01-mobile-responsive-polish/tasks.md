# Task Breakdown: Mobile Responsive Polish

## Overview
Total Tasks: 28 tasks across 5 task groups

This spec optimizes key interfaces for iPad-sized tablets (768px-1024px viewport) to support datacenter operators executing audits, viewing rack diagrams, and managing inventory on the floor.

## Task List

### Core Infrastructure

#### Task Group 1: Sidebar Auto-Collapse for Tablet Viewports
**Dependencies:** None
**Files:** `resources/js/Components/ui/sidebar/SidebarProvider.vue`

- [x] 1.0 Complete sidebar tablet auto-collapse
  - [x] 1.1 Write 3-4 focused tests for sidebar tablet behavior
    - Test that sidebar auto-collapses when viewport enters tablet range (768px-1024px)
    - Test that user manual toggle preference is preserved within session
    - Test that mobile drawer behavior is unchanged for viewports below 768px
    - Test smooth transition when viewport crosses breakpoints
  - [x] 1.2 Add tablet viewport detection using useMediaQuery
    - Add `useMediaQuery("(min-width: 768px) and (max-width: 1024px)")` for tablet detection
    - Reference existing mobile detection pattern at line 22
  - [x] 1.3 Implement auto-collapse logic for tablet viewport
    - Use `setOpen(false)` to collapse sidebar when isTablet becomes true
    - Add session-based flag to track user manual override
    - Preserve existing mobile drawer behavior (openMobile ref)
  - [x] 1.4 Add smooth CSS transition for collapse animation
    - Ensure transition animation plays when viewport crosses breakpoints
    - Maintain existing transition styles for sidebar width changes
  - [x] 1.5 Ensure sidebar tests pass
    - Run ONLY the 3-4 tests written in 1.1
    - Verify sidebar behavior at different viewport sizes

**Acceptance Criteria:**
- Sidebar auto-collapses to icon-only state on tablet viewports
- User can manually expand sidebar on tablet (preference preserved in session)
- Mobile drawer behavior unchanged for screens below 768px
- Smooth transition animation when viewport crosses breakpoints

---

### Rack Elevation Page

#### Task Group 2: Rack Elevation Vertical Stacking
**Dependencies:** Task Group 1 (sidebar must work correctly)
**Files:** `resources/js/Pages/Racks/Elevation.vue`

- [x] 2.0 Complete rack elevation tablet layout
  - [x] 2.1 Write 3-4 focused tests for elevation responsive layout
    - Test that front/rear views stack vertically on tablet viewport (md breakpoint)
    - Test that side-by-side layout is maintained on desktop (lg+ breakpoint)
    - Test collapsible unplaced devices sidebar works on tablet
    - Test max-height constraints prevent excessive scrolling on tablet
  - [x] 2.2 Update elevation views container responsive classes
    - Change line 277 container from `flex-col gap-4 md:flex-row` to `flex-col gap-4 lg:flex-row`
    - This stacks front/rear views vertically on tablet (md) and side-by-side on desktop (lg+)
  - [x] 2.3 Adjust max-height constraints for vertical layout
    - Modify CardContent max-height calculation at lines 286 and 322
    - Use responsive max-height: larger on tablet portrait, smaller on desktop
    - Pattern: `max-h-[calc(100vh-20rem)] lg:max-h-[calc(100vh-24rem)]`
  - [x] 2.4 Ensure collapsible sidebar pattern works on tablet
    - Verify existing Collapsible component at line 242-266 works for tablets
    - Adjust `lg:hidden` to `md:hidden` if needed to show collapsible on tablets only
  - [x] 2.5 Ensure elevation layout tests pass
    - Run ONLY the 3-4 tests written in 2.1
    - Verify layout changes at different viewport sizes

**Acceptance Criteria:**
- Front and rear elevation views stack vertically on tablet viewports
- Side-by-side layout maintained on desktop (lg+)
- Collapsible unplaced devices sidebar works on tablet
- No excessive scrolling on tablet viewport

---

### Audit Execution Pages

#### Task Group 3: Audit Execution Tablet Optimization
**Dependencies:** Task Group 1 (sidebar must work correctly)
**Files:**
- `resources/js/Pages/Audits/Execute.vue`
- `resources/js/Pages/Audits/InventoryExecute.vue`
- `resources/js/components/audits/DeviceVerificationTable.vue`
- `resources/js/components/audits/QrScannerModal.vue`

- [x] 3.0 Complete audit execution tablet optimization
  - [x] 3.1 Write 5-6 focused tests for audit tablet optimization
    - Test filter controls stack vertically on tablet viewport in Execute.vue
    - Test filter controls stack vertically on tablet viewport in InventoryExecute.vue
    - Test card-based view displays on tablet/mobile in DeviceVerificationTable
    - Test table view displays on desktop (lg+) in DeviceVerificationTable
    - Test QR scanner overlay is larger on tablet viewports
    - Test haptic feedback triggers on successful scan (if Vibration API available)
  - [x] 3.2 Convert Execute.vue filter layout to stacked on tablet
    - Change line 577 filter container to `flex-col gap-3 md:gap-4 lg:flex-row lg:flex-wrap lg:items-center`
    - Add `w-full md:w-auto` to filter selects for full-width on tablet
    - Ensure adequate touch spacing between filter elements
  - [x] 3.3 Update InventoryExecute.vue filter layout for tablet
    - Verify existing layout at line 655 (`flex-col gap-4 lg:flex-row lg:flex-wrap`)
    - Ensure filter dropdowns expand to full width with `w-full lg:w-40` pattern
    - Maintain debounced search behavior
  - [x] 3.4 Create responsive card view in DeviceVerificationTable.vue
    - Follow existing card pattern from Audits/Show.vue lines 487-508
    - Add `md:hidden` card view container with rounded border and padding
    - Include: device name, asset tag, position, status badge, and action button
    - Maintain checkbox selection functionality in card view
    - Add `hidden md:block` wrapper around existing table
  - [x] 3.5 Apply touch-friendly tap targets to action buttons
    - Add `min-h-11 min-w-11` classes to action buttons in audit pages
    - Increase button sizes from `size="sm"` to default on tablet
    - Use pattern: `size="sm" lg:size="default"` or conditional sizing
    - Ensure 8px minimum gaps between adjacent clickable elements
  - [x] 3.6 Apply touch-friendly sizing to pagination controls
    - Update pagination buttons at Execute.vue lines 702-718 and InventoryExecute.vue lines 799-815
    - Add minimum touch target sizing to Previous/Next buttons
  - [x] 3.7 Enhance QrScannerModal.vue for tablet
    - Increase scanner overlay from `size-48` to `size-48 md:size-64` at line 287
    - Implement haptic feedback on successful scan using Navigator Vibration API
    - Add `navigator.vibrate?.(100)` after successful scan detection in handleScannedData
    - Increase dialog width to `max-w-md sm:max-w-lg md:max-w-xl` at line 235
    - Enlarge manual device ID input field: add `text-lg md:text-xl` class
  - [x] 3.8 Ensure audit execution tests pass
    - Run ONLY the 5-6 tests written in 3.1
    - Verify filter layouts, card views, and QR scanner enhancements

**Acceptance Criteria:**
- Filter controls stack vertically on tablet with full-width dropdowns
- Card-based view shows on tablet/mobile, table on desktop
- Checkbox selection works in both card and table views
- All action buttons have minimum 44x44px touch targets
- 8px minimum spacing between clickable elements
- QR scanner overlay larger on tablet
- Haptic feedback on successful scan
- Manual device ID input enlarged for touch typing

---

### Dashboard Page

#### Task Group 4: Dashboard Responsive Verification
**Dependencies:** Task Group 1 (sidebar must work correctly)
**Files:** `resources/js/Pages/Dashboard.vue`

- [x] 4.0 Complete dashboard tablet optimization
  - [x] 4.1 Write 2-3 focused tests for dashboard tablet behavior
    - Test metric cards grid displays 2 columns on tablet (sm breakpoint)
    - Test filter controls stack appropriately on tablet landscape
    - Test charts and metric cards maintain readability at tablet widths
  - [x] 4.2 Verify existing responsive grid works on tablets
    - Confirm `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4` at line 241 works well
    - Test at various tablet widths (768px, 900px, 1024px)
    - Document any adjustments needed
  - [x] 4.3 Adjust filter controls for tablet landscape
    - Verify existing stacking pattern at line 219 (`flex-col gap-3 sm:flex-row`)
    - Ensure filter dropdowns have adequate width on tablet landscape
    - Add touch-friendly sizing if needed
  - [x] 4.4 Increase progress card badge and progress bar sizes
    - Locate progress stats sections in dashboard
    - Increase badge sizes for better tablet readability
    - Ensure progress bar has sufficient height (minimum h-2.5 or h-3)
  - [x] 4.5 Ensure dashboard tests pass
    - Run ONLY the 2-3 tests written in 4.1
    - Verify dashboard displays correctly at tablet viewports

**Acceptance Criteria:**
- Metric cards display in 2-column grid on tablets
- Filter controls work well in tablet landscape orientation
- Charts and metric cards are readable at tablet widths
- Progress badges and bars have sufficient visual clarity

---

### Testing & Integration

#### Task Group 5: Test Review & Integration Testing
**Dependencies:** Task Groups 1-4

- [x] 5.0 Review and integrate all responsive changes
  - [x] 5.1 Review tests from Task Groups 1-4
    - Review 4 tests from sidebar group (Task 1.1) in tests/Feature/Components/Sidebar/SidebarTabletBehaviorTest.php
    - Review 4 tests from elevation group (Task 2.1) in tests/Feature/RackElevation/ElevationResponsiveLayoutTest.php
    - Review 6 tests from audit execution group (Task 3.1) in tests/Feature/Audit/AuditExecutionTabletOptimizationTest.php
    - Review 3 tests from dashboard group (Task 4.1) in tests/Feature/Components/Dashboard/DashboardTabletBehaviorTest.php
    - Total existing tests: 17 tests
  - [x] 5.2 Analyze test coverage gaps for tablet responsive features
    - Identified gaps in cross-component navigation with sidebar state
    - Identified gaps in complete audit execution workflow testing
    - Identified gaps in multi-page navigation flow testing
    - Prioritized end-to-end workflows over unit test gaps
  - [x] 5.3 Write up to 8 additional integration tests if needed
    - Created tests/Feature/TabletResponsiveIntegrationTest.php with 8 tests:
      1. sidebar collapsed state persists across dashboard to elevation navigation
      2. complete inventory audit execution workflow on tablet
      3. audit list to execute navigation preserves collapsed sidebar state
      4. rack elevation page provides complete data for tablet vertical layout
      5. dashboard datacenter filter provides complete metrics for tablet grid
      6. connection audit execute page provides data for tablet stacked filters
      7. audit show to execute navigation maintains tablet layout state
      8. bulk verification workflow supports tablet batch operations
  - [x] 5.4 Run feature-specific tests only
    - Ran all 25 tablet responsive tests (17 existing + 8 new)
    - All tests pass with 439 assertions
    - Duration: 1.95s
  - [x] 5.5 Perform visual verification at tablet breakpoints
    - Created visual verification report at agent-os/specs/2026-01-01-mobile-responsive-polish/verification/visual-verification-report.md
    - Documented all responsive classes at 768px, 900px, and 1024px breakpoints
    - Confirmed touch targets meet 44x44px minimum via code analysis
    - Verified spacing between clickable elements using gap utilities
    - Checked QR scanner overlay sizing (size-48 to size-64 on tablet)

**Acceptance Criteria:**
- All feature-specific tests pass (25 tests total) - VERIFIED
- No more than 8 additional tests added when filling gaps - VERIFIED (8 tests added)
- Visual verification confirms tablet layouts work correctly - VERIFIED via code analysis
- All touch targets meet Apple HIG guidelines (44x44px minimum) - VERIFIED

---

## Execution Order

Recommended implementation sequence:

1. **Task Group 1: Sidebar Auto-Collapse** (Core infrastructure)
   - Must complete first as other pages depend on sidebar behavior

2. **Task Group 2: Rack Elevation** (Can run parallel with 3 after 1 completes)
   - Independent page, no dependencies on other UI changes

3. **Task Group 3: Audit Execution** (Can run parallel with 2 after 1 completes)
   - Largest task group, contains most UI changes
   - Card-based tables, filter layouts, QR scanner, touch targets

4. **Task Group 4: Dashboard** (After 1, can run parallel with 2-3)
   - Mostly verification of existing responsive patterns
   - Smallest scope of changes

5. **Task Group 5: Test Review & Integration** (After 1-4 complete)
   - Final verification and gap analysis
   - Integration testing across all changed components

---

## Key Files Reference

| File | Task Groups | Primary Changes |
|------|-------------|-----------------|
| `resources/js/Components/ui/sidebar/SidebarProvider.vue` | 1 | Add tablet detection, auto-collapse |
| `resources/js/Pages/Racks/Elevation.vue` | 2 | Vertical stacking, max-height adjustments |
| `resources/js/Pages/Audits/Execute.vue` | 3 | Filter layout, touch targets, pagination |
| `resources/js/Pages/Audits/InventoryExecute.vue` | 3 | Filter layout, touch targets, pagination |
| `resources/js/components/audits/DeviceVerificationTable.vue` | 3 | Card-based responsive view |
| `resources/js/components/audits/QrScannerModal.vue` | 3 | Larger overlay, haptic feedback, dialog sizing |
| `resources/js/Pages/Dashboard.vue` | 4 | Verify grid, filter stacking, progress card sizing |

---

## Tailwind CSS Breakpoint Reference

For this spec, use these Tailwind breakpoints:
- **Mobile:** Default (below 640px)
- **Small tablet/large phone:** `sm:` (640px+)
- **Tablet (primary focus):** `md:` (768px+)
- **Desktop:** `lg:` (1024px+)
- **Large desktop:** `xl:` (1280px+)

The tablet viewport range (768px-1024px) is covered by `md:` breakpoint changes that should switch at `lg:` for desktop layouts.
