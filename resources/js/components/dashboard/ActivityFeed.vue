<script setup lang="ts">
import ActionBadge from '@/components/ActionBadge.vue';
import ActivityDetailPanel from '@/components/activity/ActivityDetailPanel.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { computed, ref } from 'vue';

interface ActivityLogEntry {
    id: number;
    timestamp: string;
    timestamp_relative: string;
    user_name: string;
    action: 'created' | 'updated' | 'deleted' | 'restored';
    entity_type: string;
    summary: string;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
}

interface Props {
    activities: ActivityLogEntry[];
}

interface GroupedActivities {
    label: string;
    activities: ActivityLogEntry[];
}

const props = defineProps<Props>();

// Track expanded row IDs
const expandedRowIds = ref<Set<number>>(new Set());

/**
 * Toggle row expansion.
 */
const toggleRowExpansion = (id: number): void => {
    const newExpanded = new Set(expandedRowIds.value);
    if (newExpanded.has(id)) {
        newExpanded.delete(id);
    } else {
        newExpanded.add(id);
    }
    expandedRowIds.value = newExpanded;
};

/**
 * Check if a row is expanded.
 */
const isRowExpanded = (id: number): boolean => {
    return expandedRowIds.value.has(id);
};

/**
 * Get the date group label for an activity.
 */
const getDateGroup = (timestamp: string): string => {
    const date = new Date(timestamp);
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    const weekAgo = new Date(today);
    weekAgo.setDate(weekAgo.getDate() - 7);

    const activityDate = new Date(
        date.getFullYear(),
        date.getMonth(),
        date.getDate(),
    );

    if (activityDate.getTime() === today.getTime()) {
        return 'Today';
    } else if (activityDate.getTime() === yesterday.getTime()) {
        return 'Yesterday';
    } else if (activityDate >= weekAgo) {
        return 'This Week';
    } else {
        return 'Earlier';
    }
};

/**
 * Group activities by date.
 */
const groupedActivities = computed((): GroupedActivities[] => {
    const groups: Record<string, ActivityLogEntry[]> = {};
    const order = ['Today', 'Yesterday', 'This Week', 'Earlier'];

    for (const activity of props.activities) {
        const group = getDateGroup(activity.timestamp);
        if (!groups[group]) {
            groups[group] = [];
        }
        groups[group].push(activity);
    }

    return order
        .filter((label) => groups[label]?.length > 0)
        .map((label) => ({
            label,
            activities: groups[label],
        }));
});

/**
 * Format time for display (e.g., "2:30 PM").
 */
const formatTime = (timestamp: string): string => {
    const date = new Date(timestamp);
    return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
};

/**
 * Check if activities array is empty.
 */
const isEmpty = computed(() => props.activities.length === 0);
</script>

<template>
    <Card>
        <CardHeader class="pb-3">
            <CardTitle class="text-lg font-semibold text-foreground"
                >Recent Activity</CardTitle
            >
        </CardHeader>
        <CardContent class="max-h-80 overflow-y-auto pt-0">
            <!-- Empty state -->
            <div
                v-if="isEmpty"
                class="flex flex-col items-center justify-center py-8 text-center text-muted-foreground"
            >
                <svg
                    class="mb-4 h-12 w-12 text-muted-foreground/50"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <p class="text-sm">No recent activity to display.</p>
            </div>

            <!-- Grouped activity list -->
            <div v-else class="space-y-6">
                <div v-for="group in groupedActivities" :key="group.label">
                    <!-- Group header -->
                    <div class="mb-3 flex items-center gap-3">
                        <h3
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            {{ group.label }}
                        </h3>
                        <div class="h-px flex-1 bg-border" />
                        <span class="text-xs text-muted-foreground/70">
                            {{ group.activities.length }}
                            {{
                                group.activities.length === 1
                                    ? 'activity'
                                    : 'activities'
                            }}
                        </span>
                    </div>

                    <!-- Activities in group -->
                    <div class="space-y-1">
                        <template
                            v-for="activity in group.activities"
                            :key="activity.id"
                        >
                            <!-- Activity row -->
                            <div
                                class="group cursor-pointer rounded-lg border border-transparent px-3 py-2.5 transition-all duration-150 hover:border-border/50 hover:bg-muted/50 dark:hover:bg-muted/30"
                                :class="{
                                    'border-border bg-muted/40 dark:bg-muted/20':
                                        isRowExpanded(activity.id),
                                }"
                                @click="toggleRowExpansion(activity.id)"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <!-- Left side: Content -->
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="font-medium text-foreground"
                                            >
                                                {{ activity.user_name }}
                                            </span>
                                            <ActionBadge
                                                :action="activity.action"
                                            />
                                            <span
                                                class="text-sm text-muted-foreground"
                                            >
                                                {{ activity.entity_type }}
                                            </span>
                                        </div>
                                        <p
                                            class="mt-1 truncate text-sm text-muted-foreground/80"
                                        >
                                            {{ activity.summary }}
                                        </p>
                                    </div>

                                    <!-- Right side: Time and expand indicator -->
                                    <div
                                        class="flex shrink-0 items-center gap-2"
                                    >
                                        <span
                                            class="text-xs text-muted-foreground/70"
                                        >
                                            {{ formatTime(activity.timestamp) }}
                                        </span>
                                        <svg
                                            class="h-4 w-4 text-muted-foreground/50 transition-transform duration-200"
                                            :class="{
                                                'rotate-180': isRowExpanded(
                                                    activity.id,
                                                ),
                                            }"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M19 9l-7 7-7-7"
                                            />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Expanded detail panel -->
                            <div
                                v-if="isRowExpanded(activity.id)"
                                class="ml-3 border-l-2 border-border/50 pl-3"
                            >
                                <ActivityDetailPanel
                                    :old-values="activity.old_values"
                                    :new-values="activity.new_values"
                                />
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
