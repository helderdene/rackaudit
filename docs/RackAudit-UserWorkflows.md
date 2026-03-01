# RackAudit

## User Workflows Guide

**Datacenter Management & Auditing System**

Version 1.0 | December 2025

---

## 1. Overview

This document describes the key user workflows in RackAudit, organized by user role and common tasks. Each workflow outlines the step-by-step process, decision points, and expected outcomes.

### 1.1 User Roles Summary

| Role | Primary Responsibilities | Key Workflows |
|------|--------------------------|---------------|
| Operator | Day-to-day infrastructure management, data entry, performing audits | Asset management, connection tracking, audit execution |
| IT Manager | Oversight, approvals, capacity planning, reporting | Approve changes, review audits, generate reports |
| Auditor | Compliance verification, audit review, report generation | Review audit results, export compliance reports |
| Administrator | System configuration, user management, full access | User management, system settings, all workflows |

---

## 2. Operator Workflows

Operators are the primary users who manage the day-to-day infrastructure data and perform auditing tasks.

### 2.1 Adding a New Server to a Rack

*Scenario: A new server has been physically installed and needs to be documented in the system.*

**Preconditions:** Server is physically installed. Operator has asset information (serial number, model, specifications).

#### Steps:

1. **Navigate to Rack View**
   - Go to Datacenters → Select datacenter → Select room → Select target rack.

2. **Open Rack Elevation**
   - Click on the rack to open the elevation view showing front and rear perspectives.

3. **Add New Asset**
   - Click 'Add Device' button or click on empty U-space where server is installed.

4. **Enter Asset Details**
   - Fill in the asset form: Asset tag, serial number, hostname, manufacturer, model, U-height, U-position (starting position from bottom), power consumption, weight.

5. **Add Specifications**
   - Enter technical specifications: CPU, RAM, storage, network interfaces.

6. **Define Ports**
   - Add all ports on the device. Use 'Import from Template' if device model exists, or manually add each port with type, speed, and connector.

7. **Save and Verify**
   - Save the asset. Verify it appears correctly in the rack elevation view.

8. **Document Connections**
   - Proceed to add connections for each port (see Workflow 2.2).

**Outcome:** New server is documented with complete specifications and appears in rack elevation.

---

### 2.2 Documenting Port Connections

*Scenario: Recording the physical cable connections between devices.*

**Preconditions:** Both source and destination devices exist in the system with ports defined.

#### Steps:

1. **Navigate to Source Device**
   - Go to Assets → Search/browse to find the source device.

2. **View Device Ports**
   - Click on device to open details, then select 'Ports' tab.

3. **Select Source Port**
   - Find the port to connect and click 'Add Connection' or the connection icon.

4. **Select Destination**
   - Search for destination device by hostname, asset tag, or browse. Then select the specific destination port.

5. **Enter Cable Details**
   - Specify cable type (CAT6, fiber, etc.), cable color, length, and cable label if applicable.

6. **Confirm Connection**
   - Review the connection summary showing: Source Device → Port → Cable → Port → Destination Device.

7. **Save Connection**
   - Save the connection. Both ports are now marked as 'connected'.

**Outcome:** Connection is recorded and visible in both devices' connection lists and in connection diagrams.

**Alternative - Bulk Import:** For multiple connections, use Import → Connections, upload CSV with columns: source_device, source_port, dest_device, dest_port, cable_type, cable_color.

---

### 2.3 Performing a Connection Audit

*Scenario: Verifying that physical connections match the implementation documentation.*

**Preconditions:** Implementation file is uploaded and approved. Physical access to verify connections.

#### Steps:

1. **Create New Audit**
   - Go to Audits → Create New Audit.

2. **Configure Audit**
   - Set audit name, select audit type 'Connection Audit', define scope (datacenter, room, rack, or specific devices).

3. **Select Implementation File**
   - Choose the approved implementation file that defines expected connections.

4. **Start Audit**
   - Click 'Start Audit' to begin. System pre-loads expected vs. actual comparison.

5. **Review Each Connection**
   - For each expected connection, the system shows:
     - Expected (from implementation file)
     - Actual (from system records)
     - Status (matched, mismatched, missing)

6. **Physical Verification**
   - Go to physical location. Verify each connection matches. For each item:
     - Mark as 'Verified' if correct
     - Mark as 'Discrepancy' if incorrect and add notes
     - Optionally attach photo evidence

