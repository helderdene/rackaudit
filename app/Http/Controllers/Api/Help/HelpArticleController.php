<?php

namespace App\Http\Controllers\Api\Help;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchHelpArticlesRequest;
use App\Http\Resources\HelpArticleResource;
use App\Http\Resources\HelpSearchResultResource;
use App\Models\HelpArticle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * API controller for public help article endpoints.
 *
 * Provides endpoints for listing, viewing, and searching help articles.
 * All endpoints return only active articles.
 */
class HelpArticleController extends Controller
{
    /**
     * Display a paginated list of active help articles.
     *
     * Supports filtering by:
     * - context_key: Filter articles for a specific page/context
     * - category: Filter by article category
     * - article_type: Filter by type (tooltip, tour_step, article)
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = HelpArticle::query()->active();

        // Filter by context_key
        if ($request->filled('context_key')) {
            $query->byContextKey($request->input('context_key'));
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->byCategory($request->input('category'));
        }

        // Filter by article_type
        if ($request->filled('article_type')) {
            $query->where('article_type', $request->input('article_type'));
        }

        // Order by sort_order, then by title
        $query->orderBy('sort_order')->orderBy('title');

        // Pagination
        $perPage = min((int) $request->input('per_page', 25), 100);

        return HelpArticleResource::collection($query->paginate($perPage));
    }

    /**
     * Display a single help article by slug.
     *
     * Returns 404 if article is not found or not active.
     */
    public function show(HelpArticle $helpArticle): HelpArticleResource
    {
        // Ensure article is active
        if (! $helpArticle->is_active) {
            abort(404, 'Help article not found.');
        }

        return new HelpArticleResource($helpArticle);
    }

    /**
     * Search help articles by title and content.
     *
     * Performs case-insensitive search across title and content fields.
     * Returns results with highlighted matching text snippets.
     * Results are ordered by relevance (title matches first).
     */
    public function search(SearchHelpArticlesRequest $request): AnonymousResourceCollection
    {
        $query = $request->input('query');
        $limit = $request->input('limit', 20);

        $searchQuery = HelpArticle::query()
            ->active()
            ->where(function ($q) use ($query) {
                $q->where('title', 'LIKE', "%{$query}%")
                    ->orWhere('content', 'LIKE', "%{$query}%");
            });

        // Optional category filter
        if ($request->filled('category')) {
            $searchQuery->byCategory($request->input('category'));
        }

        // Order by relevance: title matches first, then content matches
        // Use a CASE statement to prioritize title matches
        $searchQuery->orderByRaw('
            CASE
                WHEN title LIKE ? THEN 0
                ELSE 1
            END
        ', ["%{$query}%"]);

        $results = $searchQuery->limit($limit)->get();

        // Transform results with search query for highlighting
        return HelpSearchResultResource::collection($results);
    }
}
