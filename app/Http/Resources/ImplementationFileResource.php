<?php

namespace App\Http\Resources;

use App\Models\ImplementationFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * API Resource for transforming ImplementationFile model data.
 *
 * Provides consistent JSON representation of implementation files including
 * file metadata, uploader information, version information, approval status, and action URLs.
 *
 * @mixin ImplementationFile
 */
class ImplementationFileResource extends JsonResource
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
            'file_name' => $this->file_name,
            'original_name' => $this->original_name,
            'description' => $this->description,
            'mime_type' => $this->mime_type,
            'formatted_file_size' => $this->formatted_file_size,
            'file_type_label' => $this->file_type_label,

            // Version information
            'version_number' => $this->version_number,
            'version_group_id' => $this->version_group_id,
            'has_multiple_versions' => $this->has_multiple_versions,
            'is_latest_version' => $this->is_latest_version,

            // Uploader information
            'uploader' => $this->whenLoaded('uploader', fn () => [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
            ]),

            // Approval information
            'approval_status' => $this->approval_status,
            'approved_at' => $this->approved_at,
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ] : null),
            'can_approve' => $this->canUserApprove($request),

            // Expected connections information
            'has_confirmed_connections' => $this->hasConfirmedConnections(),

            // Timestamps
            'created_at' => $this->created_at,

            // Action URLs
            'download_url' => route('datacenters.implementation-files.download', [
                'datacenter' => $this->datacenter_id,
                'implementation_file' => $this->id,
            ]),
            'preview_url' => $this->when(
                $this->supportsPreview(),
                fn () => route('datacenters.implementation-files.preview', [
                    'datacenter' => $this->datacenter_id,
                    'implementation_file' => $this->id,
                ])
            ),
            'approve_url' => $this->when(
                $this->isPendingApproval(),
                fn () => route('datacenters.implementation-files.approve', [
                    'datacenter' => $this->datacenter_id,
                    'implementation_file' => $this->id,
                ])
            ),
            'comparison_url' => $this->when(
                $this->isApproved() && $this->hasConfirmedConnections(),
                fn () => route('implementation-files.comparison', [
                    'file' => $this->id,
                ])
            ),
        ];
    }

    /**
     * Check if the file type supports preview.
     */
    private function supportsPreview(): bool
    {
        return $this->mime_type === 'application/pdf'
            || str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if the current user can approve this file.
     */
    private function canUserApprove(Request $request): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        // Only pending files can be approved
        if (! $this->isPendingApproval()) {
            return false;
        }

        return Gate::allows('approve', $this->resource);
    }

    /**
     * Check if this file has confirmed expected connections with port mappings.
     */
    private function hasConfirmedConnections(): bool
    {
        return $this->expectedConnections()
            ->confirmed()
            ->whereNotNull('source_port_id')
            ->whereNotNull('dest_port_id')
            ->exists();
    }
}
