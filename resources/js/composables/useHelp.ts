import {
    index as fetchArticles,
    search as searchArticles,
    show as showArticle,
} from '@/actions/App/Http/Controllers/Api/Help/HelpArticleController';
import { forContext as fetchTourForContext } from '@/actions/App/Http/Controllers/Api/Help/HelpTourController';
import { computed, readonly, ref } from 'vue';

/**
 * Help article structure from API
 */
export interface HelpArticle {
    id: number;
    slug: string;
    title: string;
    content: string;
    context_key: string;
    article_type: 'tooltip' | 'tour_step' | 'article';
    category: string | null;
    sort_order: number;
}

/**
 * Help tour step structure from API
 */
export interface HelpTourStep {
    id: number;
    step_order: number;
    target_selector: string;
    position: 'top' | 'right' | 'bottom' | 'left';
    article: HelpArticle;
}

/**
 * Help tour structure from API
 */
export interface HelpTour {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    context_key: string;
    is_active: boolean;
    steps: HelpTourStep[];
}

/**
 * Cache for help content to reduce API calls
 */
const articleCache = new Map<string, HelpArticle[]>();
const tourCache = new Map<string, HelpTour | null>();
const singleArticleCache = new Map<string, HelpArticle>();

/**
 * Composable for managing help content and sidebar state
 */
