<?php

namespace App\Http\Requests;

use App\Enums\CableType;
use App\Enums\ExpectedConnectionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for updating an expected connection.
 *
 * Handles validation for device/port mapping corrections during review.
 */
class UpdateExpectedConnectionRequest extends FormRequest
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
            'source_device_id' => ['sometimes', 'nullable', 'integer', 'exists:devices,id'],
            'source_port_id' => ['sometimes', 'nullable', 'integer', 'exists:ports,id'],
            'dest_device_id' => ['sometimes', 'nullable', 'integer', 'exists:devices,id'],
            'dest_port_id' => ['sometimes', 'nullable', 'integer', 'exists:ports,id'],
            'cable_type' => ['sometimes', 'nullable', Rule::enum(CableType::class)],
            'cable_length' => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:9999.99'],
            'status' => ['sometimes', Rule::enum(ExpectedConnectionStatus::class)],
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
            'source_device_id.exists' => 'The selected source device does not exist.',
            'source_port_id.exists' => 'The selected source port does not exist.',
            'dest_device_id.exists' => 'The selected destination device does not exist.',
            'dest_port_id.exists' => 'The selected destination port does not exist.',
            'cable_type.Illuminate\Validation\Rules\Enum' => 'The selected cable type is invalid.',
            'cable_length.numeric' => 'The cable length must be a number.',
            'cable_length.min' => 'The cable length must be at least 0.01 meters.',
            'cable_length.max' => 'The cable length must not exceed 9999.99 meters.',
            'status.Illuminate\Validation\Rules\Enum' => 'The selected status is invalid.',
        ];
    }
}
