<?php

namespace App\Http\Controllers;

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Enums\ScheduleFrequency;
use App\Http\Requests\StoreReportScheduleRequest;
use App\Http\Requests\ToggleReportScheduleRequest;
use App\Http\Resources\ReportScheduleExecutionResource;
use App\Http\Resources\ReportScheduleResource;
use App\Models\DistributionList;
use App\Models\ReportSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller for managing report schedules.
 *
 * Report schedules define automated report generation with configurable
 * frequencies (daily, weekly, monthly) and distribution to email lists.
 * Each schedule belongs to a user and tracks execution history.
 */
class ReportScheduleController extends Controller
{
    /**
     * Roles that have full access to all report schedules.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Display a paginated list of report schedules.
     * Admins/IT Managers see all schedules; others see only their own.
     */
    public function index(Request $request): InertiaResponse
    {
        Gate::authorize('viewAny', ReportSchedule::class);

        $user = $request->user();
        $query = ReportSchedule::query()
            ->with('distributionList');

        // Filter by user ownership unless admin/IT Manager
        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            $query->where('user_id', $user->id);
        }

        $schedules = $query->orderBy('name')
            ->get()
            ->map(fn (ReportSchedule $schedule) => (new ReportScheduleResource($schedule))->resolve());

        return Inertia::render('ReportSchedules/Index', [
            'schedules' => $schedules,
            'canCreate' => $user->can('create', ReportSchedule::class),
        ]);
    }

    /**
     * Show the form for creating a new report schedule.
     */
    public function create(Request $request): InertiaResponse
    {
        Gate::authorize('create', ReportSchedule::class);

        $user = $request->user();

        // Get user's distribution lists
        $distributionListsQuery = DistributionList::query();
        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            $distributionListsQuery->where('user_id', $user->id);
        }
        $distributionLists = $distributionListsQuery->orderBy('name')->get()
            ->map(fn (DistributionList $list) => [
                'id' => $list->id,
                'name' => $list->name,
                'members_count' => $list->members()->count(),
            ]);

        // Get report type options
        $reportTypes = array_map(
            fn (ReportType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
            ],
            ReportType::cases()
        );

        // Get frequency options
        $frequencies = array_map(
            fn (ScheduleFrequency $freq) => [
                'value' => $freq->value,
                'label' => $freq->label(),
                'description' => $freq->description(),
            ],
            ScheduleFrequency::cases()
        );

        // Get format options
        $formats = array_map(
            fn (ReportFormat $format) => [
                'value' => $format->value,
                'label' => $format->label(),
            ],
            ReportFormat::cases()
        );

        // Get common timezones
        $timezones = $this->getTimezoneOptions();

        return Inertia::render('ReportSchedules/Create', [
            'distributionLists' => $distributionLists,
            'reportTypes' => $reportTypes,
            'frequencies' => $frequencies,
            'formats' => $formats,
            'timezones' => $timezones,
            'defaultTimezone' => $user->timezone ?? config('app.timezone', 'UTC'),
        ]);
    }

    /**
     * Store a newly created report schedule.
     */
    public function store(StoreReportScheduleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $schedule = ReportSchedule::create([
            'name' => $validated['name'],
            'user_id' => $request->user()->id,
            'distribution_list_id' => $validated['distribution_list_id'],
            'report_type' => $validated['report_type'],
            'report_configuration' => $validated['report_configuration'],
            'frequency' => $validated['frequency'],
            'day_of_week' => $validated['day_of_week'] ?? null,
            'day_of_month' => $validated['day_of_month'] ?? null,
            'time_of_day' => $validated['time_of_day'],
            'timezone' => $validated['timezone'],
            'format' => $validated['format'],
            'is_enabled' => true,
            'consecutive_failures' => 0,
        ]);

        // Calculate and set next run time
        $schedule->next_run_at = $schedule->calculateNextRunAt();
        $schedule->save();

        return redirect()->route('report-schedules.index')
            ->with('success', 'Report schedule created successfully.');
    }

    /**
     * Display the specified report schedule with execution history.
     */
    public function show(ReportSchedule $reportSchedule): InertiaResponse
    {
        Gate::authorize('view', $reportSchedule);

        $reportSchedule->load(['distributionList.members', 'executions' => function ($query) {
            $query->latest()->limit(10);
        }]);

        $user = request()->user();

        return Inertia::render('ReportSchedules/Show', [
            'schedule' => (new ReportScheduleResource($reportSchedule))->resolve(),
            'recentExecutions' => ReportScheduleExecutionResource::collection(
                $reportSchedule->executions
            )->resolve(),
            'canToggle' => $user->can('update', $reportSchedule),
            'canDelete' => $user->can('delete', $reportSchedule),
        ]);
    }

    /**
     * Toggle the enabled/disabled state of a report schedule.
     * When re-enabling a schedule, resets failure count and calculates next run.
     */
    public function toggle(ToggleReportScheduleRequest $request, ReportSchedule $reportSchedule): RedirectResponse
    {
        $validated = $request->validated();
        $isEnabling = $validated['is_enabled'];

        $reportSchedule->is_enabled = $isEnabling;

        // When re-enabling, reset failures and calculate next run
        if ($isEnabling) {
            $reportSchedule->consecutive_failures = 0;
            $reportSchedule->next_run_at = $reportSchedule->calculateNextRunAt();
        }

        $reportSchedule->save();

        $status = $isEnabling ? 'enabled' : 'disabled';

        return redirect()->back()
            ->with('success', "Report schedule {$status} successfully.");
    }

    /**
     * Remove the specified report schedule (soft delete).
     */
    public function destroy(ReportSchedule $reportSchedule): RedirectResponse
    {
        Gate::authorize('delete', $reportSchedule);

        $reportSchedule->delete();

        return redirect()->route('report-schedules.index')
            ->with('success', 'Report schedule deleted successfully.');
    }

    /**
     * Get paginated execution history for a report schedule.
     */
    public function history(Request $request, ReportSchedule $reportSchedule): JsonResponse
    {
        Gate::authorize('view', $reportSchedule);

        $executions = $reportSchedule->executions()
            ->orderByDesc('created_at')
            ->paginate(15);

        return ReportScheduleExecutionResource::collection($executions)
            ->response();
    }

    /**
     * Get a list of common timezone options for the UI.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getTimezoneOptions(): array
    {
        $commonTimezones = [
            'UTC' => 'UTC',
            'America/New_York' => 'Eastern Time (US)',
            'America/Chicago' => 'Central Time (US)',
            'America/Denver' => 'Mountain Time (US)',
            'America/Los_Angeles' => 'Pacific Time (US)',
            'America/Toronto' => 'Toronto',
            'Europe/London' => 'London',
            'Europe/Paris' => 'Paris',
            'Europe/Berlin' => 'Berlin',
            'Asia/Tokyo' => 'Tokyo',
            'Asia/Shanghai' => 'Shanghai',
            'Asia/Singapore' => 'Singapore',
            'Australia/Sydney' => 'Sydney',
        ];

        return array_map(
            fn (string $value, string $label) => [
                'value' => $value,
                'label' => $label,
            ],
            array_keys($commonTimezones),
            array_values($commonTimezones)
        );
    }
}
