<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Search, ArrowLeft, Play, X, BookOpen, ChevronRight } from 'lucide-vue-next';
import { cn, debounce } from '@/lib/utils';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetDescription,
} from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import MarkdownRenderer from './MarkdownRenderer.vue';
import { useHelp, type HelpArticle, type HelpTour } from '@/composables/useHelp';
import { useHelpInteractions } from '@/composables/useHelpInteractions';

interface Props {
    /** Current context key for filtering articles */
    contextKey?: string;
    /** Whether the sidebar is open */
    modelValue?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    contextKey: '',
    modelValue: false,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
    (e: 'replayTour', tour: HelpTour): void;
}>();

// Help composables
const {
    sidebarArticles,
    selectedArticle,
    isLoading,
    error,
    articlesByCategory,
    categories,
    searchQuery,
    searchResults,
    isSearching,
    fetchArticlesByContext,
    fetchAllArticles,
    search,
    selectArticle,
    backToList,
    fetchTour,
} = useHelp();

const { isTourCompleted } = useHelpInteractions();

// Local state
const isOpen = computed({
    get: () => props.modelValue,
    set: (value: boolean) => emit('update:modelValue', value),
});

const localSearchQuery = ref('');
const activeCategory = ref<string>('');
const contextTour = ref<HelpTour | null>(null);

/**
 * Debounced search function
 */
const debouncedSearch = debounce((query: unknown) => {
    search(query as string);
}, 300);

/**
 * Handle search input
 */
function handleSearchInput(event: Event) {
    const target = event.target as HTMLInputElement;
    localSearchQuery.value = target.value;
    debouncedSearch(target.value);
}

/**
 * Clear search
 */
function clearSearch() {
    localSearchQuery.value = '';
    search('');
}

/**
 * Handle article click
 */
function handleArticleClick(article: HelpArticle) {
    selectArticle(article);
}

/**
 * Handle back button click
 */
function handleBack() {
    backToList();
}

/**
 * Handle replay tour
 */
function handleReplayTour() {
    if (contextTour.value) {
        emit('replayTour', contextTour.value);
        isOpen.value = false;
    }
}

/**
 * Handle sidebar open/close
 */
function handleOpenChange(open: boolean) {
    isOpen.value = open;
    if (!open) {
        // Reset state on close
        backToList();
        clearSearch();
    }
}

/**
 * Load content when sidebar opens or context changes
 */
watch(
    () => [isOpen.value, props.contextKey],
    async ([open, context]) => {
        if (open) {
            if (context) {
                await fetchArticlesByContext(context as string);
                // Check if there's a tour for this context
                contextTour.value = await fetchTour(context as string);
            } else {
                await fetchAllArticles();
                contextTour.value = null;
            }

            // Set active category to first one
            if (categories.value.length > 0 && !activeCategory.value) {
                activeCategory.value = categories.value[0];
            }
        }
    },
    { immediate: true },
);

/**
 * Update active category when categories change
 */
watch(categories, (newCategories) => {
    if (
        newCategories.length > 0 &&
        !newCategories.includes(activeCategory.value)
    ) {
        activeCategory.value = newCategories[0];
    }
});
</script>

