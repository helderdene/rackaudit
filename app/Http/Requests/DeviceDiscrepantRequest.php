<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for marking a device as discrepant during inventory audit execution.
 *
 * Notes are required to document the specific discrepancy found.
 */
class DeviceDiscrepantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Authorization is handled by the controller/middleware.
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
            'notes' => ['required', 'string', 'min:10', 'max:1000'],
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
            'notes.required' => 'Notes are required when marking a device as discrepant.',
            'notes.string' => 'Notes must be a valid text string.',
            'notes.min' => 'Notes must be at least 10 characters to provide meaningful context.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }
}
