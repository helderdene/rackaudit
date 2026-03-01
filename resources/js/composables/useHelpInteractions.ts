import {
    completedTours as fetchCompletedTours,
    dismissed as fetchDismissed,
    store as storeInteraction,
} from '@/actions/App/Http/Controllers/Api/Help/UserHelpInteractionController';
import { readonly, ref } from 'vue';

/**
 * Interaction type enum matching backend
 */
export type InteractionType = 'viewed' | 'dismissed' | 'completed_tour';

/**
 * Cache for user interactions to avoid repeated API calls
 */
const dismissedArticleIds = ref<Set<number>>(new Set());
const completedTourSlugs = ref<Set<string>>(new Set());
const hasFetchedDismissed = ref(false);
const hasFetchedCompleted = ref(false);

/**
 * Composable for tracking user help interactions
 */
export function useHelpInteractions() {
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    /**
     * Fetch dismissed article IDs for the current user
     */
    async function fetchDismissedArticles(): Promise<void> {
        if (hasFetchedDismissed.value) return;

        isLoading.value = true;
        error.value = null;

        try {
            const response = await fetch(fetchDismissed.url(), {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (response.ok) {
                const data = await response.json();
                dismissedArticleIds.value = new Set(data.data || []);
                hasFetchedDismissed.value = true;
            }
        } catch (err) {
            console.error('Failed to fetch dismissed articles:', err);
            error.value = 'Failed to load dismissed articles';
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Fetch completed tour slugs for the current user
     */
    async function fetchCompletedTourSlugs(): Promise<void> {
        if (hasFetchedCompleted.value) return;

        isLoading.value = true;
        error.value = null;

        try {
            const response = await fetch(fetchCompletedTours.url(), {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (response.ok) {
                const data = await response.json();
                completedTourSlugs.value = new Set(data.data || []);
                hasFetchedCompleted.value = true;
            }
        } catch (err) {
            console.error('Failed to fetch completed tours:', err);
            error.value = 'Failed to load completed tours';
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Record a user interaction
     */
    async function recordInteraction(
        type: InteractionType,
        articleId?: number,
        tourId?: number,
    ): Promise<boolean> {
        error.value = null;

        const body: Record<string, unknown> = {
            interaction_type: type,
        };

        if (articleId !== undefined) {
            body.help_article_id = articleId;
        }

        if (tourId !== undefined) {
            body.help_tour_id = tourId;
        }

        try {
            const response = await fetch(storeInteraction.url(), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });

            if (response.ok) {
                // Update local cache
                if (type === 'dismissed' && articleId !== undefined) {
                    dismissedArticleIds.value.add(articleId);
                } else if (type === 'completed_tour' && tourId !== undefined) {
                    // We would need the slug here, but since we recorded by ID,
                    // we'll refetch completed tours on next check
                    hasFetchedCompleted.value = false;
                }
                return true;
            } else {
                error.value = 'Failed to record interaction';
                return false;
            }
        } catch (err) {
            console.error('Failed to record interaction:', err);
            error.value = 'Failed to record interaction';
            return false;
        }
    }

    /**
     * Record viewing an article
     */
    async function recordView(articleId: number): Promise<boolean> {
        return recordInteraction('viewed', articleId);
    }

    /**
     * Record dismissing an article (tooltip)
     */
    async function recordDismissal(articleId: number): Promise<boolean> {
        return recordInteraction('dismissed', articleId);
    }

    /**
     * Record completing a tour
     */
    async function recordTourCompletion(tourId: number): Promise<boolean> {
        return recordInteraction('completed_tour', undefined, tourId);
    }

    /**
     * Check if an article has been dismissed
     */
    function isDismissed(articleId: number): boolean {
        return dismissedArticleIds.value.has(articleId);
    }

    /**
     * Check if a tour has been completed
     */
    function isTourCompleted(tourSlug: string): boolean {
        return completedTourSlugs.value.has(tourSlug);
    }

    /**
     * Get XSRF token from cookies
     */
    function getXsrfToken(): string {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        if (match) {
            return decodeURIComponent(match[1]);
        }
        return '';
    }

    /**
     * Initialize by fetching user interactions
     */
    async function initialize(): Promise<void> {
        await Promise.all([
            fetchDismissedArticles(),
            fetchCompletedTourSlugs(),
        ]);
    }

    /**
     * Clear cached data (e.g., on logout)
     */
    function clearCache(): void {
        dismissedArticleIds.value.clear();
        completedTourSlugs.value.clear();
        hasFetchedDismissed.value = false;
        hasFetchedCompleted.value = false;
    }

    return {
        // State
        dismissedArticleIds: readonly(dismissedArticleIds),
        completedTourSlugs: readonly(completedTourSlugs),
        isLoading: readonly(isLoading),
        error: readonly(error),

        // Actions
        initialize,
        fetchDismissedArticles,
        fetchCompletedTourSlugs,
        recordView,
        recordDismissal,
        recordTourCompletion,
        isDismissed,
        isTourCompleted,
        clearCache,
    };
}
