<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for creating device/port on the fly for unrecognized entries.
 *
 * Validates device name and port label for creation when fuzzy matching
 * couldn't find a match during expected connection review.
 */
class CreateDevicePortOnFlyRequest extends FormRequest
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
            'device_name' => ['required', 'string', 'max:255'],
            'port_label' => ['required', 'string', 'max:255'],
            'target' => ['required', 'string', Rule::in(['source', 'dest'])],
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
            'device_name.required' => 'A device name is required.',
            'device_name.max' => 'The device name must not exceed 255 characters.',
            'port_label.required' => 'A port label is required.',
            'port_label.max' => 'The port label must not exceed 255 characters.',
            'target.required' => 'The target field (source or dest) is required.',
            'target.in' => 'The target must be either "source" or "dest".',
        ];
    }
}
