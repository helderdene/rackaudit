<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHelpTourRequest;
use App\Http\Requests\Admin\UpdateHelpTourRequest;
use App\Http\Resources\HelpTourResource;
use App\Models\HelpTour;
use App\Models\HelpTourStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Admin controller for managing help tours.
 *
 * Provides CRUD operations for help tours including step management.
 * All endpoints are restricted to Administrators.
 */
class HelpTourController extends Controller
{
    /**
     * Display a listing of all help tours.
     *
     * Supports filtering by:
     * - is_active: Filter by active status (true/false)
     * - search: Search by name
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = HelpTour::query()
            ->with(['steps.article'])
            ->withCount('steps')
            ->orderBy('name');

        // Filter by active status
        if ($request->has('is_active')) {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        // Search by name
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        // Pagination
        $perPage = min((int) $request->input('per_page', 25), 100);

        return HelpTourResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created help tour.
     */
    public function store(StoreHelpTourRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $steps = $validated['steps'] ?? [];
        unset($validated['steps']);

        // Set default values
        $validated['is_active'] = $validated['is_active'] ?? true;

        $tour = DB::transaction(function () use ($validated, $steps) {
            $tour = HelpTour::create($validated);

            // Create steps if provided
            foreach ($steps as $stepData) {
                $stepData['help_tour_id'] = $tour->id;
                HelpTourStep::create($stepData);
            }

            return $tour;
        });

        $tour->load(['steps.article']);

        return response()->json([
            'data' => new HelpTourResource($tour),
            'message' => 'Help tour created successfully.',
        ], 201);
    }

    /**
     * Display the specified help tour with steps for editing.
     */
    public function show(HelpTour $tour): JsonResponse
    {
        $tour->load(['steps.article']);

        return response()->json([
            'data' => new HelpTourResource($tour),
        ]);
    }

    /**
     * Update the specified help tour and its steps.
     * When steps are provided, all existing steps are replaced.
     */
    public function update(UpdateHelpTourRequest $request, HelpTour $tour): JsonResponse
    {
        $validated = $request->validated();
        $steps = $validated['steps'] ?? null;
        unset($validated['steps']);

        DB::transaction(function () use ($tour, $validated, $steps) {
            // Update tour fields
            $tour->update($validated);

            // If steps are provided, replace all existing steps
            if ($steps !== null) {
                // Delete existing steps
                $tour->steps()->delete();

                // Create new steps
                foreach ($steps as $stepData) {
                    $stepData['help_tour_id'] = $tour->id;
                    HelpTourStep::create($stepData);
                }
            }
        });

        $tour->refresh();
        $tour->load(['steps.article']);

        return response()->json([
            'data' => new HelpTourResource($tour),
            'message' => 'Help tour updated successfully.',
        ]);
    }

    /**
     * Soft delete the specified help tour.
     * Associated steps are not soft deleted as they don't have SoftDeletes.
     */
    public function destroy(HelpTour $tour): Response
    {
        DB::transaction(function () use ($tour) {
            // Delete steps first
            $tour->steps()->delete();
            // Then soft delete the tour
            $tour->delete();
        });

        return response()->noContent();
    }
}
