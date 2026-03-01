# Task Breakdown: Audit Status Dashboard

## Overview
Total Tasks: 42

This feature creates a dedicated dashboard page at `/audits/dashboard` that provides a comprehensive overview of audit progress, finding severity distribution, and resolution status metrics with filtering by datacenter and time period.

## Task List

### Backend Layer

#### Task Group 1: Dashboard Controller Method and Route
**Dependencies:** None

- [x] 1.0 Complete dashboard backend foundation
  - [x] 1.1 Write 4-6 focused tests for dashboard controller functionality
    - Test dashboard route accessible to Auditor, IT Manager, and Administrator roles
    - Test dashboard route forbidden for Operator and unauthenticated users
    - Test datacenter filtering applies correctly to metrics
    - Test time period filtering applies correctly to metrics
    - Test query parameter persistence in response
  - [x] 1.2 Add `dashboard` method to `AuditController`
    - Return Inertia::render('Audits/Dashboard', [...])
    - Follow existing pattern from `index` method in `app/Http/Controllers/AuditController.php`
    - Include authorization check for Auditor, IT Manager, Administrator roles
  - [x] 1.3 Add dashboard route to `routes/web.php`
    - Route: `GET /audits/dashboard`
    - Name: `audits.dashboard`
    - Place before resource routes to avoid route conflicts
    - Use same auth middleware as other audit routes
  - [x] 1.4 Ensure dashboard backend tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify route registration works
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- Dashboard route is accessible at `/audits/dashboard`
- Role-based access control works correctly
- Route named `audits.dashboard` resolves correctly

---

#### Task Group 2: Dashboard Data Aggregation Queries
**Dependencies:** Task Group 1

- [x] 2.0 Complete dashboard data aggregation logic
  - [x] 2.1 Write 6-8 focused tests for dashboard metric calculations
    - Test audit progress metrics (total, by status, completion percentage)
    - Test audits past due date calculation
    - Test audits due soon calculation (within 7 days)
    - Test finding severity aggregation counts
    - Test resolution status metrics (open, resolved, resolution rate)
    - Test average resolution time calculation using Finding::getTotalResolutionTime()
    - Test overdue findings count using Finding::scopeOverdue()
  - [x] 2.2 Implement audit progress metrics in dashboard controller
    - Total audits count within filtered period
    - Count by status (Pending, In Progress, Completed, Cancelled) using AuditStatus enum
    - Completion percentage: (Completed / Total) * 100
    - Past due count: status not Completed and due_date < today
    - Due soon count: due_date within next 7 days, not yet overdue
  - [x] 2.3 Implement finding severity aggregation
    - Group findings by severity (Critical, High, Medium, Low)
    - Use FindingSeverity enum for grouping
    - Return counts with color codes from FindingSeverity::color()
    - Calculate percentages for pie/donut chart
  - [x] 2.4 Implement per-audit finding breakdown
    - Query each audit with finding counts by severity
    - Include columns: Audit ID, Name, Datacenter, Status, Critical, High, Medium, Low, Total
    - Sort by total findings descending
    - Limit to active time period and datacenter filter
  - [x] 2.5 Implement resolution status metrics
    - Open findings: status in (Open, InProgress, PendingReview, Deferred) using FindingStatus enum
    - Resolved findings: status = Resolved
    - Resolution rate: (Resolved / Total) * 100
    - Average resolution time: calculate mean of Finding::getTotalResolutionTime() for resolved findings
    - Overdue findings count: use Finding::scopeOverdue()
  - [x] 2.6 Implement trend data for completion chart
    - Group completed audits by time period (day/week/month based on filter range)
    - Return array of {period: string, count: number} objects
    - Adjust granularity: days for 30 days, weeks for 90 days, months for year
  - [x] 2.7 Implement active audit progress data
    - Query audits with status = InProgress
    - Calculate progress percentage per audit:
      - Connection audits: completedVerifications / totalVerifications
      - Inventory audits: (totalDeviceVerifications - pendingDeviceVerifications) / totalDeviceVerifications
    - Include audit name, due date, progress percentage
  - [x] 2.8 Ensure data aggregation tests pass
    - Run ONLY the 6-8 tests written in 2.1
    - Verify all metric calculations are accurate
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6-8 tests written in 2.1 pass
- All metrics calculate correctly
- Efficient queries (avoid N+1)
- Metrics respect datacenter and time period filters

