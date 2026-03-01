<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { CheckCircle2, XCircle, Clock, AlertCircle, ChevronLeft, ChevronRight } from 'lucide-vue-next';

interface Execution {
    id: number;
    status: 'pending' | 'success' | 'failed';
    started_at: string | null;
    completed_at: string | null;
    error_message: string | null;
    file_size_bytes: number | null;
    recipients_count: number | null;
    duration_seconds: number | null;
}

interface Pagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    executions: Execution[];
    pagination?: Pagination | null;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    pagination: null,
    loading: false,
});

const emit = defineEmits<{
    (e: 'page-change', page: number): void;
}>();

/**
 * Format a date string for display
 */
const formatDate = (dateString: string | null): string => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

/**
 * Format duration in seconds to human-readable format
 */
const formatDuration = (seconds: number | null): string => {
    if (seconds === null || seconds === undefined) return '-';
    if (seconds < 60) return `${seconds}s`;
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    if (minutes < 60) return `${minutes}m ${remainingSeconds}s`;
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    return `${hours}h ${remainingMinutes}m`;
};

/**
 * Format file size in bytes to human-readable format
 */
const formatFileSize = (bytes: number | null): string => {
    if (bytes === null || bytes === undefined) return '-';
    if (bytes < 1024) return `${bytes} B`;
    const kb = bytes / 1024;
    if (kb < 1024) return `${kb.toFixed(1)} KB`;
    const mb = kb / 1024;
    return `${mb.toFixed(1)} MB`;
};

/**
 * Get status badge variant
 */
const getStatusVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'success':
            return 'default';
        case 'failed':
            return 'destructive';
        case 'pending':
            return 'secondary';
        default:
            return 'outline';
    }
};

/**
 * Get status badge class
 */
const getStatusClass = (status: string): string => {
    if (status === 'success') {
        return 'bg-green-100 text-green-800 hover:bg-green-100 dark:bg-green-900/30 dark:text-green-400';
    }
    return '';
};

/**
 * Get status icon component
 */
const getStatusIcon = (status: string) => {
    switch (status) {
        case 'success':
            return CheckCircle2;
        case 'failed':
            return XCircle;
        case 'pending':
            return Clock;
        default:
            return AlertCircle;
    }
};

/**
 * Get status label
 */
const getStatusLabel = (status: string): string => {
    switch (status) {
        case 'success':
            return 'Success';
        case 'failed':
            return 'Failed';
        case 'pending':
            return 'Pending';
        default:
            return status;
    }
};

/**
 * Check if there are pages to navigate
 */
const hasPagination = computed(() => {
    return props.pagination && props.pagination.last_page > 1;
});

/**
 * Check if we can go to previous page
 */
const canGoPrevious = computed(() => {
    return props.pagination && props.pagination.current_page > 1;
});

/**
 * Check if we can go to next page
 */
const canGoNext = computed(() => {
    return props.pagination && props.pagination.current_page < props.pagination.last_page;
});

/**
 * Go to previous page
 */
const goToPrevious = () => {
    if (canGoPrevious.value && props.pagination) {
        emit('page-change', props.pagination.current_page - 1);
    }
};

/**
 * Go to next page
 */
const goToNext = () => {
    if (canGoNext.value && props.pagination) {
        emit('page-change', props.pagination.current_page + 1);
    }
};
</script>

<template>
    <div class="space-y-4">
        <!-- Empty state -->
        <div
            v-if="executions.length === 0 && !loading"
            class="flex flex-col items-center justify-center rounded-lg border border-dashed py-12 text-center"
        >
            <Clock class="h-10 w-10 text-muted-foreground" />
            <h3 class="mt-4 text-sm font-medium">No execution history</h3>
            <p class="mt-1 text-sm text-muted-foreground">
                This schedule has not been executed yet.
            </p>
        </div>

        <!-- Executions table -->
        <div v-else class="overflow-hidden rounded-md border">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="h-10 px-4 text-left font-medium text-muted-foreground">Date</th>
                            <th class="h-10 px-4 text-left font-medium text-muted-foreground">Status</th>
                            <th class="h-10 px-4 text-left font-medium text-muted-foreground">Duration</th>
                            <th class="h-10 px-4 text-left font-medium text-muted-foreground">Recipients</th>
                            <th class="h-10 px-4 text-left font-medium text-muted-foreground">File Size</th>
                            <th class="h-10 px-4 text-left font-medium text-muted-foreground">Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="execution in executions"
                            :key="execution.id"
                            class="border-b transition-colors hover:bg-muted/50"
                        >
                            <td class="p-4 font-medium">
                                {{ formatDate(execution.started_at) }}
                            </td>
                            <td class="p-4">
                                <Badge
                                    :variant="getStatusVariant(execution.status)"
                                    :class="getStatusClass(execution.status)"
                                >
                                    <component :is="getStatusIcon(execution.status)" class="mr-1 h-3 w-3" />
                                    {{ getStatusLabel(execution.status) }}
                                </Badge>
                            </td>
                            <td class="p-4 text-muted-foreground">
                                {{ formatDuration(execution.duration_seconds) }}
                            </td>
                            <td class="p-4 text-muted-foreground">
                                {{ execution.recipients_count ?? '-' }}
                            </td>
                            <td class="p-4 text-muted-foreground">
                                {{ formatFileSize(execution.file_size_bytes) }}
                            </td>
                            <td class="p-4">
                                <TooltipProvider v-if="execution.error_message">
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <span class="inline-block max-w-[200px] cursor-help truncate text-sm text-destructive">
                                                {{ execution.error_message.slice(0, 30) }}...
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent side="left" class="max-w-sm">
                                            <p class="text-sm">{{ execution.error_message }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                                <span v-else class="text-muted-foreground">-</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="hasPagination" class="flex items-center justify-between">
            <p class="text-sm text-muted-foreground">
                Page {{ pagination?.current_page }} of {{ pagination?.last_page }}
                ({{ pagination?.total }} total)
            </p>
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!canGoPrevious || loading"
                    @click="goToPrevious"
                >
                    <ChevronLeft class="mr-1 h-4 w-4" />
                    Previous
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!canGoNext || loading"
                    @click="goToNext"
                >
                    Next
                    <ChevronRight class="ml-1 h-4 w-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
