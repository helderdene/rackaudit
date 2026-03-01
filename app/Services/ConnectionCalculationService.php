<?php

namespace App\Services;

use App\Enums\CableType;
use App\Enums\PortStatus;
use App\Enums\PortType;
use App\Models\Connection;
use App\Models\Port;
use Illuminate\Database\Eloquent\Builder;

/**
 * Service for calculating connection metrics across datacenters.
 *
 * Calculates cable type distribution, port utilization metrics,
 * cable length statistics, and supports various location filters.
 */
class ConnectionCalculationService
{
    /**
     * Build a filtered connection query based on datacenter and room filters.
     *
     * Filters connections through the chain:
     * Connection > sourcePort > device > rack > row > room > datacenter
     *
     * @return Builder<Connection>
     */
    public function buildFilteredConnectionQuery(
        ?int $datacenterId = null,
        ?int $roomId = null
    ): Builder {
        $query = Connection::query()
            ->with(['sourcePort.device', 'destinationPort.device']);

        // Filter by datacenter (through source port > device > rack > row > room > datacenter chain)
        if ($datacenterId !== null) {
            $query->whereHas('sourcePort.device.rack.row.room', function (Builder $subQuery) use ($datacenterId) {
                $subQuery->where('datacenter_id', $datacenterId);
            });
        }

        // Filter by room (through source port > device > rack > row > room chain)
        if ($roomId !== null) {
            $query->whereHas('sourcePort.device.rack.row', function (Builder $subQuery) use ($roomId) {
                $subQuery->where('room_id', $roomId);
            });
        }

        return $query;
    }

    /**
     * Build a filtered port query based on datacenter and room filters.
     *
     * Filters ports through the chain:
     * Port > device > rack > row > room > datacenter
     *
     * @return Builder<Port>
     */
    public function buildFilteredPortQuery(
        ?int $datacenterId = null,
        ?int $roomId = null
    ): Builder {
        $query = Port::query()->with('device');

        // Filter by datacenter (through device > rack > row > room > datacenter chain)
        if ($datacenterId !== null) {
            $query->whereHas('device.rack.row.room', function (Builder $subQuery) use ($datacenterId) {
                $subQuery->where('datacenter_id', $datacenterId);
            });
        }

        // Filter by room (through device > rack > row > room chain)
        if ($roomId !== null) {
            $query->whereHas('device.rack.row', function (Builder $subQuery) use ($roomId) {
                $subQuery->where('room_id', $roomId);
            });
        }

        return $query;
    }

    /**
     * Get cable type distribution for connections.
     *
     * Returns count and percentage for each CableType enum value.
     *
     * @param  Builder<Connection>  $query
     * @return array<int, array{type: string, label: string, count: int, percentage: float}>
     */
    public function getCableTypeDistribution(Builder $query): array
    {
        $connections = (clone $query)->get();
        $totalConnections = $connections->count();

        // Initialize counts for all cable types
        $typeCounts = [];
        foreach (CableType::cases() as $type) {
            $typeCounts[$type->value] = 0;
        }

        // Count connections by cable type
        foreach ($connections as $connection) {
            if ($connection->cable_type !== null) {
                $typeCounts[$connection->cable_type->value]++;
            }
        }

        // Build result array with all types
        $result = [];
        foreach (CableType::cases() as $type) {
            $count = $typeCounts[$type->value];
            $percentage = $totalConnections > 0
                ? round(($count / $totalConnections) * 100, 1)
                : 0.0;

            $result[] = [
                'type' => $type->value,
                'label' => $type->label(),
                'count' => $count,
                'percentage' => $percentage,
            ];
        }

        return $result;
    }

    /**
     * Get port type distribution for connections.
     *
     * Counts connections by source port type (Ethernet, Fiber, Power).
     *
     * @param  Builder<Connection>  $query
     * @return array<int, array{type: string, label: string, count: int, percentage: float}>
     */
    public function getPortTypeDistribution(Builder $query): array
    {
        $connections = (clone $query)->with('sourcePort')->get();
        $totalConnections = $connections->count();

        // Initialize counts for all port types
        $typeCounts = [];
        foreach (PortType::cases() as $type) {
            $typeCounts[$type->value] = 0;
        }

        // Count connections by source port type
        foreach ($connections as $connection) {
            $portType = $connection->sourcePort?->type;
            if ($portType !== null) {
                $typeCounts[$portType->value]++;
            }
        }

        // Build result array with all types
        $result = [];
        foreach (PortType::cases() as $type) {
            $count = $typeCounts[$type->value];
            $percentage = $totalConnections > 0
                ? round(($count / $totalConnections) * 100, 1)
                : 0.0;

            $result[] = [
                'type' => $type->value,
                'label' => $type->label(),
                'count' => $count,
                'percentage' => $percentage,
            ];
        }

        return $result;
    }

