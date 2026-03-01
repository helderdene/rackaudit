<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveImplementationFileRequest;
use App\Http\Requests\RestoreImplementationFileRequest;
use App\Http\Requests\StoreImplementationFileRequest;
use App\Http\Resources\ImplementationFileResource;
use App\Models\Datacenter;
use App\Models\ImplementationFile;
use App\Models\User;
use App\Notifications\ImplementationFileApprovedNotification;
use App\Notifications\ImplementationFileAwaitingApprovalNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for managing implementation specification files within datacenters.
 *
 * Handles file uploads, downloads, previews, version history, restoration, and approval
 * for implementation documents that serve as the authoritative source for expected connections.
 */
class ImplementationFileController extends Controller
{
    /**
     * Roles that can approve implementation files.
     *
     * @var array<string>
     */
    private const APPROVER_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Display a listing of implementation files for the datacenter.
     */
    public function index(Request $request, Datacenter $datacenter): JsonResponse|InertiaResponse
    {
        Gate::authorize('viewAny', [ImplementationFile::class, $datacenter]);

        $query = $datacenter->implementationFiles()
            ->with(['uploader', 'approver'])
            ->orderByDesc('created_at');

        // Return JSON for API requests
        if ($request->wantsJson() || $request->is('api/*')) {
            $files = $query->get();

            return response()->json([
                'data' => ImplementationFileResource::collection($files),
            ]);
        }

        $files = $query->paginate(15)->withQueryString();

        return Inertia::render('Datacenters/ImplementationFiles/Index', [
            'datacenter' => $datacenter,
            'files' => [
                'data' => ImplementationFileResource::collection($files)->resolve(),
                'links' => $files->linkCollection()->toArray(),
                'current_page' => $files->currentPage(),
                'last_page' => $files->lastPage(),
                'per_page' => $files->perPage(),
                'total' => $files->total(),
            ],
        ]);
    }

    /**
     * Store a newly uploaded implementation file.
     * Creates version chains for files with the same original_name.
     * Does NOT delete old files - preserves all versions for version history.
     * New files start with "pending_approval" status.
     * Sends notification to approvers (IT Managers and Administrators with datacenter access).
     */
    public function store(StoreImplementationFileRequest $request, Datacenter $datacenter): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        Gate::authorize('create', [ImplementationFile::class, $datacenter]);

        $validated = $request->validated();
        $uploadedFile = $validated['file'];
        $originalName = $uploadedFile->getClientOriginalName();

        // Generate UUID-based filename to prevent collisions
        $extension = $uploadedFile->getClientOriginalExtension();
        $fileName = Str::uuid()->toString().'.'.$extension;
        $storagePath = "implementation-files/{$datacenter->id}";
        $filePath = "{$storagePath}/{$fileName}";

        // Check for existing file with same original_name to create version chain
        $existingFile = $datacenter->implementationFiles()
            ->where('original_name', $originalName)
            ->whereNotNull('version_group_id')
            ->orderByDesc('version_number')
            ->first();

        // Determine version_group_id and version_number
        $versionGroupId = null;
        $versionNumber = 1;

        if ($existingFile) {
            // Join existing version chain
            $versionGroupId = $existingFile->version_group_id;
            $versionNumber = $existingFile->version_number + 1;
        }

        // Store the new file
        $uploadedFile->storeAs($storagePath, $fileName, 'local');

        // Create new ImplementationFile record with pending_approval status
        $implementationFile = ImplementationFile::create([
            'datacenter_id' => $datacenter->id,
            'file_name' => $fileName,
            'original_name' => $originalName,
            'description' => $validated['description'] ?? null,
            'file_path' => $filePath,
            'file_size' => $uploadedFile->getSize(),
            'mime_type' => $uploadedFile->getMimeType(),
            'uploaded_by' => $request->user()->id,
            'version_group_id' => $versionGroupId,
            'version_number' => $versionNumber,
            'approval_status' => 'pending_approval',
        ]);

        // If this is the first version, set version_group_id to its own id
        if ($versionGroupId === null) {
            $implementationFile->update(['version_group_id' => $implementationFile->id]);
        }

        $implementationFile->load(['uploader', 'datacenter']);

        // Send notification to all IT Managers and Administrators with datacenter access
        // who are not the uploader (they can't approve their own files anyway)
        $this->notifyApprovers($implementationFile, $request->user());

