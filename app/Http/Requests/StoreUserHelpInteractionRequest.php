<?php

namespace App\Http\Requests;

use App\Enums\HelpInteractionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for recording user help interactions.
 *
 * Validates interaction recording requests including the interaction type
 * and the associated article or tour ID based on the interaction type.
 */
class StoreUserHelpInteractionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * All authenticated users can record their own help interactions.
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
        $interactionType = $this->input('interaction_type');
        $isCompletedTour = $interactionType === HelpInteractionType::CompletedTour->value;

        return [
            'interaction_type' => [
                'required',
                'string',
                Rule::enum(HelpInteractionType::class),
            ],
            'help_article_id' => [
                $isCompletedTour ? 'nullable' : 'required_without:help_tour_id',
                'integer',
                'exists:help_articles,id',
            ],
            'help_tour_id' => [
                $isCompletedTour ? 'required' : 'nullable',
                'integer',
                'exists:help_tours,id',
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
            'interaction_type.required' => 'Interaction type is required.',
            'interaction_type.enum' => 'Invalid interaction type. Must be viewed, dismissed, or completed_tour.',
            'help_article_id.required_without' => 'Either an article ID or tour ID is required.',
            'help_article_id.exists' => 'The specified help article does not exist.',
            'help_tour_id.required' => 'Tour ID is required for completed_tour interactions.',
            'help_tour_id.exists' => 'The specified help tour does not exist.',
        ];
    }
}
