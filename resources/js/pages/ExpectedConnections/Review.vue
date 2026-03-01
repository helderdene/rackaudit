<script setup lang="ts">
import {
    bulkConfirm,
    bulkSkip,
    index as expectedConnectionsIndex,
} from '@/actions/App/Http/Controllers/ExpectedConnectionController';
import ConnectionReviewTable, {
    type ExpectedConnectionData,
} from '@/components/expected-connections/ConnectionReviewTable.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle,
    FileSpreadsheet,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface Props {
    implementationFileId: number;
    implementationFileName?: string;
    datacenterId?: number;
}

const props = defineProps<Props>();

// State
const isLoading = ref(true);
const connections = ref<ExpectedConnectionData[]>([]);
const statistics = ref({
    total: 0,
    pending_review: 0,
    confirmed: 0,
    skipped: 0,
});
const implementationFile = ref<{ id: number; original_name: string } | null>(
    null,
);
const loadError = ref<string | null>(null);

// Bulk action state
const isBulkActionLoading = ref(false);
const bulkActionError = ref<string | null>(null);

// Finalize dialog state
const finalizeDialogOpen = ref(false);

// Computed
const allReviewed = computed(
    () => statistics.value.pending_review === 0 && statistics.value.total > 0,
);

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    const items: BreadcrumbItem[] = [];

    if (props.datacenterId) {
        items.push({
            title: 'Datacenters',
            href: '/datacenters',
        });
    }

    if (implementationFile.value) {
        items.push({
            title:
                implementationFile.value.original_name || 'Implementation File',
            href: props.datacenterId
                ? `/datacenters/${props.datacenterId}/implementation-files/${props.implementationFileId}`
                : '#',
        });
    }

    items.push({
        title: 'Review Connections',
        href: '#',
    });

    return items;
});

/**
 * Load expected connections from API
 */
async function loadConnections(): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    try {
        const response = await axios.get(
            expectedConnectionsIndex.url({
                query: {
                    implementation_file: props.implementationFileId.toString(),
                },
            }),
        );

        connections.value = response.data.data || [];
        statistics.value = response.data.statistics || {
            total: 0,
            pending_review: 0,
            confirmed: 0,
            skipped: 0,
        };
        implementationFile.value = response.data.implementation_file || null;
    } catch (error) {
        console.error('Error loading connections:', error);
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            loadError.value = error.response.data.message;
        } else {
            loadError.value = 'Failed to load expected connections.';
        }
    } finally {
        isLoading.value = false;
    }
}

/**
 * Handle connection update
 */
function handleUpdateConnection(
    connectionId: number,
    data: Partial<ExpectedConnectionData>,
): void {
    // Update local state optimistically
    const index = connections.value.findIndex((c) => c.id === connectionId);
    if (index !== -1) {
        connections.value[index] = { ...connections.value[index], ...data };
    }
    // Reload to get fresh data
    loadConnections();
}

/**
 * Handle bulk confirm action
 */
async function handleBulkConfirm(connectionIds: number[]): Promise<void> {
    if (connectionIds.length === 0) return;

    isBulkActionLoading.value = true;
    bulkActionError.value = null;

    try {
        await axios.post(bulkConfirm.url(), {
            connection_ids: connectionIds,
        });

        // Reload connections
        await loadConnections();
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            bulkActionError.value = error.response.data.message;
        } else {
            bulkActionError.value = 'Failed to confirm connections.';
        }
    } finally {
        isBulkActionLoading.value = false;
    }
}

/**
 * Handle bulk skip action
 */
async function handleBulkSkip(connectionIds: number[]): Promise<void> {
    if (connectionIds.length === 0) return;

    isBulkActionLoading.value = true;
    bulkActionError.value = null;

    try {
        await axios.post(bulkSkip.url(), {
            connection_ids: connectionIds,
        });

        // Reload connections
        await loadConnections();
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            bulkActionError.value = error.response.data.message;
        } else {
            bulkActionError.value = 'Failed to skip connections.';
        }
    } finally {
        isBulkActionLoading.value = false;
    }
}

/**
 * Handle refresh
 */
function handleRefresh(): void {
    loadConnections();
}

/**
 * Handle finalize review
 */
function handleFinalizeReview(): void {
    finalizeDialogOpen.value = false;

    // Navigate back to implementation file or datacenters
    if (props.datacenterId) {
        router.visit(`/datacenters/${props.datacenterId}`);
    } else {
        router.visit('/datacenters');
    }
}

// Load connections on mount
onMounted(() => {
    loadConnections();
});
</script>

