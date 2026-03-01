# Task Breakdown: Asset Reports

## Overview
Total Tasks: 35 (across 5 task groups)

This feature provides comprehensive reporting on device inventory, warranty status, lifecycle distribution, and quantity-based asset valuation. It follows the established Capacity Reports pattern with similar controller, service, and component architecture.

## Task List

### Backend Foundation

#### Task Group 1: Backend Services and Calculation Logic
**Dependencies:** None

- [x] 1.0 Complete backend services layer
  - [x] 1.1 Write 6 focused tests for AssetCalculationService
    - Test warranty status categorization (active, expiring soon, expired, unknown)
    - Test lifecycle distribution counting
    - Test device counts by device type
    - Test device counts by manufacturer
    - Test filter application (datacenter, room, device type, lifecycle status, manufacturer)
    - Test date range filtering for warranty expiration
  - [x] 1.2 Create AssetCalculationService
    - File: `/Users/helderdene/rackaudit/app/Services/AssetCalculationService.php`
    - Follow pattern from: `/Users/helderdene/rackaudit/app/Services/CapacityCalculationService.php`
    - Methods to implement:
      - `getWarrantyStatusCounts(Builder $query): array` - categorize devices into 4 warranty groups
      - `getLifecycleDistribution(Builder $query): array` - count devices by DeviceLifecycleStatus
      - `getDeviceCountsByType(Builder $query): array` - group counts by DeviceType relationship
      - `getDeviceCountsByManufacturer(Builder $query): array` - group counts by manufacturer field
      - `getAssetMetrics(?int $datacenterId, ?int $roomId, ...filters): array` - aggregate all metrics
      - `buildFilteredDeviceQuery(...filters): Builder` - build query with all filter combinations
    - Use 30-day threshold constant for "expiring soon" warranty status
    - Include devices with null rack_id (non-racked devices)
  - [x] 1.3 Create AssetReportService for PDF generation
    - File: `/Users/helderdene/rackaudit/app/Services/AssetReportService.php`
    - Follow pattern from: `/Users/helderdene/rackaudit/app/Services/CapacityReportService.php`
    - Methods to implement:
      - `generatePdfReport(array $filters, User $generator): string`
      - `getDeviceInventory(?int $datacenterId, ...filters): Collection` - detailed device list
      - `buildFilterScope(array $filters): string` - human-readable filter description
      - `storeReport(PDF $pdf): string` - store in `reports/assets/` directory
    - Inject AssetCalculationService for metrics
    - Use Barryvdh\DomPDF\Facade\Pdf for PDF generation
  - [x] 1.4 Ensure backend services tests pass
    - Run ONLY the 6 tests written in 1.1
    - Verify warranty categorization logic works correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6 tests written in 1.1 pass
- AssetCalculationService correctly categorizes warranty status with 30-day threshold
- Lifecycle distribution returns counts for all 7 DeviceLifecycleStatus values
- Device counts group correctly by type and manufacturer
- Filter combinations work (datacenter, room, device type, lifecycle status, manufacturer, date range)
- Non-racked devices are included in all calculations

---

#### Task Group 2: Controller and Routes
**Dependencies:** Task Group 1

