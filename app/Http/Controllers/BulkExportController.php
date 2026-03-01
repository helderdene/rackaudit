<?php

namespace App\Http\Controllers;

use App\Enums\BulkExportStatus;
use App\Enums\BulkImportEntityType;
use App\Http\Requests\StoreBulkExportRequest;
use App\Http\Resources\BulkExportResource;
use App\Models\BulkExport;
use App\Models\Datacenter;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Services\BulkExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for managing bulk exports of datacenter infrastructure data.
 *
 * Handles export initiation, progress polling, and file downloads
 * for bulk export operations.
 */
class BulkExportController extends Controller
{
    /**
     * Roles that have access to bulk export functionality.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    public function __construct(
        protected BulkExportService $bulkExportService
    ) {}

    /**
     * Display a listing of the user's export history.
     */
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        $this->authorizeAccess($request);

        $query = BulkExport::query()
            ->with('user')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at');

        // Return JSON for API requests
        if ($request->wantsJson() || $request->is('api/*')) {
            $exports = $query->get();

            return response()->json([
                'data' => BulkExportResource::collection($exports),
            ]);
        }

        $exports = $query->paginate(15)->withQueryString();

        return Inertia::render('BulkExport/Index', [
            'exports' => [
                'data' => BulkExportResource::collection($exports)->resolve(),
                'links' => $exports->linkCollection()->toArray(),
                'current_page' => $exports->currentPage(),
                'last_page' => $exports->lastPage(),
                'per_page' => $exports->perPage(),
                'total' => $exports->total(),
            ],
            'entityTypeOptions' => $this->getEntityTypeOptions(),
        ]);
    }

    /**
     * Show the form for creating a new export.
     */
    public function create(Request $request): InertiaResponse
    {
        $this->authorizeAccess($request);

        return Inertia::render('BulkExport/Create', [
            'entityTypeOptions' => $this->getEntityTypeOptions(),
            'formatOptions' => $this->getFormatOptions(),
            'filterOptions' => $this->getFilterOptions(),
        ]);
    }

    /**
     * Handle export initiation.
     */
    public function store(StoreBulkExportRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $entityType = BulkImportEntityType::from($validated['entity_type']);
        $format = $validated['format'];
        $filters = $request->getFilters();

        try {
            $bulkExport = $this->bulkExportService->initiateExport(
                $request->user(),
                $entityType,
                $format,
                $filters
            );
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors(['entity_type' => $e->getMessage()]);
        }

        $bulkExport->load('user');

        if ($request->wantsJson()) {
            return response()->json([
                'data' => new BulkExportResource($bulkExport),
                'message' => 'Export initiated successfully.',
            ], 201);
        }

        return redirect()->route('exports.show', $bulkExport)
            ->with('success', 'Export initiated successfully.');
    }

    /**
     * Display the specified export status (for polling).
     */
    public function show(Request $request, BulkExport $bulkExport): InertiaResponse|JsonResponse
    {
        $this->authorizeAccess($request);
        $this->authorizeExportAccess($request, $bulkExport);

        $bulkExport->load('user');

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'data' => new BulkExportResource($bulkExport),
            ]);
        }

        return Inertia::render('BulkExport/Show', [
            'export' => (new BulkExportResource($bulkExport))->resolve(),
        ]);
    }

    /**
     * Download the exported file.
     */
    public function download(Request $request, BulkExport $bulkExport): StreamedResponse|JsonResponse
    {
        $this->authorizeAccess($request);
        $this->authorizeExportAccess($request, $bulkExport);

        // Check if export is completed
        if ($bulkExport->status !== BulkExportStatus::Completed) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Export is not yet completed.',
                ], 404);
            }

            abort(404, 'Export is not yet completed.');
        }

        if (empty($bulkExport->file_path)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'No export file available.',
                ], 404);
            }

            abort(404, 'No export file available.');
        }

        $filePath = $bulkExport->file_path;

        if (! Storage::disk('local')->exists($filePath)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Export file not found. It may have expired.',
                ], 404);
            }

            abort(404, 'Export file not found. It may have expired.');
        }

        $fileName = $bulkExport->file_name;
        $contentType = $bulkExport->format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return Storage::disk('local')->download($filePath, $fileName, [
            'Content-Type' => $contentType,
        ]);
    }

    /**
     * Get entity type options for dropdown.
     * Excludes Mixed type as it's not supported for exports.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getEntityTypeOptions(): array
    {
        return collect(BulkImportEntityType::cases())
            ->filter(fn (BulkImportEntityType $type) => $type !== BulkImportEntityType::Mixed)
            ->map(fn (BulkImportEntityType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get format options for dropdown.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getFormatOptions(): array
    {
        return [
            ['value' => 'csv', 'label' => 'CSV'],
            ['value' => 'xlsx', 'label' => 'Excel (XLSX)'],
        ];
    }

    /**
     * Get hierarchical filter options.
     *
     * @return array<string, array<int, array{value: int, label: string}>>
     */
    private function getFilterOptions(): array
    {
        return [
            'datacenters' => Datacenter::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Datacenter $dc) => [
                    'value' => $dc->id,
                    'label' => $dc->name,
                ])
                ->toArray(),
            'rooms' => Room::query()
                ->with('datacenter')
                ->orderBy('name')
                ->get()
                ->map(fn (Room $room) => [
                    'value' => $room->id,
                    'label' => $room->name,
                    'datacenter_id' => $room->datacenter_id,
                ])
                ->toArray(),
            'rows' => Row::query()
                ->with('room')
                ->orderBy('name')
                ->get()
                ->map(fn (Row $row) => [
                    'value' => $row->id,
                    'label' => $row->name,
                    'room_id' => $row->room_id,
                    'datacenter_id' => $row->room?->datacenter_id,
                ])
                ->toArray(),
            'racks' => Rack::query()
                ->with('row.room')
                ->orderBy('name')
                ->get()
                ->map(fn (Rack $rack) => [
                    'value' => $rack->id,
                    'label' => $rack->name,
                    'row_id' => $rack->row_id,
                    'room_id' => $rack->row?->room_id,
                    'datacenter_id' => $rack->row?->room?->datacenter_id,
                ])
                ->toArray(),
        ];
    }

    /**
     * Authorize that the user has access to bulk export functionality.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    private function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->hasAnyRole(self::ADMIN_ROLES)) {
            abort(403, 'You do not have permission to access bulk exports.');
        }
    }

    /**
     * Authorize that the user owns the specified export.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    private function authorizeExportAccess(Request $request, BulkExport $bulkExport): void
    {
        if ($bulkExport->user_id !== $request->user()->id) {
            // Administrators can view all exports
            if (! $request->user()->hasRole('Administrator')) {
                abort(403, 'You do not have permission to view this export.');
            }
        }
    }
}
