<?php

namespace App\Http\Resources;

use App\Models\BulkImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for transforming BulkImport model data.
 *
 * Provides consistent JSON representation of bulk imports including
 * status, progress, and result information.
 *
 * @mixin BulkImport
 */
class BulkImportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Entity type information
            'entity_type' => $this->entity_type?->value,
            'entity_type_label' => $this->entity_type?->label(),

            // File information
            'file_name' => $this->file_name,

            // Status information
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),

            // Progress tracking
            'total_rows' => $this->total_rows,
            'processed_rows' => $this->processed_rows,
            'success_count' => $this->success_count,
            'failure_count' => $this->failure_count,
            'progress_percentage' => $this->progress_percentage,

            // Error report availability
            'has_errors' => $this->failure_count > 0,
            'has_error_report' => ! empty($this->error_report_path),

            // User who initiated the import
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),

            // Timestamps
            'created_at' => $this->created_at,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
        ];
    }
}
