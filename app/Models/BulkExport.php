<?php

namespace App\Models;

use App\Enums\BulkExportStatus;
use App\Enums\BulkImportEntityType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BulkExport model for tracking export job status and progress.
 *
 * BulkExport records track the status, progress, and results of bulk
 * export operations for datacenter infrastructure data (datacenters,
 * rooms, rows, racks, devices, and ports).
 */
class BulkExport extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'entity_type',
        'format',
        'file_name',
        'file_path',
        'status',
        'total_rows',
        'processed_rows',
        'filters',
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
            'status' => BulkExportStatus::class,
            'format' => 'string',
            'filters' => 'array',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the user who initiated the export.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the progress percentage of the export.
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
