<?php

namespace App\Exports;

use App\Exports\Templates\DeviceTemplateExport;
use App\Models\Device;
use Illuminate\Database\Eloquent\Builder;

/**
 * Export class for Device entities.
 *
 * Exports device data with the same column structure as the
 * DeviceTemplateExport for round-trip compatibility with imports.
 * Supports filtering by datacenter_id, room_id, row_id, and rack_id.
 * Uses eager loading to prevent N+1 queries.
 */
class DeviceExport extends AbstractDataExport
{
    /**
     * Get the column headers for the export.
     *
     * Reuses headings from DeviceTemplateExport for consistency.
     *
     * @return array<string>
     */
    public function headings(): array
    {
        return (new DeviceTemplateExport)->headings();
    }

    /**
     * Get the sheet title.
     */
    public function title(): string
    {
        return 'Devices';
    }

    /**
     * Get the query builder for devices with eager loading.
     */
    protected function query(): Builder
    {
        $query = Device::query()->with(['rack.row.room.datacenter', 'deviceType']);

        // Apply hierarchical filters
        if (! empty($this->filters['rack_id'])) {
            $query->where('rack_id', $this->filters['rack_id']);
        } elseif (! empty($this->filters['row_id'])) {
            $query->whereHas('rack', function (Builder $rackQuery) {
                $rackQuery->where('row_id', $this->filters['row_id']);
            });
        } elseif (! empty($this->filters['room_id'])) {
            $query->whereHas('rack.row', function (Builder $rowQuery) {
                $rowQuery->where('room_id', $this->filters['room_id']);
            });
        } elseif (! empty($this->filters['datacenter_id'])) {
            $query->whereHas('rack.row.room', function (Builder $roomQuery) {
                $roomQuery->where('datacenter_id', $this->filters['datacenter_id']);
            });
        }

        return $query;
    }

    /**
     * Transform a Device model to a row array.
     *
     * @param  Device  $device
     * @return array<mixed>
     */
    protected function transformRow($device): array
    {
        return [
            $device->rack?->row?->room?->datacenter?->name,
            $device->rack?->row?->room?->name,
            $device->rack?->row?->name,
            $device->rack?->name,
            $device->name,
            $device->deviceType?->name,
            $device->lifecycle_status?->label(),
            $device->serial_number,
            $device->manufacturer,
            $device->model,
            $device->purchase_date?->format('Y-m-d'),
            $device->warranty_start_date?->format('Y-m-d'),
            $device->warranty_end_date?->format('Y-m-d'),
            $device->u_height,
            $device->depth?->label(),
            $device->width_type?->label(),
            $device->rack_face?->label(),
            $device->start_u,
            $device->notes,
        ];
    }
}
