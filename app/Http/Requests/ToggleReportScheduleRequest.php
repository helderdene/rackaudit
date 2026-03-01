<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for toggling a report schedule's enabled state.
 *
 * Validates the is_enabled boolean field.
 * Authorization is based on the user's ability to update the report schedule.
 */
class ToggleReportScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * User must have permission to update the report schedule.
     */
    public function authorize(): bool
    {
        $reportSchedule = $this->route('reportSchedule');

        return $this->user()->can('update', $reportSchedule);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_enabled' => ['required', 'boolean'],
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
            'is_enabled.required' => 'The enabled status is required.',
            'is_enabled.boolean' => 'The enabled status must be true or false.',
        ];
    }
}
