<?php

namespace App\Models;

use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EquipmentMove model representing a device move request within the datacenter.
 *
 * Tracks the complete lifecycle of equipment moves from request through approval
 * to execution. Captures source and destination locations, connection snapshots
 * before disconnection, and maintains a full audit trail via the Loggable trait.
 *
 * Status workflow: pending_approval -> approved -> executed
 *                                   -> rejected
 *                  pending_approval -> cancelled
 */
class EquipmentMove extends Model
{
    use HasFactory, Loggable;

    /**
     * Enable full state logging for activity logs.
     *
     * When true, the Loggable trait will capture complete state snapshots
     * on updates, not just the changed fields. This is important for
     * move history where we need the full before/after state.
     */
    protected bool $logFullState = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'device_id',
        'source_rack_id',
        'destination_rack_id',
        'source_start_u',
        'destination_start_u',
        'source_rack_face',
        'destination_rack_face',
        'source_width_type',
        'destination_width_type',
        'status',
        'connections_snapshot',
        'requested_by',
        'approved_by',
        'operator_notes',
        'approval_notes',
        'requested_at',
        'approved_at',
        'executed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_start_u' => 'integer',
            'destination_start_u' => 'integer',
            'source_rack_face' => DeviceRackFace::class,
            'destination_rack_face' => DeviceRackFace::class,
            'source_width_type' => DeviceWidthType::class,
            'destination_width_type' => DeviceWidthType::class,
            'connections_snapshot' => 'array',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    /**
     * Get the device being moved.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Get the source rack where the device is currently located.
     */
    public function sourceRack(): BelongsTo
    {
        return $this->belongsTo(Rack::class, 'source_rack_id');
    }

    /**
     * Get the destination rack where the device will be moved.
     */
    public function destinationRack(): BelongsTo
    {
        return $this->belongsTo(Rack::class, 'destination_rack_id');
    }

    /**
     * Get the user who requested this move.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the user who approved or rejected this move.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if this move is pending approval.
     */
    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }

    /**
     * Check if this move has been approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if this move has been rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if this move has been executed.
     */
    public function isExecuted(): bool
    {
        return $this->status === 'executed';
    }

    /**
     * Check if this move has been cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Get enriched attributes for activity logging.
     *
     * Resolves foreign key references to include human-readable values
     * in activity log snapshots for historical context.
     *
     * @return array<string, mixed>
     */
    public function getEnrichedAttributesForLog(): array
    {
        $enriched = [];

        // Resolve device name
        $device = $this->device;
        if ($device) {
            $enriched['device_name'] = $device->name;
            $enriched['device_asset_tag'] = $device->asset_tag;
        }

        // Resolve source rack name and location
        $sourceRack = $this->sourceRack;
        if ($sourceRack) {
            $enriched['source_rack_name'] = $sourceRack->name;
        }

        // Resolve destination rack name and location
        $destinationRack = $this->destinationRack;
        if ($destinationRack) {
            $enriched['destination_rack_name'] = $destinationRack->name;
        }

        // Resolve requester name
        $requester = $this->requester;
        if ($requester) {
            $enriched['requester_name'] = $requester->name;
        }

        // Resolve approver name
        $approver = $this->approver;
        if ($approver) {
            $enriched['approver_name'] = $approver->name;
        }

        // Add rack face labels
        if ($this->source_rack_face instanceof DeviceRackFace) {
            $enriched['source_rack_face_label'] = $this->source_rack_face->label();
        }
        if ($this->destination_rack_face instanceof DeviceRackFace) {
            $enriched['destination_rack_face_label'] = $this->destination_rack_face->label();
        }

        // Add width type labels
        if ($this->source_width_type instanceof DeviceWidthType) {
            $enriched['source_width_type_label'] = $this->source_width_type->label();
        }
        if ($this->destination_width_type instanceof DeviceWidthType) {
            $enriched['destination_width_type_label'] = $this->destination_width_type->label();
        }

        return $enriched;
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeWhereStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by device.
     */
    public function scopeForDevice(Builder $query, int $deviceId): Builder
    {
        return $query->where('device_id', $deviceId);
    }

    /**
     * Scope a query to filter by rack (source or destination).
     */
    public function scopeForRack(Builder $query, int $rackId): Builder
    {
        return $query->where(function (Builder $q) use ($rackId) {
            $q->where('source_rack_id', $rackId)
                ->orWhere('destination_rack_id', $rackId);
        });
    }

    /**
     * Scope a query to filter by date range (based on requested_at).
     */
    public function scopeRequestedBetween(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('requested_at', [$startDate, $endDate]);
    }
}