---

#### Task Group 3: Filter Implementation
**Dependencies:** Task Group 2

- [x] 3.0 Complete filter implementation
  - [x] 3.1 Write 4-6 focused tests for filter functionality
    - Test datacenter filter restricts results to selected datacenter
    - Test time period filter with each preset (30 days, 90 days, quarter, year, all time)
    - Test combined filters work together
    - Test user-accessible datacenters are correctly retrieved
    - Test filter values persist in URL query parameters
  - [x] 3.2 Implement datacenter filter logic
    - Query user-accessible datacenters via Datacenter::whereHas('users') for non-admin users
    - Admin users see all datacenters
    - Follow pattern from `AuditController::create()` method
    - Apply filter to all audit and finding queries
  - [x] 3.3 Implement time period filter logic
    - Presets: Last 30 days, Last 90 days, This quarter, This year, All time
    - Calculate date ranges for each preset
    - Apply filter to audit created_at and finding created_at
    - Default to "Last 30 days" when no filter specified
  - [x] 3.4 Pass filter options and current values to frontend
    - Datacenter options array: [{id, name}]
    - Time period options array: [{value, label}]
    - Current filter values from request query parameters
  - [x] 3.5 Ensure filter tests pass
    - Run ONLY the 4-6 tests written in 3.1
    - Verify filters correctly restrict data
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 3.1 pass
- Datacenter filter shows only accessible datacenters
- Time period presets calculate correct date ranges
- URL query parameters are bookmarkable

---

### Frontend Layer

#### Task Group 4: Dashboard Page Structure and Layout
**Dependencies:** Task Group 3

- [x] 4.0 Complete dashboard page structure
  - [x] 4.1 Write 3-5 focused tests for dashboard page rendering
    - Test page renders with correct layout and heading
    - Test filter dropdowns appear with correct options
    - Test navigation to dashboard from sidebar works
    - Test breadcrumbs display correctly
  - [x] 4.2 Create `resources/js/Pages/Audits/Dashboard.vue` component
    - Follow layout pattern from `resources/js/Pages/Audits/Index.vue`
    - Use AppLayout wrapper with breadcrumbs
    - Use HeadingSmall for page title: "Audit Status Dashboard"
    - Description: "Overview of audit progress, finding severity, and resolution status"
  - [x] 4.3 Add TypeScript interfaces for dashboard props
    - Create or extend types in `resources/js/types/` for dashboard data
    - Interface for audit metrics, finding summaries, filter options
    - Interface for chart data structures
  - [x] 4.4 Implement filter section UI
    - Datacenter select dropdown following pattern from Index.vue
    - Time period select dropdown with presets
    - Use selectClass pattern from existing pages
    - Handle filter changes with router.get() and query parameters
    - Use debounce for filter changes
  - [x] 4.5 Add navigation link to sidebar
    - Update `resources/js/components/AppSidebar.vue`
    - Add "Dashboard" item under or near Audits with LayoutDashboard icon
    - Route: `/audits/dashboard`
    - Visible to same roles as Audits (no special permission restriction)
  - [x] 4.6 Ensure dashboard page tests pass
    - Run ONLY the 3-5 tests written in 4.1
    - Verify page structure renders correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 3-5 tests written in 4.1 pass
- Dashboard page renders with correct layout
- Filters work and update URL
- Sidebar navigation link works

---

#### Task Group 5: Metric Cards and Summary Section
**Dependencies:** Task Group 4

- [x] 5.0 Complete metric cards UI
  - [x] 5.1 Write 3-5 focused tests for metric cards
    - Test audit progress metrics display correctly
    - Test finding severity counts display with correct badge colors
    - Test resolution status metrics display correctly
    - Test cards are responsive on mobile/tablet
  - [x] 5.2 Create audit progress metrics section
    - Use Card components from `resources/js/Components/ui/card/`
    - Display: Total audits, Pending, In Progress, Completed, Cancelled
    - Show completion percentage with visual indicator
    - Display "Past Due" and "Due Soon" counts with warning styling
  - [x] 5.3 Create finding severity summary section
    - Display Critical, High, Medium, Low counts
    - Use color-coded badges matching FindingSeverity::color() output
    - Critical: red, High: orange, Medium: yellow, Low: blue
    - Make badges clickable to filter findings list (link to /findings?severity=X)
  - [x] 5.4 Create resolution status metrics section
    - Display open findings count, resolved findings count
    - Show resolution rate percentage
    - Display average time to resolve (format as hours/days)
    - Display overdue findings count with warning styling
  - [x] 5.5 Ensure metric cards tests pass
    - Run ONLY the 3-5 tests written in 5.1
    - Verify metrics display correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 3-5 tests written in 5.1 pass