<template>
    <Head title="Review Connections" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="flex items-start gap-4">
                    <Link
                        v-if="datacenterId"
                        :href="`/datacenters/${datacenterId}`"
                        class="mt-1"
                    >
                        <Button variant="ghost" size="icon" class="size-8">
                            <ArrowLeft class="size-4" />
                        </Button>
                    </Link>
                    <HeadingSmall
                        title="Review Expected Connections"
                        :description="
                            implementationFile?.original_name ??
                            'Review and confirm parsed connections'
                        "
                    />
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        v-if="allReviewed"
                        class="gap-2"
                        @click="finalizeDialogOpen = true"
                    >
                        <CheckCircle class="size-4" />
                        Finalize Review
                    </Button>
                </div>
            </div>

            <!-- Statistics Card -->
            <Card v-if="!isLoading && !loadError">
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <FileSpreadsheet class="size-5" />
                        Connection Summary
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap gap-6">
                        <div class="flex items-center gap-2">
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >Total Rows:</span
                            >
                            <Badge variant="secondary" class="text-base">{{
                                statistics.total
                            }}</Badge>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >Pending Review:</span
                            >
                            <Badge
                                v-if="statistics.pending_review > 0"
                                variant="warning"
                                class="text-base"
                            >
                                {{ statistics.pending_review }}
                            </Badge>
                            <Badge v-else variant="outline" class="text-base"
                                >0</Badge
                            >
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >Confirmed:</span
                            >
                            <Badge
                                v-if="statistics.confirmed > 0"
                                class="bg-green-600 text-base"
                            >
                                {{ statistics.confirmed }}
                            </Badge>
                            <Badge v-else variant="outline" class="text-base"
                                >0</Badge
                            >
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >Skipped:</span
                            >
                            <Badge variant="secondary" class="text-base">{{
                                statistics.skipped
                            }}</Badge>
                        </div>
                    </div>

                    <!-- Progress indicator -->
                    <div v-if="statistics.total > 0" class="mt-4">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground"
                                >Review Progress</span
                            >
                            <span class="font-medium">
                                {{ statistics.confirmed + statistics.skipped }}
                                / {{ statistics.total }}
                            </span>
                        </div>
                        <div
                            class="mt-2 h-2 w-full overflow-hidden rounded-full bg-secondary"
                        >
                            <div
                                class="h-full bg-green-600 transition-all duration-300"
                                :style="{
                                    width: `${((statistics.confirmed + statistics.skipped) / statistics.total) * 100}%`,
                                }"
                            />
                        </div>
                    </div>

                    <!-- All reviewed message -->
                    <div
                        v-if="allReviewed"
                        class="mt-4 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400"
                    >
                        <CheckCircle class="size-4 shrink-0" />
                        <span
                            >All connections have been reviewed! Click "Finalize
                            Review" to complete.</span
                        >
                    </div>
                </CardContent>
            </Card>

            <!-- Loading State -->
            <div
                v-if="isLoading"
                class="flex items-center justify-center py-12"
            >
                <div class="flex flex-col items-center gap-4">
                    <Spinner class="size-8" />
                    <p class="text-sm text-muted-foreground">
                        Loading expected connections...
                    </p>
                </div>
            </div>

            <!-- Error State -->
            <div
                v-else-if="loadError"
                class="flex flex-col items-center justify-center gap-4 py-12"
            >
                <AlertTriangle class="size-12 text-amber-500" />
                <div class="text-center">
                    <h3 class="text-lg font-medium">
                        Failed to load connections
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ loadError }}
                    </p>
                </div>
                <Button variant="outline" @click="loadConnections">
                    Try Again
                </Button>
            </div>

            <!-- Connection Review Table -->
            <ConnectionReviewTable
                v-else
                :connections="connections"
                :statistics="statistics"
                :is-loading="isBulkActionLoading"
                :implementation-file-id="implementationFileId"
                @update-connection="handleUpdateConnection"
                @bulk-confirm="handleBulkConfirm"
                @bulk-skip="handleBulkSkip"
                @refresh="handleRefresh"
            />

            <!-- Bulk Action Error -->
            <div
                v-if="bulkActionError"
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400"
            >
                {{ bulkActionError }}
            </div>

            <!-- Finalize Dialog -->
            <Dialog v-model:open="finalizeDialogOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Finalize Review</DialogTitle>
                        <DialogDescription>
                            <div>
                                <p>
                                    You have reviewed all
                                    {{ statistics.total }} connections:
                                </p>
                                <ul
                                    class="mt-2 list-inside list-disc space-y-1"
                                >
                                    <li>
                                        <strong>{{
                                            statistics.confirmed
                                        }}</strong>
                                        connections confirmed
                                    </li>
                                    <li>
                                        <strong>{{
                                            statistics.skipped
                                        }}</strong>
                                        connections skipped
                                    </li>
                                </ul>
                                <p class="mt-2">
                                    Only confirmed connections will be used for
                                    comparison with actual connections during
                                    audits.
                                </p>
                            </div>
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary"
                                >Continue Reviewing</Button
                            >
                        </DialogClose>
                        <Button @click="handleFinalizeReview">
                            Finalize Review
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
