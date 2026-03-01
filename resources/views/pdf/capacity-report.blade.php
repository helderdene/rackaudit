<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Capacity Planning Report</title>
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

        .summary-card.warning .value {
            color: #d97706;
        }

        .summary-card.critical .value {
            color: #dc2626;
        }

        .summary-card.success .value {
            color: #059669;
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

        /* Status Indicators */
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-badge.normal {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-badge.warning {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-badge.critical {
            background-color: #fee2e2;
            color: #991b1b;
        }

        /* Progress Bar */
        .progress-bar {
            background-color: #e5e7eb;
            border-radius: 4px;
            height: 12px;
            width: 100%;
            overflow: hidden;
        }

        .progress-bar .fill {
            height: 100%;
            border-radius: 4px;
        }

        .progress-bar .fill.normal {
            background-color: #22c55e;
        }

        .progress-bar .fill.warning {
            background-color: #f59e0b;
        }

        .progress-bar .fill.critical {
            background-color: #ef4444;
        }

        /* Port Capacity Grid */
        .port-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .port-grid td {
            padding: 10px;
            vertical-align: top;
            width: 33.33%;
        }

        .port-card {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 12px;
        }

        .port-card .title {
            font-weight: bold;
            color: #374151;
            font-size: 10pt;
            margin-bottom: 8px;
        }

        .port-card .stat {
            font-size: 9pt;
            color: #6b7280;
            margin-bottom: 3px;
        }

        .port-card .stat-value {
            font-weight: bold;
            color: #1f2937;
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

        /* Null Value Display */
        .null-value {
            color: #9ca3af;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Capacity Planning Report</h1>
        <div class="subtitle">Infrastructure Utilization Analysis</div>
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
                    @php
                        $uPercent = $metrics['u_space']['utilization_percent'];
                        $uClass = $uPercent >= 90 ? 'critical' : ($uPercent >= 80 ? 'warning' : 'success');
                    @endphp
                    <div class="summary-card {{ $uClass }}">
                        <div class="label">U-Space Utilization</div>
                        <div class="value">{{ number_format($uPercent, 1) }}%</div>
                        <div class="sub-value">
                            {{ number_format($metrics['u_space']['used_u_space']) }} / {{ number_format($metrics['u_space']['total_u_space']) }} U
                        </div>
                    </div>
                </td>
                <td>
                    <div class="summary-card">
                        <div class="label">Available U-Space</div>
                        <div class="value">{{ number_format($metrics['u_space']['available_u_space']) }} U</div>
                        <div class="sub-value">
                            Capacity for new equipment
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    @if($metrics['power']['utilization_percent'] !== null)
                        @php
                            $powerPercent = $metrics['power']['utilization_percent'];
                            $powerClass = $powerPercent >= 90 ? 'critical' : ($powerPercent >= 80 ? 'warning' : 'success');
                        @endphp
                        <div class="summary-card {{ $powerClass }}">
                            <div class="label">Power Utilization</div>
                            <div class="value">{{ number_format($powerPercent, 1) }}%</div>
                            <div class="sub-value">
                                {{ number_format($metrics['power']['total_consumption']) }} / {{ number_format($metrics['power']['total_capacity']) }} W
                            </div>
                        </div>
                    @else
                        <div class="summary-card">
                            <div class="label">Power Utilization</div>
                            <div class="value null-value">Not Configured</div>
                            <div class="sub-value">
                                No power capacity data available
                            </div>
                        </div>
                    @endif
                </td>
                <td>
                    <div class="summary-card">
                        <div class="label">Power Headroom</div>
                        @if($metrics['power']['total_capacity'] > 0)
                            <div class="value">{{ number_format($metrics['power']['power_headroom']) }} W</div>
                            <div class="sub-value">
                                Available power capacity
                            </div>
                        @else
                            <div class="value null-value">N/A</div>
                            <div class="sub-value">
                                Power capacity not configured
                            </div>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Port Capacity Summary -->
    <div class="section-header">Port Capacity by Type</div>

    <table class="port-grid">
        <tr>
            @foreach($metrics['port_capacity'] as $type => $stats)
            <td>
                <div class="port-card">
                    <div class="title">{{ $stats['label'] }}</div>
                    <div class="stat">Total: <span class="stat-value">{{ number_format($stats['total_ports']) }}</span></div>
                    <div class="stat">Connected: <span class="stat-value">{{ number_format($stats['connected_ports']) }}</span></div>
                    <div class="stat">Available: <span class="stat-value">{{ number_format($stats['available_ports']) }}</span></div>
                </div>
            </td>
            @endforeach
        </tr>
    </table>

    <!-- Racks Approaching Capacity -->
    @if($metrics['racks_approaching_capacity']->isNotEmpty())
    <div class="section-header">Racks Approaching Capacity (80%+)</div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Rack Name</th>
                <th class="text-center">Utilization</th>
                <th class="text-right">Available U</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($metrics['racks_approaching_capacity'] as $rack)
            <tr>
                <td>{{ $rack['name'] }}</td>
                <td class="text-center">{{ number_format($rack['utilization_percent'], 1) }}%</td>
                <td class="text-right">{{ $rack['available_u_space'] }} U</td>
                <td class="text-center">
                    <span class="status-badge {{ $rack['status'] }}">{{ ucfirst($rack['status']) }}</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- Page Break Before Detailed Table -->
    @if($racks->count() > 10)
    <div class="page-break"></div>
    @endif

    <!-- Detailed Rack Utilization Table -->
    <div class="section-header">U-Space Utilization by Rack</div>

    @if($racks->isNotEmpty())
    <table class="data-table">
        <thead>
            <tr>
                <th>Rack</th>
                <th>Location</th>
                <th class="text-center">U Capacity</th>
                <th class="text-center">U Used</th>
                <th class="text-center">U Available</th>
                <th class="text-center">Utilization</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($racks as $rack)
            <tr>
                <td>{{ $rack['name'] }}</td>
                <td>{{ $rack['datacenter'] }} > {{ $rack['room'] }} > {{ $rack['row'] }}</td>
                <td class="text-center">{{ $rack['u_capacity'] }}</td>
                <td class="text-center">{{ $rack['u_used'] }}</td>
                <td class="text-center">{{ $rack['u_available'] }}</td>
                <td class="text-center">{{ number_format($rack['u_utilization'], 1) }}%</td>
                <td class="text-center">
                    <span class="status-badge {{ $rack['status'] }}">{{ ucfirst($rack['status']) }}</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="no-data">
        No racks found for the selected scope.
    </div>
    @endif

    <!-- Power Consumption Table -->
    @if($racks->whereNotNull('power_capacity')->isNotEmpty())
    <div class="section-header">Power Consumption Summary</div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Rack</th>
                <th>Location</th>
                <th class="text-right">Capacity (W)</th>
                <th class="text-right">Used (W)</th>
                <th class="text-right">Available (W)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($racks->whereNotNull('power_capacity') as $rack)
            <tr>
                <td>{{ $rack['name'] }}</td>
                <td>{{ $rack['datacenter'] }} > {{ $rack['room'] }} > {{ $rack['row'] }}</td>
                <td class="text-right">{{ number_format($rack['power_capacity']) }}</td>
                <td class="text-right">{{ number_format($rack['power_used']) }}</td>
                <td class="text-right">{{ $rack['power_available'] !== null ? number_format($rack['power_available']) : '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        RackAudit - Capacity Planning Report &bull; Generated {{ $generatedAt->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>
