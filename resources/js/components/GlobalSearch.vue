<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { Search, X, Building2, Server, HardDrive, Plug, Cable, ChevronRight } from 'lucide-vue-next';
import { debounce, cn } from '@/lib/utils';
import { Spinner } from '@/components/ui/spinner';
import { quickSearch } from '@/actions/App/Http/Controllers/SearchController';
import { index as searchIndex } from '@/actions/App/Http/Controllers/SearchController';

/**
 * Search result item structure from the API
 */
interface SearchResultItem {
    id: number;
    name: string;
    entity_type: string;
    breadcrumb: string;
    matched_fields: string[];
    datacenter_id?: number;
    datacenter_name?: string;
    [key: string]: unknown;
}

/**
 * Entity type results structure
 */
interface EntityResults {
    items: SearchResultItem[];
    total: number;
}

/**
 * Search results grouped by entity type
 */
interface SearchResults {
    datacenters: EntityResults;
    racks: EntityResults;
    devices: EntityResults;
    ports: EntityResults;
    connections: EntityResults;
}

/**
 * Entity type configuration for display
 */
interface EntityTypeConfig {
    key: keyof SearchResults;
    label: string;
    icon: typeof Building2;
    route: string;
}

const entityTypes: EntityTypeConfig[] = [
    { key: 'datacenters', label: 'Datacenters', icon: Building2, route: '/datacenters' },
    { key: 'racks', label: 'Racks', icon: Server, route: '/racks' },
    { key: 'devices', label: 'Devices', icon: HardDrive, route: '/devices' },
    { key: 'ports', label: 'Ports', icon: Plug, route: '/ports' },
    { key: 'connections', label: 'Connections', icon: Cable, route: '/connections' },
];

// Component state
const searchQuery = ref('');
const isOpen = ref(false);
const isLoading = ref(false);
const results = ref<SearchResults | null>(null);
const selectedIndex = ref(-1);
const inputRef = ref<HTMLInputElement | null>(null);
const dropdownRef = ref<HTMLDivElement | null>(null);

// Compute flat list of all results for keyboard navigation
const flatResults = computed(() => {
    if (!results.value) return [];

    const items: { item: SearchResultItem; type: keyof SearchResults }[] = [];

    for (const entityType of entityTypes) {
        const entityResults = results.value[entityType.key];
        if (entityResults && entityResults.items.length > 0) {
            for (const item of entityResults.items) {
                items.push({ item, type: entityType.key });
            }
        }
    }

    return items;
});

// Compute total results count
const totalResults = computed(() => {
    if (!results.value) return 0;
    return entityTypes.reduce((sum, et) => sum + (results.value?.[et.key]?.total ?? 0), 0);
});

// Check if there are any results to display
const hasResults = computed(() => {
    if (!results.value) return false;
    return entityTypes.some(et => (results.value?.[et.key]?.items.length ?? 0) > 0);
});

