<?php

namespace App\Http\Resources;

use App\Models\Connection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for transforming Connection model data.
 *
 * Provides consistent JSON representation of connections including
 * source and destination port information, cable properties, and
 * logical path for patch panel connections.
 *
 * @mixin Connection
 */
class ConnectionResource extends JsonResource
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

            // Cable properties
            'cable_type' => $this->cable_type?->value,
            'cable_type_label' => $this->cable_type?->label(),
            'cable_length' => $this->cable_length,
            'cable_color' => $this->cable_color,
            'path_notes' => $this->path_notes,

            // Source port with device info
            'source_port' => $this->whenLoaded('sourcePort', function () {
                return [
                    'id' => $this->sourcePort->id,
                    'label' => $this->sourcePort->label,
                    'type' => $this->sourcePort->type?->value,
                    'type_label' => $this->sourcePort->type?->label(),
                    'direction' => $this->sourcePort->direction?->value,
                    'direction_label' => $this->sourcePort->direction?->label(),
                    'device' => $this->whenLoaded('sourcePort.device', function () {
                        return [
                            'id' => $this->sourcePort->device->id,
                            'name' => $this->sourcePort->device->name,
                            'asset_tag' => $this->sourcePort->device->asset_tag ?? null,
                        ];
                    }, function () {
                        if ($this->sourcePort->device) {
                            return [
                                'id' => $this->sourcePort->device->id,
                                'name' => $this->sourcePort->device->name,
                                'asset_tag' => $this->sourcePort->device->asset_tag ?? null,
                            ];
                        }

                        return null;
                    }),
                ];
            }),

            // Destination port with device info
            'destination_port' => $this->whenLoaded('destinationPort', function () {
                return [
                    'id' => $this->destinationPort->id,
                    'label' => $this->destinationPort->label,
                    'type' => $this->destinationPort->type?->value,
                    'type_label' => $this->destinationPort->type?->label(),
                    'direction' => $this->destinationPort->direction?->value,
                    'direction_label' => $this->destinationPort->direction?->label(),
                    'device' => $this->whenLoaded('destinationPort.device', function () {
                        return [
                            'id' => $this->destinationPort->device->id,
                            'name' => $this->destinationPort->device->name,
                            'asset_tag' => $this->destinationPort->device->asset_tag ?? null,
                        ];
                    }, function () {
                        if ($this->destinationPort->device) {
                            return [
                                'id' => $this->destinationPort->device->id,
                                'name' => $this->destinationPort->device->name,
                                'asset_tag' => $this->destinationPort->device->asset_tag ?? null,
                            ];
                        }

                        return null;
                    }),
                ];
            }),

            // Logical path array for patch panel connections
            'logical_path' => $this->when(
                $this->relationLoaded('sourcePort') && $this->relationLoaded('destinationPort'),
                function () {
                    $path = $this->getLogicalPath();

                    return collect($path)->map(function ($port) {
                        return [
                            'id' => $port->id,
                            'label' => $port->label,
                            'device_id' => $port->device_id,
                            'device_name' => $port->device?->name,
                        ];
                    })->toArray();
                }
            ),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
