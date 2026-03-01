<?php

namespace App\Http\Controllers;

use App\Enums\AuditStatus;
use App\Jobs\GenerateAuditReportJob;
use App\Models\Audit;
use App\Models\AuditReport;
use App\Models\Datacenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for managing audit reports.
 *
 * Handles report generation, listing, and downloading.
 * Reports can only be generated for audits with status in_progress or completed.
 */
class AuditReportController extends Controller
{
    /**
     * Roles that have full access to all reports.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Threshold for queueing report generation (number of findings).
     * Audits with more than this many findings will be queued.
     */
    private const QUEUE_THRESHOLD = 50;

    /**
     * Display a paginated list of all audit reports with filters.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $query = AuditReport::query()
            ->with(['audit.datacenter', 'generator']);

        // Filter by user access: non-admin users see only reports from audits they are assigned to
        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            $query->whereHas('audit.assignees', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        // Filter by datacenter
        if ($datacenterId = $request->input('datacenter_id')) {
            $query->whereHas('audit', function (Builder $q) use ($datacenterId) {
                $q->where('datacenter_id', $datacenterId);
            });
        }

        // Search by audit name
        if ($search = $request->input('search')) {
            $query->whereHas('audit', function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Filter by date range
        if ($dateFrom = $request->input('date_from')) {
            $query->where('generated_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->where('generated_at', '<=', $dateTo.' 23:59:59');
        }

        // Sorting
        $sortField = $request->input('sort', 'generated_at');
        $sortDirection = $request->input('direction', 'desc');

        if ($sortField === 'audit_name') {
            $query->join('audits', 'audit_reports.audit_id', '=', 'audits.id')
                ->orderBy('audits.name', $sortDirection)
                ->select('audit_reports.*');
        } else {
            $query->orderBy($sortField, $sortDirection);
        }

        $reports = $query->paginate(15)
            ->through(fn (AuditReport $report) => [
                'id' => $report->id,
                'audit_id' => $report->audit_id,
                'audit_name' => $report->audit?->name,
                'datacenter_name' => $report->audit?->datacenter?->name,
                'generator_name' => $report->generator?->name,
                'generated_at' => $report->generated_at?->format('M d, Y H:i'),
                'generated_at_iso' => $report->generated_at?->toIso8601String(),
                'file_size_bytes' => $report->file_size_bytes,
                'file_size_formatted' => $this->formatFileSize($report->file_size_bytes),
            ]);

        // Load datacenters for filter dropdown (with user access filter for non-admins)
        $datacentersQuery = Datacenter::query()->orderBy('name');

        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            $assignedDatacenterIds = $user->datacenters()->pluck('datacenters.id');
            $datacentersQuery->whereIn('id', $assignedDatacenterIds);
        }

        $datacenterOptions = $datacentersQuery->get()->map(fn (Datacenter $datacenter) => [
            'id' => $datacenter->id,
            'name' => $datacenter->name,
        ]);

        return Inertia::render('Reports/Index', [
            'reports' => $reports,
            'filters' => [
                'datacenter_id' => $request->input('datacenter_id', ''),
                'search' => $request->input('search', ''),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
            'datacenterOptions' => $datacenterOptions,
        ]);
    }

    /**
     * Generate a new report for the specified audit.
     *
     * Only audits with status in_progress or completed can have reports generated.
     * Large audits (many findings) will have report generation queued.
     */
    public function generate(Request $request, Audit $audit): RedirectResponse
    {
        $user = $request->user();

        // Authorization check: ensure user can view this audit
        if (! $this->canUserAccessAudit($audit, $user)) {
            abort(Response::HTTP_FORBIDDEN, 'You do not have permission to generate a report for this audit.');
        }

        // Validate audit status
        if (! in_array($audit->status, [AuditStatus::InProgress, AuditStatus::Completed])) {
            return redirect()->route('audits.show', $audit)
                ->with('error', 'Reports can only be generated for audits that are in progress or completed.');
        }

        // Queue the job for report generation (consistent behavior for all audits)
        GenerateAuditReportJob::dispatch($audit->id, $user->id);

        return redirect()->route('audits.show', $audit)
            ->with('success', 'Report generation has been queued. The report will be available shortly.');
    }

    /**
     * Download the specified report's PDF file.
     */
    public function download(Request $request, AuditReport $report): StreamedResponse|RedirectResponse
    {
        $user = $request->user();

        // Authorization check: ensure user can view the audit associated with this report
        if (! $this->canUserAccessAudit($report->audit, $user)) {
            abort(Response::HTTP_FORBIDDEN, 'You do not have permission to download this report.');
        }

        // Check if file exists
        if (! Storage::disk('local')->exists($report->file_path)) {
            abort(Response::HTTP_NOT_FOUND, 'Report file not found.');
        }

        $filename = basename($report->file_path);

        return Storage::disk('local')->download($report->file_path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Display the specified report details.
     *
     * Optionally redirect to download for simplicity.
     */
    public function show(Request $request, AuditReport $report): RedirectResponse
    {
        // For now, redirect to download
        return redirect()->route('reports.download', $report);
    }

    /**
     * Check if a user can access an audit.
     */
    private function canUserAccessAudit(Audit $audit, $user): bool
    {
        // Admins and IT Managers have full access
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }

        // User is assigned to this audit
        if ($audit->assignees()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // User created this audit
        if ($audit->created_by === $user->id) {
            return true;
        }

        return false;
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

        return round($size, 1).' '.$units[$i];
    }
}
