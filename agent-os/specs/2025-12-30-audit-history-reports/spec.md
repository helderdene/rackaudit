# Specification: Audit History Reports

## Goal
Provide a historical view of completed audits with trend analysis for finding counts by severity and resolution time metrics, enabling users to track audit performance over time and identify patterns in datacenter compliance.

## User Stories
- As an IT Manager, I want to view historical audit trends so that I can assess compliance improvements and identify recurring issues across datacenters.
- As an Administrator, I want to export audit history data so that I can share reports with stakeholders and maintain compliance documentation.

## Specific Requirements

**Page Layout and Structure**
- Follow the same layout pattern as CapacityReports/Index.vue: header with title/description and export buttons, filters section, metric cards row, trend charts section, and detailed data table
- Page title: "Audit History Reports"
- Description: "View historical audit trends, finding counts by severity, and resolution time metrics."
- Create page at `resources/js/pages/AuditHistoryReports/Index.vue`

**Time Range Filter**
- Implement preset time range options: "Last 30 days", "Last 6 months", "Last year"
- Add custom date range picker with start/end date inputs
- Default selection: Last 12 months
- Store time range in URL query parameters for shareable links
- Apply time range filter to audit completion dates (audits with status = Completed within the range)

**Datacenter and Audit Type Filters**
- Datacenter filter dropdown using existing `getAccessibleDatacenters()` pattern from CapacityReportController
- Audit type filter with options: "All Types", "Connection", "Inventory" (using `AuditType` enum values)
- Filters apply to all metrics, charts, and table data
- Use debounced filter application with Inertia router preserving scroll/state

**Summary Metric Cards**
- Display 4 metric cards in responsive grid (1 col mobile, 2 col tablet, 4 col desktop)
- Card 1: Total Audits Completed - count of completed audits in period with sparkline showing weekly/monthly trend
- Card 2: Total Findings - sum of all findings from completed audits with severity breakdown badges (Critical/High/Medium/Low counts)
- Card 3: Avg Resolution Time - mean `getTotalResolutionTime()` across resolved findings, formatted as hours/days (use `Finding::getTotalResolutionTime()`)
- Card 4: Avg Time to First Response - mean `getTimeToFirstResponse()` across findings with InProgress transition (use `Finding::getTimeToFirstResponse()`)

**Finding Trend Chart (Stacked Area)**
- Chart.js stacked area/line chart showing finding counts over time grouped by severity
- X-axis: time periods (weekly for 30-day view, monthly for 6-month/year views)
- Y-axis: finding count
- 4 stacked series with severity-specific colors: Critical (red), High (orange), Medium (yellow), Low (blue)
- Include legend and interactive tooltips showing breakdown per period
- Create component at `resources/js/components/AuditHistoryReports/FindingTrendChart.vue`

**Resolution Time Trend Chart**
- Chart.js line chart showing average resolution time trend over time
- Two lines: Average Resolution Time and Average Time to First Response
- X-axis: time periods matching the finding trend chart
- Y-axis: time in hours or days (auto-scale based on values)
- Different colors for each metric line (e.g., blue for resolution, green for first response)
- Create component at `resources/js/components/AuditHistoryReports/ResolutionTimeTrendChart.vue`

**Detailed Audit History Table**
- Server-side paginated table (15 rows per page) using Inertia pagination
- Columns: Audit Name (link to audit detail), Type (Connection/Inventory badge), Datacenter, Completion Date, Total Findings, Critical/High/Medium/Low counts, Avg Resolution Time
- Sortable by completion date (default descending), total findings, and resolution time
- Create component at `resources/js/components/AuditHistoryReports/AuditHistoryTable.vue`

**PDF Export**
- Generate PDF with summary metrics, both trend charts rendered as images, and top audits table
- Follow `CapacityReportService` pattern: use Barryvdh\DomPDF with Blade template
- Include filter scope description, generation timestamp, and generator name
- Store in `reports/audit-history/` directory
- Create service at `app/Services/AuditHistoryReportService.php`

**CSV Export**
- Export raw audit data following `CapacityReportExport` pattern extending `AbstractDataExport`
- Columns: Audit Name, Type, Datacenter, Completion Date, Total Findings, Critical, High, Medium, Low, Avg Resolution Time (hours)
- Include all audits matching current filters (not paginated)
- Create export at `app/Exports/AuditHistoryReportExport.php`

**Controller and Routes**
- Create `AuditHistoryReportController` with methods: `index()`, `exportPdf()`, `exportCsv()`
- Register routes: GET `/reports/audit-history`, GET `/reports/audit-history/export/pdf`, GET `/reports/audit-history/export/csv`
- Apply user datacenter access filtering using same `ADMIN_ROLES` pattern as CapacityReportController
- Create form request for validation: `AuditHistoryReportRequest`

## Existing Code to Leverage

**CapacityReportController.php and CapacityReports/Index.vue**
- Reuse the overall page structure: header with HeadingSmall, export buttons, filters card, metrics grid, trend charts section, and detailed table
- Copy the user datacenter access pattern using `getAccessibleDatacenters()` method and `ADMIN_ROLES` constant
- Follow the same Inertia data structure with metrics, filter options, filters, historical data, and trend data props

**DashboardController.php metric calculation patterns**
- Use `calculateTrend()` method pattern for computing week-over-week or period-over-period changes
- Use `generateSparklineData()` pattern for creating sparkline arrays for metric cards
- Adapt severity breakdown aggregation from `getOpenFindingsMetric()` using `FindingSeverity::cases()`

**Finding model methods**
- Use `Finding::getTimeToFirstResponse()` which calculates minutes from creation to first Open->InProgress transition via `FindingStatusTransition`
- Use `Finding::getTotalResolutionTime()` which calculates minutes from creation to `resolved_at` timestamp
- Query resolved findings using `whereNotNull('resolved_at')` and status `FindingStatus::Resolved`

**CapacityReports components**
- Extend or copy `HistoricalTrendChart.vue` for the new charts, modifying for stacked area support and multiple datasets
- Reuse `ExportButtons.vue` component directly - accepts pdfUrl and csvUrl props
- Adapt `CapacityMetricCard.vue` pattern for displaying metrics with sparklines and trend indicators
- Create similar filter component following `CapacityFilters.vue` structure but with time range and audit type filters

**CapacityReportService.php and CapacityReportExport.php**
- Follow `generatePdfReport()` pattern: calculate metrics, format data, load Blade view, store PDF
- Follow `AbstractDataExport` extension pattern with `headings()`, `title()`, `query()`, and `transformRow()` methods
- Use same file storage and download response patterns

## Out of Scope
- Real-time audit data streaming or live updates while viewing the page
- Drill-down from charts to individual finding details (charts are summary views only)
- Custom chart configuration or dashboard customization by users
- Email scheduling or automated report delivery
- Comparison between specific audits (focus is on aggregate trends)
- Finding-level detail export (export is audit-level summary)
- Mobile-specific chart interactions beyond responsive sizing
- Historical data backfill for audits completed before FindingStatusTransition tracking existed
- User-specific saved filter presets or report bookmarks
- Integration with external business intelligence tools
