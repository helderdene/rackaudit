<script setup lang="ts">
import ConnectionHistoryController from '@/actions/App/Http/Controllers/ConnectionHistoryController';
import ActionBadge from '@/components/ActionBadge.vue';
import ActivityDetailPanel from '@/components/activity/ActivityDetailPanel.vue';
import { Button } from '@/components/ui/button';
import {
    ChevronDown,
    ChevronUp,
    Clock,
    Globe,
    User as UserIcon,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface TimelineEntry {
    id: number;
    subject_type: string;
    subject_id: number;
    causer_id: number | null;
    causer_name: string;
    causer_role: string | null;
    action: 'created' | 'updated' | 'deleted' | 'restored';
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string;
    user_agent: string | null;
    created_at: string;
}

interface TimelineMeta {
    current_page: number;
    per_page: number;
    total: number;
    has_more: boolean;
}

interface Props {
    /** Connection ID to load timeline for */
    connectionId: number;
    /** Initial entries (if pre-loaded) */
    initialEntries?: TimelineEntry[];
    /** Whether to load entries on mount */
    autoLoad?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    initialEntries: () => [],
    autoLoad: true,
});

const entries = ref<TimelineEntry[]>(props.initialEntries);
const meta = ref<TimelineMeta | null>(null);
const isLoading = ref(false);
const expandedEntryIds = ref<Set<number>>(new Set());
const currentPage = ref(1);

