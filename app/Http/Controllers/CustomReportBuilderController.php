<?php

namespace App\Http\Controllers;

use App\DTOs\ReportFieldConfiguration;
use App\Enums\ReportType;
use App\Exports\CustomReportExport;
use App\Http\Requests\CustomReportBuilderRequest;
use App\Models\Datacenter;
use App\Services\CustomReportBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for the Custom Report Builder feature.
 *
 * Provides report type selection, configuration, preview with pagination,
 * and export capabilities (PDF, CSV, JSON) for customizable reports.
 */
class CustomReportBuilderController extends Controller
{
    /**
     * Roles that have full access to all datacenters.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Number of rows per page in the preview table.
     */
    private const ROWS_PER_PAGE = 25;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected CustomReportBuilderService $reportService
    ) {}

    /**
     * Display the custom report builder page with report type options.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();

        // Get accessible datacenters based on user role
        $datacenterOptions = $this->getAccessibleDatacenters($user);

        // Get all report type options with labels and descriptions
        $reportTypes = $this->getReportTypeOptions();

        return Inertia::render('CustomReports/Builder', [
            'reportTypes' => $reportTypes,
            'datacenterOptions' => $datacenterOptions->values()->toArray(),
        ]);
    }

    /**
     * Return available fields and filters for a selected report type.
     */
    public function configure(Request $request): JsonResponse
    {
        $request->validate([
            'report_type' => ['required', 'string'],
        ]);

        $reportType = ReportType::tryFrom($request->input('report_type'));

        if ($reportType === null) {
            return response()->json([
                'error' => 'Invalid report type',
            ], 422);
        }

        $fields = $this->reportService->getAvailableFieldsForType($reportType);
        $filters = $this->reportService->getAvailableFiltersForType($reportType);
        $calculatedFields = $this->reportService->getCalculatedFieldsForType($reportType);

        return response()->json([
            'fields' => array_map(fn (ReportFieldConfiguration $field) => $field->toArray(), $fields),
            'filters' => $filters,
            'calculatedFields' => array_map(fn (ReportFieldConfiguration $field) => $field->toArray(), $calculatedFields),
        ]);
    }

    /**
     * Return paginated preview data based on report configuration.
     */
    public function preview(CustomReportBuilderRequest $request): InertiaResponse
    {
        $user = $request->user();
        $reportType = ReportType::from($request->input('report_type'));
        $columns = $request->input('columns', []);
        $filters = $request->input('filters', []);
        $sort = $request->input('sort', []);
        $groupBy = $request->input('group_by');
        $page = $request->input('page', 1);

        // Apply datacenter access restrictions
        $filters = $this->applyDatacenterRestrictions($user, $filters);

        // Build the query
        $query = $this->reportService->buildQuery($reportType, $columns, $filters, $sort, $groupBy);

        // Paginate results
        $paginator = $query->paginate(self::ROWS_PER_PAGE, ['*'], 'page', $page);

        // Transform data to include only selected columns and computed fields
        $data = $this->transformPreviewData($reportType, $paginator->items(), $columns);

        // Get column headers for display
        $columnHeaders = $this->getColumnHeaders($reportType, $columns);

        // Get accessible datacenters for filters
        $datacenterOptions = $this->getAccessibleDatacenters($user);

        return Inertia::render('CustomReports/Builder', [
            'reportTypes' => $this->getReportTypeOptions(),
            'datacenterOptions' => $datacenterOptions->values()->toArray(),
            'selectedReportType' => $reportType->value,
            'selectedColumns' => $columns,
            'selectedFilters' => $filters,
            'selectedSort' => $sort,
            'selectedGroupBy' => $groupBy,
            'previewData' => [
                'columns' => $columnHeaders,
                'data' => $data,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    /**
     * Export the custom report as PDF.
     */
    public function exportPdf(CustomReportBuilderRequest $request): StreamedResponse
    {
        $user = $request->user();
        $reportType = ReportType::from($request->input('report_type'));
        $columns = $request->input('columns', []);
        $filters = $request->input('filters', []);
        $sort = $request->input('sort', []);
        $groupBy = $request->input('group_by');

        // Apply datacenter access restrictions
        $filters = $this->applyDatacenterRestrictions($user, $filters);

        // Generate PDF
        $filePath = $this->reportService->generatePdfReport(
            $reportType,
            $columns,
            $filters,
            $sort,
            $groupBy,
            $user
        );

        $filename = basename($filePath);

        return Storage::disk('local')->download($filePath, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Export the custom report as CSV.
     */
    public function exportCsv(CustomReportBuilderRequest $request): BinaryFileResponse
    {
        $user = $request->user();
        $reportType = ReportType::from($request->input('report_type'));
        $columns = $request->input('columns', []);
        $filters = $request->input('filters', []);
        $sort = $request->input('sort', []);
        $groupBy = $request->input('group_by');

        // Apply datacenter access restrictions
        $filters = $this->applyDatacenterRestrictions($user, $filters);

        $timestamp = now()->format('Y-m-d-His');
        $filename = "custom-report-{$reportType->value}-{$timestamp}.csv";

        return Excel::download(
            new CustomReportExport($reportType, $columns, $filters, $sort, $groupBy, $this->reportService),
            $filename
        );
    }

    /**
     * Export the custom report as JSON.
     */
    public function exportJson(CustomReportBuilderRequest $request): JsonResponse
    {
        $user = $request->user();
        $reportType = ReportType::from($request->input('report_type'));
        $columns = $request->input('columns', []);
        $filters = $request->input('filters', []);
        $sort = $request->input('sort', []);
        $groupBy = $request->input('group_by');

        // Apply datacenter access restrictions
        $filters = $this->applyDatacenterRestrictions($user, $filters);

        // Build the query and get all results (for JSON export, no pagination)
        $query = $this->reportService->buildQuery($reportType, $columns, $filters, $sort, $groupBy);
        $results = $query->get();

        // Transform data to include only selected columns
        $data = $this->transformPreviewData($reportType, $results->all(), $columns);

        // Get column headers for display
        $columnHeaders = $this->getColumnHeaders($reportType, $columns);

        return response()->json([
            'report_type' => $reportType->value,
            'report_label' => $reportType->label(),
            'generated_at' => now()->toIso8601String(),
            'generated_by' => $user->name,
            'columns' => $columnHeaders,
            'filters' => $filters,
            'sort' => $sort,
            'group_by' => $groupBy,
            'data' => $data,
            'total' => count($data),
        ]);
    }

    /**
     * Get datacenters accessible by the user.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function getAccessibleDatacenters($user): Collection
    {
        $query = Datacenter::query()->orderBy('name');

        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            $assignedDatacenterIds = $user->datacenters()->pluck('datacenters.id');
            $query->whereIn('id', $assignedDatacenterIds);
        }

        return $query->get()->map(fn (Datacenter $datacenter) => [
            'id' => $datacenter->id,
            'name' => $datacenter->name,
        ]);
    }

    /**
     * Get report type options for the UI.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    private function getReportTypeOptions(): array
    {
        return array_map(
            fn (ReportType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
            ],
            ReportType::cases()
        );
    }

    /**
     * Apply datacenter restrictions based on user role.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function applyDatacenterRestrictions($user, array $filters): array
    {
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return $filters;
        }

        // Non-admin users can only see their assigned datacenters
        $assignedDatacenterIds = $user->datacenters()->pluck('datacenters.id')->toArray();

        // If a datacenter is specified in filters, validate it's accessible
        if (isset($filters['datacenter_id']) && ! in_array($filters['datacenter_id'], $assignedDatacenterIds)) {
            // Reset to null if not accessible
            $filters['datacenter_id'] = null;
        }

        // If no datacenter filter specified and user has restricted access,
        // we should filter to only their datacenters
        if (empty($filters['datacenter_id']) && ! empty($assignedDatacenterIds)) {
            $filters['accessible_datacenter_ids'] = $assignedDatacenterIds;
        }

        return $filters;
    }

    /**
     * Transform query results to include only selected columns and calculated fields.
     *
     * @param  array<object>  $records
     * @param  array<string>  $columns
     * @return array<int, array<string, mixed>>
     */
    private function transformPreviewData(ReportType $reportType, array $records, array $columns): array
    {
        $calculatedFieldKeys = collect($this->reportService->getCalculatedFieldsForType($reportType))
            ->pluck('key')
            ->toArray();

        $selectedCalculatedFields = array_intersect($columns, $calculatedFieldKeys);

        return collect($records)->map(function ($record) use ($reportType, $columns, $selectedCalculatedFields) {
            $row = [];

            // Extract standard fields based on report type
            $row = $this->extractStandardFields($reportType, $record, $columns);

            // Compute calculated fields
            if (! empty($selectedCalculatedFields)) {
                $calculatedValues = $this->reportService->computeCalculatedFields(
                    $reportType,
                    $record,
                    $selectedCalculatedFields
                );
                $row = array_merge($row, $calculatedValues);
            }

            return $row;
        })->toArray();
    }

    /**
     * Extract standard (non-calculated) fields from a record.
     *
     * @param  array<string>  $columns
     * @return array<string, mixed>
     */
    private function extractStandardFields(ReportType $reportType, object $record, array $columns): array
    {
        $row = [];

        foreach ($columns as $column) {
            $value = $this->getFieldValue($reportType, $record, $column);
            if ($value !== null || in_array($column, $columns)) {
                $row[$column] = $value;
            }
        }

        return $row;
    }

    /**
     * Get the value of a specific field from a record.
     */
    private function getFieldValue(ReportType $reportType, object $record, string $fieldKey): mixed
    {
        return match ($reportType) {
            ReportType::Capacity => $this->getCapacityFieldValue($record, $fieldKey),
            ReportType::Assets => $this->getAssetsFieldValue($record, $fieldKey),
            ReportType::Connections => $this->getConnectionsFieldValue($record, $fieldKey),
            ReportType::AuditHistory => $this->getAuditHistoryFieldValue($record, $fieldKey),
        };
    }

    /**
     * Get field value for Capacity report.
     */
    private function getCapacityFieldValue(object $rack, string $fieldKey): mixed
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
    private function getAssetsFieldValue(object $device, string $fieldKey): mixed
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
    private function getConnectionsFieldValue(object $connection, string $fieldKey): mixed
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
    private function getAuditHistoryFieldValue(object $audit, string $fieldKey): mixed
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
     * Get column headers for display in the preview table.
     *
     * @param  array<string>  $columns
     * @return array<int, array{key: string, label: string}>
     */
    private function getColumnHeaders(ReportType $reportType, array $columns): array
    {
        $allFields = $this->reportService->getAvailableFieldsForType($reportType);
        $fieldMap = collect($allFields)->keyBy('key');

        return collect($columns)->map(function ($columnKey) use ($fieldMap) {
            $field = $fieldMap->get($columnKey);

            return [
                'key' => $columnKey,
                'label' => $field?->displayName ?? $columnKey,
            ];
        })->toArray();
    }
}
