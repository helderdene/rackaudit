<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { index, show, download } from '@/actions/App/Http/Controllers/BulkExportController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import {
    ArrowLeft,
    FileSpreadsheet,
    Calendar,
    User,
    Download,
    CheckCircle,
    XCircle,
    Loader2,
    Clock,
    Filter
} from 'lucide-vue-next';

interface BulkExportData {
    id: number;
    entity_type: string | null;
    entity_type_label: string | null;
    format: string;
    format_label: string;
    file_name: string;
    status: string;
    status_label: string;
    total_rows: number | null;
    processed_rows: number | null;
    progress_percentage: number | null;
    filters: Record<string, number | string> | null;
    download_url: string | null;
    user?: {
        id: number;
        name: string;
    };
    created_at: string;
    started_at: string | null;
    completed_at: string | null;
}

interface Props {
    export: BulkExportData;
}

const props = defineProps<Props>();

// Use computed to access the export prop (since 'export' is a reserved word in templates)
const bulkExport = ref<BulkExportData>(props.export);
const isPolling = ref(false);
const pollTimeoutId = ref<ReturnType<typeof setTimeout> | null>(null);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Exports',
        href: '/exports',
    },
    {
        title: props.export.file_name,
        href: `/exports/${props.export.id}`,
    },
];

// Polling configuration - 5 seconds as per requirements
const POLL_INTERVAL = 5000;

// Computed properties
const isProcessing = computed(() =>
    bulkExport.value.status === 'pending' || bulkExport.value.status === 'processing'
);

const isCompleted = computed(() =>
    bulkExport.value.status === 'completed' || bulkExport.value.status === 'failed'
);

const progressPercent = computed(() => {
    const percent = bulkExport.value.progress_percentage || 0;
    return Math.min(100, Math.max(0, percent));
});

const statusIcon = computed(() => {
    switch (bulkExport.value.status) {
        case 'completed':
            return CheckCircle;
        case 'failed':
            return XCircle;
        case 'processing':
            return Loader2;
        default:
            return Clock;
    }
});

const statusVariant = computed((): 'default' | 'secondary' | 'destructive' | 'success' | 'warning' => {
    switch (bulkExport.value.status) {
        case 'completed':
            return 'success';
        case 'failed':
            return 'destructive';
        case 'processing':
            return 'default';
        default:
            return 'secondary';
    }
});

// Format date for display
const formatDate = (dateString: string | null): string => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Format filter for display
const formatFilters = (filters: Record<string, number | string> | null): string => {
    if (!filters || Object.keys(filters).length === 0) {
        return 'None (all data)';
    }

    const parts: string[] = [];
    if (filters.datacenter_id) parts.push(`Datacenter ID: ${filters.datacenter_id}`);
    if (filters.room_id) parts.push(`Room ID: ${filters.room_id}`);
    if (filters.row_id) parts.push(`Row ID: ${filters.row_id}`);
    if (filters.rack_id) parts.push(`Rack ID: ${filters.rack_id}`);

    return parts.length > 0 ? parts.join(', ') : 'None (all data)';
};

// Polling functions
const fetchStatus = async () => {
    if (isPolling.value || !isProcessing.value) return;

    isPolling.value = true;

    try {
        const response = await fetch(show.url(bulkExport.value.id), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.ok) {
            const result = await response.json();
            bulkExport.value = result.data;
        }
    } catch (error) {
        console.error('Failed to fetch export status:', error);
    } finally {
        isPolling.value = false;
    }
};

const startPolling = () => {
    if (!isProcessing.value) return;

    pollTimeoutId.value = setTimeout(async () => {
        await fetchStatus();
        if (isProcessing.value) {
            startPolling();
        }
    }, POLL_INTERVAL);
};

const stopPolling = () => {
    if (pollTimeoutId.value) {
        clearTimeout(pollTimeoutId.value);
        pollTimeoutId.value = null;
    }
};

// Watch for prop changes
watch(() => props.export, (newExport) => {
    bulkExport.value = newExport;
});

// Watch for status changes to manage polling
watch(isProcessing, (processing) => {
    if (processing) {
        startPolling();
    } else {
        stopPolling();
    }
});

onMounted(() => {
    if (isProcessing.value) {
        startPolling();
    }
});

onUnmounted(() => {
    stopPolling();
});
</script>

