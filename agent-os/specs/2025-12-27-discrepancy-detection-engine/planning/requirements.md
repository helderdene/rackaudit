# Spec Requirements: Discrepancy Detection Engine

## Initial Description
Automated comparison identifying missing connections, extra connections, wrong endpoints, and configuration mismatches.

This is roadmap item #29 in Phase 4 (Audit System), sized as Large (L).

Related completed features:
- Item #25: Expected vs Actual Comparison View
- Item #27: Connection Audit Execution
- Item #24: Expected Connection Parsing

## Requirements Discussion

### First Round Questions

**Q1:** I notice the codebase already has robust comparison logic in `ConnectionComparisonService` that identifies missing, unexpected, mismatched, and conflicting connections. I assume this new "engine" is meant to run these comparisons automatically on a schedule or triggered by events (e.g., when a connection is added/modified, or when an implementation file is approved), rather than only when an operator initiates an audit. Is that correct, or is the scope different?
**Answer:** Event-triggered (when a connection is added/modified, or when an implementation file is approved)

**Q2:** I'm thinking the engine should generate persistent discrepancy records that exist independently of audits - essentially a real-time "discrepancy dashboard" showing the current state of drift between expected and actual. Should discrepancies be stored as their own model/table, or should they leverage the existing `Finding` model?
**Answer:** Correct - should be stored as their own model/table for a real-time discrepancy dashboard

**Q3:** For trigger mechanisms, I assume we want: real-time detection when connections or expected connections change, scheduled detection (e.g., nightly full comparison), and on-demand detection via a UI button. Should we implement all three, or focus on specific triggers?
**Answer:** All three (real-time, scheduled, on-demand)

**Q4:** For configuration mismatches, I assume this refers to detecting when cable properties differ between expected and actual (e.g., expected cable_type is "fiber" but actual is "copper", or cable_length differs). Should we also detect port type mismatches (e.g., expected connection to an Ethernet port but actual connection uses a fiber port)?
**Answer:** Both cable property differences AND port type mismatches

**Q5:** I assume the engine should support scope filtering - allowing detection at datacenter level, room level, or for specific implementation files. Is that correct, or should it always run across the entire system?
**Answer:** Correct - support filtering at datacenter level, room level, or specific implementation files

**Q6:** For notification/alerting, should the engine: simply update discrepancy counts in a dashboard, send notifications to relevant users (e.g., IT managers, auditors), both, or neither (just passive detection for audit use)?
**Answer:** Both - update dashboard counts AND send notifications to relevant users

**Q7:** I assume discrepancies detected by the engine should feed into the existing audit workflow - when an audit is created, it can optionally import pre-detected discrepancies as verification items. Is that the intended integration, or should detection and auditing remain separate?
**Answer:** Correct - discrepancies should feed into existing audit workflow as verification items

**Q8:** Is there anything that should explicitly be excluded from this feature? For example, should we exclude inventory/device discrepancies (focus only on connections), or should the engine also detect device placement mismatches?
**Answer:** Exclude device/inventory discrepancies - focus only on connections

### Existing Code to Reference

**Similar Features Identified:**
- Service: `App\Services\ConnectionComparisonService` - Core comparison logic for identifying matched, missing, unexpected, mismatched, and conflicting connections
- Service: `App\Services\AuditExecutionService` - Audit workflow integration and verification management
- DTO: `App\DTOs\ComparisonResult` - Result structure for individual comparisons
- DTO: `App\DTOs\ComparisonResultCollection` - Collection of comparison results
- Enum: `App\Enums\DiscrepancyType` - Matched, Missing, Unexpected, Mismatched, Conflicting
- Model: `App\Models\Finding` - Existing pattern for tracking issues discovered during audits
- Model: `App\Models\DiscrepancyAcknowledgment` - Pattern for acknowledging known discrepancies
- Model: `App\Models\AuditConnectionVerification` - Pattern for verification records with locking
- Page: `resources/js/Pages/Datacenters/ConnectionComparison.vue` - Datacenter-level comparison UI
- Page: `resources/js/Pages/ImplementationFiles/Comparison.vue` - Implementation file comparison UI

### Follow-up Questions

**Follow-up 1:** For scheduled detection, what frequency makes sense as the default? I'm thinking a nightly run (e.g., 2:00 AM local time) with the ability to configure the schedule. Is that reasonable, or do you have a different preference?
**Answer:** Nightly (default 2:00 AM with configurable schedule)

