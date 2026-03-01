<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for validating help article search queries.
 *
 * Validates the search query string and optional filters for
 * searching help articles by title and content.
 */
class SearchHelpArticlesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * All authenticated users can search help articles.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'query' => [
                'required',
                'string',
                'min:2',
                'max:100',
            ],
            'category' => [
                'nullable',
                'string',
                'max:50',
            ],
            'limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
            ],
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
            'query.required' => 'Please enter a search term.',
            'query.min' => 'Search term must be at least 2 characters.',
            'query.max' => 'Search term must not exceed 100 characters.',
            'category.max' => 'Category filter must not exceed 50 characters.',
            'limit.min' => 'Result limit must be at least 1.',
            'limit.max' => 'Result limit must not exceed 50.',
        ];
    }
}