7. **Document Findings**
   - For any discrepancies, select finding type:
     - Missing (expected connection doesn't exist)
     - Extra (connection exists but not in spec)
     - Mismatch (wrong port, wrong cable type, etc.)
   - Add detailed description.

8. **Complete Audit**
   - Once all items are reviewed, click 'Complete Audit'. System calculates pass/fail statistics.

9. **Generate Report**
   - Export audit report as PDF for records.

**Outcome:** Audit is completed with documented findings. Discrepancies are logged for resolution.

---

### 2.4 Resolving Audit Findings

*Scenario: Addressing discrepancies discovered during an audit.*

#### Steps:

1. **View Open Findings**
   - Go to Audits → Select completed audit → View Findings, or Dashboard → Open Findings widget.

2. **Select Finding**
   - Click on a finding to view details: what was expected, what was found, severity level.

3. **Determine Resolution**
   - Decide on action:
     - **Fix Physical** - Correct the physical connection to match spec
     - **Update System** - Update system records if physical is correct
     - **Update Spec** - Request implementation file update if spec is wrong
     - **Accept As-Is** - Document exception with justification

4. **Implement Resolution**
   - Perform the corrective action (physical or data update).

5. **Document Resolution**
   - Update finding status to 'Resolved', add resolution notes explaining what was done.

6. **Manager Review (if required)**
   - For major findings, submit for IT Manager approval.

**Outcome:** Finding is resolved and documented. Historical record maintained for compliance.

---

### 2.5 Performing Inventory Audit

*Scenario: Physically verifying that assets in the system match what's actually in the racks.*

#### Steps:

1. **Create Inventory Audit**
   - Go to Audits → Create New → Select 'Inventory Audit'.

2. **Define Scope**
   - Select the area to audit: entire datacenter, specific room, row, or individual racks.

3. **Generate Audit Checklist**
   - System creates a list of all assets expected in the scope.

4. **Physical Walk-through**
   - Visit each rack in the scope. For each asset:
     - Scan QR code or enter asset tag
     - Verify asset is present and in correct position
     - Check U-position matches records
     - Note any unrecorded equipment found

5. **Record Discrepancies**
   - Mark any issues:
     - Asset Missing (in system but not found)
     - Asset Misplaced (wrong rack/position)
     - Unknown Asset (found but not in system)
     - Incorrect Details (serial number mismatch, etc.)

6. **Complete and Report**
   - Finalize audit and generate inventory reconciliation report.

**Outcome:** Inventory accuracy verified. Discrepancies documented for correction.

---

### 2.6 Moving Equipment Between Racks

*Scenario: Relocating a server from one rack to another.*

#### Steps:

1. **Locate Asset**
   - Go to Assets → Search for the device to be moved.

2. **Document Current State**
   - Note current connections before disconnecting. Optionally export connection list.

3. **Update Connections**
   - Mark all connections as 'Disconnected' with disconnection date.

4. **Update Asset Location**
   - Edit asset → Change rack assignment → Set new U-position.

5. **Physical Move**
   - Perform the physical relocation.

6. **Reconnect and Document**
   - Re-establish connections at new location. Document new connections.

7. **Verify**
   - Check rack elevation views for both old and new locations.

**Outcome:** Asset location updated. Change history preserved. Connections re-documented.

---

## 3. IT Manager Workflows

IT Managers oversee datacenter operations, approve changes, and use reporting for capacity planning.

### 3.1 Reviewing and Approving Implementation Files

*Scenario: An operator has uploaded a new implementation file that needs approval before use in audits.*

#### Steps:

1. **View Pending Approvals**
   - Dashboard shows pending items, or go to Implementation Files → Filter by 'Pending Approval'.

2. **Review File Details**
   - Click on file to see:
     - File name and description
     - Uploaded by and date
     - Version number
     - Parsed connection count

3. **Preview Connections**
   - View the parsed expected connections:
     - Source devices and ports
     - Destination devices and ports
     - Cable specifications
     - Any parsing errors or warnings

4. **Validate Against Standards**
   - Check that connections follow organizational standards (naming conventions, cable types, etc.).

5. **Compare to Previous Version**
   - If updating existing spec, view diff showing what changed.

6. **Decision**
   - **Approve** - File becomes active and available for audits
   - **Reject** - Return with comments for correction
   - **Request Changes** - Specific modifications needed

7. **Set Effective Date**
   - If approved, set when this specification becomes active.

**Outcome:** Implementation file approved and ready for audit use, or returned with feedback.

---

### 3.2 Capacity Planning Review

*Scenario: Reviewing datacenter capacity to plan for new equipment installations.*

#### Steps:

1. **Access Capacity Dashboard**
   - Go to Reports → Capacity Report, or Dashboard → Capacity widget.

2. **Review Rack Utilization**
   - View summary showing:
     - Total racks and U-space
     - Used vs. available U-space
     - Utilization percentage by room/row

3. **Check Power Capacity**
   - Review power consumption:
     - Current draw vs. capacity per rack
     - Rooms approaching power limits
     - Power headroom for new equipment

4. **Identify Available Space**
   - Filter to show racks with available space:
     - Minimum contiguous U-space needed
     - Required power availability
     - Preferred locations

5. **Reserve Space (Optional)**
   - Mark specific U-spaces as 'Reserved' for planned installations.

6. **Export Report**
   - Generate capacity report for planning meetings.

**Outcome:** Clear understanding of available capacity. Reservations made if needed.

---

### 3.3 Reviewing Audit Results

*Scenario: Reviewing completed audits to assess infrastructure compliance.*

#### Steps:

1. **Access Audit Summary**
   - Go to Audits → Completed, or Dashboard → Recent Audits.

2. **Review Audit Statistics**
   - For each audit, see:
     - Pass rate percentage
     - Total items audited
     - Findings by severity (critical, major, minor)

3. **Drill into Findings**
   - Click on audit to view detailed findings. Filter by severity, type, or resolution status.

4. **Review Open Items**
   - Check unresolved findings:
     - How long have they been open?
     - Are there blockers?
     - Who is assigned?

5. **Approve Resolutions**
   - For findings requiring manager approval, review resolution notes and approve or request additional action.

6. **Track Trends**
   - View historical audit trends:
     - Is compliance improving or declining?
     - Recurring issues?
     - Problem areas?

7. **Generate Management Report**
   - Export summary report for stakeholders.

**Outcome:** Audit results reviewed. Issues escalated as needed. Trends identified.

---

### 3.4 Generating Compliance Reports

*Scenario: Creating reports for compliance documentation or management review.*

#### Steps:

1. **Access Reports Module**
   - Go to Reports → Generate New Report.

2. **Select Report Type**
   - Choose from:
     - Infrastructure Summary
     - Audit Compliance Report
     - Change History Report
     - Asset Inventory Report

3. **Configure Parameters**
   - Set report parameters:
     - Date range
     - Scope (datacenter, room, etc.)
     - Include/exclude specific data
     - Grouping and sorting options

4. **Preview Report**
   - View report preview in browser before generating.

5. **Generate and Export**
   - Generate final report in PDF or Excel format.

6. **Schedule (Optional)**
   - Set up recurring report generation with email delivery.

**Outcome:** Professional report generated for compliance or management use.

---

## 4. Auditor Workflows

Auditors have read-only access and focus on reviewing audit results and generating compliance documentation.

### 4.1 Conducting Compliance Review

*Scenario: An auditor needs to verify datacenter infrastructure compliance for a specific period.*

#### Steps:

1. **Access Audit History**
   - Go to Audits → View all audits for the review period.

2. **Filter by Date Range**
   - Set date range for the compliance period.

3. **Review Audit Coverage**
   - Verify that required audits were performed:
     - Were all areas audited as scheduled?
     - Any gaps in audit coverage?
     - Audit frequency meets requirements?

4. **Examine Findings**
   - Review all findings from the period:
     - Total findings by severity
     - Resolution rates
     - Average time to resolution
     - Recurring issues

5. **Check Resolution Quality**
   - For resolved findings, verify:
     - Appropriate resolution action taken
     - Proper documentation
     - Manager approval where required

6. **Review Change History**
   - Access Activity Logs to see all changes during the period. Verify proper change documentation.

7. **Generate Compliance Package**
   - Export all relevant reports:
     - Audit summary reports
     - Finding details
     - Resolution documentation
     - Change history

**Outcome:** Complete compliance review with documentation package.

---

### 4.2 Tracing Connection Paths

*Scenario: Auditor needs to verify the complete path of a critical connection.*

#### Steps:

1. **Access Connection Diagram**
   - Go to Connections → Diagram View, or search for specific device.

2. **Select Start Point**
   - Choose the starting device/port for the trace.

3. **View Connection Path**
   - System displays the complete path:
     - Server → Port → Switch Port → (through patch panels if any) → Destination

4. **Verify Each Hop**
   - For each connection point, view:
     - Cable type and specifications
     - Installation date
     - Last audit verification

5. **Compare to Specification**
   - View the expected path from implementation file. Highlight any differences.

6. **Export Path Documentation**
   - Generate path diagram for records.

**Outcome:** Complete connection path documented and verified against specifications.

---

## 5. Administrator Workflows

Administrators configure the system and manage user access.

### 5.1 Setting Up a New Datacenter

*Scenario: Configuring a new datacenter location in the system.*

#### Steps:

1. **Create Datacenter**
   - Go to Datacenters → Add New. Enter name, code, address, total power/cooling capacity.

2. **Define Rooms/Zones**
   - Add rooms within the datacenter:
     - Server rooms
     - Network rooms
     - Support areas
   - Set room dimensions, power capacity, grid layout.

3. **Create Rack Rows**
   - Within each room, define row layout (Row A, B, C, etc.).

4. **Add Racks**
   - Add individual racks:
     - Rack ID/name
     - Position in row
     - Total U-space
     - Power and weight capacity

5. **Configure Naming Conventions**
   - Set up asset tag format, hostname patterns, cable labeling scheme.

6. **Set Up Device Templates**
   - Create templates for common device models to speed up asset entry.

7. **Assign User Access**
   - Grant appropriate users access to the new datacenter.

**Outcome:** Datacenter structure configured and ready for asset entry.

---

### 5.2 User Management

*Scenario: Adding a new user and configuring their access.*

#### Steps:

1. **Access User Management**
   - Go to Administration → Users.

2. **Create New User**
   - Click 'Add User'. Enter name, email, temporary password.

3. **Assign Role**
   - Select appropriate role:
     - Administrator
     - IT Manager
     - Operator
     - Auditor
     - Viewer

4. **Configure Datacenter Access**
   - Select which datacenters the user can access (Viewers and Operators may have limited scope).

5. **Set Additional Permissions**
   - Fine-tune permissions if needed (e.g., Operator who can approve implementation files).

6. **Send Welcome Email**
   - System sends email with login instructions and temporary password.

7. **Verify Access**
   - Confirm user can log in and see appropriate content.

**Outcome:** New user configured with appropriate access levels.

---

## 6. Common Workflows (All Users)

### 6.1 Searching for Assets

#### Steps:

1. **Use Global Search**
   - Click search bar or press '/' shortcut.

2. **Enter Search Term**
   - Search by:
     - Asset tag
     - Serial number
     - Hostname
     - IP address
     - Model name

3. **Filter Results**
   - Narrow results by:
     - Device type
     - Datacenter/Room/Rack
     - Status

4. **View Asset Details**
   - Click on result to view full asset information.

---

### 6.2 Viewing Rack Elevation

#### Steps:

1. **Navigate to Rack**
   - Browse: Datacenter → Room → Rack, or search for specific rack.

2. **View Elevation**
   - See visual representation of rack contents showing all devices by U-position.

3. **Toggle View**
   - Switch between Front and Rear views.

4. **Interact with Devices**
   - Hover for quick info. Click for full device details.

5. **View Connections**
   - Click 'Show Connections' to see cable connections from devices in this rack.

---

### 6.3 Importing Data from Spreadsheet

#### Steps:

1. **Access Import**
   - Go to Import/Export → Import Data.

2. **Select Import Type**
   - Choose what to import:
     - Assets
     - Ports
     - Connections
     - Implementation file

3. **Download Template**
   - Get the CSV/Excel template with required columns.

4. **Prepare Data**
   - Fill in the template with your data following the column specifications.

5. **Upload File**
   - Upload the completed spreadsheet.

6. **Preview Import**
   - System validates data and shows:
     - Records to be created
     - Warnings (e.g., duplicates)
     - Errors (e.g., missing required fields)

7. **Confirm Import**
   - Review and confirm to complete the import.

8. **Review Results**
   - Check import summary for success/failure counts.

---

## 7. Workflow Summary by Role

| Workflow | Operator | IT Mgr | Auditor | Admin |
|----------|----------|--------|---------|-------|
| Add/Edit Assets | ✓ | ✓ | — | ✓ |
| Document Connections | ✓ | ✓ | — | ✓ |
| Perform Audits | ✓ | ✓ | — | ✓ |
| Resolve Findings | ✓ | ✓ | — | ✓ |
| Approve Impl. Files | — | ✓ | — | ✓ |
| Review Audit Results | View Own | ✓ | ✓ | ✓ |
| Generate Reports | Limited | ✓ | ✓ | ✓ |
| View Connection Diagrams | ✓ | ✓ | ✓ | ✓ |
| Manage Users | — | — | — | ✓ |
| System Configuration | — | — | — | ✓ |
| Import/Export Data | ✓ | ✓ | Export | ✓ |

---

## 8. Key Workflow Integrations

The following shows how key workflows connect to each other:

### Asset Lifecycle Flow:

```
Procurement → Asset Entry → Port Definition → Connection Documentation → Auditing → Decommission
```

### Audit Cycle:

```
Implementation File Upload → Approval → Audit Creation → Audit Execution → Finding Documentation → Resolution → Verification
```

### Change Management:

```
Change Request → Physical Change → System Update → Audit Verification → Approval
```

---

*— End of Document —*