- All metric cards render with correct data
- Color coding matches backend enum colors
- Responsive layout for mobile/tablet

---

#### Task Group 6: Charts and Visualizations
**Dependencies:** Task Group 5

- [x] 6.0 Complete chart visualizations
  - [x] 6.1 Write 3-5 focused tests for chart components
    - Test donut/pie chart renders with severity data
    - Test line chart renders with trend data
    - Test charts handle empty data gracefully
    - Test charts are responsive
  - [x] 6.2 Install and configure Chart.js
    - Add chart.js and vue-chartjs packages if not already installed
    - Configure TypeScript types for chart components
  - [x] 6.3 Create severity distribution donut/pie chart
    - Display finding severity distribution
    - Use colors: Critical (red), High (orange), Medium (yellow), Low (blue)
    - Show percentage labels
    - Enable click interaction to filter findings
  - [x] 6.4 Create audit completion trend line chart
    - X-axis: time periods based on selected range
    - Y-axis: count of completed audits
    - Responsive sizing
    - Tooltips on hover
  - [x] 6.5 Ensure chart tests pass
    - Run ONLY the 3-5 tests written in 6.1
    - Verify charts render correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 3-5 tests written in 6.1 pass
- Charts render with correct data
- Charts are interactive and responsive
- Empty states handled gracefully

---

#### Task Group 7: Per-Audit Breakdown Table
**Dependencies:** Task Group 5

- [x] 7.0 Complete per-audit breakdown table
  - [x] 7.1 Write 3-5 focused tests for breakdown table
    - Test table renders with audit data
    - Test table columns display correctly
    - Test row click navigation works
    - Test collapse/expand functionality
    - Test sorting by total findings
  - [x] 7.2 Create collapsible per-audit breakdown component
    - Follow table pattern from `resources/js/Pages/Audits/Index.vue`
    - Columns: Audit Name, Datacenter, Status, Critical, High, Medium, Low, Total
    - Default sorted by total findings descending
    - Use Collapsible component from `resources/js/Components/ui/collapsible/`
  - [x] 7.3 Implement row click navigation
    - Click on row navigates to audit show page: `/audits/{id}`
    - Alternative: click on severity count filters to finding list with audit and severity filter
    - Use Link component and AuditController wayfinder
  - [x] 7.4 Add severity count badges in table cells
    - Use same badge styling as severity summary section
    - Zero counts displayed as muted/gray
    - Non-zero counts displayed with severity color
  - [x] 7.5 Ensure breakdown table tests pass
    - Run ONLY the 3-5 tests written in 7.1
    - Verify table functionality works
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 3-5 tests written in 7.1 pass
- Table displays all audit data correctly
- Navigation from table works
- Collapsible behavior works smoothly

---

#### Task Group 8: Active Audit Progress Bars
**Dependencies:** Task Group 5

- [x] 8.0 Complete active audit progress bars section
  - [x] 8.1 Write 2-4 focused tests for progress bars
    - Test progress bars render for in-progress audits
    - Test progress percentage displays correctly
    - Test due date displays with appropriate warning styling
    - Test section hidden when no in-progress audits
  - [x] 8.2 Create active audit progress component
    - Display list of audits with status = InProgress
    - Show audit name and due date
    - Use progress bar to show completion percentage
    - Style overdue audits with warning color
  - [x] 8.3 Calculate and display progress percentage
    - Connection audits: (completedVerifications / totalVerifications) * 100
    - Inventory audits: ((total - pending) / total) * 100
    - Display percentage text alongside progress bar
    - Handle edge case of 0 total verifications
  - [x] 8.4 Add due date indicators
    - Show due date for each audit
    - Highlight overdue audits in red
    - Highlight due soon (7 days) in yellow
    - Use existing DueDateIndicator component pattern
  - [x] 8.5 Ensure progress bars tests pass
    - Run ONLY the 2-4 tests written in 8.1
    - Verify progress bars display correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-4 tests written in 8.1 pass
