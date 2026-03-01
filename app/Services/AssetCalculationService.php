<?php

namespace App\Services;

use App\Enums\DeviceLifecycleStatus;
use App\Models\Device;
use Illuminate\Database\Eloquent\Builder;

/**
 * Service for calculating asset metrics across devices.
 *
 * Calculates warranty status distribution, lifecycle distribution,
 * device counts by type and manufacturer, and supports various filters.
 */
class AssetCalculationService
{
    /**
     * Number of days threshold for "expiring soon" warranty status.
     */
    private const WARRANTY_EXPIRING_SOON_DAYS = 30;

    /**
     * Get warranty status counts for devices.
     *
     * Categorizes devices into four warranty status groups:
     * - Active: warranty_end_date > today + 30 days
     * - Expiring soon: warranty_end_date within 30 days from today
     * - Expired: warranty_end_date < today
     * - Unknown: warranty_end_date is null
     *
     * @param  Builder<Device>  $query
     * @return array{active: int, expiring_soon: int, expired: int, unknown: int}
     */
    public function getWarrantyStatusCounts(Builder $query): array
    {
        $devices = (clone $query)->get();
        $today = now()->startOfDay();
        $expiringSoonThreshold = now()->addDays(self::WARRANTY_EXPIRING_SOON_DAYS)->endOfDay();

        $counts = [
            'active' => 0,
            'expiring_soon' => 0,
            'expired' => 0,
            'unknown' => 0,
        ];

        foreach ($devices as $device) {
            if ($device->warranty_end_date === null) {
                $counts['unknown']++;
            } elseif ($device->warranty_end_date < $today) {
                $counts['expired']++;
            } elseif ($device->warranty_end_date <= $expiringSoonThreshold) {
                $counts['expiring_soon']++;
            } else {
                $counts['active']++;
            }
        }

        return $counts;
    }

    /**
     * Get device lifecycle distribution.
     *
     * Returns counts for all 7 DeviceLifecycleStatus values with
     * status code, label, count, and percentage.
     *
     * @param  Builder<Device>  $query
     * @return array<int, array{status: string, label: string, count: int, percentage: float}>
     */
    public function getLifecycleDistribution(Builder $query): array
    {
        $devices = (clone $query)->get();
        $totalDevices = $devices->count();

        // Initialize counts for all lifecycle statuses
        $statusCounts = [];
        foreach (DeviceLifecycleStatus::cases() as $status) {
            $statusCounts[$status->value] = 0;
        }

        // Count devices by lifecycle status
        foreach ($devices as $device) {
            if ($device->lifecycle_status !== null) {
                $statusCounts[$device->lifecycle_status->value]++;
            }
        }

        // Build result array with all statuses
        $result = [];
        foreach (DeviceLifecycleStatus::cases() as $status) {
            $count = $statusCounts[$status->value];
            $percentage = $totalDevices > 0
                ? round(($count / $totalDevices) * 100, 1)
                : 0.0;

            $result[] = [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => $count,
                'percentage' => $percentage,
            ];
        }

        return $result;
    }

    /**
     * Get device counts grouped by device type.
     *
     * @param  Builder<Device>  $query
     * @return array<int, array{name: string, count: int}>
     */
    public function getDeviceCountsByType(Builder $query): array
    {
        $devices = (clone $query)->with('deviceType')->get();

        $countsByType = $devices->groupBy(function ($device) {
            return $device->deviceType?->name ?? 'Unknown';
        })->map(function ($group, $typeName) {
            return [
                'name' => $typeName,
                'count' => $group->count(),
            ];
        })->values()->toArray();

        // Sort by count descending
        usort($countsByType, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $countsByType;
    }

    /**
     * Get device counts grouped by manufacturer.
     *
     * @param  Builder<Device>  $query
     * @return array<int, array{name: string, count: int}>
     */
    public function getDeviceCountsByManufacturer(Builder $query): array
    {
        $devices = (clone $query)->get();

        $countsByManufacturer = $devices->groupBy(function ($device) {
            return $device->manufacturer ?? 'Unknown';
        })->map(function ($group, $manufacturer) {
            return [
                'name' => $manufacturer,
                'count' => $group->count(),
            ];
        })->values()->toArray();

        // Sort by count descending
        usort($countsByManufacturer, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $countsByManufacturer;
    }

    /**
     * Get aggregated asset metrics for the given filter scope.
     *
     * @return array{
     *     warrantyStatus: array{active: int, expiring_soon: int, expired: int, unknown: int},
     *     lifecycleDistribution: array<int, array{status: string, label: string, count: int, percentage: float}>,
     *     countsByType: array<int, array{name: string, count: int}>,
     *     countsByManufacturer: array<int, array{name: string, count: int}>
     * }
     */
    public function getAssetMetrics(
        ?int $datacenterId = null,
        ?int $roomId = null,
        ?int $deviceTypeId = null,
        ?string $lifecycleStatus = null,
        ?string $manufacturer = null,
        ?string $warrantyStart = null,
        ?string $warrantyEnd = null
    ): array {
        $query = $this->buildFilteredDeviceQuery(
            $datacenterId,
            $roomId,
            $deviceTypeId,
            $lifecycleStatus,
            $manufacturer,
            $warrantyStart,
            $warrantyEnd
        );

        return [
            'warrantyStatus' => $this->getWarrantyStatusCounts(clone $query),
            'lifecycleDistribution' => $this->getLifecycleDistribution(clone $query),
            'countsByType' => $this->getDeviceCountsByType(clone $query),
            'countsByManufacturer' => $this->getDeviceCountsByManufacturer(clone $query),
        ];
    }

    /**
     * Build a filtered device query based on provided filters.
     *
     * Includes devices with null rack_id (non-racked devices).
     *
     * @return Builder<Device>
     */
    public function buildFilteredDeviceQuery(
        ?int $datacenterId = null,
        ?int $roomId = null,
        ?int $deviceTypeId = null,
        ?string $lifecycleStatus = null,
        ?string $manufacturer = null,
        ?string $warrantyStart = null,
        ?string $warrantyEnd = null
    ): Builder {
        $query = Device::query();

        // Filter by datacenter (through rack -> row -> room -> datacenter chain)
        if ($datacenterId !== null) {
            $query->where(function (Builder $q) use ($datacenterId) {
                $q->whereHas('rack.row.room', function (Builder $subQuery) use ($datacenterId) {
                    $subQuery->where('datacenter_id', $datacenterId);
                });
            });
        }

        // Filter by room (through rack -> row -> room chain)
        if ($roomId !== null) {
            $query->where(function (Builder $q) use ($roomId) {
                $q->whereHas('rack.row', function (Builder $subQuery) use ($roomId) {
                    $subQuery->where('room_id', $roomId);
                });
            });
        }

        // Filter by device type
        if ($deviceTypeId !== null) {
            $query->where('device_type_id', $deviceTypeId);
        }

        // Filter by lifecycle status
        if ($lifecycleStatus !== null) {
            $query->where('lifecycle_status', $lifecycleStatus);
        }

        // Filter by manufacturer
        if ($manufacturer !== null) {
            $query->where('manufacturer', $manufacturer);
        }

        // Filter by warranty expiration date range
        if ($warrantyStart !== null && $warrantyEnd !== null) {
            $query->whereBetween('warranty_end_date', [$warrantyStart, $warrantyEnd]);
        } elseif ($warrantyStart !== null) {
            $query->where('warranty_end_date', '>=', $warrantyStart);
        } elseif ($warrantyEnd !== null) {
            $query->where('warranty_end_date', '<=', $warrantyEnd);
        }

        return $query;
    }
}
