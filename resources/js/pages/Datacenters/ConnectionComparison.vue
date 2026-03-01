<script setup lang="ts">
import { ComparisonFilters, ComparisonTable } from '@/components/comparison';
import ConnectionDetailsModal from '@/components/comparison/ConnectionDetailsModal.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type {
    ComparisonFilterOptions,
    ComparisonResultData,
    ComparisonStatistics as ComparisonStatsType,
} from '@/types/comparison';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    ArrowLeft,
    ChevronLeft,
    ChevronRight,
    GitCompare,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface DatacenterData {
    id: number;
    name: string;
    formatted_location: string;
}

interface PaginationMeta {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
}

interface Props {
    datacenter: DatacenterData;
    initialComparisons: ComparisonResultData[];
    filterOptions: ComparisonFilterOptions;
    statistics: ComparisonStatsType;
    paginationMeta: PaginationMeta;
}

const props = defineProps<Props>();

// State
const isLoading = ref(false);
const comparisons = ref<ComparisonResultData[]>(props.initialComparisons);
const statistics = ref<ComparisonStatsType>(props.statistics);
const pagination = ref<PaginationMeta>(props.paginationMeta);
const loadError = ref<string | null>(null);

// Create Connection Dialog state
const createDialogOpen = ref(false);
const createDialogComparison = ref<ComparisonResultData | null>(null);
const createFormProcessing = ref(false);
const createFormError = ref<string | null>(null);
const cableType = ref('cat6');
const cableLength = ref('3.0');
const cableColor = ref('');
const pathNotes = ref('');

// Delete Connection Dialog state
const deleteDialogOpen = ref(false);
const deleteDialogComparison = ref<ComparisonResultData | null>(null);
const deleteFormProcessing = ref(false);
const deleteFormError = ref<string | null>(null);

// Connection Details Modal state
const detailsModalOpen = ref(false);
const selectedComparison = ref<ComparisonResultData | null>(null);

// Breadcrumbs
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'Datacenters',
        href: '/datacenters',
    },
    {
        title: props.datacenter.name,
        href: `/datacenters/${props.datacenter.id}`,
    },
    {
        title: 'Connection Audit',
        href: '#',
    },
]);

// Cable type options
const cableTypeOptions = [
    { value: 'cat5e', label: 'Cat5e' },
    { value: 'cat6', label: 'Cat6' },
    { value: 'cat6a', label: 'Cat6a' },
    { value: 'cat7', label: 'Cat7' },
    { value: 'fiber_om3', label: 'Fiber OM3' },
    { value: 'fiber_om4', label: 'Fiber OM4' },
    { value: 'fiber_sm', label: 'Fiber Single-mode' },
    { value: 'dac', label: 'DAC Cable' },
    { value: 'power', label: 'Power Cable' },
];

// Pagination computed values
const hasPreviousPage = computed(() => pagination.value.current_page > 1);
const hasNextPage = computed(
    () => pagination.value.current_page < pagination.value.last_page,
);
const paginationInfo = computed(() => {
    if (pagination.value.total === 0) {
        return 'No results';
    }
    return `Showing ${pagination.value.from} - ${pagination.value.to} of ${pagination.value.total}`;
});

/**
 * Load comparison data from API
 */
async function loadComparisons(
    queryParams: Record<string, string | string[]> = {},
): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    try {
        const params = new URLSearchParams();

        // Add query parameters
        Object.entries(queryParams).forEach(([key, value]) => {
            if (Array.isArray(value)) {
                value.forEach((v) => params.append(key, v));
            } else {
                params.set(key, value);
            }
        });

        const url = `/api/datacenters/${props.datacenter.id}/connection-comparison${params.toString() ? '?' + params.toString() : ''}`;
        const response = await axios.get(url);

        comparisons.value = response.data.data ?? [];
        statistics.value = response.data.statistics ?? props.statistics;
        if (response.data.pagination) {
            pagination.value = response.data.pagination;
        }
    } catch (error) {
        console.error('Error loading comparisons:', error);
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            loadError.value = error.response.data.message;
        } else {
            loadError.value = 'Failed to load comparison data.';
        }
    } finally {
        isLoading.value = false;
    }
}

