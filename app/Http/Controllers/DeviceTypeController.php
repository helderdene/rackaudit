<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceTypeRequest;
use App\Http\Requests\UpdateDeviceTypeRequest;
use App\Models\DeviceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for managing device types.
 *
 * Device types are user-configurable categories for datacenter equipment,
 * allowing organizations to categorize devices according to their needs.
 */
class DeviceTypeController extends Controller
{
    /**
     * Display a listing of device types.
     * Returns Inertia page for web requests or JSON for API requests.
     */
    public function index(): Response|JsonResponse
    {
        Gate::authorize('viewAny', DeviceType::class);

        $deviceTypes = DeviceType::query()
            ->orderBy('name')
            ->get()
            ->map(fn (DeviceType $deviceType) => [
                'id' => $deviceType->id,
                'name' => $deviceType->name,
                'description' => $deviceType->description,
                'default_u_size' => $deviceType->default_u_size,
                'created_at' => $deviceType->created_at,
                'updated_at' => $deviceType->updated_at,
            ]);

        // Return JSON for API requests (Accept: application/json)
        if (request()->wantsJson()) {
            return response()->json(['data' => $deviceTypes]);
        }

        // Return Inertia page for web requests
        return Inertia::render('DeviceTypes/Index', [
            'deviceTypes' => $deviceTypes,
            'canCreate' => Gate::allows('create', DeviceType::class),
        ]);
    }

    /**
     * Show the form for creating a new device type.
     */
    public function create(): Response
    {
        Gate::authorize('create', DeviceType::class);

        return Inertia::render('DeviceTypes/Create');
    }

    /**
     * Store a newly created device type.
     */
    public function store(StoreDeviceTypeRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $deviceType = DeviceType::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'default_u_size' => $validated['default_u_size'] ?? 1,
        ]);

        // Return JSON for API requests
        if ($request->wantsJson()) {
            return response()->json([
                'data' => [
                    'id' => $deviceType->id,
                    'name' => $deviceType->name,
                    'description' => $deviceType->description,
                    'default_u_size' => $deviceType->default_u_size,
                    'created_at' => $deviceType->created_at,
                    'updated_at' => $deviceType->updated_at,
                ],
                'message' => 'Device type created successfully.',
            ], 201);
        }

        // Redirect for web requests
        return redirect()->route('device-types.index')
            ->with('success', 'Device type created successfully.');
    }

    /**
     * Show the form for editing the specified device type.
     */
    public function edit(DeviceType $deviceType): Response
    {
        Gate::authorize('update', $deviceType);

        return Inertia::render('DeviceTypes/Edit', [
            'deviceType' => [
                'id' => $deviceType->id,
                'name' => $deviceType->name,
                'description' => $deviceType->description,
                'default_u_size' => $deviceType->default_u_size,
            ],
        ]);
    }

    /**
     * Update the specified device type.
     */
    public function update(UpdateDeviceTypeRequest $request, DeviceType $deviceType): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $deviceType->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'default_u_size' => $validated['default_u_size'] ?? $deviceType->default_u_size,
        ]);

        // Return JSON for API requests
        if ($request->wantsJson()) {
            return response()->json([
                'data' => [
                    'id' => $deviceType->id,
                    'name' => $deviceType->name,
                    'description' => $deviceType->description,
                    'default_u_size' => $deviceType->default_u_size,
                    'created_at' => $deviceType->created_at,
                    'updated_at' => $deviceType->updated_at,
                ],
                'message' => 'Device type updated successfully.',
            ]);
        }

        // Redirect for web requests
        return redirect()->route('device-types.index')
            ->with('success', 'Device type updated successfully.');
    }

    /**
     * Remove the specified device type (soft delete).
     */
    public function destroy(DeviceType $deviceType): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $deviceType);

        $deviceType->delete();

        // Return JSON for API requests
        if (request()->wantsJson()) {
            return response()->json([
                'message' => 'Device type deleted successfully.',
            ]);
        }

        // Redirect for web requests
        return redirect()->route('device-types.index')
            ->with('success', 'Device type deleted successfully.');
    }
}
