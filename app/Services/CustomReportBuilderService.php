<?php

namespace App\Services;

use App\DTOs\ReportFieldConfiguration;
use App\Enums\AuditStatus;
use App\Enums\CableType;
use App\Enums\DeviceLifecycleStatus;
use App\Enums\FindingSeverity;
use App\Enums\RackStatus;
use App\Enums\ReportType;
use App\Models\Audit;
use App\Models\Connection;
use App\Models\Device;
use App\Models\Rack;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * Service for building custom reports with configurable fields, filters, and sorting.
 *
 * Provides field configurations, filter options, and query building for each report type
 * (Capacity, Assets, Connections, Audit History) in the Custom Report Builder feature.
 */
class CustomReportBuilderService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        protected CapacityCalculationService $capacityService,
        protected AssetCalculationService $assetService,
        protected ConnectionCalculationService $connectionService,
    ) {}

    /**
     * Get available fields for a report type.
     *
     * @return array<ReportFieldConfiguration>
     */
    public function getAvailableFieldsForType(ReportType $type): array
    {
        $configurations = $this->getFieldConfigurationsForType($type);

        return array_map(
            fn (array $config) => ReportFieldConfiguration::fromArray($config),
            $configurations
        );
    }

    /**
     * Get available filters for a report type.
     *
     * @return array<string, array{type: string, label: string, options?: array}>
     */
    public function getAvailableFiltersForType(ReportType $type): array
    {
        $commonFilters = [
            'datacenter_id' => [
                'type' => 'select',
                'label' => 'Datacenter',
            ],
            'room_id' => [
                'type' => 'select',
                'label' => 'Room',
            ],
        ];

        return match ($type) {
            ReportType::Capacity => array_merge($commonFilters, [
                'row_id' => [
                    'type' => 'select',
                    'label' => 'Row',
                ],
                'utilization_threshold' => [
                    'type' => 'number',
                    'label' => 'Utilization Threshold (%)',
                ],
            ]),
            ReportType::Assets => array_merge($commonFilters, [
                'device_type_id' => [
                    'type' => 'select',
                    'label' => 'Device Type',
                ],
                'lifecycle_status' => [
                    'type' => 'select',
                    'label' => 'Lifecycle Status',
                    'options' => collect(DeviceLifecycleStatus::cases())->map(fn ($status) => [
                        'value' => $status->value,
                        'label' => $status->label(),
                    ])->toArray(),
                ],
                'manufacturer' => [
                    'type' => 'select',
                    'label' => 'Manufacturer',
                ],
                'warranty_start' => [
                    'type' => 'date',
                    'label' => 'Warranty Start Date',
                ],
                'warranty_end' => [
                    'type' => 'date',
                    'label' => 'Warranty End Date',
                ],
            ]),
            ReportType::Connections => array_merge($commonFilters, [
                'cable_type' => [
                    'type' => 'select',
                    'label' => 'Cable Type',
                    'options' => collect(CableType::cases())->map(fn ($type) => [
                        'value' => $type->value,
                        'label' => $type->label(),
                    ])->toArray(),
                ],
                'connection_status' => [
                    'type' => 'select',
                    'label' => 'Connection Status',
                    'options' => [
                        ['value' => 'active', 'label' => 'Active'],
                        ['value' => 'inactive', 'label' => 'Inactive'],
                    ],
                ],
            ]),
            ReportType::AuditHistory => array_merge($commonFilters, [
                'start_date' => [
                    'type' => 'date',
                    'label' => 'Start Date',
                ],
                'end_date' => [
                    'type' => 'date',
                    'label' => 'End Date',
                ],
                'audit_type' => [
                    'type' => 'select',
                    'label' => 'Audit Type',
                    'options' => [
                        ['value' => 'connection', 'label' => 'Connection'],
                        ['value' => 'inventory', 'label' => 'Inventory'],
                    ],
                ],
                'finding_severity' => [
                    'type' => 'select',
                    'label' => 'Finding Severity',
                    'options' => collect(FindingSeverity::cases())->map(fn ($severity) => [
                        'value' => $severity->value,
                        'label' => $severity->label(),
                    ])->toArray(),
                ],
            ]),
        };
    }

    /**
     * Get calculated fields for a report type.
     *
     * @return array<ReportFieldConfiguration>
     */
    public function getCalculatedFieldsForType(ReportType $type): array
    {
        $allFields = $this->getAvailableFieldsForType($type);

        return array_values(array_filter(
            $allFields,
            fn (ReportFieldConfiguration $field) => $field->isCalculated
        ));
    }

    /**
     * Build a query for the report data.
     *
     * @param  array<string>  $fields  Field keys to select
     * @param  array<string, mixed>  $filters  Filter values
     * @param  array<array{column: string, direction: string}>  $sort  Sort configuration
     */
    public function buildQuery(
        ReportType $type,
        array $fields,
        array $filters,
        array $sort,
        ?string $groupBy = null
    ): Builder {
        return match ($type) {
            ReportType::Capacity => $this->buildCapacityQuery($fields, $filters, $sort, $groupBy),
            ReportType::Assets => $this->buildAssetsQuery($fields, $filters, $sort, $groupBy),
            ReportType::Connections => $this->buildConnectionsQuery($fields, $filters, $sort, $groupBy),
            ReportType::AuditHistory => $this->buildAuditHistoryQuery($fields, $filters, $sort, $groupBy),
        };
    }

    /**
     * Generate a PDF report for the custom report.
     *
     * @param  array<string>  $columns  Selected column keys
     * @param  array<string, mixed>  $filters  Filter values
     * @param  array<array{column: string, direction: string}>  $sort  Sort configuration
     * @param  string|null  $groupBy  Group by field
     * @return string File path to the generated PDF
     */
    public function generatePdfReport(
        ReportType $type,
        array $columns,
        array $filters,
        array $sort,
        ?string $groupBy,
        User $generator
    ): string {
        // Build the query and get data
        $query = $this->buildQuery($type, $columns, $filters, $sort, $groupBy);
        $records = $query->get();

        // Transform data for the PDF
        $data = $this->transformDataForPdf($type, $records, $columns);

        // Get column headers
        $columnHeaders = $this->getColumnHeadersForPdf($type, $columns);

        // Build filter description
        $filterDescription = $this->buildFilterDescription($filters);

        // Prepare grouped data and subtotals if groupBy is specified
        $groupedData = [];
        $groupSubtotals = [];

        if ($groupBy !== null && ! empty($data)) {
            $groupedData = $this->groupDataForPdf($data, $groupBy);
            $groupSubtotals = $this->calculateGroupSubtotals($type, $groupedData, $columns);
        }

        $pdf = Pdf::loadView('pdf.custom-report', [
            'reportType' => $type->label(),
            'columns' => $columnHeaders,
            'data' => $data,
            'groupedData' => $groupedData,
            'groupSubtotals' => $groupSubtotals,
            'filters' => $filterDescription,
            'groupBy' => $groupBy,
            'generatedBy' => $generator->name,
            'generatedAt' => now(),
            'total' => $records->count(),
        ]);

        $pdf->setPaper('a4', 'landscape');

        return $this->storeReport($pdf, $type);
    }

    /**
     * Generate a CSV report for the custom report.
     *
     * Creates a CSV file with the selected columns and filtered data.
     * Uses the same buildQuery() and field configuration logic as PDF generation.
     *
     * @param  array<string>  $columns  Selected column keys
     * @param  array<string, mixed>  $filters  Filter values
     * @param  array<array{column: string, direction: string}>  $sort  Sort configuration
     * @param  string|null  $groupBy  Group by field (used for ordering, not grouping in CSV)
     * @return string File path to the generated CSV
     */
    public function generateCsvReport(
        ReportType $type,
        array $columns,
        array $filters,
        array $sort,
        ?string $groupBy,
        User $generator
    ): string {
        // Build the query and get data
        $query = $this->buildQuery($type, $columns, $filters, $sort, $groupBy);
        $records = $query->get();

        // Transform data using the same logic as PDF
        $data = $this->transformDataForPdf($type, $records, $columns);

        // Get column headers for the header row
        $columnHeaders = $this->getColumnHeadersForPdf($type, $columns);

        // Build CSV content
        $csvContent = $this->buildCsvContent($columnHeaders, $data, $columns);

        // Store the CSV file
        return $this->storeCsvReport($csvContent, $type);
    }

    /**
     * Build CSV content from column headers and data.
     *
     * @param  array<int, array{key: string, label: string}>  $columnHeaders
     * @param  array<int, array<string, mixed>>  $data
     * @param  array<string>  $columns
     */
    protected function buildCsvContent(array $columnHeaders, array $data, array $columns): string
    {
        $output = fopen('php://temp', 'r+');

        if ($output === false) {
            throw new \RuntimeException('Failed to create temporary file for CSV generation');
        }

        // Write header row
        $headerLabels = array_map(fn ($header) => $header['label'], $columnHeaders);
        fputcsv($output, $headerLabels);

        // Write data rows
        foreach ($data as $row) {
            $rowData = [];
            foreach ($columns as $column) {
                $value = $row[$column] ?? '';
                // Convert null to empty string and ensure proper string conversion
                $rowData[] = $value === null ? '' : (is_scalar($value) ? (string) $value : json_encode($value));
            }
            fputcsv($output, $rowData);
        }

        // Get content and close
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content !== false ? $content : '';
    }

    /**
     * Store the generated CSV report to the filesystem.
     */
    protected function storeCsvReport(string $content, ReportType $type): string
    {
        $timestamp = now()->format('YmdHis');
        $filename = "custom-report-{$type->value}-{$timestamp}.csv";
        $filePath = "reports/custom/{$filename}";

        Storage::disk('local')->put($filePath, $content);

        return $filePath;
    }

    /**
     * Group data by a specific field for PDF rendering.
     *
     * @param  array<int, array<string, mixed>>  $data
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function groupDataForPdf(array $data, string $groupBy): array
    {
        $grouped = [];

        foreach ($data as $row) {
            $groupKey = $row[$groupBy] ?? 'Unassigned';
            $groupKeyString = is_string($groupKey) || is_numeric($groupKey) ? (string) $groupKey : 'Unassigned';

            if (! isset($grouped[$groupKeyString])) {
                $grouped[$groupKeyString] = [];
            }

            $grouped[$groupKeyString][] = $row;
        }

        // Sort by group name
        ksort($grouped);

        return $grouped;
    }

    /**
     * Calculate subtotals for each group.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $groupedData
     * @param  array<string>  $columns
     * @return array<string, array<string, mixed>>
     */
    protected function calculateGroupSubtotals(ReportType $type, array $groupedData, array $columns): array
    {
        $subtotals = [];

        // Get numeric column configurations
        $allFields = $this->getAvailableFieldsForType($type);
        $numericFields = collect($allFields)
            ->filter(fn (ReportFieldConfiguration $field) => in_array($field->dataType, ['integer', 'float']))
            ->pluck('key')
            ->toArray();

        // Filter to only selected numeric columns
        $numericColumnsToSum = array_intersect($columns, $numericFields);

        foreach ($groupedData as $groupName => $groupItems) {
            $subtotals[$groupName] = [];

            foreach ($numericColumnsToSum as $column) {
                $sum = 0;
                $count = 0;

                foreach ($groupItems as $row) {
                    $value = $row[$column] ?? null;
                    if (is_numeric($value)) {
                        $sum += $value;
                        $count++;
                    }
                }

                // Store the sum for this column
                if ($count > 0) {
                    $subtotals[$groupName][$column] = round($sum, 2);
                }
            }
        }

        return $subtotals;
    }

    /**
     * Get field configurations for Capacity reports.
     *
     * @return array<array{key: string, display_name: string, category: string, is_calculated?: bool, data_type?: string}>
     */
    protected function getCapacityFieldConfigurations(): array
    {
        return [
            ['key' => 'rack_name', 'display_name' => 'Rack Name', 'category' => 'Location', 'data_type' => 'string'],
            ['key' => 'datacenter_name', 'display_name' => 'Datacenter', 'category' => 'Location', 'data_type' => 'string'],
            ['key' => 'room_name', 'display_name' => 'Room', 'category' => 'Location', 'data_type' => 'string'],
            ['key' => 'row_name', 'display_name' => 'Row', 'category' => 'Location', 'data_type' => 'string'],
            ['key' => 'u_height', 'display_name' => 'Total U Height', 'category' => 'Capacity', 'data_type' => 'integer'],
            ['key' => 'used_u_space', 'display_name' => 'Used U Space', 'category' => 'Capacity', 'data_type' => 'integer'],
            ['key' => 'available_u_space', 'display_name' => 'Available U Space', 'category' => 'Capacity', 'data_type' => 'integer'],
            ['key' => 'utilization_percent', 'display_name' => 'Utilization %', 'category' => 'Capacity', 'data_type' => 'float'],
            ['key' => 'power_capacity_watts', 'display_name' => 'Power Capacity (W)', 'category' => 'Power', 'data_type' => 'integer'],
            ['key' => 'power_used_watts', 'display_name' => 'Power Used (W)', 'category' => 'Power', 'data_type' => 'integer'],
            ['key' => 'devices_per_rack', 'display_name' => 'Devices per Rack', 'category' => 'Calculated', 'is_calculated' => true, 'data_type' => 'integer'],
        ];
    }

    /**
     * Get field configurations for Assets reports.
     *
     * @return array<array{key: string, display_name: string, category: string, is_calculated?: bool, data_type?: string}>
     */
    protected function getAssetsFieldConfigurations(): array
    {
        return [
            ['key' => 'asset_tag', 'display_name' => 'Asset Tag', 'category' => 'Identification', 'data_type' => 'string'],
            ['key' => 'name', 'display_name' => 'Device Name', 'category' => 'Identification', 'data_type' => 'string'],
            ['key' => 'serial_number', 'display_name' => 'Serial Number', 'category' => 'Identification', 'data_type' => 'string'],
            ['key' => 'manufacturer', 'display_name' => 'Manufacturer', 'category' => 'Details', 'data_type' => 'string'],
            ['key' => 'model', 'display_name' => 'Model', 'category' => 'Details', 'data_type' => 'string'],
            ['key' => 'device_type', 'display_name' => 'Device Type', 'category' => 'Details', 'data_type' => 'string'],
            ['key' => 'lifecycle_status', 'display_name' => 'Lifecycle Status', 'category' => 'Status', 'data_type' => 'string'],
            ['key' => 'datacenter_name', 'display_name' => 'Datacenter', 'category' => 'Location', 'data_type' => 'string'],
            ['key' => 'room_name', 'display_name' => 'Room', 'category' => 'Location', 'data_type' => 'string'],
            ['key' => 'rack_name', 'display_name' => 'Rack', 'category' => 'Location', 'data_type' => 'string'],
            ['key' => 'start_u', 'display_name' => 'Start U Position', 'category' => 'Location', 'data_type' => 'integer'],
            ['key' => 'warranty_end_date', 'display_name' => 'Warranty End Date', 'category' => 'Warranty', 'data_type' => 'date'],
            ['key' => 'days_until_warranty_expiration', 'display_name' => 'Days Until Warranty Expiration', 'category' => 'Calculated', 'is_calculated' => true, 'data_type' => 'integer'],
            ['key' => 'age_in_years', 'display_name' => 'Age (Years)', 'category' => 'Calculated', 'is_calculated' => true, 'data_type' => 'float'],
        ];
    }

    /**
     * Get field configurations for Connections reports.
     *
     * @return array<array{key: string, display_name: string, category: string, is_calculated?: bool, data_type?: string}>
     */
    protected function getConnectionsFieldConfigurations(): array
    {
        return [
            ['key' => 'connection_id', 'display_name' => 'Connection ID', 'category' => 'Identification', 'data_type' => 'integer'],
            ['key' => 'source_device', 'display_name' => 'Source Device', 'category' => 'Source', 'data_type' => 'string'],
            ['key' => 'source_port', 'display_name' => 'Source Port', 'category' => 'Source', 'data_type' => 'string'],
            ['key' => 'destination_device', 'display_name' => 'Destination Device', 'category' => 'Destination', 'data_type' => 'string'],
            ['key' => 'destination_port', 'display_name' => 'Destination Port', 'category' => 'Destination', 'data_type' => 'string'],
            ['key' => 'cable_type', 'display_name' => 'Cable Type', 'category' => 'Cable', 'data_type' => 'string'],
            ['key' => 'connection_status', 'display_name' => 'Connection Status', 'category' => 'Status', 'data_type' => 'string'],
            ['key' => 'port_utilization_percentage', 'display_name' => 'Port Utilization %', 'category' => 'Calculated', 'is_calculated' => true, 'data_type' => 'float'],
        ];
    }

    /**
     * Get field configurations for Audit History reports.
     *
     * @return array<array{key: string, display_name: string, category: string, is_calculated?: bool, data_type?: string}>
     */
    protected function getAuditHistoryFieldConfigurations(): array
    {
        return [
            ['key' => 'audit_id', 'display_name' => 'Audit ID', 'category' => 'Identification', 'data_type' => 'integer'],
            ['key' => 'audit_date', 'display_name' => 'Audit Date', 'category' => 'Dates', 'data_type' => 'date'],
            ['key' => 'audit_type', 'display_name' => 'Audit Type', 'category' => 'Details', 'data_type' => 'string'],
            ['key' => 'datacenter_name', 'display_name' => 'Datacenter', 'category' => 'Location', 'data_type' => 'string'],
            ['key' => 'room_name', 'display_name' => 'Room', 'category' => 'Location', 'data_type' => 'string'],
            ['key' => 'finding_count', 'display_name' => 'Finding Count', 'category' => 'Findings', 'data_type' => 'integer'],
            ['key' => 'severity', 'display_name' => 'Severity', 'category' => 'Findings', 'data_type' => 'string'],
            ['key' => 'resolution_date', 'display_name' => 'Resolution Date', 'category' => 'Dates', 'data_type' => 'date'],
            ['key' => 'days_to_resolution', 'display_name' => 'Days to Resolution', 'category' => 'Calculated', 'is_calculated' => true, 'data_type' => 'integer'],
            ['key' => 'finding_resolution_rate', 'display_name' => 'Finding Resolution Rate %', 'category' => 'Calculated', 'is_calculated' => true, 'data_type' => 'float'],
        ];
    }

    /**
     * Get field configurations for a specific report type.
     *
     * @return array<array{key: string, display_name: string, category: string, is_calculated?: bool, data_type?: string}>
     */
    protected function getFieldConfigurationsForType(ReportType $type): array
    {
        return match ($type) {
            ReportType::Capacity => $this->getCapacityFieldConfigurations(),
            ReportType::Assets => $this->getAssetsFieldConfigurations(),
            ReportType::Connections => $this->getConnectionsFieldConfigurations(),
            ReportType::AuditHistory => $this->getAuditHistoryFieldConfigurations(),
        };
    }

    /**
     * Build query for Capacity reports.
     *
     * @param  array<string>  $fields
     * @param  array<string, mixed>  $filters
     * @param  array<array{column: string, direction: string}>  $sort
     */
    protected function buildCapacityQuery(array $fields, array $filters, array $sort, ?string $groupBy): Builder
    {
        $query = Rack::query()
            ->where('status', RackStatus::Active)
            ->with(['row.room.datacenter', 'devices']);

        // Apply location filters
        if (! empty($filters['row_id'])) {
            $query->where('row_id', $filters['row_id']);
        } elseif (! empty($filters['room_id'])) {
            $query->whereHas('row', fn (Builder $q) => $q->where('room_id', $filters['room_id']));
        } elseif (! empty($filters['datacenter_id'])) {
            $query->whereHas('row.room', fn (Builder $q) => $q->where('datacenter_id', $filters['datacenter_id']));
        }

        // Apply sorting
        foreach ($sort as $sortItem) {
            $column = $this->mapCapacityColumnToDatabase($sortItem['column']);
            if ($column !== null) {
                $query->orderBy($column, $sortItem['direction']);
            }
        }

        return $query;
    }

    /**
     * Build query for Assets reports.
     *
     * @param  array<string>  $fields
     * @param  array<string, mixed>  $filters
     * @param  array<array{column: string, direction: string}>  $sort
     */
    protected function buildAssetsQuery(array $fields, array $filters, array $sort, ?string $groupBy): Builder
    {
        $query = Device::query()
            ->with(['deviceType', 'rack.row.room.datacenter']);

        // Apply location filters
        if (! empty($filters['datacenter_id'])) {
            $query->whereHas('rack.row.room', fn (Builder $q) => $q->where('datacenter_id', $filters['datacenter_id']));
        }

        if (! empty($filters['room_id'])) {
            $query->whereHas('rack.row', fn (Builder $q) => $q->where('room_id', $filters['room_id']));
        }

        // Apply type-specific filters
        if (! empty($filters['device_type_id'])) {
            $query->where('device_type_id', $filters['device_type_id']);
        }

        if (! empty($filters['lifecycle_status'])) {
            $query->where('lifecycle_status', $filters['lifecycle_status']);
        }

        if (! empty($filters['manufacturer'])) {
            $query->where('manufacturer', $filters['manufacturer']);
        }

        // Apply warranty date range
        if (! empty($filters['warranty_start']) && ! empty($filters['warranty_end'])) {
            $query->whereBetween('warranty_end_date', [$filters['warranty_start'], $filters['warranty_end']]);
        } elseif (! empty($filters['warranty_start'])) {
            $query->where('warranty_end_date', '>=', $filters['warranty_start']);
        } elseif (! empty($filters['warranty_end'])) {
            $query->where('warranty_end_date', '<=', $filters['warranty_end']);
        }

        // Apply sorting
        foreach ($sort as $sortItem) {
            $column = $this->mapAssetsColumnToDatabase($sortItem['column']);
            if ($column !== null) {
                $query->orderBy($column, $sortItem['direction']);
            }
        }

        return $query;
    }

    /**
     * Build query for Connections reports.
     *
     * @param  array<string>  $fields
     * @param  array<string, mixed>  $filters
     * @param  array<array{column: string, direction: string}>  $sort
     */
    protected function buildConnectionsQuery(array $fields, array $filters, array $sort, ?string $groupBy): Builder
    {
        $query = Connection::query()
            ->with(['sourcePort.device', 'destinationPort.device']);

        // Apply location filters
        if (! empty($filters['datacenter_id'])) {
            $query->whereHas('sourcePort.device.rack.row.room', fn (Builder $q) => $q->where('datacenter_id', $filters['datacenter_id']));
        }

        if (! empty($filters['room_id'])) {
            $query->whereHas('sourcePort.device.rack.row', fn (Builder $q) => $q->where('room_id', $filters['room_id']));
        }

        // Apply type-specific filters
        if (! empty($filters['cable_type'])) {
            $query->where('cable_type', $filters['cable_type']);
        }

        // Apply sorting
        foreach ($sort as $sortItem) {
            $column = $this->mapConnectionsColumnToDatabase($sortItem['column']);
            if ($column !== null) {
                $query->orderBy($column, $sortItem['direction']);
            }
        }

        return $query;
    }

    /**
     * Build query for Audit History reports.
     *
     * @param  array<string>  $fields
     * @param  array<string, mixed>  $filters
     * @param  array<array{column: string, direction: string}>  $sort
     */
    protected function buildAuditHistoryQuery(array $fields, array $filters, array $sort, ?string $groupBy): Builder
    {
        $query = Audit::query()
            ->where('status', AuditStatus::Completed)
            ->with(['datacenter', 'room', 'findings']);

        // Apply location filters
        if (! empty($filters['datacenter_id'])) {
            $query->where('datacenter_id', $filters['datacenter_id']);
        }

        if (! empty($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        // Apply date range filters
        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $query->whereBetween('updated_at', [$filters['start_date'], $filters['end_date']]);
        } elseif (! empty($filters['start_date'])) {
            $query->where('updated_at', '>=', $filters['start_date']);
        } elseif (! empty($filters['end_date'])) {
            $query->where('updated_at', '<=', $filters['end_date']);
        }

        // Apply type-specific filters
        if (! empty($filters['audit_type'])) {
            $query->where('type', $filters['audit_type']);
        }

        // Apply sorting
        foreach ($sort as $sortItem) {
            $column = $this->mapAuditHistoryColumnToDatabase($sortItem['column']);
            if ($column !== null) {
                $query->orderBy($column, $sortItem['direction']);
            }
        }

        return $query;
    }

    /**
     * Map Capacity report field keys to database columns.
     */
    protected function mapCapacityColumnToDatabase(string $fieldKey): ?string
    {
        return match ($fieldKey) {
            'rack_name' => 'name',
            'u_height' => 'u_height',
            'power_capacity_watts' => 'power_capacity_watts',
            default => null,
        };
    }

    /**
     * Map Assets report field keys to database columns.
     */
    protected function mapAssetsColumnToDatabase(string $fieldKey): ?string
    {
        return match ($fieldKey) {
            'asset_tag' => 'asset_tag',
            'name' => 'name',
            'serial_number' => 'serial_number',
            'manufacturer' => 'manufacturer',
            'model' => 'model',
            'lifecycle_status' => 'lifecycle_status',
            'start_u' => 'start_u',
            'warranty_end_date' => 'warranty_end_date',
            default => null,
        };
    }

    /**
     * Map Connections report field keys to database columns.
     */
    protected function mapConnectionsColumnToDatabase(string $fieldKey): ?string
    {
        return match ($fieldKey) {
            'connection_id' => 'id',
            'cable_type' => 'cable_type',
            default => null,
        };
    }

    /**
     * Map Audit History report field keys to database columns.
     */
    protected function mapAuditHistoryColumnToDatabase(string $fieldKey): ?string
    {
        return match ($fieldKey) {
            'audit_id' => 'id',
            'audit_date' => 'updated_at',
            'audit_type' => 'type',
            default => null,
        };
    }

    /**
     * Compute calculated field values for a record.
     *
     * @param  object  $record  The model record
     * @param  array<string>  $calculatedFields  Keys of calculated fields to compute
     * @return array<string, mixed> Calculated field values keyed by field key
     */
    public function computeCalculatedFields(ReportType $type, object $record, array $calculatedFields): array
    {
        return match ($type) {
            ReportType::Capacity => $this->computeCapacityCalculatedFields($record, $calculatedFields),
            ReportType::Assets => $this->computeAssetsCalculatedFields($record, $calculatedFields),
            ReportType::Connections => $this->computeConnectionsCalculatedFields($record, $calculatedFields),
            ReportType::AuditHistory => $this->computeAuditHistoryCalculatedFields($record, $calculatedFields),
        };
    }

    /**
     * Compute calculated fields for Capacity report records.
     *
     * @param  Rack  $rack
     * @param  array<string>  $calculatedFields
     * @return array<string, mixed>
     */
    protected function computeCapacityCalculatedFields(object $rack, array $calculatedFields): array
    {
        $result = [];

        if (in_array('devices_per_rack', $calculatedFields)) {
            $result['devices_per_rack'] = $rack->devices->count();
        }

        return $result;
    }

    /**
     * Compute calculated fields for Assets report records.
     *
     * @param  Device  $device
     * @param  array<string>  $calculatedFields
     * @return array<string, mixed>
     */
    protected function computeAssetsCalculatedFields(object $device, array $calculatedFields): array
    {
        $result = [];

        if (in_array('days_until_warranty_expiration', $calculatedFields)) {
            if ($device->warranty_end_date !== null) {
                // Calculate days from now until warranty expiration (positive if in future)
                $result['days_until_warranty_expiration'] = (int) now()->startOfDay()->diffInDays($device->warranty_end_date->startOfDay(), false);
            } else {
                $result['days_until_warranty_expiration'] = null;
            }
        }

        if (in_array('age_in_years', $calculatedFields)) {
            if ($device->purchase_date !== null) {
                // Calculate age as years since purchase (absolute value)
                $result['age_in_years'] = round($device->purchase_date->diffInYears(now()), 1);
            } else {
                $result['age_in_years'] = null;
            }
        }

        return $result;
    }

    /**
     * Compute calculated fields for Connections report records.
     *
     * @param  Connection  $connection
     * @param  array<string>  $calculatedFields
     * @return array<string, mixed>
     */
    protected function computeConnectionsCalculatedFields(object $connection, array $calculatedFields): array
    {
        $result = [];

        if (in_array('port_utilization_percentage', $calculatedFields)) {
            // Port utilization is computed at the device level, not connection level
            // For individual connection records, we report based on source port device
            $device = $connection->sourcePort?->device;
            if ($device !== null) {
                $totalPorts = $device->ports()->count();
                $connectedPorts = $device->ports()
                    ->whereHas('connectionAsSource')
                    ->orWhereHas('connectionAsDestination')
                    ->count();

                $result['port_utilization_percentage'] = $totalPorts > 0
                    ? round(($connectedPorts / $totalPorts) * 100, 1)
                    : 0.0;
            } else {
                $result['port_utilization_percentage'] = null;
            }
        }

        return $result;
    }

    /**
     * Compute calculated fields for Audit History report records.
     *
     * @param  Audit  $audit
     * @param  array<string>  $calculatedFields
     * @return array<string, mixed>
     */
    protected function computeAuditHistoryCalculatedFields(object $audit, array $calculatedFields): array
    {
        $result = [];

        if (in_array('days_to_resolution', $calculatedFields)) {
            // Average days to resolution for all findings in this audit
            $resolvedFindings = $audit->findings->filter(fn ($f) => $f->resolved_at !== null);
            if ($resolvedFindings->isNotEmpty()) {
                $totalMinutes = $resolvedFindings->sum(fn ($f) => $f->getTotalResolutionTime() ?? 0);
                $avgMinutes = $totalMinutes / $resolvedFindings->count();
                $result['days_to_resolution'] = (int) round($avgMinutes / (60 * 24));
            } else {
                $result['days_to_resolution'] = null;
            }
        }

        if (in_array('finding_resolution_rate', $calculatedFields)) {
            $totalFindings = $audit->findings->count();
            $resolvedFindings = $audit->findings->filter(fn ($f) => $f->resolved_at !== null)->count();

            $result['finding_resolution_rate'] = $totalFindings > 0
                ? round(($resolvedFindings / $totalFindings) * 100, 1)
                : 0.0;
        }

        return $result;
    }

    /**
     * Transform data for PDF rendering.
     *
     * @param  \Illuminate\Support\Collection  $records
     * @param  array<string>  $columns
     * @return array<int, array<string, mixed>>
     */
    protected function transformDataForPdf(ReportType $type, $records, array $columns): array
    {
        $calculatedFieldKeys = collect($this->getCalculatedFieldsForType($type))
            ->pluck('key')
            ->toArray();

        $selectedCalculatedFields = array_intersect($columns, $calculatedFieldKeys);

        return $records->map(function ($record) use ($type, $columns, $selectedCalculatedFields) {
            $row = [];

            foreach ($columns as $column) {
                if (in_array($column, $selectedCalculatedFields)) {
                    $calculatedValues = $this->computeCalculatedFields($type, $record, [$column]);
                    $row[$column] = $calculatedValues[$column] ?? null;
                } else {
                    $row[$column] = $this->getFieldValueForPdf($type, $record, $column);
                }
            }

            return $row;
        })->toArray();
    }

    /**
     * Get field value for PDF rendering.
     */
    protected function getFieldValueForPdf(ReportType $type, object $record, string $fieldKey): mixed
    {
        return match ($type) {
            ReportType::Capacity => $this->getCapacityFieldValueForPdf($record, $fieldKey),
            ReportType::Assets => $this->getAssetsFieldValueForPdf($record, $fieldKey),
            ReportType::Connections => $this->getConnectionsFieldValueForPdf($record, $fieldKey),
            ReportType::AuditHistory => $this->getAuditHistoryFieldValueForPdf($record, $fieldKey),
        };
    }

    /**
     * Get Capacity field value for PDF.
     */
    protected function getCapacityFieldValueForPdf(object $rack, string $fieldKey): mixed
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
     * Get Assets field value for PDF.
     */
    protected function getAssetsFieldValueForPdf(object $device, string $fieldKey): mixed
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
     * Get Connections field value for PDF.
     */
    protected function getConnectionsFieldValueForPdf(object $connection, string $fieldKey): mixed
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
     * Get Audit History field value for PDF.
     */
    protected function getAuditHistoryFieldValueForPdf(object $audit, string $fieldKey): mixed
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

    /**
     * Get column headers for PDF rendering.
     *
     * @param  array<string>  $columns
     * @return array<int, array{key: string, label: string}>
     */
    protected function getColumnHeadersForPdf(ReportType $type, array $columns): array
    {
        $allFields = $this->getAvailableFieldsForType($type);
        $fieldMap = collect($allFields)->keyBy('key');

        return collect($columns)->map(function ($columnKey) use ($fieldMap) {
            $field = $fieldMap->get($columnKey);

            return [
                'key' => $columnKey,
                'label' => $field?->displayName ?? $columnKey,
            ];
        })->toArray();
    }

    /**
     * Build a human-readable description of applied filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function buildFilterDescription(array $filters): string
    {
        $parts = [];

        if (! empty($filters['datacenter_id'])) {
            $parts[] = 'Datacenter: '.$filters['datacenter_id'];
        }

        if (! empty($filters['room_id'])) {
            $parts[] = 'Room: '.$filters['room_id'];
        }

        if (! empty($filters['row_id'])) {
            $parts[] = 'Row: '.$filters['row_id'];
        }

        return ! empty($parts) ? implode(', ', $parts) : 'No filters applied';
    }

    /**
     * Store the generated PDF report to the filesystem.
     */
    protected function storeReport(\Barryvdh\DomPDF\PDF $pdf, ReportType $type): string
    {
        $timestamp = now()->format('YmdHis');
        $filename = "custom-report-{$type->value}-{$timestamp}.pdf";
        $filePath = "reports/custom/{$filename}";

        Storage::disk('local')->put($filePath, $pdf->output());

        return $filePath;
    }
}
