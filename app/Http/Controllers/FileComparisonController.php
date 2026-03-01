<?php

namespace App\Http\Controllers;

use App\Http\Resources\ComparisonResultResource;
use App\Models\Device;
use App\Models\ImplementationFile;
use App\Models\Rack;
use App\Services\ConnectionComparisonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller for the implementation file comparison Inertia page.
 *
 * Provides a visual comparison view that matches confirmed expected connections
 * from an approved implementation file against documented actual connections.
 */
class FileComparisonController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ConnectionComparisonService $comparisonService
    ) {}

    /**
     * Display the comparison page for an implementation file.
     *
     * Verifies the file is approved with confirmed expected connections
     * before rendering the comparison view.
     */
    public function comparison(ImplementationFile $file): InertiaResponse|JsonResponse
    {
        // Authorize viewing the implementation file
        Gate::authorize('view', $file);

        // Ensure file is approved
        if (! $file->isApproved()) {
            abort(403, 'Only approved implementation files can be compared.');
        }

        // Check if file has confirmed expected connections
        $hasConfirmedConnections = $file->expectedConnections()
            ->confirmed()
            ->whereNotNull('source_port_id')
            ->whereNotNull('dest_port_id')
            ->exists();

        if (! $hasConfirmedConnections) {
            abort(403, 'This file has no confirmed expected connections to compare.');
        }

        // Load the file with datacenter relationship
        $file->load(['datacenter', 'uploader']);

        // Get comparison results
        $results = $this->comparisonService->compareForImplementationFile($file);

        // Get statistics
        $statistics = $results->getStatistics();

        // Get filter options (devices and racks involved in comparisons)
        $filterOptions = $this->getFilterOptions($file);

        // Get all items for initial render (no pagination for initial load)
        $comparisons = $results->all();

        return Inertia::render('ImplementationFiles/Comparison', [
            'implementationFile' => [
                'id' => $file->id,
                'original_name' => $file->original_name,
                'description' => $file->description,
                'datacenter_id' => $file->datacenter_id,
                'datacenter_name' => $file->datacenter?->name,
                'approval_status' => $file->approval_status,
                'approved_at' => $file->approved_at?->toISOString(),
            ],
            'initialComparisons' => ComparisonResultResource::collection($comparisons)->resolve(),
            'filterOptions' => $filterOptions,
            'statistics' => $statistics,
        ]);
    }

    /**
     * Get filter options for devices and racks involved in the comparison.
     *
     * @return array{devices: array, racks: array}
     */
    protected function getFilterOptions(ImplementationFile $file): array
    {
        // Get all devices involved in expected connections for this file
        $expectedConnections = $file->expectedConnections()
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
}
