<?php

namespace Database\Seeders;

use App\Enums\HelpArticleType;
use App\Models\HelpArticle;
use App\Models\HelpTour;
use App\Models\HelpTourStep;
use Illuminate\Database\Seeder;

class HelpDocumentationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createGettingStartedArticles();
        $this->createDashboardArticles();
        $this->createAuditArticlesAndTours();
        $this->createInventoryAuditArticlesAndTour();
        $this->createRackElevationArticlesAndTour();
        $this->createImplementationFilesArticlesAndTour();
        $this->createConnectionArticles();
        $this->createDeviceArticles();
        $this->createReportingArticles();
    }

    /**
     * Create Getting Started help articles.
     */
    private function createGettingStartedArticles(): void
    {
        HelpArticle::updateOrCreate(
            ['slug' => 'welcome-to-rackaudit'],
            [
                'title' => 'Welcome to RackAudit',
                'context_key' => 'dashboard',
                'article_type' => HelpArticleType::Article,
                'category' => 'Getting Started',
                'sort_order' => 1,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Welcome to RackAudit

RackAudit is a comprehensive datacenter management and auditing system designed to provide real-time visibility into your physical infrastructure.

## What You Can Do

- **Manage Infrastructure**: Track datacenters, rooms, rows, and racks with visual layouts
- **Track Assets**: Maintain a complete inventory of devices with specifications and locations
- **Document Connections**: Record and visualize port connections between devices
- **Conduct Audits**: Verify physical connections match your implementation documentation
- **Generate Reports**: Create comprehensive reports for compliance and operations

## Quick Navigation

- **Dashboard**: View key metrics and recent activity
- **Datacenters**: Manage your datacenter hierarchy
- **Racks**: Visualize rack elevations and device placement
- **Audits**: Create and execute connection or inventory audits
- **Reports**: Generate and schedule various reports

## Getting Help

Click the **?** button on any page to access contextual help and feature tours.
MARKDOWN,
            ]
        );

        HelpArticle::updateOrCreate(
            ['slug' => 'understanding-the-workflow'],
            [
                'title' => 'Understanding the Workflow',
                'context_key' => 'dashboard',
                'article_type' => HelpArticleType::Article,
                'category' => 'Getting Started',
                'sort_order' => 2,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Understanding the RackAudit Workflow

RackAudit follows a structured workflow for datacenter management and auditing.

## Typical Workflow

### 1. Set Up Infrastructure
First, define your datacenter hierarchy:
- Create **Datacenters** to represent physical locations
- Add **Rooms** within each datacenter
- Define **Rows** to organize racks
- Create **Racks** and position them in your layout

### 2. Add Devices
Populate your racks with equipment:
- Define **Device Types** (servers, switches, PDUs, etc.)
- Add **Devices** to specific rack positions
- Document **Ports** on each device

### 3. Document Connections
Record your cabling:
- Create **Connections** between device ports
- Upload **Implementation Files** with expected configurations

### 4. Conduct Audits
Verify physical reality matches documentation:
- Create a **Connection Audit** or **Inventory Audit**
- Walk through verifications on the datacenter floor
- Document any discrepancies found
- Generate audit reports

### 5. Resolve Findings
Address any issues discovered:
- Review **Findings** from audits
- Update connections or documentation as needed
- Track resolution progress
MARKDOWN,
            ]
        );

        HelpArticle::updateOrCreate(
            ['slug' => 'keyboard-shortcuts'],
            [
                'title' => 'Keyboard Shortcuts',
                'context_key' => null,
                'article_type' => HelpArticleType::Article,
                'category' => 'Getting Started',
                'sort_order' => 3,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Keyboard Shortcuts

Speed up your workflow with these keyboard shortcuts.

## Global Shortcuts

| Shortcut | Action |
|----------|--------|
| `?` | Open help sidebar |
| `Esc` | Close dialogs and sidebars |
| `/` | Focus search (on list pages) |

## Audit Execution

| Shortcut | Action |
|----------|--------|
| `v` | Mark selected as verified |
| `d` | Mark selected as discrepant |
| `n` | Move to next item |
| `p` | Move to previous item |
| `Space` | Toggle selection |

## Rack Elevation

| Shortcut | Action |
|----------|--------|
| `f` | Toggle front/rear view |
| `+` / `-` | Zoom in/out |
| `Arrow keys` | Navigate between units |
MARKDOWN,
            ]
        );
    }

    /**
     * Create Dashboard help articles.
     */
    private function createDashboardArticles(): void
    {
        HelpArticle::updateOrCreate(
            ['slug' => 'dashboard-overview'],
            [
                'title' => 'Dashboard Overview',
                'context_key' => 'dashboard',
                'article_type' => HelpArticleType::Article,
                'category' => 'Dashboard',
                'sort_order' => 1,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Dashboard Overview

The Dashboard provides a real-time snapshot of your datacenter operations.

## Key Metrics

The top cards show at-a-glance statistics:
- **Total Devices**: Count of all tracked equipment
- **Rack Utilization**: Average space usage across racks
- **Pending Audits**: Audits awaiting execution
- **Open Findings**: Unresolved issues from audits

## Charts and Trends

### Severity Distribution
Shows the breakdown of finding severities (Critical, High, Medium, Low) across all open findings.

### Capacity Trends
Displays rack utilization over time to help with capacity planning.

### Device Count Trends
Track how your inventory grows over time.

### Audit Completion
View audit completion rates and trends.

## Activity Feed

The activity feed shows recent changes across the system, including:
- New devices added
- Connections modified
- Audits completed
- Findings resolved

## Filtering

Use the datacenter filter at the top to focus on a specific location.
MARKDOWN,
            ]
        );

        HelpArticle::updateOrCreate(
            ['slug' => 'dashboard-metrics-explained'],
            [
                'title' => 'Understanding Dashboard Metrics',
                'context_key' => 'dashboard',
                'article_type' => HelpArticleType::Article,
                'category' => 'Dashboard',
                'sort_order' => 2,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Understanding Dashboard Metrics

Learn what each metric means and how it's calculated.

## Rack Utilization

**Calculation**: (Used U-Space / Total U-Space) × 100

A rack's utilization considers:
- Devices installed and their heights
- Reserved spaces (if marked)

**Target Range**: 70-80% is optimal. Higher utilization may limit future expansion.

## Pending Audits

Counts audits in these statuses:
- **Scheduled**: Created but not started
- **In Progress**: Partially completed

## Open Findings

Findings that need attention:
- **New**: Recently created, unreviewed
- **Acknowledged**: Reviewed but not resolved
- **In Progress**: Being actively addressed

Resolved and closed findings are not counted.

## Trend Calculations

All trends compare the current period to the previous equivalent period:
- Daily: Today vs yesterday
- Weekly: This week vs last week
- Monthly: This month vs last month

A green arrow indicates improvement; red indicates attention needed.
MARKDOWN,
            ]
        );
    }

    /**
     * Create Audit help articles and tour.
     */
    private function createAuditArticlesAndTours(): void
    {
        // Tour step articles for Connection Audit Execution
        $tourStepArticles = [];

        $tourStepArticles[] = HelpArticle::updateOrCreate(
            ['slug' => 'audit-execute-welcome'],
            [
                'title' => 'Connection Audit Execution',
                'context_key' => 'audits.execute.connection',
                'article_type' => HelpArticleType::TourStep,
                'category' => 'Auditing',
                'sort_order' => 1,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Connection Audit Execution

Welcome to the Connection Audit Execution screen. This is where you verify that physical connections in your datacenter match the expected configuration from your implementation files.

You'll work through a list of connections, marking each as **verified** (matches) or **discrepant** (doesn't match).
MARKDOWN,
            ]
        );

        $tourStepArticles[] = HelpArticle::updateOrCreate(
            ['slug' => 'audit-execute-progress'],
            [
                'title' => 'Audit Progress Tracking',
                'context_key' => 'audits.execute.connection',
                'article_type' => HelpArticleType::TourStep,
                'category' => 'Auditing',
                'sort_order' => 2,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Progress Tracking

The progress bar shows your audit completion status:

- **Verified**: Connections confirmed to match
- **Discrepant**: Connections with issues
- **Pending**: Not yet checked

Your progress is saved automatically as you work.
MARKDOWN,
            ]
        );

        $tourStepArticles[] = HelpArticle::updateOrCreate(
            ['slug' => 'audit-execute-filters'],
            [
                'title' => 'Filtering Verifications',
                'context_key' => 'audits.execute.connection',
                'article_type' => HelpArticleType::TourStep,
                'category' => 'Auditing',
                'sort_order' => 3,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Filtering Options

Use filters to focus on specific connections:

- **Comparison Status**: Show only matched, missing, or extra connections
- **Verification Status**: Filter by pending, verified, or discrepant
- **Search**: Find connections by device name or port label

Filter by "pending" to see only items that still need verification.
MARKDOWN,
            ]
        );

        $tourStepArticles[] = HelpArticle::updateOrCreate(
            ['slug' => 'audit-execute-table'],
            [
                'title' => 'Verification Table',
                'context_key' => 'audits.execute.connection',
                'article_type' => HelpArticleType::TourStep,
                'category' => 'Auditing',
                'sort_order' => 4,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Verification Table

Each row represents a connection to verify:

- **Source**: The originating device and port
- **Destination**: The target device and port
- **Comparison**: Whether the connection matches, is missing, or is extra
- **Status**: Current verification status

Click on a row to open the verification dialog.
MARKDOWN,
            ]
        );

        $tourStepArticles[] = HelpArticle::updateOrCreate(
            ['slug' => 'audit-execute-actions'],
            [
                'title' => 'Verification Actions',
                'context_key' => 'audits.execute.connection',
                'article_type' => HelpArticleType::TourStep,
                'category' => 'Auditing',
                'sort_order' => 5,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Taking Action

When verifying a connection:

1. **Verify**: Mark as correct if the physical connection matches
2. **Discrepant**: Mark if there's a mismatch and select the discrepancy type:
   - **Missing**: Expected connection not found
   - **Extra**: Connection exists but wasn't expected
   - **Mismatch**: Connected to wrong port/device
   - **Incorrect Labeling**: Physical labels don't match

Add notes to document any observations.
MARKDOWN,
            ]
        );

        $tourStepArticles[] = HelpArticle::updateOrCreate(
            ['slug' => 'audit-execute-bulk'],
            [
                'title' => 'Bulk Verification',
                'context_key' => 'audits.execute.connection',
                'article_type' => HelpArticleType::TourStep,
                'category' => 'Auditing',
                'sort_order' => 6,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Bulk Verification

Speed up your workflow by verifying multiple connections at once:

1. Select connections using the checkboxes
2. Click **Bulk Verify** to mark all selected as verified

This is useful when you've physically checked a group of connections and they all match.

**Note**: Only "matched" connections can be bulk verified. Discrepancies should be reviewed individually.
MARKDOWN,
            ]
        );

        // Create the tour
        $tour = HelpTour::updateOrCreate(
            ['slug' => 'connection-audit-execution-tour'],
            [
                'name' => 'Connection Audit Execution Tour',
                'context_key' => 'audits.execute.connection',
                'description' => 'Learn how to execute a connection audit and verify physical connections against your implementation documentation.',
                'is_active' => true,
            ]
        );

        // Delete existing steps and recreate
        $tour->steps()->delete();

        // Create tour steps
        $stepConfigs = [
            ['selector' => '[data-tour="audit-header"]', 'position' => 'bottom', 'article_slug' => 'audit-execute-welcome'],
            ['selector' => '[data-tour="progress-bar"]', 'position' => 'bottom', 'article_slug' => 'audit-execute-progress'],
            ['selector' => '[data-tour="filters"]', 'position' => 'bottom', 'article_slug' => 'audit-execute-filters'],
            ['selector' => '[data-tour="verification-table"]', 'position' => 'top', 'article_slug' => 'audit-execute-table'],
            ['selector' => '[data-tour="action-buttons"]', 'position' => 'left', 'article_slug' => 'audit-execute-actions'],
            ['selector' => '[data-tour="bulk-verify"]', 'position' => 'bottom', 'article_slug' => 'audit-execute-bulk'],
        ];

        foreach ($stepConfigs as $order => $config) {
            $article = collect($tourStepArticles)->firstWhere('slug', $config['article_slug']);
            if ($article) {
                HelpTourStep::create([
                    'help_tour_id' => $tour->id,
                    'help_article_id' => $article->id,
                    'target_selector' => $config['selector'],
                    'position' => $config['position'],
                    'step_order' => $order + 1,
                ]);
            }
        }

        // Full article for help sidebar
        HelpArticle::updateOrCreate(
            ['slug' => 'connection-audit-guide'],
            [
                'title' => 'Complete Guide to Connection Audits',
                'context_key' => 'audits.execute.connection',
                'article_type' => HelpArticleType::Article,
                'category' => 'Auditing',
                'sort_order' => 10,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Complete Guide to Connection Audits

A connection audit verifies that physical cable connections in your datacenter match your implementation documentation.

## Before You Start

1. **Upload an Implementation File**: Ensure you have an approved implementation file with expected connections
2. **Create an Audit**: Go to Audits → Create and select "Connection Audit"
3. **Select Scope**: Choose the datacenter and implementation file to audit against

## During the Audit

### Understanding Comparison Status

- **Matched**: Connection exists in both documentation and reality
- **Missing**: Expected in documentation but not physically present
- **Extra**: Exists physically but not in documentation

### Verification Workflow

1. Take your device (tablet/laptop) to the datacenter floor
2. Navigate to each connection in the list
3. Physically verify the cable connection
4. Mark as verified or document discrepancy

### Best Practices

- **Work systematically**: Filter by rack or row to work through connections in physical order
- **Document thoroughly**: Add notes for any unusual observations
- **Take photos**: Attach evidence for discrepancies
- **Verify in pairs**: Two people can speed up the process

## After the Audit

1. Review all discrepancies
2. Create findings for issues that need resolution
3. Complete the audit to generate the report
4. Share findings with the appropriate teams

## Handling Discrepancies

| Type | Meaning | Action |
|------|---------|--------|
| Missing | Cable not found | Investigate if removed or never installed |
| Extra | Undocumented cable | Update documentation or remove |
| Mismatch | Wrong connection | Correct the cable or update docs |
| Label Issue | Labels incorrect | Update physical labels |
MARKDOWN,
            ]
        );

        // Audit creation articles
        HelpArticle::updateOrCreate(
            ['slug' => 'creating-an-audit'],
            [
                'title' => 'Creating an Audit',
                'context_key' => 'audits.create',
                'article_type' => HelpArticleType::Article,
                'category' => 'Auditing',
                'sort_order' => 5,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Creating an Audit

Learn how to set up a new audit for your datacenter.

## Audit Types

### Connection Audit
Verifies physical cable connections against implementation documentation.

**When to use**: After new installations, during periodic verification, or when discrepancies are suspected.

### Inventory Audit
Verifies device presence, location, and condition.

**When to use**: For asset verification, compliance checks, or inventory reconciliation.

## Creating a Connection Audit

1. Navigate to **Audits** → **Create**
2. Select **Connection Audit**
3. Choose the **Datacenter** to audit
4. Select the **Implementation File** to verify against
5. Give the audit a descriptive **Name**
6. Click **Create Audit**

## Creating an Inventory Audit

1. Navigate to **Audits** → **Create**
2. Select **Inventory Audit**
3. Choose the **Datacenter** to audit
4. Optionally filter by **Room** or **Row**
5. Give the audit a descriptive **Name**
6. Click **Create Audit**

## Audit Naming Tips

Use descriptive names that include:
- Date or quarter
- Datacenter name
- Purpose (e.g., "Q1 2026 NYC-DC1 Quarterly Connection Audit")
MARKDOWN,
            ]
        );
    }

    /**
     * Create Inventory Audit articles and tour.
     */
    private function createInventoryAuditArticlesAndTour(): void
    {
        $tourStepArticles = [];

        $tourStepArticles[] = HelpArticle::updateOrCreate(
            ['slug' => 'inventory-audit-welcome'],
            [
                'title' => 'Inventory Audit Execution',
                'context_key' => 'audits.execute.inventory',
                'article_type' => HelpArticleType::TourStep,
                'category' => 'Auditing',
                'sort_order' => 1,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Inventory Audit Execution

Welcome to the Inventory Audit screen. Here you'll verify that devices in your datacenter are present, correctly positioned, and in good condition.

Work through each device in the scope, confirming its status.
MARKDOWN,
            ]
        );

        $tourStepArticles[] = HelpArticle::updateOrCreate(
            ['slug' => 'inventory-audit-device-list'],
            [
                'title' => 'Device List',
                'context_key' => 'audits.execute.inventory',
                'article_type' => HelpArticleType::TourStep,
                'category' => 'Auditing',
                'sort_order' => 2,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Device List

The device list shows all equipment in your audit scope:

- **Device Name**: Hostname or identifier
- **Asset Tag**: Physical asset tracking number
- **Location**: Rack and U-position
- **Device Type**: Server, switch, PDU, etc.
- **Status**: Current verification status

Click on any device to verify it.
MARKDOWN,
            ]
        );

        $tourStepArticles[] = HelpArticle::updateOrCreate(
            ['slug' => 'inventory-audit-verification'],
            [
                'title' => 'Verifying Devices',
                'context_key' => 'audits.execute.inventory',
                'article_type' => HelpArticleType::TourStep,
                'category' => 'Auditing',
                'sort_order' => 3,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Verifying Devices

For each device, verify:

1. **Present**: Device is physically in the location
2. **Asset Tag Matches**: Physical tag matches the record
3. **Condition**: Note any physical damage or issues

Verification options:
- **Verified**: Everything checks out
- **Location Issue**: Device in wrong position
- **Missing**: Device not found
- **Condition Issue**: Physical problems observed
MARKDOWN,
            ]
        );

        $tourStepArticles[] = HelpArticle::updateOrCreate(
            ['slug' => 'inventory-audit-rack-view'],
            [
                'title' => 'Rack View Navigation',
                'context_key' => 'audits.execute.inventory',
                'article_type' => HelpArticleType::TourStep,
                'category' => 'Auditing',
                'sort_order' => 4,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Rack View

Use the rack view to see devices organized by physical location.

- Navigate between racks using the rack selector
- View front and rear positions
- See devices in their actual U-positions
- Click directly on a device to verify it

This view is helpful when walking through the datacenter rack by rack.
MARKDOWN,
            ]
        );

        // Create the tour
        $tour = HelpTour::updateOrCreate(
            ['slug' => 'inventory-audit-execution-tour'],
            [
                'name' => 'Inventory Audit Execution Tour',
                'context_key' => 'audits.execute.inventory',
                'description' => 'Learn how to execute an inventory audit and verify device presence and condition.',
                'is_active' => true,
            ]
        );

        $tour->steps()->delete();

        $stepConfigs = [
            ['selector' => '[data-tour="audit-header"]', 'position' => 'bottom', 'article_slug' => 'inventory-audit-welcome'],
            ['selector' => '[data-tour="device-list"]', 'position' => 'right', 'article_slug' => 'inventory-audit-device-list'],
            ['selector' => '[data-tour="verification-panel"]', 'position' => 'left', 'article_slug' => 'inventory-audit-verification'],
            ['selector' => '[data-tour="rack-view"]', 'position' => 'left', 'article_slug' => 'inventory-audit-rack-view'],
        ];

        foreach ($stepConfigs as $order => $config) {
            $article = collect($tourStepArticles)->firstWhere('slug', $config['article_slug']);
            if ($article) {
                HelpTourStep::create([
                    'help_tour_id' => $tour->id,
                    'help_article_id' => $article->id,
                    'target_selector' => $config['selector'],
                    'position' => $config['position'],
                    'step_order' => $order + 1,
                ]);
            }
        }

        HelpArticle::updateOrCreate(
            ['slug' => 'inventory-audit-guide'],
            [
                'title' => 'Complete Guide to Inventory Audits',
                'context_key' => 'audits.execute.inventory',
                'article_type' => HelpArticleType::Article,
                'category' => 'Auditing',
                'sort_order' => 15,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Complete Guide to Inventory Audits

An inventory audit verifies the physical presence, location, and condition of devices in your datacenter.

## Purpose

- **Compliance**: Meet regulatory requirements for asset tracking
- **Accuracy**: Ensure records match physical reality
- **Maintenance**: Identify equipment needing attention
- **Security**: Detect unauthorized equipment

## Verification Checklist

For each device, confirm:

- [ ] Device is physically present in the documented location
- [ ] Asset tag matches the system record
- [ ] Serial number is visible and correct (if applicable)
- [ ] Device is powered on/operational (if expected)
- [ ] No visible damage or issues

## Handling Issues

### Device Not Found
1. Check adjacent racks and rows
2. Verify with recent move records
3. Mark as "Missing" and document location checked

### Wrong Location
1. Document actual location found
2. Mark as "Location Issue"
3. Decide whether to update records or move device

### Condition Problems
1. Document the issue with photos
2. Mark as "Condition Issue"
3. Create a finding for maintenance team

## Tips for Efficiency

- **Work by rack**: Verify all devices in a rack before moving on
- **Use QR codes**: Scan rack/device QR codes for quick navigation
- **Team approach**: One person reads, another verifies
- **Tablet mode**: Use the mobile-friendly interface
MARKDOWN,
            ]
        );
    }

    /**
     * Create Rack Elevation articles and tour.
     */
    private function createRackElevationArticlesAndTour(): void
    {
        $tourStepArticles = [];

        $tourStepArticles[] = HelpArticle::updateOrCreate(
            ['slug' => 'rack-elevation-welcome'],
            [
                'title' => 'Rack Elevation View',
                'context_key' => 'racks.elevation',
                'article_type' => HelpArticleType::TourStep,
                'category' => 'Infrastructure',
                'sort_order' => 1,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Rack Elevation View

The rack elevation provides a visual representation of device placement within a rack.

This view shows:
- Front and rear perspectives
- U-position numbering
- Device placement and height
- Available space
MARKDOWN,
            ]
        );

        $tourStepArticles[] = HelpArticle::updateOrCreate(
            ['slug' => 'rack-elevation-view-toggle'],
            [
                'title' => 'Front and Rear Views',
                'context_key' => 'racks.elevation',
                'article_type' => HelpArticleType::TourStep,
                'category' => 'Infrastructure',
                'sort_order' => 2,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Front and Rear Views

Toggle between front and rear views to see device placement on both sides of the rack.

- **Front View**: Shows devices installed facing front
- **Rear View**: Shows devices installed facing rear

Some devices (like patch panels) may only appear on one side.
MARKDOWN,
            ]
        );

        $tourStepArticles[] = HelpArticle::updateOrCreate(
            ['slug' => 'rack-elevation-devices'],
            [
                'title' => 'Device Placement',
                'context_key' => 'racks.elevation',
                'article_type' => HelpArticleType::TourStep,
                'category' => 'Infrastructure',
                'sort_order' => 3,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Device Placement

Each device is shown at its U-position:

- **Color Coding**: Devices are colored by type
- **Height**: Device spans its actual U-height
- **Labels**: Shows device name and key info

Click on a device to view details or edit.
MARKDOWN,
            ]
        );

        $tourStepArticles[] = HelpArticle::updateOrCreate(
            ['slug' => 'rack-elevation-capacity'],
            [
                'title' => 'Capacity Information',
                'context_key' => 'racks.elevation',
                'article_type' => HelpArticleType::TourStep,
                'category' => 'Infrastructure',
                'sort_order' => 4,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Capacity Information

The sidebar shows rack capacity metrics:

- **U-Space Used**: How many units are occupied
- **U-Space Available**: Remaining space
- **Power Consumption**: Total power draw
- **Weight**: Combined weight of installed equipment

Use this to plan new installations.
MARKDOWN,
            ]
        );

        $tourStepArticles[] = HelpArticle::updateOrCreate(
            ['slug' => 'rack-elevation-actions'],
            [
                'title' => 'Quick Actions',
                'context_key' => 'racks.elevation',
                'article_type' => HelpArticleType::TourStep,
                'category' => 'Infrastructure',
                'sort_order' => 5,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Quick Actions

Available actions from the elevation view:

- **Add Device**: Click an empty U-slot to add a new device
- **Move Device**: Drag a device to a new position
- **View Connections**: See port connections for any device
- **Print QR Code**: Generate rack identification label
- **Export**: Download rack diagram as image
MARKDOWN,
            ]
        );

        // Create the tour
        $tour = HelpTour::updateOrCreate(
            ['slug' => 'rack-elevation-tour'],
            [
                'name' => 'Rack Elevation Tour',
                'context_key' => 'racks.elevation',
                'description' => 'Learn how to use the rack elevation view to visualize and manage device placement.',
                'is_active' => true,
            ]
        );

        $tour->steps()->delete();

        $stepConfigs = [
            ['selector' => '[data-tour="elevation-diagram"]', 'position' => 'right', 'article_slug' => 'rack-elevation-welcome'],
            ['selector' => '[data-tour="view-toggle"]', 'position' => 'bottom', 'article_slug' => 'rack-elevation-view-toggle'],
            ['selector' => '[data-tour="device-slot"]', 'position' => 'right', 'article_slug' => 'rack-elevation-devices'],
            ['selector' => '[data-tour="capacity-panel"]', 'position' => 'left', 'article_slug' => 'rack-elevation-capacity'],
            ['selector' => '[data-tour="action-bar"]', 'position' => 'bottom', 'article_slug' => 'rack-elevation-actions'],
        ];

        foreach ($stepConfigs as $order => $config) {
            $article = collect($tourStepArticles)->firstWhere('slug', $config['article_slug']);
            if ($article) {
                HelpTourStep::create([
                    'help_tour_id' => $tour->id,
                    'help_article_id' => $article->id,
                    'target_selector' => $config['selector'],
                    'position' => $config['position'],
                    'step_order' => $order + 1,
                ]);
            }
        }

        HelpArticle::updateOrCreate(
            ['slug' => 'rack-elevation-guide'],
            [
                'title' => 'Working with Rack Elevations',
                'context_key' => 'racks.elevation',
                'article_type' => HelpArticleType::Article,
                'category' => 'Infrastructure',
                'sort_order' => 10,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Working with Rack Elevations

The rack elevation view is your visual interface for managing device placement within racks.

## Understanding the View

### U-Positions
Racks are measured in "U" (rack units), with 1U = 1.75 inches (44.45mm). A standard 42U rack has positions numbered 1-42 from bottom to top.

### Device Colors
Devices are color-coded by type for quick identification:
- **Blue**: Servers
- **Green**: Network equipment
- **Orange**: Storage
- **Red**: Power (PDUs)
- **Gray**: Infrastructure (panels, blanks)

## Adding Devices

### From Elevation View
1. Click on an empty U-slot
2. Select or search for the device type
3. Enter device details
4. Confirm placement

### From Device Creation
1. Go to Devices → Create
2. Fill in device details
3. Select rack and U-position
4. The elevation will update automatically

## Managing Placement

### Moving Devices
- Drag and drop to reposition within the rack
- System checks for conflicts automatically

### Removing Devices
- Click device → More → Remove from rack
- Device stays in inventory but loses position

## Capacity Planning

Use the capacity panel to ensure:
- Sufficient U-space for new equipment
- Power budget isn't exceeded
- Weight limits are respected

## Printing and Sharing

- **QR Code**: Print rack QR labels for physical identification
- **Export Image**: Download the elevation as PNG/PDF
- **Print View**: Optimized layout for printing
MARKDOWN,
            ]
        );
    }

    /**
     * Create Implementation Files articles and tour.
     */
    private function createImplementationFilesArticlesAndTour(): void
    {
        $tourStepArticles = [];

        $tourStepArticles[] = HelpArticle::updateOrCreate(
            ['slug' => 'impl-files-welcome'],
            [
                'title' => 'Implementation File Management',
                'context_key' => 'implementations.files',
                'article_type' => HelpArticleType::TourStep,
                'category' => 'Implementation Files',
                'sort_order' => 1,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Implementation File Management

Implementation files contain your expected connection configurations. They're the source of truth for connection audits.

This page lets you upload, review, and manage these files.
MARKDOWN,
            ]
        );

        $tourStepArticles[] = HelpArticle::updateOrCreate(
            ['slug' => 'impl-files-upload'],
            [
                'title' => 'Uploading Files',
                'context_key' => 'implementations.files',
                'article_type' => HelpArticleType::TourStep,
                'category' => 'Implementation Files',
                'sort_order' => 2,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Uploading Files

To upload a new implementation file:

1. Click **Upload File**
2. Select your Excel or CSV file
3. Map columns to connection fields
4. Review the preview
5. Submit for processing

Supported formats: `.xlsx`, `.xls`, `.csv`
MARKDOWN,
            ]
        );

        $tourStepArticles[] = HelpArticle::updateOrCreate(
            ['slug' => 'impl-files-versions'],
            [
                'title' => 'Version History',
                'context_key' => 'implementations.files',
                'article_type' => HelpArticleType::TourStep,
                'category' => 'Implementation Files',
                'sort_order' => 3,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Version History

Each file maintains version history:

- **Current**: The active version for audits
- **Previous**: Older versions for reference
- **Draft**: Uploaded but not yet approved

You can compare any two versions to see what changed.
MARKDOWN,
            ]
        );

        $tourStepArticles[] = HelpArticle::updateOrCreate(
            ['slug' => 'impl-files-comparison'],
            [
                'title' => 'Comparing Versions',
                'context_key' => 'implementations.files',
                'article_type' => HelpArticleType::TourStep,
                'category' => 'Implementation Files',
                'sort_order' => 4,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Comparing Versions

The comparison view highlights differences:

- **Added**: New connections in the newer version
- **Removed**: Connections removed from older version
- **Modified**: Connections with changed details

Use this to verify file updates before approval.
MARKDOWN,
            ]
        );

        $tourStepArticles[] = HelpArticle::updateOrCreate(
            ['slug' => 'impl-files-approval'],
            [
                'title' => 'Approval Workflow',
                'context_key' => 'implementations.files',
                'article_type' => HelpArticleType::TourStep,
                'category' => 'Implementation Files',
                'sort_order' => 5,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Approval Workflow

Before a file can be used for audits, it must be approved:

1. Upload creates a **Draft** version
2. Review the file content and mapping
3. Click **Approve** to make it the current version
4. The file is now available for audits

Rejecting returns the file for corrections.
MARKDOWN,
            ]
        );

        // Create the tour
        $tour = HelpTour::updateOrCreate(
            ['slug' => 'implementation-files-tour'],
            [
                'name' => 'Implementation Files Tour',
                'context_key' => 'implementations.files',
                'description' => 'Learn how to upload, manage, and approve implementation files for connection audits.',
                'is_active' => true,
            ]
        );

        $tour->steps()->delete();

        $stepConfigs = [
            ['selector' => '[data-tour="file-header"]', 'position' => 'bottom', 'article_slug' => 'impl-files-welcome'],
            ['selector' => '[data-tour="upload-button"]', 'position' => 'bottom', 'article_slug' => 'impl-files-upload'],
            ['selector' => '[data-tour="version-list"]', 'position' => 'right', 'article_slug' => 'impl-files-versions'],
            ['selector' => '[data-tour="comparison-view"]', 'position' => 'top', 'article_slug' => 'impl-files-comparison'],
            ['selector' => '[data-tour="approval-actions"]', 'position' => 'left', 'article_slug' => 'impl-files-approval'],
        ];

        foreach ($stepConfigs as $order => $config) {
            $article = collect($tourStepArticles)->firstWhere('slug', $config['article_slug']);
            if ($article) {
                HelpTourStep::create([
                    'help_tour_id' => $tour->id,
                    'help_article_id' => $article->id,
                    'target_selector' => $config['selector'],
                    'position' => $config['position'],
                    'step_order' => $order + 1,
                ]);
            }
        }

        HelpArticle::updateOrCreate(
            ['slug' => 'implementation-files-guide'],
            [
                'title' => 'Complete Guide to Implementation Files',
                'context_key' => 'implementations.files',
                'article_type' => HelpArticleType::Article,
                'category' => 'Implementation Files',
                'sort_order' => 10,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Complete Guide to Implementation Files

Implementation files define the expected connection configuration for your datacenter. They're essential for connection audits.

## File Format

### Required Columns
Your file must include:
- **Source Device**: Name or identifier of the originating device
- **Source Port**: Port label on the source device
- **Destination Device**: Name or identifier of the target device
- **Destination Port**: Port label on the destination device

### Optional Columns
Additional information you can include:
- **Cable Type**: Fiber, Cat6, etc.
- **Cable Color**: For visual identification
- **Cable Length**: In meters or feet
- **Notes**: Any additional information

## Column Mapping

During upload, you'll map your file's columns to RackAudit fields. The system will:
- Auto-detect common column names
- Allow manual mapping for custom headers
- Validate data formats

## Device Matching

The system matches devices by:
1. Exact name match
2. Asset tag match
3. Hostname match

Unmatched devices are flagged for review.

## Best Practices

### File Preparation
- Use consistent device naming
- Ensure port labels match physical labels
- Remove empty rows and formatting
- Use a template for consistency

### Version Control
- Upload new versions rather than modifying existing
- Document changes in version notes
- Keep the previous version until new one is verified

### Approval Process
- Verify column mapping is correct
- Spot-check a sample of connections
- Compare with previous version for unexpected changes
- Only approve when ready for audits
MARKDOWN,
            ]
        );
    }

    /**
     * Create Connection help articles.
     */
    private function createConnectionArticles(): void
    {
        HelpArticle::updateOrCreate(
            ['slug' => 'managing-connections'],
            [
                'title' => 'Managing Connections',
                'context_key' => 'connections.index',
                'article_type' => HelpArticleType::Article,
                'category' => 'Connections',
                'sort_order' => 1,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Managing Connections

Connections document the physical cable links between device ports.

## Viewing Connections

The connections list shows:
- **Source**: Device and port where cable originates
- **Destination**: Device and port where cable terminates
- **Cable Type**: Type of cable (fiber, copper, etc.)
- **Status**: Active, planned, or decommissioned

## Filtering

Filter connections by:
- Datacenter
- Device name
- Port type
- Connection status

## Connection Details

Click on a connection to see:
- Full path information
- Cable specifications
- Related audit history
- Creation and modification dates

## Export Options

Export connections as:
- CSV for spreadsheet analysis
- PDF for documentation
- Connection diagram for visualization
MARKDOWN,
            ]
        );

        HelpArticle::updateOrCreate(
            ['slug' => 'creating-connections'],
            [
                'title' => 'Creating Connections',
                'context_key' => 'connections.create',
                'article_type' => HelpArticleType::Article,
                'category' => 'Connections',
                'sort_order' => 2,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Creating Connections

Document a new physical connection between devices.

## Steps to Create

1. Navigate to **Connections** → **Create**
2. Select the **Source Device**
3. Choose the **Source Port** (shows available ports)
4. Select the **Destination Device**
5. Choose the **Destination Port**
6. Add cable details (optional):
   - Cable type
   - Cable color
   - Cable length
   - Label
7. Click **Create Connection**

## Port Availability

Ports shown as available:
- Not already connected
- Compatible port type (matching the opposite end)

## Bulk Creation

For multiple connections:
1. Use the **Import** feature
2. Upload a CSV with connection data
3. Review and confirm mappings
4. Create all connections at once

## Validation

The system validates:
- Source and destination are different devices
- Ports aren't already connected
- Port types are compatible
MARKDOWN,
            ]
        );

        HelpArticle::updateOrCreate(
            ['slug' => 'connection-diagrams'],
            [
                'title' => 'Connection Diagrams',
                'context_key' => 'connections.diagram',
                'article_type' => HelpArticleType::Article,
                'category' => 'Connections',
                'sort_order' => 3,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Connection Diagrams

Visualize your connection topology in diagram form.

## Diagram Views

### Network Topology
Shows devices as nodes with connections as edges. Useful for understanding overall connectivity.

### Rack-to-Rack
Displays connections between specific racks. Helpful for cable run planning.

### Device-Centric
All connections to/from a selected device. Great for troubleshooting.

## Interacting with Diagrams

- **Zoom**: Scroll or use controls
- **Pan**: Click and drag the background
- **Select**: Click a device to highlight its connections
- **Details**: Double-click for device or connection details

## Export Options

- **PNG**: Image for presentations
- **PDF**: High-quality print format
- **SVG**: Scalable vector for editing

## Layout Options

Choose layout algorithms:
- **Hierarchical**: Top-down or left-right
- **Force-directed**: Organic grouping
- **Grid**: Aligned positions
MARKDOWN,
            ]
        );
    }

    /**
     * Create Device help articles.
     */
    private function createDeviceArticles(): void
    {
        HelpArticle::updateOrCreate(
            ['slug' => 'managing-devices'],
            [
                'title' => 'Managing Devices',
                'context_key' => 'devices.index',
                'article_type' => HelpArticleType::Article,
                'category' => 'Assets',
                'sort_order' => 1,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Managing Devices

Devices are the core assets tracked in RackAudit.

## Device Information

Each device record includes:
- **Name/Hostname**: Primary identifier
- **Asset Tag**: Physical tracking number
- **Device Type**: Category (server, switch, etc.)
- **Location**: Datacenter, rack, U-position
- **Status**: Active, planned, decommissioned

## Filtering and Search

Find devices by:
- Name or asset tag
- Device type
- Location (datacenter, room, rack)
- Status

## Device Details

View comprehensive information:
- Specifications (CPU, RAM, storage)
- Port configuration
- Connection inventory
- Audit history
- Activity log

## Quick Actions

From the device list:
- **View**: Open device details
- **Edit**: Modify device information
- **Move**: Relocate to another rack
- **Connections**: View/manage ports
MARKDOWN,
            ]
        );

        HelpArticle::updateOrCreate(
            ['slug' => 'creating-devices'],
            [
                'title' => 'Creating Devices',
                'context_key' => 'devices.create',
                'article_type' => HelpArticleType::Article,
                'category' => 'Assets',
                'sort_order' => 2,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Creating Devices

Add new equipment to your inventory.

## Required Information

- **Name**: Unique identifier (usually hostname)
- **Device Type**: Select from defined types
- **Status**: Current state (active, planned, etc.)

## Location (Optional)

Assign physical location:
- **Datacenter**: Which facility
- **Rack**: Which rack
- **U-Position**: Starting position
- **Facing**: Front or rear

Leave location blank for devices not yet installed.

## Port Configuration

For devices with network/power ports:
1. Device type may pre-define ports
2. Add custom ports as needed
3. Specify port type and label

## Bulk Import

For multiple devices:
1. Download the template
2. Fill in device information
3. Upload and review
4. Confirm creation

## Device Types

Select an appropriate device type to:
- Auto-populate default ports
- Set expected dimensions (U-height)
- Apply type-specific attributes
MARKDOWN,
            ]
        );
    }

    /**
     * Create Reporting help articles.
     */
    private function createReportingArticles(): void
    {
        HelpArticle::updateOrCreate(
            ['slug' => 'reports-overview'],
            [
                'title' => 'Reports Overview',
                'context_key' => 'reports.index',
                'article_type' => HelpArticleType::Article,
                'category' => 'Reporting',
                'sort_order' => 1,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Reports Overview

RackAudit provides comprehensive reporting for datacenter management and compliance.

## Available Reports

### Asset Reports
- **Inventory Summary**: Complete device listing
- **Asset Utilization**: Equipment usage metrics
- **Lifecycle Status**: Age and warranty tracking

### Capacity Reports
- **Rack Utilization**: Space usage by rack
- **Power Consumption**: Power draw analysis
- **Capacity Planning**: Available resources

### Audit Reports
- **Audit History**: Completed audits and results
- **Finding Summary**: Issues by severity and status
- **Compliance Status**: Audit completion rates

### Connection Reports
- **Connection Inventory**: All documented connections
- **Port Utilization**: Port usage statistics
- **Cable Management**: Cable types and runs

## Generating Reports

1. Select report type
2. Configure parameters (date range, scope)
3. Choose format (PDF, Excel, CSV)
4. Generate and download

## Scheduling Reports

Automate recurring reports:
- Set frequency (daily, weekly, monthly)
- Choose recipients
- Configure delivery method (email, storage)
MARKDOWN,
            ]
        );

        HelpArticle::updateOrCreate(
            ['slug' => 'custom-reports'],
            [
                'title' => 'Custom Reports',
                'context_key' => 'reports.index',
                'article_type' => HelpArticleType::Article,
                'category' => 'Reporting',
                'sort_order' => 2,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Custom Reports

Build reports tailored to your specific needs.

## Report Builder

Create custom reports by:
1. Selecting data source (devices, connections, audits)
2. Choosing columns to include
3. Adding filters
4. Setting grouping and sorting
5. Configuring totals and summaries

## Saving Reports

Save custom report configurations:
- Give the report a name
- Set visibility (personal or shared)
- Add to favorites for quick access

## Sharing Reports

Share with team members:
- Copy shareable link
- Add to team report library
- Schedule for automatic distribution

## Export Formats

- **PDF**: Formatted for printing
- **Excel**: Full data with formatting
- **CSV**: Raw data for analysis
- **JSON**: API integration
MARKDOWN,
            ]
        );

        HelpArticle::updateOrCreate(
            ['slug' => 'scheduling-reports'],
            [
                'title' => 'Report Scheduling',
                'context_key' => 'reports.index',
                'article_type' => HelpArticleType::Article,
                'category' => 'Reporting',
                'sort_order' => 3,
                'is_active' => true,
                'content' => <<<'MARKDOWN'
# Report Scheduling

Automate report generation and delivery.

## Creating a Schedule

1. Configure the report (type, parameters)
2. Click **Schedule**
3. Set frequency:
   - **Daily**: Specify time
   - **Weekly**: Choose day and time
   - **Monthly**: Select date and time
4. Add recipients (distribution lists or individuals)
5. Activate the schedule

## Managing Schedules

View all scheduled reports:
- Enable/disable without deleting
- Modify parameters or frequency
- View delivery history
- Trigger immediate run

## Distribution Lists

Create lists for recurring recipients:
1. Go to **Distribution Lists**
2. Create a new list
3. Add email addresses
4. Use in report schedules

## Delivery Options

Reports can be delivered via:
- **Email**: As attachment or inline
- **Storage**: Saved to designated location
- **Both**: Email notification with storage link
MARKDOWN,
            ]
        );
    }
}
