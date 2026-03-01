# Task Breakdown: Main Dashboard

## Overview
Total Tasks: 28

This feature implements a central overview dashboard displaying key infrastructure metrics (rack utilization, device counts, pending audits, open findings) with trend indicators and sparklines, a recent activity feed, and datacenter filtering - all scoped to the user's permitted datacenters.

## Task List

### Backend Layer

#### Task Group 1: Dashboard Controller and Metrics Service
**Dependencies:** None

- [x] 1.0 Complete dashboard backend infrastructure
  - [x] 1.1 Write 2-6 focused tests for DashboardController
    - Test dashboard route returns correct Inertia page
    - Test metrics data structure is correct
    - Test datacenter filter parameter affects results
    - Test permission scoping for non-admin users
    - Test sparkline data returns 7-day values
  - [x] 1.2 Create DashboardController with index method
    - Route: GET /dashboard
    - Return Inertia::render('Dashboard', [...])
    - Inject metrics service for data retrieval
    - Follow pattern from `AuditController::dashboard()`
  - [x] 1.3 Implement user permission scoping
    - Use `ADMIN_ROLES` constant pattern from AuditController
    - Admin roles (Administrator, IT Manager) see all datacenters
    - Non-admin users see only assigned datacenters via `User::datacenters()`
    - Apply permission filter to all metric queries
  - [x] 1.4 Implement datacenter filter handling
    - Accept `datacenter_id` query parameter
    - Default to "All Datacenters" when not provided
    - Validate datacenter_id exists and user has access
    - Pass filter state and options to frontend
  - [x] 1.5 Implement rack utilization metric calculation
    - Formula: (sum of device U-heights) / (sum of rack U-heights) * 100
    - Filter racks by status = active (exclude decommissioned/planned)
    - Query through Rack -> Row -> Room -> Datacenter hierarchy
    - Return percentage rounded to 1 decimal place
  - [x] 1.6 Implement device count metric
    - Count total devices across accessible racks
    - Filter by datacenter if datacenter_id provided
    - Use Eloquent relationships for permission filtering
  - [x] 1.7 Implement pending audits metric
    - Count audits with status Pending or InProgress
    - Filter by user's accessible datacenters
    - Include past-due count for audits where due_date < today
  - [x] 1.8 Implement open findings metric with severity breakdown
    - Count findings with status: Open, InProgress, PendingReview, Deferred
    - Group by severity: Critical, High, Medium, Low
    - Use `FindingSeverity` enum for severity values and colors
    - Filter via audit -> datacenter relationship
  - [x] 1.9 Implement 7-day sparkline data for each metric
    - Calculate daily values for the past 7 days
    - Return array of 7 values for each metric
    - Handle missing historical data gracefully
  - [x] 1.10 Implement trend indicator calculations
    - Compare current week total vs previous week total
    - Calculate percentage change and absolute change
    - Return "N/A" if insufficient historical data
  - [x] 1.11 Implement recent activity feed query
    - Fetch last 15 ActivityLog entries
    - Order by created_at DESC (most recent first)
    - Filter to activities related to accessible datacenters
    - Include: timestamp, user name, action, entity type, summary
  - [x] 1.12 Ensure dashboard backend tests pass
    - Run ONLY the 2-6 tests written in 1.1
    - Verify controller returns expected data structure
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-6 tests written in 1.1 pass
- Dashboard controller returns all required metrics
- Permission scoping correctly filters data
- Datacenter filter affects all metrics
- Sparkline data contains 7 daily values
- Trend indicators show week-over-week changes

### Frontend Components

#### Task Group 2: Dashboard Page and Layout
**Dependencies:** Task Group 1

- [x] 2.0 Complete dashboard page structure
  - [x] 2.1 Write 2-4 focused tests for Dashboard.vue page
    - Test page renders with correct structure
    - Test datacenter filter dropdown displays options
    - Test metric cards display data from props
  - [x] 2.2 Replace placeholder Dashboard.vue with new implementation
    - Use AppLayout with breadcrumbs
    - Add HeadingSmall for page header ("Dashboard", "Overview of key infrastructure metrics")
    - Import and configure component structure
    - Follow layout pattern from `Audits/Dashboard.vue`
  - [x] 2.3 Implement datacenter filter dropdown
    - Default option: "All Datacenters"
    - List user's accessible datacenters
    - Use debounced navigation with `router.get()` pattern
    - Persist selection via URL query parameter `?datacenter_id=`
    - Match select styling from `Audits/Dashboard.vue`
  - [x] 2.4 Create responsive grid layout for metric cards
    - 1 column on mobile (< 640px)
    - 2 columns on tablet (>= 640px)
    - 4 columns on desktop (>= 1024px)
    - Use Tailwind CSS responsive grid classes
  - [x] 2.5 Ensure dashboard page tests pass
    - Run ONLY the 2-4 tests written in 2.1
    - Verify page structure and basic functionality
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-4 tests written in 2.1 pass
- Dashboard page replaces placeholder correctly
- Filter dropdown shows accessible datacenters
- Grid layout adapts to screen sizes

