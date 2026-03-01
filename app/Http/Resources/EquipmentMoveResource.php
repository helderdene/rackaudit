<?php

namespace App\Http\Resources;

use App\Models\EquipmentMove;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * API Resource for transforming EquipmentMove model data.
 *
 * Provides consistent JSON representation of equipment move requests
 * including device details, source/destination locations, connection
 * snapshots, and workflow status information.
 *
 * @mixin EquipmentMove
 */
class EquipmentMoveResource extends JsonResource
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

            // Status information
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'is_pending' => $this->isPendingApproval(),
            'is_approved' => $this->isApproved(),
            'is_executed' => $this->isExecuted(),
            'is_rejected' => $this->isRejected(),
            'is_cancelled' => $this->isCancelled(),

            // Device details with location hierarchy
            'device' => $this->whenLoaded('device', fn () => [
                'id' => $this->device->id,
                'name' => $this->device->name,
                'asset_tag' => $this->device->asset_tag,
                'serial_number' => $this->device->serial_number,
                'manufacturer' => $this->device->manufacturer,
                'model' => $this->device->model,
                'u_height' => $this->device->u_height,
                'device_type' => $this->device->relationLoaded('deviceType') && $this->device->deviceType ? [
                    'id' => $this->device->deviceType->id,
                    'name' => $this->device->deviceType->name,
                ] : null,
            ]),

            // Source location details
            'source_rack' => $this->whenLoaded('sourceRack', fn () => $this->formatRackDetails($this->sourceRack)),
            'source_start_u' => $this->source_start_u,
            'source_rack_face' => $this->source_rack_face?->value,
            'source_rack_face_label' => $this->source_rack_face?->label(),
            'source_width_type' => $this->source_width_type?->value,
            'source_width_type_label' => $this->source_width_type?->label(),

            // Destination location details
            'destination_rack' => $this->whenLoaded('destinationRack', fn () => $this->formatRackDetails($this->destinationRack)),
            'destination_start_u' => $this->destination_start_u,
            'destination_rack_face' => $this->destination_rack_face?->value,
            'destination_rack_face_label' => $this->destination_rack_face?->label(),
            'destination_width_type' => $this->destination_width_type?->value,
            'destination_width_type_label' => $this->destination_width_type?->label(),

            // Connections snapshot formatted for display
            'connections_snapshot' => $this->formatConnectionsSnapshot(),

            // User information
            'requester' => $this->whenLoaded('requester', fn () => [
                'id' => $this->requester->id,
                'name' => $this->requester->name,
            ]),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ] : null),

            // Notes
            'operator_notes' => $this->operator_notes,
            'approval_notes' => $this->approval_notes,

            // Timestamps
            'requested_at' => $this->requested_at?->toIso8601String(),
            'requested_at_formatted' => $this->requested_at?->format('M j, Y g:i A'),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'approved_at_formatted' => $this->approved_at?->format('M j, Y g:i A'),
            'executed_at' => $this->executed_at?->toIso8601String(),
            'executed_at_formatted' => $this->executed_at?->format('M j, Y g:i A'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Authorization flags
            'can_approve' => $this->canUserApprove($request),
            'can_reject' => $this->canUserReject($request),
            'can_cancel' => $this->canUserCancel($request),
        ];
    }

    /**
     * Get a human-readable status label.
     */
    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending_approval' => 'Pending Approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'executed' => 'Executed',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status ?? 'unknown'),
        };
    }

    /**
     * Format rack details including location hierarchy.
     *
     * @return array<string, mixed>|null
     */
    private function formatRackDetails($rack): ?array
    {
        if (! $rack) {
            return null;
        }

        $row = $rack->row;
        $room = $row?->room;
        $datacenter = $room?->datacenter;

        return [
            'id' => $rack->id,
            'name' => $rack->name,
            'position' => $rack->position,
            'u_height' => $rack->u_height instanceof \App\Enums\RackUHeight
                ? $rack->u_height->value
                : $rack->u_height,
            'row' => $row ? [
                'id' => $row->id,
                'name' => $row->name,
            ] : null,
            'room' => $room ? [
                'id' => $room->id,
                'name' => $room->name,
            ] : null,
            'datacenter' => $datacenter ? [
                'id' => $datacenter->id,
                'name' => $datacenter->name,
            ] : null,
            'location_path' => $this->buildLocationPath($datacenter, $room, $row, $rack),
        ];
    }

    /**
     * Build a human-readable location path.
     */
    private function buildLocationPath($datacenter, $room, $row, $rack): string
    {
        $parts = array_filter([
            $datacenter?->name,
            $room?->name,
            $row?->name,
            $rack?->name,
        ]);

        return implode(' > ', $parts);
    }

    /**
     * Format connections snapshot for display.
     *
     * @return array<int, array<string, mixed>>
     */
    private function formatConnectionsSnapshot(): array
    {
        if (empty($this->connections_snapshot)) {
            return [];
        }

        return collect($this->connections_snapshot)
            ->map(fn (array $connection) => [
                'id' => $connection['id'] ?? null,
                'source_port_label' => $connection['source_port_label'] ?? 'Unknown',
                'destination_port_label' => $connection['destination_port_label'] ?? 'Unknown',
                'destination_device_name' => $connection['destination_device_name'] ?? 'Unknown',
                'cable_type' => $connection['cable_type'] ?? null,
                'cable_length' => $connection['cable_length'] ?? null,
                'cable_color' => $connection['cable_color'] ?? null,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Check if the current user can approve this move.
     */
    private function canUserApprove(Request $request): bool
    {
        $user = $request->user();

        if (! $user || ! $this->isPendingApproval()) {
            return false;
        }

        return Gate::allows('approve', $this->resource);
    }

    /**
     * Check if the current user can reject this move.
     */
    private function canUserReject(Request $request): bool
    {
        $user = $request->user();

        if (! $user || ! $this->isPendingApproval()) {
            return false;
        }

        return Gate::allows('reject', $this->resource);
    }

    /**
     * Check if the current user can cancel this move.
     */
    private function canUserCancel(Request $request): bool
    {
        $user = $request->user();

        if (! $user || ! $this->isPendingApproval()) {
            return false;
        }

        return Gate::allows('cancel', $this->resource);
    }
}
