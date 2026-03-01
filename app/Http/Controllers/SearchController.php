<?php

namespace App\Http\Controllers;

use App\Enums\DeviceLifecycleStatus;
use App\Enums\PortStatus;
use App\Enums\PortType;
use App\Enums\RackStatus;
use App\Http\Requests\SearchRequest;
use App\Models\Datacenter;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller for search API endpoints and search results page.
 *
 * Provides quick search for dropdown/typeahead functionality,
 * full search with pagination and filtering capabilities,
 * and an Inertia-powered search results page.
 */
class SearchController extends Controller
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

    public function __construct(
        private SearchService $searchService
    ) {}

    /**
     * Display the search results page.
     *
     * Returns an Inertia page with:
     * - Search results grouped by entity type
     * - Filter options (datacenters, rooms, rows, racks, enum values)
     * - Current filter state for persistence
     *
     * Route: GET /search
     * Query parameters:
     * - q: Search query string
     * - type: Entity type filter (datacenters, racks, devices, ports, connections)
     * - datacenter_id, room_id, row_id, rack_id: Hierarchical location filters
     * - lifecycle_status, port_type, port_status, rack_status: Entity-specific filters
     */
    public function index(SearchRequest $request): InertiaResponse
    {
        $user = $request->user();
        $query = $request->getSearchQuery();
        $filters = $request->getFilters();
        $entityType = $request->getEntityType();

        // Perform search
        if ($entityType !== null) {
            $results = [
                $entityType => $this->searchService->searchByEntityType(
                    $query,
                    $entityType,
                    $user,
                    $filters
                ),
            ];

            // Add empty structures for other entity types
            $allEntityTypes = ['datacenters', 'racks', 'devices', 'ports', 'connections'];
            foreach ($allEntityTypes as $type) {
                if (! isset($results[$type])) {
                    $results[$type] = ['items' => [], 'total' => 0];
                }
            }
        } else {
            $results = $this->searchService->search(
                $query,
                $user,
                $filters
            );
        }

        // Build filter options
        $filterOptions = $this->buildFilterOptions($user, $filters);

        return Inertia::render('Search/Index', [
            'results' => $results,
            'query' => $query,
            'filters' => [
                'type' => $entityType,
                'datacenter_id' => $filters['datacenter_id'] ?? null,
                'room_id' => $filters['room_id'] ?? null,
                'row_id' => $filters['row_id'] ?? null,
                'rack_id' => $filters['rack_id'] ?? null,
                'lifecycle_status' => $filters['lifecycle_status'] ?? null,
                'port_type' => $filters['port_type'] ?? null,
                'port_status' => $filters['port_status'] ?? null,
                'rack_status' => $filters['rack_status'] ?? null,
            ],
            'filterOptions' => $filterOptions,
        ]);
    }

    /**
     * Perform a quick search for dropdown/typeahead results.
     *
     * Returns a maximum of 5 results per entity type for fast dropdown display.
     * Frontend should apply a 300ms debounce before calling this endpoint.
     *
     * Route: GET /api/search/quick
     * Query parameter: q (search query string)
     */
    public function quickSearch(SearchRequest $request): JsonResponse
    {
        $query = $request->getSearchQuery();
        $filters = $request->getFilters();

        $results = $this->searchService->quickSearch(
            $query,
            $request->user(),
            $filters
        );

        return response()->json([
            'data' => $results,
            'query' => $query,
        ]);
    }

    /**
     * Perform a full search with pagination and filtering.
     *
     * Returns paginated results (up to 20 per entity type) with support for:
     * - Hierarchical location filters (datacenter, room, row, rack)
     * - Entity-specific attribute filters (lifecycle_status, port_type, port_status, rack_status)
     * - Entity type filter to show only specific entity types
     *
     * Route: GET /api/search
     * Query parameters:
     * - q: Search query string
     * - type: Entity type filter (datacenters, racks, devices, ports, connections)
     * - datacenter_id, room_id, row_id, rack_id: Hierarchical location filters
     * - lifecycle_status, port_type, port_status, rack_status: Entity-specific filters
     */
    public function search(SearchRequest $request): JsonResponse
    {
        $query = $request->getSearchQuery();
        $filters = $request->getFilters();
        $entityType = $request->getEntityType();

        // If a specific entity type is requested, search only that type
        if ($entityType !== null) {
            $results = [
                $entityType => $this->searchService->searchByEntityType(
                    $query,
                    $entityType,
                    $request->user(),
                    $filters
                ),
            ];

            // Add empty structures for other entity types to maintain consistent response structure
            $allEntityTypes = ['datacenters', 'racks', 'devices', 'ports', 'connections'];
            foreach ($allEntityTypes as $type) {
                if (! isset($results[$type])) {
                    $results[$type] = ['items' => [], 'total' => 0];
                }
            }
        } else {
            // Search all entity types
            $results = $this->searchService->search(
                $query,
                $request->user(),
                $filters
            );
        }

        return response()->json([
            'data' => $results,
            'query' => $query,
            'filters' => $filters,
        ]);
    }

    /**
     * Build filter options for the search results page.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildFilterOptions($user, array $filters): array
    {
        // Get accessible datacenters based on user role
        $datacenters = $this->getAccessibleDatacenters($user);

        // Get rooms based on selected datacenter
        $rooms = $this->getRoomOptions($filters['datacenter_id'] ?? null);

        // Get rows based on selected room
        $rows = $this->getRowOptions($filters['room_id'] ?? null);

        // Get racks based on selected row
        $racks = $this->getRackOptions($filters['row_id'] ?? null);

        return [
            'datacenters' => $datacenters->values()->toArray(),
            'rooms' => $rooms->values()->toArray(),
            'rows' => $rows->values()->toArray(),
            'racks' => $racks->values()->toArray(),
            'lifecycleStatuses' => collect(DeviceLifecycleStatus::cases())
                ->map(fn (DeviceLifecycleStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values()
                ->toArray(),
            'portTypes' => collect(PortType::cases())
                ->map(fn (PortType $type) => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ])
                ->values()
                ->toArray(),
            'portStatuses' => collect(PortStatus::cases())
                ->map(fn (PortStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values()
                ->toArray(),
            'rackStatuses' => collect(RackStatus::cases())
                ->map(fn (RackStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values()
                ->toArray(),
            'entityTypes' => [
                ['value' => 'datacenters', 'label' => 'Datacenters'],
                ['value' => 'racks', 'label' => 'Racks'],
                ['value' => 'devices', 'label' => 'Devices'],
                ['value' => 'ports', 'label' => 'Ports'],
                ['value' => 'connections', 'label' => 'Connections'],
            ],
        ];
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
     * Get racks for a row (for cascading filter).
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function getRackOptions(?int $rowId): Collection
    {
        if ($rowId === null) {
            return collect();
        }

        return Rack::query()
            ->where('row_id', $rowId)
            ->orderBy('name')
            ->get()
            ->map(fn (Rack $rack) => [
                'id' => $rack->id,
                'name' => $rack->name,
            ]);
    }
}
