<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Equipment Move Work Order #{{ $move->id }}</title>
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
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 20pt;
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

        .work-order-number {
            font-size: 14pt;
            font-weight: bold;
            color: #1e40af;
            background-color: #dbeafe;
            padding: 8px 15px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 10px;
        }

        /* Section Headers */
        .section-header {
            background-color: #f3f4f6;
            border-left: 4px solid #3b82f6;
            padding: 10px 15px;
            margin: 20px 0 15px 0;
            font-size: 12pt;
            font-weight: bold;
            color: #1f2937;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-approved {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-executed {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-cancelled {
            background-color: #e5e7eb;
            color: #374151;
        }

        /* Device Info Card */
        .device-card {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .device-card .title {
            font-size: 14pt;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 10px;
        }

        .device-info-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .device-info-grid td {
            padding: 5px 10px;
            vertical-align: top;
        }

        .device-info-grid .label {
            font-size: 9pt;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            width: 120px;
        }

        .device-info-grid .value {
            font-weight: 500;
            color: #1f2937;
        }

        /* Location Comparison */
        .location-comparison {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        .location-comparison td {
            padding: 10px;
            vertical-align: top;
            width: 45%;
        }

        .location-comparison .arrow-cell {
            width: 10%;
            text-align: center;
            vertical-align: middle;
        }

        .location-box {
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            padding: 15px;
        }

        .location-box.source {
            background-color: #fafafa;
        }

        .location-box.destination {
            background-color: #eff6ff;
            border-color: #3b82f6;
        }

        .location-box .box-title {
            font-size: 10pt;
            font-weight: bold;
            color: #6b7280;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .location-box .rack-name {
            font-size: 14pt;
            font-weight: bold;
            color: #1f2937;
        }

        .location-box .location-path {
            font-size: 9pt;
            color: #6b7280;
            margin-top: 5px;
        }

        .location-box .position-details {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
        }

        .position-tag {
            display: inline-block;
            background-color: #e5e7eb;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 9pt;
            margin-right: 5px;
            margin-top: 5px;
        }

        .arrow {
            font-size: 24pt;
            color: #3b82f6;
        }

        /* Connections Table */
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

        .data-table .text-center {
            text-align: center;
        }

        .data-table .checkbox-cell {
            width: 30px;
            text-align: center;
        }

        .checkbox {
            width: 14px;
            height: 14px;
            border: 2px solid #9ca3af;
            display: inline-block;
        }

        /* Request Info */
        .request-info {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        .request-info td {
            padding: 8px 15px;
            vertical-align: top;
        }

        .request-info .label {
            font-size: 9pt;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            width: 150px;
        }

        .request-info .value {
            font-weight: 500;
        }

        /* Notes Section */
        .notes-box {
            background-color: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }

        .notes-box .label {
            font-weight: bold;
            color: #92400e;
            margin-bottom: 5px;
        }

        .notes-box .content {
            color: #78350f;
            white-space: pre-wrap;
        }

        /* Signature Section */
        .signature-section {
            margin-top: 30px;
            border-top: 2px solid #e5e7eb;
            padding-top: 20px;
        }

        .signature-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .signature-grid td {
            padding: 15px;
            width: 50%;
            vertical-align: top;
        }

        .signature-box {
            border: 1px dashed #9ca3af;
            padding: 15px;
            min-height: 80px;
        }

        .signature-box .label {
            font-size: 9pt;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 40px;
        }

        .signature-line {
            border-bottom: 1px solid #1f2937;
            width: 100%;
            margin-bottom: 5px;
        }

        .signature-sublabel {
            font-size: 8pt;
            color: #9ca3af;
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
            padding: 20px;
            color: #6b7280;
            font-style: italic;
        }

        /* Checklist Header */
        .checklist-header {
            background-color: #dbeafe;
            border: 1px solid #93c5fd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .checklist-header .title {
            font-size: 11pt;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 5px;
        }

        .checklist-header .instruction {
            font-size: 9pt;
            color: #3b82f6;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Equipment Move Work Order</h1>
        <div class="subtitle">Device Relocation Documentation</div>
        <div class="work-order-number">Work Order #{{ $move->id }}</div>
        <div class="generated-info">
            Generated on {{ $generatedAt->format('F d, Y \a\t g:i A') }}
            @if($generatedBy)
            by {{ $generatedBy }}
            @endif
        </div>
    </div>

    <!-- Status and Request Info -->
    <table class="request-info">
        <tr>
            <td class="label">Status</td>
            <td class="value">
                <span class="status-badge status-{{ $move->status }}">
                    {{ ucfirst(str_replace('_', ' ', $move->status)) }}
                </span>
            </td>
            <td class="label">Requested By</td>
            <td class="value">{{ $move->requester?->name ?? 'Unknown' }}</td>
        </tr>
        <tr>
            <td class="label">Requested At</td>
            <td class="value">{{ $move->requested_at?->format('M d, Y g:i A') ?? 'N/A' }}</td>
            <td class="label">Approved By</td>
            <td class="value">{{ $move->approver?->name ?? 'Pending' }}</td>
        </tr>
        @if($move->approved_at)
        <tr>
            <td class="label">Approved At</td>
            <td class="value">{{ $move->approved_at->format('M d, Y g:i A') }}</td>
            @if($move->executed_at)
            <td class="label">Executed At</td>
            <td class="value">{{ $move->executed_at->format('M d, Y g:i A') }}</td>
            @else
            <td></td>
            <td></td>
            @endif
        </tr>
        @endif
    </table>

    <!-- Device Information -->
    <div class="section-header">Device Information</div>

    <div class="device-card">
        <div class="title">{{ $device['name'] }}</div>
        <table class="device-info-grid">
            <tr>
                <td class="label">Asset Tag</td>
                <td class="value">{{ $device['asset_tag'] }}</td>
                <td class="label">Serial Number</td>
                <td class="value">{{ $device['serial_number'] }}</td>
            </tr>
            <tr>
                <td class="label">Manufacturer</td>
                <td class="value">{{ $device['manufacturer'] }}</td>
                <td class="label">Model</td>
                <td class="value">{{ $device['model'] }}</td>
            </tr>
            <tr>
                <td class="label">Device Type</td>
                <td class="value">{{ $device['device_type'] }}</td>
                <td class="label">U Height</td>
                <td class="value">{{ $device['u_height'] }}U</td>
            </tr>
        </table>
    </div>

    <!-- Move Details (Source and Destination) -->
    <div class="section-header">Move Details</div>

    <table class="location-comparison">
        <tr>
            <td>
                <div class="location-box source">
                    <div class="box-title">Current Location (Source)</div>
                    <div class="rack-name">{{ $sourceLocation['rack'] }}</div>
                    <div class="location-path">{{ $sourceLocation['location_path'] }}</div>
                    <div class="position-details">
                        <span class="position-tag">U{{ $sourceLocation['start_u'] }}
                            @if($sourceLocation['end_u'] !== $sourceLocation['start_u'])
                                - U{{ $sourceLocation['end_u'] }}
                            @endif
                        </span>
                        <span class="position-tag">{{ $sourceLocation['rack_face'] }}</span>
                        <span class="position-tag">{{ $sourceLocation['width_type'] }}</span>
                    </div>
                </div>
            </td>
            <td class="arrow-cell">
                <span class="arrow">&rarr;</span>
            </td>
            <td>
                <div class="location-box destination">
                    <div class="box-title">Destination</div>
                    <div class="rack-name">{{ $destinationLocation['rack'] }}</div>
                    <div class="location-path">{{ $destinationLocation['location_path'] }}</div>
                    <div class="position-details">
                        <span class="position-tag">U{{ $destinationLocation['start_u'] }}
                            @if($destinationLocation['end_u'] !== $destinationLocation['start_u'])
                                - U{{ $destinationLocation['end_u'] }}
                            @endif
                        </span>
                        <span class="position-tag">{{ $destinationLocation['rack_face'] }}</span>
                        <span class="position-tag">{{ $destinationLocation['width_type'] }}</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Connections to Disconnect -->
    <div class="section-header">Connections to Disconnect ({{ count($connections) }})</div>

    @if(count($connections) > 0)
    <div class="checklist-header">
        <div class="title">Connection Disconnection Checklist</div>
        <div class="instruction">Check each box as you disconnect the cables. Ensure all connections are properly labeled before removal.</div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th class="checkbox-cell"><span class="checkbox"></span></th>
                <th>Source Port</th>
                <th>Destination Port</th>
                <th>Connected Device</th>
                <th>Cable Type</th>
                <th>Length</th>
                <th>Color</th>
            </tr>
        </thead>
        <tbody>
            @foreach($connections as $connection)
            <tr>
                <td class="checkbox-cell"><span class="checkbox"></span></td>
                <td>{{ $connection['source_port'] }}</td>
                <td>{{ $connection['destination_port'] }}</td>
                <td>{{ $connection['destination_device'] }}</td>
                <td>{{ $connection['cable_type'] }}</td>
                <td class="text-center">{{ is_numeric($connection['cable_length']) ? number_format($connection['cable_length'], 1) . 'm' : $connection['cable_length'] }}</td>
                <td>{{ $connection['cable_color'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="no-data">
        No active connections to disconnect for this device.
    </div>
    @endif

    <!-- Operator Notes -->
    @if($move->operator_notes)
    <div class="section-header">Operator Notes</div>
    <div class="notes-box">
        <div class="label">Instructions / Comments</div>
        <div class="content">{{ $move->operator_notes }}</div>
    </div>
    @endif

    <!-- Approval Notes -->
    @if($move->approval_notes)
    <div class="section-header">Approval Notes</div>
    <div class="notes-box">
        <div class="label">Approver Comments</div>
        <div class="content">{{ $move->approval_notes }}</div>
    </div>
    @endif

    <!-- Signature Section -->
    <div class="signature-section">
        <div class="section-header">Execution Sign-Off</div>
        <table class="signature-grid">
            <tr>
                <td>
                    <div class="signature-box">
                        <div class="label">Technician / Operator</div>
                        <div class="signature-line"></div>
                        <div class="signature-sublabel">Signature</div>
                        <br>
                        <div class="signature-line"></div>
                        <div class="signature-sublabel">Print Name</div>
                        <br>
                        <div class="signature-line"></div>
                        <div class="signature-sublabel">Date / Time</div>
                    </div>
                </td>
                <td>
                    <div class="signature-box">
                        <div class="label">Verified By (Optional)</div>
                        <div class="signature-line"></div>
                        <div class="signature-sublabel">Signature</div>
                        <br>
                        <div class="signature-line"></div>
                        <div class="signature-sublabel">Print Name</div>
                        <br>
                        <div class="signature-line"></div>
                        <div class="signature-sublabel">Date / Time</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        RackAudit - Equipment Move Work Order #{{ $move->id }} &bull; Generated {{ $generatedAt->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>