<template>
    <Sheet :open="isOpen" @update:open="handleOpenChange">
        <SheetContent
            side="right"
            :class="cn(
                'w-full sm:max-w-md md:max-w-lg',
                'flex flex-col p-0',
            )"
        >
            <SheetHeader class="border-b px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <!-- Back button when viewing article -->
                        <Button
                            v-if="selectedArticle"
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            @click="handleBack"
                        >
                            <ArrowLeft class="size-4" />
                            <span class="sr-only">Back to articles</span>
                        </Button>

                        <div>
                            <SheetTitle class="flex items-center gap-2">
                                <BookOpen class="size-5" />
                                <span v-if="selectedArticle">{{
                                    selectedArticle.title
                                }}</span>
                                <span v-else>Help Center</span>
                            </SheetTitle>
                            <SheetDescription
                                v-if="!selectedArticle && contextKey"
                                class="text-xs mt-1"
                            >
                                Help for this page
                            </SheetDescription>
                        </div>
                    </div>

                    <!-- Replay tour button -->
                    <Button
                        v-if="!selectedArticle && contextTour"
                        variant="outline"
                        size="sm"
                        class="gap-1.5"
                        @click="handleReplayTour"
                    >
                        <Play class="size-3.5" />
                        <span>{{
                            isTourCompleted(contextTour.slug)
                                ? 'Replay Tour'
                                : 'Start Tour'
                        }}</span>
                    </Button>
                </div>
            </SheetHeader>

            <!-- Article detail view -->
            <div
                v-if="selectedArticle"
                class="flex-1 overflow-y-auto px-6 py-4"
            >
                <!-- Category badge -->
                <Badge
                    v-if="selectedArticle.category"
                    variant="secondary"
                    class="mb-4"
                >
                    {{ selectedArticle.category }}
                </Badge>

                <!-- Article content -->
                <MarkdownRenderer :content="selectedArticle.content" />
            </div>

            <!-- Article list view -->
            <div v-else class="flex flex-1 flex-col overflow-hidden">
                <!-- Search input -->
                <div class="px-6 py-3 border-b">
                    <div class="relative">
                        <Search
                            class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground"
                        />
                        <Input
                            :model-value="localSearchQuery"
                            placeholder="Search help articles..."
                            class="pl-9 pr-9"
                            @input="handleSearchInput"
                        />
                        <button
                            v-if="localSearchQuery"
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            @click="clearSearch"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </div>

                <!-- Loading state -->
                <div
                    v-if="isLoading || isSearching"
                    class="flex-1 px-6 py-4 space-y-3"
                >
                    <div class="space-y-2">
                        <Skeleton class="h-10 w-full" />
                        <Skeleton class="h-10 w-full" />
                        <Skeleton class="h-10 w-full" />
                    </div>
                </div>

                <!-- Error state -->
                <div
                    v-else-if="error"
                    class="flex-1 flex items-center justify-center px-6 text-center"
                >
                    <div class="text-muted-foreground">
                        <p>{{ error }}</p>
                        <Button
                            variant="outline"
                            size="sm"
                            class="mt-4"
                            @click="
                                contextKey
                                    ? fetchArticlesByContext(contextKey)
                                    : fetchAllArticles()
                            "
                        >
                            Try again
                        </Button>
                    </div>
                </div>

                <!-- Empty state -->
                <div
                    v-else-if="
                        sidebarArticles.length === 0 && !localSearchQuery
                    "
                    class="flex-1 flex items-center justify-center px-6 text-center"
                >
                    <div class="text-muted-foreground">
                        <BookOpen class="size-12 mx-auto mb-4 opacity-40" />
                        <p>No help articles available.</p>
                    </div>
                </div>

                <!-- No search results -->
                <div
                    v-else-if="
                        localSearchQuery && searchResults.length === 0
                    "
                    class="flex-1 flex items-center justify-center px-6 text-center"
                >
                    <div class="text-muted-foreground">
                        <Search class="size-12 mx-auto mb-4 opacity-40" />
                        <p>No articles found for "{{ localSearchQuery }}"</p>
                    </div>
                </div>

                <!-- Search results -->
                <div
                    v-else-if="localSearchQuery"
                    class="flex-1 overflow-y-auto"
                >
                    <div class="px-2 py-2">
                        <p
                            class="px-4 py-2 text-xs text-muted-foreground"
                        >
                            {{ searchResults.length }} result{{
                                searchResults.length !== 1 ? 's' : ''
                            }}
                        </p>
                        <button
                            v-for="article in searchResults"
                            :key="article.id"
                            type="button"
                            class="w-full px-4 py-3 flex items-center justify-between gap-3 rounded-md hover:bg-accent text-left transition-colors"
                            @click="handleArticleClick(article)"
                        >
                            <div class="min-w-0">
                                <p class="font-medium text-sm truncate">
                                    {{ article.title }}
                                </p>
                                <p
                                    v-if="article.category"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ article.category }}
                                </p>
                            </div>
                            <ChevronRight class="size-4 text-muted-foreground shrink-0" />
                        </button>
                    </div>
                </div>

                <!-- Category tabs with articles -->
                <div v-else class="flex-1 flex flex-col overflow-hidden">
                    <Tabs
                        v-model="activeCategory"
                        class="flex flex-1 flex-col overflow-hidden"
                    >
                        <!-- Category tabs -->
                        <TabsList
                            v-if="categories.length > 1"
                            class="mx-6 mt-3 mb-0 h-auto flex-wrap justify-start"
                        >
                            <TabsTrigger
                                v-for="category in categories"
                                :key="category"
                                :value="category"
                                class="text-xs"
                            >
                                {{ category }}
                            </TabsTrigger>
                        </TabsList>

                        <!-- Articles list -->
                        <div class="flex-1 overflow-y-auto">
                            <TabsContent
                                v-for="(articles, category) in articlesByCategory"
                                :key="category"
                                :value="category"
                                class="m-0"
                            >
                                <div class="px-2 py-2">
                                    <button
                                        v-for="article in articles"
                                        :key="article.id"
                                        type="button"
                                        class="w-full px-4 py-3 flex items-center justify-between gap-3 rounded-md hover:bg-accent text-left transition-colors"
                                        @click="handleArticleClick(article)"
                                    >
                                        <div class="min-w-0">
                                            <p
                                                class="font-medium text-sm truncate"
                                            >
                                                {{ article.title }}
                                            </p>
                                        </div>
                                        <ChevronRight
                                            class="size-4 text-muted-foreground shrink-0"
                                        />
                                    </button>
                                </div>
                            </TabsContent>
                        </div>
                    </Tabs>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
