<script setup lang="ts">
import { computed } from 'vue';
import { Calendar, AlertTriangle, Clock } from 'lucide-vue-next';

interface Props {
    dueDate: string | null;
    isOverdue: boolean;
    isDueSoon: boolean;
}

const props = defineProps<Props>();

// Format date for display
const formattedDate = computed(() => {
    if (!props.dueDate) return null;

    const date = new Date(props.dueDate);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: date.getFullYear() !== new Date().getFullYear() ? 'numeric' : undefined,
    });
});

// Get indicator classes based on due date status
const indicatorClasses = computed(() => {
    if (props.isOverdue) {
        return 'text-red-600 dark:text-red-400';
    }
    if (props.isDueSoon) {
        return 'text-amber-600 dark:text-amber-400';
    }
    return 'text-muted-foreground';
});

// Get background classes for badge style
const badgeClasses = computed(() => {
    if (props.isOverdue) {
        return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
    }
    if (props.isDueSoon) {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400';
    }
    return 'bg-muted text-muted-foreground';
});

// Get icon based on status
const IconComponent = computed(() => {
    if (props.isOverdue) {
        return AlertTriangle;
    }
    if (props.isDueSoon) {
        return Clock;
    }
    return Calendar;
});

// Get status text
const statusText = computed(() => {
    if (props.isOverdue) {
        return 'Overdue';
    }
    if (props.isDueSoon) {
        return 'Due Soon';
    }
    return null;
});
</script>

<template>
    <div v-if="dueDate" class="flex items-center gap-1">
        <span
            :class="[
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                badgeClasses,
            ]"
        >
            <component :is="IconComponent" class="size-3" />
            <span>{{ formattedDate }}</span>
            <span v-if="statusText" class="ml-1 font-semibold">
                ({{ statusText }})
            </span>
        </span>
    </div>
    <div v-else class="text-sm text-muted-foreground">
        -
    </div>
</template>
