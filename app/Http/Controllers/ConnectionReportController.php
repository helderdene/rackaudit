<?php

namespace App\Http\Controllers;

use App\Exports\ConnectionReportExport;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Room;
use App\Services\ConnectionCalculationService;
use App\Services\ConnectionReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for managing connection reports.
 *
 * Provides connection inventory reports, cable type distribution,
 * port utilization metrics, and export capabilities (PDF/CSV).
 */
class ConnectionReportController extends Controller
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
     * Number of connections per page in the inventory table.
     */
    private const CONNECTIONS_PER_PAGE = 25;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ConnectionCalculationService $calculationService,
        protected ConnectionReportService $reportService
    ) {}

    /**
     * Display the connection reports page.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $isAdmin = $user->hasAnyRole(self::ADMIN_ROLES);

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

        // Get connection metrics via service
        // For restricted users without a datacenter filter, calculate metrics per accessible datacenter
        if ($datacenterId === null && ! $isAdmin && ! empty($accessibleDatacenterIds)) {
            // Aggregate metrics across accessible datacenters only
            $metrics = $this->getMetricsForDatacenterIds($accessibleDatacenterIds, $roomId);
        } else {
            $metrics = $this->calculationService->getConnectionMetrics(
                $datacenterId,
                $roomId
            );
        }

        // Get all connections for client-side filtering/sorting/pagination
        $connections = $this->getAllConnections(
            $datacenterId,
            $roomId,
            $accessibleDatacenterIds,
            $isAdmin
        );

        // Add connections to metrics
        $metrics['connections'] = $connections;

        return Inertia::render('ConnectionReports/Index', [
            'metrics' => $metrics,
            'datacenterOptions' => $datacenterOptions->values()->toArray(),
            'roomOptions' => $roomOptions->values()->toArray(),
            'filters' => [
                'datacenter_id' => $datacenterId,
                'room_id' => $roomId,
            ],
        ]);
    }

    /**
     * Export connection report as PDF.
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

        // Generate the PDF report
        $filePath = $this->reportService->generatePdfReport(
            [
                'datacenter_id' => $datacenterId,
                'room_id' => $roomId,
            ],
            $user
        );

        $filename = basename($filePath);

        return Storage::disk('local')->download($filePath, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Export connection report as CSV.
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

        $filters = [
            'datacenter_id' => $datacenterId,
            'room_id' => $roomId,
        ];

        $timestamp = now()->format('Y-m-d-His');
        $filename = "connection-report-{$timestamp}.csv";

        return Excel::download(new ConnectionReportExport($filters), $filename);
    }

    /**
     * Get all connections for client-side filtering/sorting/pagination.
     *
     * @param  array<int>  $accessibleDatacenterIds
     * @return array<int, array<string, mixed>>
     */
    private function getAllConnections(
        ?int $datacenterId,
        ?int $roomId,
        array $accessibleDatacenterIds,
        bool $isAdmin
    ): array {
        $query = $this->calculationService->buildFilteredConnectionQuery(
            $datacenterId,
            $roomId
        );

        // Apply role-based datacenter restriction if no specific datacenter filter
        if ($datacenterId === null && ! $isAdmin && ! empty($accessibleDatacenterIds)) {
            $query->whereHas('sourcePort.device.rack.row.room', function ($subQuery) use ($accessibleDatacenterIds) {
                $subQuery->whereIn('datacenter_id', $accessibleDatacenterIds);
            });
        }

        return $query
            ->with(['sourcePort.device', 'destinationPort.device'])
            ->orderBy('id')
            ->get()
            ->map(function (Connection $connection) {
                return [
                    'id' => $connection->id,
                    'source_device_name' => $connection->sourcePort?->device?->name,
                    'source_port_label' => $connection->sourcePort?->label,
                    'destination_device_name' => $connection->destinationPort?->device?->name,
                    'destination_port_label' => $connection->destinationPort?->label,
                    'cable_type' => $connection->cable_type?->value,
                    'cable_type_label' => $connection->cable_type?->label() ?? 'Unknown',
                    'cable_length' => $connection->cable_length,
                    'cable_color' => $connection->cable_color,
                ];
            })
            ->toArray();
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
     * Get metrics aggregated across specific datacenter IDs.
     *
     * For users with restricted access who have multiple datacenters assigned,
     * this aggregates metrics from each accessible datacenter.
     *
     * @param  array<int>  $datacenterIds
     * @return array<string, mixed>
     */
    private function getMetricsForDatacenterIds(array $datacenterIds, ?int $roomId): array
    {
        if (empty($datacenterIds)) {
            return $this->getEmptyMetrics();
        }

        // For a single datacenter, use the standard service method
        if (count($datacenterIds) === 1) {
            return $this->calculationService->getConnectionMetrics($datacenterIds[0], $roomId);
        }

        // For multiple datacenters, aggregate metrics from each
        $totalConnections = 0;
        $cableTypeAggregated = [];
        $portTypeAggregated = [];
        $cableLengths = [];
        $portUtilizationTotals = [];

        foreach ($datacenterIds as $dcId) {
            $dcMetrics = $this->calculationService->getConnectionMetrics($dcId, $roomId);

            $totalConnections += $dcMetrics['totalConnections'];

            // Aggregate cable type distribution
            foreach ($dcMetrics['cableTypeDistribution'] as $cableType) {
                if (! isset($cableTypeAggregated[$cableType['type']])) {
                    $cableTypeAggregated[$cableType['type']] = [
                        'type' => $cableType['type'],
                        'label' => $cableType['label'],
                        'count' => 0,
                    ];
                }
                $cableTypeAggregated[$cableType['type']]['count'] += $cableType['count'];
            }

            // Aggregate port type distribution
            foreach ($dcMetrics['portTypeDistribution'] as $portType) {
                if (! isset($portTypeAggregated[$portType['type']])) {
                    $portTypeAggregated[$portType['type']] = [
                        'type' => $portType['type'],
                        'label' => $portType['label'],
                        'count' => 0,
                    ];
                }
                $portTypeAggregated[$portType['type']]['count'] += $portType['count'];
            }

            // Collect cable length stats for aggregation
            if ($dcMetrics['cableLengthStats']['count'] > 0) {
                $cableLengths[] = $dcMetrics['cableLengthStats'];
            }

            // Aggregate port utilization
            foreach ($dcMetrics['portUtilization']['byType'] as $utilType) {
                if (! isset($portUtilizationTotals[$utilType['type']])) {
                    $portUtilizationTotals[$utilType['type']] = [
                        'type' => $utilType['type'],
                        'label' => $utilType['label'],
                        'total' => 0,
                        'connected' => 0,
                    ];
                }
                $portUtilizationTotals[$utilType['type']]['total'] += $utilType['total'];
                $portUtilizationTotals[$utilType['type']]['connected'] += $utilType['connected'];
            }
        }

        // Calculate percentages for aggregated cable types
        $cableTypeDistribution = [];
        foreach ($cableTypeAggregated as $type) {
            $type['percentage'] = $totalConnections > 0
                ? round(($type['count'] / $totalConnections) * 100, 1)
                : 0.0;
            $cableTypeDistribution[] = $type;
        }

        // Calculate percentages for aggregated port types
        $portTypeDistribution = [];
        foreach ($portTypeAggregated as $type) {
            $type['percentage'] = $totalConnections > 0
                ? round(($type['count'] / $totalConnections) * 100, 1)
                : 0.0;
            $portTypeDistribution[] = $type;
        }

        // Calculate aggregated cable length stats
        $cableLengthStats = $this->aggregateCableLengthStats($cableLengths);

        // Calculate aggregated port utilization
        $portUtilization = $this->aggregatePortUtilization($portUtilizationTotals);

        return [
            'totalConnections' => $totalConnections,
            'cableTypeDistribution' => $cableTypeDistribution,
            'portTypeDistribution' => $portTypeDistribution,
            'cableLengthStats' => $cableLengthStats,
            'portUtilization' => $portUtilization,
        ];
    }

    /**
     * Get empty metrics structure.
     *
     * @return array<string, mixed>
     */
    private function getEmptyMetrics(): array
    {
        return [
            'totalConnections' => 0,
            'cableTypeDistribution' => [],
            'portTypeDistribution' => [],
            'cableLengthStats' => ['mean' => null, 'min' => null, 'max' => null, 'count' => 0],
            'portUtilization' => [
                'byType' => [],
                'byStatus' => [],
                'overall' => ['total' => 0, 'connected' => 0, 'percentage' => 0.0],
            ],
        ];
    }

    /**
     * Aggregate cable length statistics from multiple datacenters.
     *
     * @param  array<array{mean: float|null, min: float|null, max: float|null, count: int}>  $lengthStats
     * @return array{mean: float|null, min: float|null, max: float|null, count: int}
     */
    private function aggregateCableLengthStats(array $lengthStats): array
    {
        if (empty($lengthStats)) {
            return ['mean' => null, 'min' => null, 'max' => null, 'count' => 0];
        }

        $totalCount = 0;
        $weightedSum = 0.0;
        $min = null;
        $max = null;

        foreach ($lengthStats as $stats) {
            $totalCount += $stats['count'];
            $weightedSum += ($stats['mean'] ?? 0) * $stats['count'];

            if ($stats['min'] !== null && ($min === null || $stats['min'] < $min)) {
                $min = $stats['min'];
            }
            if ($stats['max'] !== null && ($max === null || $stats['max'] > $max)) {
                $max = $stats['max'];
            }
        }

        return [
            'mean' => $totalCount > 0 ? round($weightedSum / $totalCount, 2) : null,
            'min' => $min,
            'max' => $max,
            'count' => $totalCount,
        ];
    }

    /**
     * Aggregate port utilization from multiple datacenters.
     *
     * @param  array<string, array{type: string, label: string, total: int, connected: int}>  $utilTotals
     * @return array{byType: array<mixed>, byStatus: array<mixed>, overall: array{total: int, connected: int, percentage: float}}
     */
    private function aggregatePortUtilization(array $utilTotals): array
    {
        $byType = [];
        $totalPorts = 0;
        $totalConnected = 0;

        foreach ($utilTotals as $util) {
            $percentage = $util['total'] > 0
                ? round(($util['connected'] / $util['total']) * 100, 1)
                : 0.0;

            $byType[] = [
                'type' => $util['type'],
                'label' => $util['label'],
                'total' => $util['total'],
                'connected' => $util['connected'],
                'percentage' => $percentage,
            ];

            $totalPorts += $util['total'];
            $totalConnected += $util['connected'];
        }

        $overallPercentage = $totalPorts > 0
            ? round(($totalConnected / $totalPorts) * 100, 1)
            : 0.0;

        return [
            'byType' => $byType,
            'byStatus' => [], // Status breakdown not available in aggregation
            'overall' => [
                'total' => $totalPorts,
                'connected' => $totalConnected,
                'percentage' => $overallPercentage,
            ],
        ];
    }
}
