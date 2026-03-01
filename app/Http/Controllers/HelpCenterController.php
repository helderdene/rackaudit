<?php

namespace App\Http\Controllers;

use App\Enums\HelpArticleType;
use App\Enums\HelpInteractionType;
use App\Models\HelpArticle;
use App\Models\UserHelpInteraction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller for the public-facing Help Center page.
 *
 * Provides the Help Center index and article detail views with:
 * - Searchable article listing
 * - Category-based filtering
 * - Article detail view with related articles
 * - Most viewed articles based on user interactions
 */
class HelpCenterController extends Controller
{
    /**
     * Maximum length for article excerpts.
     */
    private const EXCERPT_LENGTH = 150;

    /**
     * Display the Help Center index page.
     *
     * Shows all active articles grouped by category with search and filtering.
     */
    public function index(Request $request): InertiaResponse
    {
        $query = HelpArticle::query()
            ->active()
            ->where('article_type', HelpArticleType::Article);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('content', 'LIKE', "%{$search}%");
            });
        }

        // Category filter
        if ($request->filled('category')) {
            $query->byCategory($request->input('category'));
        }

        // Order by category, then sort_order, then title
        $query->orderBy('category')->orderBy('sort_order')->orderBy('title');

        $articles = $query->get()->map(fn (HelpArticle $article) => [
            'id' => $article->id,
            'slug' => $article->slug,
            'title' => $article->title,
            'content' => $article->content,
            'excerpt' => $this->generateExcerpt($article->content),
            'category' => $article->category,
            'context_key' => $article->context_key,
            'sort_order' => $article->sort_order,
            'is_new' => $article->created_at->isAfter(now()->subDays(7)),
            'created_at' => $article->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $article->updated_at->format('Y-m-d H:i:s'),
        ]);

        // Get unique categories
        $categories = HelpArticle::query()
            ->active()
            ->where('article_type', HelpArticleType::Article)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();

        // Get most viewed articles (top 5)
        $mostViewed = $this->getMostViewedArticles(5);

        return Inertia::render('Help/Index', [
            'articles' => $articles,
            'categories' => $categories,
            'mostViewed' => $mostViewed,
            'filters' => [
                'search' => $request->input('search', ''),
                'category' => $request->input('category', ''),
            ],
        ]);
    }

    /**
     * Display a single help article.
     */
    public function show(HelpArticle $helpArticle): InertiaResponse
    {
        // Ensure article is active
        if (! $helpArticle->is_active) {
            abort(404, 'Help article not found.');
        }

        // Get related articles (same category, excluding current)
        $relatedArticles = HelpArticle::query()
            ->active()
            ->where('article_type', HelpArticleType::Article)
            ->where('id', '!=', $helpArticle->id)
            ->when($helpArticle->category, fn ($q) => $q->byCategory($helpArticle->category))
            ->orderBy('sort_order')
            ->limit(5)
            ->get()
            ->map(fn (HelpArticle $article) => [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $article->title,
                'excerpt' => $this->generateExcerpt($article->content),
                'category' => $article->category,
            ]);

        // Get article view count
        $viewCount = UserHelpInteraction::query()
            ->where('help_article_id', $helpArticle->id)
            ->where('interaction_type', HelpInteractionType::Viewed)
            ->count();

        return Inertia::render('Help/Show', [
            'article' => [
                'id' => $helpArticle->id,
                'slug' => $helpArticle->slug,
                'title' => $helpArticle->title,
                'content' => $helpArticle->content,
                'category' => $helpArticle->category,
                'context_key' => $helpArticle->context_key,
                'view_count' => $viewCount,
                'is_new' => $helpArticle->created_at->isAfter(now()->subDays(7)),
                'created_at' => $helpArticle->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $helpArticle->updated_at->format('Y-m-d H:i:s'),
            ],
            'relatedArticles' => $relatedArticles,
        ]);
    }

    /**
     * Generate a plain text excerpt from markdown content.
     */
    protected function generateExcerpt(?string $content): string
    {
        if (empty($content)) {
            return '';
        }

        // Remove markdown formatting
        $plainText = $content;

        // Remove headers
        $plainText = preg_replace('/^#{1,6}\s+/m', '', $plainText);

        // Remove bold/italic markers
        $plainText = preg_replace('/[*_]{1,2}([^*_]+)[*_]{1,2}/', '$1', $plainText);

        // Remove links but keep text
        $plainText = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $plainText);

        // Remove list markers
        $plainText = preg_replace('/^[-*+]\s+/m', '', $plainText);
        $plainText = preg_replace('/^\d+\.\s+/m', '', $plainText);

        // Remove code blocks
        $plainText = preg_replace('/```[\s\S]*?```/', '', $plainText);
        $plainText = preg_replace('/`[^`]+`/', '', $plainText);

        // Collapse whitespace
        $plainText = preg_replace('/\s+/', ' ', $plainText);

        // Trim and limit length
        return Str::limit(trim($plainText), self::EXCERPT_LENGTH);
    }

    /**
     * Get the most viewed articles based on user interactions.
     *
     * Uses a subquery to count views and filter articles with views > 0.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getMostViewedArticles(int $limit): array
    {
        // Get article IDs with view counts using a separate query
        $viewCounts = UserHelpInteraction::query()
            ->select('help_article_id')
            ->selectRaw('COUNT(*) as view_count')
            ->where('interaction_type', HelpInteractionType::Viewed)
            ->whereNotNull('help_article_id')
            ->groupBy('help_article_id')
            ->orderByDesc('view_count')
            ->limit($limit)
            ->get()
            ->keyBy('help_article_id');

        if ($viewCounts->isEmpty()) {
            return [];
        }

        // Fetch the articles
        return HelpArticle::query()
            ->active()
            ->where('article_type', HelpArticleType::Article)
            ->whereIn('id', $viewCounts->keys())
            ->get()
            ->map(fn (HelpArticle $article) => [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $article->title,
                'excerpt' => $this->generateExcerpt($article->content),
                'category' => $article->category,
                'view_count' => $viewCounts[$article->id]->view_count ?? 0,
            ])
            ->sortByDesc('view_count')
            ->values()
            ->toArray();
    }
}
