<?php

namespace App\Models;

use App\Enums\DiscrepancyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DiscrepancyAcknowledgment model representing user acknowledgments of connection discrepancies.
 *
 * Discrepancy acknowledgments allow users to mark specific mismatches between expected
 * and actual connections as reviewed. This helps track which discrepancies have been
 * evaluated and any notes about why they exist or when they will be resolved.
 */
class DiscrepancyAcknowledgment extends Model
{
    /** @use HasFactory<\Database\Factories\DiscrepancyAcknowledgmentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'expected_connection_id',
        'connection_id',
        'discrepancy_type',
        'acknowledged_by',
        'acknowledged_at',
        'notes',
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
            'acknowledged_at' => 'datetime',
        ];
    }

    /**
     * Get the expected connection this acknowledgment relates to.
     */
    public function expectedConnection(): BelongsTo
    {
        return $this->belongsTo(ExpectedConnection::class);
    }

    /**
     * Get the actual connection this acknowledgment relates to.
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    /**
     * Get the user who acknowledged the discrepancy.
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
