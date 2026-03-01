<?php

namespace App\Http\Requests\Admin;

use App\Enums\HelpArticleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for creating a new help article.
 *
 * Validates all article fields and ensures the user is an Administrator.
 */
class StoreHelpArticleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Only Administrators can create help articles.
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
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'unique:help_articles,slug',
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'content' => [
                'required',
                'string',
                'max:65535',
            ],
            'context_key' => [
                'nullable',
                'string',
                'max:255',
            ],
            'article_type' => [
                'required',
                Rule::enum(HelpArticleType::class),
            ],
            'category' => [
                'nullable',
                'string',
                'max:100',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:9999',
            ],
            'is_active' => [
                'nullable',
                'boolean',
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
            'slug.required' => 'The slug is required.',
            'slug.regex' => 'The slug must be lowercase with hyphens only (e.g., "getting-started-guide").',
            'slug.unique' => 'This slug is already in use by another article.',
            'title.required' => 'The title is required.',
            'title.max' => 'The title must not exceed 255 characters.',
            'content.required' => 'The content is required.',
            'content.max' => 'The content must not exceed 65535 characters.',
            'article_type.required' => 'The article type is required.',
            'article_type.enum' => 'The article type must be one of: tooltip, tour_step, or article.',
            'sort_order.integer' => 'The sort order must be a number.',
            'sort_order.min' => 'The sort order must be at least 0.',
            'sort_order.max' => 'The sort order must not exceed 9999.',
        ];
    }
}
