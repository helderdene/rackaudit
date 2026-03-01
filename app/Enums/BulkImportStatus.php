<?php

namespace App\Enums;

/**
 * Bulk import job status.
 *
 * Indicates the current processing state of a bulk import job,
 * from pending through processing to completion or failure.
 */
enum BulkImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Get the human-readable label for the import status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
