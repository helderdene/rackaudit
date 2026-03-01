<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivityLogIndexRequest extends FormRequest
{
    /**
     * Valid action types for activity logs.
     *
     * @var array<string>
     */
    private const VALID_ACTIONS = [
        'created',
        'updated',
        'deleted',
    ];

    /**
     * Valid subject types that can be logged.
     *
     * @var array<string>
     */
    private const VALID_SUBJECT_TYPES = [
        'App\Models\User',
        'App\Models\Datacenter',
    ];

    /**
     * Determine if the user is authorized to make this request.
     * All authenticated users can view activity logs (filtering happens in controller).
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
        return [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'action' => ['nullable', 'string', Rule::in(self::VALID_ACTIONS)],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'subject_type' => ['nullable', 'string', Rule::in(self::VALID_SUBJECT_TYPES)],
            'search' => ['nullable', 'string', 'max:255'],
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
            'start_date.date' => 'The start date must be a valid date.',
            'end_date.date' => 'The end date must be a valid date.',
            'end_date.after_or_equal' => 'The end date must be after or equal to the start date.',
            'action.in' => 'The action must be one of: '.implode(', ', self::VALID_ACTIONS),
            'user_id.integer' => 'The user ID must be a valid integer.',
            'user_id.exists' => 'The selected user does not exist.',
            'subject_type.in' => 'The selected entity type is invalid.',
            'search.max' => 'The search term must not exceed 255 characters.',
        ];
    }
}
