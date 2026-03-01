<?php

namespace App\Models;

use App\Enums\CableType;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Connection model representing a physical cable connection between two ports.
 *
 * Connections track cable properties (type, length, color) and support
 * logical path derivation through patch panel port pairs. Uses soft
 * deletes for historical tracking.
 */
class Connection extends Model
{
    /** @use HasFactory<\Database\Factories\ConnectionFactory> */
    use HasFactory, Loggable, SoftDeletes;

    /**
     * Enable full state logging for activity logs.
     *
     * When true, the Loggable trait will capture complete state snapshots
     * on updates, not just the changed fields. This is important for
     * connection history where we need the full before/after state.
     */
    protected bool $logFullState = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'source_port_id',
        'destination_port_id',
        'cable_type',
        'cable_length',
        'cable_color',
        'path_notes',
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
        ];
    }

    /**
     * Get the source port of this connection.
     */
    public function sourcePort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'source_port_id');
    }

    /**
     * Get the destination port of this connection.
     */
    public function destinationPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'destination_port_id');
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

        // Resolve source port label and device name
        $sourcePort = $this->sourcePort;
        if ($sourcePort) {
            $enriched['source_port_label'] = $sourcePort->label;
            $sourceDevice = $sourcePort->device;
            if ($sourceDevice) {
                $enriched['source_device_name'] = $sourceDevice->name;
            }
        }

        // Resolve destination port label and device name
        $destinationPort = $this->destinationPort;
        if ($destinationPort) {
            $enriched['destination_port_label'] = $destinationPort->label;
            $destinationDevice = $destinationPort->device;
            if ($destinationDevice) {
                $enriched['destination_device_name'] = $destinationDevice->name;
            }
        }

        // Add cable type human-readable label
        $cableType = $this->cable_type;
        if ($cableType instanceof CableType) {
            $enriched['cable_type_label'] = $cableType->label();
        }

        return $enriched;
    }

    /**
     * Get the logical path from source to destination, traversing patch panel port pairs.
     *
     * Follows paired ports to determine the true end-to-end path:
     * Example: Server Port -> Patch Panel Front -> (paired) Patch Panel Back -> Switch Port
     *
     * @return array<Port>
     */
    public function getLogicalPath(): array
    {
        $path = [];

        // Start from source port
        $currentPort = $this->sourcePort;

        // If source port has a paired port, the actual source is the paired port
        if ($currentPort->pairedPort) {
            $path[] = $currentPort->pairedPort;
            $path[] = $currentPort;
        } else {
            $path[] = $currentPort;
        }

        // End with destination port
        $destPort = $this->destinationPort;

        // If destination port has a paired port, include both in the path
        if ($destPort->pairedPort) {
            $path[] = $destPort;
            $path[] = $destPort->pairedPort;
        } else {
            $path[] = $destPort;
        }

        return $path;
    }
}
