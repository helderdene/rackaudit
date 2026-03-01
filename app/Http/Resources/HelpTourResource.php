<?php

namespace App\Http\Resources;

use App\Models\HelpTour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for transforming HelpTour model data.
 *
 * Provides consistent JSON representation of help tours including
 * nested steps with their associated articles.
 *
 * @mixin HelpTour
 */
class HelpTourResource extends JsonResource
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
            'slug' => $this->slug,
            'name' => $this->name,
            'context_key' => $this->context_key,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'steps' => HelpTourStepResource::collection($this->whenLoaded('steps')),
            'step_count' => $this->whenLoaded('steps', fn () => $this->steps->count()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
