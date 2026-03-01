<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for validating audit history report filters.
 *
 * Validates time range presets, custom date ranges, datacenter filtering,
 * and audit type filtering for the audit history reports feature.
 */
class AuditHistoryReportRequest extends FormRequest
{
    /**
     * Valid time range preset options.
     *
     * @var array<string>
     */
    private const TIME_RANGE_PRESETS = [
        '30_days',
        '6_months',
        '12_months',
    ];

    /**
     * Valid audit type options.
     *
     * @var array<string>
     */
    private const AUDIT_TYPES = [
        'connection',
        'inventory',
    ];

    /**
     * Determine if the user is authorized to make this request.
     * All authenticated users can view audit history reports.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'time_range_preset' => ['nullable', 'string', Rule::in(self::TIME_RANGE_PRESETS)],
            'start_date' => ['nullable', 'date', 'before_or_equal:end_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'datacenter_id' => ['nullable', 'integer', 'exists:datacenters,id'],
            'audit_type' => ['nullable', 'string', Rule::in(self::AUDIT_TYPES)],
            'sort_by' => ['nullable', 'string', Rule::in(['completion_date', 'total_findings', 'avg_resolution_time'])],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
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
            'time_range_preset.in' => 'The time range preset must be one of: '.implode(', ', self::TIME_RANGE_PRESETS),
            'start_date.date' => 'The start date must be a valid date.',
            'start_date.before_or_equal' => 'The start date must be before or equal to the end date.',
            'end_date.date' => 'The end date must be a valid date.',
            'end_date.after_or_equal' => 'The end date must be after or equal to the start date.',
            'datacenter_id.integer' => 'The datacenter ID must be a valid integer.',
            'datacenter_id.exists' => 'The selected datacenter does not exist.',
            'audit_type.in' => 'The audit type must be one of: '.implode(', ', self::AUDIT_TYPES),
            'sort_by.in' => 'The sort field must be one of: completion_date, total_findings, avg_resolution_time.',
            'sort_direction.in' => 'The sort direction must be either asc or desc.',
        ];
    }
}
