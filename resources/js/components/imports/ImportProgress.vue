<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { show } from '@/actions/App/Http/Controllers/BulkImportController';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { CheckCircle, XCircle, Loader2, Clock } from 'lucide-vue-next';

interface BulkImportData {
    id: number;
    status: string;
    status_label: string;
    total_rows: number;
    processed_rows: number;
    success_count: number;
    failure_count: number;
    progress_percentage: number;
    has_errors: boolean;
}

interface Props {
    import: BulkImportData;
    pollInterval?: number;
    autoPoll?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    pollInterval: 2000,
    autoPoll: true,
});

const emit = defineEmits<{
    (e: 'status-updated', data: BulkImportData): void;
    (e: 'completed', data: BulkImportData): void;
}>();

const currentImport = ref<BulkImportData>(props.import);
const isPolling = ref(false);
const pollTimeoutId = ref<ReturnType<typeof setTimeout> | null>(null);

const isProcessing = computed(() =>
    currentImport.value.status === 'pending' || currentImport.value.status === 'processing'
);

const isCompleted = computed(() =>
    currentImport.value.status === 'completed' || currentImport.value.status === 'failed'
);

const progressPercent = computed(() => {
    const percent = currentImport.value.progress_percentage || 0;
    return Math.min(100, Math.max(0, percent));
});

const statusIcon = computed(() => {
    switch (currentImport.value.status) {
        case 'completed':
            return currentImport.value.failure_count > 0 ? XCircle : CheckCircle;
        case 'failed':
            return XCircle;
        case 'processing':
            return Loader2;
        default:
            return Clock;
    }
});

const statusVariant = computed((): 'default' | 'secondary' | 'destructive' | 'success' | 'warning' => {
    switch (currentImport.value.status) {
        case 'completed':
            return currentImport.value.failure_count > 0 ? 'warning' : 'success';
        case 'failed':
            return 'destructive';
        case 'processing':
            return 'default';
        default:
            return 'secondary';
    }
});

const fetchStatus = async () => {
    if (isPolling.value || !isProcessing.value) return;

    isPolling.value = true;

    try {
        const response = await fetch(show.url(currentImport.value.id), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.ok) {
            const result = await response.json();
            currentImport.value = result.data;
            emit('status-updated', currentImport.value);

            if (isCompleted.value) {
                emit('completed', currentImport.value);
            }
        }
    } catch (error) {
        console.error('Failed to fetch import status:', error);
    } finally {
        isPolling.value = false;
    }
};

const startPolling = () => {
    if (!props.autoPoll || !isProcessing.value) return;

    pollTimeoutId.value = setTimeout(async () => {
        await fetchStatus();
        if (isProcessing.value) {
            startPolling();
        }
    }, props.pollInterval);
};

const stopPolling = () => {
    if (pollTimeoutId.value) {
        clearTimeout(pollTimeoutId.value);
        pollTimeoutId.value = null;
    }
};

// Watch for prop changes
watch(() => props.import, (newImport) => {
    currentImport.value = newImport;
});

// Watch for status changes to manage polling
watch(isProcessing, (processing) => {
    if (processing && props.autoPoll) {
        startPolling();
    } else {
        stopPolling();
    }
});

onMounted(() => {
    if (props.autoPoll && isProcessing.value) {
        startPolling();
    }
});

onUnmounted(() => {
    stopPolling();
});

defineExpose({
    currentImport,
    fetchStatus,
    startPolling,
    stopPolling,
});
</script>

<template>
    <div class="space-y-4">
        <!-- Status badge and icon -->
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-full" :class="{
                'bg-primary/10 text-primary': currentImport.status === 'processing',
                'bg-muted text-muted-foreground': currentImport.status === 'pending',
                'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400': currentImport.status === 'completed' && currentImport.failure_count === 0,
                'bg-yellow-100 text-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-400': currentImport.status === 'completed' && currentImport.failure_count > 0,
                'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400': currentImport.status === 'failed',
            }">
                <component
                    :is="statusIcon"
                    class="h-5 w-5"
                    :class="{ 'animate-spin': currentImport.status === 'processing' }"
                />
            </div>
            <div class="flex-1">
                <div class="flex items-center gap-2">
                    <Badge :variant="statusVariant">
                        {{ currentImport.status_label }}
                    </Badge>
                    <span v-if="isProcessing" class="text-xs text-muted-foreground">
                        Updating automatically...
                    </span>
                </div>
                <p v-if="currentImport.total_rows" class="mt-1 text-sm text-muted-foreground">
                    {{ currentImport.processed_rows }} of {{ currentImport.total_rows }} rows processed
                </p>
            </div>
        </div>

        <!-- Progress bar -->
        <div v-if="currentImport.total_rows" class="space-y-2">
            <div class="flex justify-between text-sm">
                <span class="text-muted-foreground">Progress</span>
                <span class="font-medium">{{ progressPercent.toFixed(0) }}%</span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full rounded-full transition-all duration-300"
                    :class="{
                        'bg-primary': !isCompleted,
                        'bg-green-500': currentImport.status === 'completed' && currentImport.failure_count === 0,
                        'bg-yellow-500': currentImport.status === 'completed' && currentImport.failure_count > 0,
                        'bg-red-500': currentImport.status === 'failed',
                    }"
                    :style="{ width: `${progressPercent}%` }"
                />
            </div>
        </div>

        <!-- Loading skeleton when pending without row count -->
        <div v-else-if="currentImport.status === 'pending'" class="space-y-2">
            <Skeleton class="h-4 w-24" />
            <Skeleton class="h-2 w-full" />
        </div>

        <!-- Success/failure counts on completion -->
        <div v-if="isCompleted" class="grid grid-cols-2 gap-4">
            <div class="rounded-lg bg-green-50 p-3 dark:bg-green-900/20">
                <p class="text-xs font-medium text-green-600 dark:text-green-400">Successful</p>
                <p class="mt-1 text-2xl font-semibold text-green-700 dark:text-green-300">
                    {{ currentImport.success_count }}
                </p>
            </div>
            <div class="rounded-lg bg-red-50 p-3 dark:bg-red-900/20">
                <p class="text-xs font-medium text-red-600 dark:text-red-400">Failed</p>
                <p class="mt-1 text-2xl font-semibold text-red-700 dark:text-red-300">
                    {{ currentImport.failure_count }}
                </p>
            </div>
        </div>
    </div>
</template>
