<?php

namespace App\Http\Resources;

use App\Models\AuditReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for transforming AuditReport model data.
 *
 * Provides consistent JSON representation of audit reports including
 * related audit name, generator information, and formatted file size.
 *
 * @mixin AuditReport
 */
class AuditReportResource extends JsonResource
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
            'audit_id' => $this->audit_id,
            'audit_name' => $this->whenLoaded('audit', fn () => $this->audit?->name),
            'generator_id' => $this->user_id,
            'generator_name' => $this->whenLoaded('generator', fn () => $this->generator?->name),
            'file_path' => $this->file_path,
            'generated_at' => $this->generated_at?->format('M d, Y H:i'),
            'generated_at_iso' => $this->generated_at?->toIso8601String(),
            'file_size_bytes' => $this->file_size_bytes,
            'file_size_formatted' => $this->formatFileSize($this->file_size_bytes),
            'download_url' => route('reports.download', $this->id),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Format file size in human-readable format.
     */
    private function formatFileSize(?int $bytes): string
    {
        if ($bytes === null || $bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = $bytes;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 1) . ' ' . $units[$i];
    }
}
