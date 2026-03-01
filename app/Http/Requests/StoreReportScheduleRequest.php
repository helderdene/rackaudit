<?php

namespace App\Http\Requests;

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Enums\ScheduleFrequency;
use App\Models\ReportSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for creating a new report schedule.
 *
 * Validates all schedule configuration including frequency, timing,
 * distribution list, report type, and report configuration.
 * Authorization is based on the user's ability to create scheduled reports.
 */
class StoreReportScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * User must have permission to create scheduled reports.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', ReportSchedule::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'distribution_list_id' => [
                'required',
                'integer',
                'exists:distribution_lists,id',
            ],

            'report_type' => [
                'required',
                'string',
                Rule::in(array_column(ReportType::cases(), 'value')),
            ],

            'report_configuration' => ['required', 'array'],
            'report_configuration.columns' => ['required', 'array', 'min:1'],
            'report_configuration.columns.*' => ['required', 'string'],
            'report_configuration.filters' => ['nullable', 'array'],
            'report_configuration.sort' => ['nullable', 'array'],
            'report_configuration.group_by' => ['nullable', 'string'],

            'frequency' => [
                'required',
                'string',
                Rule::in(array_column(ScheduleFrequency::cases(), 'value')),
            ],

            'day_of_week' => [
                'nullable',
                'required_if:frequency,weekly',
                'integer',
                'between:0,6',
            ],

            'day_of_month' => [
                'nullable',
                'required_if:frequency,monthly',
                'string',
                function ($attribute, $value, $fail) {
                    if ($value === 'last') {
                        return;
                    }
                    if (! is_numeric($value) || (int) $value < 1 || (int) $value > 28) {
                        $fail('The day of month must be between 1 and 28, or "last".');
                    }
                },
            ],

            'time_of_day' => ['required', 'date_format:H:i'],

            'timezone' => ['required', 'timezone'],

            'format' => [
                'required',
                'string',
                Rule::in(array_column(ReportFormat::cases(), 'value')),
            ],
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
            'name.required' => 'The schedule name is required.',
            'name.max' => 'The schedule name must not exceed 255 characters.',

            'distribution_list_id.required' => 'A distribution list is required.',
            'distribution_list_id.exists' => 'The selected distribution list does not exist.',

            'report_type.required' => 'The report type is required.',
            'report_type.in' => 'The selected report type is invalid.',

            'report_configuration.required' => 'The report configuration is required.',
            'report_configuration.columns.required' => 'At least one column must be selected for the report.',
            'report_configuration.columns.min' => 'At least one column must be selected for the report.',

            'frequency.required' => 'The schedule frequency is required.',
            'frequency.in' => 'The selected frequency is invalid.',

            'day_of_week.required_if' => 'The day of week is required for weekly schedules.',
            'day_of_week.between' => 'The day of week must be between 0 (Sunday) and 6 (Saturday).',

            'day_of_month.required_if' => 'The day of month is required for monthly schedules.',

            'time_of_day.required' => 'The time of day is required.',
            'time_of_day.date_format' => 'The time of day must be in HH:MM format.',

            'timezone.required' => 'The timezone is required.',
            'timezone.timezone' => 'The selected timezone is invalid.',

            'format.required' => 'The report format is required.',
            'format.in' => 'The selected format must be PDF or CSV.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure day_of_week is null for non-weekly schedules
        if ($this->input('frequency') !== 'weekly') {
            $this->merge(['day_of_week' => null]);
        }

        // Ensure day_of_month is null for non-monthly schedules
        if ($this->input('frequency') !== 'monthly') {
            $this->merge(['day_of_month' => null]);
        }
    }
}
