<?php

namespace App\Models;

use App\Enums\RoomType;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Room model representing a physical space within a datacenter.
 *
 * Rooms can contain rows for rack placement and PDUs for power distribution.
 * Each room belongs to a datacenter and inherits access permissions from
 * its parent datacenter.
 */
class Room extends Model
{
    use HasFactory, Loggable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'square_footage',
        'type',
        'datacenter_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RoomType::class,
            'square_footage' => 'decimal:2',
        ];
    }

    /**
     * Get the datacenter that this room belongs to.
     */
    public function datacenter(): BelongsTo
    {
        return $this->belongsTo(Datacenter::class);
    }

    /**
     * Get the rows within this room.
     */
    public function rows(): HasMany
    {
        return $this->hasMany(Row::class);
    }

    /**
     * Get the room-level PDUs assigned to this room.
     */
    public function pdus(): HasMany
    {
        return $this->hasMany(Pdu::class);
    }
}
