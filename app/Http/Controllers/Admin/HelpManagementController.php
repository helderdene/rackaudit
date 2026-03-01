<?php

namespace App\Http\Controllers\Admin;

use App\Enums\HelpArticleType;
use App\Enums\HelpTourStepPosition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHelpArticleRequest;
use App\Http\Requests\Admin\StoreHelpTourRequest;
use App\Http\Requests\Admin\UpdateHelpArticleRequest;
use App\Http\Requests\Admin\UpdateHelpTourRequest;
use App\Models\HelpArticle;
use App\Models\HelpTour;
use App\Models\HelpTourStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Admin controller for managing help content through Inertia pages.
 *
 * Provides CRUD operations for help articles and tours with
 * a rich admin interface including markdown editing and preview.
 */
class HelpManagementController extends Controller
{
    /**
     * Known context keys from existing pages for the picker.
     *
     * @var array<string, string>
     */
    private const KNOWN_CONTEXT_KEYS = [
        'audits.execute.connection' => 'Connection Audit Execution',
        'audits.execute.inventory' => 'Inventory Audit Execution',
        'audits.index' => 'Audits List',
        'audits.create' => 'Create Audit',
        'audits.dashboard' => 'Audit Dashboard',
        'implementations.files' => 'Implementation File Management',
        'racks.elevation' => 'Rack Elevation View',
        'connections.create' => 'Connection Creation',
        'connections.index' => 'Connections List',
        'connections.diagram' => 'Connection Diagram',
        'devices.index' => 'Devices List',
        'devices.create' => 'Create Device',
        'datacenters.index' => 'Datacenters List',
        'reports.index' => 'Reports List',
        'dashboard' => 'Dashboard',
    ];

