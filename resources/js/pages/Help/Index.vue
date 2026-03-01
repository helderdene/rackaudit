<script setup lang="ts">
import { store as storeInteraction } from '@/actions/App/Http/Controllers/Api/Help/UserHelpInteractionController';
import {
    index as helpIndex,
    show as helpShow,
} from '@/actions/App/Http/Controllers/HelpCenterController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import HelpArticleCard from '@/components/HelpCenter/HelpArticleCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { debounce } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { BookOpen, Search, TrendingUp } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * Type definitions for Help Center props
 */
interface HelpArticle {
    id: number;
    slug: string;
    title: string;
    content: string;
    excerpt: string;
    category: string | null;
    context_key: string | null;
    sort_order: number;
    is_new: boolean;
    created_at: string;
    updated_at: string;
}

interface MostViewedArticle {
    id: number;
    slug: string;
    title: string;
    excerpt: string;
    category: string | null;
    view_count: number;
}

interface Filters {
    search: string;
    category: string;
}

interface Props {
    articles: HelpArticle[];
    categories: string[];
    mostViewed: MostViewedArticle[];
    filters: Filters;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Help Center',
        href: helpIndex.url(),
    },
];

// Local filter state
const searchQuery = ref(props.filters.search);
const selectedCategory = ref(props.filters.category || 'all');

// Debounced search handler
const debouncedSearch = debounce(() => {
    applyFilters();
}, 300);

// Watch for search query changes
watch(searchQuery, () => {
    debouncedSearch();
});

// Group articles by category
const articlesByCategory = computed(() => {
    const grouped: Record<string, HelpArticle[]> = {};

    for (const article of props.articles) {
        const category = article.category || 'General';
        if (!grouped[category]) {
            grouped[category] = [];
        }
        grouped[category].push(article);
    }

    return grouped;
});

// Filter articles based on selected category
const filteredArticles = computed(() => {
    if (selectedCategory.value === 'all') {
        return props.articles;
    }
    return props.articles.filter(
        (article) => (article.category || 'General') === selectedCategory.value,
    );
});

// Apply filters
const applyFilters = () => {
    router.get(
        helpIndex.url(),
        {
            search: searchQuery.value || undefined,
            category:
                selectedCategory.value === 'all'
                    ? undefined
                    : selectedCategory.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

// Handle category change
const handleCategoryChange = (category: string) => {
    selectedCategory.value = category;
    applyFilters();
};

// Navigate to article detail
const navigateToArticle = async (article: HelpArticle) => {
    // Record view interaction
    try {
        await fetch(storeInteraction.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                interaction_type: 'viewed',
                help_article_id: article.id,
            }),
        });
    } catch {
        // Ignore errors for view tracking
    }

    router.visit(helpShow.url(article.slug));
};

// Clear search
const clearSearch = () => {
    searchQuery.value = '';
    applyFilters();
};
</script>

