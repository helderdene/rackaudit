<?php

namespace App\Models;

use App\Enums\DiscrepancyType;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * AuditConnectionVerification model representing verification status of a connection during an audit.
 *
 * Tracks the verification state of each connection comparison result during an audit.
 * Supports locking for multi-operator concurrent access and records verification details.
 */
class AuditConnectionVerification extends Model
{
    /** @use HasFactory<\Database\Factories\AuditConnectionVerificationFactory> */
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
        'expected_connection_id',
        'connection_id',
        'discrepancy_type',
        'comparison_status',
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
            'discrepancy_type' => DiscrepancyType::class,
            'comparison_status' => DiscrepancyType::class,
            'verification_status' => VerificationStatus::class,
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
     * Get the expected connection being verified (optional for unexpected connections).
     */
    public function expectedConnection(): BelongsTo
    {
        return $this->belongsTo(ExpectedConnection::class);
    }

    /**
     * Get the actual connection being verified (optional for missing connections).
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    /**
     * Get the user who verified this connection.
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Get the user who currently has this connection locked.
     */
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * Get the finding created when this verification was marked as discrepant.
     */
    public function finding(): HasOne
    {
        return $this->hasOne(Finding::class, 'audit_connection_verification_id');
    }

    /**
     * Scope query to only pending verifications.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('verification_status', VerificationStatus::Pending);
    }

    /**
     * Scope query to only verified verifications.
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verification_status', VerificationStatus::Verified);
    }

    /**
     * Scope query to only discrepant verifications.
     */
    public function scopeDiscrepant(Builder $query): Builder
    {
        return $query->where('verification_status', VerificationStatus::Discrepant);
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
        $this->verification_status = VerificationStatus::Verified;
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
    public function markDiscrepant(User $user, DiscrepancyType $discrepancyType, string $notes): void
    {
        $this->verification_status = VerificationStatus::Discrepant;
        $this->discrepancy_type = $discrepancyType;
        $this->verified_by = $user->id;
        $this->verified_at = now();
        $this->notes = $notes;
        $this->locked_by = null;
        $this->locked_at = null;
        $this->save();
    }
}
