# Task Breakdown: QR Code Generation

## Overview
Total Tasks: 4 Task Groups with ~30 sub-tasks

This feature enables users to generate, download, and print QR codes for racks and devices that link directly to their detail pages, supporting both individual and bulk generation workflows.

## Task List

### Frontend Components

#### Task Group 1: QR Code Dialog Component
**Dependencies:** None

- [x] 1.0 Complete QR Code dialog component
  - [x] 1.1 Write 4-6 focused tests for QR Code dialog functionality
    - Test dialog opens when trigger button is clicked
    - Test QR code renders with correct URL
    - Test PNG download button triggers file download
    - Test SVG download button triggers file download
    - Test print button opens browser print dialog
    - Test dialog closes properly
  - [x] 1.2 Install QR code generation library
    - Install `qrcode` npm package for client-side QR code generation
    - Verify TypeScript types are available
  - [x] 1.3 Create `QrCodeDialog.vue` component
    - Location: `/resources/js/components/common/QrCodeDialog.vue`
    - Props: `entityType: 'rack' | 'device'`, `entityId: number`, `entityName: string`, `secondaryLabel?: string` (asset_tag or serial_number)
    - Follow dialog pattern from `DeleteDeviceDialog.vue`
    - Use Dialog, DialogTrigger, DialogContent, DialogHeader, DialogFooter from ui/dialog
  - [x] 1.4 Implement QR code preview display
    - Generate QR code encoding full URL (e.g., `https://rackaudit.test/devices/123`)
    - Display QR code at scannable size (minimum 150px for preview)
    - Show entity name prominently below QR code
    - Show secondary label (asset_tag for devices, serial_number for racks) below name
    - Support both 2" x 1" and 2" x 2" label preview layouts
  - [x] 1.5 Implement PNG download functionality
    - Generate PNG using canvas from QR code
    - Include label text in the PNG (name and secondary label)
    - Filename format: `{entity_type}-{id}-qr.png`
    - Trigger immediate client-side download
  - [x] 1.6 Implement SVG download functionality
    - Generate SVG version of QR code with label
    - Filename format: `{entity_type}-{id}-qr.svg`
    - Trigger immediate client-side download
  - [x] 1.7 Implement browser print functionality
    - Print button opens browser print dialog
    - Apply optimized print stylesheet for label printing
    - Show only QR code and label info (hide dialog chrome)
    - Support both portrait and landscape orientations
  - [x] 1.8 Ensure QR Code dialog tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify dialog functionality works correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- Dialog opens and displays QR code preview correctly
- QR code encodes the correct full URL for the entity
- PNG and SVG downloads work and include label information
- Print dialog opens with optimized print layout
- Matches existing dialog design patterns

---

#### Task Group 2: Single Item QR Code Integration
**Dependencies:** Task Group 1

- [x] 2.0 Complete single item QR code integration
  - [x] 2.1 Write 4-6 focused tests for single item integration
    - Test QR code button appears on Device Show page
    - Test QR code button appears on Rack Show page
    - Test correct props are passed to QrCodeDialog for devices (includes asset_tag)
    - Test correct props are passed to QrCodeDialog for racks (includes serial_number)
    - Test button is available to all authenticated users with view access
  - [x] 2.2 Add QR code button to Device Show page
    - Location: `/resources/js/Pages/Devices/Show.vue`
    - Add "QR Code" button in header button group alongside Edit/Delete
    - Use `variant="outline"` for consistent secondary action styling
    - Import and use QrCodeDialog component
    - Pass device.id, device.name, device.asset_tag as props
  - [x] 2.3 Add QR code button to Rack Show page
    - Location: `/resources/js/Pages/Racks/Show.vue`
    - Add "QR Code" button in header button group alongside Edit/Delete
    - Use `variant="outline"` for consistent secondary action styling
    - Import and use QrCodeDialog component
    - Pass rack.id, rack.name, rack.serial_number as props
  - [x] 2.4 Add QR Code icon from lucide-vue-next
    - Use `QrCode` icon from lucide-vue-next for button
    - Maintain consistent icon sizing with other action buttons
  - [x] 2.5 Ensure single item integration tests pass
    - Run ONLY the 4-6 tests written in 2.1
    - Verify buttons appear and function correctly on both pages
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 2.1 pass
- QR Code button appears on Device Show page header
- QR Code button appears on Rack Show page header
- Clicking button opens QrCodeDialog with correct entity data
- Button follows existing design patterns and styling

---

### Backend Layer

#### Task Group 3: Bulk QR Code Generation
**Dependencies:** Task Groups 1 and 2

