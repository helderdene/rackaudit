<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * DeviceType model representing a category of datacenter devices.
 *
 * Device types allow users to categorize equipment according to their
 * organization's needs, with a default U size for rack placement.
 */
class DeviceType extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'default_u_size',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_u_size' => 'float',
        ];
    }

    /**
     * Get the devices that belong to this device type.
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}
