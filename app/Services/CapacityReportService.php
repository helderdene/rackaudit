<?php

namespace App\Services;

use App\Enums\RackStatus;
use App\Models\Datacenter;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Service for generating capacity planning PDF reports.
 *
 * Generates comprehensive reports containing executive summaries,
 * U-space utilization tables, power consumption data, and
 * port capacity analysis.
 */
class CapacityReportService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        protected CapacityCalculationService $calculationService
    ) {}

    /**
     * Generate a PDF report for capacity planning.
     *
     * @param  array{datacenter_id?: int|null, room_id?: int|null, row_id?: int|null}  $filters
     */
    public function generatePdfReport(array $filters, User $generator): string
    {
        $datacenterId = $filters['datacenter_id'] ?? null;
        $roomId = $filters['room_id'] ?? null;
        $rowId = $filters['row_id'] ?? null;

        // Get capacity metrics
        $metrics = $this->calculationService->getCapacityMetrics(
            $datacenterId,
            $roomId,
            $rowId
        );

        // Get rack details for the table
        $racks = $this->getRackDetails($datacenterId, $roomId, $rowId);

        // Build filter scope description
        $filterScope = $this->buildFilterScope($datacenterId, $roomId, $rowId);

        $pdf = Pdf::loadView('pdf.capacity-report', [
            'metrics' => $metrics,
            'racks' => $racks,
            'filterScope' => $filterScope,
            'generatedBy' => $generator->name,
            'generatedAt' => now(),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $this->storeReport($pdf);
    }

    /**
     * Get detailed rack information for the report.
     *
     * @return Collection<int, array{
     *     datacenter: string,
     *     room: string,
     *     row: string,
     *     name: string,
     *     u_capacity: int,
     *     u_used: int,
     *     u_available: int,
     *     u_utilization: float,
     *     power_capacity: int|null,
     *     power_used: int,
     *     power_available: int|null,
     *     status: string
     * }>
     */
    protected function getRackDetails(?int $datacenterId, ?int $roomId, ?int $rowId): Collection
    {
        $query = Rack::query()
            ->where('status', RackStatus::Active)
            ->with(['row.room.datacenter', 'devices']);

        if ($rowId !== null) {
            $query->where('row_id', $rowId);
        } elseif ($roomId !== null) {
            $query->whereHas('row', function (Builder $q) use ($roomId) {
                $q->where('room_id', $roomId);
            });
        } elseif ($datacenterId !== null) {
            $query->whereHas('row.room', function (Builder $q) use ($datacenterId) {
                $q->where('datacenter_id', $datacenterId);
            });
        }

        return $query->get()->map(function (Rack $rack) {
            $uCapacity = $rack->u_height->value;
            $uUsed = (int) $rack->devices->sum('u_height');
            $uAvailable = $uCapacity - $uUsed;
            $uUtilization = $uCapacity > 0
                ? round(($uUsed / $uCapacity) * 100, 1)
                : 0.0;

            $powerCapacity = $rack->power_capacity_watts;
            $powerUsed = (int) $rack->devices->whereNotNull('power_draw_watts')->sum('power_draw_watts');
            $powerAvailable = $powerCapacity !== null ? $powerCapacity - $powerUsed : null;

            // Determine status based on utilization
            $status = 'normal';
            if ($uUtilization >= 90) {
                $status = 'critical';
            } elseif ($uUtilization >= 80) {
                $status = 'warning';
            }

            return [
                'datacenter' => $rack->row?->room?->datacenter?->name ?? 'Unknown',
                'room' => $rack->row?->room?->name ?? 'Unknown',
                'row' => $rack->row?->name ?? 'Unknown',
                'name' => $rack->name,
                'u_capacity' => $uCapacity,
                'u_used' => $uUsed,
                'u_available' => $uAvailable,
                'u_utilization' => $uUtilization,
                'power_capacity' => $powerCapacity,
                'power_used' => $powerUsed,
                'power_available' => $powerAvailable,
                'status' => $status,
            ];
        });
    }

    /**
     * Build a human-readable description of the filter scope.
     */
    protected function buildFilterScope(?int $datacenterId, ?int $roomId, ?int $rowId): string
    {
        $parts = [];

        if ($rowId !== null) {
            $row = Row::find($rowId);
            if ($row) {
                $parts[] = "Row: {$row->name}";
                $parts[] = "Room: {$row->room?->name}";
                $parts[] = "Datacenter: {$row->room?->datacenter?->name}";
            }
        } elseif ($roomId !== null) {
            $room = Room::find($roomId);
            if ($room) {
                $parts[] = "Room: {$room->name}";
                $parts[] = "Datacenter: {$room->datacenter?->name}";
            }
        } elseif ($datacenterId !== null) {
            $datacenter = Datacenter::find($datacenterId);
            if ($datacenter) {
                $parts[] = "Datacenter: {$datacenter->name}";
            }
        }

        return ! empty($parts) ? implode(' > ', array_reverse($parts)) : 'All Datacenters';
    }

    /**
     * Store the generated PDF report to the filesystem.
     */
    protected function storeReport(\Barryvdh\DomPDF\PDF $pdf): string
    {
        $timestamp = now()->format('YmdHis');
        $filename = "capacity-report-{$timestamp}.pdf";
        $filePath = "reports/capacity/{$filename}";

        Storage::disk('local')->put($filePath, $pdf->output());

        return $filePath;
    }
}
