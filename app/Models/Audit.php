<?php

namespace App\Models;

use App\Enums\AuditScopeType;
use App\Enums\AuditStatus;
use App\Enums\AuditType;
use App\Enums\DeviceVerificationStatus;
use App\Enums\VerificationStatus;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Audit model representing a datacenter audit session.
 *
 * Audits are used to verify physical datacenter configurations against
 * expected states. Supports two types:
 * - Connection audits verify physical connections match approved implementation files
 * - Inventory audits verify documented devices exist and are in correct positions
 *
 * Scope can be at the datacenter, room, or individual racks/devices level.
 */
class Audit extends Model
{
    use HasFactory, Loggable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'due_date',
        'type',
        'scope_type',
        'status',
        'datacenter_id',
        'room_id',
        'implementation_file_id',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AuditType::class,
            'scope_type' => AuditScopeType::class,
            'status' => AuditStatus::class,
            'due_date' => 'date',
        ];
    }

    /**
     * Get the datacenter that this audit covers.
     */
    public function datacenter(): BelongsTo
    {
        return $this->belongsTo(Datacenter::class);
    }

    /**
     * Get the room that this audit covers (for room-level scope).
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the implementation file used for connection audits.
     */
    public function implementationFile(): BelongsTo
    {
        return $this->belongsTo(ImplementationFile::class);
    }

    /**
     * Get the user who created this audit.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the users assigned to execute this audit.
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'audit_user')
            ->withTimestamps();
    }

    /**
     * Get the racks included in this audit scope.
     */
    public function racks(): BelongsToMany
    {
        return $this->belongsToMany(Rack::class, 'audit_rack')
            ->withTimestamps();
    }

    /**
     * Get the devices included in this audit scope.
     */
    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'audit_device')
            ->withTimestamps();
    }

    /**
     * Get the connection verifications for this audit.
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(AuditConnectionVerification::class);
    }

    /**
     * Get the device verifications for this audit.
     */
    public function deviceVerifications(): HasMany
    {
        return $this->hasMany(AuditDeviceVerification::class);
    }

    /**
     * Get the rack verifications for this audit (empty rack confirmations).
     */
    public function rackVerifications(): HasMany
    {
        return $this->hasMany(AuditRackVerification::class);
    }

    /**
     * Get all findings discovered during this audit.
     */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    /**
     * Get all generated reports for this audit.
     *
     * Returns reports ordered by generated_at descending (most recent first).
     */
    public function reports(): HasMany
    {
        return $this->hasMany(AuditReport::class)->orderByDesc('generated_at');
    }

    /**
     * Get the total number of verifications for this audit.
     */
    public function totalVerifications(): int
    {
        return $this->verifications()->count();
    }

    /**
     * Get the number of completed verifications (verified or discrepant).
     */
    public function completedVerifications(): int
    {
        return $this->verifications()
            ->whereIn('verification_status', [
                VerificationStatus::Verified,
                VerificationStatus::Discrepant,
            ])
            ->count();
    }

    /**
     * Get the number of pending verifications.
     */
    public function pendingVerifications(): int
    {
        return $this->verifications()
            ->where('verification_status', VerificationStatus::Pending)
            ->count();
    }

    /**
     * Get the total number of device verifications for this audit.
     */
    public function totalDeviceVerifications(): int
    {
        return $this->deviceVerifications()->count();
    }

    /**
     * Get the number of pending device verifications.
     */
    public function pendingDeviceVerifications(): int
    {
        return $this->deviceVerifications()
            ->where('verification_status', DeviceVerificationStatus::Pending)
            ->count();
    }
}