// Load timeline data
const loadTimeline = async (page: number = 1) => {
    if (isLoading.value) return;

    isLoading.value = true;

    try {
        const response = await fetch(
            ConnectionHistoryController.timeline.url(props.connectionId, {
                query: { page },
            }),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (!response.ok) throw new Error('Failed to load timeline');

        const data = await response.json();

        if (page === 1) {
            entries.value = data.data;
        } else {
            entries.value = [...entries.value, ...data.data];
        }

        meta.value = data.meta;
        currentPage.value = page;
    } catch (error) {
        console.error('Failed to load timeline:', error);
    } finally {
        isLoading.value = false;
    }
};

// Load more entries
const loadMore = () => {
    if (meta.value?.has_more) {
        loadTimeline(currentPage.value + 1);
    }
};

// Toggle entry expansion
const toggleEntryExpansion = (id: number) => {
    const newExpanded = new Set(expandedEntryIds.value);
    if (newExpanded.has(id)) {
        newExpanded.delete(id);
    } else {
        newExpanded.add(id);
    }
    expandedEntryIds.value = newExpanded;
};

const isExpanded = (id: number): boolean => {
    return expandedEntryIds.value.has(id);
};

// Format timestamp as relative time with exact datetime on hover
const formatRelativeTime = (dateString: string): string => {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffSeconds = Math.floor(diffMs / 1000);
    const diffMinutes = Math.floor(diffSeconds / 60);
    const diffHours = Math.floor(diffMinutes / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffSeconds < 60) {
        return 'Just now';
    } else if (diffMinutes < 60) {
        return `${diffMinutes} minute${diffMinutes > 1 ? 's' : ''} ago`;
    } else if (diffHours < 24) {
        return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
    } else if (diffDays < 7) {
        return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
    } else {
        return date.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    }
};

const formatExactDateTime = (dateString: string): string => {
    const date = new Date(dateString);
    return date.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

// Format user display with role
const formatUserDisplay = (entry: TimelineEntry): string => {
    if (!entry.causer_name || entry.causer_name === 'System') {
        return 'System';
    }
    if (entry.causer_role) {
        return `${entry.causer_name} (${entry.causer_role})`;
    }
    return entry.causer_name;
};

// Get action color for timeline indicator
const getActionColor = (action: string): string => {
    const colors: Record<string, string> = {
        created: 'bg-green-500',
        updated: 'bg-yellow-500',
        deleted: 'bg-red-500',
        restored: 'bg-blue-500',
    };
    return colors[action] || 'bg-gray-500';
};

// Check if timeline has entries
const hasEntries = computed(() => entries.value.length > 0);

// Check if we can load more
const canLoadMore = computed(() => meta.value?.has_more ?? false);

// Auto-load on mount if enabled
if (props.autoLoad && props.initialEntries.length === 0) {
    loadTimeline();
}

// Expose methods for parent components
defineExpose({
    loadTimeline,
    loadMore,
});
</script>

<template>
    <div class="connection-timeline">
        <!-- Loading skeleton -->
        <div v-if="isLoading && entries.length === 0" class="space-y-4">
            <div v-for="i in 3" :key="i" class="flex animate-pulse gap-4">
                <div class="flex flex-col items-center">
                    <div class="h-3 w-3 rounded-full bg-muted"></div>
                    <div class="h-full w-0.5 bg-muted"></div>
                </div>
                <div class="flex-1 space-y-2 pb-6">
                    <div class="h-4 w-24 rounded bg-muted"></div>
                    <div class="h-3 w-48 rounded bg-muted"></div>
                    <div class="h-3 w-32 rounded bg-muted"></div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else-if="!hasEntries && !isLoading" class="py-8 text-center">
            <Clock class="mx-auto h-12 w-12 text-muted-foreground/50" />
            <p class="mt-4 text-sm text-muted-foreground">
                No history entries found
            </p>
        </div>

        <!-- Timeline entries -->
        <div v-else class="relative">
            <!-- Timeline line -->
            <div
                class="absolute top-0 bottom-0 left-[5px] w-0.5 bg-border"
            ></div>

            <div class="space-y-0">
                <div
                    v-for="(entry, index) in entries"
                    :key="entry.id"
                    class="relative flex gap-4"
                >
                    <!-- Timeline indicator -->
                    <div class="relative z-10 flex flex-col items-center">
                        <div
                            :class="[
                                'h-3 w-3 rounded-full ring-4 ring-background',
                                getActionColor(entry.action),
                            ]"
                        ></div>
                        <div
                            v-if="index < entries.length - 1"
                            class="h-full w-0.5 bg-transparent"
                        ></div>
                    </div>

                    <!-- Entry content -->
                    <div
                        class="group flex-1 cursor-pointer pb-6"
                        @click="toggleEntryExpansion(entry.id)"
                    >
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3"
                        >
                            <!-- Action badge -->
                            <ActionBadge :action="entry.action" />

                            <!-- Timestamp with hover tooltip -->
                            <span
                                class="flex items-center gap-1 text-sm text-muted-foreground"
                                :title="formatExactDateTime(entry.created_at)"
                            >
                                <Clock class="h-3.5 w-3.5" />
                                {{ formatRelativeTime(entry.created_at) }}
                            </span>

                            <!-- Expand indicator -->
                            <component
                                :is="
                                    isExpanded(entry.id)
                                        ? ChevronUp
                                        : ChevronDown
                                "
                                class="ml-auto h-4 w-4 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100"
                            />
                        </div>

                        <!-- User info -->
                        <div
                            class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm"
                        >
                            <span
                                class="flex items-center gap-1.5 text-foreground"
                            >
                                <UserIcon
                                    class="h-3.5 w-3.5 text-muted-foreground"
                                />
                                {{ formatUserDisplay(entry) }}
                            </span>

                            <span
                                v-if="entry.ip_address"
                                class="flex items-center gap-1.5 text-muted-foreground"
                            >
                                <Globe class="h-3.5 w-3.5" />
                                {{ entry.ip_address }}
                            </span>
                        </div>

                        <!-- Expanded detail panel -->
                        <Transition
                            enter-active-class="transition-all duration-200 ease-out"
                            enter-from-class="opacity-0 max-h-0"
                            enter-to-class="opacity-100 max-h-[1000px]"
                            leave-active-class="transition-all duration-150 ease-in"
                            leave-from-class="opacity-100 max-h-[1000px]"
                            leave-to-class="opacity-0 max-h-0"
                        >
                            <div
                                v-if="isExpanded(entry.id)"
                                class="overflow-hidden"
                            >
                                <ActivityDetailPanel
                                    :old-values="entry.old_values"
                                    :new-values="entry.new_values"
                                />
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>

            <!-- Load more button -->
            <div v-if="canLoadMore" class="mt-4 flex justify-center">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="isLoading"
                    @click.stop="loadMore"
                >
                    <template v-if="isLoading">Loading...</template>
                    <template v-else>Load more</template>
                </Button>
            </div>
        </div>
    </div>
</template>
