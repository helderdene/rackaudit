<?php

namespace App\Http\Resources;

use App\Models\ReportScheduleExecution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for transforming ReportScheduleExecution model data.
 *
 * Provides consistent JSON representation of execution history records
 * including status, timing, metrics, and computed duration.
 *
 * @mixin ReportScheduleExecution
 */
class ReportScheduleExecutionResource extends JsonResource
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
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'error_message' => $this->error_message,
            'file_size_bytes' => $this->file_size_bytes,
            'recipients_count' => $this->recipients_count,
            'duration_seconds' => $this->calculateDurationSeconds(),
        ];
    }

    /**
     * Calculate the duration of the execution in seconds.
     */
    protected function calculateDurationSeconds(): ?int
    {
        if ($this->started_at === null || $this->completed_at === null) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->completed_at);
    }
}
