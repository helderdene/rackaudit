<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
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
     * Valid user statuses.
     *
     * @var array<string>
     */
    private const VALID_STATUSES = [
        'active',
        'inactive',
        'suspended',
    ];

    /**
     * Determine if the user is authorized to make this request.
     * Only users with Administrator role can update users.
     * Prevents self-demotion and self-deactivation for Administrators.
     */
    public function authorize(): bool
    {
        $currentUser = $this->user();

        if (! $currentUser || ! $currentUser->hasRole('Administrator')) {
            return false;
        }

        /** @var User $targetUser */
        $targetUser = $this->route('user');

        // If editing self, check for self-demotion or self-deactivation
        if ($currentUser->id === $targetUser->id) {
            // Prevent self-demotion from Administrator
            if ($currentUser->hasRole('Administrator') && $this->input('role') !== 'Administrator') {
                return false;
            }

            // Prevent self-deactivation
            if ($this->input('status') !== 'active') {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', Password::min(8), 'confirmed'],
            'role' => ['required', 'string', Rule::in(self::VALID_ROLES)],
            'status' => ['required', 'string', Rule::in(self::VALID_STATUSES)],
            'datacenter_ids' => ['nullable', 'array'],
            'datacenter_ids.*' => ['integer', 'exists:datacenters,id'],
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
            'name.required' => 'The name field is required.',
            'name.max' => 'The name must not exceed 255 characters.',
            'email.required' => 'The email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already in use.',
            'password.min' => 'The password must be at least 8 characters.',
            'password.confirmed' => 'The password confirmation does not match.',
            'role.required' => 'A role must be specified.',
            'role.in' => 'The selected role is invalid. Must be one of: '.implode(', ', self::VALID_ROLES),
            'status.required' => 'A status must be specified.',
            'status.in' => 'The selected status is invalid. Must be one of: '.implode(', ', self::VALID_STATUSES),
            'datacenter_ids.array' => 'Datacenter IDs must be an array.',
            'datacenter_ids.*.exists' => 'One or more selected datacenters do not exist.',
        ];
    }
}
