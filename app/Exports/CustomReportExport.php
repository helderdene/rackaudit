<?php

namespace App\Exports;

use App\Enums\ReportType;
use App\Services\CustomReportBuilderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Export class for Custom Report Builder data.
 *
 * Exports dynamically configured report data based on selected
 * report type, columns, filters, sorting, and grouping.
 */
class CustomReportExport implements FromCollection, WithHeadings, WithTitle
{
    /**
     * Create a new export instance.
     *
     * @param  array<string>  $columns  Selected column keys
     * @param  array<string, mixed>  $filters  Filter values
     * @param  array<array{column: string, direction: string}>  $sort  Sort configuration
     * @param  string|null  $groupBy  Group by field
     */
    public function __construct(
        protected ReportType $reportType,
        protected array $columns,
        protected array $filters,
        protected array $sort,
        protected ?string $groupBy,
        protected CustomReportBuilderService $reportService,
    ) {}

    /**
     * Get the collection of data to export.
     *
     * @return Collection<int, array<mixed>>
     */
    public function collection(): Collection
    {
        $query = $this->reportService->buildQuery(
            $this->reportType,
            $this->columns,
            $this->filters,
            $this->sort,
            $this->groupBy
        );

        return $query->get()->map(fn ($record) => $this->transformRow($record));
    }

    /**
     * Get the column headings for the export.
     *
     * @return array<string>
     */
    public function headings(): array
    {
        $allFields = $this->reportService->getAvailableFieldsForType($this->reportType);
        $fieldMap = collect($allFields)->keyBy('key');

        return collect($this->columns)->map(function ($columnKey) use ($fieldMap) {
            $field = $fieldMap->get($columnKey);

            return $field?->displayName ?? $columnKey;
        })->toArray();
    }

    /**
     * Get the sheet title.
     */
    public function title(): string
    {
        return $this->reportType->label();
    }

    /**
     * Transform a single record to an array row matching the columns.
     *
     * @param  object  $record
     * @return array<mixed>
     */
    protected function transformRow($record): array
    {
        $row = [];

        // Get calculated field keys
        $calculatedFieldKeys = collect($this->reportService->getCalculatedFieldsForType($this->reportType))
            ->pluck('key')
            ->toArray();

        $selectedCalculatedFields = array_intersect($this->columns, $calculatedFieldKeys);

        // Get calculated values if any calculated fields are selected
        $calculatedValues = [];
        if (! empty($selectedCalculatedFields)) {
            $calculatedValues = $this->reportService->computeCalculatedFields(
                $this->reportType,
                $record,
                $selectedCalculatedFields
            );
        }

        // Build row in column order
        foreach ($this->columns as $columnKey) {
            if (isset($calculatedValues[$columnKey])) {
                $row[] = $calculatedValues[$columnKey];
            } else {
                $row[] = $this->getFieldValue($record, $columnKey);
            }
        }

        return $row;
    }

    /**
     * Get the value of a specific field from a record.
     */
    protected function getFieldValue(object $record, string $fieldKey): mixed
    {
        return match ($this->reportType) {
            ReportType::Capacity => $this->getCapacityFieldValue($record, $fieldKey),
            ReportType::Assets => $this->getAssetsFieldValue($record, $fieldKey),
            ReportType::Connections => $this->getConnectionsFieldValue($record, $fieldKey),
            ReportType::AuditHistory => $this->getAuditHistoryFieldValue($record, $fieldKey),
        };
    }

    /**
     * Get field value for Capacity report.
     */
    protected function getCapacityFieldValue(object $rack, string $fieldKey): mixed
    {
        return match ($fieldKey) {
            'rack_name' => $rack->name,
            'datacenter_name' => $rack->row?->room?->datacenter?->name,
            'room_name' => $rack->row?->room?->name,
            'row_name' => $rack->row?->name,
            'u_height' => $rack->u_height?->value ?? $rack->u_height,
            'used_u_space' => $rack->used_u_space ?? 0,
            'available_u_space' => $rack->available_u_space ?? 0,
            'utilization_percent' => $rack->utilization_percent ?? 0,
            'power_capacity_watts' => $rack->power_capacity_watts,
            'power_used_watts' => $rack->power_used_watts ?? 0,
            default => null,
        };
    }

    /**
     * Get field value for Assets report.
     */
    protected function getAssetsFieldValue(object $device, string $fieldKey): mixed
    {
        return match ($fieldKey) {
            'asset_tag' => $device->asset_tag,
            'name' => $device->name,
            'serial_number' => $device->serial_number,
            'manufacturer' => $device->manufacturer,
            'model' => $device->model,
            'device_type' => $device->deviceType?->name,
            'lifecycle_status' => $device->lifecycle_status?->label() ?? $device->lifecycle_status,
            'datacenter_name' => $device->rack?->row?->room?->datacenter?->name,
            'room_name' => $device->rack?->row?->room?->name,
            'rack_name' => $device->rack?->name,
            'start_u' => $device->start_u,
            'warranty_end_date' => $device->warranty_end_date?->format('Y-m-d'),
            default => null,
        };
    }

    /**
     * Get field value for Connections report.
     */
    protected function getConnectionsFieldValue(object $connection, string $fieldKey): mixed
    {
        return match ($fieldKey) {
            'connection_id' => $connection->id,
            'source_device' => $connection->sourcePort?->device?->name,
            'source_port' => $connection->sourcePort?->name,
            'destination_device' => $connection->destinationPort?->device?->name,
            'destination_port' => $connection->destinationPort?->name,
            'cable_type' => $connection->cable_type?->label() ?? $connection->cable_type,
            'connection_status' => $connection->status ?? 'active',
            default => null,
        };
    }

    /**
     * Get field value for Audit History report.
     */
    protected function getAuditHistoryFieldValue(object $audit, string $fieldKey): mixed
    {
        return match ($fieldKey) {
            'audit_id' => $audit->id,
            'audit_date' => $audit->updated_at?->format('Y-m-d'),
            'audit_type' => $audit->type?->label() ?? $audit->type,
            'datacenter_name' => $audit->datacenter?->name,
            'room_name' => $audit->room?->name,
            'finding_count' => $audit->findings?->count() ?? 0,
            'severity' => $audit->findings?->max('severity')?->label() ?? null,
            'resolution_date' => $audit->resolved_at?->format('Y-m-d'),
            default => null,
        };
    }
}