    /**
     * Display the help management index with tabbed view.
     */
    public function index(Request $request): InertiaResponse
    {
        $articlesQuery = HelpArticle::query()
            ->orderBy('sort_order')
            ->orderBy('title');

        // Filter by article type
        if ($request->filled('article_type')) {
            $type = HelpArticleType::tryFrom($request->input('article_type'));
            if ($type) {
                $articlesQuery->byType($type);
            }
        }

        // Filter by category
        if ($request->filled('category')) {
            $articlesQuery->byCategory($request->input('category'));
        }

        // Filter by active status
        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $articlesQuery->where('is_active', $isActive);
            }
        }

        // Search by title
        if ($request->filled('search')) {
            $articlesQuery->where('title', 'like', '%' . $request->input('search') . '%');
        }

        $articles = $articlesQuery->paginate(25);

        $toursQuery = HelpTour::query()
            ->with(['steps.article'])
            ->withCount('steps')
            ->orderBy('name');

        // Filter tours by active status
        if ($request->has('tour_is_active') && $request->input('tour_is_active') !== '') {
            $isActive = filter_var($request->input('tour_is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $toursQuery->where('is_active', $isActive);
            }
        }

        // Search tours by name
        if ($request->filled('tour_search')) {
            $toursQuery->where('name', 'like', '%' . $request->input('tour_search') . '%');
        }

        $tours = $toursQuery->paginate(25);

        // Get unique categories from existing articles
        $categories = HelpArticle::query()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values()
            ->all();

        return Inertia::render('Admin/Help/Index', [
            'articles' => $articles,
            'tours' => $tours,
            'categories' => $categories,
            'articleTypes' => $this->getArticleTypes(),
            'filters' => [
                'search' => $request->input('search', ''),
                'article_type' => $request->input('article_type', ''),
                'category' => $request->input('category', ''),
                'is_active' => $request->input('is_active', ''),
                'tour_search' => $request->input('tour_search', ''),
                'tour_is_active' => $request->input('tour_is_active', ''),
            ],
            'activeTab' => $request->input('tab', 'articles'),
        ]);
    }

    /**
     * Show the form for creating a new article.
     */
    public function createArticle(): InertiaResponse
    {
        $categories = HelpArticle::query()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values()
            ->all();

        return Inertia::render('Admin/Help/ArticleForm', [
            'mode' => 'create',
            'articleTypes' => $this->getArticleTypes(),
            'categories' => $categories,
            'contextKeys' => self::KNOWN_CONTEXT_KEYS,
        ]);
    }

    /**
     * Store a newly created article.
     */
    public function storeArticle(StoreHelpArticleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        HelpArticle::create($validated);

        return redirect()->route('admin.help.index')
            ->with('success', 'Help article created successfully.');
    }

    /**
     * Show the form for editing an article.
     */
    public function editArticle(int $articleId): InertiaResponse
    {
        $article = HelpArticle::findOrFail($articleId);

        $categories = HelpArticle::query()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values()
            ->all();

        return Inertia::render('Admin/Help/ArticleForm', [
            'mode' => 'edit',
            'article' => [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $article->title,
                'content' => $article->content,
                'context_key' => $article->context_key,
                'article_type' => $article->article_type?->value,
                'category' => $article->category,
                'sort_order' => $article->sort_order,
                'is_active' => $article->is_active,
            ],
            'articleTypes' => $this->getArticleTypes(),
            'categories' => $categories,
            'contextKeys' => self::KNOWN_CONTEXT_KEYS,
        ]);
    }

    /**
     * Update the specified article.
     */
    public function updateArticle(UpdateHelpArticleRequest $request, int $articleId): RedirectResponse
    {
        $article = HelpArticle::findOrFail($articleId);
        $validated = $request->validated();

        $article->update($validated);

        return redirect()->route('admin.help.index')
            ->with('success', 'Help article updated successfully.');
    }

    /**
     * Delete the specified article.
     */
    public function destroyArticle(int $articleId): RedirectResponse
    {
        $article = HelpArticle::findOrFail($articleId);

        // Check if article is used in any tour steps
        $usedInSteps = HelpTourStep::where('help_article_id', $article->id)->exists();
        if ($usedInSteps) {
            return redirect()->back()
                ->with('error', 'Cannot delete article that is used in tour steps.');
        }

        $article->delete();

        return redirect()->route('admin.help.index')
            ->with('success', 'Help article deleted successfully.');
    }

    /**
     * Show the form for creating a new tour.
     */
    public function createTour(): InertiaResponse
    {
        // Get tour_step articles for selection
        $availableArticles = HelpArticle::query()
            ->where('is_active', true)
            ->orderBy('title')
            ->get()
            ->map(fn (HelpArticle $article) => [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $article->title,
                'article_type' => $article->article_type?->value,
            ]);

        return Inertia::render('Admin/Help/TourForm', [
            'mode' => 'create',
            'availableArticles' => $availableArticles,
            'stepPositions' => $this->getStepPositions(),
            'contextKeys' => self::KNOWN_CONTEXT_KEYS,
        ]);
    }

    /**
     * Store a newly created tour.
     */
    public function storeTour(StoreHelpTourRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $steps = $validated['steps'] ?? [];
        unset($validated['steps']);

        $validated['is_active'] = $validated['is_active'] ?? true;

        DB::transaction(function () use ($validated, $steps) {
            $tour = HelpTour::create($validated);

            foreach ($steps as $stepData) {
                $stepData['help_tour_id'] = $tour->id;
                HelpTourStep::create($stepData);
            }
        });

        return redirect()->route('admin.help.index', ['tab' => 'tours'])
            ->with('success', 'Help tour created successfully.');
    }

    /**
     * Show the form for editing a tour.
     */
    public function editTour(int $tourId): InertiaResponse
    {
        $tour = HelpTour::with(['steps.article'])->findOrFail($tourId);

        $availableArticles = HelpArticle::query()
            ->where('is_active', true)
            ->orderBy('title')
            ->get()
            ->map(fn (HelpArticle $article) => [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $article->title,
                'article_type' => $article->article_type?->value,
            ]);

        return Inertia::render('Admin/Help/TourForm', [
            'mode' => 'edit',
            'tour' => [
                'id' => $tour->id,
                'slug' => $tour->slug,
                'name' => $tour->name,
                'context_key' => $tour->context_key,
                'description' => $tour->description,
                'is_active' => $tour->is_active,
                'steps' => $tour->steps->map(fn (HelpTourStep $step) => [
                    'id' => $step->id,
                    'help_article_id' => $step->help_article_id,
                    'target_selector' => $step->target_selector,
                    'position' => $step->position?->value,
                    'step_order' => $step->step_order,
                    'article' => $step->article ? [
                        'id' => $step->article->id,
                        'title' => $step->article->title,
                    ] : null,
                ])->all(),
            ],
            'availableArticles' => $availableArticles,
            'stepPositions' => $this->getStepPositions(),
            'contextKeys' => self::KNOWN_CONTEXT_KEYS,
        ]);
    }

    /**
     * Update the specified tour and its steps.
     */
    public function updateTour(UpdateHelpTourRequest $request, int $tourId): RedirectResponse
    {
        $tour = HelpTour::findOrFail($tourId);
        $validated = $request->validated();
        $steps = $validated['steps'] ?? null;
        unset($validated['steps']);

        DB::transaction(function () use ($tour, $validated, $steps) {
            $tour->update($validated);

            if ($steps !== null) {
                $tour->steps()->delete();

                foreach ($steps as $stepData) {
                    $stepData['help_tour_id'] = $tour->id;
                    HelpTourStep::create($stepData);
                }
            }
        });

        return redirect()->route('admin.help.index', ['tab' => 'tours'])
            ->with('success', 'Help tour updated successfully.');
    }

    /**
     * Delete the specified tour.
     */
    public function destroyTour(int $tourId): RedirectResponse
    {
        $tour = HelpTour::findOrFail($tourId);

        DB::transaction(function () use ($tour) {
            $tour->steps()->delete();
            $tour->delete();
        });

        return redirect()->route('admin.help.index', ['tab' => 'tours'])
            ->with('success', 'Help tour deleted successfully.');
    }

    /**
     * Preview an article with tooltip/tour display.
     */
    public function previewArticle(int $articleId): InertiaResponse
    {
        $article = HelpArticle::findOrFail($articleId);

        return Inertia::render('Admin/Help/ArticlePreview', [
            'article' => [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $article->title,
                'content' => $article->content,
                'context_key' => $article->context_key,
                'article_type' => $article->article_type?->value,
                'article_type_label' => $article->article_type?->label(),
                'category' => $article->category,
                'is_active' => $article->is_active,
            ],
        ]);
    }

    /**
     * Preview a tour with step walkthrough.
     */
    public function previewTour(int $tourId): InertiaResponse
    {
        $tour = HelpTour::with(['steps.article'])->findOrFail($tourId);

        return Inertia::render('Admin/Help/TourPreview', [
            'tour' => [
                'id' => $tour->id,
                'slug' => $tour->slug,
                'name' => $tour->name,
                'context_key' => $tour->context_key,
                'description' => $tour->description,
                'is_active' => $tour->is_active,
                'steps' => $tour->steps->map(fn (HelpTourStep $step) => [
                    'id' => $step->id,
                    'step_order' => $step->step_order,
                    'target_selector' => $step->target_selector,
                    'position' => $step->position?->value,
                    'position_label' => $step->position?->label(),
                    'article' => $step->article ? [
                        'id' => $step->article->id,
                        'slug' => $step->article->slug,
                        'title' => $step->article->title,
                        'content' => $step->article->content,
                    ] : null,
                ])->all(),
            ],
        ]);
    }

    /**
     * Get article types for select options.
     *
     * @return array<array{value: string, label: string}>
     */
    private function getArticleTypes(): array
    {
        return array_map(
            fn (HelpArticleType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            HelpArticleType::cases()
        );
    }

    /**
     * Get step positions for select options.
     *
     * @return array<array{value: string, label: string}>
     */
    private function getStepPositions(): array
    {
        return array_map(
            fn (HelpTourStepPosition $position) => [
                'value' => $position->value,
                'label' => $position->label(),
            ],
            HelpTourStepPosition::cases()
        );
    }
}