<template>
    <Head title="Help Center" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4 md:p-6">
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <HeadingSmall
                    title="Help Center"
                    description="Find answers to your questions and learn how to use the application effectively."
                />
            </div>

            <!-- Search Bar -->
            <div class="relative">
                <Search
                    class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="searchQuery"
                    placeholder="Search help articles..."
                    class="pr-20 pl-10"
                />
                <Button
                    v-if="searchQuery"
                    variant="ghost"
                    size="sm"
                    class="absolute top-1/2 right-2 -translate-y-1/2"
                    @click="clearSearch"
                >
                    Clear
                </Button>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
                <!-- Articles Section (3/4 width on desktop) -->
                <div class="lg:col-span-3">
                    <!-- Category Tabs -->
                    <Tabs :default-value="selectedCategory" class="w-full">
                        <TabsList class="mb-4 flex h-auto flex-wrap gap-1">
                            <TabsTrigger
                                value="all"
                                class="text-sm"
                                @click="handleCategoryChange('all')"
                            >
                                All Articles
                            </TabsTrigger>
                            <TabsTrigger
                                v-for="category in categories"
                                :key="category"
                                :value="category"
                                class="text-sm"
                                @click="handleCategoryChange(category)"
                            >
                                {{ category }}
                            </TabsTrigger>
                        </TabsList>

                        <!-- Articles Grid -->
                        <TabsContent value="all" class="mt-0">
                            <div
                                v-if="filteredArticles.length > 0"
                                class="grid grid-cols-1 gap-4 md:grid-cols-2"
                            >
                                <HelpArticleCard
                                    v-for="article in filteredArticles"
                                    :key="article.id"
                                    :article="article"
                                    @click="navigateToArticle(article)"
                                />
                            </div>
                            <div
                                v-else
                                class="flex flex-col items-center justify-center py-12 text-center"
                            >
                                <BookOpen
                                    class="mb-4 h-12 w-12 text-muted-foreground"
                                />
                                <h3 class="text-lg font-medium">
                                    No articles found
                                </h3>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Try adjusting your search or filter
                                    criteria.
                                </p>
                            </div>
                        </TabsContent>

                        <TabsContent
                            v-for="category in categories"
                            :key="category"
                            :value="category"
                            class="mt-0"
                        >
                            <div
                                v-if="filteredArticles.length > 0"
                                class="grid grid-cols-1 gap-4 md:grid-cols-2"
                            >
                                <HelpArticleCard
                                    v-for="article in filteredArticles"
                                    :key="article.id"
                                    :article="article"
                                    @click="navigateToArticle(article)"
                                />
                            </div>
                            <div
                                v-else
                                class="flex flex-col items-center justify-center py-12 text-center"
                            >
                                <BookOpen
                                    class="mb-4 h-12 w-12 text-muted-foreground"
                                />
                                <h3 class="text-lg font-medium">
                                    No articles in this category
                                </h3>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Check back later for new content.
                                </p>
                            </div>
                        </TabsContent>
                    </Tabs>
                </div>

                <!-- Sidebar (1/4 width on desktop) -->
                <div class="space-y-6">
                    <!-- Most Viewed Articles -->
                    <Card v-if="mostViewed.length > 0">
                        <CardHeader class="pb-3">
                            <CardTitle
                                class="flex items-center gap-2 text-base"
                            >
                                <TrendingUp class="h-4 w-4" />
                                Most Viewed
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2">
                            <button
                                v-for="article in mostViewed"
                                :key="article.id"
                                class="block w-full rounded-md p-2 text-left transition-colors hover:bg-muted"
                                @click="
                                    navigateToArticle(article as HelpArticle)
                                "
                            >
                                <div
                                    class="flex items-start justify-between gap-2"
                                >
                                    <span
                                        class="line-clamp-2 text-sm font-medium"
                                    >
                                        {{ article.title }}
                                    </span>
                                    <Badge
                                        variant="secondary"
                                        class="shrink-0 text-xs"
                                    >
                                        {{ article.view_count }} views
                                    </Badge>
                                </div>
                                <span
                                    v-if="article.category"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{ article.category }}
                                </span>
                            </button>
                        </CardContent>
                    </Card>

                    <!-- Browse by Category (visible on mobile/tablet) -->
                    <Card class="lg:hidden">
                        <CardHeader class="pb-3">
                            <CardTitle class="text-base"
                                >Browse by Category</CardTitle
                            >
                        </CardHeader>
                        <CardContent class="flex flex-wrap gap-2">
                            <Button
                                v-for="category in categories"
                                :key="category"
                                variant="outline"
                                size="sm"
                                @click="handleCategoryChange(category)"
                            >
                                {{ category }}
                                <Badge variant="secondary" class="ml-1">
                                    {{
                                        articlesByCategory[category]?.length ||
                                        0
                                    }}
                                </Badge>
                            </Button>
                        </CardContent>
                    </Card>

                    <!-- Quick Links Card -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="text-base"
                                >Need More Help?</CardTitle
                            >
                        </CardHeader>
                        <CardContent
                            class="space-y-2 text-sm text-muted-foreground"
                        >
                            <p>
                                Can't find what you're looking for? Try using
                                the search bar or browse articles by category.
                            </p>
                            <p>
                                For context-specific help, look for the help
                                button (?) on any page.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