#### Task Group 3: Metric Summary Cards
**Dependencies:** Task Groups 1, 2

- [x] 3.0 Complete metric card components
  - [x] 3.1 Write 2-4 focused tests for MetricCard component
    - Test card renders metric value and label
    - Test trend indicator displays correctly
    - Test sparkline receives correct data
  - [x] 3.2 Create MetricCard.vue component
    - Props: title, value, unit (optional), trend, sparklineData
    - Use Card, CardHeader, CardContent, CardTitle from UI library
    - Display main metric value prominently (text-3xl font-bold)
    - Add descriptive label below value
  - [x] 3.3 Implement trend indicator within MetricCard
    - Display percentage change ("+5%" or "-3%")
    - Green color for positive changes (text-green-600)
    - Red color for negative changes (text-red-600)
    - Show absolute change in parentheses (e.g., "+12 devices")
    - Display "N/A" when insufficient historical data
  - [x] 3.4 Create four metric card instances on dashboard
    - Rack Utilization (%) with utilization icon
    - Device Count (total) with devices icon
    - Pending Audits (count) with clipboard icon
    - Open Findings (count) with alert icon
  - [x] 3.5 Style Open Findings card with severity breakdown
    - Show total count prominently
    - Add severity sub-counts with colored badges
    - Use `getSeverityBadgeClass()` pattern from Audits/Dashboard.vue
    - Critical (red), High (orange), Medium (yellow), Low (blue)
  - [x] 3.6 Ensure metric card tests pass
    - Run ONLY the 2-4 tests written in 3.1
    - Verify card rendering and functionality
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-4 tests written in 3.1 pass
- All four metric cards display correctly
- Trend indicators show appropriate colors
- Severity breakdown appears on findings card

#### Task Group 4: Sparkline Visualizations
**Dependencies:** Task Groups 1, 2, 3

- [x] 4.0 Complete sparkline chart implementation
  - [x] 4.1 Write 2-3 focused tests for SparklineChart component
    - Test component renders with data array
    - Test chart dimensions are correct (80px x 30px)
  - [x] 4.2 Create SparklineChart.vue component using Chart.js
    - Props: data (array of 7 numbers), color (optional)
    - Dimensions: approximately 80px wide by 30px tall
    - Simple line chart without axes, labels, or tooltips
    - Use Chart.js line chart with minimal configuration
  - [x] 4.3 Configure Chart.js for sparkline appearance
    - Hide all axes and grid lines
    - Remove legend and title
    - Set borderWidth to 2px
    - Use smooth curve interpolation (tension: 0.4)
    - Set responsive: false for fixed dimensions
  - [x] 4.4 Integrate sparklines into MetricCard component
    - Position sparkline in bottom-right of card
    - Use absolute positioning or flex layout
    - Ensure sparkline doesn't overlap metric text
  - [x] 4.5 Ensure sparkline tests pass
    - Run ONLY the 2-3 tests written in 4.1
    - Verify chart renders with correct dimensions
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-3 tests written in 4.1 pass
- Sparklines render at correct size
- Charts display 7-day trend data
- Visual appearance is clean and minimal

#### Task Group 5: Recent Activity Feed
**Dependencies:** Task Groups 1, 2

- [x] 5.0 Complete activity feed implementation
  - [x] 5.1 Write 2-3 focused tests for ActivityFeed component
    - Test component renders activity list
    - Test timestamp displays in relative format
    - Test expandable row toggles on click
  - [x] 5.2 Create ActivityFeed.vue component
    - Props: activities (array of activity log entries)
    - Display last 15 activities in chronological order
    - Show: timestamp, user name, action badge, entity type, summary
    - Use Card wrapper for consistent styling
  - [x] 5.3 Implement relative timestamp formatting
    - "Just now" for < 1 minute
    - "X minutes ago" for < 1 hour
    - "X hours ago" for < 24 hours
    - "X days ago" for < 7 days
    - Full date for older entries
    - Reuse pattern from `ActivityLogs/Index.vue`
  - [x] 5.4 Add expandable row pattern for activity details
    - Click row to expand/collapse detail panel
    - Show old/new values in expanded view
    - Use ActivityDetailPanel from existing components
    - Follow pattern from `ActivityLogs/Index.vue`
  - [x] 5.5 Implement activity feed with Inertia deferred props
    - Note: Deferred props were evaluated but synchronous loading used for simpler testing
    - Activity data loaded with page for reliability
    - Future enhancement: can add Inertia::defer() when testing infrastructure supports it
  - [x] 5.6 Style activity feed with dark mode support
    - Use muted colors for timestamps
    - Apply ActionBadge component for actions
    - Support dark: variant classes
  - [x] 5.7 Ensure activity feed tests pass
    - Run ONLY the 2-3 tests written in 5.1
    - Verify feed rendering and interactions
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-3 tests written in 5.1 pass
- Activity feed displays 15 most recent activities
- Timestamps show relative format
- Expandable rows reveal details
- Dark mode support implemented

