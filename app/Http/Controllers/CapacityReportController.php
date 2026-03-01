<?php

namespace App\Http\Controllers;

use App\Exports\CapacityReportExport;
use App\Models\CapacitySnapshot;
use App\Models\Datacenter;
use App\Models\Room;
use App\Models\Row;
use App\Services\CapacityCalculationService;
use App\Services\CapacityReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for managing capacity planning reports.
 *
 * Provides capacity metrics overview, cascading location filters,
 * historical trend data, and export capabilities (PDF/CSV).
 */
class CapacityReportController extends Controller
{
    /**
     * Roles that have full access to all datacenters.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected CapacityCalculationService $calculationService,
        protected CapacityReportService $reportService
    ) {}

    /**
     * Display the capacity planning reports page.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();

        // Get accessible datacenters based on user role
        $datacenterOptions = $this->getAccessibleDatacenters($user);
        $accessibleDatacenterIds = $datacenterOptions->pluck('id')->toArray();

        // Get and validate filter values
        $datacenterId = $this->validateDatacenterId(
            $request->input('datacenter_id'),
            $accessibleDatacenterIds
        );

        // Get rooms for the selected datacenter (for cascading filter)
        $roomOptions = $this->getRoomOptions($datacenterId);
        $roomId = $this->validateRoomId(
            $request->input('room_id'),
            $datacenterId,
            $roomOptions->pluck('id')->toArray()
        );

        // Get rows for the selected room (for cascading filter)
        $rowOptions = $this->getRowOptions($roomId);
        $rowId = $this->validateRowId(
            $request->input('row_id'),
            $roomId,
            $rowOptions->pluck('id')->toArray()
        );

        // Get capacity metrics via service
        $metrics = $this->calculationService->getCapacityMetrics(
            $datacenterId,
            $roomId,
            $rowId
        );

        // Get historical snapshots for sparkline data (last 12 weeks)
        $historicalSnapshots = $this->getHistoricalSnapshots($datacenterId);

        // Calculate week-over-week trend data
        $trendData = $this->calculateWeekOverWeekTrend($historicalSnapshots);

        return Inertia::render('CapacityReports/Index', [
            'metrics' => $metrics,
            'datacenterOptions' => $datacenterOptions->values()->toArray(),
            'roomOptions' => $roomOptions->values()->toArray(),
            'rowOptions' => $rowOptions->values()->toArray(),
            'filters' => [
                'datacenter_id' => $datacenterId,
                'room_id' => $roomId,
                'row_id' => $rowId,
            ],
            'historicalSnapshots' => $historicalSnapshots,
            'trendData' => $trendData,
        ]);
    }

    /**
     * Export capacity report as PDF.
     */
    public function exportPdf(Request $request): StreamedResponse|BinaryFileResponse
    {
        $user = $request->user();

        // Get accessible datacenters based on user role
        $datacenterOptions = $this->getAccessibleDatacenters($user);
        $accessibleDatacenterIds = $datacenterOptions->pluck('id')->toArray();

        // Get and validate filter values
        $datacenterId = $this->validateDatacenterId(
            $request->input('datacenter_id'),
            $accessibleDatacenterIds
        );

        $roomOptions = $this->getRoomOptions($datacenterId);
        $roomId = $this->validateRoomId(
            $request->input('room_id'),
            $datacenterId,
            $roomOptions->pluck('id')->toArray()
        );

        $rowOptions = $this->getRowOptions($roomId);
        $rowId = $this->validateRowId(
            $request->input('row_id'),
            $roomId,
            $rowOptions->pluck('id')->toArray()
        );

        // Generate the PDF report
        $filePath = $this->reportService->generatePdfReport(
            [
                'datacenter_id' => $datacenterId,
                'room_id' => $roomId,
                'row_id' => $rowId,
            ],
            $user
        );

        $filename = basename($filePath);

        return Storage::disk('local')->download($filePath, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Export capacity report as CSV.
     */
    public function exportCsv(Request $request): BinaryFileResponse
    {
        $user = $request->user();

        // Get accessible datacenters based on user role
        $datacenterOptions = $this->getAccessibleDatacenters($user);
        $accessibleDatacenterIds = $datacenterOptions->pluck('id')->toArray();

        // Get and validate filter values
        $datacenterId = $this->validateDatacenterId(
            $request->input('datacenter_id'),
            $accessibleDatacenterIds
        );

        $roomOptions = $this->getRoomOptions($datacenterId);
        $roomId = $this->validateRoomId(
            $request->input('room_id'),
            $datacenterId,
            $roomOptions->pluck('id')->toArray()
        );

        $rowOptions = $this->getRowOptions($roomId);
        $rowId = $this->validateRowId(
            $request->input('row_id'),
            $roomId,
            $rowOptions->pluck('id')->toArray()
        );

        $filters = [
            'datacenter_id' => $datacenterId,
            'room_id' => $roomId,
            'row_id' => $rowId,
        ];

        $timestamp = now()->format('Y-m-d-His');
        $filename = "capacity-report-{$timestamp}.csv";

        return Excel::download(new CapacityReportExport($filters), $filename);
    }

    /**
     * Get datacenters accessible by the user.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function getAccessibleDatacenters($user): Collection
    {
        $query = Datacenter::query()->orderBy('name');

        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            $assignedDatacenterIds = $user->datacenters()->pluck('datacenters.id');
            $query->whereIn('id', $assignedDatacenterIds);
        }

        return $query->get()->map(fn (Datacenter $datacenter) => [
            'id' => $datacenter->id,
            'name' => $datacenter->name,
        ]);
    }

    /**
     * Get rooms for a datacenter (for cascading filter).
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function getRoomOptions(?int $datacenterId): Collection
    {
        if ($datacenterId === null) {
            return collect();
        }

        return Room::query()
            ->where('datacenter_id', $datacenterId)
            ->orderBy('name')
            ->get()
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'name' => $room->name,
            ]);
    }

    /**
     * Get rows for a room (for cascading filter).
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function getRowOptions(?int $roomId): Collection
    {
        if ($roomId === null) {
            return collect();
        }

        return Row::query()
            ->where('room_id', $roomId)
            ->orderBy('name')
            ->get()
            ->map(fn (Row $row) => [
                'id' => $row->id,
                'name' => $row->name,
            ]);
    }

    /**
     * Validate and return datacenter ID if it's in the accessible list.
     *
     * @param  array<int>  $accessibleIds
     */
    private function validateDatacenterId(mixed $datacenterId, array $accessibleIds): ?int
    {
        if ($datacenterId === null || $datacenterId === '') {
            return null;
        }

        $id = (int) $datacenterId;

        return in_array($id, $accessibleIds, true) ? $id : null;
    }

    /**
     * Validate and return room ID if it belongs to the selected datacenter.
     *
     * @param  array<int>  $validRoomIds
     */
    private function validateRoomId(mixed $roomId, ?int $datacenterId, array $validRoomIds): ?int
    {
        if ($datacenterId === null || $roomId === null || $roomId === '') {
            return null;
        }

        $id = (int) $roomId;

        return in_array($id, $validRoomIds, true) ? $id : null;
    }

    /**
     * Validate and return row ID if it belongs to the selected room.
     *
     * @param  array<int>  $validRowIds
     */
    private function validateRowId(mixed $rowId, ?int $roomId, array $validRowIds): ?int
    {
        if ($roomId === null || $rowId === null || $rowId === '') {
            return null;
        }

        $id = (int) $rowId;

        return in_array($id, $validRowIds, true) ? $id : null;
    }

    /**
     * Get historical capacity snapshots for sparkline visualization.
     *
     * Retrieves snapshots for the last 12 weeks, ordered chronologically.
     *
     * @return array<int, array{date: string, rack_utilization: float, power_utilization: float|null}>
     */
    private function getHistoricalSnapshots(?int $datacenterId): array
    {
        $query = CapacitySnapshot::query()
            ->orderBy('snapshot_date', 'asc')
            ->where('snapshot_date', '>=', now()->subWeeks(12));

        if ($datacenterId !== null) {
            $query->where('datacenter_id', $datacenterId);
        }

        return $query->get()->map(fn (CapacitySnapshot $snapshot) => [
            'date' => $snapshot->snapshot_date->format('Y-m-d'),
            'rack_utilization' => (float) $snapshot->rack_utilization_percent,
            'power_utilization' => $snapshot->power_utilization_percent !== null
                ? (float) $snapshot->power_utilization_percent
                : null,
        ])->toArray();
    }

    /**
     * Calculate week-over-week trend data from historical snapshots.
     *
     * Compares the most recent snapshot with the previous one to calculate
     * percentage change in both rack and power utilization.
     *
     * @param  array<int, array{date: string, rack_utilization: float, power_utilization: float|null}>  $snapshots
     * @return array{rack_utilization_trend: array{percentage: string, change: string}|null, power_utilization_trend: array{percentage: string, change: string}|null, has_trend_data: bool}
     */
    private function calculateWeekOverWeekTrend(array $snapshots): array
    {
        $result = [
            'rack_utilization_trend' => null,
            'power_utilization_trend' => null,
            'has_trend_data' => false,
        ];

        if (count($snapshots) < 2) {
            return $result;
        }

        $result['has_trend_data'] = true;

        // Get the two most recent snapshots
        $currentSnapshot = $snapshots[count($snapshots) - 1];
        $previousSnapshot = $snapshots[count($snapshots) - 2];

        // Calculate rack utilization trend
        $currentRackUtil = $currentSnapshot['rack_utilization'];
        $previousRackUtil = $previousSnapshot['rack_utilization'];
        $rackDiff = $currentRackUtil - $previousRackUtil;

        if ($previousRackUtil > 0) {
            $rackPercentChange = ($rackDiff / $previousRackUtil) * 100;
            $result['rack_utilization_trend'] = [
                'percentage' => $this->formatTrendPercentage($rackPercentChange),
                'change' => $this->formatTrendChange($rackDiff, 'rack utilization'),
            ];
        }

        // Calculate power utilization trend (if data exists)
        if ($currentSnapshot['power_utilization'] !== null && $previousSnapshot['power_utilization'] !== null) {
            $currentPowerUtil = $currentSnapshot['power_utilization'];
            $previousPowerUtil = $previousSnapshot['power_utilization'];
            $powerDiff = $currentPowerUtil - $previousPowerUtil;

            if ($previousPowerUtil > 0) {
                $powerPercentChange = ($powerDiff / $previousPowerUtil) * 100;
                $result['power_utilization_trend'] = [
                    'percentage' => $this->formatTrendPercentage($powerPercentChange),
                    'change' => $this->formatTrendChange($powerDiff, 'power utilization'),
                ];
            }
        }

        return $result;
    }

    /**
     * Format a percentage change value for display.
     */
    private function formatTrendPercentage(float $value): string
    {
        $rounded = round($value, 1);

        if ($rounded === 0.0) {
            return '0%';
        }

        if ($rounded > 0) {
            return '+'.number_format($rounded, 1).'%';
        }

        return number_format($rounded, 1).'%';
    }

    /**
     * Format a trend change description.
     */
    private function formatTrendChange(float $diff, string $metric): string
    {
        $rounded = round($diff, 1);

        if ($rounded === 0.0) {
            return 'No change in '.$metric;
        }

        if ($rounded > 0) {
            return '+'.number_format($rounded, 1).'% increase in '.$metric.' vs last week';
        }

        return number_format(abs($rounded), 1).'% decrease in '.$metric.' vs last week';
    }
}