**Follow-up 2:** For notifications, which user roles should receive discrepancy alerts? I assume: IT Managers - notified of all new discrepancies in their datacenter(s), Auditors - notified when discrepancy counts exceed a threshold or on schedule, Operators - only notified if explicitly subscribed. Is that the right hierarchy, or should all roles with datacenter access receive alerts?
**Answer:** Correct - IT Managers get all discrepancies, Auditors get threshold/scheduled alerts, Operators only if subscribed

**Follow-up 3:** For the discrepancy dashboard, should it be: a new standalone page (e.g., `/discrepancies`), an enhanced section on the existing datacenter detail page, or both (standalone page + summary widget on datacenter page)?
**Answer:** Both - standalone page (`/discrepancies`) + summary widget on datacenter detail page

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A

## Requirements Summary

### Functional Requirements

**Discrepancy Detection:**
- Automated comparison of expected connections (from approved implementation files) against actual documented connections
- Detection of the following discrepancy types:
  - Missing connections (expected but no actual)
  - Unexpected connections (actual but no expectation)
  - Mismatched connections (source matches but destination differs)
  - Conflicting connections (multiple implementation files disagree)
  - Configuration mismatches (cable type, cable length, port type differences)
- Support for bidirectional connection matching (A->B equals B->A)

**Trigger Mechanisms:**
- Real-time detection: Triggered when connections are added, modified, or deleted
- Real-time detection: Triggered when implementation files are approved
- Real-time detection: Triggered when expected connections are confirmed
- Scheduled detection: Nightly full comparison (default 2:00 AM, configurable)
- On-demand detection: Manual trigger via UI button

**Scope Filtering:**
- Datacenter-level detection (all connections in a datacenter)
- Room-level detection (all connections in a room)
- Implementation file-level detection (connections defined in a specific file)

**Persistent Storage:**
- New Discrepancy model/table for storing detected discrepancies
- Real-time dashboard showing current discrepancy state
- Historical tracking of discrepancy creation and resolution

**Notification System:**
- IT Managers: Notified of all new discrepancies in their datacenter(s)
- Auditors: Notified when discrepancy counts exceed threshold or on schedule
- Operators: Only notified if explicitly subscribed
- Dashboard count updates in real-time

**Dashboard & UI:**
- Standalone discrepancy page at `/discrepancies`
- Summary widget on datacenter detail page
- Filtering and sorting capabilities
- Discrepancy detail view with expected vs actual comparison

**Audit Integration:**
- Ability to import pre-detected discrepancies as audit verification items
- Link discrepancies to audit findings when verified as issues
- Resolution tracking when discrepancies are addressed

### Reusability Opportunities
- Extend `ConnectionComparisonService` for the detection engine logic
- Reuse `ComparisonResult` and `ComparisonResultCollection` DTOs
- Follow `Finding` model pattern for the new `Discrepancy` model
- Reuse comparison UI patterns from `ConnectionComparison.vue`
- Leverage existing `DiscrepancyType` enum
- Use Laravel's notification system for alerts
- Use Laravel's scheduler for nightly detection jobs

### Scope Boundaries

**In Scope:**
- Connection discrepancy detection (missing, unexpected, mismatched, conflicting)
- Configuration mismatch detection (cable type, cable length, port type)
- Real-time, scheduled, and on-demand detection triggers
- Persistent discrepancy storage and tracking
- Discrepancy dashboard (standalone page + datacenter widget)
- Role-based notification system
- Audit workflow integration (import discrepancies as verification items)
- Scope filtering (datacenter, room, implementation file)

**Out of Scope:**
- Device/inventory discrepancy detection (focus only on connections)
- Automatic remediation of discrepancies
- External system integrations (SNMP, network discovery)
- Mobile-specific discrepancy management interface

### Technical Considerations

**New Database Tables:**
- `discrepancies` table for persistent discrepancy records
- Possible `discrepancy_notifications` or leverage existing notifications table

**New Models:**
- `Discrepancy` model with relationships to Connection, ExpectedConnection, ImplementationFile, Datacenter

**New Services:**
- `DiscrepancyDetectionService` - Core detection engine
- Extend or wrap `ConnectionComparisonService`

**Events & Listeners:**
- Connection created/updated/deleted events trigger detection
- ImplementationFile approved event triggers detection
- ExpectedConnection confirmed event triggers detection

**Jobs & Scheduling:**
- `DetectDiscrepanciesJob` for scheduled/queued detection
- Laravel Scheduler configuration for nightly runs

**Notifications:**
- `NewDiscrepancyNotification` for alerting users
- `DiscrepancyThresholdNotification` for threshold alerts
- `ScheduledDiscrepancyReportNotification` for periodic summaries

**Frontend Components:**
- Discrepancy index page with filtering/sorting
- Discrepancy detail modal or page
- Datacenter discrepancy summary widget
- On-demand detection trigger button
