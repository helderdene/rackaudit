<?php

namespace App\Exports;

use App\Exports\Templates\RowTemplateExport;
use App\Models\Row;
use Illuminate\Database\Eloquent\Builder;

/**
 * Export class for Row entities.
 *
 * Exports row data with the same column structure as the
 * RowTemplateExport for round-trip compatibility with imports.
 * Supports filtering by datacenter_id and room_id.
 */
class RowExport extends AbstractDataExport
{
    /**
     * Get the column headers for the export.
     *
     * Reuses headings from RowTemplateExport for consistency.
     *
     * @return array<string>
     */
    public function headings(): array
    {
        return (new RowTemplateExport())->headings();
    }

    /**
     * Get the sheet title.
     */
    public function title(): string
    {
        return 'Rows';
    }

    /**
     * Get the query builder for rows with eager loading.
     */
    protected function query(): Builder
    {
        $query = Row::query()->with('room.datacenter');

        // Apply hierarchical filters
        if (! empty($this->filters['row_id'])) {
            $query->where('id', $this->filters['row_id']);
        } elseif (! empty($this->filters['room_id'])) {
            $query->where('room_id', $this->filters['room_id']);
        } elseif (! empty($this->filters['datacenter_id'])) {
            $query->whereHas('room', function (Builder $roomQuery) {
                $roomQuery->where('datacenter_id', $this->filters['datacenter_id']);
            });
        }

        return $query;
    }

    /**
     * Transform a Row model to a row array.
     *
     * @param  Row  $row
     * @return array<mixed>
     */
    protected function transformRow($row): array
    {
        return [
            $row->room?->datacenter?->name,
            $row->room?->name,
            $row->name,
            $row->position,
            $row->orientation?->label(),
            $row->status?->label(),
        ];
    }
}
