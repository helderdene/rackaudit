# Specification: Main Dashboard

## Goal
Build a central overview dashboard that displays key infrastructure metrics (rack utilization, device counts, pending audits, open findings) with trend indicators and sparklines, a recent activity feed, and datacenter filtering - all scoped to the user's permitted datacenters.

## User Stories
- As an IT Manager, I want to see real-time metrics and trends across my datacenters so that I can monitor capacity and identify issues at a glance.
- As an Auditor, I want to see pending audits and open findings with severity breakdown so that I can prioritize my audit work effectively.

## Specific Requirements

**Dashboard Route and Controller**
- Replace the existing placeholder `Dashboard.vue` page with the new Main Dashboard implementation
- Create a new `DashboardController` with an `index` method to serve the dashboard
- Dashboard should be accessible at the existing `/dashboard` route
- Controller should gather all metrics data and pass to the Inertia page component

**Metric Summary Cards Section**
- Display four primary metric cards in a responsive grid at top of dashboard
- Cards include: Rack Utilization (%), Device Count (total), Pending Audits (count), Open Findings (count)
- Each card shows the main metric value prominently with a label
- Cards should be styled using existing `Card`, `CardHeader`, `CardContent`, `CardTitle` components
- Use a responsive grid layout: 1 column on mobile, 2 on tablet, 4 on desktop

**Trend Indicators on Metric Cards**
- Each metric card displays a trend indicator showing change from previous week
- Format: "+5%" or "-3%" with green/red color coding for positive/negative changes
- Show absolute change in parentheses (e.g., "+12 devices")
- Trend comparison period: current week vs previous week
- If insufficient historical data, show "N/A" for trend

**Sparkline Visualizations**
- Each metric card includes a small inline sparkline chart showing 7-day trend
- Sparklines should be simple line charts approximately 80px wide by 30px tall
- Use Chart.js for sparkline implementation (already available in tech stack)
- Sparklines show daily values for the past 7 days

**Datacenter Filter Dropdown**
- Add datacenter filter dropdown at top of dashboard
- Default shows "All Datacenters" (aggregated data across all accessible)
- Dropdown lists only datacenters the user has permission to access
- Selecting a datacenter filters all metrics to that datacenter only
- Filter persists via URL query parameter `?datacenter_id=`
- Use debounced navigation with Inertia `router.get()` pattern from existing dashboards

**Rack Utilization Metric**
- Calculate percentage of total U-space occupied across accessible racks
- Formula: (sum of device U-heights) / (sum of rack U-heights) * 100
- Physical space only (not power utilization)
- Query through Rack -> Row -> Room -> Datacenter hierarchy for permission filtering
- Include only racks with `status` of active (exclude decommissioned/planned)

**Open Findings with Severity Breakdown**
- Display total count of open findings (status: Open, InProgress, PendingReview, Deferred)
- Show sub-counts by severity: Critical, High, Medium, Low
- Use severity color badges matching `FindingSeverity` enum colors
- Clicking a severity badge could navigate to filtered findings list (nice-to-have)
- Reference existing severity badge styling in `Audits/Dashboard.vue`

**Pending Audits Count**
- Count audits with status `Pending` or `InProgress` (both count as "pending" for dashboard)
- Include audits from user's accessible datacenters only
- Optionally show past-due indicator if any pending audits are overdue

**Recent Activity Feed**
- Display the last 15 activity log entries in chronological order (most recent first)
- Show: timestamp (relative format), user name, action badge, entity type, brief summary
- Simple chronological feed, no filtering needed on dashboard
- Filter activities to only those related to accessible datacenters
- Use expandable row pattern from `ActivityLogs/Index.vue` for detail on click
- Consider using Inertia deferred props for activity feed to improve initial load

**User Permission Scoping**
- All metrics must be scoped to user's assigned datacenters via `User.datacenters()` relationship
- Admin roles (Administrator, IT Manager) see all datacenters
- Non-admin users see only datacenters in their `datacenter_user` pivot table
- Reuse existing permission pattern from `AuditController` with `ADMIN_ROLES` constant

## Visual Design
No visual mockups provided. Follow existing dashboard patterns from `Audits/Dashboard.vue`:
- Use `HeadingSmall` component for page header
- Consistent card styling with `Card` components
- Responsive grid layouts using Tailwind CSS
- Dark mode support with `dark:` variant classes
- Skeleton loading states for deferred content

## Existing Code to Leverage

**`resources/js/Pages/Audits/Dashboard.vue`**
- Comprehensive dashboard layout with metric cards and filtering
- Datacenter filter dropdown implementation with debounced navigation
- Severity badge styling in `getSeverityBadgeClass()` function
- Card-based metric display patterns

**`resources/js/components/Discrepancies/DiscrepancySummaryStats.vue`**
- Summary stats card pattern with type-based icons and counts
- Datacenter breakdown display pattern
- Click-to-filter interaction pattern

**`app/Http/Controllers/AuditController.php` (dashboard method)**
- Permission-based datacenter filtering pattern with `ADMIN_ROLES`
- Metric aggregation queries using Eloquent builder
- Filter parameter handling with Inertia response

**`resources/js/Pages/ActivityLogs/Index.vue`**
- Activity log display with timestamp formatting
- Expandable row pattern for activity details
- Action badge component usage

**`resources/js/components/ui/` component library**
- Card, Badge, Button, Input, Skeleton components
- Consistent styling patterns for the application

## Out of Scope
- Power utilization metrics (physical U-space only)
- Filterable activity feed on the dashboard
- Separate counts for in-progress vs scheduled audits
- Drill-down navigation from metrics to detail pages
- Real-time live updates (polling/websockets)
- Export functionality for dashboard data
- Customizable dashboard layouts or widgets
- Time period filter for dashboard metrics (current state only)
- Caching layer for metrics (can be added later for performance)
- Mobile-specific navigation or gestures
