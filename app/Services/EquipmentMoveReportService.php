<?php

namespace App\Services;

use App\Models\EquipmentMove;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Service for generating equipment move work order PDFs.
 *
 * Generates printable work orders containing device details,
 * source and destination locations, connection information,
 * and signature fields for datacenter floor operations.
 */
class EquipmentMoveReportService
{
    /**
     * Generate a work order PDF for an equipment move.
     *
     * Creates a detailed PDF document suitable for printing that contains
     * all information needed by operators to execute the move on the
     * datacenter floor, including connection disconnection checklist.
     */
    public function generateWorkOrder(EquipmentMove $move, User $generator): string
    {
        // Load all necessary relationships
        $move->load([
            'device.deviceType',
            'sourceRack.row.room.datacenter',
            'destinationRack.row.room.datacenter',
            'requester',
            'approver',
        ]);

        // Prepare device data
        $deviceData = $this->prepareDeviceData($move);

        // Prepare location data
        $sourceLocation = $this->prepareLocationData($move, 'source');
        $destinationLocation = $this->prepareLocationData($move, 'destination');

        // Prepare connections data
        $connections = $this->prepareConnectionsData($move);

        $pdf = Pdf::loadView('pdf.move-work-order', [
            'move' => $move,
            'device' => $deviceData,
            'sourceLocation' => $sourceLocation,
            'destinationLocation' => $destinationLocation,
            'connections' => $connections,
            'generatedBy' => $generator->name,
            'generatedAt' => now(),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $this->storeReport($pdf, $move);
    }

    /**
     * Prepare device data for the PDF template.
     *
     * @return array<string, mixed>
     */
    protected function prepareDeviceData(EquipmentMove $move): array
    {
        $device = $move->device;

        return [
            'name' => $device?->name ?? 'Unknown Device',
            'asset_tag' => $device?->asset_tag ?? 'N/A',
            'serial_number' => $device?->serial_number ?? 'N/A',
            'manufacturer' => $device?->manufacturer ?? 'N/A',
            'model' => $device?->model ?? 'N/A',
            'device_type' => $device?->deviceType?->name ?? 'Unknown Type',
            'u_height' => $device?->u_height ?? 1,
        ];
    }

    /**
     * Prepare location data for source or destination.
     *
     * @return array<string, mixed>
     */
    protected function prepareLocationData(EquipmentMove $move, string $type): array
    {
        $rack = $type === 'source' ? $move->sourceRack : $move->destinationRack;
        $startU = $type === 'source' ? $move->source_start_u : $move->destination_start_u;
        $rackFace = $type === 'source' ? $move->source_rack_face : $move->destination_rack_face;
        $widthType = $type === 'source' ? $move->source_width_type : $move->destination_width_type;

        $datacenterName = $rack?->row?->room?->datacenter?->name ?? 'Unknown Datacenter';
        $roomName = $rack?->row?->room?->name ?? 'Unknown Room';
        $rowName = $rack?->row?->name ?? 'Unknown Row';
        $rackName = $rack?->name ?? 'Unknown Rack';

        return [
            'datacenter' => $datacenterName,
            'room' => $roomName,
            'row' => $rowName,
            'rack' => $rackName,
            'location_path' => "{$datacenterName} > {$roomName} > {$rowName} > {$rackName}",
            'start_u' => $startU,
            'end_u' => $startU + ($move->device?->u_height ?? 1) - 1,
            'rack_face' => $rackFace?->label() ?? 'Front',
            'width_type' => $widthType?->label() ?? 'Full Width',
        ];
    }

    /**
     * Prepare connections data from the snapshot.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function prepareConnectionsData(EquipmentMove $move): array
    {
        $snapshot = $move->connections_snapshot ?? [];

        return collect($snapshot)->map(function ($connection) {
            return [
                'source_port' => $connection['source_port_label'] ?? 'N/A',
                'destination_port' => $connection['destination_port_label'] ?? 'N/A',
                'destination_device' => $connection['destination_device_name'] ?? 'Unknown',
                'cable_type' => $connection['cable_type'] ?? 'Unknown',
                'cable_length' => $connection['cable_length'] ?? 'N/A',
                'cable_color' => $connection['cable_color'] ?? 'Unknown',
            ];
        })->all();
    }

    /**
     * Store the generated PDF report to the filesystem.
     */
    protected function storeReport(\Barryvdh\DomPDF\PDF $pdf, EquipmentMove $move): string
    {
        $timestamp = now()->format('YmdHis');
        $filename = "move-work-order-{$move->id}-{$timestamp}.pdf";
        $filePath = "reports/equipment-moves/{$filename}";

        Storage::disk('local')->put($filePath, $pdf->output());

        return $filePath;
    }

    /**
     * Stream the PDF directly without storing.
     *
     * Used for direct download without file storage.
     */
    public function streamWorkOrder(EquipmentMove $move, User $generator): \Barryvdh\DomPDF\PDF
    {
        // Load all necessary relationships
        $move->load([
            'device.deviceType',
            'sourceRack.row.room.datacenter',
            'destinationRack.row.room.datacenter',
            'requester',
            'approver',
        ]);

        // Prepare device data
        $deviceData = $this->prepareDeviceData($move);

        // Prepare location data
        $sourceLocation = $this->prepareLocationData($move, 'source');
        $destinationLocation = $this->prepareLocationData($move, 'destination');

        // Prepare connections data
        $connections = $this->prepareConnectionsData($move);

        $pdf = Pdf::loadView('pdf.move-work-order', [
            'move' => $move,
            'device' => $deviceData,
            'sourceLocation' => $sourceLocation,
            'destinationLocation' => $destinationLocation,
            'connections' => $connections,
            'generatedBy' => $generator->name,
            'generatedAt' => now(),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }
}
