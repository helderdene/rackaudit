<?php

namespace App\Services;

use App\Enums\PortType;
use App\Enums\RackStatus;
use App\Models\Port;
use App\Models\Rack;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Service for calculating capacity metrics across datacenters.
 *
 * Calculates U-space utilization, power consumption, port capacity,
 * and identifies racks approaching capacity thresholds.
 */
class CapacityCalculationService
{
    /**
     * Warning threshold for rack utilization (80%).
     */
    private const WARNING_THRESHOLD = 80;

    /**
     * Critical threshold for rack utilization (90%).
     */
    private const CRITICAL_THRESHOLD = 90;

    /**
     * Calculate U-space utilization for filtered racks.
     *
     * @param  Builder<Rack>  $rackQuery
     * @return array{total_u_space: int, used_u_space: int, available_u_space: int, utilization_percent: float}
     */
    public function calculateUSpaceUtilization(Builder $rackQuery): array
    {
        $racks = (clone $rackQuery)->with('devices')->get();

        $totalUSpace = 0;
        $usedUSpace = 0;

        foreach ($racks as $rack) {
            $totalUSpace += $rack->u_height->value;
            $usedUSpace += (int) $rack->devices->sum('u_height');
        }

        $availableUSpace = $totalUSpace - $usedUSpace;
        $utilizationPercent = $totalUSpace > 0
            ? round(($usedUSpace / $totalUSpace) * 100, 1)
            : 0.0;

        return [
            'total_u_space' => $totalUSpace,
            'used_u_space' => $usedUSpace,
            'available_u_space' => $availableUSpace,
            'utilization_percent' => $utilizationPercent,
        ];
    }

    /**
     * Calculate power utilization for filtered racks.
     *
     * Handles null values gracefully by excluding them from calculations.
     *
     * @param  Builder<Rack>  $rackQuery
     * @return array{total_capacity: int, total_consumption: int, power_headroom: int, utilization_percent: float|null}
     */
    public function calculatePowerUtilization(Builder $rackQuery): array
    {
        $racks = (clone $rackQuery)->with('devices')->get();

        $totalCapacity = 0;
        $totalConsumption = 0;

        foreach ($racks as $rack) {
            // Only count racks with configured power capacity
            if ($rack->power_capacity_watts !== null) {
                $totalCapacity += $rack->power_capacity_watts;
            }

            // Sum device power draw, excluding null values
            foreach ($rack->devices as $device) {
                if ($device->power_draw_watts !== null) {
                    $totalConsumption += $device->power_draw_watts;
                }
            }
        }

        $powerHeadroom = $totalCapacity - $totalConsumption;

        // Only calculate utilization percent if we have capacity configured
        $utilizationPercent = $totalCapacity > 0
            ? round(($totalConsumption / $totalCapacity) * 100, 1)
            : null;

        return [
            'total_capacity' => $totalCapacity,
            'total_consumption' => $totalConsumption,
            'power_headroom' => $powerHeadroom,
            'utilization_percent' => $utilizationPercent,
        ];
    }

    /**
     * Calculate port capacity grouped by PortType enum.
     *
     * @param  Builder<Rack>  $rackQuery
     * @return array<string, array{total_ports: int, connected_ports: int, available_ports: int, label: string}>
     */
    public function calculatePortCapacity(Builder $rackQuery): array
    {
        $rackIds = (clone $rackQuery)->pluck('id');

        // Initialize result array with all port types
        $result = [];
        foreach (PortType::cases() as $portType) {
            $result[$portType->value] = [
                'total_ports' => 0,
                'connected_ports' => 0,
                'available_ports' => 0,
                'label' => $portType->label(),
            ];
        }

        if ($rackIds->isEmpty()) {
            return $result;
        }

        // Query ports through device > rack relationship
        $ports = Port::query()
            ->whereHas('device', function (Builder $q) use ($rackIds) {
                $q->whereIn('rack_id', $rackIds);
            })
            ->with(['connectionAsSource', 'connectionAsDestination'])
            ->get();

        // Group and count ports by type
        foreach ($ports as $port) {
            $typeValue = $port->type->value;

            $result[$typeValue]['total_ports']++;

            // Port is connected if it has a connection as source or destination
            $isConnected = $port->connectionAsSource !== null || $port->connectionAsDestination !== null;

            if ($isConnected) {
                $result[$typeValue]['connected_ports']++;
            } else {
                $result[$typeValue]['available_ports']++;
            }
        }

        return $result;
    }

