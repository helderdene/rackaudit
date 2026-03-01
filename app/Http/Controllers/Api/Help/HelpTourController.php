<?php

namespace App\Http\Controllers\Api\Help;

use App\Http\Controllers\Controller;
use App\Http\Resources\HelpTourResource;
use App\Models\HelpTour;
use Illuminate\Http\JsonResponse;

/**
 * API controller for public help tour endpoints.
 *
 * Provides endpoints for fetching tour data with steps and articles.
 * All endpoints return only active tours.
 */
class HelpTourController extends Controller
{
    /**
     * Display a tour by slug with eager-loaded steps and articles.
     *
     * Returns 404 if tour is not found or not active.
     */
    public function show(HelpTour $helpTour): HelpTourResource
    {
        // Ensure tour is active
        if (! $helpTour->is_active) {
            abort(404, 'Help tour not found.');
        }

        // Eager load steps with their articles
        $helpTour->load(['steps.article']);

        return new HelpTourResource($helpTour);
    }

    /**
     * Get the active tour for a specific context key.
     *
     * Returns the first active tour matching the context_key.
     * If no tour is found, returns a 404 response.
     */
    public function forContext(string $contextKey): HelpTourResource|JsonResponse
    {
        $tour = HelpTour::query()
            ->active()
            ->byContextKey($contextKey)
            ->with(['steps.article'])
            ->first();

        if (! $tour) {
            return response()->json([
                'message' => 'No active tour found for the specified context.',
                'context_key' => $contextKey,
            ], 404);
        }

        return new HelpTourResource($tour);
    }
}
