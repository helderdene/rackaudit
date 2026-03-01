<?php

namespace App\Http\Controllers;

use App\Enums\BulkImportEntityType;
use App\Http\Requests\StoreBulkImportRequest;
use App\Http\Resources\BulkImportResource;
use App\Models\BulkImport;
use App\Services\BulkImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for managing bulk imports of datacenter infrastructure data.
 *
 * Handles file uploads, import initiation, progress polling, and error report
 * downloads for bulk import operations.
 */
class BulkImportController extends Controller
{
    /**
     * Roles that have access to bulk import functionality.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    public function __construct(
        protected BulkImportService $bulkImportService
    ) {}

    /**
     * Display a listing of the user's import history.
     */
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        $this->authorizeAccess($request);

        $query = BulkImport::query()
            ->with('user')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at');

        // Return JSON for API requests
        if ($request->wantsJson() || $request->is('api/*')) {
            $imports = $query->get();

            return response()->json([
                'data' => BulkImportResource::collection($imports),
            ]);
        }

        $imports = $query->paginate(15)->withQueryString();

        return Inertia::render('BulkImport/Index', [
            'imports' => [
                'data' => BulkImportResource::collection($imports)->resolve(),
                'links' => $imports->linkCollection()->toArray(),
                'current_page' => $imports->currentPage(),
                'last_page' => $imports->lastPage(),
                'per_page' => $imports->perPage(),
                'total' => $imports->total(),
            ],
            'entityTypeOptions' => $this->getEntityTypeOptions(),
        ]);
    }

    /**
     * Show the form for creating a new import.
     */
    public function create(Request $request): InertiaResponse
    {
        $this->authorizeAccess($request);

        return Inertia::render('BulkImport/Create', [
            'entityTypeOptions' => $this->getEntityTypeOptions(),
            'maxFileSizeMB' => 10,
        ]);
    }

    /**
     * Handle file upload and initiate import.
     */
    public function store(StoreBulkImportRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $file = $validated['file'];

        // Parse entity type if provided
        $entityType = null;
        if (! empty($validated['entity_type'])) {
            $entityType = $validated['entity_type'] instanceof BulkImportEntityType
                ? $validated['entity_type']
                : BulkImportEntityType::tryFrom($validated['entity_type']);
        }

        try {
            $bulkImport = $this->bulkImportService->handleUpload(
                $file,
                $request->user(),
                $entityType
            );
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $bulkImport->load('user');

        if ($request->wantsJson()) {
            return response()->json([
                'data' => new BulkImportResource($bulkImport),
                'message' => 'Import initiated successfully.',
            ], 201);
        }

        return redirect()->route('imports.show', $bulkImport)
            ->with('success', 'Import initiated successfully.');
    }

    /**
     * Display the specified import status (for polling).
     */
    public function show(Request $request, BulkImport $bulkImport): InertiaResponse|JsonResponse
    {
        $this->authorizeAccess($request);
        $this->authorizeImportAccess($request, $bulkImport);

        $bulkImport->load('user');

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'data' => new BulkImportResource($bulkImport),
            ]);
        }

        return Inertia::render('BulkImport/Show', [
            'import' => (new BulkImportResource($bulkImport))->resolve(),
        ]);
    }

    /**
     * Download the error report CSV for an import.
     */
    public function downloadErrors(Request $request, BulkImport $bulkImport): StreamedResponse|JsonResponse
    {
        $this->authorizeAccess($request);
        $this->authorizeImportAccess($request, $bulkImport);

        if (empty($bulkImport->error_report_path)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'No error report available for this import.',
                ], 404);
            }

            abort(404, 'No error report available for this import.');
        }

        $filePath = $bulkImport->error_report_path;

        if (! Storage::disk('local')->exists($filePath)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Error report file not found. It may have expired.',
                ], 404);
            }

            abort(404, 'Error report file not found. It may have expired.');
        }

        $fileName = "import_{$bulkImport->id}_errors.csv";

        return Storage::disk('local')->download($filePath, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Get entity type options for dropdown.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getEntityTypeOptions(): array
    {
        return collect(BulkImportEntityType::cases())
            ->map(fn (BulkImportEntityType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Authorize that the user has access to bulk import functionality.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    private function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->hasAnyRole(self::ADMIN_ROLES)) {
            abort(403, 'You do not have permission to access bulk imports.');
        }
    }

    /**
     * Authorize that the user owns the specified import.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    private function authorizeImportAccess(Request $request, BulkImport $bulkImport): void
    {
        if ($bulkImport->user_id !== $request->user()->id) {
            // Administrators can view all imports
            if (! $request->user()->hasRole('Administrator')) {
                abort(403, 'You do not have permission to view this import.');
            }
        }
    }
}
