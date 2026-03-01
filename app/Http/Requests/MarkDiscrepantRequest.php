<?php

namespace App\Http\Requests;

use App\Enums\DiscrepancyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for marking a connection as discrepant during audit execution.
 *
 * Requires a discrepancy type and notes explaining the issue.
 */
class MarkDiscrepantRequest extends FormRequest
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
        $discrepancyTypeValues = array_map(
            fn (DiscrepancyType $type) => $type->value,
            DiscrepancyType::cases()
        );

        return [
            'discrepancy_type' => ['required', 'string', Rule::in($discrepancyTypeValues)],
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
        $validTypes = implode(', ', array_map(
            fn (DiscrepancyType $type) => $type->label(),
            DiscrepancyType::cases()
        ));

        return [
            'discrepancy_type.required' => 'Please select a discrepancy type.',
            'discrepancy_type.in' => "Invalid discrepancy type. Valid types are: {$validTypes}.",
            'notes.required' => 'Notes are required when marking a connection as discrepant.',
            'notes.string' => 'Notes must be a valid text string.',
            'notes.min' => 'Notes must be at least 10 characters to provide meaningful context.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Get the discrepancy type as an enum instance.
     */
    public function getDiscrepancyType(): DiscrepancyType
    {
        return DiscrepancyType::from($this->input('discrepancy_type'));
    }
}
