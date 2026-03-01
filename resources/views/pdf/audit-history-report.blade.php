<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Audit History Report</title>
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

        .summary-card .sub-value {
            font-size: 10pt;
            color: #6b7280;
            margin-top: 5px;
        }

        /* Filter Scope */
        .filter-scope {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 4px;
            padding: 10px 15px;
            margin-bottom: 15px;
        }

        .filter-scope .label {
            font-weight: bold;
            color: #1e40af;
            font-size: 9pt;
        }

        .filter-scope .value {
            color: #3b82f6;
            font-size: 10pt;
        }

        /* Severity Badges */
        .severity-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
            margin-right: 4px;
        }

        .severity-badge.critical {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .severity-badge.high {
            background-color: #ffedd5;
            color: #9a3412;
        }

        .severity-badge.medium {
            background-color: #fef3c7;
            color: #92400e;
        }

        .severity-badge.low {
            background-color: #dbeafe;
            color: #1e40af;
        }

        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 9pt;
        }

        .data-table th,
        .data-table td {
            padding: 8px 10px;
            text-align: left;
            border: 1px solid #e5e7eb;
        }

        .data-table th {
            background-color: #f3f4f6;
            font-weight: bold;
            color: #374151;
        }

        .data-table tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .data-table .text-right {
            text-align: right;
        }

        .data-table .text-center {
            text-align: center;
        }

        /* Type Badge */
        .type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
            background-color: #e5e7eb;
            color: #374151;
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

        /* No Data Message */
        .no-data {
            text-align: center;
            padding: 30px;
            color: #6b7280;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Audit History Report</h1>
        <div class="subtitle">Historical Audit Trends and Finding Analysis</div>
        <div class="generated-info">
            Generated on {{ $generatedAt->format('F d, Y \a\t g:i A') }}
            @if($generatedBy)
            by {{ $generatedBy }}
            @endif
        </div>
    </div>

    <!-- Filter Scope -->
    <div class="filter-scope">
        <span class="label">Report Scope:</span>
        <span class="value">{{ $filterScope }}</span>
    </div>

    <!-- Executive Summary -->
    <div class="section-header">Executive Summary</div>

    <div class="executive-summary">
        <table class="summary-grid">
            <tr>
                <td>
                    <div class="summary-card">
                        <div class="label">Total Audits Completed</div>
                        <div class="value">{{ number_format($metrics['totalAudits']) }}</div>
                        <div class="sub-value">
                            Audits in selected period
                        </div>
                    </div>
                </td>
                <td>
                    <div class="summary-card">
                        <div class="label">Total Findings</div>
                        <div class="value">{{ number_format($metrics['totalFindings']) }}</div>
                        <div class="sub-value">
                            <span class="severity-badge critical">{{ $metrics['bySeverity']['critical'] ?? 0 }} Critical</span>
                            <span class="severity-badge high">{{ $metrics['bySeverity']['high'] ?? 0 }} High</span>
                            <span class="severity-badge medium">{{ $metrics['bySeverity']['medium'] ?? 0 }} Med</span>
                            <span class="severity-badge low">{{ $metrics['bySeverity']['low'] ?? 0 }} Low</span>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="summary-card">
                        <div class="label">Avg Resolution Time</div>
                        <div class="value">{{ $metrics['avgResolutionTime'] }}</div>
                        <div class="sub-value">
                            Mean time from finding creation to resolution
                        </div>
                    </div>
                </td>
                <td>
                    <div class="summary-card">
                        <div class="label">Avg Time to First Response</div>
                        <div class="value">{{ $metrics['avgFirstResponse'] }}</div>
                        <div class="sub-value">
                            Mean time from Open to In Progress
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Audit History Table -->
    <div class="section-header">Completed Audits</div>

    @if($audits->isNotEmpty())
    <table class="data-table">
        <thead>
            <tr>
                <th>Audit Name</th>
                <th class="text-center">Type</th>
                <th>Datacenter</th>
                <th class="text-center">Completed</th>
                <th class="text-center">Findings</th>
                <th>Severity Breakdown</th>
                <th class="text-right">Avg Resolution</th>
            </tr>
        </thead>
        <tbody>
            @foreach($audits as $audit)
            <tr>
                <td>{{ $audit['name'] }}</td>
                <td class="text-center">
                    <span class="type-badge">{{ $audit['type'] }}</span>
                </td>
                <td>{{ $audit['datacenter'] }}</td>
                <td class="text-center">{{ $audit['completion_date'] }}</td>
                <td class="text-center">{{ $audit['total_findings'] }}</td>
                <td>
                    @if($audit['severity_counts']['critical'] > 0)
                    <span class="severity-badge critical">{{ $audit['severity_counts']['critical'] }}</span>
                    @endif
                    @if($audit['severity_counts']['high'] > 0)
                    <span class="severity-badge high">{{ $audit['severity_counts']['high'] }}</span>
                    @endif
                    @if($audit['severity_counts']['medium'] > 0)
                    <span class="severity-badge medium">{{ $audit['severity_counts']['medium'] }}</span>
                    @endif
                    @if($audit['severity_counts']['low'] > 0)
                    <span class="severity-badge low">{{ $audit['severity_counts']['low'] }}</span>
                    @endif
                    @if($audit['total_findings'] == 0)
                    <span style="color: #9ca3af; font-style: italic;">None</span>
                    @endif
                </td>
                <td class="text-right">{{ $audit['avg_resolution_time'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="no-data">
        No completed audits found for the selected scope.
    </div>
    @endif

    <div class="footer">
        RackAudit - Audit History Report &bull; Generated {{ $generatedAt->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>
