<?php

namespace App\Http\Requests;

use App\Enums\DiscrepancyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for validating connection comparison filter parameters.
 *
 * Validates filter parameters for comparing expected connections against
 * actual connections. Supports filtering by discrepancy type, device,
 * rack, and acknowledgment status.
 */
class CompareConnectionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * All authenticated users can view comparisons.
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
            'discrepancy_type' => ['nullable', 'array'],
            'discrepancy_type.*' => ['string', Rule::in($discrepancyTypeValues)],
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'rack_id' => ['nullable', 'integer', 'exists:racks,id'],
            'show_acknowledged' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
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
            'discrepancy_type.array' => 'The discrepancy type filter must be an array.',
            'discrepancy_type.*.in' => "Invalid discrepancy type. Valid types are: {$validTypes}.",
            'device_id.integer' => 'The device ID must be a valid integer.',
            'device_id.exists' => 'The selected device does not exist.',
            'rack_id.integer' => 'The rack ID must be a valid integer.',
            'rack_id.exists' => 'The selected rack does not exist.',
            'show_acknowledged.boolean' => 'The show acknowledged filter must be true or false.',
            'limit.integer' => 'The limit must be a valid integer.',
            'limit.min' => 'The limit must be at least 1.',
            'limit.max' => 'The limit cannot exceed 100.',
            'offset.integer' => 'The offset must be a valid integer.',
            'offset.min' => 'The offset must be at least 0.',
        ];
    }

    /**
     * Get the discrepancy types as enum instances.
     *
     * @return array<int, DiscrepancyType>
     */
    public function getDiscrepancyTypes(): array
    {
        $types = $this->input('discrepancy_type', []);

        if (empty($types)) {
            return [];
        }

        return array_map(
            fn (string $value) => DiscrepancyType::from($value),
            $types
        );
    }
}