<template>
    <Head :title="`Export: ${bulkExport.file_name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <HeadingSmall
                    title="Export Details"
                    :description="`Viewing export: ${bulkExport.file_name}`"
                />
                <Link :href="index.url()">
                    <Button variant="outline">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Exports
                    </Button>
                </Link>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Progress Card -->
                <Card>
                    <CardHeader>
                        <CardTitle>Export Progress</CardTitle>
                        <CardDescription>
                            Real-time status and progress of your export.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <!-- Status badge and icon -->
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full" :class="{
                                    'bg-primary/10 text-primary': bulkExport.status === 'processing',
                                    'bg-muted text-muted-foreground': bulkExport.status === 'pending',
                                    'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400': bulkExport.status === 'completed',
                                    'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400': bulkExport.status === 'failed',
                                }">
                                    <component
                                        :is="statusIcon"
                                        class="h-5 w-5"
                                        :class="{ 'animate-spin': bulkExport.status === 'processing' }"
                                    />
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <Badge :variant="statusVariant">
                                            {{ bulkExport.status_label }}
                                        </Badge>
                                        <span v-if="isProcessing" class="text-xs text-muted-foreground">
                                            Updating every 5 seconds...
                                        </span>
                                    </div>
                                    <p v-if="bulkExport.total_rows" class="mt-1 text-sm text-muted-foreground">
                                        {{ bulkExport.processed_rows ?? 0 }} of {{ bulkExport.total_rows }} rows processed
                                    </p>
                                </div>
                            </div>

                            <!-- Progress bar -->
                            <div v-if="bulkExport.total_rows" class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-muted-foreground">Progress</span>
                                    <span class="font-medium">{{ progressPercent.toFixed(0) }}%</span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full transition-all duration-300"
                                        :class="{
                                            'bg-primary': !isCompleted,
                                            'bg-green-500': bulkExport.status === 'completed',
                                            'bg-red-500': bulkExport.status === 'failed',
                                        }"
                                        :style="{ width: `${progressPercent}%` }"
                                    />
                                </div>
                            </div>

                            <!-- Loading skeleton when pending without row count -->
                            <div v-else-if="bulkExport.status === 'pending'" class="space-y-2">
                                <Skeleton class="h-4 w-24" />
                                <Skeleton class="h-2 w-full" />
                            </div>

                            <!-- Download button when completed -->
                            <div v-if="bulkExport.status === 'completed'" class="pt-4">
                                <a
                                    v-if="bulkExport.download_url"
                                    :href="download.url(bulkExport.id)"
                                    target="_blank"
                                    class="inline-block"
                                >
                                    <Button class="w-full">
                                        <Download class="mr-2 h-4 w-4" />
                                        Download Export
                                    </Button>
                                </a>
                            </div>

                            <!-- Error message if failed -->
                            <div
                                v-if="bulkExport.status === 'failed'"
                                class="rounded-lg bg-red-50 p-4 dark:bg-red-900/20"
                            >
                                <p class="text-sm font-medium text-red-600 dark:text-red-400">
                                    Export Failed
                                </p>
                                <p class="mt-1 text-sm text-red-600/80 dark:text-red-400/80">
                                    An error occurred while generating the export. Please try again.
                                </p>
                            </div>

                            <!-- Row count on completion -->
                            <div v-if="isCompleted && bulkExport.total_rows" class="rounded-lg bg-muted/50 p-4">
                                <p class="text-sm font-medium">Total Rows Exported</p>
                                <p class="mt-1 text-2xl font-semibold">
                                    {{ bulkExport.total_rows }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Details Card -->
                <Card>
                    <CardHeader>
                        <CardTitle>Export Information</CardTitle>
                        <CardDescription>
                            Details about this export operation.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl class="space-y-4">
                            <div class="flex items-start gap-3">
                                <FileSpreadsheet class="mt-0.5 h-4 w-4 text-muted-foreground" />
                                <div>
                                    <dt class="text-sm font-medium">File Name</dt>
                                    <dd class="text-sm text-muted-foreground">{{ bulkExport.file_name }}</dd>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 h-4 w-4 flex items-center justify-center text-xs font-bold text-muted-foreground">
                                    T
                                </div>
                                <div>
                                    <dt class="text-sm font-medium">Entity Type</dt>
                                    <dd class="text-sm text-muted-foreground">
                                        {{ bulkExport.entity_type_label || 'Unknown' }}
                                    </dd>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <FileSpreadsheet class="mt-0.5 h-4 w-4 text-muted-foreground" />
                                <div>
                                    <dt class="text-sm font-medium">Format</dt>
                                    <dd class="text-sm text-muted-foreground">
                                        {{ bulkExport.format?.toUpperCase() || 'Unknown' }}
                                    </dd>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <Filter class="mt-0.5 h-4 w-4 text-muted-foreground" />
                                <div>
                                    <dt class="text-sm font-medium">Applied Filters</dt>
                                    <dd class="text-sm text-muted-foreground">
                                        {{ formatFilters(bulkExport.filters) }}
                                    </dd>
                                </div>
                            </div>

                            <div v-if="bulkExport.user" class="flex items-start gap-3">
                                <User class="mt-0.5 h-4 w-4 text-muted-foreground" />
                                <div>
                                    <dt class="text-sm font-medium">Exported By</dt>
                                    <dd class="text-sm text-muted-foreground">{{ bulkExport.user.name }}</dd>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <Calendar class="mt-0.5 h-4 w-4 text-muted-foreground" />
                                <div>
                                    <dt class="text-sm font-medium">Created</dt>
                                    <dd class="text-sm text-muted-foreground">{{ formatDate(bulkExport.created_at) }}</dd>
                                </div>
                            </div>

                            <div v-if="bulkExport.started_at" class="flex items-start gap-3">
                                <Calendar class="mt-0.5 h-4 w-4 text-muted-foreground" />
                                <div>
                                    <dt class="text-sm font-medium">Started</dt>
                                    <dd class="text-sm text-muted-foreground">{{ formatDate(bulkExport.started_at) }}</dd>
                                </div>
                            </div>

                            <div v-if="bulkExport.completed_at" class="flex items-start gap-3">
                                <Calendar class="mt-0.5 h-4 w-4 text-muted-foreground" />
                                <div>
                                    <dt class="text-sm font-medium">Completed</dt>
                                    <dd class="text-sm text-muted-foreground">{{ formatDate(bulkExport.completed_at) }}</dd>
                                </div>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
