<?php

namespace App\Exports;

use App\Enums\DeviceLifecycleStatus;
use App\Models\Device;
use Illuminate\Database\Eloquent\Builder;

/**
 * Export class for Asset Report data.
 *
 * Exports device inventory data including asset tag, name, serial number,
 * manufacturer, model, device type, lifecycle status, location, and warranty info.
 */
class AssetReportExport extends AbstractDataExport
{
    /**
     * Get the column headers for the export.
     *
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'Asset Tag',
            'Name',
            'Serial Number',
            'Manufacturer',
            'Model',
            'Device Type',
            'Lifecycle Status',
            'Datacenter',
            'Room',
            'Rack',
            'U Position',
            'Warranty End Date',
        ];
    }

    /**
     * Get the sheet title.
     */
    public function title(): string
    {
        return 'Asset Report';
    }

    /**
     * Get the query builder for devices with eager loading.
     */
    protected function query(): Builder
    {
        $query = Device::query()
            ->with(['deviceType', 'rack.row.room.datacenter']);

        // Apply datacenter filter (devices in racks within the datacenter)
        if (! empty($this->filters['datacenter_id'])) {
            $datacenterId = $this->filters['datacenter_id'];
            $query->whereHas('rack.row.room', function (Builder $subQuery) use ($datacenterId) {
                $subQuery->where('datacenter_id', $datacenterId);
            });
        }

        // Apply room filter (devices in racks within the room)
        if (! empty($this->filters['room_id'])) {
            $roomId = $this->filters['room_id'];
            $query->whereHas('rack.row', function (Builder $subQuery) use ($roomId) {
                $subQuery->where('room_id', $roomId);
            });
        }

        // Apply device type filter
        if (! empty($this->filters['device_type_id'])) {
            $query->where('device_type_id', $this->filters['device_type_id']);
        }

        // Apply lifecycle status filter
        if (! empty($this->filters['lifecycle_status'])) {
            $query->where('lifecycle_status', $this->filters['lifecycle_status']);
        }

        // Apply manufacturer filter
        if (! empty($this->filters['manufacturer'])) {
            $query->where('manufacturer', $this->filters['manufacturer']);
        }

        // Apply warranty date range filter
        if (! empty($this->filters['warranty_start']) && ! empty($this->filters['warranty_end'])) {
            $query->whereBetween('warranty_end_date', [
                $this->filters['warranty_start'],
                $this->filters['warranty_end'],
            ]);
        } elseif (! empty($this->filters['warranty_start'])) {
            $query->where('warranty_end_date', '>=', $this->filters['warranty_start']);
        } elseif (! empty($this->filters['warranty_end'])) {
            $query->where('warranty_end_date', '<=', $this->filters['warranty_end']);
        }

        return $query->orderBy('asset_tag');
    }

    /**
     * Transform a Device model to a row array with inventory data.
     *
     * @param  Device  $device
     * @return array<mixed>
     */
    protected function transformRow($device): array
    {
        $lifecycleStatus = $device->lifecycle_status instanceof DeviceLifecycleStatus
            ? $device->lifecycle_status->label()
            : 'Unknown';

        return [
            $device->asset_tag ?? 'N/A',
            $device->name ?? 'N/A',
            $device->serial_number ?? 'N/A',
            $device->manufacturer ?? 'N/A',
            $device->model ?? 'N/A',
            $device->deviceType?->name ?? 'Unknown',
            $lifecycleStatus,
            $device->rack?->row?->room?->datacenter?->name ?? 'N/A',
            $device->rack?->row?->room?->name ?? 'N/A',
            $device->rack?->name ?? 'N/A',
            $device->start_u ?? 'N/A',
            $device->warranty_end_date?->format('Y-m-d') ?? 'Not tracked',
        ];
    }
}
