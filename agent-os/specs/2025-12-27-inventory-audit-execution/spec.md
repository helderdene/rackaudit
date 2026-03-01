# Specification: Inventory Audit Execution

## Goal
Enable operators to execute inventory audits by verifying physical devices against documented inventory records, with barcode/QR scanning support for device identification, and tracking verification progress across multiple operators working simultaneously.

## User Stories
- As an operator, I want to see a list of devices requiring verification grouped by rack/room so that I can systematically work through the datacenter
- As an auditor, I want to scan a device's QR code to quickly navigate to that device for verification so that I can work efficiently in the datacenter

## Specific Requirements

**Entry Point and Audit Status Transitions**
- Add "Start Audit" button on Audit Show page when status is "Pending" (inventory audit type only)
- Add "Continue Audit" button when status is "In Progress"
- Automatically transition audit status from "Pending" to "In Progress" when first verification is recorded
- Auto-complete audit when all devices and racks are verified (transition to "Completed")
- Mirror the connection audit entry point pattern from `Show.vue` with `can_start_audit` and `can_continue_audit` props

**Device Verification List Pre-population**
- Query devices based on audit scope: datacenter-level queries all devices in datacenter, room-level uses room, specific racks/devices use pivot tables
- Include empty racks (racks with no devices) for "nothing found here" confirmation
- Store verification items in new `audit_device_verifications` table for tracking individual device verification status
- Store empty rack confirmations in new `audit_rack_verifications` table
- Pre-populate verification records on first access to the execution page

**Verification Actions and Recording**
- Three verification statuses: Verified (device confirmed at location), Not Found (device missing), Discrepant (device found with issues)
- For Verified: confirm device exists physically at documented rack/U position, optional barcode match
- For Not Found: device not present at documented location, auto-create Finding
- For Discrepant: device found but with issues (wrong position, wrong asset tag, etc.), require notes, auto-create Finding
- Record verifying operator and timestamp for each verification

**Barcode/QR Scanning Integration**
- Implement camera-based QR code scanning using browser APIs (getUserMedia)
- Scanning a device QR code navigates/scrolls to that device in the verification list
- Scanning does NOT auto-verify - operator must manually confirm after scanning
- Provide manual search/select fallback when camera unavailable or impractical
- Leverage URL format from `QrCodePdfService`: `/devices/{id}` to extract device ID from scanned QR

**Multi-Operator Support and Device Locking**
- Allow multiple operators to work on the same audit simultaneously
- Implement soft device locking: when verifying a device, lock it for 5 minutes
- Show locked devices with the operator's name who has them locked
- Use Laravel Broadcasting (Echo) for real-time updates when devices are verified or locked
- Create events: `DeviceLocked`, `DeviceUnlocked`, `DeviceVerificationCompleted` mirroring connection audit events

**Grouping and Filtering**
- Group devices by rack for organized verification workflow
- Filter by room to divide work by physical location
- Filter by verification status (Pending, Verified, Not Found, Discrepant)
- Search by device name, asset tag, or serial number
- Display rack headers with device count and rack-level verification summary
- Show empty racks as separate verification items within their room/row context

**Progress Tracking**
- Display progress bar showing verified count vs total devices + empty racks
- Show breakdown: X verified, Y not found, Z discrepant, W pending
- Display real-time updates when other operators complete verifications
- Show per-rack progress within grouped view
- Follow progress stats pattern from `AuditExecutionService::getProgressStats()`

**Bulk Verification Operations**
- Support selecting multiple devices and marking them as verified in bulk
- Bulk verify only works for devices in "Pending" status
- Bulk operations should skip locked devices and notify the operator
- Mirror `bulkVerify()` pattern from `AuditExecutionService`

**Finding Auto-Creation**
- When device marked as Not Found or Discrepant, automatically create a Finding record
- Link Finding to the audit and the device verification
- Set Finding status to "Open" by default
- Extend Finding model to support `audit_device_verification_id` foreign key

## Visual Design

No visual mockups were provided. Follow existing UI patterns from Connection Audit Execution (`Execute.vue`) and `ExpectedConnections/Review.vue`:
- Statistics card at top showing progress summary with colored badges (verified=green, not found=red, discrepant=yellow)
- Progress bar showing completion percentage
- Table/list layout with selection checkboxes for bulk operations
- Row highlighting based on verification status
- Rack group headers with collapsible device lists
- Bulk action buttons above table for selected items
- Filter controls for narrowing the device list
- QR scanner button/modal for camera access
- Search input for device name/asset tag lookup

## Existing Code to Leverage

**`AuditExecutionService` (`app/Services/AuditExecutionService.php`)**
- Extend with inventory audit methods: `prepareDeviceVerificationItems()`, `getDeviceVerificationItems()`, `markDeviceVerified()`, `markDeviceNotFound()`, `markDeviceDiscrepant()`
- Reuse locking pattern with `lockDevice()`, `unlockDevice()`, `releaseExpiredLocks()`
- Reuse progress tracking pattern with inventory-specific stats

**`AuditConnectionVerification` model (`app/Models/AuditConnectionVerification.php`)**
- Use as template for `AuditDeviceVerification` model structure
- Replicate locking logic, scopes (pending, verified, locked, expiredLocks), and verification methods
- Follow same fillable, cast, and relationship patterns

**`Execute.vue` page (`resources/js/Pages/Audits/Execute.vue`)**
- Reuse page layout with stats card, progress bar, and verification table structure
- Follow filter and search patterns
- Adapt selection state and bulk action logic
- Reuse Echo channel subscription pattern for real-time updates

**`QrCodePdfService` (`app/Services/QrCodePdfService.php`)**
- Reference URL format `/devices/{id}` for parsing scanned QR codes
- Device QR codes contain direct links to device pages, extract ID for verification lookup

**Broadcasting Events (`app/Events/AuditExecution/`)**
- Create parallel events for inventory: `DeviceLocked`, `DeviceUnlocked`, `DeviceVerificationCompleted`
- Follow same channel naming and payload structure

## Out of Scope
- Audit creation workflow (covered by Audit Creation spec)
- Audit report generation and export
- Finding resolution workflow (separate spec)
- Finding management CRUD beyond auto-creation
- Editing or deleting existing verifications once recorded
- Audit editing or cancellation workflows
- Email notifications for audit completion
- Historical comparison of multiple audit runs
- Permission/role management (use existing audit assignee system)
- Auto-verification on scan (explicitly rejected - manual verification required)
