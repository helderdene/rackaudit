<?php

namespace App\Http\Controllers;

use App\Enums\EvidenceType;
use App\Http\Requests\StoreEvidenceRequest;
use App\Models\Finding;
use App\Models\FindingEvidence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Controller for managing evidence attached to findings.
 *
 * Handles file uploads and text note creation for evidence,
 * as well as evidence deletion with cleanup of stored files.
 */
class FindingEvidenceController extends Controller
{
    /**
     * Roles that have admin-level access to findings.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * The storage disk to use for evidence files.
     *
     * @var string
     */
    private const STORAGE_DISK = 'local';

    /**
     * Store a new evidence record for a finding.
     *
     * Handles both file uploads and text notes. Files are stored
     * in the finding-evidence/{finding_id}/ directory.
     */
    public function store(StoreEvidenceRequest $request, Finding $finding): RedirectResponse
    {
        $validated = $request->validated();
        $type = EvidenceType::from($validated['type']);

        if ($type === EvidenceType::File) {
            $this->storeFileEvidence($finding, $validated['file']);
        } else {
            $this->storeTextEvidence($finding, $validated['content']);
        }

        return redirect()->back()->with('success', 'Evidence added successfully.');
    }

    /**
     * Remove the specified evidence from storage.
     *
     * Deletes the evidence record and removes the file from storage if applicable.
     */
    public function destroy(Request $request, Finding $finding, FindingEvidence $evidence): RedirectResponse
    {
        $user = $request->user();

        // Authorization check
        if (! $this->canUserManageEvidence($finding, $user)) {
            abort(403, 'You are not authorized to delete this evidence.');
        }

        // Verify evidence belongs to this finding
        if ($evidence->finding_id !== $finding->id) {
            abort(404, 'Evidence not found for this finding.');
        }

        // Delete file from storage if this is file evidence
        if ($evidence->isFile() && $evidence->file_path) {
            Storage::disk(self::STORAGE_DISK)->delete($evidence->file_path);
        }

        // Delete the evidence record
        $evidence->delete();

        return redirect()->back()->with('success', 'Evidence deleted successfully.');
    }

    /**
     * Store file evidence for a finding.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     */
    private function storeFileEvidence(Finding $finding, $file): FindingEvidence
    {
        $originalFilename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getMimeType();

        // Generate UUID-based filename to prevent collisions
        $fileName = Str::uuid()->toString().'.'.$extension;
        $storagePath = "finding-evidence/{$finding->id}";
        $filePath = "{$storagePath}/{$fileName}";

        // Store the file
        $file->storeAs($storagePath, $fileName, self::STORAGE_DISK);

        // Create the evidence record
        return FindingEvidence::create([
            'finding_id' => $finding->id,
            'type' => EvidenceType::File,
            'content' => null,
            'file_path' => $filePath,
            'original_filename' => $originalFilename,
            'mime_type' => $mimeType,
        ]);
    }

    /**
     * Store text evidence (note) for a finding.
     */
    private function storeTextEvidence(Finding $finding, string $content): FindingEvidence
    {
        return FindingEvidence::create([
            'finding_id' => $finding->id,
            'type' => EvidenceType::Text,
            'content' => $content,
            'file_path' => null,
            'original_filename' => null,
            'mime_type' => null,
        ]);
    }

    /**
     * Check if a user can manage evidence for a finding.
     *
     * @param  \App\Models\User  $user
     */
    private function canUserManageEvidence(Finding $finding, $user): bool
    {
        // Admins and IT Managers can always manage evidence
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }

        // User is assigned to this finding
        if ($finding->assigned_to === $user->id) {
            return true;
        }

        // User is assigned to the parent audit
        if ($finding->audit && $finding->audit->assignees()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return false;
    }
}