/**
 * Handle filter changes
 */
function handleFilterChange(filters: {
    discrepancyTypes: string[];
    deviceId: number | null;
    rackId: number | null;
    showAcknowledged: boolean;
}): void {
    const queryParams: Record<string, string | string[]> = {};

    if (filters.discrepancyTypes.length > 0) {
        queryParams['discrepancy_type[]'] = filters.discrepancyTypes;
    }

    if (filters.deviceId !== null) {
        queryParams['device_id'] = filters.deviceId.toString();
    }

    if (filters.rackId !== null) {
        queryParams['rack_id'] = filters.rackId.toString();
    }

    if (!filters.showAcknowledged) {
        queryParams['show_acknowledged'] = '0';
    }

    // Reset to page 1 when filters change
    queryParams['page'] = '1';

    loadComparisons(queryParams);
}

/**
 * Handle export
 */
function handleExport(): void {
    const exportUrl = `/api/datacenters/${props.datacenter.id}/connection-comparison/export`;
    window.open(exportUrl, '_blank');
}

/**
 * Handle refresh
 */
function handleRefresh(): void {
    loadComparisons({ page: pagination.value.current_page.toString() });
}

/**
 * Handle pagination
 */
function goToPage(page: number): void {
    router.visit(
        `/datacenters/${props.datacenter.id}/connection-comparison?page=${page}`,
    );
}

function previousPage(): void {
    if (hasPreviousPage.value) {
        goToPage(pagination.value.current_page - 1);
    }
}

function nextPage(): void {
    if (hasNextPage.value) {
        goToPage(pagination.value.current_page + 1);
    }
}

/**
 * Handle create connection
 */
function handleCreateConnection(comparison: ComparisonResultData): void {
    createDialogComparison.value = comparison;
    createFormError.value = null;
    cableType.value = 'cat6';
    cableLength.value = '3.0';
    cableColor.value = '';
    pathNotes.value = '';
    createDialogOpen.value = true;
}

/**
 * Submit create connection form
 */
async function submitCreateConnection(): Promise<void> {
    if (!createDialogComparison.value) return;

    const sourcePortId = createDialogComparison.value.source_port?.id;
    const destPortId = createDialogComparison.value.dest_port?.id;

    if (!sourcePortId || !destPortId) {
        createFormError.value = 'Missing port information.';
        return;
    }

    createFormProcessing.value = true;
    createFormError.value = null;

    try {
        await axios.post('/connections', {
            source_port_id: sourcePortId,
            destination_port_id: destPortId,
            cable_type: cableType.value,
            cable_length: parseFloat(cableLength.value),
            cable_color: cableColor.value || null,
            path_notes: pathNotes.value || null,
        });

        createDialogOpen.value = false;
        createDialogComparison.value = null;

        // Refresh the comparison data
        handleRefresh();
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            createFormError.value = error.response.data.message;
        } else {
            createFormError.value = 'Failed to create connection.';
        }
    } finally {
        createFormProcessing.value = false;
    }
}

/**
 * Handle delete connection
 */
function handleDeleteConnection(comparison: ComparisonResultData): void {
    deleteDialogComparison.value = comparison;
    deleteFormError.value = null;
    deleteDialogOpen.value = true;
}

/**
 * Submit delete connection
 */
