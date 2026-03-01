# Specification: Audit Creation

## Goal
Enable IT Managers and Auditors to create new audits with configurable scope (datacenter, room, or individual racks/devices), audit type (connection or inventory), and team assignment for execution.

## User Stories
- As an IT Manager, I want to create a connection audit for a specific datacenter so that I can verify all physical connections match the approved implementation file.
- As an Auditor, I want to create an inventory audit for selected racks and devices so that I can verify documented devices exist at their correct positions.

## Specific Requirements

**Audit Type Selection**
- Two mutually exclusive audit types: Connection Audit and Inventory Audit
- Connection Audit verifies physical connections against expected connections from an approved implementation file
- Inventory Audit verifies documented devices exist physically (barcodes/serial numbers) and are in correct rack/U position
- UI should clearly differentiate the purpose of each audit type with descriptive text

**Scope Selection - Datacenter Level**
- User can select a single datacenter as scope, which includes all racks within that datacenter
- When datacenter is selected, display rack count summary for confirmation
- For connection audits, automatically use the latest approved implementation file for that datacenter

**Scope Selection - Room Level**
- User can select a single room as scope, which includes all racks within that room
- Room dropdown should cascade from datacenter selection (show rooms for selected datacenter)
- Display rack count summary when room is selected

**Scope Selection - Individual Racks**
- User can multi-select specific racks from one or more rooms within the selected datacenter
- Provide searchable multi-select component with rack names and locations
- When racks are selected, allow further filtering to specific devices within those racks (partial scope)

**Partial Scope - Device Selection**
- Only available when "Individual Racks" scope type is selected
- Show devices for each selected rack with multi-select capability
- Display device name, asset tag, and U position for easy identification
- If no devices selected, audit covers all devices in the selected racks

**Audit Metadata Configuration**
- Name field (required): descriptive audit name
- Description field (optional): additional context or instructions
- Due date field (required): target completion date using date picker
- Multiple assignees field (required): multi-select users who can execute the audit

**Connection Audit - Implementation File Validation**
- Automatically find the latest approved implementation file for the selected scope (datacenter)
- Display the linked implementation file name and version for user confirmation
- Block audit creation if no approved implementation file exists
- Show error message with link to navigate to Implementation Files page to upload/approve one

**Role-Based Access Control**
- Only IT Manager and Auditor roles can access the audit creation interface
- Operators are excluded from creating audits (they can only execute)
- Use form request authorization pattern matching existing StoreDatacenterRequest

**Audit Status on Creation**
- All audits created in "pending" status ready for immediate execution
- No future scheduling or draft states
- Created audit should redirect to audit detail/list page

## Visual Design
No visual assets provided.

## Existing Code to Leverage

**DatacenterForm.vue Component Pattern**
- Use same Form component structure from @inertiajs/vue3
- Follow section-based layout with HeadingSmall for grouping
- Reuse Input, Label, Button components from existing UI library
- Match error handling pattern with InputError component

**StoreDatacenterRequest.php Form Request Pattern**
- Follow same authorization pattern using `AUTHORIZED_ROLES` constant
- Use array-based validation rules matching existing convention
- Include custom error messages for all validation rules
- Use `hasAnyRole()` method for role checking

**Datacenter/Room/Rack Model Hierarchy**
- Leverage existing relationships: Datacenter -> Room -> Row -> Rack -> Device
- Use Datacenter.hasApprovedImplementationFiles() method for validation
- Follow model patterns with HasFactory, Loggable concerns

**ImplementationFile Model**
- Use isApproved() method to filter for approved files
- Leverage datacenter relationship to find files by scope
- Follow version_number ordering for finding latest approved file

**Existing Enum Patterns**
- Create new enums following existing patterns in app/Enums/ (e.g., RackStatus, RoomType)
- Use backed string enums with label methods

## Out of Scope
- Audit execution workflow and mobile interface
- Discrepancy detection engine and finding management
- Audit templates or recurring/scheduled audit series
- Future date scheduling (audits are immediately available)
- Row-level scope selection
- Combined connection + inventory audits in single audit
- Audit editing or cloning functionality
- Audit deletion or archiving
- Audit notifications or reminders
- Audit reporting or export
