<?php

namespace App\Services;

use App\Enums\BulkExportStatus;
use App\Enums\BulkImportEntityType;
use App\Jobs\ProcessBulkExportJob;
use App\Models\BulkExport;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Service for managing bulk exports.
 *
 * Handles export initiation, query building with hierarchical filters,
 * sync/async processing determination, and file generation.
 */
class BulkExportService
{
    /**
     * Threshold for async processing (rows >= this will be processed asynchronously).
     */
    protected int $asyncThreshold = 100;

    /**
     * Initiate an export for the given entity type and filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function initiateExport(
        User $user,
        BulkImportEntityType $entityType,
        string $format,
        array $filters = []
    ): BulkExport {
        // Build the query to count rows
        $query = $this->buildExportQuery($entityType, $filters);
        $rowCount = $query->count();

        // Generate file name
        $uuid = Str::uuid();
        $entityLabel = strtolower($entityType->label());
        $fileName = "{$entityLabel}_export_{$uuid}.{$format}";
        $filePath = "exports/{$fileName}";

        // Create the BulkExport record
        $bulkExport = BulkExport::create([
            'user_id' => $user->id,
            'entity_type' => $entityType,
            'format' => $format,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'status' => BulkExportStatus::Pending,
            'total_rows' => $rowCount,
            'processed_rows' => 0,
            'filters' => $filters,
        ]);

        // Decide sync vs async processing
        if ($rowCount >= $this->asyncThreshold) {
            // Dispatch job for async processing
            ProcessBulkExportJob::dispatch($bulkExport);
        } else {
            // Process synchronously
            $this->processSynchronously($bulkExport);
        }

        return $bulkExport;
    }

    /**
     * Build the export query for the given entity type with filters applied.
     *
     * @param  array<string, mixed>  $filters
     */
    public function buildExportQuery(BulkImportEntityType $entityType, array $filters): Builder
    {
        $query = match ($entityType) {
            BulkImportEntityType::Datacenter => Datacenter::query(),
            BulkImportEntityType::Room => Room::query()->with('datacenter'),
            BulkImportEntityType::Row => Row::query()->with('room.datacenter'),
            BulkImportEntityType::Rack => Rack::query()->with('row.room.datacenter'),
            BulkImportEntityType::Device => Device::query()->with(['rack.row.room.datacenter', 'deviceType']),
            BulkImportEntityType::Port => Port::query()->with('device.rack.row.room.datacenter'),
            BulkImportEntityType::Mixed => throw new \InvalidArgumentException('Mixed entity type is not supported for export.'),
        };

        return $this->applyHierarchicalFilters($query, $filters, $entityType);
    }

    /**
     * Apply hierarchical filters to the query.
     *
     * Filters cascade: filtering by datacenter includes all rooms/rows/racks/devices/ports within.
     *
     * @param  array<string, mixed>  $filters
     */
    public function applyHierarchicalFilters(Builder $query, array $filters, BulkImportEntityType $entityType): Builder
    {
        // If no filters, return the query as-is
        if (empty($filters)) {
            return $query;
        }

        $datacenterId = $filters['datacenter_id'] ?? null;
        $roomId = $filters['room_id'] ?? null;
        $rowId = $filters['row_id'] ?? null;
        $rackId = $filters['rack_id'] ?? null;

        return match ($entityType) {
            BulkImportEntityType::Datacenter => $this->filterDatacenters($query, $datacenterId),
            BulkImportEntityType::Room => $this->filterRooms($query, $datacenterId, $roomId),
            BulkImportEntityType::Row => $this->filterRows($query, $datacenterId, $roomId, $rowId),
            BulkImportEntityType::Rack => $this->filterRacks($query, $datacenterId, $roomId, $rowId, $rackId),
            BulkImportEntityType::Device => $this->filterDevices($query, $datacenterId, $roomId, $rowId, $rackId),
            BulkImportEntityType::Port => $this->filterPorts($query, $datacenterId, $roomId, $rowId, $rackId),
            BulkImportEntityType::Mixed => throw new \InvalidArgumentException('Mixed entity type is not supported for export.'),
        };
    }