        // Return JSON for API requests, redirect for Inertia requests
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'data' => new ImplementationFileResource($implementationFile),
                'message' => 'File uploaded successfully.',
            ], 201);
        }

        return back()->with('success', 'File uploaded successfully.');
    }

    /**
     * Display the specified implementation file.
     */
    public function show(Request $request, Datacenter $datacenter, ImplementationFile $implementationFile): JsonResponse
    {
        Gate::authorize('view', $implementationFile);

        $implementationFile->load(['uploader', 'approver']);

        return response()->json([
            'data' => new ImplementationFileResource($implementationFile),
        ]);
    }

    /**
     * Download the implementation file with Content-Disposition: attachment.
     */
    public function download(Request $request, Datacenter $datacenter, ImplementationFile $implementationFile): StreamedResponse|JsonResponse
    {
        Gate::authorize('download', $implementationFile);

        if (! Storage::disk('local')->exists($implementationFile->file_path)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'File not found.',
                ], 404);
            }

            abort(404, 'File not found.');
        }

        return Storage::disk('local')->download(
            $implementationFile->file_path,
            $implementationFile->original_name,
            [
                'Content-Type' => $implementationFile->mime_type,
            ]
        );
    }

    /**
     * Preview PDF files inline in the browser.
     * Returns 415 Unsupported Media Type for non-PDF files.
     */
    public function preview(Request $request, Datacenter $datacenter, ImplementationFile $implementationFile): StreamedResponse|Response|JsonResponse
    {
        Gate::authorize('download', $implementationFile);

        // Only allow PDF preview
        if ($implementationFile->mime_type !== 'application/pdf') {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Preview is only available for PDF files.',
                ], 415);
            }

            abort(415, 'Preview is only available for PDF files.');
        }

        if (! Storage::disk('local')->exists($implementationFile->file_path)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'File not found.',
                ], 404);
            }

            abort(404, 'File not found.');
        }

        return Storage::disk('local')->response(
            $implementationFile->file_path,
            $implementationFile->original_name,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.addslashes($implementationFile->original_name).'"',
            ]
        );
    }

    /**
     * Get all versions of the implementation file in its version group.
     * Returns versions ordered by version_number descending (newest first).
     */
    public function versions(Request $request, Datacenter $datacenter, ImplementationFile $implementationFile): JsonResponse
    {
        Gate::authorize('viewVersions', $implementationFile);

        $versions = ImplementationFile::query()
            ->with(['uploader', 'approver'])
            ->where('version_group_id', $implementationFile->version_group_id)
            ->orderByDesc('version_number')
            ->get();

        return response()->json([
            'data' => ImplementationFileResource::collection($versions),
        ]);
    }

    /**
     * Restore a previous version of an implementation file.
     * Creates a new version from the selected file's content.
     * New restored versions start with "pending_approval" status.
     * Sends notification to approvers (IT Managers and Administrators with datacenter access).
     */
    public function restore(RestoreImplementationFileRequest $request, Datacenter $datacenter, ImplementationFile $implementationFile): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        // Get the maximum version number in the version group
        $maxVersionNumber = ImplementationFile::query()
            ->where('version_group_id', $implementationFile->version_group_id)
            ->max('version_number');

        // Generate new UUID-based filename
        $extension = pathinfo($implementationFile->file_name, PATHINFO_EXTENSION);
        $newFileName = Str::uuid()->toString().'.'.$extension;
        $storagePath = "implementation-files/{$datacenter->id}";
        $newFilePath = "{$storagePath}/{$newFileName}";

        // Copy the old file to new location
        Storage::disk('local')->copy($implementationFile->file_path, $newFilePath);

        // Create new ImplementationFile record with incremented version number
        // and pending_approval status (approval does not carry over from restored version)
        $newFile = ImplementationFile::create([
            'datacenter_id' => $datacenter->id,
            'file_name' => $newFileName,
            'original_name' => $implementationFile->original_name,
            'description' => $implementationFile->description,
            'file_path' => $newFilePath,
            'file_size' => $implementationFile->file_size,
            'mime_type' => $implementationFile->mime_type,
            'uploaded_by' => $request->user()->id,
            'version_group_id' => $implementationFile->version_group_id,
            'version_number' => $maxVersionNumber + 1,
            'approval_status' => 'pending_approval',
        ]);

        $newFile->load(['uploader', 'datacenter']);

        // Send notification to approvers for the restored version
        $this->notifyApprovers($newFile, $request->user());

        // Return JSON for API requests, redirect for Inertia requests
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'data' => new ImplementationFileResource($newFile),
                'message' => 'Version restored successfully.',
            ], 201);
        }

        return back()->with('success', 'Version restored successfully.');
    }

    /**
     * Approve an implementation file.
     * Sets the approval_status to "approved" and records the approver and timestamp.
     * Activity logging is handled automatically by the Loggable concern on the model.
     * Sends notification to the uploader that their file has been approved.
     */
    public function approve(ApproveImplementationFileRequest $request, Datacenter $datacenter, ImplementationFile $implementationFile): JsonResponse
    {
        $approver = $request->user();

        $implementationFile->update([
            'approval_status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        $implementationFile->load(['uploader', 'approver', 'datacenter']);

        // Send notification to the uploader that their file has been approved
        $uploader = $implementationFile->uploader;
        if ($uploader) {
            $uploader->notify(new ImplementationFileApprovedNotification($implementationFile, $approver));
        }

        return response()->json([
            'data' => new ImplementationFileResource($implementationFile),
            'message' => 'File approved successfully.',
        ]);
    }

    /**
     * Delete the implementation file (soft-delete the record and remove file from storage).
     */
    public function destroy(Request $request, Datacenter $datacenter, ImplementationFile $implementationFile): Response|JsonResponse|\Illuminate\Http\RedirectResponse
    {
        Gate::authorize('delete', $implementationFile);

        // Delete file from storage
        if (Storage::disk('local')->exists($implementationFile->file_path)) {
            Storage::disk('local')->delete($implementationFile->file_path);
        }

        // Soft-delete the record
        $implementationFile->delete();

        // Return JSON for API requests, redirect for Inertia requests
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->noContent();
        }

        return back()->with('success', 'File deleted successfully.');
    }

    /**
     * Notify all IT Managers and Administrators that a file is awaiting approval.
     * Excludes the uploader since they cannot approve their own files.
     */
    private function notifyApprovers(ImplementationFile $implementationFile, User $uploader): void
    {
        // Get all users with approver roles who are not the uploader
        // Note: APPROVER_ROLES (Administrator, IT Manager) have global datacenter access
        // so we don't need to filter by datacenter assignment
        $approvers = User::role(self::APPROVER_ROLES)
            ->where('id', '!=', $uploader->id)
            ->where('status', 'active')
            ->get();

        if ($approvers->isNotEmpty()) {
            Notification::send(
                $approvers,
                new ImplementationFileAwaitingApprovalNotification($implementationFile)
            );
        }
    }
}
