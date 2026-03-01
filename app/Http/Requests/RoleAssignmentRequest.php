<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleAssignmentRequest extends FormRequest
{
    /**
     * The five predefined roles in the system.
     *
     * @var array<string>
     */
    private const VALID_ROLES = [
        'Administrator',
        'IT Manager',
        'Operator',
        'Auditor',
        'Viewer',
    ];

    /**
     * Determine if the user is authorized to make this request.
     * Only users with Administrator role can assign roles.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole('Administrator');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(self::VALID_ROLES)],
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
            'role.required' => 'A role must be specified.',
            'role.in' => 'The selected role is invalid. Must be one of: '.implode(', ', self::VALID_ROLES),
        ];
    }
}
