<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Room;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Service for generating connection PDF reports.
 *
 * Generates comprehensive reports containing connection inventory,
 * cable type distribution, port utilization, and cable length statistics.
 */
class ConnectionReportService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        protected ConnectionCalculationService $calculationService
    ) {}

    /**
     * Generate a PDF report for connection management.
     *
     * @param  array{
     *     datacenter_id?: int|null,
     *     room_id?: int|null
     * }  $filters
     */
    public function generatePdfReport(array $filters, User $generator): string
    {
        $datacenterId = $filters['datacenter_id'] ?? null;
        $roomId = $filters['room_id'] ?? null;

        // Get connection metrics
        $metrics = $this->calculationService->getConnectionMetrics(
            $datacenterId,
            $roomId
        );

        // Get connection inventory for the table
        $connections = $this->getConnectionInventory(
            $datacenterId,
            $roomId
        );

        // Build filter scope description
        $filterScope = $this->buildFilterScope($filters);

        $pdf = Pdf::loadView('pdf.connection-report', [
            'metrics' => $metrics,
            'connections' => $connections,
            'filterScope' => $filterScope,
            'generatedBy' => $generator->name,
            'generatedAt' => now(),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $this->storeReport($pdf);
    }

    /**
     * Get detailed connection inventory for the report.
     *
     * @return Collection<int, array{
     *     source_device: string,
     *     source_port: string,
     *     destination_device: string,
     *     destination_port: string,
     *     cable_type: string,
     *     cable_length: string|null,
     *     cable_color: string|null
     * }>
     */
    public function getConnectionInventory(
        ?int $datacenterId = null,
        ?int $roomId = null
    ): Collection {
        $query = $this->calculationService->buildFilteredConnectionQuery(
            $datacenterId,
            $roomId
        );

        return $query
            ->with(['sourcePort.device', 'destinationPort.device'])
            ->get()
            ->map(function (Connection $connection) {
                return [
                    'source_device' => $connection->sourcePort?->device?->name ?? 'Unknown',
                    'source_port' => $connection->sourcePort?->label ?? 'Unknown',
                    'destination_device' => $connection->destinationPort?->device?->name ?? 'Unknown',
                    'destination_port' => $connection->destinationPort?->label ?? 'Unknown',
                    'cable_type' => $connection->cable_type?->label() ?? 'Unknown',
                    'cable_length' => $connection->cable_length !== null
                        ? number_format((float) $connection->cable_length, 2)
                        : null,
                    'cable_color' => $connection->cable_color,
                ];
            });
    }

    /**
     * Build a human-readable description of the filter scope.
     *
     * @param  array{
     *     datacenter_id?: int|null,
     *     room_id?: int|null
     * }  $filters
     */
    public function buildFilterScope(array $filters): string
    {
        $parts = [];

        // Location filters
        if (! empty($filters['room_id'])) {
            $room = Room::find($filters['room_id']);
            if ($room) {
                $parts[] = "Room: {$room->name}";
                $parts[] = "Datacenter: {$room->datacenter?->name}";
            }
        } elseif (! empty($filters['datacenter_id'])) {
            $datacenter = Datacenter::find($filters['datacenter_id']);
            if ($datacenter) {
                $parts[] = "Datacenter: {$datacenter->name}";
            }
        }

        return ! empty($parts) ? implode(' | ', $parts) : 'All Connections';
    }

    /**
     * Store the generated PDF report to the filesystem.
     */
    protected function storeReport(\Barryvdh\DomPDF\PDF $pdf): string
    {
        $timestamp = now()->format('YmdHis');
        $filename = "connection-report-{$timestamp}.pdf";
        $filePath = "reports/connections/{$filename}";

        Storage::disk('local')->put($filePath, $pdf->output());

        return $filePath;
    }
}
