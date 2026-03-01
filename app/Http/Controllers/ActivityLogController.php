<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivityLogIndexRequest;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ActivityLogController extends Controller
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
    ];

    /**
     * Valid subject types that can be logged.
     *
     * @var array<string>
     */
    private const VALID_SUBJECT_TYPES = [
        'App\Models\User',
        'App\Models\Datacenter',
    ];

    /**
     * Display a paginated list of activity logs with filters.
     */
    public function index(ActivityLogIndexRequest $request): InertiaResponse|JsonResponse
    {
        $user = $request->user();
        $query = ActivityLog::with(['causer', 'subject']);

        // Apply role-based filtering
        $query = $this->applyRoleBasedFiltering($query, $user);

        // Apply filters
        $query = $this->applyFilters($query, $request);

        $activityLogs = $query->orderByDesc('created_at')
            ->paginate(25)
            ->through(function (ActivityLog $log) {
                return [
                    'id' => $log->id,
                    'subject_type' => $log->subject_type,
                    'subject_id' => $log->subject_id,
                    'causer_id' => $log->causer_id,
                    'causer_name' => $log->causer?->name ?? 'System',
                    'action' => $log->action,
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'created_at' => $log->created_at,
                ];
            });

        $users = User::select('id', 'name')->orderBy('name')->get();

        // Return JSON for API requests
        if ($request->expectsJson()) {
            return response()->json([
                'activityLogs' => $activityLogs,
                'availableActions' => self::VALID_ACTIONS,
                'availableSubjectTypes' => self::VALID_SUBJECT_TYPES,
                'users' => $users,
            ]);
        }

        return Inertia::render('ActivityLogs/Index', [
            'activityLogs' => $activityLogs,
            'availableActions' => self::VALID_ACTIONS,
            'availableSubjectTypes' => self::VALID_SUBJECT_TYPES,
            'users' => $users,
            'filters' => [
                'start_date' => $request->input('start_date', ''),
                'end_date' => $request->input('end_date', ''),
                'action' => $request->input('action', ''),
                'user_id' => $request->input('user_id', ''),
                'subject_type' => $request->input('subject_type', ''),
                'search' => $request->input('search', ''),
            ],
        ]);
    }

    /**
     * Apply role-based filtering to the query.
     *
     * Administrator: see all logs
     * IT Manager/Operator/Auditor: see own logs + logs where subject belongs to their datacenters
     * Viewer: see only own activity logs
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ActivityLog>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ActivityLog>
     */
    private function applyRoleBasedFiltering($query, User $user)
    {
        // Administrator sees all
        if ($user->hasRole('Administrator')) {
            return $query;
        }

        // Viewer sees only their own logs
        if ($user->hasRole('Viewer')) {
            return $query->where('causer_id', $user->id);
        }

        // IT Manager, Operator, Auditor: see own logs + logs within datacenter scope
        if ($user->hasAnyRole(['IT Manager', 'Operator', 'Auditor'])) {
            $datacenterIds = $user->datacenters()->pluck('datacenters.id')->toArray();

            return $query->where(function ($q) use ($user, $datacenterIds) {
                // Own logs
                $q->where('causer_id', $user->id);

                // Logs where subject is a User who belongs to the same datacenters
                if (! empty($datacenterIds)) {
                    $q->orWhere(function ($subQuery) use ($datacenterIds) {
                        $subQuery->where('subject_type', User::class)
                            ->whereIn('subject_id', function ($innerQuery) use ($datacenterIds) {
                                $innerQuery->select('user_id')
                                    ->from('datacenter_user')
                                    ->whereIn('datacenter_id', $datacenterIds);
                            });
                    });

                    // Logs where subject is a Datacenter that user has access to
                    $q->orWhere(function ($subQuery) use ($datacenterIds) {
                        $subQuery->where('subject_type', 'App\Models\Datacenter')
                            ->whereIn('subject_id', $datacenterIds);
                    });
                }
            });
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
    private function applyFilters($query, ActivityLogIndexRequest $request)
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

        // Filter by subject_type
        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
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
}
