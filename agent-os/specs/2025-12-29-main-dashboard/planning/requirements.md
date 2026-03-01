# Spec Requirements: Main Dashboard

## Initial Description

Main Dashboard — Overview page with key metrics including rack utilization, device counts, pending audits, open findings, and recent activity

## Requirements Discussion

### First Round Questions

**Q1:** I assume this dashboard will primarily serve Maria the IT Manager (who needs real-time dashboards and capacity planning data) and Alex the Auditor (who needs audit status visibility), with secondary value for Dave the Datacenter Operator. Is that correct, or should we prioritize a different user persona?
**Answer:** Confirmed - Maria the IT Manager and Alex the Auditor as primary users, Dave the Datacenter Operator as secondary.

**Q2:** I'm thinking the key metrics (rack utilization, device counts, pending audits, open findings) should be displayed as summary cards at the top of the dashboard, similar to common analytics patterns. Should these be simple static counts, or should they include trend indicators (e.g., "+5% from last week", sparklines)?
**Answer:** Confirmed - include trend indicators (e.g., "+5% from last week", sparklines).

**Q3:** I assume "rack utilization" means percentage of U-space occupied across all racks the user has access to. Should this also include power utilization if that data is available, or keep it focused on physical space only?
**Answer:** Confirmed - percentage of U-space occupied across accessible racks (physical space only).

**Q4:** For the recent activity section, I assume we should show the last 10-20 activities across the infrastructure (device additions, connection changes, audit completions, finding resolutions). Should this be filterable by activity type, or a simple chronological feed is sufficient for the dashboard?
**Answer:** Confirmed - last 10-20 activities, simple chronological feed sufficient.

**Q5:** I assume the dashboard should respect the user's datacenter access permissions and aggregate data across all datacenters they can access. Should there also be a datacenter filter dropdown to view metrics for a specific datacenter?
**Answer:** Confirmed - respect user permissions, include datacenter filter dropdown.

**Q6:** For open findings, should we show a breakdown by severity (critical, high, medium, low) or just the total count? I'm assuming severity breakdown would be more actionable for Maria and Alex.
**Answer:** Confirmed - show breakdown by severity (critical, high, medium, low).

**Q7:** I assume "pending audits" includes both audits that are in-progress and audits that are scheduled but not yet started. Is that correct, or should these be shown separately?
**Answer:** Confirmed - includes both in-progress and scheduled audits.

**Q8:** Is there anything that should explicitly be excluded from this dashboard, or any functionality you want to defer to future iterations?
**Answer:** None specified - no exclusions.

### Existing Code to Reference

No similar existing features identified for reference.

### Follow-up Questions

No follow-up questions were needed - all assumptions were confirmed.

## Visual Assets

### Files Provided:

No visual assets provided.

### Visual Insights:

N/A - No visual files were found in the visuals folder.

## Requirements Summary

### Functional Requirements

- Display key metrics as summary cards at the top of the dashboard:
  - Rack utilization (percentage of U-space occupied)
  - Device counts (total devices across accessible infrastructure)
  - Pending audits (in-progress and scheduled)
  - Open findings (with severity breakdown)
- Include trend indicators on metric cards showing change over time (e.g., "+5% from last week")
- Include sparkline visualizations for metric trends
- Show recent activity feed with last 10-20 activities in chronological order
- Implement datacenter filter dropdown to view metrics for specific datacenters
- Respect user's datacenter access permissions when aggregating data
- Display open findings breakdown by severity levels (critical, high, medium, low)

### User Personas Served

**Primary Users:**
- Maria the IT Manager - needs real-time dashboards and capacity planning visibility
- Alex the Auditor - needs audit status visibility and finding tracking

**Secondary Users:**
- Dave the Datacenter Operator - general infrastructure awareness

### Reusability Opportunities

- Investigate existing card/metric components in the UI component library
- Look for existing Chart.js implementations for sparklines
- Reference the existing Audit Status Dashboard (roadmap item 32) for potential patterns
- Check for existing activity logging display patterns

### Scope Boundaries

**In Scope:**
- Metric summary cards with trend indicators and sparklines
- Rack utilization percentage (U-space)
- Device count aggregation
- Pending audits count (in-progress + scheduled)
- Open findings with severity breakdown
- Recent activity feed (10-20 items, chronological)
- Datacenter filter dropdown
- Permission-based data scoping

**Out of Scope:**
- Power utilization metrics (physical space only for now)
- Filterable activity feed (simple chronological sufficient)
- Separate display for in-progress vs scheduled audits
- No other exclusions specified

### Technical Considerations

- Chart.js is available for dashboard charts and sparklines (per tech stack)
- Activity logging infrastructure already exists (Phase 1, item 6)
- Must integrate with existing RBAC system (Spatie Laravel-Permission)
- Should use Inertia.js for the page component
- Consider deferred props for metrics that require heavy computation
- Existing database entities: Datacenters, Racks, Devices, Audits, Findings, Activity Logs
