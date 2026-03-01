<?php

namespace App\Http\Requests;

use App\Models\DistributionList;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for creating a new distribution list.
 *
 * Validates the distribution list name, description, and member emails.
 * Authorization is based on the user's ability to create distribution lists.
 */
class StoreDistributionListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * User must have permission to create distribution lists.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', DistributionList::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'members' => ['nullable', 'array'],
            'members.*.email' => ['required', 'email'],
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
            'name.required' => 'The distribution list name is required.',
            'name.string' => 'The distribution list name must be a string.',
            'name.max' => 'The distribution list name must not exceed 255 characters.',
            'description.string' => 'The description must be a string.',
            'description.max' => 'The description must not exceed 1000 characters.',
            'members.array' => 'The members must be an array.',
            'members.*.email.required' => 'Each member must have an email address.',
            'members.*.email.email' => 'Each member email must be a valid email address.',
        ];
    }
}
