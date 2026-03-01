<?php

namespace App\Exports;

use App\Enums\RackStatus;
use App\Models\Rack;
use Illuminate\Database\Eloquent\Builder;

/**
 * Export class for Capacity Report data.
 *
 * Exports rack capacity data including U-space and power utilization
 * for analysis in external spreadsheet applications.
 */
class CapacityReportExport extends AbstractDataExport
{
    /**
     * Get the column headers for the export.
     *
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'Datacenter',
            'Room',
            'Row',
            'Rack',
            'U Capacity',
            'U Used',
            'U Available',
            'Power Capacity (W)',
            'Power Used (W)',
            'Power Available (W)',
        ];
    }

    /**
     * Get the sheet title.
     */
    public function title(): string
    {
        return 'Capacity Report';
    }

    /**
     * Get the query builder for racks with eager loading.
     */
    protected function query(): Builder
    {
        $query = Rack::query()
            ->where('status', RackStatus::Active)
            ->with(['row.room.datacenter', 'devices']);

        // Apply hierarchical filters
        if (! empty($this->filters['row_id'])) {
            $query->where('row_id', $this->filters['row_id']);
        } elseif (! empty($this->filters['room_id'])) {
            $query->whereHas('row', function (Builder $rowQuery) {
                $rowQuery->where('room_id', $this->filters['room_id']);
            });
        } elseif (! empty($this->filters['datacenter_id'])) {
            $query->whereHas('row.room', function (Builder $roomQuery) {
                $roomQuery->where('datacenter_id', $this->filters['datacenter_id']);
            });
        }

        return $query;
    }

    /**
     * Transform a Rack model to a row array with capacity data.
     *
     * @param  Rack  $rack
     * @return array<mixed>
     */
    protected function transformRow($rack): array
    {
        $uCapacity = $rack->u_height?->value ?? 0;
        $uUsed = (int) $rack->devices->sum('u_height');
        $uAvailable = $uCapacity - $uUsed;

        $powerCapacity = $rack->power_capacity_watts;
        $powerUsed = (int) $rack->devices->whereNotNull('power_draw_watts')->sum('power_draw_watts');
        $powerAvailable = $powerCapacity !== null ? $powerCapacity - $powerUsed : null;

        return [
            $rack->row?->room?->datacenter?->name ?? 'Unknown',
            $rack->row?->room?->name ?? 'Unknown',
            $rack->row?->name ?? 'Unknown',
            $rack->name,
            $uCapacity,
            $uUsed,
            $uAvailable,
            $powerCapacity ?? 'Not configured',
            $powerUsed,
            $powerAvailable ?? 'N/A',
        ];
    }
}
