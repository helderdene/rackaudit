# Spec Requirements: Audit Status Dashboard

## Initial Description

Create an Audit Status Dashboard that provides an overview of audit progress, finding counts by severity, and resolution status. This is item #32 in the product roadmap, part of Phase 4: Audit System.

## Requirements Discussion

### First Round Questions

**Q1:** Should this dashboard be a dedicated page (e.g., `/audits/dashboard`) or replace/enhance the existing main dashboard with audit-specific widgets?
**Answer:** Confirmed - dedicated page (e.g., `/audits/dashboard`), not replacing the main dashboard.

**Q2:** For audit progress metrics, I assume we want to show: total audits, audits in progress, completed audits, and completion percentage. Should we also include "audits past due date" or "audits due soon" metrics?
**Answer:** Confirmed - include "audits past due date" and "audits due soon" metrics in addition to the base progress metrics.

**Q3:** For finding counts by severity, should this show overall totals only, or also a per-audit breakdown with the ability to drill down into specific audits?
**Answer:** Both - overall totals AND per-audit breakdown with ability to drill down into specific audits.

**Q4:** For resolution status, I assume we want: open findings, resolved findings, and resolution rate. Should we also track "average time to resolve" or "overdue findings" (past SLA)?
**Answer:** Confirmed - include "average time to resolve" and "overdue findings" metrics.

**Q5:** Should the dashboard support filtering by datacenter and/or time period (e.g., last 30 days, this quarter)?
**Answer:** Confirmed - support filtering by datacenter and/or time period.

**Q6:** For visualizations, I'm thinking: a trend chart showing audit completion over time, a progress bar for active audits, and a severity breakdown (donut/pie chart). Does this match your expectations?
**Answer:** Confirmed - include trend chart, progress bar, and severity donut/pie chart.

**Q7:** Is there anything you specifically want to exclude from this dashboard, or features you'd prefer to defer to a later phase?
**Answer:** Exclude: user-specific views, export functionality, and real-time updates. These can be deferred.

### Existing Code to Reference

No similar existing features identified for reference by the user. However, based on the roadmap:
- Finding Management (item #30) - existing finding severity and status tracking
- Audit Creation and Execution (items #26-29) - existing audit models and workflows
- Discrepancy Detection Engine (item #29) - existing finding generation logic

### Follow-up Questions

No follow-up questions were needed - user provided comprehensive answers.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A - No visuals to analyze.

## Requirements Summary

### Functional Requirements

**Dashboard Location & Access:**
- Dedicated page at route like `/audits/dashboard`
- Accessible from audit section navigation
- Does not replace the main application dashboard (item #34)

**Audit Progress Metrics:**
- Total audits count
- Audits in progress count
- Completed audits count
- Completion percentage
- Audits past due date (overdue audits)
- Audits due soon (upcoming deadlines)

**Finding Counts by Severity:**
- Overall totals across all audits (Critical, High, Medium, Low)
- Per-audit breakdown view
- Drill-down capability to view findings for specific audits

**Resolution Status Metrics:**
- Open findings count
- Resolved findings count
- Resolution rate percentage
- Average time to resolve (mean resolution time)
- Overdue findings count (past SLA/expected resolution date)

**Filtering Capabilities:**
- Filter by datacenter (single or multiple selection)
- Filter by time period (e.g., last 30 days, this quarter, custom range)

**Chart Visualizations:**
- Trend chart: audit completion over time
- Progress bars: for active audit completion status
- Severity breakdown: donut or pie chart showing finding distribution

### Reusability Opportunities

Based on product context and existing Phase 4 features:
- Audit model relationships and scopes for querying audit data
- Finding model with severity and status fields
- Existing chart/visualization patterns from other dashboard components
- Datacenter filter components if they exist elsewhere in the app
- Date range picker components

### Scope Boundaries

**In Scope:**
- Dedicated audit status dashboard page
- Audit progress metrics with due date tracking
- Finding severity counts (overall and per-audit)
- Resolution status with time-based metrics
- Datacenter and time period filtering
- Trend chart, progress bars, severity pie/donut chart
- Drill-down navigation to specific audits/findings

**Out of Scope (Deferred):**
- User-specific views (e.g., "my assigned findings")
- Export functionality (PDF/CSV export of dashboard data)
- Real-time updates (Laravel Echo integration for live data)
- Comparison with previous periods
- Custom dashboard configuration/widget arrangement

### Technical Considerations

- Part of Phase 4: Audit System - depends on completed audit and finding management features
- Should use existing Audit and Finding Eloquent models
- Chart library: likely Chart.js as mentioned in roadmap item #41
- Should follow existing application patterns for dashboard layouts
- Needs efficient queries for aggregating finding and audit data
- Consider caching for performance with large datasets
- Filtering should use query parameters for bookmarkable/shareable URLs
