<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Execution history record for a report schedule.
 *
 * Tracks each execution attempt of a scheduled report, including status,
 * timing, error messages, and metrics like file size and recipient count.
 * Used for troubleshooting and monitoring schedule health.
 */
class ReportScheduleExecution extends Model
{
    /** @use HasFactory<\Database\Factories\ReportScheduleExecutionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'report_schedule_id',
        'status',
        'started_at',
        'completed_at',
        'error_message',
        'file_size_bytes',
        'recipients_count',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'file_size_bytes' => 'integer',
            'recipients_count' => 'integer',
        ];
    }

    /**
     * Get the schedule this execution belongs to.
     */
    public function reportSchedule(): BelongsTo
    {
        return $this->belongsTo(ReportSchedule::class);
    }
}
