<?php

namespace App\Http\Resources;

use App\Models\AuditConnectionVerification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for transforming AuditConnectionVerification data.
 *
 * Provides consistent JSON representation of verification records including
 * comparison status, verification status, source and destination details,
 * lock status, and verified by information.
 */
class AuditConnectionVerificationResource extends JsonResource
{
    /**
     * The resource instance.
     *
     * @var AuditConnectionVerification
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
            'id' => $this->resource->id,

            // Comparison and verification status
            'comparison_status' => $this->resource->comparison_status?->value,
            'comparison_status_label' => $this->resource->comparison_status?->label(),
            'verification_status' => $this->resource->verification_status->value,
            'verification_status_label' => $this->resource->verification_status->label(),
            'discrepancy_type' => $this->resource->discrepancy_type?->value,
            'discrepancy_type_label' => $this->resource->discrepancy_type?->label(),

            // Source device and port
            'source_device' => $this->transformSourceDevice(),
            'source_port' => $this->transformSourcePort(),

            // Destination device and port
            'dest_device' => $this->transformDestDevice(),
            'dest_port' => $this->transformDestPort(),

            // Expected connection details
            'expected_connection' => $this->transformExpectedConnection(),

            // Actual connection details
            'actual_connection' => $this->transformActualConnection(),

            // Row number from expected connection (for sorting)
            'row_number' => $this->resource->expectedConnection?->row_number,

            // Notes
            'notes' => $this->resource->notes,

            // Verification info
            'verified_by' => $this->transformVerifiedBy(),
            'verified_at' => $this->resource->verified_at?->toIso8601String(),

            // Lock info
            'locked_by' => $this->transformLockedBy(),
            'locked_at' => $this->resource->locked_at?->toIso8601String(),
            'is_locked' => $this->resource->isLocked(),

            // Timestamps
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Transform source device data.
     *
     * @return array<string, mixed>|null
     */
    protected function transformSourceDevice(): ?array
    {
        $device = $this->resource->expectedConnection?->sourceDevice
            ?? $this->resource->connection?->sourcePort?->device;

        if (! $device) {
            return null;
        }

        return [
            'id' => $device->id,
            'name' => $device->name,
            'asset_tag' => $device->asset_tag,
            'rack_id' => $device->rack_id,
        ];
    }

    /**
     * Transform source port data.
     *
     * @return array<string, mixed>|null
     */
    protected function transformSourcePort(): ?array
    {
        $port = $this->resource->expectedConnection?->sourcePort
            ?? $this->resource->connection?->sourcePort;

        if (! $port) {
            return null;
        }

        return [
            'id' => $port->id,
            'label' => $port->label,
            'type' => $port->type?->value,
            'type_label' => $port->type?->label(),
        ];
    }

    /**
     * Transform destination device data.
     *
     * @return array<string, mixed>|null
     */
    protected function transformDestDevice(): ?array
    {
        $device = $this->resource->expectedConnection?->destDevice
            ?? $this->resource->connection?->destinationPort?->device;

        if (! $device) {
            return null;
        }

        return [
            'id' => $device->id,
            'name' => $device->name,
            'asset_tag' => $device->asset_tag,
            'rack_id' => $device->rack_id,
        ];
    }

    /**
     * Transform destination port data.
     *
     * @return array<string, mixed>|null
     */
    protected function transformDestPort(): ?array
    {
        $port = $this->resource->expectedConnection?->destPort
            ?? $this->resource->connection?->destinationPort;

        if (! $port) {
            return null;
        }

        return [
            'id' => $port->id,
            'label' => $port->label,
            'type' => $port->type?->value,
            'type_label' => $port->type?->label(),
        ];
    }

    /**
     * Transform expected connection data.
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
            'status' => $expected->status?->value,
            'implementation_file_id' => $expected->implementation_file_id,
        ];
    }

    /**
     * Transform actual connection data.
     *
     * @return array<string, mixed>|null
     */
    protected function transformActualConnection(): ?array
    {
        $actual = $this->resource->connection;

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
     * Transform verified by user data.
     *
     * @return array<string, mixed>|null
     */
    protected function transformVerifiedBy(): ?array
    {
        $user = $this->resource->verifiedBy;

        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }

    /**
     * Transform locked by user data.
     *
     * @return array<string, mixed>|null
     */
    protected function transformLockedBy(): ?array
    {
        // Only show if actually locked (not expired)
        if (! $this->resource->isLocked()) {
            return null;
        }

        $user = $this->resource->lockedBy;

        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }
}
