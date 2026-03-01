<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDatacenterRequest;
use App\Http\Requests\UpdateDatacenterRequest;
use App\Http\Resources\ComparisonResultResource;
use App\Http\Resources\ImplementationFileResource;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\ExpectedConnection;
use App\Models\Rack;
use App\Services\ConnectionComparisonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DatacenterController extends Controller
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
     * Display a paginated list of datacenters with search filtering.
     */
    public function index(Request $request): InertiaResponse
    {
        Gate::authorize('viewAny', Datacenter::class);

        $user = $request->user();
        $query = Datacenter::query();

        // Filter by user access: non-admin users see only assigned datacenters
        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            $assignedDatacenterIds = $user->datacenters()->pluck('datacenters.id');
            $query->whereIn('id', $assignedDatacenterIds);
        }

        // Search by name, city, or contact name
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('primary_contact_name', 'like', "%{$search}%");
            });
        }

        $datacenters = $query->orderBy('name')
            ->paginate(15)
            ->through(function (Datacenter $datacenter) {
                return [
                    'id' => $datacenter->id,
                    'name' => $datacenter->name,
                    'city' => $datacenter->city,
                    'country' => $datacenter->country,
                    'formatted_location' => $datacenter->formatted_location,
                    'primary_contact_name' => $datacenter->primary_contact_name,
                    'primary_contact_email' => $datacenter->primary_contact_email,
                    'primary_contact_phone' => $datacenter->primary_contact_phone,
                    'floor_plan_path' => $datacenter->floor_plan_path,
                ];
            });

        return Inertia::render('Datacenters/Index', [
            'datacenters' => $datacenters,
            'filters' => [
                'search' => $request->input('search', ''),
            ],
            'canCreate' => $request->user()->hasAnyRole(self::ADMIN_ROLES),
        ]);
    }

    /**
     * Show the form for creating a new datacenter.
     */
    public function create(): InertiaResponse
    {
        Gate::authorize('create', Datacenter::class);

        return Inertia::render('Datacenters/Create');
    }

    /**
     * Store a newly created datacenter.
     */
    public function store(StoreDatacenterRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Handle floor plan file upload
        $floorPlanPath = null;
        if ($request->hasFile('floor_plan')) {
            $floorPlanPath = $this->storeFloorPlan($request->file('floor_plan'), null);
        }

        $datacenter = Datacenter::create([
            'name' => $validated['name'],
            'address_line_1' => $validated['address_line_1'],
            'address_line_2' => $validated['address_line_2'] ?? null,
            'city' => $validated['city'],
            'state_province' => $validated['state_province'],
            'postal_code' => $validated['postal_code'],
            'country' => $validated['country'],
            'company_name' => $validated['company_name'] ?? null,
            'primary_contact_name' => $validated['primary_contact_name'],
            'primary_contact_email' => $validated['primary_contact_email'],
            'primary_contact_phone' => $validated['primary_contact_phone'],
            'secondary_contact_name' => $validated['secondary_contact_name'] ?? null,
            'secondary_contact_email' => $validated['secondary_contact_email'] ?? null,
            'secondary_contact_phone' => $validated['secondary_contact_phone'] ?? null,
            'floor_plan_path' => $floorPlanPath,
        ]);

        // If we uploaded a file before creating the datacenter, rename it with the datacenter ID
        if ($floorPlanPath && $request->hasFile('floor_plan')) {
            $newPath = $this->renameFloorPlanWithId($floorPlanPath, $datacenter->id);
            if ($newPath !== $floorPlanPath) {
                $datacenter->update(['floor_plan_path' => $newPath]);
            }
        }

        return redirect()->route('datacenters.index')
            ->with('success', 'Datacenter created successfully.');
    }

    /**
     * Display the specified datacenter.
     */
    public function show(Datacenter $datacenter): InertiaResponse
    {
        Gate::authorize('view', $datacenter);

        $user = request()->user();

        // Eager load implementation files with uploader relationship
        $implementationFiles = $datacenter->implementationFiles()
            ->with('uploader')
            ->orderByDesc('created_at')
            ->get();

        // Determine permissions for implementation files based on policy
        $canUploadFiles = $user->hasAnyRole(self::ADMIN_ROLES);
        $canDeleteFiles = $user->hasAnyRole(self::ADMIN_ROLES);

        return Inertia::render('Datacenters/Show', [
            'datacenter' => [
                'id' => $datacenter->id,
                'name' => $datacenter->name,
                'address_line_1' => $datacenter->address_line_1,
                'address_line_2' => $datacenter->address_line_2,
                'city' => $datacenter->city,
                'state_province' => $datacenter->state_province,
                'postal_code' => $datacenter->postal_code,
                'country' => $datacenter->country,
                'formatted_address' => $datacenter->formatted_address,
                'formatted_location' => $datacenter->formatted_location,
                'company_name' => $datacenter->company_name,
                'primary_contact_name' => $datacenter->primary_contact_name,
                'primary_contact_email' => $datacenter->primary_contact_email,
                'primary_contact_phone' => $datacenter->primary_contact_phone,
                'secondary_contact_name' => $datacenter->secondary_contact_name,
                'secondary_contact_email' => $datacenter->secondary_contact_email,
                'secondary_contact_phone' => $datacenter->secondary_contact_phone,
                'floor_plan_path' => $datacenter->floor_plan_path,
                'floor_plan_url' => $datacenter->floor_plan_path
                    ? Storage::url($datacenter->floor_plan_path)
                    : null,
                'created_at' => $datacenter->created_at,
                'updated_at' => $datacenter->updated_at,
                'has_approved_implementation_files' => $datacenter->hasApprovedImplementationFiles(),
            ],
            'implementationFiles' => ImplementationFileResource::collection($implementationFiles)->resolve(),
            'canEdit' => $user->hasAnyRole(self::ADMIN_ROLES),
            'canDelete' => $user->hasAnyRole(self::ADMIN_ROLES),
            'canUploadFiles' => $canUploadFiles,
            'canDeleteFiles' => $canDeleteFiles,
        ]);
    }

    /**
     * Show the form for editing the specified datacenter.
     */
    public function edit(Datacenter $datacenter): InertiaResponse
    {
        Gate::authorize('update', $datacenter);

        return Inertia::render('Datacenters/Edit', [
            'datacenter' => [
                'id' => $datacenter->id,
                'name' => $datacenter->name,
                'address_line_1' => $datacenter->address_line_1,
                'address_line_2' => $datacenter->address_line_2,
                'city' => $datacenter->city,
                'state_province' => $datacenter->state_province,
                'postal_code' => $datacenter->postal_code,
                'country' => $datacenter->country,
                'company_name' => $datacenter->company_name,
                'primary_contact_name' => $datacenter->primary_contact_name,
                'primary_contact_email' => $datacenter->primary_contact_email,
                'primary_contact_phone' => $datacenter->primary_contact_phone,
                'secondary_contact_name' => $datacenter->secondary_contact_name,
                'secondary_contact_email' => $datacenter->secondary_contact_email,
                'secondary_contact_phone' => $datacenter->secondary_contact_phone,
                'floor_plan_path' => $datacenter->floor_plan_path,
                'floor_plan_url' => $datacenter->floor_plan_path
                    ? Storage::url($datacenter->floor_plan_path)
                    : null,
            ],
        ]);
    }

    /**
     * Update the specified datacenter.
     */
    public function update(UpdateDatacenterRequest $request, Datacenter $datacenter): RedirectResponse
    {
        $validated = $request->validated();

        // Handle floor plan file changes
        $floorPlanPath = $datacenter->floor_plan_path;

        // Check if floor plan should be removed
        if ($request->boolean('remove_floor_plan') && $floorPlanPath) {
            $this->deleteFloorPlan($floorPlanPath);
            $floorPlanPath = null;
        }

        // Check if new floor plan is uploaded
        if ($request->hasFile('floor_plan')) {
            // Delete old floor plan if exists
            if ($datacenter->floor_plan_path) {
                $this->deleteFloorPlan($datacenter->floor_plan_path);
            }

            // Store new floor plan
            $floorPlanPath = $this->storeFloorPlan($request->file('floor_plan'), $datacenter->id);
        }

        $datacenter->update([
            'name' => $validated['name'],
            'address_line_1' => $validated['address_line_1'],
            'address_line_2' => $validated['address_line_2'] ?? null,
            'city' => $validated['city'],
            'state_province' => $validated['state_province'],
            'postal_code' => $validated['postal_code'],
            'country' => $validated['country'],
            'company_name' => $validated['company_name'] ?? null,
            'primary_contact_name' => $validated['primary_contact_name'],
            'primary_contact_email' => $validated['primary_contact_email'],
            'primary_contact_phone' => $validated['primary_contact_phone'],
            'secondary_contact_name' => $validated['secondary_contact_name'] ?? null,
            'secondary_contact_email' => $validated['secondary_contact_email'] ?? null,
            'secondary_contact_phone' => $validated['secondary_contact_phone'] ?? null,
            'floor_plan_path' => $floorPlanPath,
        ]);

        return redirect()->route('datacenters.index')
            ->with('success', 'Datacenter updated successfully.');
    }

    /**
     * Remove the specified datacenter.
     */
    public function destroy(Datacenter $datacenter): RedirectResponse
    {
        Gate::authorize('delete', $datacenter);

        // Delete floor plan file if exists
        if ($datacenter->floor_plan_path) {
            $this->deleteFloorPlan($datacenter->floor_plan_path);
        }

        $datacenter->delete();

        return redirect()->route('datacenters.index')
            ->with('success', 'Datacenter deleted successfully.');
    }

    /**
     * Display the connection comparison page for the datacenter.
     *
     * Aggregates confirmed expected connections from all approved implementation
     * files and compares against actual connections. Includes conflict detection
     * and pagination.
     */
    public function connectionComparison(
        Request $request,
        Datacenter $datacenter,
        ConnectionComparisonService $comparisonService,
    ): InertiaResponse {
        Gate::authorize('view', $datacenter);

        // Get comparison results using the service
        $results = $comparisonService->compareForDatacenter($datacenter);

        // Get statistics (unaffected by pagination)
        $statistics = $results->getStatistics();

        // Handle pagination
        $perPage = 50;
        $currentPage = (int) $request->input('page', 1);
        $total = $results->count();

        // Get paginated items - paginate() returns array with 'items' key
        $offset = ($currentPage - 1) * $perPage;
        $paginatedData = $results->paginate($offset, $perPage);
        $paginatedItems = $paginatedData['items'];

        // Get filter options (devices and racks involved in comparisons)
        $filterOptions = $this->getComparisonFilterOptions($datacenter);

        // Build pagination metadata
        $lastPage = max(1, (int) ceil($total / $perPage));
        $paginationMeta = [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'from' => $total > 0 ? $offset + 1 : null,
            'to' => $total > 0 ? min($offset + $perPage, $total) : null,
        ];

        return Inertia::render('Datacenters/ConnectionComparison', [
            'datacenter' => [
                'id' => $datacenter->id,
                'name' => $datacenter->name,
                'formatted_location' => $datacenter->formatted_location,
            ],
            'initialComparisons' => ComparisonResultResource::collection($paginatedItems)->resolve(),
            'filterOptions' => $filterOptions,
            'statistics' => $statistics,
            'paginationMeta' => $paginationMeta,
        ]);
    }

    /**
     * Get filter options for devices and racks involved in the datacenter comparison.
     *
     * @return array{devices: array, racks: array}
     */
    protected function getComparisonFilterOptions(Datacenter $datacenter): array
    {
        // Get all approved implementation files for the datacenter
        $approvedFileIds = $datacenter->implementationFiles()
            ->where('approval_status', 'approved')
            ->pluck('id');

        // Get all devices involved in expected connections for these files
        $expectedConnections = ExpectedConnection::query()
            ->whereIn('implementation_file_id', $approvedFileIds)
            ->confirmed()
            ->whereNotNull('source_port_id')
            ->whereNotNull('dest_port_id')
            ->with(['sourceDevice.rack', 'destDevice.rack'])
            ->get();

        $deviceIds = collect();
        $rackIds = collect();

        foreach ($expectedConnections as $expected) {
            if ($expected->sourceDevice) {
                $deviceIds->push($expected->source_device_id);
                if ($expected->sourceDevice->rack) {
                    $rackIds->push($expected->sourceDevice->rack_id);
                }
            }
            if ($expected->destDevice) {
                $deviceIds->push($expected->dest_device_id);
                if ($expected->destDevice->rack) {
                    $rackIds->push($expected->destDevice->rack_id);
                }
            }
        }

        $deviceIds = $deviceIds->unique();
        $rackIds = $rackIds->unique();

        // Get device data
        $devices = Device::whereIn('id', $deviceIds)
            ->orderBy('name')
            ->get()
            ->map(fn (Device $device) => [
                'id' => $device->id,
                'name' => $device->name,
            ])
            ->values()
            ->toArray();

        // Get rack data
        $racks = Rack::whereIn('id', $rackIds)
            ->orderBy('name')
            ->get()
            ->map(fn (Rack $rack) => [
                'id' => $rack->id,
                'name' => $rack->name,
            ])
            ->values()
            ->toArray();

        return [
            'devices' => $devices,
            'racks' => $racks,
        ];
    }

    /**
     * Store a floor plan file and return the path.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     */
    private function storeFloorPlan($file, ?int $datacenterId): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = time();
        $idPart = $datacenterId ?? 'temp';
        $filename = "{$idPart}_{$timestamp}.{$extension}";

        return $file->storeAs('floor-plans', $filename, 'public');
    }

    /**
     * Rename a floor plan file to include the datacenter ID.
     */
    private function renameFloorPlanWithId(string $currentPath, int $datacenterId): string
    {
        // If the path already contains the datacenter ID, no need to rename
        if (str_contains($currentPath, "/{$datacenterId}_")) {
            return $currentPath;
        }

        // Extract the filename info
        $pathInfo = pathinfo($currentPath);
        $extension = $pathInfo['extension'] ?? 'png';
        $timestamp = time();
        $newFilename = "{$datacenterId}_{$timestamp}.{$extension}";
        $newPath = "floor-plans/{$newFilename}";

        // Move the file
        if (Storage::disk('public')->exists($currentPath)) {
            Storage::disk('public')->move($currentPath, $newPath);

            return $newPath;
        }

        return $currentPath;
    }

    /**
     * Delete a floor plan file from storage.
     */
    private function deleteFloorPlan(string $path): void
    {
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
