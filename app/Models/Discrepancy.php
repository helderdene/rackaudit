<?php

namespace App\Models;

use App\Enums\DiscrepancyStatus;
use App\Enums\DiscrepancyType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Discrepancy model representing detected differences between expected and actual connections.
 *
 * Discrepancies are automatically detected when comparing expected connections from
 * implementation files against actual documented connections. They track issues that
 * need resolution and can be imported into audits as verification items.
 *
 * Supports status tracking (open, acknowledged, resolved, in_audit) with timestamps
 * and user references for audit trail purposes.
 */
class Discrepancy extends Model
{
    /** @use HasFactory<\Database\Factories\DiscrepancyFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'datacenter_id',
        'room_id',
        'implementation_file_id',
        'discrepancy_type',
        'status',
        'source_port_id',
        'dest_port_id',
        'connection_id',
        'expected_connection_id',
        'expected_config',
        'actual_config',
        'mismatch_details',
        'title',
        'description',
        'detected_at',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
        'resolved_by',
        'audit_id',
        'finding_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discrepancy_type' => DiscrepancyType::class,
            'status' => DiscrepancyStatus::class,
            'expected_config' => 'array',
            'actual_config' => 'array',
            'mismatch_details' => 'array',
            'detected_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Get the datacenter that this discrepancy belongs to.
     */
    public function datacenter(): BelongsTo
    {
        return $this->belongsTo(Datacenter::class);
    }

    /**
     * Get the room that this discrepancy belongs to (optional).
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the implementation file associated with this discrepancy.
     */
    public function implementationFile(): BelongsTo
    {
        return $this->belongsTo(ImplementationFile::class);
    }

    /**
     * Get the source port of this discrepancy.
     */
    public function sourcePort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'source_port_id');
    }

    /**
     * Get the destination port of this discrepancy.
     */
    public function destPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'dest_port_id');
    }

    /**
     * Get the actual connection associated with this discrepancy.
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    /**
     * Get the expected connection associated with this discrepancy.
     */
    public function expectedConnection(): BelongsTo
    {
        return $this->belongsTo(ExpectedConnection::class);
    }

    /**
     * Get the user who acknowledged this discrepancy.
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * Get the user who resolved this discrepancy.
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Get the audit this discrepancy was imported into.
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    /**
     * Get the finding created from this discrepancy.
     */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    /**
     * Scope to filter open discrepancies only.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', DiscrepancyStatus::Open);
    }

    /**
     * Scope to filter acknowledged discrepancies only.
     */
    public function scopeAcknowledged(Builder $query): Builder
    {
        return $query->where('status', DiscrepancyStatus::Acknowledged);
    }

    /**
     * Scope to filter resolved discrepancies only.
     */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', DiscrepancyStatus::Resolved);
    }

    /**
     * Scope to filter discrepancies that are in audit.
     */
    public function scopeInAudit(Builder $query): Builder
    {
        return $query->where('status', DiscrepancyStatus::InAudit);
    }

    /**
     * Scope to filter discrepancies for a specific datacenter.
     */
    public function scopeForDatacenter(Builder $query, int $datacenterId): Builder
    {
        return $query->where('datacenter_id', $datacenterId);
    }

    /**
     * Scope to filter discrepancies for a specific room.
     */
    public function scopeForRoom(Builder $query, int $roomId): Builder
    {
        return $query->where('room_id', $roomId);
    }

    /**
     * Scope to filter discrepancies by type.
     */
    public function scopeForType(Builder $query, DiscrepancyType $type): Builder
    {
        return $query->where('discrepancy_type', $type);
    }

    /**
     * Scope to filter discrepancies detected within a date range.
     */
    public function scopeDetectedBetween(Builder $query, $start, $end): Builder
    {
        return $query->whereBetween('detected_at', [$start, $end]);
    }
}
