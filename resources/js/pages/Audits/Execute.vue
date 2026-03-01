<script setup lang="ts">
import {
    bulkVerify as bulkVerifyAction,
    discrepant as discrepantAction,
    index as verificationsIndex,
    stats as verificationsStats,
    verify as verifyAction,
} from '@/actions/App/Http/Controllers/Api/AuditConnectionVerificationController';
import AuditController from '@/actions/App/Http/Controllers/AuditController';
import BulkVerifyButton from '@/components/audits/BulkVerifyButton.vue';
import ConnectionVerificationTable from '@/components/audits/ConnectionVerificationTable.vue';
import VerificationActionDialog from '@/components/audits/VerificationActionDialog.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { FeatureTour } from '@/components/help';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle,
    ClipboardCheck,
    RefreshCw,
    XCircle,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface ProgressStats {
    total: number;
    verified: number;
    discrepant: number;
    pending: number;
    completed: number;
    progress_percentage: number;
}

interface AuditData {
    id: number;
    name: string;
    status: string;
    status_label: string;
    type: string;
    type_label: string;
    datacenter: {
        id: number;
        name: string;
    };
    implementation_file: {
        id: number;
        original_name: string;
    } | null;
}

interface DiscrepancyTypeOption {
    value: string;
    label: string;
}

interface VerificationStatusOption {
    value: string;
    label: string;
}

interface VerificationData {
    id: number;
    comparison_status: string;
    comparison_status_label: string;
    verification_status: string;
    verification_status_label: string;
    discrepancy_type: string | null;
    discrepancy_type_label: string | null;
    source_device: {
        id: number;
        name: string;
        asset_tag: string | null;
    } | null;
    source_port: {
        id: number;
        label: string;
        type: string | null;
        type_label: string | null;
    } | null;
    dest_device: {
        id: number;
        name: string;
        asset_tag: string | null;
    } | null;
    dest_port: {
        id: number;
        label: string;
        type: string | null;
        type_label: string | null;
    } | null;
    expected_connection: {
        id: number;
        row_number: number;
    } | null;
    actual_connection: {
        id: number;
    } | null;
    row_number: number | null;
    notes: string | null;
    verified_by: {
        id: number;
        name: string;
    } | null;
    verified_at: string | null;
    locked_by: {
        id: number;
        name: string;
    } | null;
    locked_at: string | null;
    is_locked: boolean;
}

interface Props {
    audit: AuditData;
    progress_stats: ProgressStats;
    discrepancy_types: DiscrepancyTypeOption[];
    verification_statuses: VerificationStatusOption[];
}

const props = defineProps<Props>();

// Shared select styles with touch-friendly sizing
const selectClass =
    'flex h-11 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';

// State
const isLoading = ref(true);
const verifications = ref<VerificationData[]>([]);
const loadError = ref<string | null>(null);
const stats = ref<ProgressStats>(props.progress_stats);

// Filter state
const comparisonStatusFilter = ref<string>('');
const verificationStatusFilter = ref<string>('');
const searchQuery = ref<string>('');

// Pagination
const currentPage = ref(1);
const lastPage = ref(1);
const perPage = ref(25);
const total = ref(0);

// Selection state for bulk actions
const selectedIds = ref<Set<number>>(new Set());

// Dialog state
const actionDialogOpen = ref(false);
const actionDialogVerification = ref<VerificationData | null>(null);
const isActionLoading = ref(false);
const actionError = ref<string | null>(null);

// Echo channel reference
let echoChannel: any = null;

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'Audits',
        href: AuditController.index.url(),
    },
    {
        title: props.audit.name,
        href: AuditController.show.url(props.audit.id),
    },
    {
        title: 'Execute',
        href: '#',
    },
]);