    /**
     * Get racks approaching capacity threshold.
     *
     * @param  Builder<Rack>  $rackQuery
     * @param  int  $threshold  Utilization threshold percentage (default: 80)
     * @return Collection<int, array{id: int, name: string, utilization_percent: float, available_u_space: int, status: string}>
     */
    public function getRacksApproachingCapacity(Builder $rackQuery, int $threshold = self::WARNING_THRESHOLD): Collection
    {
        $racks = (clone $rackQuery)->with('devices')->get();

        $result = collect();

        foreach ($racks as $rack) {
            $totalUSpace = $rack->u_height->value;
            $usedUSpace = (int) $rack->devices->sum('u_height');
            $availableUSpace = $totalUSpace - $usedUSpace;

            $utilizationPercent = $totalUSpace > 0
                ? round(($usedUSpace / $totalUSpace) * 100, 1)
                : 0.0;

            if ($utilizationPercent >= $threshold) {
                $status = $utilizationPercent >= self::CRITICAL_THRESHOLD ? 'critical' : 'warning';

                $result->push([
                    'id' => $rack->id,
                    'name' => $rack->name,
                    'utilization_percent' => $utilizationPercent,
                    'available_u_space' => $availableUSpace,
                    'status' => $status,
                ]);
            }
        }

        // Sort by utilization descending (most critical first)
        return $result->sortByDesc('utilization_percent')->values();
    }

    /**
     * Get aggregated capacity metrics for the given filter scope.
     *
     * @return array{
     *     u_space: array{total_u_space: int, used_u_space: int, available_u_space: int, utilization_percent: float},
     *     power: array{total_capacity: int, total_consumption: int, power_headroom: int, utilization_percent: float|null},
     *     port_capacity: array<string, array{total_ports: int, connected_ports: int, available_ports: int, label: string}>,
     *     racks_approaching_capacity: Collection
     * }
     */
    public function getCapacityMetrics(?int $datacenterId, ?int $roomId, ?int $rowId): array
    {
        $query = $this->buildFilteredRackQuery($datacenterId, $roomId, $rowId);

        return [
            'u_space' => $this->calculateUSpaceUtilization(clone $query),
            'power' => $this->calculatePowerUtilization(clone $query),
            'port_capacity' => $this->calculatePortCapacity(clone $query),
            'racks_approaching_capacity' => $this->getRacksApproachingCapacity(clone $query),
        ];
    }

    /**
     * Build a filtered rack query based on datacenter, room, and row filters.
     *
     * @return Builder<Rack>
     */
    private function buildFilteredRackQuery(?int $datacenterId, ?int $roomId, ?int $rowId): Builder
    {
        $query = Rack::query()->where('status', RackStatus::Active);

        if ($rowId !== null) {
            // Filter by specific row
            $query->where('row_id', $rowId);
        } elseif ($roomId !== null) {
            // Filter by room (all rows in the room)
            $query->whereHas('row', function (Builder $q) use ($roomId) {
                $q->where('room_id', $roomId);
            });
        } elseif ($datacenterId !== null) {
            // Filter by datacenter (all rooms in the datacenter)
            $query->whereHas('row.room', function (Builder $q) use ($datacenterId) {
                $q->where('datacenter_id', $datacenterId);
            });
        }

        return $query;
    }
}
