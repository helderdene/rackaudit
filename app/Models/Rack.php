<?php

namespace App\Models;

use App\Enums\RackStatus;
use App\Enums\RackUHeight;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rack model representing a physical rack within a row.
 *
 * Racks are equipment containers that hold servers and other datacenter
 * equipment. Each rack has a U-height indicating the number of rack units
 * available for device placement.
 */
class Rack extends Model
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
        'u_height',
        'serial_number',
        'status',
        'power_capacity_watts',
        'row_id',
        'manufacturer',
        'model',
        'depth',
        'installation_date',
        'location_notes',
        'specs',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RackStatus::class,
            'u_height' => RackUHeight::class,
            'position' => 'integer',
            'power_capacity_watts' => 'integer',
            'installation_date' => 'date',
            'specs' => 'array',
        ];
    }

    /**
     * Get the row that this rack belongs to.
     */
    public function row(): BelongsTo
    {
        return $this->belongsTo(Row::class);
    }

    /**
     * Get the PDUs assigned to this rack.
     */
    public function pdus(): BelongsToMany
    {
        return $this->belongsToMany(Pdu::class)->withTimestamps();
    }

    /**
     * Get the devices placed in this rack.
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}
