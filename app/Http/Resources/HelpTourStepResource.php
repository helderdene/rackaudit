<?php

namespace App\Http\Resources;

use App\Models\HelpTourStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for transforming HelpTourStep model data.
 *
 * Provides consistent JSON representation of tour steps including
 * the associated article content and positioning information.
 *
 * @mixin HelpTourStep
 */
class HelpTourStepResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'step_order' => $this->step_order,
            'target_selector' => $this->target_selector,
            'position' => $this->position?->value,
            'position_label' => $this->position?->label(),
            'article' => $this->whenLoaded('article', function () {
                return [
                    'id' => $this->article->id,
                    'slug' => $this->article->slug,
                    'title' => $this->article->title,
                    'content' => $this->article->content,
                ];
            }),
        ];
    }
}
