<?php

namespace App\Exports;

use App\Exports\Templates\PortTemplateExport;
use App\Models\Port;
use Illuminate\Database\Eloquent\Builder;

/**
 * Export class for Port entities.
 *
 * Exports port data with the same column structure as the
 * PortTemplateExport for round-trip compatibility with imports.
 * Supports filtering by datacenter_id, room_id, row_id, and rack_id.
 * Uses eager loading to prevent N+1 queries.
 */
class PortExport extends AbstractDataExport
{
    /**
     * Get the column headers for the export.
     *
     * Reuses headings from PortTemplateExport for consistency.
     *
     * @return array<string>
     */
    public function headings(): array
    {
        return (new PortTemplateExport)->headings();
    }

    /**
     * Get the sheet title.
     */
    public function title(): string
    {
        return 'Ports';
    }

    /**
     * Get the query builder for ports with eager loading.
     */
    protected function query(): Builder
    {
        $query = Port::query()->with('device.rack.row.room.datacenter');

        // Apply hierarchical filters
        if (! empty($this->filters['rack_id'])) {
            $query->whereHas('device', function (Builder $deviceQuery) {
                $deviceQuery->where('rack_id', $this->filters['rack_id']);
            });
        } elseif (! empty($this->filters['row_id'])) {
            $query->whereHas('device.rack', function (Builder $rackQuery) {
                $rackQuery->where('row_id', $this->filters['row_id']);
            });
        } elseif (! empty($this->filters['room_id'])) {
            $query->whereHas('device.rack.row', function (Builder $rowQuery) {
                $rowQuery->where('room_id', $this->filters['room_id']);
            });
        } elseif (! empty($this->filters['datacenter_id'])) {
            $query->whereHas('device.rack.row.room', function (Builder $roomQuery) {
                $roomQuery->where('datacenter_id', $this->filters['datacenter_id']);
            });
        }

        return $query;
    }

    /**
     * Transform a Port model to a row array.
     *
     * @param  Port  $port
     * @return array<mixed>
     */
    protected function transformRow($port): array
    {
        return [
            $port->device?->name,
            $port->label,
            $port->type?->label(),
            $port->subtype?->label(),
            $port->status?->label(),
            $port->direction?->label(),
            $port->position_slot,
            $port->position_row,
            $port->position_column,
        ];
    }
}