// Get status badge class
const getStatusBadgeClass = (status: string): string => {
    const baseClasses =
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium';
    switch (status) {
        case 'pending':
            return `${baseClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200`;
        case 'in_progress':
            return `${baseClasses} bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200`;
        case 'completed':
            return `${baseClasses} bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200`;
        case 'cancelled':
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300`;
        default:
            return `${baseClasses} bg-gray-100 text-gray-800`;
    }
};

// Matched connections that can be bulk verified
const matchedSelectedCount = computed(() => {
    return verifications.value.filter(
        (v) =>
            selectedIds.value.has(v.id) &&
            v.comparison_status === 'matched' &&
            v.verification_status === 'pending' &&
            !v.is_locked,
    ).length;
});

// Check if any filters are active
const hasActiveFilters = computed(() => {
    return (
        comparisonStatusFilter.value ||
        verificationStatusFilter.value ||
        searchQuery.value
    );
});

/**
 * Load verifications from API
 */
async function loadVerifications(): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    try {
        const queryParams: Record<string, string> = {
            page: currentPage.value.toString(),
            per_page: perPage.value.toString(),
        };

        if (comparisonStatusFilter.value) {
            queryParams.comparison_status = comparisonStatusFilter.value;
        }
        if (verificationStatusFilter.value) {
            queryParams.verification_status = verificationStatusFilter.value;
        }
        if (searchQuery.value) {
            queryParams.search = searchQuery.value;
        }

        const response = await axios.get(
            verificationsIndex.url(props.audit.id, { query: queryParams }),
        );

        verifications.value = response.data.data || [];
        currentPage.value = response.data.meta?.current_page || 1;
        lastPage.value = response.data.meta?.last_page || 1;
        total.value = response.data.meta?.total || 0;
    } catch (error) {
        console.error('Error loading verifications:', error);
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            loadError.value = error.response.data.message;
        } else {
            loadError.value = 'Failed to load verification items.';
        }
    } finally {
        isLoading.value = false;
    }
}

/**
 * Load progress stats
 */
async function loadStats(): Promise<void> {
    try {
        const response = await axios.get(
            verificationsStats.url(props.audit.id),
        );
        stats.value = response.data.data;
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

/**
 * Handle filter change
 */
function handleFilterChange(): void {
    currentPage.value = 1;
    selectedIds.value.clear();
    loadVerifications();
}

/**
 * Handle search
 */
function handleSearch(): void {
    currentPage.value = 1;
    selectedIds.value.clear();
    loadVerifications();
}

/**
 * Clear filters
 */
function clearFilters(): void {
    comparisonStatusFilter.value = '';
    verificationStatusFilter.value = '';
    searchQuery.value = '';
    currentPage.value = 1;
    selectedIds.value.clear();
    loadVerifications();
}

/**
 * Open action dialog for a verification
 */
function openActionDialog(verification: VerificationData): void {
    actionDialogVerification.value = verification;
    actionDialogOpen.value = true;
    actionError.value = null;
}

/**
 * Handle verification action (verified or discrepant)
 */
async function handleVerificationAction(data: {
    action: 'verified' | 'discrepant';
    discrepancyType?: string;
    notes: string;
}): Promise<void> {
    if (!actionDialogVerification.value) return;

    isActionLoading.value = true;
    actionError.value = null;

    try {
        if (data.action === 'verified') {
            await axios.post(
                verifyAction.url({
                    audit: props.audit.id,
                    verification: actionDialogVerification.value.id,
                }),
                { notes: data.notes || null },
            );
        } else {
            await axios.post(
                discrepantAction.url({
                    audit: props.audit.id,
                    verification: actionDialogVerification.value.id,
                }),
                {
                    discrepancy_type: data.discrepancyType,
                    notes: data.notes,
                },
            );
        }

        actionDialogOpen.value = false;
        actionDialogVerification.value = null;

        // Reload data
        await Promise.all([loadVerifications(), loadStats()]);

        // Check if audit status changed to completed
        router.reload({ only: ['audit', 'progress_stats'] });
    } catch (error) {
        console.error('Error processing verification:', error);
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            actionError.value = error.response.data.message;
        } else {
            actionError.value = 'Failed to process verification.';
        }
    } finally {
        isActionLoading.value = false;
    }
}

/**
 * Handle bulk verify action
 */
async function handleBulkVerify(): Promise<{
    verified: number;
    skipped: number;
}> {
    const selectedMatchedIds = verifications.value
        .filter(
            (v) =>
                selectedIds.value.has(v.id) &&
                v.comparison_status === 'matched' &&
                v.verification_status === 'pending' &&
                !v.is_locked,
        )
        .map((v) => v.id);

    if (selectedMatchedIds.length === 0) {
        return { verified: 0, skipped: 0 };
    }

    const response = await axios.post(bulkVerifyAction.url(props.audit.id), {
        verification_ids: selectedMatchedIds,
    });

    selectedIds.value.clear();

    // Reload data
    await Promise.all([loadVerifications(), loadStats()]);

    // Check if audit status changed
    router.reload({ only: ['audit', 'progress_stats'] });

    return {
        verified: response.data.results.verified_count,
        skipped:
            response.data.results.skipped_locked_count +
            response.data.results.skipped_not_matched_count,
    };
}

/**
 * Toggle selection for a verification
 */
function toggleSelection(id: number): void {
    if (selectedIds.value.has(id)) {
        selectedIds.value.delete(id);
    } else {
        selectedIds.value.add(id);
    }
}

/**
 * Toggle all selections
 */
function toggleAllSelection(): void {
    const pendingVerifications = verifications.value.filter(
        (v) => v.verification_status === 'pending' && !v.is_locked,
    );

    if (pendingVerifications.every((v) => selectedIds.value.has(v.id))) {
        // Deselect all
        pendingVerifications.forEach((v) => selectedIds.value.delete(v.id));
    } else {
        // Select all pending, unlocked
        pendingVerifications.forEach((v) => selectedIds.value.add(v.id));
    }
}

/**
 * Set up Echo channel for real-time updates
 */
function setupEchoChannel(): void {
    // Check if Echo is available (may not be in all environments)
    if (typeof window !== 'undefined' && (window as any).Echo) {
        echoChannel = (window as any).Echo.private(`audit.${props.audit.id}`);

        // Handle verification completed events
        echoChannel.listen('.verification.completed', (data: any) => {
            // Update the verification in the list
            const index = verifications.value.findIndex(
                (v) => v.id === data.verification.id,
            );
            if (index !== -1) {
                verifications.value[index] = data.verification;
            }
            // Refresh stats
            loadStats();
        });

        // Handle connection locked events
        echoChannel.listen('.connection.locked', (data: any) => {
            const index = verifications.value.findIndex(
                (v) => v.id === data.verification_id,
            );
            if (index !== -1) {
                verifications.value[index].is_locked = true;
                verifications.value[index].locked_by = data.locked_by;
                verifications.value[index].locked_at = data.locked_at;
            }
        });

        // Handle connection unlocked events
        echoChannel.listen('.connection.unlocked', (data: any) => {
            const index = verifications.value.findIndex(
                (v) => v.id === data.verification_id,
            );
            if (index !== -1) {
                verifications.value[index].is_locked = false;
                verifications.value[index].locked_by = null;
                verifications.value[index].locked_at = null;
            }
        });
    }
}

/**
 * Clean up Echo channel
 */
function cleanupEchoChannel(): void {
    if (echoChannel) {
        echoChannel.stopListening('.verification.completed');
        echoChannel.stopListening('.connection.locked');
        echoChannel.stopListening('.connection.unlocked');
        (window as any).Echo?.leave(`audit.${props.audit.id}`);
        echoChannel = null;
    }
}

// Lifecycle hooks
onMounted(() => {
    loadVerifications();
    setupEchoChannel();
});

onUnmounted(() => {
    cleanupEchoChannel();
});
</script>

<template>
    <Head :title="`Execute: ${audit.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 md:gap-6">
            <!-- Header -->
            <div
                data-tour="audit-header"
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="flex items-start gap-4">
                    <Link
                        :href="AuditController.show.url(audit.id)"
                        class="mt-1"
                    >
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8 min-h-11 min-w-11 md:size-8 md:min-h-8 md:min-w-8 lg:size-8"
                        >
                            <ArrowLeft class="size-4" />
                        </Button>
                    </Link>
                    <div>
                        <HeadingSmall
                            :title="audit.name"
                            description="Verify connections against implementation specs"
                        />
                        <div class="mt-2 flex gap-2">
                            <span :class="getStatusBadgeClass(audit.status)">
                                {{ audit.status_label }}
                            </span>
                            <Badge variant="outline">{{
                                audit.type_label
                            }}</Badge>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        id="audit-refresh-btn"
                        variant="outline"
                        class="min-h-11 min-w-11 lg:min-h-9 lg:min-w-0"
                        @click="loadVerifications"
                    >
                        <RefreshCw class="size-4 lg:mr-1 lg:size-3.5" />
                        <span class="hidden lg:inline">Refresh</span>
                    </Button>
                </div>
            </div>

            <!-- Progress Card -->
            <Card id="progress-card" data-tour="progress-bar">
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <ClipboardCheck class="size-5" />
                        Verification Progress
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap gap-4 md:gap-6">
                        <div class="flex items-center gap-2">
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >Total:</span
                            >
                            <Badge variant="secondary" class="text-base">{{
                                stats.total
                            }}</Badge>
                        </div>
                        <div class="flex items-center gap-2">
                            <CheckCircle class="size-4 text-green-600" />
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >Verified:</span
                            >
                            <Badge class="bg-green-600 text-base">{{
                                stats.verified
                            }}</Badge>
                        </div>
                        <div class="flex items-center gap-2">
                            <XCircle class="size-4 text-red-600" />
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >Discrepant:</span
                            >
                            <Badge variant="destructive" class="text-base">{{
                                stats.discrepant
                            }}</Badge>
                        </div>
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="size-4 text-yellow-600" />
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >Pending:</span
                            >
                            <Badge variant="warning" class="text-base">{{
                                stats.pending
                            }}</Badge>
                        </div>
                    </div>

                    <!-- Progress bar -->
                    <div v-if="stats.total > 0" class="mt-4">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground"
                                >Completion</span
                            >
                            <span class="font-medium"
                                >{{
                                    stats.progress_percentage.toFixed(1)
                                }}%</span
                            >
                        </div>
                        <div
                            class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-secondary"
                        >
                            <div
                                class="h-full bg-green-600 transition-all duration-300"
                                :style="{
                                    width: `${stats.progress_percentage}%`,
                                }"
                            />
                        </div>
                    </div>

                    <!-- Completion message -->
                    <div
                        v-if="stats.pending === 0 && stats.total > 0"
                        class="mt-4 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400"
                    >
                        <CheckCircle class="size-4 shrink-0" />
                        <span
                            >All connections have been verified! The audit is
                            now complete.</span
                        >
                    </div>
                </CardContent>
            </Card>

            <!-- Filter Controls - Stacked on tablet, inline on desktop -->
            <div
                id="filter-controls"
                data-tour="filters"
                class="flex flex-col gap-3 md:gap-4 lg:flex-row lg:flex-wrap lg:items-center"
            >
                <!-- Comparison Filter -->
                <div class="flex items-center gap-2">
                    <span
                        class="text-sm font-medium whitespace-nowrap text-muted-foreground"
                        >Comparison:</span
                    >
                    <select
                        v-model="comparisonStatusFilter"
                        :class="selectClass"
                        class="w-full lg:w-40"
                        @change="handleFilterChange"
                    >
                        <option value="">All</option>
                        <option
                            v-for="type in discrepancy_types"
                            :key="type.value"
                            :value="type.value"
                        >
                            {{ type.label }}
                        </option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="flex items-center gap-2">
                    <span
                        class="text-sm font-medium whitespace-nowrap text-muted-foreground"
                        >Status:</span
                    >
                    <select
                        v-model="verificationStatusFilter"
                        :class="selectClass"
                        class="w-full lg:w-40"
                        @change="handleFilterChange"
                    >
                        <option value="">All</option>
                        <option
                            v-for="status in verification_statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                </div>

                <!-- Search Input -->
                <div class="flex items-center gap-2">
                    <Input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search device/port..."
                        class="h-11 w-full lg:h-9 lg:w-48"
                        @keyup.enter="handleSearch"
                    />
                    <Button
                        variant="outline"
                        class="min-h-11 min-w-11 lg:min-h-9 lg:min-w-0"
                        @click="handleSearch"
                    >
                        Search
                    </Button>
                </div>

                <!-- Clear Filters -->
                <Button
                    v-if="hasActiveFilters"
                    variant="ghost"
                    class="min-h-11 lg:min-h-9"
                    @click="clearFilters"
                >
                    Clear Filters
                </Button>

                <!-- Bulk Actions -->
                <div
                    data-tour="bulk-verify"
                    class="flex items-center gap-2 lg:ml-auto"
                >
                    <BulkVerifyButton
                        v-if="selectedIds.size > 0"
                        :selected-count="selectedIds.size"
                        :matched-count="matchedSelectedCount"
                        @bulk-verify="handleBulkVerify"
                    />
                </div>
            </div>

            <!-- Loading State -->
            <div
                v-if="isLoading"
                class="flex items-center justify-center py-12"
            >
                <div class="flex flex-col items-center gap-4">
                    <Spinner class="size-8" />
                    <p class="text-sm text-muted-foreground">
                        Loading verification items...
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
                        Failed to load verifications
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ loadError }}
                    </p>
                </div>
                <Button
                    variant="outline"
                    class="min-h-11 lg:min-h-9"
                    @click="loadVerifications"
                    >Try Again</Button
                >
            </div>

            <!-- Verification Table -->
            <div id="verification-table" data-tour="verification-table">
                <ConnectionVerificationTable
                    v-if="!isLoading && !loadError"
                    :verifications="verifications"
                    :selected-ids="selectedIds"
                    :discrepancy-types="discrepancy_types"
                    @toggle-selection="toggleSelection"
                    @toggle-all="toggleAllSelection"
                    @open-action="openActionDialog"
                />
            </div>

            <!-- Empty State -->
            <div
                v-if="!isLoading && !loadError && verifications.length === 0"
                class="flex flex-col items-center justify-center py-12 text-center"
            >
                <ClipboardCheck class="mb-4 size-12 text-muted-foreground/50" />
                <h3 class="text-lg font-medium">No verification items found</h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        hasActiveFilters
                            ? 'Try adjusting your filters.'
                            : 'No connections to verify for this audit.'
                    }}
                </p>
            </div>

            <!-- Pagination - Touch-friendly controls -->
            <div
                v-if="!isLoading && !loadError && lastPage > 1"
                class="flex flex-col items-center justify-between gap-4 sm:flex-row"
            >
                <p class="text-sm text-muted-foreground">
                    Showing page {{ currentPage }} of {{ lastPage }} ({{
                        total
                    }}
                    items)
                </p>
                <div class="flex gap-3">
                    <Button
                        variant="outline"
                        class="min-h-11 min-w-20 lg:min-h-9"
                        :disabled="currentPage <= 1"
                        @click="
                            currentPage--;
                            loadVerifications();
                        "
                    >
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        class="min-h-11 min-w-20 lg:min-h-9"
                        :disabled="currentPage >= lastPage"
                        @click="
                            currentPage++;
                            loadVerifications();
                        "
                    >
                        Next
                    </Button>
                </div>
            </div>

            <!-- Verification Action Dialog -->
            <VerificationActionDialog
                v-model:open="actionDialogOpen"
                :verification="actionDialogVerification"
                :discrepancy-types="discrepancy_types"
                :is-loading="isActionLoading"
                :error="actionError"
                @submit="handleVerificationAction"
            />
        </div>

        <!-- Feature Tour for Connection Audit Execution -->
        <FeatureTour
            context-key="audits.execute.connection"
            :auto-start="true"
        />
    </AppLayout>
</template>
