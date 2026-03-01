<?php

namespace App\Models;

use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Datacenter model representing a physical data center location.
 *
 * Stores location information, contact details, and floor plan references
 * for datacenter facilities. Supports user access control through the
 * User-Datacenter pivot relationship.
 */
class Datacenter extends Model
{
    use HasFactory, Loggable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'address_line_1',
        'address_line_2',
        'city',
        'state_province',
        'postal_code',
        'country',
        'company_name',
        'primary_contact_name',
        'primary_contact_email',
        'primary_contact_phone',
        'secondary_contact_name',
        'secondary_contact_email',
        'secondary_contact_phone',
        'floor_plan_path',
    ];

    /**
     * Get the users that have access to this datacenter.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Get the rooms within this datacenter.
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Get the implementation files for this datacenter.
     */
    public function implementationFiles(): HasMany
    {
        return $this->hasMany(ImplementationFile::class);
    }

    /**
     * Get the capacity snapshots for this datacenter.
     */
    public function capacitySnapshots(): HasMany
    {
        return $this->hasMany(CapacitySnapshot::class);
    }

    /**
     * Get the dashboard snapshots for this datacenter.
     */
    public function dashboardSnapshots(): HasMany
    {
        return $this->hasMany(DashboardSnapshot::class);
    }

    /**
     * Check if this datacenter has any approved implementation files.
     *
     * Used to determine if the datacenter has authoritative sources
     * ready for use in audits.
     */
    public function hasApprovedImplementationFiles(): bool
    {
        return $this->implementationFiles()
            ->where('approval_status', 'approved')
            ->exists();
    }

    /**
     * Scope to filter datacenters that have approved implementation files.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Datacenter>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Datacenter>
     */
    public function scopeWithApprovedImplementationFiles($query)
    {
        return $query->whereHas('implementationFiles', function ($q) {
            $q->where('approval_status', 'approved');
        });
    }

    /**
     * Get the formatted full address for display purposes.
     *
     * Combines all address fields into a readable multi-line format:
     * - Address Line 1
     * - Address Line 2 (if present)
     * - City, State/Province Postal Code
     * - Country
     */
    protected function formattedAddress(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $lines = [];

                $lines[] = $this->address_line_1;

                if ($this->address_line_2) {
                    $lines[] = $this->address_line_2;
                }

                $lines[] = "{$this->city}, {$this->state_province} {$this->postal_code}";
                $lines[] = $this->country;

                return implode("\n", $lines);
            }
        );
    }

    /**
     * Get the formatted location summary for display (city, country).
     *
     * Returns a concise location string suitable for table displays
     * and list views.
     */
    protected function formattedLocation(): Attribute
    {
        return Attribute::make(
            get: fn (): string => "{$this->city}, {$this->country}"
        );
    }
}
