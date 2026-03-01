<?php

namespace App\Http\Resources;

use App\DTOs\ComparisonResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for transforming ComparisonResult DTO data.
 *
 * Provides consistent JSON representation of comparison results including
 * discrepancy type, expected vs actual values, source and destination
 * device/port information, and acknowledgment status.
 */
class ComparisonResultResource extends JsonResource
{
    /**
     * The resource instance.
     *
     * @var ComparisonResult
     */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'discrepancy_type' => $this->resource->discrepancyType->value,
            'discrepancy_type_label' => $this->resource->discrepancyType->label(),

            // Expected connection info
            'expected_connection' => $this->transformExpectedConnection(),

            // Actual connection info
            'actual_connection' => $this->transformActualConnection(),

            // Source device and port
            'source_device' => $this->transformDevice($this->resource->getSourceDevice()),
            'source_port' => $this->transformPort($this->resource->sourcePort),

            // Destination device and port
            'dest_device' => $this->transformDevice($this->resource->getDestDevice()),
            'dest_port' => $this->transformPort($this->resource->destPort),

            // Expected vs actual comparison
            'expected_dest_port' => $this->transformPort($this->resource->expectedDestPort),
            'actual_dest_port' => $this->transformPort($this->resource->actualDestPort),

            // Differences for highlighting
            'has_port_difference' => $this->hasPortDifference(),
            'port_difference' => $this->getPortDifference(),

            // Conflict information
            'conflict_info' => $this->resource->conflictInfo,

            // Acknowledgment status
            'is_acknowledged' => $this->resource->isAcknowledged(),
            'acknowledgment' => $this->transformAcknowledgment(),
        ];
    }

    /**
     * Transform expected connection to array.
     *
     * @return array<string, mixed>|null
     */
    protected function transformExpectedConnection(): ?array
    {
        $expected = $this->resource->expectedConnection;

        if (! $expected) {
            return null;
        }

        return [
            'id' => $expected->id,
            'row_number' => $expected->row_number,
            'cable_type' => $expected->cable_type?->value,
            'cable_type_label' => $expected->cable_type?->label(),
            'cable_length' => $expected->cable_length,
            'status' => $expected->status->value,
            'implementation_file_id' => $expected->implementation_file_id,
        ];
    }

    /**
     * Transform actual connection to array.
     *
     * @return array<string, mixed>|null
     */
    protected function transformActualConnection(): ?array
    {
        $actual = $this->resource->actualConnection;

        if (! $actual) {
            return null;
        }

        return [
            'id' => $actual->id,
            'cable_type' => $actual->cable_type?->value,
            'cable_type_label' => $actual->cable_type?->label(),
            'cable_length' => $actual->cable_length,
            'cable_color' => $actual->cable_color,
        ];
    }

    /**
     * Transform device to array.
     *
     * @return array<string, mixed>|null
     */
    protected function transformDevice(?\App\Models\Device $device): ?array
    {
        if (! $device) {
            return null;
        }

        return [
            'id' => $device->id,
            'name' => $device->name,
            'asset_tag' => $device->asset_tag,
            'rack_id' => $device->rack_id,
            'rack' => $device->rack ? [
                'id' => $device->rack->id,
                'name' => $device->rack->name,
            ] : null,
        ];
    }

    /**
     * Transform port to array.
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
        ];
    }

    /**
     * Transform acknowledgment to array.
     *
     * @return array<string, mixed>|null
     */
    protected function transformAcknowledgment(): ?array
    {
        $ack = $this->resource->acknowledgment;

        if (! $ack) {
            return null;
        }

        return [
            'id' => $ack->id,
            'acknowledged_by' => $ack->acknowledged_by,
            'acknowledged_by_name' => $ack->acknowledgedBy?->name,
            'acknowledged_at' => $ack->acknowledged_at?->toIso8601String(),
            'notes' => $ack->notes,
        ];
    }

    /**
     * Check if there is a port difference between expected and actual.
     */
    protected function hasPortDifference(): bool
    {
        if (! $this->resource->expectedDestPort || ! $this->resource->actualDestPort) {
            return false;
        }

        return $this->resource->expectedDestPort->id !== $this->resource->actualDestPort->id;
    }

    /**
     * Get port difference description for display.
     *
     * @return array<string, mixed>|null
     */
    protected function getPortDifference(): ?array
    {
        if (! $this->hasPortDifference()) {
            return null;
        }

        return [
            'expected' => [
                'port_id' => $this->resource->expectedDestPort?->id,
                'port_label' => $this->resource->expectedDestPort?->label,
                'device_name' => $this->resource->expectedDestPort?->device?->name,
            ],
            'actual' => [
                'port_id' => $this->resource->actualDestPort?->id,
                'port_label' => $this->resource->actualDestPort?->label,
                'device_name' => $this->resource->actualDestPort?->device?->name,
            ],
        ];
    }

    /**
     * Create a collection of comparison result resources.
     *
     * @param  mixed  $resource
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public static function collection($resource)
    {
        // Handle both arrays and collection objects
        if (is_array($resource)) {
            return parent::collection(collect($resource));
        }

        return parent::collection($resource);
    }
}
