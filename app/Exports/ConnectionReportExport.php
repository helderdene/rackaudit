<?php

namespace App\Exports;

use App\Enums\CableType;
use App\Models\Connection;
use Illuminate\Database\Eloquent\Builder;

/**
 * Export class for Connection Report data.
 *
 * Exports connection inventory data including source device, source port,
 * destination device, destination port, cable type, cable length, and cable color.
 */
class ConnectionReportExport extends AbstractDataExport
{
    /**
     * Get the column headers for the export.
     *
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'Source Device',
            'Source Port',
            'Destination Device',
            'Destination Port',
            'Cable Type',
            'Cable Length',
            'Cable Color',
        ];
    }

    /**
     * Get the sheet title.
     */
    public function title(): string
    {
        return 'Connection Report';
    }

    /**
     * Get the query builder for connections with eager loading.
     */
    protected function query(): Builder
    {
        $query = Connection::query()
            ->with(['sourcePort.device', 'destinationPort.device']);

        // Apply datacenter filter (connections through port > device > rack > row > room > datacenter chain)
        if (! empty($this->filters['datacenter_id'])) {
            $datacenterId = $this->filters['datacenter_id'];
            $query->whereHas('sourcePort.device.rack.row.room', function (Builder $subQuery) use ($datacenterId) {
                $subQuery->where('datacenter_id', $datacenterId);
            });
        }

        // Apply room filter (connections through port > device > rack > row > room chain)
        if (! empty($this->filters['room_id'])) {
            $roomId = $this->filters['room_id'];
            $query->whereHas('sourcePort.device.rack.row', function (Builder $subQuery) use ($roomId) {
                $subQuery->where('room_id', $roomId);
            });
        }

        return $query->orderBy(
            Connection::query()
                ->select('devices.name')
                ->join('ports', 'ports.id', '=', 'connections.source_port_id')
                ->join('devices', 'devices.id', '=', 'ports.device_id')
                ->whereColumn('connections.id', 'connections.id')
                ->limit(1),
            'asc'
        );
    }

    /**
     * Transform a Connection model to a row array with inventory data.
     *
     * @param  Connection  $connection
     * @return array<mixed>
     */
    protected function transformRow($connection): array
    {
        $cableTypeLabel = $connection->cable_type instanceof CableType
            ? $connection->cable_type->label()
            : 'Unknown';

        // Format cable length with unit (meters as default)
        $cableLengthFormatted = $connection->cable_length !== null
            ? number_format((float) $connection->cable_length, 2).' m'
            : 'N/A';

        return [
            $connection->sourcePort?->device?->name ?? 'N/A',
            $connection->sourcePort?->label ?? 'N/A',
            $connection->destinationPort?->device?->name ?? 'N/A',
            $connection->destinationPort?->label ?? 'N/A',
            $cableTypeLabel,
            $cableLengthFormatted,
            $connection->cable_color ?? 'N/A',
        ];
    }
}
