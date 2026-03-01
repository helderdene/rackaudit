<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Audit Report - {{ $audit->name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #1f2937;
        }

        .page-break {
            page-break-after: always;
        }

        /* Header */
        .header {
            border-bottom: 3px solid #3b82f6;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }

        .header h1 {
            font-size: 22pt;
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 5px;
        }

        .header .subtitle {
            font-size: 11pt;
            color: #6b7280;
        }

        .header .generated-info {
            font-size: 9pt;
            color: #9ca3af;
            margin-top: 8px;
        }

        /* Section Headers */
        .section-header {
            background-color: #f3f4f6;
            border-left: 4px solid #3b82f6;
            padding: 10px 15px;
            margin: 20px 0 15px 0;
            font-size: 13pt;
            font-weight: bold;
            color: #1f2937;
        }

        /* Executive Summary */
        .executive-summary {
            margin-bottom: 25px;
        }

        .summary-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .summary-grid td {
            padding: 12px 15px;
            vertical-align: top;
            width: 50%;
        }

        .summary-card {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 15px;
        }

        .summary-card .label {
            font-size: 9pt;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .summary-card .value {
            font-size: 18pt;
            font-weight: bold;
            color: #1f2937;
        }

        .summary-card.critical .value {
            color: #dc2626;
        }

        .summary-card.success .value {
            color: #059669;
        }

        /* Audit Details */
        .audit-details {
            margin: 15px 0;
        }

        .audit-details table {
            width: 100%;
            border-collapse: collapse;
        }

        .audit-details td {
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .audit-details .label {
            font-weight: bold;
            color: #374151;
            width: 150px;
        }

        .audit-details .value {
            color: #4b5563;
        }

        /* Connection Comparison */
        .connection-summary {
            margin: 15px 0;
        }

        .connection-summary table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .connection-summary th,
        .connection-summary td {
            padding: 10px 15px;
            text-align: left;
            border: 1px solid #e5e7eb;
        }

        .connection-summary th {
            background-color: #f3f4f6;
            font-weight: bold;
            color: #374151;
        }

        .connection-summary .matched {
            color: #059669;
        }

        .connection-summary .missing {
            color: #dc2626;
        }

        .connection-summary .unexpected {
            color: #d97706;
        }

        /* Severity Sections */
        .severity-section {
            margin-bottom: 25px;
        }

        .severity-header {
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-weight: bold;
            font-size: 12pt;
        }

        .severity-header.critical {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        .severity-header.high {
            background-color: #ffedd5;
            color: #9a3412;
            border-left: 4px solid #ea580c;
        }

        .severity-header.medium {
            background-color: #fef9c3;
            color: #854d0e;
            border-left: 4px solid #ca8a04;
        }

        .severity-header.low {
            background-color: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        /* Finding Cards */
        .finding {
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            margin-bottom: 15px;
            page-break-inside: avoid;
        }

        .finding-header {
            background-color: #f9fafb;
            padding: 12px 15px;
            border-bottom: 1px solid #e5e7eb;
        }

        .finding-title {
            font-weight: bold;
            font-size: 11pt;
            color: #1f2937;
            margin-bottom: 5px;
        }

        .finding-meta {
            font-size: 9pt;
            color: #6b7280;
        }

        .finding-body {
            padding: 12px 15px;
        }

        .finding-description {
            color: #4b5563;
            margin-bottom: 10px;
        }

        .finding-details {
            width: 100%;
            border-collapse: collapse;
        }

        .finding-details td {
            padding: 5px 0;
            font-size: 9pt;
        }

        .finding-details .label {
            font-weight: bold;
            color: #374151;
            width: 120px;
        }

        .finding-details .value {
            color: #4b5563;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-badge.open {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-badge.in_progress {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-badge.pending_review {
            background-color: #fef9c3;
            color: #854d0e;
        }

        .status-badge.deferred {
            background-color: #f3f4f6;
            color: #4b5563;
        }

        .status-badge.resolved {
            background-color: #dcfce7;
            color: #166534;
        }

        /* Resolution Notes */
        .resolution-notes {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 4px;
            padding: 10px 12px;
            margin-top: 10px;
        }

        .resolution-notes .label {
            font-weight: bold;
            color: #166534;
            font-size: 9pt;
            margin-bottom: 5px;
        }

        .resolution-notes .content {
            color: #15803d;
            font-size: 9pt;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #9ca3af;
            padding: 10px;
            border-top: 1px solid #e5e7eb;
        }

        /* No Findings Message */
        .no-findings {
            text-align: center;
            padding: 30px;
            color: #6b7280;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Audit Report</h1>
        <div class="subtitle">{{ $audit->name }}</div>
        <div class="generated-info">Generated on {{ now()->format('F d, Y \a\t g:i A') }}</div>
    </div>

    <!-- Executive Summary -->
    <div class="section-header">Executive Summary</div>

    <div class="executive-summary">
        <div class="audit-details">
            <table>
                <tr>
                    <td class="label">Audit Name</td>
                    <td class="value">{{ $audit->name }}</td>
                </tr>
                <tr>
                    <td class="label">Datacenter</td>
                    <td class="value">{{ $audit->datacenter?->name ?? 'N/A' }}</td>
                </tr>
                @if($audit->room)
                <tr>
                    <td class="label">Room</td>
                    <td class="value">{{ $audit->room->name }}</td>
                </tr>
                @endif
                <tr>
                    <td class="label">Audit Type</td>
                    <td class="value">{{ $audit->type->label() }}</td>
                </tr>
                <tr>
                    <td class="label">Date Range</td>
                    <td class="value">{{ $executiveSummary['date_range']['start'] }} - {{ $executiveSummary['date_range']['end'] }}</td>
                </tr>
                <tr>
                    <td class="label">Status</td>
                    <td class="value">{{ $audit->status->label() }}</td>
                </tr>
            </table>
        </div>

        <table class="summary-grid">
            <tr>
                <td>
                    <div class="summary-card">
                        <div class="label">Total Findings</div>
                        <div class="value">{{ $executiveSummary['total_findings'] }}</div>
                    </div>
                </td>
                <td>
                    <div class="summary-card success">
                        <div class="label">Resolution Rate</div>
                        <div class="value">{{ number_format($executiveSummary['resolution_rate'], 1) }}%</div>
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="summary-card critical">
                        <div class="label">Critical Issues</div>
                        <div class="value">{{ $executiveSummary['critical_count'] }}</div>
                    </div>
                </td>
                <td>
                    @if($connectionComparison)
                    <div class="summary-card">
                        <div class="label">Connections Verified</div>
                        <div class="value">{{ $connectionComparison['total_count'] }}</div>
                    </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <!-- Connection Comparison Summary (only for connection audits) -->
    @if($connectionComparison)
    <div class="section-header">Connection Comparison Summary</div>

    <div class="connection-summary">
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="matched">Matched Connections</td>
                    <td>{{ $connectionComparison['matched_count'] }}</td>
                    <td>{{ $connectionComparison['total_count'] > 0 ? number_format(($connectionComparison['matched_count'] / $connectionComparison['total_count']) * 100, 1) : 0 }}%</td>
                </tr>
                <tr>
                    <td class="missing">Missing Connections</td>
                    <td>{{ $connectionComparison['missing_count'] }}</td>
                    <td>{{ $connectionComparison['total_count'] > 0 ? number_format(($connectionComparison['missing_count'] / $connectionComparison['total_count']) * 100, 1) : 0 }}%</td>
                </tr>
                <tr>
                    <td class="unexpected">Unexpected Connections</td>
                    <td>{{ $connectionComparison['unexpected_count'] }}</td>
                    <td>{{ $connectionComparison['total_count'] > 0 ? number_format(($connectionComparison['unexpected_count'] / $connectionComparison['total_count']) * 100, 1) : 0 }}%</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    <!-- Findings by Severity -->
    @if(count($groupedFindings) > 0)
    <div class="section-header">Findings by Severity</div>

    @foreach($groupedFindings as $severity => $findings)
    <div class="severity-section">
        <div class="severity-header {{ $severity }}">
            {{ ucfirst($severity) }} ({{ $findings->count() }})
        </div>

        @foreach($findings as $finding)
        <div class="finding">
            <div class="finding-header">
                <div class="finding-title">{{ $finding->title }}</div>
                <div class="finding-meta">
                    <span class="status-badge {{ $finding->status->value }}">{{ $finding->status->label() }}</span>
                    @if($finding->assignee)
                        &nbsp;&bull;&nbsp; Assigned to: {{ $finding->assignee->name }}
                    @endif
                </div>
            </div>
            <div class="finding-body">
                @if($finding->description)
                <div class="finding-description">{{ $finding->description }}</div>
                @endif

                <table class="finding-details">
                    @if($finding->discrepancy_type)
                    <tr>
                        <td class="label">Discrepancy Type</td>
                        <td class="value">{{ $finding->discrepancy_type->label() }}</td>
                    </tr>
                    @endif
                    @if($finding->category)
                    <tr>
                        <td class="label">Category</td>
                        <td class="value">{{ $finding->category->name }}</td>
                    </tr>
                    @endif
                    @if($finding->verification && $finding->verification->expectedConnection)
                    <tr>
                        <td class="label">Expected Connection</td>
                        <td class="value">
                            {{ $finding->verification->expectedConnection->source_device_name ?? 'Unknown' }}
                            ({{ $finding->verification->expectedConnection->source_port_label ?? 'N/A' }})
                            &rarr;
                            {{ $finding->verification->expectedConnection->destination_device_name ?? 'Unknown' }}
                            ({{ $finding->verification->expectedConnection->destination_port_label ?? 'N/A' }})
                        </td>
                    </tr>
                    @endif
                    @if($finding->deviceVerification && $finding->deviceVerification->device)
                    <tr>
                        <td class="label">Related Device</td>
                        <td class="value">{{ $finding->deviceVerification->device->name }}</td>
                    </tr>
                    @endif
                    @if($finding->due_date)
                    <tr>
                        <td class="label">Due Date</td>
                        <td class="value">{{ $finding->due_date->format('M d, Y') }}</td>
                    </tr>
                    @endif
                </table>

                @if($finding->status === \App\Enums\FindingStatus::Resolved && $finding->resolution_notes)
                <div class="resolution-notes">
                    <div class="label">Resolution Notes</div>
                    <div class="content">{{ $finding->resolution_notes }}</div>
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endforeach

    @else
    <div class="section-header">Findings</div>
    <div class="no-findings">
        No findings were recorded for this audit.
    </div>
    @endif

    <div class="footer">
        RackAudit - Audit Report &bull; Generated {{ now()->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>
