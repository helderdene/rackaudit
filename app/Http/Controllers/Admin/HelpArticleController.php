<?php

namespace App\Http\Controllers\Admin;

use App\Enums\HelpArticleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHelpArticleRequest;
use App\Http\Requests\Admin\UpdateHelpArticleRequest;
use App\Http\Resources\HelpArticleResource;
use App\Models\HelpArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Admin controller for managing help articles.
 *
 * Provides CRUD operations for help articles including filtering by type,
 * category, and active status. All endpoints are restricted to Administrators.
 */
class HelpArticleController extends Controller
{
    /**
     * Display a listing of all help articles with optional filters.
     *
     * Supports filtering by:
     * - article_type: Filter by type (tooltip, tour_step, article)
     * - category: Filter by category name
     * - is_active: Filter by active status (true/false)
     * - search: Search by title
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = HelpArticle::query()
            ->orderBy('sort_order')
            ->orderBy('title');

        // Filter by article type
        if ($request->filled('article_type')) {
            $type = HelpArticleType::tryFrom($request->input('article_type'));
            if ($type) {
                $query->byType($type);
            }
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->byCategory($request->input('category'));
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        // Search by title
        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->input('search').'%');
        }

        // Pagination
        $perPage = min((int) $request->input('per_page', 25), 100);

        return HelpArticleResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created help article.
     */
    public function store(StoreHelpArticleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Set default values
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $article = HelpArticle::create($validated);

        return response()->json([
            'data' => new HelpArticleResource($article),
            'message' => 'Help article created successfully.',
        ], 201);
    }

    /**
     * Display the specified help article for editing.
     */
    public function show(HelpArticle $article): JsonResponse
    {
        return response()->json([
            'data' => new HelpArticleResource($article),
        ]);
    }

    /**
     * Update the specified help article.
     */
    public function update(UpdateHelpArticleRequest $request, HelpArticle $article): JsonResponse
    {
        $validated = $request->validated();

        $article->update($validated);

        return response()->json([
            'data' => new HelpArticleResource($article),
            'message' => 'Help article updated successfully.',
        ]);
    }

    /**
     * Soft delete the specified help article.
     */
    public function destroy(HelpArticle $article): Response
    {
        $article->delete();

        return response()->noContent();
    }
}
