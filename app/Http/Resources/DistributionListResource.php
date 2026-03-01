<?php

namespace App\Http\Resources;

use App\Models\DistributionList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for transforming DistributionList model data.
 *
 * Provides consistent JSON representation of distribution lists including
 * name, description, member count, and optionally loaded members.
 *
 * @mixin DistributionList
 */
class DistributionListResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'members_count' => $this->whenCounted('members', $this->members_count),
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($member) => [
                'id' => $member->id,
                'email' => $member->email,
                'sort_order' => $member->sort_order,
            ])->values()->toArray()),
            'created_at' => $this->created_at,
        ];
    }
}
