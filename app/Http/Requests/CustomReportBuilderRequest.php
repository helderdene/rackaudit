<?php

namespace App\Http\Requests;

use App\Enums\ReportType;
use App\Services\CustomReportBuilderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for validating custom report builder configuration.
 *
 * Validates report type, column selection, filters, sorting, grouping,
 * and pagination parameters for the Custom Report Builder feature.
 */
class CustomReportBuilderRequest extends FormRequest
{
    /**
     * Roles that can access custom reports.
     *
     * @var array<string>
     */
    private const AUTHORIZED_ROLES = [
        'Administrator',
        'IT Manager',
        'Auditor',
    ];

    /**
     * Determine if the user is authorized to make this request.
     * Only Administrators, IT Managers, and Auditors can access custom reports.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasAnyRole(self::AUTHORIZED_ROLES);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reportType = $this->getReportType();
        $validColumns = $this->getValidColumnsForReportType($reportType);

        return [
            // Report type is required and must be a valid enum value
            'report_type' => ['required', 'string', Rule::enum(ReportType::class)],

            // Columns are required, must be an array with at least 1 item
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['required', 'string', Rule::in($validColumns)],

            // Filters are optional, must be an array
            'filters' => ['nullable', 'array'],
            'filters.datacenter_id' => ['nullable', 'integer', 'exists:datacenters,id'],
            'filters.room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'filters.row_id' => ['nullable', 'integer', 'exists:rows,id'],
            'filters.device_type_id' => ['nullable', 'integer', 'exists:device_types,id'],
            'filters.lifecycle_status' => ['nullable', 'string'],
            'filters.manufacturer' => ['nullable', 'string', 'max:255'],
            'filters.warranty_start' => ['nullable', 'date'],
            'filters.warranty_end' => ['nullable', 'date', 'after_or_equal:filters.warranty_start'],
            'filters.utilization_threshold' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'filters.cable_type' => ['nullable', 'string'],
            'filters.connection_status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'filters.start_date' => ['nullable', 'date'],
            'filters.end_date' => ['nullable', 'date', 'after_or_equal:filters.start_date'],
            'filters.audit_type' => ['nullable', 'string', Rule::in(['connection', 'inventory'])],
            'filters.finding_severity' => ['nullable', 'string'],

            // Sort is optional, array with max 3 sort columns
            'sort' => ['nullable', 'array', 'max:3'],
            'sort.*.column' => ['required_with:sort', 'string', Rule::in($validColumns)],
            'sort.*.direction' => ['required_with:sort', 'string', Rule::in(['asc', 'desc'])],

            // Group by is optional, must be a valid field for the report type
            'group_by' => ['nullable', 'string', Rule::in($validColumns)],

            // Page is optional, must be positive integer
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'report_type.required' => 'Please select a report type.',
            'report_type.Illuminate\Validation\Rules\Enum' => 'The selected report type is invalid.',
            'columns.required' => 'Please select at least one column for your report.',
            'columns.array' => 'The columns selection must be a list.',
            'columns.min' => 'Please select at least one column for your report.',
            'columns.*.in' => 'One or more selected columns are not valid for this report type.',
            'filters.array' => 'The filters must be provided as an object.',
            'filters.datacenter_id.exists' => 'The selected datacenter does not exist.',
            'filters.room_id.exists' => 'The selected room does not exist.',
            'filters.row_id.exists' => 'The selected row does not exist.',
            'filters.device_type_id.exists' => 'The selected device type does not exist.',
            'filters.warranty_end.after_or_equal' => 'The warranty end date must be after or equal to the warranty start date.',
            'filters.utilization_threshold.numeric' => 'The utilization threshold must be a number.',
            'filters.utilization_threshold.min' => 'The utilization threshold must be at least 0.',
            'filters.utilization_threshold.max' => 'The utilization threshold cannot exceed 100.',
            'filters.end_date.after_or_equal' => 'The end date must be after or equal to the start date.',
            'filters.audit_type.in' => 'The audit type must be either connection or inventory.',
            'filters.connection_status.in' => 'The connection status must be either active or inactive.',
            'sort.array' => 'The sort configuration must be a list.',
            'sort.max' => 'You can sort by a maximum of 3 columns.',
            'sort.*.column.required_with' => 'Each sort item must specify a column.',
            'sort.*.column.in' => 'One or more sort columns are not valid for this report type.',
            'sort.*.direction.required_with' => 'Each sort item must specify a direction.',
            'sort.*.direction.in' => 'The sort direction must be either asc or desc.',
            'group_by.in' => 'The selected grouping field is not valid for this report type.',
            'page.integer' => 'The page number must be an integer.',
            'page.min' => 'The page number must be at least 1.',
        ];
    }

    /**
     * Get the report type from the request.
     */
    protected function getReportType(): ?ReportType
    {
        $value = $this->input('report_type');
        if ($value === null) {
            return null;
        }

        return ReportType::tryFrom($value);
    }

    /**
     * Get valid column keys for the specified report type.
     *
     * @return array<string>
     */
    protected function getValidColumnsForReportType(?ReportType $reportType): array
    {
        if ($reportType === null) {
            // Return all possible columns from all report types when type is unknown
            return $this->getAllPossibleColumns();
        }

        $service = app(CustomReportBuilderService::class);
        $fields = $service->getAvailableFieldsForType($reportType);

        return collect($fields)->pluck('key')->toArray();
    }

    /**
     * Get all possible columns across all report types.
     *
     * @return array<string>
     */
    protected function getAllPossibleColumns(): array
    {
        $service = app(CustomReportBuilderService::class);
        $allColumns = [];

        foreach (ReportType::cases() as $type) {
            $fields = $service->getAvailableFieldsForType($type);
            $columns = collect($fields)->pluck('key')->toArray();
            $allColumns = array_merge($allColumns, $columns);
        }

        return array_unique($allColumns);
    }
}