export function useHelp() {
    // Sidebar state
    const isSidebarOpen = ref(false);
    const currentContextKey = ref<string | null>(null);
    const sidebarArticles = ref<HelpArticle[]>([]);
    const selectedArticle = ref<HelpArticle | null>(null);
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const searchQuery = ref('');
    const searchResults = ref<HelpArticle[]>([]);
    const isSearching = ref(false);

    /**
     * Group articles by category
     */
    const articlesByCategory = computed(() => {
        const grouped: Record<string, HelpArticle[]> = {};
        const articles = searchQuery.value
            ? searchResults.value
            : sidebarArticles.value;

        for (const article of articles) {
            const category = article.category || 'General';
            if (!grouped[category]) {
                grouped[category] = [];
            }
            grouped[category].push(article);
        }

        // Sort articles within each category by sort_order
        for (const category in grouped) {
            grouped[category].sort((a, b) => a.sort_order - b.sort_order);
        }

        return grouped;
    });

    /**
     * Get list of categories
     */
    const categories = computed(() =>
        Object.keys(articlesByCategory.value).sort(),
    );

    /**
     * Open the help sidebar for a specific context
     */
    async function openSidebar(contextKey?: string) {
        isSidebarOpen.value = true;

        if (contextKey && contextKey !== currentContextKey.value) {
            currentContextKey.value = contextKey;
            await fetchArticlesByContext(contextKey);
        } else if (!contextKey && !currentContextKey.value) {
            // Fetch all articles if no context specified
            await fetchAllArticles();
        }
    }

    /**
     * Close the help sidebar
     */
    function closeSidebar() {
        isSidebarOpen.value = false;
        selectedArticle.value = null;
        searchQuery.value = '';
        searchResults.value = [];
    }

    /**
     * Toggle sidebar open/closed
     */
    function toggleSidebar(contextKey?: string) {
        if (isSidebarOpen.value) {
            closeSidebar();
        } else {
            openSidebar(contextKey);
        }
    }

    /**
     * Fetch articles by context key
     */
    async function fetchArticlesByContext(contextKey: string): Promise<void> {
        // Check cache first
        if (articleCache.has(contextKey)) {
            sidebarArticles.value = articleCache.get(contextKey) || [];
            return;
        }

        isLoading.value = true;
        error.value = null;

        try {
            const response = await fetch(
                fetchArticles.url({
                    query: { context_key: contextKey },
                }),
                {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                },
            );

            if (response.ok) {
                const data = await response.json();
                const articles = data.data || [];
                sidebarArticles.value = articles;
                articleCache.set(contextKey, articles);
            } else {
                error.value = 'Failed to load help articles';
            }
        } catch (err) {
            console.error('Failed to fetch help articles:', err);
            error.value = 'Failed to load help articles';
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Fetch all articles (no context filter)
     */
    async function fetchAllArticles(): Promise<void> {
        const cacheKey = '__all__';

        if (articleCache.has(cacheKey)) {
            sidebarArticles.value = articleCache.get(cacheKey) || [];
            return;
        }

        isLoading.value = true;
        error.value = null;

        try {
            const response = await fetch(fetchArticles.url(), {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (response.ok) {
                const data = await response.json();
                const articles = data.data || [];
                sidebarArticles.value = articles;
                articleCache.set(cacheKey, articles);
            } else {
                error.value = 'Failed to load help articles';
            }
        } catch (err) {
            console.error('Failed to fetch help articles:', err);
            error.value = 'Failed to load help articles';
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Fetch a single article by slug
     */
    async function fetchArticle(slug: string): Promise<HelpArticle | null> {
        // Check cache first
        if (singleArticleCache.has(slug)) {
            const cached = singleArticleCache.get(slug);
            selectedArticle.value = cached || null;
            return cached || null;
        }

        isLoading.value = true;
        error.value = null;

        try {
            const response = await fetch(showArticle.url(slug), {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (response.ok) {
                const data = await response.json();
                const article = data.data;
                singleArticleCache.set(slug, article);
                selectedArticle.value = article;
                return article;
            } else {
                error.value = 'Failed to load article';
                return null;
            }
        } catch (err) {
            console.error('Failed to fetch article:', err);
            error.value = 'Failed to load article';
            return null;
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Search articles
     */
    async function search(query: string): Promise<void> {
        searchQuery.value = query;

        if (!query.trim()) {
            searchResults.value = [];
            isSearching.value = false;
            return;
        }

        isSearching.value = true;
        error.value = null;

        try {
            const response = await fetch(
                searchArticles.url({
                    query: { query },
                }),
                {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                },
            );

            if (response.ok) {
                const data = await response.json();
                searchResults.value = data.data || [];
            } else {
                error.value = 'Search failed';
            }
        } catch (err) {
            console.error('Search failed:', err);
            error.value = 'Search failed';
        } finally {
            isSearching.value = false;
        }
    }

    /**
     * Select an article to view
     */
    function selectArticle(article: HelpArticle | null) {
        selectedArticle.value = article;
    }

    /**
     * Go back to article list
     */
    function backToList() {
        selectedArticle.value = null;
    }

    /**
     * Fetch tour for context
     */
    async function fetchTour(contextKey: string): Promise<HelpTour | null> {
        // Check cache first
        if (tourCache.has(contextKey)) {
            return tourCache.get(contextKey) || null;
        }

        try {
            const response = await fetch(fetchTourForContext.url(contextKey), {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (response.ok) {
                const data = await response.json();
                const tour = data.data || null;
                tourCache.set(contextKey, tour);
                return tour;
            } else {
                tourCache.set(contextKey, null);
                return null;
            }
        } catch (err) {
            console.error('Failed to fetch tour:', err);
            tourCache.set(contextKey, null);
            return null;
        }
    }

    /**
     * Clear all caches
     */
    function clearCache() {
        articleCache.clear();
        tourCache.clear();
        singleArticleCache.clear();
    }

    return {
        // State
        isSidebarOpen: readonly(isSidebarOpen),
        currentContextKey: readonly(currentContextKey),
        sidebarArticles: readonly(sidebarArticles),
        selectedArticle: readonly(selectedArticle),
        isLoading: readonly(isLoading),
        error: readonly(error),
        searchQuery,
        searchResults: readonly(searchResults),
        isSearching: readonly(isSearching),

        // Computed
        articlesByCategory,
        categories,

        // Actions
        openSidebar,
        closeSidebar,
        toggleSidebar,
        fetchArticlesByContext,
        fetchAllArticles,
        fetchArticle,
        search,
        selectArticle,
        backToList,
        fetchTour,
        clearCache,
    };
}
