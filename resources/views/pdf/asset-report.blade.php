<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Asset Report</title>
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

        .summary-card.active .value {
            color: #059669;
        }

        .summary-card.warning .value {
            color: #d97706;
        }

        .summary-card.expired .value {
            color: #dc2626;
        }

        .summary-card.unknown .value {
            color: #6b7280;
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
        <h1>Asset Report</h1>
        <div class="subtitle">Device Inventory and Warranty Analysis</div>
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

    <!-- Warranty Status Summary -->
    <div class="section-header">Warranty Status Summary</div>

    <table class="summary-grid">
        <tr>
            <td>
                <div class="summary-card active">
                    <div class="label">Active</div>
                    <div class="value">{{ $metrics['warrantyStatus']['active'] }}</div>
                </div>
            </td>
            <td>
                <div class="summary-card warning">
                    <div class="label">Expiring Soon</div>
                    <div class="value">{{ $metrics['warrantyStatus']['expiring_soon'] }}</div>
                </div>
            </td>
            <td>
                <div class="summary-card expired">
                    <div class="label">Expired</div>
                    <div class="value">{{ $metrics['warrantyStatus']['expired'] }}</div>
                </div>
            </td>
            <td>
                <div class="summary-card unknown">
                    <div class="label">Unknown</div>
                    <div class="value">{{ $metrics['warrantyStatus']['unknown'] }}</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Lifecycle Distribution -->
    <div class="section-header">Lifecycle Distribution</div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Status</th>
                <th class="text-center">Count</th>
                <th class="text-center">Percentage</th>
            </tr>
        </thead>
        <tbody>
            @foreach($metrics['lifecycleDistribution'] as $status)
            <tr>
                <td>{{ $status['label'] }}</td>
                <td class="text-center">{{ $status['count'] }}</td>
                <td class="text-center">{{ number_format($status['percentage'], 1) }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Counts by Device Type -->
    @if(count($metrics['countsByType']) > 0)
    <div class="section-header">Counts by Device Type</div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Device Type</th>
                <th class="text-center">Count</th>
            </tr>
        </thead>
        <tbody>
            @foreach($metrics['countsByType'] as $type)
            <tr>
                <td>{{ $type['name'] }}</td>
                <td class="text-center">{{ $type['count'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- Counts by Manufacturer -->
    @if(count($metrics['countsByManufacturer']) > 0)
    <div class="section-header">Counts by Manufacturer</div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Manufacturer</th>
                <th class="text-center">Count</th>
            </tr>
        </thead>
        <tbody>
            @foreach($metrics['countsByManufacturer'] as $manufacturer)
            <tr>
                <td>{{ $manufacturer['name'] }}</td>
                <td class="text-center">{{ $manufacturer['count'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- Device Inventory List -->
    @if($devices->count() > 0)
    <div class="page-break"></div>
    <div class="section-header">Device Inventory</div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Asset Tag</th>
                <th>Name</th>
                <th>Manufacturer</th>
                <th>Model</th>
                <th>Type</th>
                <th>Status</th>
                <th>Location</th>
                <th>Warranty End</th>
            </tr>
        </thead>
        <tbody>
            @foreach($devices->take(100) as $device)
            <tr>
                <td>{{ $device['asset_tag'] }}</td>
                <td>{{ $device['name'] }}</td>
                <td>{{ $device['manufacturer'] ?? 'N/A' }}</td>
                <td>{{ $device['model'] ?? 'N/A' }}</td>
                <td>{{ $device['device_type'] }}</td>
                <td>{{ $device['lifecycle_status'] }}</td>
                <td>
                    @if($device['datacenter'])
                        {{ $device['datacenter'] }}
                        @if($device['room']) > {{ $device['room'] }} @endif
                        @if($device['rack']) > {{ $device['rack'] }} @endif
                        @if($device['u_position']) (U{{ $device['u_position'] }}) @endif
                    @else
                        Not Racked
                    @endif
                </td>
                <td>{{ $device['warranty_end_date'] ?? 'Not tracked' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if($devices->count() > 100)
    <div class="no-data">
        Showing first 100 of {{ $devices->count() }} devices. Export to CSV for complete list.
    </div>
    @endif
    @else
    <div class="section-header">Device Inventory</div>
    <div class="no-data">
        No devices found for the selected scope.
    </div>
    @endif

    <div class="footer">
        RackAudit - Asset Report &bull; Generated {{ $generatedAt->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>