// Perform search API call
const performSearch = async (query: string) => {
    if (!query.trim()) {
        results.value = null;
        isLoading.value = false;
        return;
    }

    isLoading.value = true;

    try {
        const response = await fetch(quickSearch.url({ query: { q: query } }), {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            const data = await response.json();
            results.value = data.data;
            selectedIndex.value = -1;
        }
    } catch (error) {
        console.error('Search failed:', error);
        results.value = null;
    } finally {
        isLoading.value = false;
    }
};

// Debounced search (300ms)
const debouncedSearch = debounce((query: unknown) => {
    performSearch(query as string);
}, 300);

// Watch search query changes
watch(searchQuery, (newQuery) => {
    if (newQuery.trim()) {
        isOpen.value = true;
        debouncedSearch(newQuery);
    } else {
        results.value = null;
        selectedIndex.value = -1;
    }
});

// Handle input focus
const handleFocus = () => {
    if (searchQuery.value.trim()) {
        isOpen.value = true;
    }
};

// Handle input blur (with delay to allow clicking results)
const handleBlur = (event: FocusEvent) => {
    // Check if focus moved to dropdown
    const relatedTarget = event.relatedTarget as HTMLElement;
    if (dropdownRef.value?.contains(relatedTarget)) {
        return;
    }

    setTimeout(() => {
        isOpen.value = false;
    }, 200);
};

// Close dropdown and clear input
const closeAndClear = () => {
    isOpen.value = false;
    searchQuery.value = '';
    results.value = null;
    selectedIndex.value = -1;
    inputRef.value?.blur();
};

// Navigate to result
const navigateToResult = (item: SearchResultItem, type: keyof SearchResults) => {
    let url = '';
    switch (type) {
        case 'datacenters':
            url = `/datacenters/${item.id}`;
            break;
        case 'racks':
            url = `/racks/${item.id}`;
            break;
        case 'devices':
            url = `/devices/${item.id}`;
            break;
        case 'ports':
            url = `/devices/${item.device_id}`;
            break;
        case 'connections':
            url = `/connections/${item.id}`;
            break;
    }

    closeAndClear();
    router.visit(url);
};

// Navigate to full search results page
const navigateToSearchPage = () => {
    const query = searchQuery.value.trim();
    if (query) {
        closeAndClear();
        router.visit(searchIndex.url({ query: { q: query } }));
    }
};

// Keyboard navigation
const handleKeydown = (event: KeyboardEvent) => {
    if (!isOpen.value && event.key !== 'Escape') return;

    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            if (selectedIndex.value < flatResults.value.length - 1) {
                selectedIndex.value++;
            }
            break;

        case 'ArrowUp':
            event.preventDefault();
            if (selectedIndex.value > 0) {
                selectedIndex.value--;
            }
            break;

        case 'Enter':
            event.preventDefault();
            if (selectedIndex.value >= 0 && flatResults.value[selectedIndex.value]) {
                const { item, type } = flatResults.value[selectedIndex.value];
                navigateToResult(item, type);
            } else if (searchQuery.value.trim()) {
                navigateToSearchPage();
            }
            break;

        case 'Escape':
            event.preventDefault();
            closeAndClear();
            break;
    }
};

// Global keyboard shortcut (Cmd/Ctrl + K)
const handleGlobalKeydown = (event: KeyboardEvent) => {
    if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
        event.preventDefault();
        inputRef.value?.focus();
        isOpen.value = true;
    }
};

// Click outside to close
const handleClickOutside = (event: MouseEvent) => {
    const target = event.target as HTMLElement;
    if (!dropdownRef.value?.contains(target) && !inputRef.value?.contains(target)) {
        isOpen.value = false;
    }
};

// Highlight matched text
const highlightMatch = (text: string, query: string): string => {
    if (!query.trim() || !text) return text;

    const regex = new RegExp(`(${escapeRegex(query)})`, 'gi');
    return text.replace(regex, '<mark class="bg-yellow-200 dark:bg-yellow-800/50 text-inherit px-0.5 rounded">$1</mark>');
};

const escapeRegex = (str: string): string => {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
};

// Check if Mac platform for keyboard shortcut display
const isMac = computed(() => typeof window !== 'undefined' && window.navigator?.platform?.includes('Mac'));

// Get result index for keyboard navigation
const getResultIndex = (type: keyof SearchResults, itemIndex: number): number => {
    let index = 0;
    for (const entityType of entityTypes) {
        if (entityType.key === type) {
            return index + itemIndex;
        }
        index += results.value?.[entityType.key]?.items.length ?? 0;
    }
    return -1;
};

