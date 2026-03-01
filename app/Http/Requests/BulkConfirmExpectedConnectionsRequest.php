<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for bulk confirming expected connections.
 *
 * Validates an array of connection IDs for batch status update to confirmed.
 */
class BulkConfirmExpectedConnectionsRequest extends FormRequest
{
    /**
     * Roles that can manage expected connections.
     *
     * @var array<string>
     */
    private const AUTHORIZED_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (! $this->user()) {
            return false;
        }

        return $this->user()->hasAnyRole(self::AUTHORIZED_ROLES);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'connection_ids' => ['required', 'array', 'min:1'],
            'connection_ids.*' => ['integer', 'exists:expected_connections,id'],
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
            'connection_ids.required' => 'At least one connection must be selected.',
            'connection_ids.array' => 'Connection IDs must be an array.',
            'connection_ids.min' => 'At least one connection must be selected.',
            'connection_ids.*.exists' => 'One or more selected connections do not exist.',
        ];
    }
}
