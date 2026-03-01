<?php

namespace App\Exports;

use App\Exports\Templates\RackTemplateExport;
use App\Models\Rack;
use Illuminate\Database\Eloquent\Builder;

/**
 * Export class for Rack entities.
 *
 * Exports rack data with the same column structure as the
 * RackTemplateExport for round-trip compatibility with imports.
 * Supports filtering by datacenter_id, room_id, and row_id.
 */
class RackExport extends AbstractDataExport
{
    /**
     * Get the column headers for the export.
     *
     * Reuses headings from RackTemplateExport for consistency.
     *
     * @return array<string>
     */
    public function headings(): array
    {
        return (new RackTemplateExport())->headings();
    }

    /**
     * Get the sheet title.
     */
    public function title(): string
    {
        return 'Racks';
    }

    /**
     * Get the query builder for racks with eager loading.
     */
    protected function query(): Builder
    {
        $query = Rack::query()->with('row.room.datacenter');

        // Apply hierarchical filters
        if (! empty($this->filters['rack_id'])) {
            $query->where('id', $this->filters['rack_id']);
        } elseif (! empty($this->filters['row_id'])) {
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
     * Transform a Rack model to a row array.
     *
     * @param  Rack  $rack
     * @return array<mixed>
     */
    protected function transformRow($rack): array
    {
        return [
            $rack->row?->room?->datacenter?->name,
            $rack->row?->room?->name,
            $rack->row?->name,
            $rack->name,
            $rack->position,
            $rack->u_height?->value,
            $rack->serial_number,
            $rack->status?->label(),
        ];
    }
}
