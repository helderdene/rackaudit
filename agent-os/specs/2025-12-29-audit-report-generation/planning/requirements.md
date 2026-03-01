# Spec Requirements: Audit Report Generation

## Initial Description
Generate PDF reports summarizing audit scope, findings, resolution status, and recommendations (from raw-idea.md)

## Requirements Discussion

### First Round Questions

**Q1:** For report content, I assume you want: Executive summary with key metrics (total findings, resolution rate, critical issues count), findings organized by severity, and a section showing connection comparison results. Should we also include timeline/history of when findings were created and resolved?
**Answer:** Yes to executive summary, findings by severity, and connection comparison. Timeline/history is not needed for initial version.

**Q2:** I'm thinking the report should be generated from an individual audit's detail page (not from the dashboard). Is that correct, or should there be a "Generate Report" action available in multiple places?
**Answer:** Correct - generate from individual audit detail page only.

**Q3:** For findings display, should each finding include: title, description, severity, status, assignee, resolution notes, and related device/connection details? Or is a simpler summary preferred?
**Answer:** Include all details mentioned - title, description, status, assignee, resolution notes, AND related connection/device details.

**Q4:** Should generated reports be stored for later access (report history), or are they one-time downloads that users generate fresh each time they need one?
**Answer:** Store generated reports for later access (report history).

**Q5:** Is there anything you specifically want to EXCLUDE from this feature's scope?
**Answer:** Initial question was about exclusions - followed up with specific question about recommendations section.

### Follow-up Questions

**Follow-up 1:** Should the report include a recommendations section (suggested next steps or remediation guidance), or should it focus purely on documenting what was found and its current status?
**Answer:** EXCLUDE entirely - no recommendations section in the report.

**Follow-up 2:** For report history access - should it be accessible from the individual audit detail page only, or also from a separate "Reports" section in the navigation?
**Answer:** BOTH locations - accessible from individual audit detail page AND a separate "Reports" section in navigation.

### Existing Code to Reference
No similar existing features identified for reference.

## Visual Assets

### Files Provided:
No visual assets provided.

## Requirements Summary

### Functional Requirements
- Generate PDF reports from individual audit detail pages
- Executive summary section with key metrics:
  - Total findings count
  - Resolution rate (percentage of resolved findings)
  - Critical issues count
- Findings section organized by severity level (Critical -> High -> Medium -> Low)
- Each finding displays:
  - Title
  - Description
  - Status
  - Assignee
  - Resolution notes
  - Related connection/device details
- Connection comparison summary showing:
  - Matched connections
  - Missing connections
  - Unexpected connections
- Store generated reports for later access
- Report history accessible from:
  - Individual audit detail page
  - Separate "Reports" section in main navigation

### Reusability Opportunities
- No existing similar features identified for reference
- May be able to leverage existing audit detail page components
- PDF generation library will need to be selected/integrated

### Scope Boundaries
**In Scope:**
- PDF report generation
- Executive summary with metrics
- Findings organized by severity with full details
- Connection comparison summary
- Report storage and history
- Access from audit detail page
- Separate Reports navigation section

**Out of Scope:**
- Recommendations section (explicitly excluded)
- Timeline/history of finding creation and resolution
- Report generation from dashboard (only from individual audit pages)
- Multiple report format options (PDF only for initial version)

### Technical Considerations
- PDF generation library needed (e.g., DomPDF, Snappy, or similar Laravel-compatible option)
- Report storage - database record with file storage (likely S3 or local storage)
- Reports table will need: audit_id, generated_at, file_path, user_id (who generated it)
- Integration with existing audit and findings models
- Consider report generation as a queued job for larger audits
