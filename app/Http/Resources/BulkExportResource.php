<?php

namespace App\Http\Resources;

use App\Enums\BulkExportStatus;
use App\Enums\BulkImportEntityType;
use App\Models\BulkExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for transforming BulkExport model data.
 *
 * Provides consistent JSON representation of bulk exports including
 * status, progress, and download information.
 *
 * @mixin BulkExport
 */
class BulkExportResource extends JsonResource
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

            // Format information
            'format' => $this->format,

            // File information
            'file_name' => $this->file_name,

            // Status information
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),

            // Progress tracking
            'total_rows' => $this->total_rows,
            'processed_rows' => $this->processed_rows,
            'progress_percentage' => $this->progress_percentage,

            // Filters applied to this export
            'filters' => $this->filters,

            // Download availability
            'is_completed' => $this->status === BulkExportStatus::Completed,
            'download_url' => $this->when(
                $this->status === BulkExportStatus::Completed && $this->file_path,
                fn () => $this->getDownloadUrl()
            ),

            // User who initiated the export
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

    /**
     * Get the download URL based on the entity type.
     */
    protected function getDownloadUrl(): string
    {
        if ($this->entity_type === BulkImportEntityType::ConnectionHistory) {
            return route('connections.history.export.download', $this->resource);
        }

        return route('exports.download', $this->resource);
    }
}
