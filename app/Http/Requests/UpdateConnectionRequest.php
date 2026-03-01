<?php

namespace App\Http\Requests;

use App\Enums\CableType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConnectionRequest extends FormRequest
{
    /**
     * Roles that can manage connections.
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
     * Only cable properties can be updated. Source and destination ports
     * cannot be changed after creation.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cable_type' => ['sometimes', 'required', Rule::enum(CableType::class)],
            'cable_length' => ['sometimes', 'required', 'numeric', 'min:0.01', 'max:9999.99'],
            'cable_color' => ['nullable', 'string', 'max:50'],
            'path_notes' => ['nullable', 'string', 'max:65535'],
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
            'cable_type.required' => 'The cable type is required.',
            'cable_type.Illuminate\Validation\Rules\Enum' => 'The selected cable type is invalid.',
            'cable_length.required' => 'The cable length is required.',
            'cable_length.numeric' => 'The cable length must be a number.',
            'cable_length.min' => 'The cable length must be at least 0.01 meters.',
            'cable_length.max' => 'The cable length must not exceed 9999.99 meters.',
            'cable_color.string' => 'The cable color must be a string.',
            'cable_color.max' => 'The cable color must not exceed 50 characters.',
            'path_notes.string' => 'The path notes must be a string.',
            'path_notes.max' => 'The path notes are too long.',
        ];
    }
}
