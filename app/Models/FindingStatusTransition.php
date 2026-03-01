<?php

namespace App\Models;

use App\Enums\FindingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FindingStatusTransition model for tracking all status changes on findings.
 *
 * Records each transition between statuses including who made the change,
 * when it occurred, and any optional notes. Used for timeline display,
 * audit reporting, and calculating resolution metrics.
 */
class FindingStatusTransition extends Model
{
    /** @use HasFactory<\Database\Factories\FindingStatusTransitionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'finding_id',
        'from_status',
        'to_status',
        'user_id',
        'notes',
        'transitioned_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => FindingStatus::class,
            'to_status' => FindingStatus::class,
            'transitioned_at' => 'datetime',
        ];
    }

    /**
     * Get the finding this transition belongs to.
     */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    /**
     * Get the user who performed this transition.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
