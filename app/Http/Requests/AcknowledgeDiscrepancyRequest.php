<?php

namespace App\Http\Requests;

use App\Enums\DiscrepancyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for validating discrepancy acknowledgment creation.
 *
 * Validates that either expected_connection_id or connection_id is provided,
 * and that the discrepancy type is valid.
 */
class AcknowledgeDiscrepancyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * All authenticated users can create acknowledgments.
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
            'expected_connection_id' => [
                'nullable',
                'integer',
                'exists:expected_connections,id',
                'required_without:connection_id',
            ],
            'connection_id' => [
                'nullable',
                'integer',
                'exists:connections,id',
                'required_without:expected_connection_id',
            ],
            'discrepancy_type' => [
                'required',
                'string',
                Rule::in($discrepancyTypeValues),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
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
            fn (DiscrepancyType $type) => $type->value,
            DiscrepancyType::cases()
        ));

        return [
            'expected_connection_id.exists' => 'The selected expected connection does not exist.',
            'expected_connection_id.required_without' => 'Either an expected connection or an actual connection must be provided.',
            'connection_id.exists' => 'The selected connection does not exist.',
            'connection_id.required_without' => 'Either an expected connection or an actual connection must be provided.',
            'discrepancy_type.required' => 'The discrepancy type is required.',
            'discrepancy_type.in' => "Invalid discrepancy type. Valid types are: {$validTypes}.",
            'notes.max' => 'The notes may not exceed 1000 characters.',
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
