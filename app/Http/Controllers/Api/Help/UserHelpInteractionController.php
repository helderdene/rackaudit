<?php

namespace App\Http\Controllers\Api\Help;

use App\Enums\HelpInteractionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserHelpInteractionRequest;
use App\Models\HelpTour;
use App\Models\UserHelpInteraction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for user help interaction endpoints.
 *
 * Provides endpoints for tracking user interactions with help content,
 * including viewing, dismissing articles, and completing tours.
 */
class UserHelpInteractionController extends Controller
{
    /**
     * Get the current user's dismissed article IDs.
     *
     * Returns an array of article IDs that the user has dismissed.
     * This is used client-side to hide "Don't show again" tooltips.
     */
    public function dismissed(Request $request): JsonResponse
    {
        $dismissedIds = $request->user()->dismissedHelpArticles()->values()->all();

        return response()->json([
            'data' => $dismissedIds,
        ]);
    }

    /**
     * Get the current user's completed tour slugs.
     *
     * Returns an array of tour slugs that the user has completed.
     * This is used to determine whether to auto-trigger tours.
     */
    public function completedTours(Request $request): JsonResponse
    {
        $completedTourIds = $request->user()->completedTours();

        // Get tour slugs from the IDs
        $tourSlugs = HelpTour::query()
            ->whereIn('id', $completedTourIds)
            ->pluck('slug')
            ->values()
            ->all();

        return response()->json([
            'data' => $tourSlugs,
        ]);
    }

    /**
     * Record a user interaction with help content.
     *
     * Accepts interaction_type (viewed, dismissed, completed_tour)
     * and either help_article_id or help_tour_id based on type.
     */
    public function store(StoreUserHelpInteractionRequest $request): JsonResponse
    {
        $interactionType = HelpInteractionType::from($request->input('interaction_type'));

        $interaction = UserHelpInteraction::create([
            'user_id' => $request->user()->id,
            'help_article_id' => $request->input('help_article_id'),
            'help_tour_id' => $request->input('help_tour_id'),
            'interaction_type' => $interactionType,
        ]);

        return response()->json([
            'message' => 'Interaction recorded successfully.',
            'data' => [
                'id' => $interaction->id,
                'interaction_type' => $interactionType->value,
            ],
        ], 201);
    }
}
