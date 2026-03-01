# Specification: QR Code Generation

## Goal
Enable users to generate, download, and print QR codes for racks and devices that link directly to their detail pages, supporting both individual and bulk generation workflows.

## User Stories
- As a datacenter technician, I want to print QR code labels for racks and devices so that I can quickly scan them with my phone to access their details
- As an IT manager, I want to bulk generate QR code labels for multiple items so that I can efficiently label new equipment

## Specific Requirements

**Single Item QR Code Generation**
- Add a "Generate QR Code" button to the Rack Show page header alongside existing Edit/Delete buttons
- Add a "Generate QR Code" button to the Device Show page header alongside existing Edit/Delete buttons
- Button click opens a dialog showing QR code preview with label information
- QR codes encode the full URL to the detail page (e.g., `https://rackaudit.test/devices/123`)
- Generate QR codes on-demand (not stored in database)

**QR Code Label Content**
- Label displays QR code at a scannable size (minimum 1 inch / 25mm)
- Label includes the item name prominently displayed
- For devices: include the asset_tag field below the name
- For racks: include the serial_number field if present
- Layout optimized for standard label sizes (2" x 1" and 2" x 2")

**Download Functionality**
- Provide PNG download option for QR code with label
- Provide SVG download option for high-resolution printing
- Download button generates the file client-side for immediate download
- Filename format: `{entity_type}-{id}-qr.{extension}` (e.g., `device-123-qr.png`)

**Browser Print Functionality**
- Print button opens browser print dialog with optimized print stylesheet
- Print layout shows QR code and label info without browser chrome
- Support both portrait and landscape orientations

**Bulk QR Code Generation**
- Add bulk QR code generation via a dedicated route/page similar to bulk export pattern
- User selects entity type (rack or device) and optional filters
- Generate PDF with multiple QR code labels per page (grid layout)
- Support standard label sheet formats (e.g., Avery 5160 - 30 labels per page)
- PDF download available immediately after generation

**Authentication and Scanning Workflow**
- QR codes link to authenticated routes (no public preview)
- When scanned, unauthenticated users are redirected to login
- After login, users are redirected to the originally requested detail page
- Existing Laravel authentication handles this redirect flow automatically

**Authorization**
- QR code generation available to all authenticated users with view access
- No separate permission required beyond existing view permissions
- Bulk generation follows same access rules as individual generation

## Visual Design

No visual mockups provided. Design should:
- Follow existing dialog pattern used by DeleteDeviceDialog and DeleteRackDialog
- Use consistent button styling with existing action buttons
- Match overall application design system (Tailwind CSS, shadcn/ui components)

## Existing Code to Leverage

**Dialog Component Pattern (`/Users/helderdene/rackaudit/resources/js/components/devices/DeleteDeviceDialog.vue`)**
- Reuse Dialog component structure for QR code preview modal
- Follow same pattern of DialogTrigger, DialogContent, DialogHeader, DialogFooter
- Use similar ref-based state management for dialog open/close

**Show Page Header Pattern (`/Users/helderdene/rackaudit/resources/js/Pages/Devices/Show.vue`, `/Users/helderdene/rackaudit/resources/js/Pages/Racks/Show.vue`)**
- Add QR code button in the existing button group in the header section
- Follow established button variant patterns (variant="outline" for secondary actions)
- Use Wayfinder for URL generation consistency

**Bulk Export Pattern (`/Users/helderdene/rackaudit/app/Http/Controllers/BulkExportController.php`)**
- Follow similar controller structure for bulk QR generation
- Reuse filter options pattern for datacenter/room/row/rack hierarchy
- Apply same authorization pattern (check for view access)

**Route Structure (`/Users/helderdene/rackaudit/routes/web.php`)**
- Follow existing nested route patterns for rack-scoped operations
- Use named routes for Wayfinder integration
- Place routes within authenticated middleware group

## Out of Scope
- Storing QR codes in the database (generate fresh each time)
- Custom branding or company logos on labels
- Integration with specific label printer APIs (Dymo, Zebra, Brother, etc.)
- QR codes for ports, connections, PDUs, or other entities beyond racks and devices
- Public preview pages for unauthenticated users
- QR code scanning functionality within the application (relies on device camera apps)
- Custom label sizes or templates beyond standard formats
- Batch printing to network printers directly from the application
- QR code history or tracking of when codes were generated
- Expiring or revocable QR codes
