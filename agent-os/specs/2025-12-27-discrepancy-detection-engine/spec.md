# Specification: Discrepancy Detection Engine

## Goal
Build an automated detection engine that continuously monitors and identifies discrepancies between expected connections (from approved implementation files) and actual documented connections, providing real-time alerts and a centralized dashboard for tracking connection drift.

## User Stories
- As an IT Manager, I want to be automatically notified when new connection discrepancies are detected in my datacenter so that I can address configuration drift before it causes problems.
- As an Auditor, I want a centralized dashboard showing all current discrepancies so that I can prioritize which areas need attention and import discrepancies into audits as verification items.

## Specific Requirements

**Discrepancy Model and Persistent Storage**
- Create a new `discrepancies` table to store detected discrepancies independently of audits
- Store discrepancy type (missing, unexpected, mismatched, conflicting, configuration_mismatch)
- Track source and destination ports, expected and actual connections
- Include status field (open, acknowledged, resolved) with timestamps
- Link to datacenter, room (optional), and implementation file (optional) for scope tracking
- Store configuration mismatch details (cable_type, cable_length, port_type differences) as JSON

**Discrepancy Detection Service**
- Create `DiscrepancyDetectionService` that wraps `ConnectionComparisonService`
- Add configuration mismatch detection (compare cable_type, cable_length between expected and actual)
- Add port type mismatch detection (compare source/destination port types)
- Support scope filtering: datacenter-level, room-level, implementation file-level
- Upsert discrepancies (update existing if same connection pair, create new otherwise)
- Mark resolved discrepancies when connections match expectations

**Real-time Event-Triggered Detection**
- Listen for Connection created/updated/deleted events and trigger detection
- Listen for ImplementationFile approved event and trigger full detection for that file's scope
- Listen for ExpectedConnection confirmed event and trigger detection for that connection
- Use queued jobs to avoid blocking the main request cycle
- Limit detection scope to affected connections (not full datacenter rescan)

**Scheduled Detection**
- Create `DetectDiscrepanciesJob` for scheduled and on-demand detection runs
- Default nightly schedule at 2:00 AM (configurable via config file)
- Support full datacenter scans or incremental detection based on last run timestamp
- Register scheduled task in `routes/console.php`

**On-Demand Detection**
- Add API endpoint to trigger detection manually with scope parameters
- Add UI button on discrepancy dashboard to run detection now
- Show progress indicator during detection run

**Notification System**
- Create `NewDiscrepancyNotification` for alerting users of new discrepancies
- Create `DiscrepancyThresholdNotification` for when counts exceed configurable thresholds
- IT Managers receive all new discrepancy notifications for their datacenters
- Auditors receive threshold-based or scheduled summary notifications
- Operators only receive notifications if explicitly subscribed via user preferences
- Use Laravel's database and mail notification channels

**Discrepancy Dashboard Page**
- Create standalone page at `/discrepancies` showing all open discrepancies
- Display filterable table with columns: type, source device/port, dest device/port, datacenter, detected_at, status
- Include filter controls for discrepancy type, datacenter, room, status, date range
- Add sorting by type, datacenter, detected date
- Show summary statistics at top (counts by type, by datacenter)
- Include "Run Detection Now" button for on-demand scans

**Datacenter Summary Widget**
- Add discrepancy summary widget to datacenter detail page
- Show counts by discrepancy type (missing, unexpected, mismatched, conflicting)
- Link to filtered discrepancy dashboard for that datacenter
- Include visual indicator (badge) when discrepancies exist

**Audit Workflow Integration**
- Add ability to import discrepancies as verification items when creating an audit
- Allow selecting which discrepancies to import (checkbox selection)
- Mark imported discrepancies as "in_audit" status to prevent duplicate imports
- Link discrepancies to resulting findings when verification confirms issue
- Auto-resolve discrepancies when audit finding is resolved

## Existing Code to Leverage

**ConnectionComparisonService (app/Services/ConnectionComparisonService.php)**
- Contains all core comparison logic for matched, missing, unexpected, mismatched, conflicting connections
- Extend or wrap this service; do not duplicate the comparison algorithms
- Use `compareForDatacenter()` and `compareForImplementationFile()` methods as foundation
- Leverage `detectConflicts()` method for conflict detection across implementation files

**ComparisonResult DTO (app/DTOs/ComparisonResult.php)**
- Use static factory methods (matched, missing, unexpected, mismatched, conflicting) for creating results
- Reference the `toArray()` method pattern for discrepancy serialization
- Contains acknowledgment relationship pattern to follow for discrepancy status tracking

**DiscrepancyType Enum (app/Enums/DiscrepancyType.php)**
- Reuse existing enum values: Matched, Missing, Unexpected, Mismatched, Conflicting
- Add new value for ConfigurationMismatch if needed for cable/port type differences

**Finding Model Pattern (app/Models/Finding.php)**
- Follow same structure for Discrepancy model (fillable, casts, relationships)
- Use similar status tracking pattern (open, acknowledged, resolved)
- Reference relationship patterns for linking to verifications and users

**AuditExecutionService (app/Services/AuditExecutionService.php)**
- Reference `prepareVerificationItems()` pattern for importing discrepancies into audits
- Use same transaction pattern for bulk operations
- Follow same event broadcasting pattern for real-time UI updates

## Out of Scope
- Device/inventory discrepancy detection (focus only on connections per requirements)
- Automatic remediation of discrepancies (manual resolution only)
- External system integrations (SNMP, network discovery, API sync)
- Mobile-specific discrepancy management interface
- Email digest customization (daily/weekly summaries)
- Discrepancy assignment to specific users for resolution
- SLA tracking or resolution time metrics
- Integration with external ticketing systems
- Bulk resolution of multiple discrepancies at once
- Historical trend analysis or reporting dashboards
