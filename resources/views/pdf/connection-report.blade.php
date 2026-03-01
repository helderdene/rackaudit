<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Connection Report</title>
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

        /* Summary Grid */
        .summary-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .summary-grid td {
            padding: 8px;
            vertical-align: top;
            width: 25%;
        }

        .summary-card {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 15px;
            text-align: center;
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

        .summary-card.primary .value {
            color: #3b82f6;
        }

        .summary-card.success .value {
            color: #059669;
        }

        .summary-card.warning .value {
            color: #d97706;
        }

        .summary-card.info .value {
            color: #6366f1;
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

        /* Color Swatch */
        .color-swatch {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 2px;
            border: 1px solid #d1d5db;
            vertical-align: middle;
            margin-right: 5px;
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

        /* Two column layout for distributions */
        .two-column-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .two-column-grid td {
            width: 50%;
            vertical-align: top;
            padding: 0 10px;
        }

        .two-column-grid td:first-child {
            padding-left: 0;
        }

        .two-column-grid td:last-child {
            padding-right: 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Connection Report</h1>
        <div class="subtitle">Connection Inventory and Cable Analysis</div>
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

    <!-- Connection Summary -->
    <div class="section-header">Connection Summary</div>

    <table class="summary-grid">
        <tr>
            <td>
                <div class="summary-card primary">
                    <div class="label">Total Connections</div>
                    <div class="value">{{ $metrics['totalConnections'] }}</div>
                </div>
            </td>
            <td>
                <div class="summary-card success">
                    <div class="label">Total Ports</div>
                    <div class="value">{{ $metrics['portUtilization']['overall']['total'] }}</div>
                </div>
            </td>
            <td>
                <div class="summary-card warning">
                    <div class="label">Connected Ports</div>
                    <div class="value">{{ $metrics['portUtilization']['overall']['connected'] }}</div>
                </div>
            </td>
            <td>
                <div class="summary-card info">
                    <div class="label">Port Utilization</div>
                    <div class="value">{{ number_format($metrics['portUtilization']['overall']['percentage'], 1) }}%</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Cable Length Statistics -->
    @if($metrics['cableLengthStats']['count'] > 0)
    <div class="section-header">Cable Length Statistics</div>

    <table class="summary-grid">
        <tr>
            <td>
                <div class="summary-card">
                    <div class="label">Cables Measured</div>
                    <div class="value">{{ $metrics['cableLengthStats']['count'] }}</div>
                </div>
            </td>
            <td>
                <div class="summary-card">
                    <div class="label">Average Length</div>
                    <div class="value">{{ number_format($metrics['cableLengthStats']['mean'], 2) }}m</div>
                </div>
            </td>
            <td>
                <div class="summary-card">
                    <div class="label">Minimum Length</div>
                    <div class="value">{{ number_format($metrics['cableLengthStats']['min'], 2) }}m</div>
                </div>
            </td>
            <td>
                <div class="summary-card">
                    <div class="label">Maximum Length</div>
                    <div class="value">{{ number_format($metrics['cableLengthStats']['max'], 2) }}m</div>
                </div>
            </td>
        </tr>
    </table>
    @endif

    <!-- Cable Type Distribution and Port Type Distribution side by side -->
    <table class="two-column-grid">
        <tr>
            <td>
                <!-- Cable Type Distribution -->
                <div class="section-header">Cable Type Distribution</div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Cable Type</th>
                            <th class="text-center">Count</th>
                            <th class="text-center">Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($metrics['cableTypeDistribution'] as $type)
                        @if($type['count'] > 0)
                        <tr>
                            <td>{{ $type['label'] }}</td>
                            <td class="text-center">{{ $type['count'] }}</td>
                            <td class="text-center">{{ number_format($type['percentage'], 1) }}%</td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                </table>
            </td>
            <td>
                <!-- Port Type Distribution -->
                <div class="section-header">Connections by Port Type</div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Port Type</th>
                            <th class="text-center">Count</th>
                            <th class="text-center">Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($metrics['portTypeDistribution'] as $type)
                        @if($type['count'] > 0)
                        <tr>
                            <td>{{ $type['label'] }}</td>
                            <td class="text-center">{{ $type['count'] }}</td>
                            <td class="text-center">{{ number_format($type['percentage'], 1) }}%</td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <!-- Port Utilization by Type -->
    <div class="section-header">Port Utilization by Type</div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Port Type</th>
                <th class="text-center">Total Ports</th>
                <th class="text-center">Connected</th>
                <th class="text-center">Utilization</th>
            </tr>
        </thead>
        <tbody>
            @foreach($metrics['portUtilization']['byType'] as $type)
            @if($type['total'] > 0)
            <tr>
                <td>{{ $type['label'] }}</td>
                <td class="text-center">{{ $type['total'] }}</td>
                <td class="text-center">{{ $type['connected'] }}</td>
                <td class="text-center">{{ number_format($type['percentage'], 1) }}%</td>
            </tr>
            @endif
            @endforeach
        </tbody>
    </table>

    <!-- Port Status Breakdown -->
    <div class="section-header">Port Status Breakdown</div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Status</th>
                <th class="text-center">Count</th>
                <th class="text-center">Percentage</th>
            </tr>
        </thead>
        <tbody>
            @foreach($metrics['portUtilization']['byStatus'] as $status)
            <tr>
                <td>{{ $status['label'] }}</td>
                <td class="text-center">{{ $status['count'] }}</td>
                <td class="text-center">{{ number_format($status['percentage'], 1) }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Connection Inventory List -->
    @if($connections->count() > 0)
    <div class="page-break"></div>
    <div class="section-header">Connection Inventory</div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Source Device</th>
                <th>Source Port</th>
                <th>Destination Device</th>
                <th>Destination Port</th>
                <th>Cable Type</th>
                <th class="text-center">Length</th>
                <th>Color</th>
            </tr>
        </thead>
        <tbody>
            @foreach($connections->take(100) as $connection)
            <tr>
                <td>{{ $connection['source_device'] }}</td>
                <td>{{ $connection['source_port'] }}</td>
                <td>{{ $connection['destination_device'] }}</td>
                <td>{{ $connection['destination_port'] }}</td>
                <td>{{ $connection['cable_type'] }}</td>
                <td class="text-center">{{ $connection['cable_length'] ? $connection['cable_length'] . 'm' : 'N/A' }}</td>
                <td>
                    @if($connection['cable_color'])
                        <span class="color-swatch" style="background-color: {{ $connection['cable_color'] }};"></span>
                        {{ ucfirst($connection['cable_color']) }}
                    @else
                        N/A
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if($connections->count() > 100)
    <div class="no-data">
        Showing first 100 of {{ $connections->count() }} connections. Export to CSV for complete list.
    </div>
    @endif
    @else
    <div class="section-header">Connection Inventory</div>
    <div class="no-data">
        No connections found for the selected scope.
    </div>
    @endif

    <div class="footer">
        RackAudit - Connection Report &bull; Generated {{ $generatedAt->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>
