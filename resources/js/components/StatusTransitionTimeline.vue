<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { FindingStatusValue } from '@/types/finding';
import { ArrowRight, Clock, MessageSquare, User } from 'lucide-vue-next';
import { computed } from 'vue';

interface StatusTransition {
    id: number;
    from_status: FindingStatusValue;
    from_status_label: string;
    to_status: FindingStatusValue;
    to_status_label: string;
    user: {
        id: number;
        name: string;
    } | null;
    notes: string | null;
    transitioned_at: string | null;
}

interface Props {
    transitions: StatusTransition[];
}

const props = defineProps<Props>();

// Check if there are any transitions
const hasTransitions = computed(() => props.transitions.length > 0);

// Format relative time
const formatRelativeTime = (dateString: string | null): string => {
    if (!dateString) return 'Unknown time';

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
        return `${diffMinutes} minute${diffMinutes === 1 ? '' : 's'} ago`;
    } else if (diffHours < 24) {
        return `${diffHours} hour${diffHours === 1 ? '' : 's'} ago`;
    } else if (diffDays < 7) {
        return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`;
    } else {
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year:
                date.getFullYear() !== now.getFullYear()
                    ? 'numeric'
                    : undefined,
        });
    }
};

// Get status badge classes
const getStatusBadgeClass = (status: FindingStatusValue): string => {
    const baseClasses =
        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium';
    switch (status) {
        case 'open':
            return `${baseClasses} bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400`;
        case 'in_progress':
            return `${baseClasses} bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400`;
        case 'pending_review':
            return `${baseClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400`;
        case 'deferred':
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400`;
        case 'resolved':
            return `${baseClasses} bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400`;
        default:
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300`;
    }
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2 text-lg">
                <Clock class="size-5" />
                Status Transition History
            </CardTitle>
        </CardHeader>
        <CardContent>
            <div v-if="hasTransitions" class="space-y-4">
                <div
                    v-for="transition in transitions"
                    :key="transition.id"
                    class="relative border-l-2 border-muted pb-4 pl-4 last:pb-0"
                >
                    <!-- Timeline dot -->
                    <div
                        class="absolute top-1 -left-1.5 size-3 rounded-full border-2 border-background bg-muted"
                    />

                    <!-- Status change -->
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            :class="getStatusBadgeClass(transition.from_status)"
                        >
                            {{ transition.from_status_label }}
                        </span>
                        <ArrowRight class="size-4 text-muted-foreground" />
                        <span
                            :class="getStatusBadgeClass(transition.to_status)"
                        >
                            {{ transition.to_status_label }}
                        </span>
                    </div>

                    <!-- User and time -->
                    <div
                        class="mt-1 flex flex-wrap items-center gap-3 text-sm text-muted-foreground"
                    >
                        <span
                            v-if="transition.user"
                            class="flex items-center gap-1"
                        >
                            <User class="size-3" />
                            {{ transition.user.name }}
                        </span>
                        <span class="flex items-center gap-1">
                            <Clock class="size-3" />
                            {{ formatRelativeTime(transition.transitioned_at) }}
                        </span>
                    </div>

                    <!-- Notes -->
                    <div
                        v-if="transition.notes"
                        class="mt-2 flex items-start gap-2 rounded-md bg-muted/50 p-2 text-sm"
                    >
                        <MessageSquare
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        />
                        <p class="whitespace-pre-line text-muted-foreground">
                            {{ transition.notes }}
                        </p>
                    </div>
                </div>
            </div>

            <div v-else class="py-8 text-center text-muted-foreground">
                <Clock class="mx-auto mb-2 size-8 opacity-50" />
                <p>No status transitions yet.</p>
                <p class="text-sm">
                    Changes to the finding status will appear here.
                </p>
            </div>
        </CardContent>
    </Card>
</template>