// Lifecycle
onMounted(() => {
    document.addEventListener('keydown', handleGlobalKeydown);
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleGlobalKeydown);
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="relative">
        <!-- Search Input -->
        <div class="relative flex items-center">
            <Search
                class="pointer-events-none absolute left-3 size-4 text-muted-foreground"
            />
            <input
                ref="inputRef"
                v-model="searchQuery"
                type="text"
                placeholder="Search..."
                :class="cn(
                    'h-9 w-full rounded-md border border-input bg-transparent pl-9 pr-9 text-sm shadow-xs transition-[color,box-shadow] outline-none',
                    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                    'placeholder:text-muted-foreground dark:bg-input/30',
                    'lg:w-64'
                )"
                @focus="handleFocus"
                @blur="handleBlur"
                @keydown="handleKeydown"
            />
            <!-- Clear button -->
            <button
                v-if="searchQuery"
                type="button"
                class="absolute right-3 text-muted-foreground hover:text-foreground"
                @click="closeAndClear"
            >
                <X class="size-4" />
            </button>
            <!-- Keyboard shortcut hint (desktop only) -->
            <div
                v-if="!searchQuery"
                class="pointer-events-none absolute right-3 hidden items-center gap-0.5 text-xs text-muted-foreground lg:flex"
            >
                <kbd class="rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px]">
                    {{ isMac ? '⌘' : 'Ctrl' }}
                </kbd>
                <kbd class="rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px]">K</kbd>
            </div>
        </div>

        <!-- Search Dropdown -->
        <div
            v-if="isOpen && (searchQuery.trim() || isLoading)"
            ref="dropdownRef"
            :class="cn(
                'absolute right-0 top-full z-50 mt-2 w-full min-w-[300px] max-w-[400px] overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-lg',
                'lg:w-[400px]'
            )"
        >
            <!-- Loading State -->
            <div
                v-if="isLoading"
                class="flex items-center justify-center gap-2 p-4"
            >
                <Spinner class="size-5" />
                <span class="text-sm text-muted-foreground">Searching...</span>
            </div>

            <!-- Empty State -->
            <div
                v-else-if="searchQuery.trim() && !hasResults"
                class="p-4 text-center text-sm text-muted-foreground"
            >
                No results found for "{{ searchQuery }}"
            </div>

            <!-- Results -->
            <div
                v-else-if="hasResults"
                class="max-h-[400px] overflow-y-auto"
            >
                <template v-for="entityType in entityTypes" :key="entityType.key">
                    <div
                        v-if="results?.[entityType.key]?.items.length"
                        class="border-b border-border last:border-b-0"
                    >
                        <!-- Section Header -->
                        <div class="flex items-center gap-2 bg-muted/50 px-3 py-2">
                            <component
                                :is="entityType.icon"
                                class="size-4 text-muted-foreground"
                            />
                            <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                {{ entityType.label }}
                            </span>
                            <span class="ml-auto text-xs text-muted-foreground">
                                {{ results?.[entityType.key]?.total }} found
                            </span>
                        </div>

                        <!-- Result Items -->
                        <div class="p-1">
                            <button
                                v-for="(item, itemIndex) in results?.[entityType.key]?.items"
                                :key="item.id"
                                type="button"
                                :class="cn(
                                    'w-full rounded px-3 py-2 text-left transition-colors hover:bg-accent',
                                    getResultIndex(entityType.key, itemIndex) === selectedIndex && 'bg-accent'
                                )"
                                @click="navigateToResult(item, entityType.key)"
                                @mouseenter="selectedIndex = getResultIndex(entityType.key, itemIndex)"
                            >
                                <!-- Result Name with Highlight -->
                                <div
                                    class="text-sm font-medium"
                                    v-html="highlightMatch(item.name, searchQuery)"
                                />

                                <!-- Breadcrumb Context -->
                                <div class="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground">
                                    <span v-html="highlightMatch(item.breadcrumb, searchQuery)" />
                                </div>
                            </button>
                        </div>
                    </div>
                </template>

                <!-- View All Results Link -->
                <div class="border-t border-border p-2">
                    <button
                        type="button"
                        class="flex w-full items-center justify-center gap-2 rounded px-3 py-2 text-sm text-primary hover:bg-accent"
                        @click="navigateToSearchPage"
                    >
                        View all {{ totalResults }} results
                        <ChevronRight class="size-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