- [x] 3.0 Complete bulk QR code generation
  - [x] 3.1 Write 5-8 focused tests for bulk QR code generation
    - Test bulk QR page loads for devices
    - Test bulk QR page loads for racks
    - Test PDF generation with multiple devices
    - Test PDF generation with multiple racks
    - Test filter by datacenter/room/row/rack hierarchy works
    - Test authorization (authenticated users with view access only)
    - Test PDF contains correct QR codes and labels
  - [x] 3.2 Create BulkQrCodeController
    - Location: `/app/Http/Controllers/BulkQrCodeController.php`
    - Follow pattern from BulkExportController
    - Methods: `create()`, `generate()`
    - Authorization: All authenticated users with view access (no special role required)
  - [x] 3.3 Create bulk QR code form request
    - Location: `/app/Http/Requests/GenerateBulkQrCodesRequest.php`
    - Validate entity_type (rack or device)
    - Validate optional filter parameters (datacenter_id, room_id, row_id, rack_id)
  - [x] 3.4 Install PDF generation library
    - Install appropriate PDF library (e.g., `barryvdh/laravel-dompdf` or `spatie/laravel-pdf`)
    - Configure for label sheet layouts
  - [x] 3.5 Create PDF generation service
    - Location: `/app/Services/QrCodePdfService.php`
    - Generate PDF with grid layout of QR code labels
    - Support Avery 5160 format (30 labels per page, 3 columns x 10 rows)
    - Each label includes QR code, name, and secondary label
    - QR codes generated at scannable size (minimum 1 inch / 25mm)
  - [x] 3.6 Create bulk QR code routes
    - Location: `/routes/web.php`
    - `GET /qr-codes/bulk` - Show bulk generation form
    - `POST /qr-codes/bulk` - Generate and download PDF
    - Place within authenticated middleware group
    - Use named routes for Wayfinder integration
  - [x] 3.7 Create Bulk QR Code Vue page
    - Location: `/resources/js/Pages/QrCodes/Bulk.vue`
    - Entity type selector (racks or devices)
    - Hierarchical filter options (datacenter/room/row/rack)
    - Follow pattern from BulkExport/Create.vue
    - Preview count of items to be included
    - Generate PDF button
  - [x] 3.8 Implement PDF download response
    - PDF download available immediately after generation
    - Filename format: `qr-codes-{entity_type}-{timestamp}.pdf`
    - Proper Content-Type headers for PDF
  - [x] 3.9 Ensure bulk QR code tests pass
    - Run ONLY the 5-8 tests written in 3.1
    - Verify bulk generation flow works end-to-end
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 5-8 tests written in 3.1 pass
- Bulk QR code page loads with entity type and filter options
- PDF generates correctly with multiple QR code labels
- Labels formatted for Avery 5160 or similar standard label sheets
- Filter options work for narrowing down items
- PDF downloads immediately after generation

---

### Testing

#### Task Group 4: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-3

- [x] 4.0 Review existing tests and fill critical gaps only
  - [x] 4.1 Review tests from Task Groups 1-3
    - Review the 6 tests written for QrCodeDialog (tests/Feature/QrCode/QrCodeDialogTest.php)
    - Review the 5 tests written for single item integration (tests/Feature/QrCode/SingleItemQrCodeIntegrationTest.php)
    - Review the 8 tests written for bulk generation (tests/Feature/QrCode/BulkQrCodeGenerationTest.php)
    - Total existing tests: 19 tests
  - [x] 4.2 Analyze test coverage gaps for QR Code feature only
    - Identify critical user workflows lacking test coverage
    - Focus ONLY on gaps related to QR code generation feature
    - Do NOT assess entire application test coverage
    - Prioritize end-to-end workflows over unit test gaps
  - [x] 4.3 Write up to 8 additional strategic tests maximum
    - Add tests for mobile scanning workflow (redirect to login, redirect after auth)
    - Test QR code URL stability (routes don't change unexpectedly)
    - Test label format options if multiple sizes implemented
    - Test edge cases: very long entity names, missing secondary labels
    - Skip performance tests and exhaustive accessibility tests
  - [x] 4.4 Run feature-specific tests only
    - Run ONLY tests related to QR code generation feature
    - Expected total: approximately 21-28 tests maximum
    - Do NOT run the entire application test suite
    - Verify critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 21-28 tests total)
- Critical user workflows for QR code generation are covered
- No more than 8 additional tests added when filling in testing gaps
- Testing focused exclusively on QR code generation feature requirements

---

## Execution Order

Recommended implementation sequence:

1. **Task Group 1: QR Code Dialog Component** - Build the core reusable dialog component first
2. **Task Group 2: Single Item QR Code Integration** - Integrate dialog into existing Show pages
3. **Task Group 3: Bulk QR Code Generation** - Build backend and frontend for bulk operations
4. **Task Group 4: Test Review and Gap Analysis** - Review and fill critical testing gaps

## Technical Notes

### QR Code Generation Approach
- Use client-side QR code generation for single items (immediate, no server roundtrip)
- Use server-side QR code generation for bulk PDF (consistent rendering across many items)

### Dependencies to Install
- Frontend: `qrcode` npm package for client-side QR generation
- Backend: PDF generation library (e.g., `barryvdh/laravel-dompdf`)

### URL Generation
- Use absolute URLs from window.location.origin for QR codes
- Follow existing Wayfinder patterns for route generation
- Example URL format: `https://rackaudit.test/devices/123`

### Authentication Flow
- QR codes link to authenticated routes
- Laravel's existing auth middleware handles redirect to login
- After login, users are automatically redirected to originally requested page

### Label Specifications
- Minimum QR code size: 1 inch / 25mm for reliable scanning
- Standard label sizes: 2" x 1" (small) and 2" x 2" (large)
- Avery 5160 format for bulk: 30 labels per page (3 columns x 10 rows)

### Files to Create
- `/resources/js/components/common/QrCodeDialog.vue`
- `/app/Http/Controllers/BulkQrCodeController.php`
- `/app/Http/Requests/GenerateBulkQrCodesRequest.php`
- `/app/Services/QrCodePdfService.php`
- `/resources/js/Pages/QrCodes/Bulk.vue`

### Files to Modify
- `/resources/js/Pages/Devices/Show.vue` - Add QR code button
- `/resources/js/Pages/Racks/Show.vue` - Add QR code button
- `/routes/web.php` - Add bulk QR code routes