    /**
     * Get cable length statistics for connections.
     *
     * Calculates mean, min, max for connections with non-null cable_length.
     * Handles edge case when no connections have cable_length data.
     *
     * @param  Builder<Connection>  $query
     * @return array{mean: float|null, min: float|null, max: float|null, count: int}
     */
    public function getCableLengthStatistics(Builder $query): array
    {
        $connections = (clone $query)
            ->whereNotNull('cable_length')
            ->get();

        $count = $connections->count();

        if ($count === 0) {
            return [
                'mean' => null,
                'min' => null,
                'max' => null,
                'count' => 0,
            ];
        }

        $lengths = $connections->pluck('cable_length')->map(fn ($l) => (float) $l);

        return [
            'mean' => round($lengths->avg(), 2),
            'min' => round($lengths->min(), 2),
            'max' => round($lengths->max(), 2),
            'count' => $count,
        ];
    }

    /**
     * Get port utilization metrics.
     *
     * Calculates total ports vs connected ports with breakdowns by type and status.
     *
     * @param  Builder<Port>  $query
     * @return array{
     *     byType: array<int, array{type: string, label: string, total: int, connected: int, percentage: float}>,
     *     byStatus: array<int, array{status: string, label: string, count: int, percentage: float}>,
     *     overall: array{total: int, connected: int, percentage: float}
     * }
     */
    public function getPortUtilizationMetrics(Builder $query): array
    {
        $ports = (clone $query)->get();
        $totalPorts = $ports->count();

        // Calculate by port type
        $byType = [];
        foreach (PortType::cases() as $type) {
            $portsOfType = $ports->filter(fn ($port) => $port->type === $type);
            $totalOfType = $portsOfType->count();
            $connectedOfType = $portsOfType->filter(fn ($port) => $port->status === PortStatus::Connected)->count();
            $percentage = $totalOfType > 0
                ? round(($connectedOfType / $totalOfType) * 100, 1)
                : 0.0;

            $byType[] = [
                'type' => $type->value,
                'label' => $type->label(),
                'total' => $totalOfType,
                'connected' => $connectedOfType,
                'percentage' => $percentage,
            ];
        }

        // Calculate by status
        $byStatus = [];
        foreach (PortStatus::cases() as $status) {
            $count = $ports->filter(fn ($port) => $port->status === $status)->count();
            $percentage = $totalPorts > 0
                ? round(($count / $totalPorts) * 100, 1)
                : 0.0;

            $byStatus[] = [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => $count,
                'percentage' => $percentage,
            ];
        }

        // Calculate overall
        $totalConnected = $ports->filter(fn ($port) => $port->status === PortStatus::Connected)->count();
        $overallPercentage = $totalPorts > 0
            ? round(($totalConnected / $totalPorts) * 100, 1)
            : 0.0;

        return [
            'byType' => $byType,
            'byStatus' => $byStatus,
            'overall' => [
                'total' => $totalPorts,
                'connected' => $totalConnected,
                'percentage' => $overallPercentage,
            ],
        ];
    }

    /**
     * Get aggregated connection metrics for the given filter scope.
     *
     * Combines all metrics into a single structured response.
     *
     * @return array{
     *     totalConnections: int,
     *     cableTypeDistribution: array<int, array{type: string, label: string, count: int, percentage: float}>,
     *     portTypeDistribution: array<int, array{type: string, label: string, count: int, percentage: float}>,
     *     cableLengthStats: array{mean: float|null, min: float|null, max: float|null, count: int},
     *     portUtilization: array{
     *         byType: array<int, array{type: string, label: string, total: int, connected: int, percentage: float}>,
     *         byStatus: array<int, array{status: string, label: string, count: int, percentage: float}>,
     *         overall: array{total: int, connected: int, percentage: float}
     *     }
     * }
     */
    public function getConnectionMetrics(
        ?int $datacenterId = null,
        ?int $roomId = null
    ): array {
        $connectionQuery = $this->buildFilteredConnectionQuery($datacenterId, $roomId);
        $portQuery = $this->buildFilteredPortQuery($datacenterId, $roomId);

        return [
            'totalConnections' => (clone $connectionQuery)->count(),
            'cableTypeDistribution' => $this->getCableTypeDistribution(clone $connectionQuery),
            'portTypeDistribution' => $this->getPortTypeDistribution(clone $connectionQuery),
            'cableLengthStats' => $this->getCableLengthStatistics(clone $connectionQuery),
            'portUtilization' => $this->getPortUtilizationMetrics(clone $portQuery),
        ];
    }
}