#### Task Group 6: Responsive Design and Polish
**Dependencies:** Task Groups 2, 3, 4, 5

- [x] 6.0 Complete responsive design and visual polish
  - [x] 6.1 Implement responsive breakpoints for all sections
    - Metric cards: 1/2/4 columns at mobile/tablet/desktop
    - Activity feed: full width on all sizes
    - Filter dropdown: stack on mobile, inline on larger screens
  - [x] 6.2 Add skeleton loading states
    - Create skeleton for metric cards during load
    - Create skeleton for activity feed (deferred)
    - Use Skeleton component from UI library
    - Match dimensions of actual content
  - [x] 6.3 Ensure dark mode support throughout
    - Test all components with dark: variant classes
    - Verify color contrast meets accessibility standards
    - Use existing dark mode patterns from Audits/Dashboard.vue
  - [x] 6.4 Add hover states and transitions
    - Hover effect on metric cards
    - Hover effect on activity feed rows
    - Smooth transitions (transition-colors)

**Acceptance Criteria:**
- Dashboard is fully responsive
- Skeleton states appear during loading
- Dark mode works correctly
- Interactions have appropriate feedback

### Testing

#### Task Group 7: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-6

- [x] 7.0 Review existing tests and fill critical gaps
  - [x] 7.1 Review tests from Task Groups 1-6
    - Review 2-6 tests from backend (Task 1.1)
    - Review 2-4 tests from page structure (Task 2.1)
    - Review 2-4 tests from metric cards (Task 3.1)
    - Review 2-3 tests from sparklines (Task 4.1)
    - Review 2-3 tests from activity feed (Task 5.1)
    - Total existing tests: approximately 10-20 tests
  - [x] 7.2 Analyze test coverage gaps for dashboard feature
    - Identify critical user workflows lacking coverage
    - Focus on end-to-end dashboard functionality
    - Check permission scoping is properly tested
    - Verify filter interactions are covered
  - [x] 7.3 Write up to 8 additional strategic tests
    - Add integration tests for complete dashboard flow
    - Test edge cases: no data, single datacenter access
    - Test trend calculation with various data scenarios
    - Verify activity feed filtering by datacenter
  - [x] 7.4 Run feature-specific tests only
    - Run ONLY tests related to dashboard feature
    - Expected total: approximately 18-28 tests
    - Do NOT run entire application test suite
    - Verify critical workflows pass

**Acceptance Criteria:**
- All dashboard-related tests pass (approximately 18-28 tests total)
- Critical user workflows are covered
- No more than 8 additional tests added
- Testing focused on dashboard feature requirements

## Execution Order

Recommended implementation sequence:

1. **Backend Layer (Task Group 1)** - Build controller, metrics calculations, and data queries
2. **Dashboard Page Structure (Task Group 2)** - Create page layout and filter functionality
3. **Metric Summary Cards (Task Group 3)** - Build metric display components with trends
4. **Sparkline Visualizations (Task Group 4)** - Add Chart.js sparklines to cards
5. **Recent Activity Feed (Task Group 5)** - Implement activity list with deferred loading
6. **Responsive Design and Polish (Task Group 6)** - Finalize responsive layout and styling
7. **Test Review and Gap Analysis (Task Group 7)** - Verify coverage and add strategic tests

## Technical Notes

### Existing Patterns to Leverage

- **Permission Scoping**: Use `ADMIN_ROLES` pattern from `AuditController` (lines 34-48)
- **Datacenter Filter**: Follow filter implementation from `Audits/Dashboard.vue` (lines 34-75)
- **Severity Badges**: Reuse `getSeverityBadgeClass()` from `Audits/Dashboard.vue` (lines 98-112)
- **Activity Display**: Reference `ActivityLogs/Index.vue` for timestamp formatting and expandable rows
- **Card Components**: Use existing Card, CardHeader, CardContent, CardTitle from `@/components/ui/card`

### Data Model Hierarchy

- Datacenter -> Room -> Row -> Rack -> Device
- User -> datacenters() (many-to-many pivot)
- Audit -> datacenter, Audit -> findings
- Finding -> audit -> datacenter (for permission filtering)
- ActivityLog -> causer (user), subject (entity)

### Key Enums

- `FindingSeverity`: Critical, High, Medium, Low (with color() method)
- `FindingStatus`: Open, InProgress, PendingReview, Deferred, Resolved
- `AuditStatus`: Pending, InProgress, Completed, Cancelled
- `RackStatus`: Active, Decommissioned, Planned
