<?php

namespace App\Http\Requests\Admin;

use App\Enums\HelpArticleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for updating an existing help article.
 *
 * Validates update fields and ensures the user is an Administrator.
 * Allows partial updates - only provided fields are validated.
 */
class UpdateHelpArticleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Only Administrators can update help articles.
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
        // Get article ID from route - could be model instance or string ID
        $articleParam = $this->route('article');
        $articleId = is_object($articleParam) ? $articleParam->id : $articleParam;

        return [
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('help_articles', 'slug')->ignore($articleId),
            ],
            'title' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'content' => [
                'sometimes',
                'string',
                'max:65535',
            ],
            'context_key' => [
                'nullable',
                'string',
                'max:255',
            ],
            'article_type' => [
                'sometimes',
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
                'sometimes',
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
            'slug.regex' => 'The slug must be lowercase with hyphens only (e.g., "getting-started-guide").',
            'slug.unique' => 'This slug is already in use by another article.',
            'title.max' => 'The title must not exceed 255 characters.',
            'content.max' => 'The content must not exceed 65535 characters.',
            'article_type.enum' => 'The article type must be one of: tooltip, tour_step, or article.',
            'sort_order.integer' => 'The sort order must be a number.',
            'sort_order.min' => 'The sort order must be at least 0.',
            'sort_order.max' => 'The sort order must not exceed 9999.',
        ];
    }
}
