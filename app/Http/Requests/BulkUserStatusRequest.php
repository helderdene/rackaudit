<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUserStatusRequest extends FormRequest
{
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
     * Only users with Administrator role can perform bulk status changes.
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
        $currentUserId = $this->user()?->id;

        return [
            'user_ids' => [
                'required',
                'array',
                'min:1',
                function ($attribute, $value, $fail) use ($currentUserId) {
                    if (in_array($currentUserId, $value)) {
                        $fail('You cannot include yourself in a bulk status change.');
                    }
                },
            ],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'status' => ['required', 'string', Rule::in(self::VALID_STATUSES)],
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
            'user_ids.required' => 'At least one user must be selected.',
            'user_ids.array' => 'User IDs must be an array.',
            'user_ids.min' => 'At least one user must be selected.',
            'user_ids.*.exists' => 'One or more selected users do not exist.',
            'status.required' => 'A status must be specified.',
            'status.in' => 'The selected status is invalid. Must be one of: '.implode(', ', self::VALID_STATUSES),
        ];
    }
}
