<?php

namespace App\Http\Resources;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for transforming Device model data.
 *
 * Provides consistent JSON representation of devices including
 * device type, rack references, and calculated warranty status.
 *
 * @mixin Device
 */
class DeviceResource extends JsonResource
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
            'asset_tag' => $this->asset_tag,
            'serial_number' => $this->serial_number,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,

            // Lifecycle status
            'lifecycle_status' => $this->lifecycle_status?->value,
            'lifecycle_status_label' => $this->lifecycle_status?->label(),

            // Physical dimensions
            'u_height' => $this->u_height,
            'depth' => $this->depth?->value,
            'depth_label' => $this->depth?->label(),
            'width_type' => $this->width_type?->value,
            'width_type_label' => $this->width_type?->label(),
            'rack_face' => $this->rack_face?->value,
            'rack_face_label' => $this->rack_face?->label(),

            // Placement
            'start_u' => $this->start_u,

            // Dates
            'purchase_date' => $this->purchase_date?->format('Y-m-d'),
            'warranty_start_date' => $this->warranty_start_date?->format('Y-m-d'),
            'warranty_end_date' => $this->warranty_end_date?->format('Y-m-d'),
            'warranty_status' => $this->getWarrantyStatus(),

            // Flexible specifications
            'specs' => $this->specs,

            // Notes
            'notes' => $this->notes,

            // Relationships
            'device_type' => $this->whenLoaded('deviceType', fn () => [
                'id' => $this->deviceType->id,
                'name' => $this->deviceType->name,
                'default_u_size' => $this->deviceType->default_u_size,
            ]),
            'rack' => $this->whenLoaded('rack', fn () => $this->rack ? [
                'id' => $this->rack->id,
                'name' => $this->rack->name,
                'position' => $this->rack->position,
                'row_id' => $this->rack->row_id,
                'room_id' => $this->rack->row?->room_id,
                'datacenter_id' => $this->rack->row?->room?->datacenter_id,
            ] : null),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Calculate warranty status based on warranty dates.
     *
     * @return string 'active' | 'expired' | 'none'
     */
    private function getWarrantyStatus(): string
    {
        if ($this->warranty_start_date === null && $this->warranty_end_date === null) {
            return 'none';
        }

        if ($this->warranty_end_date === null) {
            return 'none';
        }

        $now = now()->startOfDay();

        if ($this->warranty_end_date->startOfDay()->gte($now)) {
            return 'active';
        }

        return 'expired';
    }
}