- [x] 2.0 Complete controller and routing layer
  - [x] 2.1 Write 6 focused tests for AssetReportController
    - Test index page returns correct Inertia response with all props
    - Test role-based datacenter access (admin vs restricted user)
    - Test cascading filter validation (datacenter -> room)
    - Test exportPdf generates and downloads PDF file
    - Test exportCsv generates and downloads CSV file
    - Test filter parameter validation and sanitization
  - [x] 2.2 Create AssetReportController
    - File: `/Users/helderdene/rackaudit/app/Http/Controllers/AssetReportController.php`
    - Follow pattern from: `/Users/helderdene/rackaudit/app/Http/Controllers/CapacityReportController.php`
    - Use ADMIN_ROLES constant pattern for role-based access
    - Methods to implement:
      - `index(Request $request): InertiaResponse` - main report page
      - `exportPdf(Request $request): StreamedResponse|BinaryFileResponse`
      - `exportCsv(Request $request): BinaryFileResponse`
    - Inject AssetCalculationService and AssetReportService via constructor
    - Private helper methods:
      - `getAccessibleDatacenters($user): Collection`
      - `validateDatacenterId(mixed $id, array $accessible): ?int`
      - `validateRoomId(mixed $id, ?int $datacenterId, array $valid): ?int`
      - `getRoomOptions(?int $datacenterId): Collection`
      - `getDeviceTypeOptions(): Collection`
      - `getLifecycleStatusOptions(): array`
      - `getManufacturerOptions(): Collection`
  - [x] 2.3 Create AssetReportExport class for CSV export
    - File: `/Users/helderdene/rackaudit/app/Exports/AssetReportExport.php`
    - Follow pattern from: `/Users/helderdene/rackaudit/app/Exports/CapacityReportExport.php`
    - Extend AbstractDataExport class
    - Column headers: Asset Tag, Name, Serial Number, Manufacturer, Model, Device Type, Lifecycle Status, Datacenter, Room, Rack, U Position, Warranty End Date
    - Include devices regardless of rack placement (show location as N/A for non-racked)
  - [x] 2.4 Register routes in web.php
    - File: `/Users/helderdene/rackaudit/routes/web.php`
    - Routes to add (within authenticated middleware group):
      - `GET /reports/assets` - AssetReportController@index (name: asset-reports.index)
      - `GET /reports/assets/export/pdf` - AssetReportController@exportPdf (name: asset-reports.export.pdf)
      - `GET /reports/assets/export/csv` - AssetReportController@exportCsv (name: asset-reports.export.csv)
  - [x] 2.5 Ensure controller tests pass
    - Run ONLY the 6 tests written in 2.1
    - Verify Inertia response contains all required props
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6 tests written in 2.1 pass
- Controller applies role-based access control correctly
- Cascading filters validate properly (room must belong to selected datacenter)
- PDF export generates downloadable file with correct filename format
- CSV export generates downloadable file with all device columns
- Routes are accessible and return expected responses

---

### Frontend Components

#### Task Group 3: Vue Page and Filter Components
**Dependencies:** Task Group 2

