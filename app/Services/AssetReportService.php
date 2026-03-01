<?php

namespace App\Services;

use App\Enums\DeviceLifecycleStatus;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Room;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Service for generating asset PDF reports.
 *
 * Generates comprehensive reports containing device inventory,
 * warranty status breakdown, lifecycle distribution, and
 * counts by type and manufacturer.
 */
class AssetReportService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        protected AssetCalculationService $calculationService
    ) {}

    /**
     * Generate a PDF report for asset management.
     *
     * @param  array{
     *     datacenter_id?: int|null,
     *     room_id?: int|null,
     *     device_type_id?: int|null,
     *     lifecycle_status?: string|null,
     *     manufacturer?: string|null,
     *     warranty_start?: string|null,
     *     warranty_end?: string|null
     * }  $filters
     */
    public function generatePdfReport(array $filters, User $generator): string
    {
        $datacenterId = $filters['datacenter_id'] ?? null;
        $roomId = $filters['room_id'] ?? null;
        $deviceTypeId = $filters['device_type_id'] ?? null;
        $lifecycleStatus = $filters['lifecycle_status'] ?? null;
        $manufacturer = $filters['manufacturer'] ?? null;
        $warrantyStart = $filters['warranty_start'] ?? null;
        $warrantyEnd = $filters['warranty_end'] ?? null;

        // Get asset metrics
        $metrics = $this->calculationService->getAssetMetrics(
            $datacenterId,
            $roomId,
            $deviceTypeId,
            $lifecycleStatus,
            $manufacturer,
            $warrantyStart,
            $warrantyEnd
        );

        // Get device inventory for the table
        $devices = $this->getDeviceInventory(
            $datacenterId,
            $roomId,
            $deviceTypeId,
            $lifecycleStatus,
            $manufacturer,
            $warrantyStart,
            $warrantyEnd
        );

        // Build filter scope description
        $filterScope = $this->buildFilterScope($filters);

        $pdf = Pdf::loadView('pdf.asset-report', [
            'metrics' => $metrics,
            'devices' => $devices,
            'filterScope' => $filterScope,
            'generatedBy' => $generator->name,
            'generatedAt' => now(),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $this->storeReport($pdf);
    }

    /**
     * Get detailed device inventory for the report.
     *
     * @return Collection<int, array{
     *     asset_tag: string,
     *     name: string,
     *     serial_number: string|null,
     *     manufacturer: string|null,
     *     model: string|null,
     *     device_type: string,
     *     lifecycle_status: string,
     *     datacenter: string|null,
     *     room: string|null,
     *     rack: string|null,
     *     u_position: int|null,
     *     warranty_end_date: string|null
     * }>
     */
    public function getDeviceInventory(
        ?int $datacenterId = null,
        ?int $roomId = null,
        ?int $deviceTypeId = null,
        ?string $lifecycleStatus = null,
        ?string $manufacturer = null,
        ?string $warrantyStart = null,
        ?string $warrantyEnd = null
    ): Collection {
        $query = $this->calculationService->buildFilteredDeviceQuery(
            $datacenterId,
            $roomId,
            $deviceTypeId,
            $lifecycleStatus,
            $manufacturer,
            $warrantyStart,
            $warrantyEnd
        );

        return $query
            ->with(['deviceType', 'rack.row.room.datacenter'])
            ->orderBy('asset_tag')
            ->get()
            ->map(function (Device $device) {
                return [
                    'asset_tag' => $device->asset_tag,
                    'name' => $device->name,
                    'serial_number' => $device->serial_number,
                    'manufacturer' => $device->manufacturer,
                    'model' => $device->model,
                    'device_type' => $device->deviceType?->name ?? 'Unknown',
                    'lifecycle_status' => $device->lifecycle_status?->label() ?? 'Unknown',
                    'datacenter' => $device->rack?->row?->room?->datacenter?->name,
                    'room' => $device->rack?->row?->room?->name,
                    'rack' => $device->rack?->name,
                    'u_position' => $device->start_u,
                    'warranty_end_date' => $device->warranty_end_date?->format('Y-m-d'),
                ];
            });
    }

    /**
     * Build a human-readable description of the filter scope.
     *
     * @param  array{
     *     datacenter_id?: int|null,
     *     room_id?: int|null,
     *     device_type_id?: int|null,
     *     lifecycle_status?: string|null,
     *     manufacturer?: string|null,
     *     warranty_start?: string|null,
     *     warranty_end?: string|null
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

        // Device type filter
        if (! empty($filters['device_type_id'])) {
            $deviceType = DeviceType::find($filters['device_type_id']);
            if ($deviceType) {
                $parts[] = "Type: {$deviceType->name}";
            }
        }

        // Lifecycle status filter
        if (! empty($filters['lifecycle_status'])) {
            $status = DeviceLifecycleStatus::tryFrom($filters['lifecycle_status']);
            if ($status) {
                $parts[] = "Status: {$status->label()}";
            }
        }

        // Manufacturer filter
        if (! empty($filters['manufacturer'])) {
            $parts[] = "Manufacturer: {$filters['manufacturer']}";
        }

        // Warranty date range filter
        if (! empty($filters['warranty_start']) || ! empty($filters['warranty_end'])) {
            $warrantyScope = 'Warranty: ';
            if (! empty($filters['warranty_start']) && ! empty($filters['warranty_end'])) {
                $warrantyScope .= "{$filters['warranty_start']} to {$filters['warranty_end']}";
            } elseif (! empty($filters['warranty_start'])) {
                $warrantyScope .= "from {$filters['warranty_start']}";
            } else {
                $warrantyScope .= "until {$filters['warranty_end']}";
            }
            $parts[] = $warrantyScope;
        }

        return ! empty($parts) ? implode(' | ', $parts) : 'All Devices';
    }

    /**
     * Store the generated PDF report to the filesystem.
     */
    protected function storeReport(\Barryvdh\DomPDF\PDF $pdf): string
    {
        $timestamp = now()->format('YmdHis');
        $filename = "asset-report-{$timestamp}.pdf";
        $filePath = "reports/assets/{$filename}";

        Storage::disk('local')->put($filePath, $pdf->output());

        return $filePath;
    }
}