- Progress bars show accurate percentages
- Due date warnings display correctly
- Empty state handled when no in-progress audits

---

### Testing

#### Task Group 9: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-8

- [x] 9.0 Review existing tests and fill critical gaps only
  - [x] 9.1 Review tests from Task Groups 1-8
    - Review the 4-6 tests written by Task Group 1 (controller/route)
    - Review the 6-8 tests written by Task Group 2 (data aggregation)
    - Review the 4-6 tests written by Task Group 3 (filters)
    - Review the 3-5 tests written by Task Group 4 (page structure)
    - Review the 3-5 tests written by Task Group 5 (metric cards)
    - Review the 3-5 tests written by Task Group 6 (charts)
    - Review the 3-5 tests written by Task Group 7 (breakdown table)
    - Review the 2-4 tests written by Task Group 8 (progress bars)
    - Total existing tests: approximately 28-44 tests
  - [x] 9.2 Analyze test coverage gaps for dashboard feature only
    - Identify critical user workflows that lack test coverage
    - Focus ONLY on gaps related to this spec's feature requirements
    - Do NOT assess entire application test coverage
    - Prioritize end-to-end workflows over unit test gaps
  - [x] 9.3 Write up to 10 additional strategic tests maximum
    - Add maximum of 10 new tests to fill identified critical gaps
    - Focus on integration points and end-to-end workflows
    - Consider: filter persistence, chart data accuracy, navigation flows
    - Do NOT write comprehensive coverage for all scenarios
    - Skip edge cases, performance tests, and accessibility tests unless business-critical
  - [x] 9.4 Run feature-specific tests only
    - Run ONLY tests related to this spec's feature (tests from 1.1-8.1 and 9.3)
    - Expected total: approximately 38-54 tests maximum
    - Do NOT run the entire application test suite
    - Verify critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 38-54 tests total)
- Critical user workflows for this feature are covered
- No more than 10 additional tests added when filling in testing gaps
- Testing focused exclusively on this spec's feature requirements

---

## Execution Order

Recommended implementation sequence:

1. **Backend Foundation** (Task Group 1) - Controller method and route
2. **Data Aggregation** (Task Group 2) - All metric calculations
3. **Filter Implementation** (Task Group 3) - Datacenter and time period filters
4. **Page Structure** (Task Group 4) - Dashboard page layout and navigation
5. **Metric Cards** (Task Group 5) - Summary statistics display
6. **Charts** (Task Group 6) - Visualizations (can parallel with Task Group 7)
7. **Breakdown Table** (Task Group 7) - Per-audit finding breakdown (can parallel with Task Group 6)
8. **Progress Bars** (Task Group 8) - Active audit progress section
9. **Test Review** (Task Group 9) - Gap analysis and additional testing

---

## Reference Files

### Backend Patterns
- **Controller pattern**: `/Users/helderdene/rackaudit/app/Http/Controllers/AuditController.php`
- **Finding queries**: `/Users/helderdene/rackaudit/app/Http/Controllers/FindingController.php`
- **Audit model**: `/Users/helderdene/rackaudit/app/Models/Audit.php`
- **Finding model**: `/Users/helderdene/rackaudit/app/Models/Finding.php`
- **AuditStatus enum**: `/Users/helderdene/rackaudit/app/Enums/AuditStatus.php`
- **FindingStatus enum**: `/Users/helderdene/rackaudit/app/Enums/FindingStatus.php`
- **FindingSeverity enum**: `/Users/helderdene/rackaudit/app/Enums/FindingSeverity.php`
- **Routes file**: `/Users/helderdene/rackaudit/routes/web.php`

### Frontend Patterns
- **Page layout pattern**: `/Users/helderdene/rackaudit/resources/js/Pages/Audits/Index.vue`
- **Filter pattern**: `/Users/helderdene/rackaudit/resources/js/Pages/Findings/Index.vue`
- **Sidebar navigation**: `/Users/helderdene/rackaudit/resources/js/components/AppSidebar.vue`
- **Card components**: `/Users/helderdene/rackaudit/resources/js/Components/ui/card/`
- **Collapsible component**: `/Users/helderdene/rackaudit/resources/js/Components/ui/collapsible/`

### Test Patterns
- **Audit tests**: `/Users/helderdene/rackaudit/tests/Feature/Audit/`
- **Finding tests**: `/Users/helderdene/rackaudit/tests/Feature/FindingManagement/`
