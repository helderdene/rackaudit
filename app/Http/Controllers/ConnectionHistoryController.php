<?php

namespace App\Http\Controllers;

use App\Enums\BulkExportStatus;
use App\Enums\BulkImportEntityType;
use App\Http\Requests\ConnectionHistoryIndexRequest;
use App\Http\Resources\BulkExportResource;
use App\Jobs\ConnectionHistoryExportJob;
use App\Models\ActivityLog;
use App\Models\BulkExport;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConnectionHistoryController extends Controller
{
    /**
     * Valid action types for filtering.
     *
     * @var array<string>
     */
    private const VALID_ACTIONS = [
        'created',
        'updated',
        'deleted',
        'restored',
    ];

    /**
     * Roles that have access to export functionality.
     *
     * @var array<string>
     */
    private const EXPORT_ROLES = [
        'Administrator',
        'IT Manager',
        'Auditor',
    ];

    /**
     * Threshold for async processing (rows >= this will be processed asynchronously).
     */
    private const ASYNC_THRESHOLD = 100;

    /**
     * Display a paginated list of connection history logs with filters.
     */
    public function index(ConnectionHistoryIndexRequest $request): InertiaResponse|JsonResponse
    {
        Gate::authorize('viewHistory', Connection::class);

        $user = $request->user();
        $query = ActivityLog::with(['causer', 'subject'])
            ->where('subject_type', Connection::class);

        // Apply role-based filtering
        $query = $this->applyRoleBasedFiltering($query, $user);

        // Apply filters
        $query = $this->applyFilters($query, $request);

        $activityLogs = $query->orderByDesc('created_at')
            ->paginate(25)
            ->through(function (ActivityLog $log) {
                return $this->transformActivityLog($log);
            });

        $users = User::select('id', 'name')->orderBy('name')->get();

        // Return JSON for API requests
        if ($request->expectsJson()) {
            return response()->json([
                'activityLogs' => $activityLogs,
                'availableActions' => self::VALID_ACTIONS,
                'users' => $users,
            ]);
        }

        return Inertia::render('ConnectionHistory/Index', [
            'activityLogs' => $activityLogs,
            'availableActions' => self::VALID_ACTIONS,
            'users' => $users,
            'filters' => [
                'start_date' => $request->input('start_date', ''),
                'end_date' => $request->input('end_date', ''),
                'action' => $request->input('action', ''),
                'user_id' => $request->input('user_id', ''),
                'search' => $request->input('search', ''),
            ],
        ]);
    }

    /**
     * Get timeline entries for a specific connection.
     */
    public function timeline(Connection $connection): JsonResponse
    {
        Gate::authorize('view', $connection);

        $perPage = 10;
        $page = (int) request()->input('page', 1);

        $query = ActivityLog::with(['causer'])
            ->where('subject_type', Connection::class)
            ->where('subject_id', $connection->id)
            ->orderByDesc('created_at');

        $total = $query->count();
        $hasMore = $total > ($page * $perPage);

        $logs = $query
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function (ActivityLog $log) {
                return $this->transformActivityLog($log);
            });

        return response()->json([
            'data' => $logs,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => $hasMore,
            ],
        ]);
    }

    /**
     * Initiate a connection history export.
     */
    public function export(Request $request): JsonResponse
    {
        $this->authorizeExportAccess($request);

        $validated = $request->validate([
            'format' => ['required', 'string', 'in:csv,pdf'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'action' => ['nullable', 'string', 'in:created,updated,deleted,restored'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $format = $validated['format'];
        $filters = array_filter([
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'action' => $validated['action'] ?? null,
            'user_id' => $validated['user_id'] ?? null,
            'search' => $validated['search'] ?? null,
        ], fn ($value) => $value !== null);

        // Build query to count rows
        $query = $this->buildExportQuery($filters);
        $rowCount = $query->count();

        // Generate file name
        $uuid = Str::uuid();
        $fileName = "connection_history_export_{$uuid}.{$format}";
        $filePath = "exports/{$fileName}";

        // Create the BulkExport record
        $bulkExport = BulkExport::create([
            'user_id' => $request->user()->id,
            'entity_type' => BulkImportEntityType::ConnectionHistory,
            'format' => $format,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'status' => BulkExportStatus::Pending,
            'total_rows' => $rowCount,
            'processed_rows' => 0,
            'filters' => $filters,
        ]);

        // Decide sync vs async processing
        if ($rowCount >= self::ASYNC_THRESHOLD) {
            ConnectionHistoryExportJob::dispatch($bulkExport);
        } else {
            // Process synchronously
            $job = new ConnectionHistoryExportJob($bulkExport);
            $job->handle();
        }

        $bulkExport->load('user');

        return response()->json([
            'data' => new BulkExportResource($bulkExport),
            'message' => 'Export initiated successfully.',
        ], 201);
    }

    /**
     * Get the status of an export.
     */
    public function exportStatus(Request $request, BulkExport $bulkExport): JsonResponse
    {
        $this->authorizeExportAccess($request);
        $this->authorizeExportOwnership($request, $bulkExport);

        $bulkExport->load('user');

        return response()->json([
            'data' => new BulkExportResource($bulkExport),
        ]);
    }

    /**
     * Download the exported file.
     */
    public function exportDownload(Request $request, BulkExport $bulkExport): StreamedResponse|JsonResponse
    {
        $this->authorizeExportAccess($request);
        $this->authorizeExportOwnership($request, $bulkExport);

        // Check if export is completed
        if ($bulkExport->status !== BulkExportStatus::Completed) {
            return response()->json([
                'message' => 'Export is not yet completed.',
            ], 404);
        }

        if (empty($bulkExport->file_path)) {
            return response()->json([
                'message' => 'No export file available.',
            ], 404);
        }

        $filePath = $bulkExport->file_path;

        if (! Storage::disk('local')->exists($filePath)) {
            return response()->json([
                'message' => 'Export file not found. It may have expired.',
            ], 404);
        }

        $fileName = $bulkExport->file_name;
        $contentType = match ($bulkExport->format) {
            'csv' => 'text/csv',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };

        return Storage::disk('local')->download($filePath, $fileName, [
            'Content-Type' => $contentType,
        ]);
    }

    /**
     * Transform an ActivityLog model to array for response.
     *
     * @return array<string, mixed>
     */
    private function transformActivityLog(ActivityLog $log): array
    {
        $causer = $log->causer;
        $causerRole = null;

        if ($causer) {
            $roles = $causer->getRoleNames();
            $causerRole = $roles->first();
        }

        return [
            'id' => $log->id,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'causer_id' => $log->causer_id,
            'causer_name' => $causer?->name ?? 'System',
            'causer_role' => $causerRole,
            'action' => $log->action,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'created_at' => $log->created_at,
        ];
    }

    /**
     * Apply role-based filtering to the query.
     *
     * Administrator and Auditor: see all connection history logs
     * IT Manager/Operator: see logs within their datacenter scope
     * Viewer: see only their own activity logs
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ActivityLog>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ActivityLog>
     */
    private function applyRoleBasedFiltering($query, User $user)
    {
        // Administrator and Auditor see all
        if ($user->hasRole('Administrator') || $user->hasRole('Auditor')) {
            return $query;
        }

        // Viewer sees only their own logs
        if ($user->hasRole('Viewer')) {
            return $query->where('causer_id', $user->id);
        }

        // IT Manager and Operator: see all connection history
        // (connections don't have direct datacenter scope in the same way users do)
        if ($user->hasAnyRole(['IT Manager', 'Operator'])) {
            return $query;
        }

        // Default: no access (empty result)
        return $query->whereRaw('1 = 0');
    }

    /**
     * Apply request filters to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ActivityLog>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ActivityLog>
     */
    private function applyFilters($query, ConnectionHistoryIndexRequest $request)
    {
        // Filter by date range
        if ($request->filled('start_date') || $request->filled('end_date')) {
            $query->inDateRange(
                $request->input('start_date'),
                $request->input('end_date')
            );
        }

        // Filter by action type
        if ($request->filled('action')) {
            $query->byAction($request->input('action'));
        }

        // Filter by user_id (causer)
        if ($request->filled('user_id')) {
            $query->byUser((int) $request->input('user_id'));
        }

        // Search across old_values and new_values JSON columns
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('old_values', 'like', '%' . $search . '%')
                    ->orWhere('new_values', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }

    /**
     * Build the export query with filters applied.
     *
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Database\Eloquent\Builder<ActivityLog>
     */
    private function buildExportQuery(array $filters)
    {
        $query = ActivityLog::query()
            ->where('subject_type', Connection::class)
            ->orderByDesc('created_at');

        // Apply date range filter
        if (! empty($filters['start_date']) || ! empty($filters['end_date'])) {
            $query->inDateRange(
                $filters['start_date'] ?? null,
                $filters['end_date'] ?? null
            );
        }

        // Apply action filter
        if (! empty($filters['action'])) {
            $query->byAction($filters['action']);
        }

        // Apply user filter
        if (! empty($filters['user_id'])) {
            $query->byUser((int) $filters['user_id']);
        }

        // Apply search filter
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('old_values', 'like', '%' . $search . '%')
                    ->orWhere('new_values', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }

    /**
     * Authorize that the user has access to export functionality.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    private function authorizeExportAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->hasAnyRole(self::EXPORT_ROLES)) {
            abort(403, 'You do not have permission to export connection history.');
        }
    }

    /**
     * Authorize that the user owns the specified export.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    private function authorizeExportOwnership(Request $request, BulkExport $bulkExport): void
    {
        if ($bulkExport->user_id !== $request->user()->id) {
            // Administrators can view all exports
            if (! $request->user()->hasRole('Administrator')) {
                abort(403, 'You do not have permission to view this export.');
            }
        }
    }
}
