<?php

namespace App\Http\Resources;

use App\Models\Discrepancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for transforming Discrepancy model data.
 *
 * Provides consistent JSON representation of discrepancy records including
 * type, status, timestamps, and related device/port/datacenter information.
 *
 * @mixin Discrepancy
 */
class DiscrepancyResource extends JsonResource
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
            'discrepancy_type' => $this->discrepancy_type->value,
            'discrepancy_type_label' => $this->discrepancy_type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'title' => $this->title,
            'description' => $this->description,

            // Timestamps
            'detected_at' => $this->detected_at?->toIso8601String(),
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Datacenter and room
            'datacenter' => $this->whenLoaded('datacenter', fn () => [
                'id' => $this->datacenter->id,
                'name' => $this->datacenter->name,
            ]),
            'datacenter_id' => $this->datacenter_id,

            'room' => $this->whenLoaded('room', fn () => $this->room ? [
                'id' => $this->room->id,
                'name' => $this->room->name,
            ] : null),
            'room_id' => $this->room_id,

            // Implementation file
            'implementation_file' => $this->whenLoaded('implementationFile', fn () => $this->implementationFile ? [
                'id' => $this->implementationFile->id,
                'filename' => $this->implementationFile->filename,
            ] : null),
            'implementation_file_id' => $this->implementation_file_id,

            // Source port and device
            'source_port' => $this->whenLoaded('sourcePort', fn () => $this->transformPort($this->sourcePort)),
            'source_port_id' => $this->source_port_id,

            // Destination port and device
            'dest_port' => $this->whenLoaded('destPort', fn () => $this->transformPort($this->destPort)),
            'dest_port_id' => $this->dest_port_id,

            // Connection info
            'connection' => $this->whenLoaded('connection', fn () => $this->connection ? [
                'id' => $this->connection->id,
                'cable_type' => $this->connection->cable_type?->value,
                'cable_type_label' => $this->connection->cable_type?->label(),
                'cable_length' => $this->connection->cable_length,
            ] : null),
            'connection_id' => $this->connection_id,

            // Expected connection info
            'expected_connection' => $this->whenLoaded('expectedConnection', fn () => $this->expectedConnection ? [
                'id' => $this->expectedConnection->id,
                'row_number' => $this->expectedConnection->row_number,
                'cable_type' => $this->expectedConnection->cable_type?->value,
                'cable_type_label' => $this->expectedConnection->cable_type?->label(),
                'cable_length' => $this->expectedConnection->cable_length,
                'status' => $this->expectedConnection->status->value,
            ] : null),
            'expected_connection_id' => $this->expected_connection_id,

            // Configuration data
            'expected_config' => $this->expected_config,
            'actual_config' => $this->actual_config,
            'mismatch_details' => $this->mismatch_details,

            // User relationships
            'acknowledged_by' => $this->whenLoaded('acknowledgedBy', fn () => $this->acknowledgedBy ? [
                'id' => $this->acknowledgedBy->id,
                'name' => $this->acknowledgedBy->name,
            ] : null),

            'resolved_by' => $this->whenLoaded('resolvedBy', fn () => $this->resolvedBy ? [
                'id' => $this->resolvedBy->id,
                'name' => $this->resolvedBy->name,
            ] : null),

            // Audit and finding links
            'audit_id' => $this->audit_id,
            'finding_id' => $this->finding_id,
        ];
    }

    /**
     * Transform a port model to array representation.
     *
     * @return array<string, mixed>|null
     */
    protected function transformPort(?\App\Models\Port $port): ?array
    {
        if (! $port) {
            return null;
        }

        return [
            'id' => $port->id,
            'label' => $port->label,
            'type' => $port->type?->value,
            'type_label' => $port->type?->label(),
            'device_id' => $port->device_id,
            'device' => $port->relationLoaded('device') && $port->device ? [
                'id' => $port->device->id,
                'name' => $port->device->name,
                'asset_tag' => $port->device->asset_tag,
                'rack' => $port->device->relationLoaded('rack') && $port->device->rack ? [
                    'id' => $port->device->rack->id,
                    'name' => $port->device->rack->name,
                ] : null,
            ] : null,
        ];
    }
}
