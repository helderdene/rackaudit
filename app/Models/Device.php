<?php

namespace App\Models;

use App\Enums\DeviceDepth;
use App\Enums\DeviceLifecycleStatus;
use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Device model representing a physical datacenter device/asset.
 *
 * Devices are equipment items that can be placed within racks.
 * Each device has an auto-generated asset tag, lifecycle status,
 * physical dimensions, and optional rack placement.
 */
class Device extends Model
{
    use HasFactory, Loggable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'device_type_id',
        'lifecycle_status',
        'serial_number',
        'manufacturer',
        'model',
        'purchase_date',
        'warranty_start_date',
        'warranty_end_date',
        'u_height',
        'depth',
        'width_type',
        'rack_face',
        'rack_id',
        'start_u',
        'specs',
        'notes',
        'power_draw_watts',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lifecycle_status' => DeviceLifecycleStatus::class,
            'depth' => DeviceDepth::class,
            'width_type' => DeviceWidthType::class,
            'rack_face' => DeviceRackFace::class,
            'u_height' => 'float',
            'start_u' => 'integer',
            'purchase_date' => 'date',
            'warranty_start_date' => 'date',
            'warranty_end_date' => 'date',
            'specs' => 'array',
            'power_draw_watts' => 'integer',
        ];
    }

    /**
     * Boot the model and register event listeners.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Device $device): void {
            if (empty($device->asset_tag)) {
                $device->asset_tag = self::generateAssetTag();
            }
        });
    }

    /**
     * Generate a unique asset tag in the format ASSET-{YYYYMMDD}-{sequential}.
     *
     * The sequential number is zero-padded to 5 digits and resets daily.
     */
    protected static function generateAssetTag(): string
    {
        $today = now()->format('Ymd');
        $prefix = "ASSET-{$today}-";

        // Get the highest sequential number for today
        $lastTag = DB::table('devices')
            ->where('asset_tag', 'LIKE', "{$prefix}%")
            ->orderBy('asset_tag', 'desc')
            ->value('asset_tag');

        if ($lastTag) {
            $lastSequential = (int) substr($lastTag, -5);
            $nextSequential = $lastSequential + 1;
        } else {
            $nextSequential = 1;
        }

        return $prefix.str_pad((string) $nextSequential, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get the device type that this device belongs to.
     */
    public function deviceType(): BelongsTo
    {
        return $this->belongsTo(DeviceType::class);
    }

    /**
     * Get the rack that this device is placed in.
     */
    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    /**
     * Get all ports that belong to this device.
     */
    public function ports(): HasMany
    {
        return $this->hasMany(Port::class);
    }
}