- [x] 3.0 Complete Vue page and filter components
  - [x] 3.1 Write 4 focused tests for frontend components
    - Test AssetFilters component renders all filter dropdowns
    - Test cascading filter behavior (selecting datacenter loads rooms)
    - Test filter application triggers Inertia request with correct params
    - Test AssetReports/Index page renders all report sections
  - [x] 3.2 Create AssetFilters component
    - File: `/Users/helderdene/rackaudit/resources/js/components/AssetReports/AssetFilters.vue`
    - Follow pattern from: `/Users/helderdene/rackaudit/resources/js/components/CapacityReports/CapacityFilters.vue`
    - Props interface:
      - `filters: { datacenter_id, room_id, device_type_id, lifecycle_status, manufacturer, warranty_start, warranty_end }`
      - `datacenters: FilterOption[]`
      - `rooms: FilterOption[]`
      - `deviceTypes: FilterOption[]`
      - `lifecycleStatuses: { value: string, label: string }[]`
      - `manufacturers: string[]`
    - Features:
      - Cascading datacenter -> room filters
      - Device type dropdown
      - Lifecycle status dropdown
      - Manufacturer dropdown
      - Warranty date range picker (start/end dates)
      - Clear all filters button
      - Mobile collapsible version
      - Debounced filter application (300ms)
    - Emit `filtering` event to parent for loading state
  - [x] 3.3 Create WarrantyStatusCards component
    - File: `/Users/helderdene/rackaudit/resources/js/components/AssetReports/WarrantyStatusCards.vue`
    - Display 4 metric cards for warranty categories:
      - Active (green theme)
      - Expiring Soon (amber/warning theme, prominently highlighted)
      - Expired (red theme)
      - Unknown (gray theme)
    - Props: `warrantyStatus: { active: number, expiring_soon: number, expired: number, unknown: number }`
    - Use Card, CardHeader, CardContent, CardTitle from ui/card
    - Include icons: Shield, AlertTriangle, XCircle, HelpCircle from lucide-vue-next
  - [x] 3.4 Create LifecycleDistributionChart component
    - File: `/Users/helderdene/rackaudit/resources/js/components/AssetReports/LifecycleDistributionChart.vue`
    - Pie chart using Chart.js (already in tech stack)
    - Props: `distribution: { status: string, label: string, count: number, percentage: number }[]`
    - Colors per status:
      - Ordered: blue (#3b82f6)
      - Received: cyan (#06b6d4)
      - In Stock: teal (#14b8a6)
      - Deployed: green (#22c55e)
      - Maintenance: amber (#f59e0b)
      - Decommissioned: orange (#f97316)
      - Disposed: gray (#6b7280)
    - Include legend with status labels and counts
    - Show percentages in chart segments
  - [x] 3.5 Create DeviceInventoryTable component
    - File: `/Users/helderdene/rackaudit/resources/js/components/AssetReports/DeviceInventoryTable.vue`
    - Props: `devices: Device[], loading: boolean`
    - Columns: Asset Tag, Name, Serial Number, Manufacturer, Model, Device Type, Lifecycle Status, Location (Datacenter > Room > Rack > U), Warranty End Date
    - Features:
      - Sortable columns (click header to sort)
      - Pagination controls
      - Loading skeleton state
      - Empty state when no devices
    - Location shows "N/A" or "Not Racked" for devices without rack placement
    - Warranty End Date shows formatted date or "Not tracked"
  - [x] 3.6 Create AssetCountTables component
    - File: `/Users/helderdene/rackaudit/resources/js/components/AssetReports/AssetCountTables.vue`
    - Two side-by-side summary tables:
      - Counts by Device Type (device type name, count)
      - Counts by Manufacturer (manufacturer name, count)
    - Props: `countsByType: { name: string, count: number }[], countsByManufacturer: { name: string, count: number }[]`
    - Use responsive grid layout (stack on mobile, side-by-side on desktop)
  - [x] 3.7 Create component index file
    - File: `/Users/helderdene/rackaudit/resources/js/components/AssetReports/index.ts`
    - Export all components for clean imports
  - [x] 3.8 Ensure frontend component tests pass
    - Run ONLY the 4 tests written in 3.1
    - Verify components render correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4 tests written in 3.1 pass
- AssetFilters renders all filter types with cascading behavior
- WarrantyStatusCards displays 4 categories with appropriate styling
- LifecycleDistributionChart renders pie chart with correct colors
- DeviceInventoryTable displays device data with sorting and pagination
- AssetCountTables shows counts grouped by type and manufacturer

---

#### Task Group 4: Main Page and PDF Template
**Dependencies:** Task Group 3

- [x] 4.0 Complete main page and PDF template
  - [x] 4.1 Write 4 focused tests for page integration
    - Test full page renders with all sections (inventory, warranty, lifecycle, counts)
    - Test export buttons generate correct URLs with filter params
    - Test loading skeleton displays during filter changes
    - Test empty state displays when no devices match filters
  - [x] 4.2 Create AssetReports/Index.vue page (already exists, verified complete)
    - File: `/Users/helderdene/rackaudit/resources/js/Pages/AssetReports/Index.vue`
    - Follow pattern from: `/Users/helderdene/rackaudit/resources/js/Pages/CapacityReports/Index.vue`
    - Props interface:
      ```typescript
      interface Props {
        metrics: {
          warrantyStatus: { active: number, expiring_soon: number, expired: number, unknown: number },
          lifecycleDistribution: { status: string, label: string, count: number, percentage: number }[],
          countsByType: { name: string, count: number }[],
          countsByManufacturer: { name: string, count: number }[]
        },
        devices: Device[],
        pagination: { current_page: number, last_page: number, per_page: number, total: number },
        datacenterOptions: FilterOption[],
        roomOptions: FilterOption[],
        deviceTypeOptions: FilterOption[],
        lifecycleStatusOptions: { value: string, label: string }[],
        manufacturerOptions: string[],
        filters: Filters
      }
      ```
    - Layout sections:
      1. Header with title "Asset Reports" and ExportButtons
      2. AssetFilters component
      3. WarrantyStatusCards (4 metric cards)
      4. LifecycleDistributionChart (pie chart)
      5. AssetCountTables (by type and manufacturer)
      6. DeviceInventoryTable (full device list with pagination)
    - Use deferred props pattern for devices list (large dataset)
    - Show skeleton loading state during filtering
    - Use AppLayout with breadcrumbs
    - Import and reuse ExportButtons from CapacityReports
  - [x] 4.3 Create PDF template for asset report (already exists with all required sections)
    - File: `/Users/helderdene/rackaudit/resources/views/pdf/asset-report.blade.php`
    - Follow pattern from: `/Users/helderdene/rackaudit/resources/views/pdf/capacity-report.blade.php`
    - Sections:
      1. Header with report title, filter scope, generation info
      2. Warranty Status Summary (counts for each category)
      3. Lifecycle Distribution (table with counts and percentages)
      4. Asset Counts by Device Type (table)
      5. Asset Counts by Manufacturer (table)
      6. Device Inventory List (paginated table with key fields)
    - Use consistent styling with existing PDF templates
    - Include footer with page numbers
  - [x] 4.4 Add navigation link to Asset Reports
    - File: `/Users/helderdene/rackaudit/resources/js/components/AppSidebar.vue`
    - Add "Asset Reports" link under Reports section
    - Use Package icon from lucide-vue-next
  - [x] 4.5 Run Wayfinder generation
    - Command: `php artisan wayfinder:generate`
    - Generates TypeScript functions for new controller routes
  - [x] 4.6 Ensure page integration tests pass
    - Run ONLY the 4 tests written in 4.1
    - Verify page renders all sections correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4 tests written in 4.1 pass
- Main page displays all report sections with correct data
- Export buttons generate URLs with current filter parameters
- PDF template generates formatted multi-section report
- Navigation includes link to Asset Reports page
- Wayfinder TypeScript functions are generated for new routes

---

### Testing

#### Task Group 5: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-4

- [x] 5.0 Review existing tests and fill critical gaps only
  - [x] 5.1 Review tests from Task Groups 1-4
    - Review the 6 tests written for AssetCalculationService (Task 1.1)
    - Review the 6 tests written for AssetReportController (Task 2.1)
    - Review the 4 tests written for frontend components (Task 3.1)
    - Review the 4 tests written for page integration (Task 4.1)
    - Total existing tests: approximately 20 tests
  - [x] 5.2 Analyze test coverage gaps for Asset Reports feature only
    - Identify critical user workflows that lack test coverage
    - Focus ONLY on gaps related to this spec's feature requirements
    - Do NOT assess entire application test coverage
    - Prioritize end-to-end workflows over unit test gaps
    - Key workflows to verify coverage:
      - Complete filter-to-report workflow
      - Warranty expiration date boundary conditions (30-day threshold)
      - PDF export with various filter combinations
      - CSV export with all device fields
      - Role-based access control for different user types
  - [x] 5.3 Write up to 8 additional strategic tests maximum
    - Add maximum of 8 new tests to fill identified critical gaps
    - Suggested gap tests (implement only if missing):
      - Test warranty 30-day boundary (day 29, 30, 31 from today)
      - Test empty state when no devices exist
      - Test non-racked devices appear in inventory
      - Test filter combinations (multiple filters applied simultaneously)
      - Test PDF includes all sections with filter applied
      - Test CSV contains correct headers and data format
      - Test admin sees all datacenters, restricted user sees only assigned
      - Test lifecycle status filter returns only matching devices
    - Do NOT write comprehensive coverage for all scenarios
    - Skip edge cases, performance tests unless business-critical
  - [x] 5.4 Run feature-specific tests only
    - Run ONLY tests related to Asset Reports feature
    - Command: `php artisan test --filter=AssetReport`
    - Expected total: approximately 20-28 tests maximum
    - Do NOT run the entire application test suite
    - Verify critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 20-28 tests total)
- Critical user workflows for Asset Reports are covered
- No more than 8 additional tests added when filling in testing gaps
- Testing focused exclusively on Asset Reports feature requirements
- Warranty 30-day threshold boundary is tested
- Both racked and non-racked devices are tested in inventory

---

## Execution Order

Recommended implementation sequence:

1. **Backend Services (Task Group 1)** - Create AssetCalculationService and AssetReportService
2. **Controller and Routes (Task Group 2)** - Create AssetReportController, export class, and routes
3. **Vue Components (Task Group 3)** - Create filter, metric, chart, and table components
4. **Main Page and PDF (Task Group 4)** - Assemble page, create PDF template, add navigation
5. **Test Review (Task Group 5)** - Review coverage and fill critical gaps

## Files to Create

| File | Description |
|------|-------------|
| `app/Services/AssetCalculationService.php` | Metrics calculation logic |
| `app/Services/AssetReportService.php` | PDF report generation |
| `app/Http/Controllers/AssetReportController.php` | Main controller |
| `app/Exports/AssetReportExport.php` | CSV export class |
| `resources/views/pdf/asset-report.blade.php` | PDF template |
| `resources/js/Pages/AssetReports/Index.vue` | Main page |
| `resources/js/components/AssetReports/AssetFilters.vue` | Filter component |
| `resources/js/components/AssetReports/WarrantyStatusCards.vue` | Warranty metrics |
| `resources/js/components/AssetReports/LifecycleDistributionChart.vue` | Pie chart |
| `resources/js/components/AssetReports/DeviceInventoryTable.vue` | Device table |
| `resources/js/components/AssetReports/AssetCountTables.vue` | Summary tables |
| `resources/js/components/AssetReports/index.ts` | Component exports |
| `tests/Feature/AssetReportControllerTest.php` | Controller tests |
| `tests/Feature/AssetCalculationServiceTest.php` | Service tests |

## Files to Modify

| File | Change |
|------|--------|
| `routes/web.php` | Add asset report routes |
| Navigation component | Add Asset Reports link |

## Key Patterns to Follow

- **Controller**: `/Users/helderdene/rackaudit/app/Http/Controllers/CapacityReportController.php`
- **Services**: `/Users/helderdene/rackaudit/app/Services/CapacityCalculationService.php`, `/Users/helderdene/rackaudit/app/Services/CapacityReportService.php`
- **Vue Page**: `/Users/helderdene/rackaudit/resources/js/Pages/CapacityReports/Index.vue`
- **Filters Component**: `/Users/helderdene/rackaudit/resources/js/components/CapacityReports/CapacityFilters.vue`
- **Export Buttons**: `/Users/helderdene/rackaudit/resources/js/components/CapacityReports/ExportButtons.vue` (reuse directly)
- **Export Class**: `/Users/helderdene/rackaudit/app/Exports/CapacityReportExport.php`
- **PDF Template**: `/Users/helderdene/rackaudit/resources/views/pdf/capacity-report.blade.php`
- **Device Model**: `/Users/helderdene/rackaudit/app/Models/Device.php`
- **DeviceLifecycleStatus Enum**: `/Users/helderdene/rackaudit/app/Enums/DeviceLifecycleStatus.php`
