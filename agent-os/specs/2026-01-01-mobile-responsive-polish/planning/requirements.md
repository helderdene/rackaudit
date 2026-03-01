# Spec Requirements: Mobile Responsive Polish

## Initial Description
Ensure all interfaces work well on tablets for operators in the datacenter. This is item #47 in the product roadmap (Phase 6: Polish & Optimization). The primary use case is datacenter operators (like "Dave the Datacenter Technician" persona) who work on the floor using tablets to execute audits, verify devices, view rack diagrams, and document changes in real-time.

## Requirements Discussion

### First Round Questions

**Q1:** I assume the primary target devices are iPad-sized tablets (roughly 768px-1024px viewport width) since datacenter operators typically use these for floor work. Is that correct, or should we also optimize for smaller tablets (7" Android tablets) or even large phones?
**Answer:** Correct - iPad-sized tablets (768px-1024px viewport) are the primary target devices

**Q2:** I'm thinking the highest-priority pages for tablet optimization should be the audit execution workflows (connection verification and inventory verification with QR scanning), rack elevation views, and the main dashboard. Should we prioritize these pages first, or are there other pages operators use more frequently on the floor?
**Answer:** Correct - Prioritize audit execution workflows, rack elevation views, and dashboard

**Q3:** The current Rack Elevation page shows front and rear views side-by-side on desktop, with a collapsible sidebar for unplaced devices. I assume on tablets we should stack the elevation views vertically while keeping the collapsible sidebar pattern. Is that correct, or would operators prefer a different layout (like swipe-to-switch between front/rear views)?
**Answer:** Stack the elevation views vertically on tablets (not swipe-to-switch)

**Q4:** The audit execution pages have multi-column filter layouts and data tables. I assume we should convert these to a stacked filter layout on tablets with horizontally scrollable or card-based table views. Is that the right approach, or do operators prefer seeing as much data as possible even if it means horizontal scrolling?
**Answer:** Convert to stacked filter layout on tablets with card-based table views (not horizontal scrolling)

**Q5:** The sidebar navigation is already using a collapsible "inset" variant. I assume this works well on tablets in its collapsed icon-only state. Should we auto-collapse it on tablet viewport sizes, or should operators have control over this?
**Answer:** Correct - Auto-collapse sidebar on tablet viewport sizes

**Q6:** For touch interactions on tablets, I assume we should increase tap target sizes for action buttons (currently many use `size="sm"`) and ensure adequate spacing between clickable elements. Are there specific touch-friendly requirements like swipe gestures for common actions?
**Answer:** Correct - Increase tap target sizes and ensure adequate spacing for touch

**Q7:** The QR code scanning feature in inventory audits is tablet-centric. I assume this already works well, but should we add any tablet-specific enhancements like a larger scanning overlay or haptic feedback on successful scans?
**Answer:** Correct - Add tablet-specific enhancements for QR scanning (larger overlay, haptic feedback)

**Q8:** Is there anything that should explicitly be out of scope for this polish pass? For example, should we exclude phone-sized responsive design, offline capabilities, or specific pages that operators don't use on tablets?
**Answer:** Exclude suggested features - phone-sized responsive design, offline capabilities are out of scope

### Existing Code to Reference

No similar existing features identified for reference.

### Follow-up Questions

None required - all questions were answered with sufficient clarity.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
Not applicable - no visual files were provided.

## Requirements Summary

### Functional Requirements

**Target Viewport:**
- iPad-sized tablets (768px-1024px viewport width)
- Primary orientation: Both portrait and landscape should work

**Priority Pages (in order):**
1. Audit execution workflows (`/Pages/Audits/Execute.vue`, `/Pages/Audits/InventoryExecute.vue`)
2. Rack elevation views (`/Pages/Racks/Elevation.vue`)
3. Main dashboard (`/Pages/Dashboard.vue`)

**Layout Requirements:**
- Rack Elevation: Stack front and rear views vertically on tablets (keep collapsible sidebar pattern)
- Audit Pages: Convert multi-column filters to stacked layout; use card-based views instead of tables
- Sidebar Navigation: Auto-collapse to icon-only state on tablet viewport sizes

**Touch Interaction Requirements:**
- Increase tap target sizes for action buttons (minimum 44x44px touch targets per Apple HIG)
- Ensure adequate spacing between clickable elements (minimum 8px gaps)
- No swipe gestures required for this phase

**QR Scanning Enhancements:**
- Larger scanning overlay for tablet screens
- Haptic feedback on successful scans

### Reusability Opportunities

- The audit Show page (`/Pages/Audits/Show.vue`) already has a mobile card view pattern for the Report History table that can serve as a template for other card-based tablet views
- The Dashboard already has responsive grid layouts (`grid-cols-1 sm:grid-cols-2 lg:grid-cols-4`) that work well

### Scope Boundaries

**In Scope:**
- Tablet viewport optimization (768px-1024px)
- Priority pages: Audit execution, Rack elevation, Dashboard
- Stacked/card-based layouts for complex data
- Auto-collapsing sidebar on tablet sizes
- Touch-friendly tap targets and spacing
- QR scanner tablet enhancements (larger overlay, haptic feedback)

**Out of Scope:**
- Phone-sized responsive design (below 768px)
- Offline capabilities
- New features or functionality
- Pages not in the priority list (can be addressed in future iterations)

### Technical Considerations

- Use Tailwind CSS responsive prefixes for tablet breakpoints (primarily `md:` for 768px+)
- Leverage existing collapsible patterns in the codebase
- The app uses Vue 3 + Inertia.js with Tailwind CSS 4
- Existing sidebar uses ShadCN's Sidebar component with `collapsible="icon"` variant
- QR scanning already exists in `QrScannerModal.vue` - needs tablet enhancements
- Consider using CSS container queries for component-level responsiveness where appropriate