async function submitDeleteConnection(): Promise<void> {
    if (!deleteDialogComparison.value?.actual_connection?.id) return;

    deleteFormProcessing.value = true;
    deleteFormError.value = null;

    try {
        await axios.delete(
            `/connections/${deleteDialogComparison.value.actual_connection.id}`,
        );

        deleteDialogOpen.value = false;
        deleteDialogComparison.value = null;

        // Refresh the comparison data
        handleRefresh();
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            deleteFormError.value = error.response.data.message;
        } else {
            deleteFormError.value = 'Failed to delete connection.';
        }
    } finally {
        deleteFormProcessing.value = false;
    }
}

/**
 * Handle view details (for matched connections)
 */
function handleViewDetails(comparison: ComparisonResultData): void {
    selectedComparison.value = comparison;
    detailsModalOpen.value = true;
}

// Select styling class
const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';
</script>

<template>
    <Head title="Connection Audit" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="flex items-start gap-4">
                    <Link :href="`/datacenters/${datacenter.id}`" class="mt-1">
                        <Button variant="ghost" size="icon" class="size-8">
                            <ArrowLeft class="size-4" />
                        </Button>
                    </Link>
                    <HeadingSmall
                        title="Connection Audit"
                        :description="`${datacenter.name} - ${datacenter.formatted_location}`"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        class="gap-2"
                        @click="handleRefresh"
                    >
                        <GitCompare class="size-4" />
                        Refresh
                    </Button>
                </div>
            </div>

            <!-- Filters -->
            <ComparisonFilters
                :devices="filterOptions.devices"
                :racks="filterOptions.racks"
                :is-loading="isLoading"
                :export-url="`/api/datacenters/${datacenter.id}/connection-comparison/export`"
                @filter-change="handleFilterChange"
                @export="handleExport"
                @refresh="handleRefresh"
            />

            <!-- Loading State -->
            <div
                v-if="isLoading"
                class="flex items-center justify-center py-12"
            >
                <div class="flex flex-col items-center gap-4">
                    <Spinner class="size-8" />
                    <p class="text-sm text-muted-foreground">
                        Loading comparison data...
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
                        Failed to load comparison
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ loadError }}
                    </p>
                </div>
                <Button variant="outline" @click="handleRefresh">
                    Try Again
                </Button>
            </div>

            <!-- Comparison Table -->
            <ComparisonTable
                v-else
                :comparisons="comparisons"
                :statistics="statistics"
                :is-loading="isLoading"
                @create-connection="handleCreateConnection"
                @delete-connection="handleDeleteConnection"
                @view-details="handleViewDetails"
                @refresh="handleRefresh"
            />

            <!-- Pagination Controls -->
            <div
                v-if="
                    !isLoading &&
                    !loadError &&
                    pagination.total > pagination.per_page
                "
                class="flex items-center justify-between border-t pt-4"
            >
                <div class="text-sm text-muted-foreground">
                    {{ paginationInfo }}
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!hasPreviousPage"
                        @click="previousPage"
                    >
                        <ChevronLeft class="size-4" />
                        Previous
                    </Button>
                    <span class="text-sm text-muted-foreground">
                        Page {{ pagination.current_page }} of
                        {{ pagination.last_page }}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!hasNextPage"
                        @click="nextPage"
                    >
                        Next
                        <ChevronRight class="size-4" />
                    </Button>
                </div>
            </div>

            <!-- Create Connection Dialog -->
            <Dialog v-model:open="createDialogOpen">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Create Connection</DialogTitle>
                        <DialogDescription>
                            Create the missing connection to match the expected
                            configuration.
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="createDialogComparison" class="space-y-4">
                        <!-- Connection Summary -->
                        <div
                            class="rounded-lg border bg-muted/30 p-4 dark:bg-muted/20"
                        >
                            <div class="space-y-2 text-sm">
                                <div class="flex items-center gap-2">
                                    <span class="text-muted-foreground"
                                        >From:</span
                                    >
                                    <span class="font-medium">
                                        {{
                                            createDialogComparison.source_device
                                                ?.name ?? 'Unknown'
                                        }}
                                        ({{
                                            createDialogComparison.source_port
                                                ?.label ?? 'Unknown'
                                        }})
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-muted-foreground"
                                        >To:</span
                                    >
                                    <span class="font-medium">
                                        {{
                                            createDialogComparison.dest_device
                                                ?.name ?? 'Unknown'
                                        }}
                                        ({{
                                            createDialogComparison.dest_port
                                                ?.label ?? 'Unknown'
                                        }})
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Cable Properties -->
                        <div class="space-y-4">
                            <div class="grid gap-2">
                                <Label for="cable-type">Cable Type</Label>
                                <select
                                    id="cable-type"
                                    v-model="cableType"
                                    :class="selectClass"
                                >
                                    <option
                                        v-for="option in cableTypeOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>

                            <div class="grid gap-2">
                                <Label for="cable-length"
                                    >Cable Length (meters)</Label
                                >
                                <Input
                                    id="cable-length"
                                    v-model="cableLength"
                                    type="number"
                                    step="0.1"
                                    min="0.1"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="cable-color"
                                    >Cable Color (optional)</Label
                                >
                                <Input
                                    id="cable-color"
                                    v-model="cableColor"
                                    placeholder="e.g., blue, yellow"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="path-notes"
                                    >Path Notes (optional)</Label
                                >
                                <Textarea
                                    id="path-notes"
                                    v-model="pathNotes"
                                    placeholder="Notes about cable routing..."
                                    rows="2"
                                />
                            </div>
                        </div>

                        <InputError
                            v-if="createFormError"
                            :message="createFormError"
                        />
                    </div>

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button
                                variant="secondary"
                                :disabled="createFormProcessing"
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            :disabled="createFormProcessing"
                            @click="submitCreateConnection"
                        >
                            {{
                                createFormProcessing
                                    ? 'Creating...'
                                    : 'Create Connection'
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Delete Connection Dialog -->
            <Dialog v-model:open="deleteDialogOpen">
                <DialogContent>
                    <DialogHeader class="space-y-3">
                        <DialogTitle class="flex items-center gap-2">
                            <AlertTriangle class="size-5 text-destructive" />
                            Delete Connection
                        </DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this unexpected
                            connection? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        v-if="deleteDialogComparison"
                        class="rounded-lg border bg-muted/30 p-4 dark:bg-muted/20"
                    >
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center gap-2">
                                <span class="text-muted-foreground">From:</span>
                                <span class="font-medium">
                                    {{
                                        deleteDialogComparison.source_device
                                            ?.name ?? 'Unknown'
                                    }}
                                    ({{
                                        deleteDialogComparison.source_port
                                            ?.label ?? 'Unknown'
                                    }})
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-muted-foreground">To:</span>
                                <span class="font-medium">
                                    {{
                                        deleteDialogComparison.dest_device
                                            ?.name ?? 'Unknown'
                                    }}
                                    ({{
                                        deleteDialogComparison.dest_port
                                            ?.label ?? 'Unknown'
                                    }})
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Warning -->
                    <div
                        class="rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
                    >
                        <div
                            class="relative space-y-0.5 text-red-600 dark:text-red-100"
                        >
                            <p class="font-medium">Warning</p>
                            <p class="text-sm">
                                Both ports will be set back to "Available"
                                status. The connection record will be
                                permanently removed.
                            </p>
                        </div>
                    </div>

                    <InputError
                        v-if="deleteFormError"
                        :message="deleteFormError"
                    />

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button
                                variant="secondary"
                                :disabled="deleteFormProcessing"
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            :disabled="deleteFormProcessing"
                            @click="submitDeleteConnection"
                        >
                            {{
                                deleteFormProcessing
                                    ? 'Deleting...'
                                    : 'Delete Connection'
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Connection Details Modal -->
            <ConnectionDetailsModal
                v-model:open="detailsModalOpen"
                :comparison="selectedComparison"
            />
        </div>
    </AppLayout>
</template>
