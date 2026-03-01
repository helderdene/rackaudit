<?php

namespace App\Http\Requests\Admin;

use App\Enums\HelpTourStepPosition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for updating an existing help tour.
 *
 * Validates tour update fields including nested steps and ensures the user is an Administrator.
 * When steps are provided, all existing steps are replaced with the new set.
 */
class UpdateHelpTourRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Only Administrators can update help tours.
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
        // Get tour ID from route - could be model instance or string ID
        $tourParam = $this->route('tour');
        $tourId = is_object($tourParam) ? $tourParam->id : $tourParam;

        return [
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('help_tours', 'slug')->ignore($tourId),
            ],
            'name' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'context_key' => [
                'nullable',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'steps' => [
                'sometimes',
                'array',
            ],
            'steps.*.help_article_id' => [
                'required_with:steps',
                'integer',
                'exists:help_articles,id',
            ],
            'steps.*.target_selector' => [
                'required_with:steps',
                'string',
                'max:255',
            ],
            'steps.*.position' => [
                'required_with:steps',
                Rule::enum(HelpTourStepPosition::class),
            ],
            'steps.*.step_order' => [
                'required_with:steps',
                'integer',
                'min:0',
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
            'slug.regex' => 'The slug must be lowercase with hyphens only (e.g., "audit-tour").',
            'slug.unique' => 'This slug is already in use by another tour.',
            'name.max' => 'The tour name must not exceed 255 characters.',
            'description.max' => 'The description must not exceed 1000 characters.',
            'steps.*.help_article_id.required_with' => 'Each step must have an associated article.',
            'steps.*.help_article_id.exists' => 'The selected article does not exist.',
            'steps.*.target_selector.required_with' => 'Each step must have a target selector.',
            'steps.*.target_selector.max' => 'The target selector must not exceed 255 characters.',
            'steps.*.position.required_with' => 'Each step must have a position.',
            'steps.*.position.enum' => 'The step position must be one of: top, right, bottom, or left.',
            'steps.*.step_order.required_with' => 'Each step must have an order.',
            'steps.*.step_order.integer' => 'The step order must be a number.',
            'steps.*.step_order.min' => 'The step order must be at least 0.',
        ];
    }
}
