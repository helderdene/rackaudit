<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CapacitySnapshot model for storing historical capacity metrics.
 *
 * Captures point-in-time capacity data for datacenters including
 * rack utilization, power consumption, port statistics, and device counts
 * for trend analysis and historical reporting.
 */
class CapacitySnapshot extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'datacenter_id',
        'snapshot_date',
        'rack_utilization_percent',
        'power_utilization_percent',
        'total_u_space',
        'used_u_space',
        'total_power_capacity',
        'total_power_consumption',
        'port_stats',
        'device_count',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'rack_utilization_percent' => 'decimal:2',
            'power_utilization_percent' => 'decimal:2',
            'total_u_space' => 'integer',
            'used_u_space' => 'integer',
            'total_power_capacity' => 'integer',
            'total_power_consumption' => 'integer',
            'port_stats' => 'array',
            'device_count' => 'integer',
        ];
    }

    /**
     * Get the datacenter that this snapshot belongs to.
     */
    public function datacenter(): BelongsTo
    {
        return $this->belongsTo(Datacenter::class);
    }
}
