<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceTypeRequest extends FormRequest
{
    /**
     * Roles that can update device types.
     *
     * @var array<string>
     */
    private const AUTHORIZED_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine if the user is authorized to make this request.
     * Only Administrators and IT Managers can update device types.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasAnyRole(self::AUTHORIZED_ROLES);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('device_types', 'name')->ignore($this->route('device_type')),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'default_u_size' => ['nullable', 'integer', 'min:1', 'max:48'],
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
            'name.required' => 'The device type name is required.',
            'name.string' => 'The device type name must be a string.',
            'name.max' => 'The device type name must not exceed 255 characters.',
            'name.unique' => 'A device type with this name already exists.',
            'description.string' => 'The description must be a string.',
            'description.max' => 'The description must not exceed 1000 characters.',
            'default_u_size.integer' => 'The default U size must be a whole number.',
            'default_u_size.min' => 'The default U size must be at least 1.',
            'default_u_size.max' => 'The default U size must not exceed 48.',
        ];
    }
}
