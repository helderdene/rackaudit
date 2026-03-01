<?php

namespace App\Http\Resources;

use App\Models\AuditDeviceVerification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for transforming AuditDeviceVerification data.
 *
 * Provides consistent JSON representation of device verification records including
 * verification status, device details, rack/room context, lock status,
 * and verified by information.
 */
class AuditDeviceVerificationResource extends JsonResource
{
    /**
     * The resource instance.
     *
     * @var AuditDeviceVerification
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

            // Device details
            'device' => $this->transformDevice(),

            // Rack context
            'rack' => $this->transformRack(),

            // Room context (via rack relationship)
            'room' => $this->transformRoom(),

            // Verification status
            'verification_status' => $this->resource->verification_status->value,
            'verification_status_label' => $this->resource->verification_status->label(),

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
     * Transform device data.
     *
     * @return array<string, mixed>|null
     */
    protected function transformDevice(): ?array
    {
        $device = $this->resource->device;

        if (! $device) {
            return null;
        }

        return [
            'id' => $device->id,
            'name' => $device->name,
            'asset_tag' => $device->asset_tag,
            'serial_number' => $device->serial_number,
            'manufacturer' => $device->manufacturer,
            'model' => $device->model,
            'u_height' => $device->u_height,
            'start_u' => $device->start_u,
        ];
    }

    /**
     * Transform rack data.
     *
     * @return array<string, mixed>|null
     */
    protected function transformRack(): ?array
    {
        $rack = $this->resource->rack;

        if (! $rack) {
            return null;
        }

        return [
            'id' => $rack->id,
            'name' => $rack->name,
        ];
    }

    /**
     * Transform room data via rack relationship.
     *
     * @return array<string, mixed>|null
     */
    protected function transformRoom(): ?array
    {
        $room = $this->resource->rack?->row?->room;

        if (! $room) {
            return null;
        }

        return [
            'id' => $room->id,
            'name' => $room->name,
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
