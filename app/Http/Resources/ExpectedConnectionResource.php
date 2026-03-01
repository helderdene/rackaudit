<?php

namespace App\Http\Resources;

use App\Models\ExpectedConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for transforming ExpectedConnection model data.
 *
 * Provides consistent JSON representation of expected connections including
 * source and destination device/port information, cable properties, match
 * status, and original parsed values for review purposes.
 *
 * @mixin ExpectedConnection
 */
class ExpectedConnectionResource extends JsonResource
{
    /**
     * Additional match data to include in the response.
     *
     * @var array<string, array>|null
     */
    protected ?array $matchData = null;

    /**
     * Set match data for this resource.
     *
     * @param  array<string, array>  $matchData
     */
    public function withMatchData(array $matchData): static
    {
        $this->matchData = $matchData;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'row_number' => $this->row_number,

            // Cable properties
            'cable_type' => $this->cable_type?->value,
            'cable_type_label' => $this->cable_type?->label(),
            'cable_length' => $this->cable_length,

            // Status
            'status' => $this->status->value,
            'status_label' => $this->status->label(),

            // Source device with info
            'source_device' => $this->whenLoaded('sourceDevice', function () {
                return [
                    'id' => $this->sourceDevice?->id,
                    'name' => $this->sourceDevice?->name,
                    'asset_tag' => $this->sourceDevice?->asset_tag ?? null,
                ];
            }, function () {
                if ($this->sourceDevice) {
                    return [
                        'id' => $this->sourceDevice->id,
                        'name' => $this->sourceDevice->name,
                        'asset_tag' => $this->sourceDevice->asset_tag ?? null,
                    ];
                }

                return null;
            }),

            // Source port with info
            'source_port' => $this->whenLoaded('sourcePort', function () {
                return [
                    'id' => $this->sourcePort?->id,
                    'label' => $this->sourcePort?->label,
                    'type' => $this->sourcePort?->type?->value,
                    'type_label' => $this->sourcePort?->type?->label(),
                ];
            }, function () {
                if ($this->sourcePort) {
                    return [
                        'id' => $this->sourcePort->id,
                        'label' => $this->sourcePort->label,
                        'type' => $this->sourcePort->type?->value,
                        'type_label' => $this->sourcePort->type?->label(),
                    ];
                }

                return null;
            }),

            // Destination device with info
            'dest_device' => $this->whenLoaded('destDevice', function () {
                return [
                    'id' => $this->destDevice?->id,
                    'name' => $this->destDevice?->name,
                    'asset_tag' => $this->destDevice?->asset_tag ?? null,
                ];
            }, function () {
                if ($this->destDevice) {
                    return [
                        'id' => $this->destDevice->id,
                        'name' => $this->destDevice->name,
                        'asset_tag' => $this->destDevice->asset_tag ?? null,
                    ];
                }

                return null;
            }),

            // Destination port with info
            'dest_port' => $this->whenLoaded('destPort', function () {
                return [
                    'id' => $this->destPort?->id,
                    'label' => $this->destPort?->label,
                    'type' => $this->destPort?->type?->value,
                    'type_label' => $this->destPort?->type?->label(),
                ];
            }, function () {
                if ($this->destPort) {
                    return [
                        'id' => $this->destPort->id,
                        'label' => $this->destPort->label,
                        'type' => $this->destPort->type?->value,
                        'type_label' => $this->destPort->type?->label(),
                    ];
                }

                return null;
            }),

            // Match data (original parsed values and confidence)
            'match' => $this->when($this->matchData !== null, function () {
                return $this->matchData;
            }),

            // Implementation file reference
            'implementation_file_id' => $this->implementation_file_id,

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
