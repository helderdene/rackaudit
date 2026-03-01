<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DashboardSnapshot model for storing historical audit and activity metrics.
 *
 * Captures daily snapshots of audit findings counts by severity,
 * pending/completed audit counts, and activity metrics grouped by entity type
 * for dashboard trend analysis and historical reporting.
 */
class DashboardSnapshot extends Model
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
        'open_findings_count',
        'critical_findings_count',
        'high_findings_count',
        'medium_findings_count',
        'low_findings_count',
        'pending_audits_count',
        'completed_audits_count',
        'activity_count',
        'activity_by_entity',
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
            'open_findings_count' => 'integer',
            'critical_findings_count' => 'integer',
            'high_findings_count' => 'integer',
            'medium_findings_count' => 'integer',
            'low_findings_count' => 'integer',
            'pending_audits_count' => 'integer',
            'completed_audits_count' => 'integer',
            'activity_count' => 'integer',
            'activity_by_entity' => 'array',
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
