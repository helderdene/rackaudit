<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AuditRackVerification model representing verification of an empty rack during an inventory audit.
 *
 * Empty racks need explicit "nothing found here" confirmation during inventory audits.
 * This tracks whether the operator has verified that an expected-empty rack is indeed empty.
 */
class AuditRackVerification extends Model
{
    /** @use HasFactory<\Database\Factories\AuditRackVerificationFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'audit_id',
        'rack_id',
        'verified',
        'notes',
        'verified_by',
        'verified_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
            'verified_at' => 'datetime',
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
     * Get the rack being verified.
     */
    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    /**
     * Get the user who verified this rack.
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Mark this rack as verified (confirmed empty).
     */
    public function markVerified(User $user, ?string $notes = null): void
    {
        $this->verified = true;
        $this->verified_by = $user->id;
        $this->verified_at = now();
        $this->notes = $notes;
        $this->save();
    }
}
