<?php

namespace App\Http\Resources;

use App\Models\Port;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for transforming Port model data.
 *
 * Provides consistent JSON representation of ports including
 * enum labels for type, subtype, status, direction, and visual face.
 * Also includes pairing information for patch panel port pairs and
 * connection data for connected ports.
 *
 * @mixin Port
 */
class PortResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Determine the connection for this port (could be source or destination)
        $connection = $this->whenLoaded('connectionAsSource', function () {
            return $this->connectionAsSource;
        }) ?? $this->whenLoaded('connectionAsDestination', function () {
            return $this->connectionAsDestination;
        });

        // Determine remote device and port info based on connection direction
        $remoteDeviceName = null;
        $remotePortLabel = null;

        if ($this->relationLoaded('connectionAsSource') && $this->connectionAsSource) {
            $remoteDeviceName = $this->connectionAsSource->destinationPort?->device?->name;
            $remotePortLabel = $this->connectionAsSource->destinationPort?->label;
        } elseif ($this->relationLoaded('connectionAsDestination') && $this->connectionAsDestination) {
            $remoteDeviceName = $this->connectionAsDestination->sourcePort?->device?->name;
            $remotePortLabel = $this->connectionAsDestination->sourcePort?->label;
        }

        return [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'label' => $this->label,

            // Type fields with labels
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'subtype' => $this->subtype?->value,
            'subtype_label' => $this->subtype?->label(),

            // Status with label
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),

            // Direction with label
            'direction' => $this->direction?->value,
            'direction_label' => $this->direction?->label(),

            // Port pairing for patch panels
            'paired_port_id' => $this->paired_port_id,
            'paired_port' => $this->whenLoaded('pairedPort', function () {
                return [
                    'id' => $this->pairedPort->id,
                    'label' => $this->pairedPort->label,
                    'type' => $this->pairedPort->type?->value,
                    'type_label' => $this->pairedPort->type?->label(),
                ];
            }),

            // Physical position fields
            'position_slot' => $this->position_slot,
            'position_row' => $this->position_row,
            'position_column' => $this->position_column,

            // Visual position fields
            'visual_x' => $this->visual_x,
            'visual_y' => $this->visual_y,
            'visual_face' => $this->visual_face?->value,
            'visual_face_label' => $this->visual_face?->label(),

            // Connection data (when port is connected)
            'connection' => $this->getConnectionData(),

            // Remote device/port info for display convenience
            'remote_device_name' => $remoteDeviceName,
            'remote_port_label' => $remotePortLabel,

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get the connection data for this port.
     *
     * Returns the connection resource if the port has a connection loaded,
     * checking both source and destination relationships.
     *
     * @return array<string, mixed>|null
     */
    private function getConnectionData(): ?array
    {
        // Check if connection as source is loaded and exists
        if ($this->relationLoaded('connectionAsSource') && $this->connectionAsSource) {
            return $this->formatConnection($this->connectionAsSource, 'source');
        }

        // Check if connection as destination is loaded and exists
        if ($this->relationLoaded('connectionAsDestination') && $this->connectionAsDestination) {
            return $this->formatConnection($this->connectionAsDestination, 'destination');
        }

        return null;
    }

    /**
     * Format a connection for the response.
     *
     * @return array<string, mixed>
     */
    private function formatConnection($connection, string $role): array
    {
        return [
            'id' => $connection->id,
            'cable_type' => $connection->cable_type?->value,
            'cable_type_label' => $connection->cable_type?->label(),
            'cable_length' => $connection->cable_length,
            'cable_color' => $connection->cable_color,
            'path_notes' => $connection->path_notes,
            'created_at' => $connection->created_at,
            'updated_at' => $connection->updated_at,
            'source_port' => $this->formatPortInfo($connection->sourcePort),
            'destination_port' => $this->formatPortInfo($connection->destinationPort),
        ];
    }

    /**
     * Format port info for connection display.
     *
     * @return array<string, mixed>|null
     */
    private function formatPortInfo($port): ?array
    {
        if (! $port) {
            return null;
        }

        return [
            'id' => $port->id,
            'label' => $port->label,
            'type' => $port->type?->value,
            'type_label' => $port->type?->label(),
            'direction' => $port->direction?->value,
            'direction_label' => $port->direction?->label(),
            'device' => $port->device ? [
                'id' => $port->device->id,
                'name' => $port->device->name,
                'asset_tag' => $port->device->asset_tag,
            ] : null,
        ];
    }
}
