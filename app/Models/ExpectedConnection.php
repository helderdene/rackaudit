<?php

namespace App\Models;

use App\Enums\CableType;
use App\Enums\ExpectedConnectionStatus;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ExpectedConnection model representing parsed connections from implementation files.
 *
 * Expected connections store port-to-port mappings extracted from Excel/CSV
 * implementation specification documents. They undergo a review process before
 * becoming authoritative for audit comparisons against actual connections.
 *
 * Supports soft deletes for archiving old versions when new implementation
 * file versions are uploaded.
 */
class ExpectedConnection extends Model
{
    /** @use HasFactory<\Database\Factories\ExpectedConnectionFactory> */
    use HasFactory, Loggable, SoftDeletes;

    /**
     * Enable full state logging for activity logs.
     *
     * When true, the Loggable trait will capture complete state snapshots
     * on updates, not just the changed fields. This is important for
     * expected connection history where we need the full before/after state.
     */
    protected bool $logFullState = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'implementation_file_id',
        'source_device_id',
        'source_port_id',
        'dest_device_id',
        'dest_port_id',
        'cable_type',
        'cable_length',
        'row_number',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cable_type' => CableType::class,
            'cable_length' => 'decimal:2',
            'row_number' => 'integer',
            'status' => ExpectedConnectionStatus::class,
        ];
    }

    /**
     * Get the implementation file that this expected connection belongs to.
     */
    public function implementationFile(): BelongsTo
    {
        return $this->belongsTo(ImplementationFile::class);
    }

    /**
     * Get the source device of this expected connection.
     */
    public function sourceDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'source_device_id');
    }

    /**
     * Get the destination device of this expected connection.
     */
    public function destDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'dest_device_id');
    }

    /**
     * Get the source port of this expected connection.
     */
    public function sourcePort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'source_port_id');
    }

    /**
     * Get the destination port of this expected connection.
     */
    public function destPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'dest_port_id');
    }

    /**
     * Scope to filter confirmed expected connections only.
     *
     * Used for the comparison view where only finalized connections
     * should be compared against actual connections.
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', ExpectedConnectionStatus::Confirmed);
    }

    /**
     * Scope to filter pending review expected connections.
     */
    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', ExpectedConnectionStatus::PendingReview);
    }

    /**
     * Scope to filter skipped expected connections.
     */
    public function scopeSkipped(Builder $query): Builder
    {
        return $query->where('status', ExpectedConnectionStatus::Skipped);
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

        // Resolve implementation file name
        $implementationFile = $this->implementationFile;
        if ($implementationFile) {
            $enriched['implementation_file_name'] = $implementationFile->original_name;
        }

        // Resolve source port label and device name
        $sourcePort = $this->sourcePort;
        if ($sourcePort) {
            $enriched['source_port_label'] = $sourcePort->label;
        }
        $sourceDevice = $this->sourceDevice;
        if ($sourceDevice) {
            $enriched['source_device_name'] = $sourceDevice->name;
        }

        // Resolve destination port label and device name
        $destPort = $this->destPort;
        if ($destPort) {
            $enriched['dest_port_label'] = $destPort->label;
        }
        $destDevice = $this->destDevice;
        if ($destDevice) {
            $enriched['dest_device_name'] = $destDevice->name;
        }

        // Add cable type human-readable label
        $cableType = $this->cable_type;
        if ($cableType instanceof CableType) {
            $enriched['cable_type_label'] = $cableType->label();
        }

        // Add status human-readable label
        $status = $this->status;
        if ($status instanceof ExpectedConnectionStatus) {
            $enriched['status_label'] = $status->label();
        }

        return $enriched;
    }
}
