<?php

namespace App\Models;

use App\Enums\PduPhase;
use App\Enums\PduStatus;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Pdu model representing a Power Distribution Unit.
 *
 * PDUs can be assigned at room level (room_id set, row_id null) to serve
 * an entire room, or at row level (row_id set, room_id null) to serve
 * a specific row of racks. Exactly one of room_id or row_id must be set.
 * PDUs can also be assigned to multiple racks through a many-to-many relationship.
 */
class Pdu extends Model
{
    use HasFactory, Loggable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'model',
        'manufacturer',
        'total_capacity_kw',
        'voltage',
        'phase',
        'circuit_count',
        'status',
        'room_id',
        'row_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phase' => PduPhase::class,
            'status' => PduStatus::class,
            'total_capacity_kw' => 'decimal:2',
            'voltage' => 'integer',
            'circuit_count' => 'integer',
        ];
    }

    /**
     * Get the room this PDU is assigned to (room-level assignment).
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the row this PDU is assigned to (row-level assignment).
     */
    public function row(): BelongsTo
    {
        return $this->belongsTo(Row::class);
    }

    /**
     * Get the racks assigned to this PDU.
     */
    public function racks(): BelongsToMany
    {
        return $this->belongsToMany(Rack::class)->withTimestamps();
    }

    /**
     * Determine if this PDU is assigned at room level.
     */
    public function isRoomLevel(): bool
    {
        return $this->room_id !== null && $this->row_id === null;
    }

    /**
     * Determine if this PDU is assigned at row level.
     */
    public function isRowLevel(): bool
    {
        return $this->row_id !== null && $this->room_id === null;
    }
}
