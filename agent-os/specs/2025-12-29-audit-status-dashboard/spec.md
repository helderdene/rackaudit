# Specification: Audit Status Dashboard

## Goal
Create a dedicated dashboard page at `/audits/dashboard` that provides a comprehensive overview of audit progress, finding severity distribution, and resolution status metrics with filtering by datacenter and time period.

## User Stories
- As an IT Manager, I want to see an at-a-glance overview of all audit progress and findings so that I can prioritize resources and track resolution status
- As an Auditor, I want to drill down from summary metrics to specific audits and findings so that I can quickly navigate to items needing attention

## Specific Requirements

**Dashboard Route and Access**
- Create new route at `/audits/dashboard` with route name `audits.dashboard`
- Add new `dashboard` method to existing `AuditController`
- Accessible to users with Auditor, IT Manager, or Administrator roles
- Add navigation link in sidebar under Audits section

**Audit Progress Metrics Section**
- Display total audits count within the filtered period
- Show audits by status: Pending, In Progress, Completed, Cancelled
- Calculate and display completion percentage (Completed / Total)
- Display audits past due date (status not Completed and due_date < today)
- Display audits due soon (due_date within next 7 days, not yet overdue)

**Finding Severity Summary Section**
- Show overall finding totals grouped by severity: Critical, High, Medium, Low
- Display counts as numeric values with color-coded badges matching FindingSeverity enum colors
- Use a donut/pie chart visualization showing severity distribution
- Enable clicking on a severity to filter to that severity in the findings list

**Per-Audit Finding Breakdown**
- Display a collapsible/expandable table showing each audit with its finding counts by severity
- Include columns: Audit Name, Datacenter, Status, Critical, High, Medium, Low, Total
- Enable row click to navigate to the audit show page or filtered findings list
- Sort by total findings descending by default

**Resolution Status Metrics Section**
- Display open findings count (status: Open, InProgress, PendingReview, Deferred)
- Display resolved findings count (status: Resolved)
- Calculate resolution rate percentage (Resolved / Total)
- Calculate and display average time to resolve using Finding::getTotalResolutionTime()
- Display overdue findings count using Finding::scopeOverdue()

**Datacenter and Time Period Filtering**
- Add datacenter dropdown filter populated from user-accessible datacenters
- Add time period filter with presets: Last 30 days, Last 90 days, This quarter, This year, All time
- Store filter selections in URL query parameters for bookmarkable/shareable URLs
- Apply filters to all metrics, charts, and tables on the page

**Trend Chart Visualization**
- Display line chart showing audit completion count over time
- X-axis: time periods (days/weeks/months depending on selected range)
- Y-axis: count of completed audits
- Use Chart.js library for chart rendering

**Progress Bars for Active Audits**
- Display progress bars for audits with status InProgress
- Calculate progress as (completedVerifications / totalVerifications) for connection audits
- Calculate progress as (completedDeviceVerifications / totalDeviceVerifications) for inventory audits
- Show audit name, due date, and progress percentage

## Existing Code to Leverage

**Audit Model (app/Models/Audit.php)**
- Use existing `totalVerifications()`, `completedVerifications()` methods for progress calculation
- Use `totalDeviceVerifications()`, `pendingDeviceVerifications()` for inventory audit progress
- Leverage `datacenter()` relationship for filtering
- Use AuditStatus enum for status filtering and display

**Finding Model (app/Models/Finding.php)**
- Use `scopeOverdue()` for overdue findings count
- Use `scopeDueSoon()` for due soon findings (modify if needed for dashboard needs)
- Use `getTotalResolutionTime()` for average resolution time calculation
- Use FindingStatus and FindingSeverity enums for grouping and display colors

**Existing Vue Page Patterns (resources/js/Pages/Audits/Index.vue)**
- Follow same layout structure with AppLayout, breadcrumbs, HeadingSmall
- Replicate filter UI pattern with select dropdowns and query parameter handling
- Use same responsive card/table pattern for mobile and desktop views

**UI Components (resources/js/Components/ui/)**
- Use Card component for metric summary cards
- Use existing badge styling patterns from FindingSeverity::color() method
- Use Button, Input, select patterns from existing pages

**Datacenter Model and Filtering**
- Query datacenters user has access to via Datacenter::whereHas('users') relationship
- Follow existing datacenter filter patterns from other pages

## Out of Scope
- User-specific views (e.g., "my assigned findings" dashboard section)
- Export functionality (PDF/CSV export of dashboard data)
- Real-time updates via Laravel Echo or WebSockets
- Comparison with previous time periods (e.g., "vs last month")
- Custom dashboard configuration or widget arrangement
- Email notifications or scheduled reports
- Caching layer for dashboard metrics (can be added later if performance requires)
- Saving favorite/default filter configurations
- Audit creation or management from the dashboard page
- Finding status changes from the dashboard page
