<?php

namespace App\Models;

use App\Enums\RowOrientation;
use App\Enums\RowStatus;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Row model representing a lane of racks within a room.
 *
 * Rows are positioned within a room and have hot/cold aisle orientation
 * for proper cooling management. Each row can have PDUs assigned to it
 * for row-level power distribution.
 */
class Row extends Model
{
    use HasFactory, Loggable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'position',
        'orientation',
        'status',
        'room_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orientation' => RowOrientation::class,
            'status' => RowStatus::class,
            'position' => 'integer',
        ];
    }

    /**
     * Get the room that this row belongs to.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the row-level PDUs assigned to this row.
     */
    public function pdus(): HasMany
    {
        return $this->hasMany(Pdu::class);
    }

    /**
     * Get the racks within this row.
     */
    public function racks(): HasMany
    {
        return $this->hasMany(Rack::class);
    }
}
