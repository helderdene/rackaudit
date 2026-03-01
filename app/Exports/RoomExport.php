<?php

namespace App\Exports;

use App\Exports\Templates\RoomTemplateExport;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;

/**
 * Export class for Room entities.
 *
 * Exports room data with the same column structure as the
 * RoomTemplateExport for round-trip compatibility with imports.
 * Supports filtering by datacenter_id.
 */
class RoomExport extends AbstractDataExport
{
    /**
     * Get the column headers for the export.
     *
     * Reuses headings from RoomTemplateExport for consistency.
     *
     * @return array<string>
     */
    public function headings(): array
    {
        return (new RoomTemplateExport)->headings();
    }

    /**
     * Get the sheet title.
     */
    public function title(): string
    {
        return 'Rooms';
    }

    /**
     * Get the query builder for rooms with eager loading.
     */
    protected function query(): Builder
    {
        $query = Room::query()->with('datacenter');

        // Apply hierarchical filters
        if (! empty($this->filters['room_id'])) {
            $query->where('id', $this->filters['room_id']);
        } elseif (! empty($this->filters['datacenter_id'])) {
            $query->where('datacenter_id', $this->filters['datacenter_id']);
        }

        return $query;
    }

    /**
     * Transform a Room model to a row array.
     *
     * @param  Room  $room
     * @return array<mixed>
     */
    protected function transformRow($room): array
    {
        return [
            $room->datacenter?->name,
            $room->name,
            $room->type?->label(),
            $room->description,
            $room->square_footage,
        ];
    }
}
