<?php

namespace App\Models;

use App\Enums\BulkImportEntityType;
use App\Enums\BulkImportStatus;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BulkImport model for tracking import job status and progress.
 *
 * BulkImport records track the status, progress, and results of bulk
 * import operations for datacenter infrastructure data (datacenters,
 * rooms, rows, racks, devices, and ports).
 */
class BulkImport extends Model
{
    use HasFactory, Loggable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'entity_type',
        'file_name',
        'file_path',
        'status',
        'total_rows',
        'processed_rows',
        'success_count',
        'failure_count',
        'error_report_path',
        'started_at',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_type' => BulkImportEntityType::class,
            'status' => BulkImportStatus::class,
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'success_count' => 'integer',
            'failure_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the user who initiated the import.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the progress percentage of the import.
     *
     * Returns 0 if total_rows is 0 to avoid division by zero.
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_rows === 0) {
            return 0.0;
        }

        return round(($this->processed_rows / $this->total_rows) * 100, 1);
    }
}
