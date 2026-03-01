# Spec Requirements: Scheduled Report Generation

## Initial Description
Configure reports to generate automatically on schedule and distribute via email

## Requirements Discussion

### First Round Questions

**Q1:** For schedule configuration, I assume you want predefined frequency options (daily, weekly, monthly) with specific day/time selection, rather than complex cron expressions. Is that correct?
**Answer:** Yes, predefined frequency options (daily, weekly, monthly) with specific day/time selection - no cron expressions.

**Q2:** Should scheduled reports be timezone-aware, defaulting to the user's timezone or system timezone?
**Answer:** Timezone-aware, defaulting to user's or system timezone.

**Q3:** Which report types should support scheduling? I assume this should integrate with the existing Custom Report Builder and support all 4 report types (Capacity, Assets, Connections, Audit History). Is that correct?
**Answer:** Yes, integrate with existing Custom Report Builder and support all 4 report types: Capacity, Assets, Connections, Audit History.

**Q4:** Can users schedule the same report configuration multiple times with different schedules (e.g., daily summary AND weekly detailed)?
**Answer:** Yes, users can schedule the same report configuration multiple times with different schedules.

**Q5:** For email delivery, should reports be attached directly (PDF/CSV) or sent as download links with expiration?
**Answer:** Reports sent as email attachments only (PDF/CSV) - no download links.

**Q6:** For recipients, should users enter email addresses directly, or select from existing users, or both?
**Answer:** Build a new distribution list management feature where users can create and manage named lists of email addresses (e.g., "Finance Team", "Weekly Audit Recipients").

**Q7:** I assume IT Managers and Administrators can create/manage scheduled reports. Should Operators and Auditors have limited scheduling capabilities for reports within their accessible datacenters?
**Answer:** IT Managers and Administrators can create/manage scheduled reports. Operators and Auditors can also create schedules for reports within their accessible datacenters.

**Q8:** When a scheduled report runs, should it use the creator's datacenter access permissions at time of generation, or should it capture permissions at schedule creation time?
**Answer:** Scheduled reports respect the creator's datacenter access permissions at time of generation.

**Q9:** What should happen when report generation fails (e.g., database unavailable, email delivery failure)?
**Answer:** Notify schedule owner on failure, retry once, disable schedule after repeated failures.

**Q10:** Is there anything specific you want to explicitly exclude from this feature or defer to future work?
**Answer:** Nothing specific mentioned.

### Existing Code to Reference

No similar existing features identified for reference.

### Follow-up Questions

**Follow-up 1:** For recipients/distribution, should we: A) Build a new distribution list management feature where users can create and manage named lists of email addresses (e.g., "Finance Team", "Weekly Audit Recipients"), or B) Allow entering ad-hoc email addresses directly on each schedule, or C) Both options?
**Answer:** Option A - Build a new distribution list management feature where users can create and manage named lists of email addresses (e.g., "Finance Team", "Weekly Audit Recipients").

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A

## Requirements Summary

### Functional Requirements
- Schedule reports using predefined frequencies: daily, weekly, monthly
- Allow specific day and time selection for each frequency type
- Support timezone-aware scheduling (default to user's or system timezone)
- Integrate with existing Custom Report Builder
- Support all 4 report types: Capacity, Assets, Connections, Audit History
- Allow multiple schedules for the same report configuration
- Deliver reports as email attachments (PDF and CSV formats)
- Build distribution list management feature for named recipient lists
- Create, edit, and delete distribution lists with descriptive names
- Manage email addresses within distribution lists
- Notify schedule owner on generation/delivery failures
- Retry failed reports once before marking as failed
- Disable schedules after repeated failures

### Reusability Opportunities
- Existing Custom Report Builder for report configuration and generation
- Existing user permission/role system for access control
- Existing datacenter access permissions logic

### Scope Boundaries
**In Scope:**
- Schedule configuration interface (frequency, day, time, timezone)
- Distribution list management (create, edit, delete lists and members)
- Report generation job/queue system
- Email delivery with PDF/CSV attachments
- Failure handling, retry logic, and notifications
- Permission checks based on user roles and datacenter access
- Schedule management UI (list, enable/disable, delete schedules)

**Out of Scope:**
- Cron expression-based scheduling
- Download links for reports (attachments only)
- Real-time/on-demand report generation (existing feature)
- SMS or other notification channels
- Report storage/archive system

### Technical Considerations
- Must integrate with existing Custom Report Builder
- Must respect existing role-based permissions (IT Manager, Administrator, Operator, Auditor)
- Must enforce datacenter access permissions at report generation time
- Requires job queue system for scheduled report generation
- Requires email service configuration for attachment delivery
- Distribution lists stored independently for reuse across schedules