    /**
     * Filter datacenters query.
     */
    protected function filterDatacenters(Builder $query, ?int $datacenterId): Builder
    {
        if ($datacenterId !== null) {
            $query->where('id', $datacenterId);
        }

        return $query;
    }

    /**
     * Filter rooms query by datacenter.
     */
    protected function filterRooms(Builder $query, ?int $datacenterId, ?int $roomId): Builder
    {
        if ($roomId !== null) {
            $query->where('id', $roomId);
        } elseif ($datacenterId !== null) {
            $query->where('datacenter_id', $datacenterId);
        }

        return $query;
    }

    /**
     * Filter rows query by datacenter and room.
     */
    protected function filterRows(Builder $query, ?int $datacenterId, ?int $roomId, ?int $rowId): Builder
    {
        if ($rowId !== null) {
            $query->where('id', $rowId);
        } elseif ($roomId !== null) {
            $query->where('room_id', $roomId);
        } elseif ($datacenterId !== null) {
            $query->whereHas('room', function (Builder $roomQuery) use ($datacenterId) {
                $roomQuery->where('datacenter_id', $datacenterId);
            });
        }

        return $query;
    }

    /**
     * Filter racks query by datacenter, room, and row.
     */
    protected function filterRacks(Builder $query, ?int $datacenterId, ?int $roomId, ?int $rowId, ?int $rackId): Builder
    {
        if ($rackId !== null) {
            $query->where('id', $rackId);
        } elseif ($rowId !== null) {
            $query->where('row_id', $rowId);
        } elseif ($roomId !== null) {
            $query->whereHas('row', function (Builder $rowQuery) use ($roomId) {
                $rowQuery->where('room_id', $roomId);
            });
        } elseif ($datacenterId !== null) {
            $query->whereHas('row.room', function (Builder $roomQuery) use ($datacenterId) {
                $roomQuery->where('datacenter_id', $datacenterId);
            });
        }

        return $query;
    }

    /**
     * Filter devices query by datacenter, room, row, and rack.
     */
    protected function filterDevices(Builder $query, ?int $datacenterId, ?int $roomId, ?int $rowId, ?int $rackId): Builder
    {
        if ($rackId !== null) {
            $query->where('rack_id', $rackId);
        } elseif ($rowId !== null) {
            $query->whereHas('rack', function (Builder $rackQuery) use ($rowId) {
                $rackQuery->where('row_id', $rowId);
            });
        } elseif ($roomId !== null) {
            $query->whereHas('rack.row', function (Builder $rowQuery) use ($roomId) {
                $rowQuery->where('room_id', $roomId);
            });
        } elseif ($datacenterId !== null) {
            $query->whereHas('rack.row.room', function (Builder $roomQuery) use ($datacenterId) {
                $roomQuery->where('datacenter_id', $datacenterId);
            });
        }

        return $query;
    }

    /**
     * Filter ports query by datacenter, room, row, and rack.
     */
    protected function filterPorts(Builder $query, ?int $datacenterId, ?int $roomId, ?int $rowId, ?int $rackId): Builder
    {
        if ($rackId !== null) {
            $query->whereHas('device', function (Builder $deviceQuery) use ($rackId) {
                $deviceQuery->where('rack_id', $rackId);
            });
        } elseif ($rowId !== null) {
            $query->whereHas('device.rack', function (Builder $rackQuery) use ($rowId) {
                $rackQuery->where('row_id', $rowId);
            });
        } elseif ($roomId !== null) {
            $query->whereHas('device.rack.row', function (Builder $rowQuery) use ($roomId) {
                $rowQuery->where('room_id', $roomId);
            });
        } elseif ($datacenterId !== null) {
            $query->whereHas('device.rack.row.room', function (Builder $roomQuery) use ($datacenterId) {
                $roomQuery->where('datacenter_id', $datacenterId);
            });
        }

        return $query;
    }

    /**
     * Process the export synchronously (for small datasets).
     */
    protected function processSynchronously(BulkExport $bulkExport): void
    {
        $job = new ProcessBulkExportJob($bulkExport);
        $job->handle();
    }

    /**
     * Get the async processing threshold.
     */
    public function getAsyncThreshold(): int
    {
        return $this->asyncThreshold;
    }
}
