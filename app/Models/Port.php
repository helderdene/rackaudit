<?php

namespace App\Models;

use App\Enums\PortDirection;
use App\Enums\PortStatus;
use App\Enums\PortSubtype;
use App\Enums\PortType;
use App\Enums\PortVisualFace;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Port model representing a physical port on a device.
 *
 * Ports are connection points on devices with type classification
 * (Ethernet, Fiber, Power), labeling, status tracking, and visual
 * position data to support future visual port mapping capabilities.
 */
class Port extends Model
{
    use HasFactory, Loggable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'device_id',
        'label',
        'type',
        'subtype',
        'status',
        'direction',
        'paired_port_id',
        'position_slot',
        'position_row',
        'position_column',
        'visual_x',
        'visual_y',
        'visual_face',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PortType::class,
            'subtype' => PortSubtype::class,
            'status' => PortStatus::class,
            'direction' => PortDirection::class,
            'visual_face' => PortVisualFace::class,
            'position_slot' => 'integer',
            'position_row' => 'integer',
            'position_column' => 'integer',
            'visual_x' => 'float',
            'visual_y' => 'float',
        ];
    }

    /**
     * Get the device that this port belongs to.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Get the paired port for patch panel connections.
     *
     * Used to link front and back ports on patch panels for
     * logical path derivation.
     */
    public function pairedPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'paired_port_id');
    }

    /**
     * Get the connection where this port is the source.
     */
    public function connectionAsSource(): HasOne
    {
        return $this->hasOne(Connection::class, 'source_port_id');
    }

    /**
     * Get the connection where this port is the destination.
     */
    public function connectionAsDestination(): HasOne
    {
        return $this->hasOne(Connection::class, 'destination_port_id');
    }

    /**
     * Get the connection for this port regardless of direction.
     *
     * Returns the connection where this port is either source or destination.
     */
    public function getConnectionAttribute(): ?Connection
    {
        return $this->connectionAsSource ?? $this->connectionAsDestination;
    }
}
