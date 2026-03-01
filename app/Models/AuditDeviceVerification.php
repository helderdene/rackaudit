<?php

namespace App\Models;

use App\Enums\DeviceVerificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * AuditDeviceVerification model representing verification status of a device during an inventory audit.
 *
 * Tracks the verification state of each device during an audit.
 * Supports locking for multi-operator concurrent access and records verification details.
 */
class AuditDeviceVerification extends Model
{
    /** @use HasFactory<\Database\Factories\AuditDeviceVerificationFactory> */
    use HasFactory;

    /**
     * Lock expiration time in minutes.
     */
    public const LOCK_EXPIRATION_MINUTES = 5;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'audit_id',
        'device_id',
        'rack_id',
        'verification_status',
        'notes',
        'verified_by',
        'verified_at',
        'locked_by',
        'locked_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verification_status' => DeviceVerificationStatus::class,
            'verified_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    /**
     * Get the audit this verification belongs to.
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    /**
     * Get the device being verified.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Get the rack context for this verification.
     */
    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    /**
     * Get the user who verified this device.
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Get the user who currently has this device locked.
     */
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * Get the finding created when this verification was marked as not found or discrepant.
     */
    public function finding(): HasOne
    {
        return $this->hasOne(Finding::class, 'audit_device_verification_id');
    }

    /**
     * Scope query to only pending verifications.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('verification_status', DeviceVerificationStatus::Pending);
    }

    /**
     * Scope query to only verified verifications.
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verification_status', DeviceVerificationStatus::Verified);
    }

    /**
     * Scope query to only not found verifications.
     */
    public function scopeNotFound(Builder $query): Builder
    {
        return $query->where('verification_status', DeviceVerificationStatus::NotFound);
    }

    /**
     * Scope query to only discrepant verifications.
     */
    public function scopeDiscrepant(Builder $query): Builder
    {
        return $query->where('verification_status', DeviceVerificationStatus::Discrepant);
    }

    /**
     * Scope query to only currently locked verifications (not expired).
     */
    public function scopeLocked(Builder $query): Builder
    {
        return $query->whereNotNull('locked_by')
            ->where('locked_at', '>', now()->subMinutes(self::LOCK_EXPIRATION_MINUTES));
    }

    /**
     * Scope query to only expired locks (locks older than expiration time).
     */
    public function scopeExpiredLocks(Builder $query): Builder
    {
        return $query->whereNotNull('locked_by')
            ->where('locked_at', '<=', now()->subMinutes(self::LOCK_EXPIRATION_MINUTES));
    }

    /**
     * Check if this verification is currently locked.
     */
    public function isLocked(): bool
    {
        if ($this->locked_by === null || $this->locked_at === null) {
            return false;
        }

        return $this->locked_at->gt(now()->subMinutes(self::LOCK_EXPIRATION_MINUTES));
    }

    /**
     * Check if this verification is locked by a specific user.
     */
    public function isLockedBy(User $user): bool
    {
        return $this->isLocked() && $this->locked_by === $user->id;
    }

    /**
     * Lock this verification for a user.
     */
    public function lockFor(User $user): bool
    {
        if ($this->isLocked() && ! $this->isLockedBy($user)) {
            return false;
        }

        $this->locked_by = $user->id;
        $this->locked_at = now();
        $this->save();

        return true;
    }

    /**
     * Unlock this verification.
     */
    public function unlock(): void
    {
        $this->locked_by = null;
        $this->locked_at = null;
        $this->save();
    }

    /**
     * Mark this verification as verified.
     */
    public function markVerified(User $user, ?string $notes = null): void
    {
        $this->verification_status = DeviceVerificationStatus::Verified;
        $this->verified_by = $user->id;
        $this->verified_at = now();
        $this->notes = $notes;
        $this->locked_by = null;
        $this->locked_at = null;
        $this->save();
    }

    /**
     * Mark this verification as not found.
     */
    public function markNotFound(User $user, string $notes): void
    {
        $this->verification_status = DeviceVerificationStatus::NotFound;
        $this->verified_by = $user->id;
        $this->verified_at = now();
        $this->notes = $notes;
        $this->locked_by = null;
        $this->locked_at = null;
        $this->save();
    }

    /**
     * Mark this verification as discrepant.
     */
    public function markDiscrepant(User $user, string $notes): void
    {
        $this->verification_status = DeviceVerificationStatus::Discrepant;
        $this->verified_by = $user->id;
        $this->verified_at = now();
        $this->notes = $notes;
        $this->locked_by = null;
        $this->locked_at = null;
        $this->save();
    }
}
